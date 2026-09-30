# Production Deployment & Operations Guide
## Distributor Cash Collection & Retailer Operations Platform (Hostinger Cloud)

---

### 1. Architectural Overview & Hosting Model

This platform is engineered specifically for **native deployment on Hostinger Cloud** (or standard Apache/LiteSpeed PHP hosting).

- **NO Docker / NO Kubernetes required.**
- **NO mandatory Node.js backend server.**
- **NO mandatory Redis dependency** (uses MySQL 8.0 database queues and cache).
- **Authoritative Backend:** Laravel 11.x + PHP 8.2+ or PHP 8.3+.
- **Database:** MySQL 8.0 with InnoDB row-level locking (`SELECT ... FOR UPDATE`), spatial geometry functions (`ST_Distance_Sphere`), and `BIGINT UNSIGNED` paise precision.
- **Admin Panel:** Native Laravel Blade + Tailwind CSS + Alpine.js + Livewire (no decoupled SPA server).
- **Mobile Fleet:** Native Flutter Android/iOS Apps for Collector and Retailer communicating via HTTPS REST APIs with Sanctum tokens and X-Device-Id binding.

---

### 2. Pre-Deployment Checklist on Hostinger Cloud

1. **Hostinger Plan:** Cloud Startup, Cloud Professional, or Business Web Hosting.
2. **PHP Version:** Set to **PHP 8.2 or PHP 8.3** in Hostinger hPanel (**Websites -> Manage -> Advanced -> PHP Configuration**).
3. **Required PHP Extensions:**
   - `pdo_mysql`, `openssl`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`.
4. **MySQL Database:** Create a new database via **Databases -> Management**:
   - Database Name: e.g. `u123456789_guruji`
   - Username: e.g. `u123456789_admin`
   - Password: Use a strong 24+ character alphanumeric password.

---

### 3. Step-by-Step Deployment Procedure

#### Step A: Upload Files to Server
Upload the `/backend-laravel` codebase to your Hostinger file manager or via SSH/Git:
```bash
# Recommended directory structure:
/home/u123456789/
    ├── guruji_app/          # Core Laravel application files
    │    ├── app/
    │    ├── config/
    │    ├── database/
    │    ├── storage/
    │    └── ...
    └── public_html/         # Point this directly to guruji_app/public
```

> **Security Rule:** Never expose the root application folder or `.env` inside `public_html`. If Hostinger hPanel allows changing the public root, point your domain directly to `/guruji_app/public`. If using standard `public_html`, copy `public/*` into `public_html/` and update `index.php` paths accordingly.

#### Step B: Install Composer Dependencies
Via Hostinger SSH Terminal:
```bash
cd /home/u123456789/guruji_app
composer install --no-dev --optimize-autoloader
```

#### Step C: Configure Environment (`.env`)
Copy the production template:
```bash
cp .env.example .env
php artisan key:generate
```
Edit `.env` with your production values:
```ini
APP_NAME="Guruji Cash Logistics"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u123456789_guruji
DB_USERNAME=u123456789_admin
DB_PASSWORD="your_strong_mysql_password"

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

#### Step D: Database Migration & Initial Seeding
Execute database migrations and seed system roles, default banks, and initial admin credentials:
```bash
php artisan migrate --force
php artisan db:seed --force
```

#### Step E: Directory Permissions & Storage Link
```bash
chmod -R 775 storage bootstrap/cache
php artisan storage:link
```

#### Step F: Production Optimization
Compile and cache configuration, routes, and views for sub-millisecond response times:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

### 4. Cron Jobs Configuration in Hostinger hPanel

Configure scheduled tasks under **Hostinger hPanel -> Advanced -> Cron Jobs**:

#### 1. Laravel Task Scheduler (Every Minute)
Handles the 09:00 AM daily retailer outstanding notice, auto-expiry of 90s broadcasts, and GPS cleanup:
```bash
* * * * * cd /home/u123456789/guruji_app && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

#### 2. Background Queue Worker (Continuous or Minutely Watchdog)
Since Hostinger shared/cloud plans don't run continuous Supervisor daemons by default, run the queue worker with `--stop-when-empty` every minute:
```bash
* * * * * cd /home/u123456789/guruji_app && /usr/bin/php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```

---

### 5. Mobile App Configuration & Build

#### Pointing Flutter Apps to Production:
In `collector_app/lib/core/api_service.dart` and `retailer_app/lib/core/api_service.dart`:
```dart
static String baseUrl = 'https://your-domain.com/api/v1';
```

#### Building Release APKs:
```bash
# Collector App
cd collector_app
flutter build apk --release

# Retailer App
cd retailer_app
flutter build apk --release
```
Release APKs will be generated in:
- `collector_app/build/app/outputs/flutter-apk/app-release.apk`
- `retailer_app/build/app/outputs/flutter-apk/app-release.apk`

---

### 6. Automated Backup & Disaster Recovery Strategy

1. **Daily MySQL Database Dump:**
   Configure a nightly cron job in Hostinger:
   ```bash
   0 2 * * * mysqldump -u u123456789_admin -p'password' u123456789_guruji | gzip > /home/u123456789/backups/db_$(date +\%F).sql.gz
   ```
2. **Storage & Receipts Backup:**
   Nightly backup of uploaded documents and thermal receipts stored in `storage/app/`.
3. **Retention Policy:**
   - Daily backups retained for 30 days.
   - Monthly snapshots retained for 1 year.
