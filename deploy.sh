#!/usr/bin/env bash
# OSMS redeploy script for Hostinger (run from the project root via SSH).
set -e

# Pre-flight checks: verify critical env vars before deployment.
echo "→ Checking deployment readiness..."
if [ "$APP_ENV" != "production" ]; then
  echo "⚠ APP_ENV is '$APP_ENV', not 'production'. Update .env before deploy."
fi
if [ "$APP_DEBUG" != "false" ]; then
  echo "⚠ APP_DEBUG is '$APP_DEBUG', not 'false'. Stack traces will leak in production."
fi
if [ -z "$RAZORPAY_KEY" ] || [ -z "$RAZORPAY_SECRET" ]; then
  echo "! RAZORPAY_KEY and RAZORPAY_SECRET are not set. Billing will be disabled."
fi

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
