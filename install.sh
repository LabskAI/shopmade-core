#!/usr/bin/env bash
#
# Install Shopmade core on a Linux server with Docker (Ubuntu or Debian; run as root).
#
#   curl -fsSL https://raw.githubusercontent.com/LabskAI/shopmade-core/main/install.sh | sudo bash -s -- \
#       --domain site.example.com --owner-email you@example.com --packages jobs,invoicing
#
# With --domain, Caddy serves the site over HTTPS on ports 80 and 443 (point the domain's DNS at this server first).
# Without it, the site is plain HTTP on port 8080 of this server, for a quick try.
# Run it again to update: it pulls the code, rebuilds the image and keeps your settings, data and content.
#
set -euo pipefail

DIR=/opt/shopmade
REF=main
REPO=https://github.com/LabskAI/shopmade-core.git
DOMAIN=""
OWNER_EMAIL=""
OWNER_NAME="Owner"
PACKAGES=""
ORG_NAME=""

usage() {
    sed -n '3,10p' "$0" 2>/dev/null | sed 's/^# \{0,1\}//'
    cat <<'TXT'
Options:
  --domain HOST        Serve https://HOST with an automatic certificate
  --owner-email EMAIL  Create the first owner account and print its password
  --owner-name NAME    Owner's name (default: Owner)
  --org-name NAME      Business name shown in the admin (default: Shopmade)
  --packages LIST      Public packages to include, e.g. jobs,invoicing,shop,crm,ai
  --dir PATH           Install folder (default: /opt/shopmade)
  --ref REF            Git branch or tag of shopmade-core (default: main)
TXT
}

while [ $# -gt 0 ]; do
    case "$1" in
        --domain) DOMAIN="$2"; shift 2 ;;
        --owner-email) OWNER_EMAIL="$2"; shift 2 ;;
        --owner-name) OWNER_NAME="$2"; shift 2 ;;
        --org-name) ORG_NAME="$2"; shift 2 ;;
        --packages) PACKAGES="$2"; shift 2 ;;
        --dir) DIR="$2"; shift 2 ;;
        --ref) REF="$2"; shift 2 ;;
        --repo) REPO="$2"; shift 2 ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage >&2; exit 1 ;;
    esac
done

say() { printf '\n\033[1m==> %s\033[0m\n' "$*"; }

if [ "$(id -u)" -ne 0 ]; then
    echo "Run this as root (sudo bash install.sh ...)." >&2
    exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
    say "Installing Docker"
    curl -fsSL https://get.docker.com | sh
fi

if ! docker compose version >/dev/null 2>&1; then
    echo "Docker is installed but the compose plugin is missing. Install docker-compose-plugin and run this again." >&2
    exit 1
fi

if ! command -v git >/dev/null 2>&1 || ! command -v openssl >/dev/null 2>&1; then
    say "Installing git and openssl"
    apt-get update -qq && apt-get install -y -qq git openssl ca-certificates
fi

mkdir -p "$DIR"
SRC="$DIR/src"

if [ -d "$SRC/.git" ]; then
    say "Updating the code to $REF"
    git -C "$SRC" fetch --depth 1 origin "$REF"
    git -C "$SRC" checkout -q --force FETCH_HEAD
else
    say "Downloading shopmade-core ($REF)"
    git clone -q --depth 1 --branch "$REF" "$REPO" "$SRC"
fi

# Short names become labskai/shopmade-<name>; a full vendor/name is used as is.
PACKAGE_NAMES=""
for name in $(echo "$PACKAGES" | tr ',' ' '); do
    case "$name" in
        */*) PACKAGE_NAMES="$PACKAGE_NAMES $name" ;;
        *) PACKAGE_NAMES="$PACKAGE_NAMES labskai/shopmade-$name" ;;
    esac
done
PACKAGE_NAMES="${PACKAGE_NAMES# }"

ENV_FILE="$DIR/.env"

set_env() {
    if grep -q "^$1=" "$ENV_FILE"; then
        sed -i "s|^$1=.*|$1=$2|" "$ENV_FILE"
    else
        echo "$1=$2" >> "$ENV_FILE"
    fi
}

if [ ! -f "$ENV_FILE" ]; then
    say "Writing settings to $ENV_FILE"
    umask 077
    {
        echo "# Shopmade settings for docker compose. Keep this file: APP_KEY and DB_PASSWORD must not change."
        echo "APP_KEY=base64:$(openssl rand -base64 32)"
        echo "DB_PASSWORD=$(openssl rand -hex 24)"
    } > "$ENV_FILE"
    umask 022
fi

# Options given on this run win; options left out keep what the last run saved.
if [ -n "$DOMAIN" ]; then
    set_env SHOPMADE_DOMAIN "$DOMAIN"
fi
if [ -n "$PACKAGES" ]; then
    set_env SHOPMADE_PACKAGES "\"$PACKAGE_NAMES\""
fi
if [ -n "$ORG_NAME" ]; then
    set_env APP_ORG_NAME "\"$ORG_NAME\""
fi

DOMAIN="$(grep '^SHOPMADE_DOMAIN=' "$ENV_FILE" | cut -d= -f2- || true)"

if [ -n "$DOMAIN" ]; then
    set_env APP_URL "https://$DOMAIN"
    set_env SESSION_SECURE_COOKIE true
    set_env SHOPMADE_BIND 127.0.0.1
else
    HOST_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
    set_env APP_URL "http://${HOST_IP:-localhost}:8080"
    set_env SESSION_SECURE_COOKIE false
    set_env SHOPMADE_BIND 0.0.0.0
fi

# $DIR/compose runs docker compose on this install from anywhere, e.g. `/opt/shopmade/compose logs -f app`.
cat > "$DIR/compose" <<SH
#!/bin/sh
exec docker compose --project-name shopmade --env-file "$ENV_FILE" -f "$SRC/docker/server/compose.yaml" ${DOMAIN:+--profile https} "\$@"
SH
chmod +x "$DIR/compose"

compose() {
    "$DIR/compose" "$@"
}

say "Building and starting (the first build takes a few minutes)"
compose up -d --build --remove-orphans

say "Waiting for the site to answer"
for _ in $(seq 1 90); do
    if compose exec -T app php -r 'exit(@file_get_contents("http://127.0.0.1:8080/up") === false ? 1 : 0);' >/dev/null 2>&1; then
        READY=1
        break
    fi
    sleep 2
done

if [ "${READY:-0}" != "1" ]; then
    echo "The site did not come up. Check the logs with: $DIR/compose logs app" >&2
    exit 1
fi

if [ -n "$OWNER_EMAIL" ]; then
    say "Creating the owner account"
    compose exec -T app php artisan user:create-owner "$OWNER_EMAIL" "$OWNER_NAME" || true
fi

URL="$(grep '^APP_URL=' "$ENV_FILE" | cut -d= -f2-)"

say "Shopmade is running"
cat <<TXT
  Admin:          $URL/login
  Public site:    $URL/site
  Claude (MCP):   add $URL/mcp/agents as a custom connector and sign in as the owner
  Settings:       $ENV_FILE
  Logs:           $DIR/compose logs -f app
  Update:         run this script again
TXT

if [ -z "$OWNER_EMAIL" ]; then
    echo "  Owner account:  $DIR/compose exec app php artisan user:create-owner you@example.com \"Your Name\""
fi
