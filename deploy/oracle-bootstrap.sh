#!/usr/bin/env bash
# First-time installation for an Oracle Cloud Always Free Ubuntu VM.
# Run as root only after goldentor.hu and www.goldentor.hu point to this VM.
set -Eeuo pipefail

readonly APP_USER="goldentor"
readonly APP_DIR="/opt/goldentor"
readonly SERVICE_NAME="goldentor"

DOMAIN=""
EMAIL=""
REPOSITORY="https://github.com/Androw96/GoldenTor.git"
BRANCH="reorg"

usage() {
  printf '%s\n' "Usage: sudo bash deploy/oracle-bootstrap.sh --domain goldentor.hu --email owner@example.com [--repository URL] [--branch reorg]"
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain) DOMAIN="${2:-}"; shift 2 ;;
    --email) EMAIL="${2:-}"; shift 2 ;;
    --repository) REPOSITORY="${2:-}"; shift 2 ;;
    --branch) BRANCH="${2:-}"; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    *) printf 'Unknown option: %s\n' "$1" >&2; usage >&2; exit 2 ;;
  esac
done

if [[ ${EUID} -ne 0 || -z "$DOMAIN" || -z "$EMAIL" ]]; then
  usage >&2
  exit 2
fi

if [[ ! "$DOMAIN" =~ ^[A-Za-z0-9.-]+\.[A-Za-z]{2,}$ ]] || [[ ! "$EMAIL" =~ ^[^[:space:]@]+@[^[:space:]@]+\.[^[:space:]@]+$ ]]; then
  printf '%s\n' 'A domain or e-mail format is invalid.' >&2
  exit 2
fi

apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
  certbot curl git nginx openssl python3 python3-certbot-nginx ufw

if ! id "$APP_USER" >/dev/null 2>&1; then
  adduser --system --group --home "$APP_DIR" --shell /usr/sbin/nologin "$APP_USER"
fi

if [[ -d "$APP_DIR/.git" ]]; then
  git -C "$APP_DIR" fetch --prune origin
  git -C "$APP_DIR" switch "$BRANCH"
  git -C "$APP_DIR" pull --ff-only origin "$BRANCH"
else
  if [[ -d "$APP_DIR" ]]; then
    rmdir "$APP_DIR" 2>/dev/null || {
      printf '%s\n' "${APP_DIR} exists but is not a Golden Tor checkout. Stop to protect its contents." >&2
      exit 1
    }
  fi
  git clone --branch "$BRANCH" --single-branch "$REPOSITORY" "$APP_DIR"
fi

install -d -o "$APP_USER" -g "$APP_USER" -m 0750 "$APP_DIR/data/backups"
chown -R root:"$APP_USER" "$APP_DIR"
chmod -R u=rwX,g=rX,o= "$APP_DIR"
chown -R "$APP_USER":"$APP_USER" "$APP_DIR/data"

if [[ ! -f "$APP_DIR/.env" ]]; then
  umask 077
  admin_key="$(openssl rand -hex 32)"
  cat > "$APP_DIR/.env" <<EOF
GOLDENTOR_HOST=127.0.0.1
GOLDENTOR_PORT=4174
GOLDENTOR_PUBLIC_URL=https://${DOMAIN}
GOLDENTOR_ADMIN_KEY=${admin_key}
CONTACT_RECIPIENT=info@${DOMAIN}
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASSWORD=
SMTP_FROM=info@${DOMAIN}
EOF
  chown "$APP_USER":"$APP_USER" "$APP_DIR/.env"
  chmod 0600 "$APP_DIR/.env"
fi

install -o root -g root -m 0644 "$APP_DIR/deploy/goldentor.service" "/etc/systemd/system/${SERVICE_NAME}.service"
sed "s/__DOMAIN__/${DOMAIN}/g" "$APP_DIR/deploy/goldentor.nginx.conf" > "/etc/nginx/sites-available/${SERVICE_NAME}"
ln -sfn "/etc/nginx/sites-available/${SERVICE_NAME}" "/etc/nginx/sites-enabled/${SERVICE_NAME}"
rm -f /etc/nginx/sites-enabled/default

nginx -t
systemctl daemon-reload
systemctl enable --now "${SERVICE_NAME}.service"
systemctl enable --now nginx
systemctl reload nginx

ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

certbot --nginx --non-interactive --agree-tos --redirect --email "$EMAIL" \
  -d "$DOMAIN" -d "www.${DOMAIN}"

curl --fail --silent --show-error "https://${DOMAIN}/api/health" >/dev/null
printf '%s\n' "Golden Tor is running at https://${DOMAIN}. The generated admin key is stored only in ${APP_DIR}/.env."
