#!/bin/bash
# Throwaway phone-service POS demos at https://demo-<name>.sfera.my
# Runs as mnamz on sfera1 (one-time root prep: setup-once.sh). The box is too
# small to build assets, so create reads a prebuilt app bundle (.tar.gz) on
# stdin; the GitHub workflow builds and pipes it in.
#
#   demo.sh create <name> [days] < app.tgz   A record + app + SQLite + cert (default 14 days)
#   demo.sh destroy <name>         remove everything create made
#   demo.sh list                   show live demos and their expiry
#   demo.sh expire                 destroy demos past expiry (nightly cron)
#   demo.sh gate                   SSH forced command for the GitHub Actions key
set -euo pipefail

CONF="${SFERA_DEMO_CONF:-$HOME/.config/sfera-demo}"
# shellcheck source=/dev/null
source "$CONF/env" # CF_API_TOKEN CF_ZONE_ID DEMO_IP

ROOT=/var/www/demos
META="$ROOT/_meta"
DOMAIN=sfera.my
DEFAULT_DAYS=14
MAX_DAYS=90
CF_API="https://api.cloudflare.com/client/v4/zones/$CF_ZONE_ID/dns_records"

die() { echo "error: $*" >&2; exit 1; }
log() { echo "==> $*"; }

valid_name() { [[ $1 =~ ^[a-z0-9]([a-z0-9-]{0,22}[a-z0-9])?$ ]]; }
host_of() { echo "demo-$1.$DOMAIN"; }
json() { php -r '$d = json_decode(stream_get_contents(STDIN), true); echo eval("return $argv[1];");' "$1" 2>/dev/null; }

cf() {
    local method=$1 url=$2 body=${3:-}
    curl -4 -sS -X "$method" "$url" \
        -H "Authorization: Bearer $CF_API_TOKEN" \
        -H 'Content-Type: application/json' \
        ${body:+--data "$body"}
}

dns_create() {
    local host=$1 expires=$2 resp id
    resp=$(cf POST "$CF_API" "{\"type\":\"A\",\"name\":\"$host\",\"content\":\"$DEMO_IP\",\"ttl\":300,\"proxied\":false,\"comment\":\"sfera-demo, expires $expires\"}")
    id=$(json '$d["success"] ? $d["result"]["id"] : ""' <<<"$resp")
    if [[ -z $id ]]; then
        # Already there: reuse it only if an earlier demo run made it.
        id=$(dns_find_ours "$host")
    fi
    [[ -n $id ]] || die "Cloudflare refused the A record: $resp"
    echo "$id"
}

# Record id for $host, but only if its comment marks it as a demo record.
dns_find_ours() {
    cf GET "$CF_API?type=A&name=$1" | json 'str_starts_with($d["result"][0]["comment"] ?? "", "sfera-demo") ? $d["result"][0]["id"] : ""'
}

dns_delete() {
    local id
    id=$(dns_find_ours "$1")
    [[ -n $id ]] && cf DELETE "$CF_API/$id" >/dev/null || true
}

wait_for_dns() {
    local host=$1 ns
    ns=$(dig +short NS "$DOMAIN" | head -1)
    for _ in $(seq 1 30); do
        [[ $(dig +short "@$ns" A "$host") == "$DEMO_IP" ]] && return 0
        sleep 2
    done
    die "$host never resolved to $DEMO_IP on $ns"
}

write_env() {
    local dir=$1 name=$2 host=$3 expires=$4
    cat > "$dir/.env" <<ENV
APP_NAME="Demo Phone Repair"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://$host

LOG_CHANNEL=single
LOG_LEVEL=warning

DB_CONNECTION=sqlite
DB_DATABASE=$dir/database/database.sqlite

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
MAIL_MAILER=log

MYINVOIS_ENABLED=false
TANGENT_ENABLED=false

DEMO_MODE=true
DEMO_LOGIN_EMAIL=demo@sfera.my
DEMO_LOGIN_PASSWORD=demo1234
DEMO_EXPIRES_AT=$expires
ENV
    chmod 640 "$dir/.env"
}

