#!/bin/bash
# One-time root prep on the sfera VPS so demo.sh never needs a password again.
# Run from a checkout of this branch:  sudo bash deploy/demo/setup-once.sh
#
# Reads (prompted if unset): MARIADB_ROOT_PW, CF_API_TOKEN, CF_ZONE_ID
# Optional: GH_DEMO_PUBKEY  - public key GitHub Actions uses (forced to `demo.sh gate`)
set -euo pipefail

[[ $EUID -eq 0 ]] || { echo "run with sudo" >&2; exit 1; }

OWNER=mnamz
HOME_DIR=$(getent passwd "$OWNER" | cut -d: -f6)
ROOT=/var/www/demos
CONF="$HOME_DIR/.config/sfera-demo"
SRC=$(cd "$(dirname "$0")" && pwd)
APP=/var/www/service-pos-ph.sfera.my
DEMO_IP=45.136.71.25
as_owner() { sudo -u "$OWNER" -H "$@"; }

ask() { [[ -n ${!1:-} ]] || { read -r ${3:+-s} -p "$2: " "$1"; echo; }; }
ask MARIADB_ROOT_PW "MariaDB root password" secret
ask CF_API_TOKEN "Cloudflare API token (Zone.DNS:Edit on sfera.my)" secret
ask CF_ZONE_ID "Cloudflare zone ID for sfera.my"

echo "==> root helper + sudoers"
install -o root -g root -m 755 "$SRC/sfera-demo-vhost" /usr/local/sbin/sfera-demo-vhost
echo "$OWNER ALL=(root) NOPASSWD: /usr/local/sbin/sfera-demo-vhost" > /etc/sudoers.d/sfera-demo
chmod 440 /etc/sudoers.d/sfera-demo
visudo -cf /etc/sudoers.d/sfera-demo

echo "==> $ROOT"
install -d -o "$OWNER" -g www-data -m 755 "$ROOT" "$ROOT/_meta"

echo "==> MariaDB user demo_provisioner (only demo_* databases)"
DB_PASS=$(openssl rand -hex 24)
MYSQL_PWD="$MARIADB_ROOT_PW" mysql -uroot <<SQL
CREATE USER IF NOT EXISTS 'demo_provisioner'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER 'demo_provisioner'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`demo\\_%\`.* TO 'demo_provisioner'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "==> $CONF"
install -d -o "$OWNER" -g "$OWNER" -m 700 "$CONF"
umask 077
cat > "$CONF/env" <<ENV
CF_API_TOKEN=$CF_API_TOKEN
CF_ZONE_ID=$CF_ZONE_ID
DEMO_IP=$DEMO_IP
DEMO_DB_USER=demo_provisioner
DEMO_DB_PASS=$DB_PASS
ENV
printf '[client]\nuser=demo_provisioner\npassword=%s\nhost=127.0.0.1\n' "$DB_PASS" > "$CONF/my.cnf"
chown "$OWNER:$OWNER" "$CONF/env" "$CONF/my.cnf"
umask 022

echo "==> template checkout (same GitHub remote as $APP)"
if [[ ! -d $ROOT/_template/.git ]]; then
    as_owner git clone -q "$APP" "$ROOT/_template"
    as_owner git -C "$ROOT/_template" remote set-url origin "$(as_owner git -C "$APP" remote get-url origin)"
fi

echo "==> nightly expiry cron for $OWNER"
CRON="15 3 * * * $ROOT/_template/deploy/demo/demo.sh expire >> $HOME_DIR/sfera-demo.log 2>&1"
( crontab -u "$OWNER" -l 2>/dev/null | grep -v 'deploy/demo/demo.sh expire' || true; echo "$CRON" ) | crontab -u "$OWNER" -

if [[ -n ${GH_DEMO_PUBKEY:-} ]]; then
    echo "==> GitHub Actions key (restricted to demo.sh gate)"
    AK="$HOME_DIR/.ssh/authorized_keys"
    grep -qF "$GH_DEMO_PUBKEY" "$AK" 2>/dev/null || \
        echo "command=\"$ROOT/_template/deploy/demo/demo.sh gate\",no-port-forwarding,no-X11-forwarding,no-agent-forwarding,no-pty $GH_DEMO_PUBKEY" >> "$AK"
fi

echo "Done. Try:  $ROOT/_template/deploy/demo/demo.sh create test 1"
