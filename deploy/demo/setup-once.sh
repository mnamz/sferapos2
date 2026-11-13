#!/bin/bash
# One-time root prep on sfera1 so demo.sh never needs a password again.
#   sudo bash setup-once.sh <commit-sha>
# Self-contained (fetches its files from the public repo at that commit), so it
# can be pasted into the Webdock dashboard's script runner as-is.
#
# Optional env: GH_DEMO_PUBKEY (GitHub Actions key, forced to `demo.sh gate`),
#               ADMIN_PUBKEY (a normal login key for mnamz)
set -euo pipefail

[[ $EUID -eq 0 ]] || { echo "run as root" >&2; exit 1; }
REF=${1:?usage: setup-once.sh <commit-sha>}
RAW="https://raw.githubusercontent.com/mnamz/sferapos2/$REF/deploy/demo"

OWNER=mnamz
HOME_DIR=$(getent passwd "$OWNER" | cut -d: -f6)
GROUP=$(id -gn "$OWNER")
ROOT=/var/www/demos

echo "==> root helper + sudoers"
curl -fsSL "$RAW/sfera-demo-vhost" -o /tmp/sfera-demo-vhost
install -o root -g root -m 755 /tmp/sfera-demo-vhost /usr/local/sbin/sfera-demo-vhost
rm -f /tmp/sfera-demo-vhost
echo "$OWNER ALL=(root) NOPASSWD: /usr/local/sbin/sfera-demo-vhost" > /etc/sudoers.d/sfera-demo
chmod 440 /etc/sudoers.d/sfera-demo
visudo -cf /etc/sudoers.d/sfera-demo
[[ -S /run/php/php8.3-fpm.sock ]] || echo "WARNING: /run/php/php8.3-fpm.sock missing; fix PHP_SOCK in the helper"

echo "==> dirs + demo.sh"
install -d -o "$OWNER" -g www-data -m 755 "$ROOT" "$ROOT/_meta"
install -d -o "$OWNER" -g "$GROUP" -m 755 "$HOME_DIR/bin"
install -d -o "$OWNER" -g "$GROUP" -m 700 "$HOME_DIR/.config" "$HOME_DIR/.config/sfera-demo" "$HOME_DIR/.ssh"
curl -fsSL "$RAW/demo.sh" -o "$HOME_DIR/bin/demo.sh"
chown "$OWNER:$GROUP" "$HOME_DIR/bin/demo.sh"
chmod 755 "$HOME_DIR/bin/demo.sh"

echo "==> nightly expiry cron for $OWNER"
CRON="15 3 * * * $HOME_DIR/bin/demo.sh expire >> $HOME_DIR/sfera-demo.log 2>&1"
( crontab -u "$OWNER" -l 2>/dev/null | grep -v 'demo.sh expire' || true; echo "$CRON" ) | crontab -u "$OWNER" -

AK="$HOME_DIR/.ssh/authorized_keys"
touch "$AK"
if [[ -n ${GH_DEMO_PUBKEY:-} ]] && ! grep -qF "$GH_DEMO_PUBKEY" "$AK"; then
    echo "command=\"$HOME_DIR/bin/demo.sh gate\",no-port-forwarding,no-X11-forwarding,no-agent-forwarding,no-pty $GH_DEMO_PUBKEY" >> "$AK"
fi
if [[ -n ${ADMIN_PUBKEY:-} ]] && ! grep -qF "$ADMIN_PUBKEY" "$AK"; then
    echo "$ADMIN_PUBKEY" >> "$AK"
fi
chown "$OWNER:$GROUP" "$AK"
chmod 600 "$AK"

echo "Done. Next, as $OWNER: write $HOME_DIR/.config/sfera-demo/env (CF_API_TOKEN, CF_ZONE_ID, DEMO_IP)."
