#!/usr/bin/env bash
# Läuft bei jedem Ploi-Deploy nach "git pull origin main". Aufruf: bash deploy/ploi-deploy.sh
set -e
DOMAIN="kanzler.stache-advisory.online"
ROOT="/home/ploi/$DOMAIN"
CONF="/home/ploi/kanzler-config"
DATA="/home/ploi/kanzler-data"

mkdir -p "$CONF" "$DATA"
chmod 700 "$CONF" "$DATA"

# Config beim ersten Deploy aus der Vorlage anlegen, danach nie überschreiben
if [ ! -f "$CONF/config.php" ]; then
  cp "$ROOT/config.example.php" "$CONF/config.php"
  chmod 600 "$CONF/config.php"
  echo "HINWEIS: $CONF/config.php aus Vorlage angelegt. Datenbankzugang eintragen!"
fi
# Config an ihren Platz (außerhalb von public/)
cp "$CONF/config.php" "$ROOT/src/config.php"
chmod 600 "$ROOT/src/config.php"

# PHP-FPM neu laden, sonst zeigt OPcache alten Code
( echo "" | sudo -S service php8.3-fpm reload ) 2>/dev/null || sudo -n systemctl reload php8.3-fpm || true

echo "Deploy fertig: $(date '+%Y-%m-%d %H:%M:%S') $(git -C "$ROOT" rev-parse --short HEAD)"