cmd_create() {
    local name=${1:-} days=${2:-$DEFAULT_DAYS}
    valid_name "$name" || die "name must be 1-24 chars of a-z, 0-9, '-' (got '$name')"
    [[ $days =~ ^[0-9]+$ && $days -ge 1 && $days -le $MAX_DAYS ]] || die "days must be 1-$MAX_DAYS"

    [[ ! -t 0 ]] || die "pipe the app bundle in: demo.sh create $name $days < app.tgz"

    local host dir expires record
    host=$(host_of "$name"); dir="$ROOT/$host"
    [[ -e $dir || -e $META/$name ]] && die "$host already exists (destroy it first)"
    [[ -e /var/www/$host || -e /etc/nginx/sites-available/$host ]] && die "$host is a real site, pick another name"
    expires=$(date -d "+$days days" +%F)

    log "DNS: $host -> $DEMO_IP"
    record=$(dns_create "$host" "$expires")
    printf 'HOST=%s\nRECORD=%s\nEXPIRES=%s\nCREATED=%s\n' "$host" "$record" "$expires" "$(date +%F)" > "$META/$name"

    log "unpacking app to $dir"
    mkdir "$dir"
    tar -C "$dir" -xzf -
    [[ -f $dir/artisan && -f $dir/public/build/manifest.json ]] || die "bundle is missing artisan or public/build"

    write_env "$dir" "$name" "$host" "$expires"
    touch "$dir/database/database.sqlite"
    (
        cd "$dir"
        php artisan key:generate --force -q
        php artisan migrate --force --seed --seeder=DemoSeeder -q
        php artisan storage:link -q
        php artisan optimize -q
    ) 2> >(grep -v '^PHP Warning' >&2)

    wait_for_dns "$host"
    log "nginx + certificate"
    sudo -n /usr/local/sbin/sfera-demo-vhost create "$host"

    curl -fsS --retry 3 -o /dev/null "https://$host/login" || die "https://$host/login is not answering"
    cat <<DONE

Demo ready: https://$host
Login:      demo@sfera.my / demo1234 (shown on the login page)
Expires:    $expires
DONE
}

cmd_destroy() {
    local name=${1:-}
    valid_name "$name" || die "invalid name '$name'"
    local HOST
    HOST=$(host_of "$name")
    # shellcheck source=/dev/null
    [[ -f $META/$name ]] && source "$META/$name"

    log "removing $HOST"
    sudo -n /usr/local/sbin/sfera-demo-vhost destroy "$HOST"
    dns_delete "$HOST"
    rm -f "$META/$name"
    echo "Destroyed $HOST"
}

cmd_list() {
    local f found=
    for f in "$META"/*; do
        [[ -f $f ]] || continue
        found=1
        (source "$f"; printf '%-40s created %s  expires %s\n' "https://$HOST" "$CREATED" "$EXPIRES")
    done
    [[ -n $found ]] || echo "No demos running."
}

cmd_expire() {
    local f today
    today=$(date +%F)
    for f in "$META"/*; do
        [[ -f $f ]] || continue
        local EXPIRES
        EXPIRES=$(source "$f"; echo "$EXPIRES")
        if [[ $EXPIRES < $today || $EXPIRES == "$today" ]]; then
            (cmd_destroy "$(basename "$f")") || echo "failed to expire $(basename "$f")" >&2
        fi
    done
}

# Forced command for the GitHub Actions key: only demo actions, never a shell.
cmd_gate() {
    read -r -a args <<<"${SSH_ORIGINAL_COMMAND:-}"
    case ${args[0]:-} in
        create|destroy|list) exec "$0" "${args[@]}" ;;
        *) die "allowed: create <name> [days] | destroy <name> | list" ;;
    esac
}

mkdir -p "$META"
case ${1:-} in
    create) shift; cmd_create "$@" ;;
    destroy) shift; cmd_destroy "$@" ;;
    list) cmd_list ;;
    expire) cmd_expire ;;
    gate) cmd_gate ;;
    *) sed -n '2,10p' "$0"; exit 1 ;;
esac
