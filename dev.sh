#!/usr/bin/env bash
# OSMS Laravel development helper (Bash/Git Bash)
# Usage: bash dev.sh setup  |  bash dev.sh test  |  bash dev.sh serve  etc.

set -o pipefail

HERD_BIN="$HOME/.config/herd/bin"
HERD_PHP="$HOME/.config/herd/bin/php82"

# Prepend Herd to PATH for non-interactive shells
export PATH="$HERD_BIN:$HERD_PHP:/c/Program\ Files/nodejs:$PATH"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

function status() { echo -e "${CYAN}→ $@${NC}"; }
function success() { echo -e "${GREEN}✓ $@${NC}"; }
function error() { echo -e "${RED}✗ $@${NC}"; }

case "${1:-help}" in
    setup)
        status "Installing Composer dependencies..."
        composer install
        status "Installing npm dependencies..."
        npm install
        status "Copying .env..."
        [ ! -f .env ] && cp .env.example .env
        status "Generating app key..."
        php artisan key:generate
        status "Creating SQLite database..."
        mkdir -p database && touch database/database.sqlite
        status "Running migrations..."
        php artisan migrate --seed
        status "Building assets..."
        npm run build
        status "Linking storage..."
        php artisan storage:link
        success "Setup complete! Visit http://osms-laravel.test (via Herd) or run: bash dev.sh serve"
        ;;

    serve)
        status "Starting Laravel dev server on http://localhost:8000..."
        php artisan serve
        ;;

    test)
        shift
        status "Running tests..."
        php artisan test "$@"
        ;;

    migrate)
        status "Migrating database..."
        php artisan migrate --seed
        ;;

    build)
        status "Building frontend assets..."
        npm run build
        ;;

    dev)
        status "Starting Vite dev server (watch mode)..."
        npm run dev
        ;;

    tinker)
        status "Opening interactive shell..."
        php artisan tinker
        ;;

    reset)
        status "Resetting database (⚠️  will delete all data)..."
        read -p "Type 'yes' to confirm: " confirm
        if [ "$confirm" = "yes" ]; then
            php artisan migrate:fresh --seed
            success "Database reset and seeded"
        fi
        ;;

    lint)
        status "Running linter..."
        php artisan lint
        ;;

    *)
        cat <<EOF
OSMS Laravel Development Helper

Usage: bash dev.sh <command>

Commands:
  setup       Initial setup (composer, npm, migrations, seed)
  serve       Start dev server on http://localhost:8000
  test        Run PHPUnit tests (pass args: bash dev.sh test --filter=Phase1)
  migrate     Run migrations + seed
  build       Compile frontend (Bootstrap SCSS + JS)
  dev         Start Vite watch mode (npm run dev)
  tinker      Open interactive shell
  reset       Nuke database + reseed (careful!)
  lint        Run ESLint
  help        Show this message

Examples:
  bash dev.sh setup
  bash dev.sh test
  bash dev.sh test --filter=Phase4OrderTest
EOF
        ;;
esac
