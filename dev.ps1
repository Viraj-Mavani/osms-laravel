# OSMS Laravel development helper (PowerShell)
# Usage: .\dev.ps1 setup  |  .\dev.ps1 test  |  .\dev.ps1 serve  etc.

param([string]$command = "help")

# Herd paths
$herdBin = "C:\Users\viraj\.config\herd\bin"
$herdPhp = "C:\Users\viraj\.config\herd\bin\php82"
$env:PATH = "$herdBin;$herdPhp;C:\Program Files\nodejs;" + $env:PATH

function Write-Status { Write-Host "→ $args" -ForegroundColor Cyan }
function Write-Success { Write-Host "✓ $args" -ForegroundColor Green }
function Write-Error { Write-Host "✗ $args" -ForegroundColor Red }

switch ($command) {
    "setup" {
        Write-Status "Installing Composer dependencies..."
        composer install
        Write-Status "Installing npm dependencies..."
        npm install
        Write-Status "Copying .env..."
        if (!(Test-Path .env)) { Copy-Item .env.example .env }
        Write-Status "Generating app key..."
        php artisan key:generate
        Write-Status "Creating SQLite database..."
        if (!(Test-Path database/database.sqlite)) { New-Item -ItemType File database/database.sqlite }
        Write-Status "Running migrations..."
        php artisan migrate --seed
        Write-Status "Building assets..."
        npm run build
        Write-Status "Linking storage..."
        php artisan storage:link
        Write-Success "Setup complete! Visit http://osms-laravel.test (via Herd) or run: .\dev.ps1 serve"
    }

    "serve" {
        Write-Status "Starting Laravel dev server on http://localhost:8000..."
        php artisan serve
    }

    "test" {
        Write-Status "Running tests..."
        php artisan test @args
    }

    "migrate" {
        Write-Status "Migrating database..."
        php artisan migrate --seed
    }

    "build" {
        Write-Status "Building frontend assets..."
        npm run build
    }

    "dev" {
        Write-Status "Starting Vite dev server (watch mode)..."
        npm run dev
    }

    "tinker" {
        Write-Status "Opening interactive shell..."
        php artisan tinker
    }

    "reset" {
        Write-Status "Resetting database (⚠️  will delete all data)..."
        $confirm = Read-Host "Type 'yes' to confirm"
        if ($confirm -eq "yes") {
            php artisan migrate:fresh --seed
            Write-Success "Database reset and seeded"
        }
    }

    "lint" {
        Write-Status "Running linter..."
        php artisan lint
    }

    default {
        Write-Host @"
OSMS Laravel Development Helper

Usage: .\dev.ps1 <command>

Commands:
  setup       Initial setup (composer, npm, migrations, seed)
  serve       Start dev server on http://localhost:8000
  test        Run PHPUnit tests (pass args: .\dev.ps1 test --filter=Phase1)
  migrate     Run migrations + seed
  build       Compile frontend (Bootstrap SCSS + JS)
  dev         Start Vite watch mode (npm run dev)
  tinker      Open interactive shell
  reset       Nuke database + reseed (careful!)
  lint        Run ESLint
  help        Show this message

Examples:
  .\dev.ps1 setup
  .\dev.ps1 test
  .\dev.ps1 test --filter=Phase4OrderTest
"@
    }
}
