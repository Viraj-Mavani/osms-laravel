# Deploying OSMS to Hostinger Premium Shared Hosting

Target domain: **osms.satvscript.com** · Repo: `github.com/Viraj-Mavani/osms-laravel`

---

## 1. One-time Hostinger setup (hPanel)

1. **PHP version** → set to **8.2** or higher (Advanced → PHP Configuration).
2. **MySQL Databases** → create:
   - Database: `osms_db`
   - User: `osms_user` + a strong password
   - Grant the user **all privileges** on the database.
   - Note the DB host (usually `localhost` on shared hosting).
3. **Subdomain / Domain** → point `osms.satvscript.com`'s **document root** to
   `.../osms-laravel/public` (see §4 if you can't change the document root).
4. **SSL** → enable the free SSL certificate for the domain.

## 2. Get the code onto the server (SSH)

```bash
cd ~/domains/satvscript.com        # or your home dir
git clone https://github.com/Viraj-Mavani/osms-laravel.git
cd osms-laravel

# PHP deps (production)
composer install --no-dev --optimize-autoloader

# Front-end build — if Node is unavailable on the server, build locally and
# commit/upload the public/build folder instead (see note below).
npm install && npm run build
```

> **No Node on the server?** Run `npm run build` locally and upload the generated
> `public/build/` directory. The app only needs the compiled assets at runtime.

## 3. Configure the environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```env
APP_NAME=OSMS
APP_ENV=production
APP_DEBUG=false
APP_URL=https://osms.satvscript.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=osms_db
DB_USERNAME=osms_user
DB_PASSWORD=your_db_password

FILESYSTEM_DISK=public

# Razorpay (from the Razorpay dashboard)
RAZORPAY_KEY=rzp_live_xxx
RAZORPAY_SECRET=xxx
RAZORPAY_WEBHOOK_SECRET=xxx
RAZORPAY_PLAN_BASIC=plan_xxx
RAZORPAY_PLAN_PRO=plan_xxx
RAZORPAY_PLAN_ENTERPRISE=plan_xxx
```

Then:

```bash
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> Optionally seed a superadmin + demo data on first deploy: `php artisan db:seed --force`
> (remove or change the seeded passwords afterwards).

## 4. If you can't change the document root

Some shared-hosting plans serve from a fixed `public_html`. Two options:

**Option A — symlink (preferred):**
```bash
ln -s ~/domains/satvscript.com/osms-laravel/public ~/public_html/osms
```

**Option B — root `.htaccess` forward:** a `public_html/.htaccess` is included in this
repo's root as `htaccess-root-forward.txt`. Copy its contents into a `.htaccess` placed
beside the Laravel folder so requests are routed into `public/`. Never expose the project
root directly — only `public/` should be web-accessible.

## 5. Razorpay webhook

In the Razorpay dashboard → **Settings → Webhooks**, add:

```
URL:    https://osms.satvscript.com/webhooks/razorpay
Events: subscription.activated, subscription.charged, subscription.pending,
        subscription.halted, subscription.cancelled, subscription.completed
Secret: (use the same value as RAZORPAY_WEBHOOK_SECRET)
```

## 6. Scheduler (optional, for future jobs)

hPanel → **Cron Jobs**, add (every minute):

```
php /home/USER/domains/satvscript.com/osms-laravel/artisan schedule:run >> /dev/null 2>&1
```

## 7. Redeploying after changes

```bash
cd ~/domains/satvscript.com/osms-laravel
bash deploy.sh
```

`deploy.sh` (included) pulls, installs, migrates, rebuilds caches.

---

## Post-deploy smoke checklist

- [ ] `https://osms.satvscript.com` loads the landing page over HTTPS
- [ ] Register → onboarding → dashboard works
- [ ] Add a patient, an inventory item (SKU/barcode auto-generate), create an order
- [ ] Order receipt PDF downloads
- [ ] Analytics Excel export downloads
- [ ] Razorpay checkout opens (with live keys) and webhook flips the subscription to active
- [ ] Logo upload on onboarding appears on the receipt (storage:link working)
