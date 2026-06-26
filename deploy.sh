#!/usr/bin/env bash
# OSMS redeploy script for Hostinger (run from the project root via SSH).
set -e

echo "→ Pulling latest..."
git pull origin main

echo "→ Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

# Build front-end assets if Node is available; otherwise skip (upload public/build manually).
if command -v npm >/dev/null 2>&1; then
  echo "→ Building front-end assets..."
  npm install
  npm run build
else
  echo "! npm not found — skipping asset build (ensure public/build is uploaded)."
fi

echo "→ Running migrations..."
php artisan migrate --force

echo "→ Refreshing caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✓ Deploy complete."
