# M0 Foundations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the Laravel 13 backend foundation — API conventions, users and the three roles, tamper-evident audit trail, settings, actor and zone schema, retailer token login, device-bound collector sessions, device approval, and idempotency — all verified on MariaDB.

**Architecture:** One Laravel 13 application in `/backend-laravel`. Thin controllers call services; services own transactions; every security-relevant action writes an audit row in the same transaction. Collector requests are signed with a per-phone EC P-256 key (spec §18.3–18.4). Tests run on MariaDB (portable 11.4 LTS locally on port 3307, 10.11 in CI) — never SQLite.

**Tech Stack:** PHP 8.3, Laravel 13, MariaDB 11.4 / 10.11, Laravel Sanctum, spatie/laravel-permission, PHPUnit, Pint, Larastan.

**Spec:** `docs/architecture.md` (approved 2026-09-30; every §21 default accepted). This plan covers milestone **M0** of spec §20. Domain tables for pickups, ledger, vault and so on are created in the milestone that implements them, each with its tests, instead of all at once in M0.

## Global Constraints

- PHP ≥ 8.3 and Laravel 13.x; SQL must run on MariaDB ≥ 10.6 and MySQL 8.0 (spec §0.3).
- No Dockerfiles or compose files, no Node.js runtime on the server, Redis never required (spec §0.1).
- Exactly three roles — `admin`, `collector`, `retailer`. Nothing named or modelled as a distributor (spec §0.1).
- Money is integer paise in `BIGINT` columns named `*_paise`; no floats in money paths (spec §0.6).
- Times stored in UTC (`DATETIME(6)` for event times), displayed in Asia/Kolkata; the server clock is authoritative (spec §0.6).
- APIs expose `public_id` (ULID), never auto-increment ids (spec §0.6).
- Every JSON response uses the envelope `{success, code, message, data, errors, meta: {server_time, request_id}}` (spec §5.1).
- Every PHP file starts with `declare(strict_types=1);`; Pint (Laravel preset) and Larastan level 5 must pass.
- Local test database credentials (`cms_test`/`cms_test` on 127.0.0.1) are throwaway and may be committed; no other credential is ever committed.

## Review Focus

1. **Phone clock wrong on a collector's phone** → every signed request fails; the error must tell the collector to set the phone's date and time automatically, not just say "invalid". Pinned in Task 7.
2. **Mobile numbers typed in everyday formats** ("98120 00004", "+91 98120 00004", "098120 00004", "91-98120-00004") must reach the same account at login. Pinned in Task 8.
3. **Hindi text in audited data** (Devanagari shop names) must seal and verify without false tamper alarms. Pinned in Task 4.
4. **The same Idempotency-Key sent to a different endpoint** must be rejected, never replay the other endpoint's response. Pinned in Task 11.
5. **An expired session** must answer `TOKEN_EXPIRED` (so the apps can show "session expired, please log in") rather than a generic `UNAUTHENTICATED`. Pinned in Task 8.

## File map (created or changed in M0)

```
.gitattributes                                   LF line endings for the repo
.github/workflows/backend.yml                    CI: Pint, Larastan, PHPUnit on MariaDB 10.11 (no containers)
tools/dev/mariadb.ps1                            start/stop/status for the portable dev MariaDB
docs/development.md                              local setup and day-to-day commands
backend-laravel/
  app/Enums/                                     UserRole, UserStatus, AdminPermission, AdminPermissionPreset, AuditActorType,
                                                 SettingType, RecordStatus, TerritoryType, CollectorStatus, RetailerStatus,
                                                  KycStatus, ShopRole, LocationSource, DeviceStatus, AppClient,
                                                 TokenAbility, IdempotencyStatus
  app/Exceptions/ApiException.php                business error with machine code + HTTP status
  app/Exceptions/ApiExceptionRenderer.php        maps every exception to the envelope for api/* requests
  app/Http/Middleware/                           AssignRequestId, ForceJsonResponse, SecurityHeaders, EnsureRole,
                                                 EnsureAdminPermission, EnsurePasswordChanged, EnsureRetailerMembership,
                                                 VerifyDeviceSignature, EnforceIdempotency
  app/Http/Controllers/Api/V1/                   MetaController; Auth/* (login, logout, me, password, rotate, device);
                                                 Admin/* (approve/revoke device)
  app/Http/Requests/                             LoginRequest, ChangePasswordRequest, RegisterDeviceRequest, RevokeDeviceRequest
  app/Http/Resources/UserProfileResource.php
  app/Listeners/RecordTokenIp.php
  app/Models/                                    User, AuditLog, Setting, Territory, Zone, Collector, Retailer, RetailerUser,
                                                 RetailerLocation, Device, PersonalAccessToken, IdempotencyKey, Concerns/HasPublicId
  app/Services/Audit/                            AuditLogger, AuditActor, AuditHasher, AuditSealer, AuditChainVerifier, AuditChainResult
  app/Services/Auth/                             LoginService, TokenIssuer, IssuedToken, RetailerMembershipGuard,
                                                 DeviceSignatureVerifier, DevicePublicKey, DeviceRegistrationService,
                                                 DeviceBindingService, Integrity/{IntegrityVerifier, IntegrityVerdict, UncheckedIntegrityVerifier}
  app/Services/Geo/ZoneResolver.php
  app/Support/                                   MobileNumber, Api/ApiResponse, Settings/{Settings, SettingDefinition, SettingsCatalog}
  app/Console/Commands/                          audit:seal, audit:verify-chain, security:prune-nonces, idempotency:prune,
                                                 devices:pending, devices:approve, devices:revoke, cms:create-admin
  config/cms.php                                 code-level business constants (geofence radius)
  database/migrations/2026_10_02_000N00_*        audit, settings, zones, collectors, retailers, devices, tokens, idempotency
  database/factories/                            User, Territory, Zone, Collector, Retailer, RetailerUser, Device
  database/seeders/RolesAndPermissionsSeeder.php
  tests/Support/{DeviceKeyPair, SignsDeviceRequests}.php
  tests/Feature/**, tests/Unit/**
```

Custom migrations use the fixed prefixes `2026_10_02_000100_` … `2026_10_02_000800_` so they always sort after the migrations generated by the scaffold, Sanctum and spatie on 2026-09-30/10-01.

---

### Task 1: Toolchain, Laravel 13 scaffold and quality gates

**Files:**
- Create: `tools/dev/mariadb.ps1`, `.gitattributes`, `.github/workflows/backend.yml`
- Create: `backend-laravel/` (via `composer create-project`), `backend-laravel/pint.json`, `backend-laravel/phpstan.neon`
- Modify: `backend-laravel/config/database.php` (mariadb connection timezone), `backend-laravel/phpunit.xml`, `backend-laravel/.env.example`, `backend-laravel/composer.json` (scripts)
- Delete: `backend-laravel/tests/Feature/ExampleTest.php`, `backend-laravel/tests/Unit/ExampleTest.php`, `backend-laravel/database/database.sqlite`
- Test: `backend-laravel/tests/Feature/Platform/PlatformTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: a Laravel 13 app whose tests run on MariaDB database `cms_test` (127.0.0.1:3307, user `cms_test`/`cms_test`); composer scripts `lint`, `analyse`, `test`; the mariadb connection pinned to session time zone `+00:00`.

- [ ] **Step 1: Install the portable MariaDB 11.4.13 LTS (PowerShell)**

```powershell
$ver = '11.4.13'
$mdb = "C:\tools\mariadb-$ver-winx64"
New-Item -ItemType Directory -Force C:\tools | Out-Null
Invoke-WebRequest "https://archive.mariadb.org/mariadb-$ver/winx64-packages/mariadb-$ver-winx64.zip" -OutFile "$env:TEMP\mariadb-$ver.zip"
Expand-Archive "$env:TEMP\mariadb-$ver.zip" -DestinationPath C:\tools -Force
$rootPw = -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 24 | ForEach-Object { [char]$_ })
& "$mdb\bin\mariadb-install-db.exe" --datadir="$mdb\data" --port=3307 --password=$rootPw
Set-Content -Path "$mdb\ROOT_PASSWORD.txt" -Value $rootPw
```

Expected: `mariadb-install-db` reports the data directory was created. The root password lives only in `C:\tools\...\ROOT_PASSWORD.txt`, outside the repository.

- [ ] **Step 2: Add the dev database helper script**

Create `tools/dev/mariadb.ps1`:

```powershell
# Starts, stops or reports the portable MariaDB used for local development and tests.
# Override the install folder with the CMS_MARIADB_HOME environment variable.
param([ValidateSet('start', 'stop', 'status')][string]$Action = 'status')

$mdb  = if ($env:CMS_MARIADB_HOME) { $env:CMS_MARIADB_HOME } else { 'C:\tools\mariadb-11.4.13-winx64' }
$port = 3307

function Test-Up {
    try { $c = [Net.Sockets.TcpClient]::new(); $c.Connect('127.0.0.1', $port); $c.Close(); return $true } catch { return $false }
}

switch ($Action) {
    'start' {
        if (Test-Up) { "MariaDB already listening on 127.0.0.1:$port"; break }
        Start-Process -FilePath "$mdb\bin\mariadbd.exe" -ArgumentList "--defaults-file=`"$mdb\data\my.ini`"", '--bind-address=127.0.0.1' -WindowStyle Hidden
        for ($i = 0; $i -lt 40 -and -not (Test-Up); $i++) { Start-Sleep -Milliseconds 250 }
        if (Test-Up) { "MariaDB started on 127.0.0.1:$port" } else { throw 'MariaDB did not start; check the .err file in the data folder.' }
    }
    'stop' {
        $pw = Get-Content "$mdb\ROOT_PASSWORD.txt"
        & "$mdb\bin\mariadb-admin.exe" --host=127.0.0.1 --port=$port --user=root "--password=$pw" shutdown
        'MariaDB stopped'
    }
    'status' { if (Test-Up) { "MariaDB listening on 127.0.0.1:$port" } else { 'MariaDB is not running' } }
}
```

Run: `powershell -File tools/dev/mariadb.ps1 start` → Expected: `MariaDB started on 127.0.0.1:3307`.

- [ ] **Step 3: Create the dev and test databases**

```powershell
$mdb = 'C:\tools\mariadb-11.4.13-winx64'
$pw = Get-Content "$mdb\ROOT_PASSWORD.txt"
$devPw = -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 24 | ForEach-Object { [char]$_ })
$sql = @"
CREATE DATABASE IF NOT EXISTS cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS cms_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'cms'@'127.0.0.1' IDENTIFIED BY '$devPw';
CREATE USER IF NOT EXISTS 'cms'@'localhost' IDENTIFIED BY '$devPw';
GRANT ALL PRIVILEGES ON cms.* TO 'cms'@'127.0.0.1';
GRANT ALL PRIVILEGES ON cms.* TO 'cms'@'localhost';
CREATE USER IF NOT EXISTS 'cms_test'@'127.0.0.1' IDENTIFIED BY 'cms_test';
CREATE USER IF NOT EXISTS 'cms_test'@'localhost' IDENTIFIED BY 'cms_test';
GRANT ALL PRIVILEGES ON cms_test.* TO 'cms_test'@'127.0.0.1';
GRANT ALL PRIVILEGES ON cms_test.* TO 'cms_test'@'localhost';
"@
& "$mdb\bin\mariadb.exe" --host=localhost --port=3307 --user=root "--password=$pw" -e $sql
"DB_PASSWORD for .env: $devPw"
```

Expected: no errors; note the printed dev password for Step 5.

- [ ] **Step 4: Scaffold Laravel 13 with Sanctum and Larastan**

```bash
cd /c/xampp/htdocs/guruji
composer create-project laravel/laravel backend-laravel "^13.0" --prefer-dist --no-interaction
cd backend-laravel
php artisan install:api --without-migration-prompt --no-interaction
composer require --dev larastan/larastan --no-interaction
rm -f tests/Feature/ExampleTest.php tests/Unit/ExampleTest.php database/database.sqlite
```

Expected: `routes/api.php` and a `create_personal_access_tokens_table` migration exist; `vendor/bin/phpstan` exists.

- [ ] **Step 5: Point the local `.env` at the dev database**

Edit `backend-laravel/.env` (never committed) so these keys read:

```dotenv
APP_NAME="Cash Logistics"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=cms
DB_USERNAME=cms
DB_PASSWORD=<the dev password printed in Step 3>
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

- [ ] **Step 6: Write the failing platform test**

Create `backend-laravel/tests/Feature/Platform/PlatformTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PlatformTest extends TestCase
{
    public function test_health_endpoint_is_up(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_tests_run_on_mariadb_10_6_or_newer_with_utc_sessions(): void
    {
        $this->assertSame('mariadb', DB::connection()->getDriverName());

        $version = (string) DB::scalar('select version()');
        $this->assertStringContainsStringIgnoringCase('mariadb', $version);
        $this->assertSame(1, preg_match('/^(\d+\.\d+\.\d+)/', $version, $matches));
        $this->assertTrue(version_compare($matches[1], '10.6.0', '>='), "MariaDB {$version} is older than 10.6");

        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('+00:00', (string) DB::scalar('select @@session.time_zone'));
    }
}
```

- [ ] **Step 7: Run it to verify it fails**

Run: `cd backend-laravel && php artisan test --filter=PlatformTest`
Expected: FAIL — the driver is `sqlite` (the skeleton's `phpunit.xml` still points at SQLite).

- [ ] **Step 8: Point tests at MariaDB and pin the session time zone**

In `backend-laravel/phpunit.xml`, replace the `DB_CONNECTION`/`DB_DATABASE` lines inside `<php>` with:

```xml
        <env name="DB_CONNECTION" value="mariadb"/>
        <env name="DB_HOST" value="127.0.0.1"/>
        <env name="DB_PORT" value="3307"/>
        <env name="DB_DATABASE" value="cms_test"/>
        <env name="DB_USERNAME" value="cms_test"/>
        <env name="DB_PASSWORD" value="cms_test"/>
```

(`<env>` does not override variables already set in the environment, so CI can point the same file at port 3306.)

In `backend-laravel/config/database.php`, inside the `'mariadb' => [...]` connection array, add after `'engine' => null,`:

```php
            'timezone' => '+00:00',
```

- [ ] **Step 9: Run the test to verify it passes**

Run: `php artisan test --filter=PlatformTest`
Expected: PASS (2 tests).

- [ ] **Step 10: Add quality gates**

Create `backend-laravel/pint.json`:

```json
{
    "preset": "laravel",
    "rules": {
        "declare_strict_types": true
    }
}
```

Create `backend-laravel/phpstan.neon`:

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app/
    level: 5
```

In `backend-laravel/composer.json`, add to `"scripts"`:

```json
        "lint": "pint --test",
        "analyse": "phpstan analyse --memory-limit=1G --no-progress",
```

(The skeleton already defines `"test"`.) Then run `vendor/bin/pint` once so every skeleton file gets `declare(strict_types=1);`, and run `php artisan test` again → Expected: PASS.

- [ ] **Step 11: Replace `.env.example` with the spec's placeholder set (spec §19.8)**

Overwrite `backend-laravel/.env.example`:

```dotenv
APP_NAME="Cash Logistics"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://ops.example.com
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mariadb
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=30
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=ops.example.com

# Real-time: polling works without this. Set reverb or pusher only on a VPS.
BROADCAST_CONNECTION=null
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=
REVERB_PORT=
REVERB_SCHEME=https

FIREBASE_PROJECT_ID=
FIREBASE_CREDENTIALS=storage/app/private/credentials/firebase.json
PLAY_INTEGRITY_PACKAGE_NAMES=

GOOGLE_MAPS_BROWSER_KEY=
GOOGLE_MAPS_SERVER_KEY=

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="${APP_NAME}"

RECHARGE_DRIVER=mock
RECHARGE_BASE_URL=
RECHARGE_API_KEY=
RECHARGE_API_SECRET=
RECHARGE_WEBHOOK_SECRET=

BBPS_DRIVER=mock
BBPS_BASE_URL=
BBPS_AGENT_ID=
BBPS_API_KEY=
BBPS_API_SECRET=
BBPS_WEBHOOK_SECRET=

WHATSAPP_DRIVER=log
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_TOKEN=
WHATSAPP_WEBHOOK_SECRET=

SMS_DRIVER=log
SMS_API_KEY=
SMS_SENDER_ID=
SMS_DLT_ENTITY_ID=

BACKUP_DISK=offsite
BACKUP_ARCHIVE_PASSWORD=
OFFSITE_KEY=
OFFSITE_SECRET=
OFFSITE_BUCKET=
OFFSITE_ENDPOINT=
```

- [ ] **Step 12: Add CI (no containers) and LF line endings**

Create `.github/workflows/backend.yml`:

```yaml
name: backend

on:
  push:
    branches: [main]
    paths: ['backend-laravel/**', '.github/workflows/backend.yml']
  pull_request:
    paths: ['backend-laravel/**', '.github/workflows/backend.yml']

jobs:
  test:
    runs-on: ubuntu-24.04
    defaults:
      run:
        working-directory: backend-laravel
    env:
      DB_CONNECTION: mariadb
      DB_HOST: 127.0.0.1
      DB_PORT: 3306
      DB_DATABASE: cms_test
      DB_USERNAME: cms_test
      DB_PASSWORD: cms_test
    steps:
      - uses: actions/checkout@v4

      - name: Install MariaDB 10.11 from release binaries (no containers)
        uses: shogo82148/actions-setup-mysql@v1
        with:
          distribution: mariadb
          mysql-version: '10.11'
          root-password: root

      - name: Create the test database
        run: >
          mysql --host=127.0.0.1 --user=root --password=root -e
          "CREATE DATABASE cms_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
           CREATE USER 'cms_test'@'%' IDENTIFIED BY 'cms_test';
           GRANT ALL PRIVILEGES ON cms_test.* TO 'cms_test'@'%';"

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: bcmath, intl, mbstring, pdo_mysql, sodium, zip, gd, openssl
          coverage: none

      - run: composer install --no-interaction --prefer-dist --no-progress
      - run: cp .env.example .env && php artisan key:generate
      - run: composer lint
      - run: composer analyse
      - run: php artisan test
```

Create `.gitattributes` at the repo root:

```gitattributes
* text=auto eol=lf
*.bat text eol=crlf
*.cmd text eol=crlf
*.ps1 text eol=crlf
*.png binary
*.jpg binary
*.jpeg binary
*.gif binary
*.ico binary
*.pdf binary
*.zip binary
*.jks binary
*.keystore binary
*.ttf binary
*.woff binary
*.woff2 binary
```

- [ ] **Step 13: Verify the gates and commit**

Run: `cd backend-laravel && composer lint && composer analyse && php artisan test`
Expected: Pint reports no changes, PHPStan reports no errors, tests PASS.

```bash
cd /c/xampp/htdocs/guruji
git add --renormalize .
git add .gitattributes .github tools backend-laravel
git commit -m "chore(backend): scaffold Laravel 13 on MariaDB with quality gates and CI"
```

---

### Task 2: API conventions — request id, envelope, error rendering, security headers

**Files:**
- Create: `app/Support/Api/ApiResponse.php`, `app/Exceptions/ApiException.php`, `app/Exceptions/ApiExceptionRenderer.php`
- Create: `app/Http/Middleware/AssignRequestId.php`, `app/Http/Middleware/ForceJsonResponse.php`, `app/Http/Middleware/SecurityHeaders.php`
- Create: `app/Http/Controllers/Api/V1/MetaController.php`
- Modify: `bootstrap/app.php`, `routes/api.php`
- Test: `tests/Feature/Api/ApiConventionsTest.php`

(All paths in Tasks 2–12 are relative to `backend-laravel/`.)

**Interfaces:**
- Consumes: the scaffold from Task 1.
- Produces:
  - `ApiResponse::success(mixed $data = null, string $message = 'OK', int $status = 200, string $code = 'OK', array $meta = []): JsonResponse`
  - `ApiResponse::error(string $code, string $message, int $status, array $errors = [], mixed $data = null): JsonResponse`
  - `ApiResponse::formatTime(CarbonInterface $time): string` (UTC, `Y-m-d\TH:i:s.v\Z`) and `ApiResponse::serverTime(): string`
  - `new ApiException(string $errorCode, string $message, int $status = 422, array $errors = [], mixed $data = null)` with public readonly `$errorCode`, `$status`, `$errors`, `$data`
  - Request attribute `request_id` and `Context::get('request_id')` set on every request.
  - Route `GET /api/v1/meta` named `api.v1.meta`; route name prefix `api.v1.` for everything under `/api/v1`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/ApiConventionsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Exceptions\ApiException;
use App\Support\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class ApiConventionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api/v1/_test')->group(function (): void {
            Route::post('validate', fn (Request $request) => ApiResponse::success(
                $request->validate(['amount_paise' => ['required', 'integer', 'min:1']])
            ));
            Route::get('domain-error', fn () => throw new ApiException(
                'PICKUP_ALREADY_ACCEPTED',
                'Pickup already accepted by another collector.',
                409,
            ));
            Route::get('crash', fn () => throw new RuntimeException('secret internal detail'));
        });
    }

    public function test_meta_returns_the_standard_envelope_with_utc_server_time(): void
    {
        $response = $this->getJson('/api/v1/meta');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'OK')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('data.api_version', 'v1')
            ->assertJsonStructure(['success', 'code', 'message', 'data', 'errors', 'meta' => ['server_time', 'request_id']]);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $response->json('meta.server_time'));
    }

    public function test_a_valid_client_request_id_is_echoed_and_a_bad_one_is_replaced(): void
    {
        $id = 'c1f0e0a2-7d1b-4c1e-9a55-3f2d1b0a9e77';
        $this->getJson('/api/v1/meta', ['X-Request-Id' => $id])
            ->assertHeader('X-Request-Id', $id)
            ->assertJsonPath('meta.request_id', $id);

        $replaced = $this->getJson('/api/v1/meta', ['X-Request-Id' => 'bad id with spaces'])->headers->get('X-Request-Id');
        $this->assertTrue(Str::isUuid((string) $replaced));
    }

    public function test_unknown_api_routes_answer_not_found_in_the_envelope(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'NOT_FOUND')
            ->assertHeader('X-Request-Id');
    }

    public function test_validation_failures_list_field_rule_and_message(): void
    {
        $this->postJson('/api/v1/_test/validate', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('errors.0.field', 'amount_paise')
            ->assertJsonPath('errors.0.code', 'REQUIRED');
    }

    public function test_business_errors_keep_their_code_and_status(): void
    {
        $this->getJson('/api/v1/_test/domain-error')
            ->assertStatus(409)
            ->assertJsonPath('code', 'PICKUP_ALREADY_ACCEPTED')
            ->assertJsonPath('message', 'Pickup already accepted by another collector.');
    }

    public function test_unexpected_errors_hide_internal_details_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/v1/_test/crash')->assertStatus(500)->assertJsonPath('code', 'SERVER_ERROR');

        $this->assertStringNotContainsString('secret internal detail', (string) $response->getContent());
    }

    public function test_wrong_method_answers_method_not_allowed(): void
    {
        $this->postJson('/api/v1/meta')->assertStatus(405)->assertJsonPath('code', 'METHOD_NOT_ALLOWED');
    }

    public function test_api_responses_carry_security_headers(): void
    {
        $this->getJson('/api/v1/meta')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=ApiConventionsTest`
Expected: FAIL — `Class "App\Exceptions\ApiException" not found`.

- [ ] **Step 3: Implement the envelope, the exception and the renderer**

Create `app/Support/Api/ApiResponse.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support\Api;

use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;

/**
 * Builds every JSON response in the platform's envelope (spec §5.1).
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200, string $code = 'OK', array $meta = []): JsonResponse
    {
        return self::make(true, $code, $message, $data, [], $status, $meta);
    }

    /**
     * @param  list<array{field: string|null, code: string, message: string}>  $errors
     */
    public static function error(string $code, string $message, int $status, array $errors = [], mixed $data = null): JsonResponse
    {
        return self::make(false, $code, $message, $data, $errors, $status, []);
    }

    public static function formatTime(CarbonInterface $time): string
    {
        return $time->copy()->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    public static function serverTime(): string
    {
        return self::formatTime(Carbon::now());
    }

    /**
     * @param  list<array{field: string|null, code: string, message: string}>  $errors
     * @param  array<string, mixed>  $meta
     */
    private static function make(bool $success, string $code, string $message, mixed $data, array $errors, int $status, array $meta): JsonResponse
    {
        return new JsonResponse([
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
            'meta' => array_merge($meta, [
                'server_time' => self::serverTime(),
                'request_id' => Context::get('request_id'),
            ]),
        ], $status);
    }
}
```

Create `app/Exceptions/ApiException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * An expected business or security outcome with a machine-readable code (spec Appendix B).
 * Rendered by ApiExceptionRenderer; never reported to the error log.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  list<array{field: string|null, code: string, message: string}>  $errors
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $errors = [],
        public readonly mixed $data = null,
    ) {
        parent::__construct($message);
    }
}
```

Create `app/Exceptions/ApiExceptionRenderer.php`:

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\Api\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Turns any exception raised while serving /api/* into the standard envelope.
 * Web (admin panel) requests fall through to Laravel's normal HTML rendering.
 */
final class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        $response = match (true) {
            $e instanceof ApiException => ApiResponse::error($e->errorCode, $e->getMessage(), $e->status, $e->errors, $e->data),
            $e instanceof ValidationException => ApiResponse::error('VALIDATION_FAILED', 'Some fields are invalid.', 422, self::validationErrors($e)),
            $e instanceof AuthenticationException => $this->unauthenticated($request),
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => ApiResponse::error('FORBIDDEN', 'You are not allowed to do this.', 403),
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => ApiResponse::error('NOT_FOUND', 'Not found.', 404),
            $e instanceof ThrottleRequestsException => ApiResponse::error('RATE_LIMITED', 'Too many requests. Please wait and try again.', 429),
            $e instanceof MethodNotAllowedHttpException => ApiResponse::error('METHOD_NOT_ALLOWED', 'Method not allowed.', 405),
            $e instanceof HttpExceptionInterface => ApiResponse::error('HTTP_ERROR', $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.', $e->getStatusCode()),
            default => ApiResponse::error('SERVER_ERROR', config('app.debug') ? $e->getMessage() : 'Something went wrong. Please try again.', 500),
        };

        if ($e instanceof HttpExceptionInterface) {
            $response->headers->add($e->getHeaders());
        }

        return $response;
    }

    private function unauthenticated(Request $request): JsonResponse
    {
        return ApiResponse::error('UNAUTHENTICATED', 'Please log in.', 401);
    }

    /**
     * @return list<array{field: string|null, code: string, message: string}>
     */
    private static function validationErrors(ValidationException $e): array
    {
        $failed = $e->validator->failed();
        $errors = [];

        foreach ($e->errors() as $field => $messages) {
            $rules = array_keys($failed[$field] ?? []);
            foreach (array_values($messages) as $index => $message) {
                $rule = $rules[$index] ?? 'Invalid';
                $errors[] = [
                    'field' => $field,
                    'code' => Str::upper(Str::snake(class_basename($rule))),
                    'message' => $message,
                ];
            }
        }

        return $errors;
    }
}
```

- [ ] **Step 4: Implement the middleware, the meta endpoint and the wiring**

Create `app/Http/Middleware/AssignRequestId.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts a well-formed client X-Request-Id (or creates one) and echoes it back.
 * The id is stored in Context so logs, audit rows and queued jobs carry it.
 */
final class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get(self::HEADER, '');
        $requestId = preg_match('/^[A-Za-z0-9-]{8,64}$/', $incoming) === 1 ? $incoming : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        Context::add('request_id', $requestId);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
```

Create `app/Http/Middleware/ForceJsonResponse.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
```

Create `app/Http/Middleware/SecurityHeaders.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers (spec §18.8). The admin panel's own CSP arrives with M3.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($request->is('api/*')) {
            $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
            $headers->set('Cache-Control', 'no-cache, private');
        }

        return $response;
    }
}
```

Create `app/Http/Controllers/Api/V1/MetaController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

final class MetaController
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'api_version' => 'v1',
            'server_time' => ApiResponse::serverTime(),
        ]);
    }
}
```

Replace `routes/api.php`:

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\MetaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('meta', MetaController::class)->name('meta');
});
```

Replace `bootstrap/app.php`:

```php
<?php

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->api(prepend: [ForceJsonResponse::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport(ApiException::class);
        $exceptions->render(new ApiExceptionRenderer);
    })->create();
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=ApiConventionsTest`
Expected: PASS (8 tests).

- [ ] **Step 6: Run the gates and commit**

Run: `composer lint && composer analyse && php artisan test` → Expected: all green.

```bash
git add backend-laravel
git commit -m "feat(api): add JSON envelope, request ids, error rendering and security headers"
```

---

### Task 3: Users, the three roles and admin permissions

**Files:**
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php` (users table only)
- Create: `app/Enums/UserRole.php`, `app/Enums/UserStatus.php`, `app/Enums/AdminPermission.php`, `app/Enums/AdminPermissionPreset.php`
- Create: `app/Models/Concerns/HasPublicId.php`
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `tests/TestCase.php`
- Create: `database/seeders/RolesAndPermissionsSeeder.php`, `app/Http/Middleware/EnsureRole.php`, `app/Http/Middleware/EnsureAdminPermission.php`
- Test: `tests/Feature/Auth/RolesAndPermissionsTest.php`

**Interfaces:**
- Consumes: `ApiException`, `ApiResponse` (Task 2).
- Produces:
  - `enum UserRole: string { Admin='admin'; Collector='collector'; Retailer='retailer'; static values(): list<string> }`
  - `enum UserStatus: string { Active='ACTIVE'; Blocked='BLOCKED'; Pending='PENDING' }`
  - `enum AdminPermission: string` (26 cases, e.g. `DevicesManage='devices.manage'`) with `static values(): list<string>`
  - `enum AdminPermissionPreset: string { Operations; VaultFinance; Viewer }` with `permissions(): list<AdminPermission>`
  - `trait HasPublicId` (ULID in `public_id`, used as route key)
  - `User` attributes: `public_id, name, mobile, email, password, role (UserRole), status (UserStatus), is_super_admin, must_change_password, failed_login_count, locked_until, last_login_at, last_login_ip`; methods `isActive(): bool`
  - `UserFactory` states: `admin(bool $super = false)`, `collector()`, `retailer()`, `blocked()`, `mustChangePassword()`
  - Middleware aliases: `role:{admin|collector|retailer}` and `permission:{name}`
  - `tests/TestCase.php`: seeds roles/permissions and re-authenticates bearer-token requests on every call.

- [ ] **Step 1: Install spatie/laravel-permission**

```bash
composer require spatie/laravel-permission --no-interaction
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --no-interaction
```

Expected: `config/permission.php` and a `create_permission_tables` migration exist.

- [ ] **Step 2: Write the failing tests**

Create `tests/Feature/Auth/RolesAndPermissionsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\AdminPermission;
use App\Enums\AdminPermissionPreset;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'role:admin'])->prefix('api/v1/_test')->group(function (): void {
            Route::get('admin-only', fn () => ApiResponse::success());
            Route::get('pickups', fn () => ApiResponse::success())->middleware('permission:pickups.manage');
            Route::get('devices', fn () => ApiResponse::success())->middleware('permission:devices.manage');
        });
    }

    public function test_exactly_the_three_platform_roles_and_all_admin_permissions_are_seeded(): void
    {
        $this->assertEqualsCanonicalizing(['admin', 'collector', 'retailer'], Role::query()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(AdminPermission::values(), Permission::query()->pluck('name')->all());
        $this->assertCount(26, AdminPermission::cases());
    }

    public function test_no_fourth_role_can_ever_be_created(): void
    {
        $this->expectException(LogicException::class);

        Role::create(['name' => 'distributor', 'guard_name' => 'web']);
    }

    public function test_a_new_user_receives_the_role_matching_their_role_column(): void
    {
        $user = User::factory()->collector()->create();

        $this->assertTrue($user->hasRole('collector'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_a_users_role_can_never_change(): void
    {
        $user = User::factory()->retailer()->create();

        $this->expectException(LogicException::class);

        $user->update(['role' => UserRole::Admin]);
    }

    public function test_users_get_a_ulid_public_id_that_is_the_route_key(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Str::isUlid($user->public_id));
        $this->assertSame('public_id', $user->getRouteKeyName());
        $this->assertIsInt($user->id);
    }

    public function test_the_role_middleware_allows_only_the_named_role(): void
    {
        Sanctum::actingAs(User::factory()->collector()->create());
        $this->getJson('/api/v1/_test/admin-only')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/v1/_test/admin-only')->assertOk();
    }

    public function test_the_role_middleware_rejects_blocked_accounts(): void
    {
        Sanctum::actingAs(User::factory()->admin()->blocked()->create());

        $this->getJson('/api/v1/_test/admin-only')->assertForbidden()->assertJsonPath('code', 'ACCOUNT_BLOCKED');
    }

    public function test_a_limited_admin_passes_only_the_permissions_granted(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->givePermissionTo(AdminPermission::PickupsManage->value);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/_test/pickups')->assertOk();
        $this->getJson('/api/v1/_test/devices')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_a_super_admin_passes_every_permission_check(): void
    {
        Sanctum::actingAs(User::factory()->admin(super: true)->create());

        $this->getJson('/api/v1/_test/devices')->assertOk();
    }

    public function test_permission_presets_reference_only_real_permissions(): void
    {
        foreach (AdminPermissionPreset::cases() as $preset) {
            $this->assertNotEmpty($preset->permissions());
            foreach ($preset->permissions() as $permission) {
                $this->assertContains($permission->value, AdminPermission::values());
            }
            $this->assertNotContains(AdminPermission::PermissionsManage, $preset->permissions(), 'Only super admins manage permissions');
        }
    }
}
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --filter=RolesAndPermissionsTest`
Expected: FAIL — `Class "App\Enums\AdminPermission" not found`.

- [ ] **Step 4: Replace the users table definition**

In `database/migrations/0001_01_01_000000_create_users_table.php`, replace the `Schema::create('users', ...)` block (keep `password_reset_tokens` and `sessions` unchanged):

```php
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 120);
            $table->string('mobile', 16)->unique();
            $table->string('email', 191)->nullable()->unique();
            $table->string('password');
            $table->string('role', 20);
            $table->string('status', 20)->default('ACTIVE');
            $table->boolean('is_super_admin')->default(false);
            $table->boolean('must_change_password')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->dateTime('two_factor_confirmed_at')->nullable();
            $table->unsignedSmallInteger('failed_login_count')->default(0);
            $table->dateTime('locked_until')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['role', 'status']);
        });
```

- [ ] **Step 5: Add the enums and the public-id trait**

Create `app/Enums/UserRole.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

/** The only three roles the platform will ever have (spec §0.1). */
enum UserRole: string
{
    case Admin = 'admin';
    case Collector = 'collector';
    case Retailer = 'retailer';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
```

Create `app/Enums/UserStatus.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'ACTIVE';
    case Blocked = 'BLOCKED';
    case Pending = 'PENDING';
}
```

Create `app/Enums/AdminPermission.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

/** Fine-grained permissions for ADMIN users (spec §6). Collectors and retailers never hold these. */
enum AdminPermission: string
{
    case PickupsManage = 'pickups.manage';
    case PickupsOverride = 'pickups.override';
    case LedgerView = 'ledger.view';
    case WalletAdjust = 'wallet.adjust';
    case PenaltiesManage = 'penalties.manage';
    case VaultOperate = 'vault.operate';
    case VaultSignoff = 'vault.signoff';
    case BanksManage = 'banks.manage';
    case DepositsManage = 'deposits.manage';
    case ReconciliationManage = 'reconciliation.manage';
    case LiveView = 'live.view';
    case SosManage = 'sos.manage';
    case BroadcastsManage = 'broadcasts.manage';
    case CollectorsManage = 'collectors.manage';
    case RetailersManage = 'retailers.manage';
    case ZonesManage = 'zones.manage';
    case DevicesManage = 'devices.manage';
    case DocumentsManage = 'documents.manage';
    case DocumentsView = 'documents.view';
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';
    case AuditView = 'audit.view';
    case UsersManage = 'users.manage';
    case PermissionsManage = 'permissions.manage';
    case SettingsManage = 'settings.manage';
    case ImportsManage = 'imports.manage';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $permission): string => $permission->value, self::cases());
    }
}
```

Create `app/Enums/AdminPermissionPreset.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

/** Permission bundles applied to ADMIN users (spec §6). They are presets, not roles. */
enum AdminPermissionPreset: string
{
    case Operations = 'operations';
    case VaultFinance = 'vault_finance';
    case Viewer = 'viewer';

    /** @return list<AdminPermission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Operations => [
                AdminPermission::LiveView, AdminPermission::PickupsManage, AdminPermission::CollectorsManage,
                AdminPermission::RetailersManage, AdminPermission::ZonesManage, AdminPermission::DevicesManage,
                AdminPermission::SosManage, AdminPermission::BroadcastsManage, AdminPermission::DocumentsView,
            ],
            self::VaultFinance => [
                AdminPermission::VaultOperate, AdminPermission::VaultSignoff, AdminPermission::BanksManage,
                AdminPermission::DepositsManage, AdminPermission::ReconciliationManage, AdminPermission::LedgerView,
                AdminPermission::WalletAdjust, AdminPermission::PenaltiesManage, AdminPermission::ReportsView,
                AdminPermission::ReportsExport,
            ],
            self::Viewer => [
                AdminPermission::LiveView, AdminPermission::LedgerView, AdminPermission::ReportsView,
                AdminPermission::AuditView, AdminPermission::DocumentsView,
            ],
        };
    }
}
```

Create `app/Models/Concerns/HasPublicId.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * Keeps the auto-increment primary key internal and exposes a ULID `public_id`
 * that is filled on create and used for route model binding (spec §0.6).
 */
trait HasPublicId
{
    use HasUlids;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
```

- [ ] **Step 6: Update the User model and factory**

Replace `app/Models/User.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Concerns\HasPublicId;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use LogicException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPublicId, HasRoles, Notifiable, SoftDeletes;

    /** Admin permissions always resolve against the web guard, whichever guard authenticated the request. */
    protected $guard_name = 'web';

    protected $fillable = ['name', 'mobile', 'email', 'password', 'role', 'status', 'must_change_password'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'must_change_password' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'failed_login_count' => 'integer',
            'locked_until' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(static function (User $user): void {
            $user->assignRole(Role::findOrCreate($user->role->value, 'web'));
        });

        static::updating(static function (User $user): void {
            if ($user->isDirty('role')) {
                throw new LogicException("A user's role cannot be changed after creation.");
            }
        });
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
```

Replace `database/factories/UserFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'mobile' => '+91'.fake()->unique()->numerify('9#########'),
            'email' => null,
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Retailer,
            'status' => UserStatus::Active,
            'must_change_password' => false,
        ];
    }

    public function admin(bool $super = false): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::Admin,
            'email' => fake()->unique()->safeEmail(),
            'is_super_admin' => $super,
        ]);
    }

    public function collector(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::Collector]);
    }

    public function retailer(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::Retailer]);
    }

    public function blocked(): static
    {
        return $this->state(fn (): array => ['status' => UserStatus::Blocked]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn (): array => ['must_change_password' => true]);
    }
}
```

(Factories fill models unguarded, so `is_super_admin` may be set here even though it is not fillable.)

- [ ] **Step 7: Seed roles and permissions; guard against a fourth role**

Create `database/seeders/RolesAndPermissionsSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AdminPermission;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        foreach (AdminPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }
    }
}
```

Replace `database/seeders/DatabaseSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Production-safe reference data only. Demo data never belongs here. */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
    }
}
```

Replace `app/Providers/AppServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Role::creating(static function (Role $role): void {
            if (! in_array($role->name, UserRole::values(), true)) {
                throw new LogicException("Only the three platform roles may exist; refusing to create role [{$role->name}].");
            }
        });

        Gate::before(static fn (User $user): ?bool => $user->role === UserRole::Admin && $user->is_super_admin && $user->isActive() ? true : null);
    }
}
```

- [ ] **Step 8: Add the role and permission middleware**

Create `app/Http/Middleware/EnsureRole.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: `role:admin` or `role:collector,retailer`. The role comes from the user row, never the request. */
final class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        if (! $user->isActive()) {
            throw new ApiException('ACCOUNT_BLOCKED', 'This account is not active. Contact the operations team.', 403);
        }

        if (! in_array($user->role->value, $roles, true)) {
            throw new ApiException('FORBIDDEN', 'You are not allowed to do this.', 403);
        }

        return $next($request);
    }
}
```

Create `app/Http/Middleware/EnsureAdminPermission.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: `permission:devices.manage`. Super admins pass through Gate::before. */
final class EnsureAdminPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        if ($user->role !== UserRole::Admin || ! $user->can($permission)) {
            throw new ApiException('FORBIDDEN', 'You are not allowed to do this.', 403);
        }

        return $next($request);
    }
}
```

In `bootstrap/app.php`, add inside `withMiddleware(...)` (plus the two `use` lines for the classes):

```php
        $middleware->alias([
            'role' => EnsureRole::class,
            'permission' => EnsureAdminPermission::class,
        ]);
```

- [ ] **Step 9: Make the base test case seed and re-authenticate**

Replace `tests/TestCase.php`:

```php
<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Seed the three roles and admin permissions whenever the test database is rebuilt. */
    protected $seed = true;

    /**
     * The auth manager caches the resolved user between requests made in one test.
     * Requests that carry a bearer token must authenticate from scratch every time,
     * otherwise a revoked or expired token would still look logged in.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if (isset($server['HTTP_AUTHORIZATION'])) {
            $this->app['auth']->forgetGuards();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
```

- [ ] **Step 10: Run the tests to verify they pass**

Run: `php artisan test --filter=RolesAndPermissionsTest`
Expected: PASS (10 tests). Then `php artisan test` → all PASS.

- [ ] **Step 11: Run the gates and commit**

Run: `composer lint && composer analyse && php artisan test` → Expected: all green.

```bash
git add backend-laravel
git commit -m "feat(auth): add users, the three fixed roles and admin permissions"
```

---

### Task 4: Tamper-evident audit logger

**Files:**
- Create: `database/migrations/2026_10_02_000100_create_audit_logs_table.php`
- Create: `app/Enums/AuditActorType.php`, `app/Models/AuditLog.php`
- Create: `app/Services/Audit/AuditActor.php`, `AuditLogger.php`, `AuditHasher.php`, `AuditSealer.php`, `AuditChainVerifier.php`, `AuditChainResult.php`
- Create: `app/Console/Commands/AuditSealCommand.php`, `app/Console/Commands/AuditVerifyChainCommand.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Audit/AuditLoggerTest.php`, `tests/Feature/Audit/AuditChainTest.php`

**Interfaces:**
- Consumes: `User`, `UserRole` (Task 3); `Context` request id (Task 2).
- Produces:
  - `AuditLogger::record(string $action, ?Model $entity = null, ?array $before = null, ?array $after = null, array $meta = [], ?AuditActor $actor = null): AuditLog`
  - `AuditActor::user(User)`, `AuditActor::system()`, `AuditActor::scheduler()`, `AuditActor::anonymous()`, `AuditActor::current()`
  - Request attribute `device_id` (int) is copied into audit rows when present (set by Task 9's middleware).
  - Commands `audit:seal` (scheduled every minute) and `audit:verify-chain` (exit code 1 on a break).

- [ ] **Step 1: Write the failing logger tests**

Create `tests/Feature/Audit/AuditLoggerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use LogicException;
use RuntimeException;
use Tests\TestCase;

final class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_the_actor_entity_request_id_and_ip_of_an_api_request(): void
    {
        Route::middleware(['api', 'auth:sanctum'])->post('/api/v1/_test/audit', function (AuditLogger $audit) {
            $user = request()->user();
            $audit->record('TEST.ACTION', $user, ['status' => 'OLD'], ['status' => 'NEW']);

            return ApiResponse::success();
        });
        $user = User::factory()->retailer()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/_test/audit', [], ['X-Request-Id' => 'req-12345678'])->assertOk();

        $log = AuditLog::query()->where('action', 'TEST.ACTION')->sole();
        $this->assertSame($user->id, $log->actor_user_id);
        $this->assertSame('retailer', $log->actor_role);
        $this->assertSame('USER', $log->actor_type);
        $this->assertSame('User', $log->entity_type);
        $this->assertSame($user->public_id, $log->entity_id);
        $this->assertSame('req-12345678', $log->request_id);
        $this->assertSame('127.0.0.1', $log->ip);
        $this->assertSame(['status' => 'OLD'], $log->before);
        $this->assertSame(['status' => 'NEW'], $log->after);
    }

    public function test_secrets_are_redacted_at_any_depth(): void
    {
        $log = app(AuditLogger::class)->record(
            'TEST.SECRETS',
            null,
            ['password' => 'hunter2'],
            ['nested' => ['token' => 'abc', 'kept' => 1], 'pan' => 'ABCDE1234F'],
        );

        $this->assertSame('[REDACTED]', $log->before['password']);
        $this->assertSame('[REDACTED]', $log->after['nested']['token']);
        $this->assertSame(1, $log->after['nested']['kept']);
        $this->assertSame('[REDACTED]', $log->after['pan']);
    }

    public function test_the_audit_row_rolls_back_with_the_business_transaction(): void
    {
        try {
            DB::transaction(function (): void {
                app(AuditLogger::class)->record('TEST.ROLLED_BACK');
                throw new RuntimeException('business step failed');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertDatabaseMissing('audit_logs', ['action' => 'TEST.ROLLED_BACK']);
    }

    public function test_audit_rows_cannot_be_updated_through_the_model(): void
    {
        $log = app(AuditLogger::class)->record('TEST.IMMUTABLE');

        $this->expectException(LogicException::class);

        $log->update(['action' => 'TEST.CHANGED']);
    }

    public function test_audit_rows_cannot_be_deleted_through_the_model(): void
    {
        $log = app(AuditLogger::class)->record('TEST.IMMUTABLE');

        $this->expectException(LogicException::class);

        $log->delete();
    }
}
```

- [ ] **Step 2: Write the failing chain tests**

Create `tests/Feature/Audit/AuditChainTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AuditChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_sealing_links_rows_into_a_chain_that_verifies(): void
    {
        $logger = app(AuditLogger::class);
        $first = $logger->record('TEST.ONE');
        $second = $logger->record('TEST.TWO');

        $this->artisan('audit:seal')->assertSuccessful();

        $rows = DB::table('audit_logs')->orderBy('seal_seq')->get();
        $this->assertSame([1, 2], $rows->pluck('seal_seq')->map(fn ($v): int => (int) $v)->all());
        $this->assertSame(str_repeat('0', 64), $rows[0]->prev_hash);
        $this->assertSame($rows[0]->hash, $rows[1]->prev_hash);
        $this->assertSame($first->id, (int) $rows[0]->id);
        $this->assertSame($second->id, (int) $rows[1]->id);

        $this->artisan('audit:verify-chain')->expectsOutputToContain('intact')->assertSuccessful();
    }

    public function test_changing_a_sealed_row_in_the_database_is_detected(): void
    {
        $logger = app(AuditLogger::class);
        $logger->record('TEST.ONE', null, null, ['amount_paise' => 4000000]);
        $second = $logger->record('TEST.TWO', null, null, ['amount_paise' => 5000000]);
        $this->artisan('audit:seal')->assertSuccessful();

        DB::table('audit_logs')->where('id', $second->id)->update(['after' => json_encode(['amount_paise' => 9900000])]);

        $this->artisan('audit:verify-chain')->expectsOutputToContain("id={$second->id}")->assertFailed();
    }

    public function test_deleting_a_sealed_row_in_the_database_is_detected(): void
    {
        $logger = app(AuditLogger::class);
        $logger->record('TEST.ONE');
        $middle = $logger->record('TEST.TWO');
        $logger->record('TEST.THREE');
        $this->artisan('audit:seal')->assertSuccessful();

        DB::table('audit_logs')->where('id', $middle->id)->delete();

        $this->artisan('audit:verify-chain')->assertFailed();
    }

    public function test_rows_committed_out_of_id_order_still_form_a_valid_chain(): void
    {
        $this->insertRawRow(900);
        $this->artisan('audit:seal')->assertSuccessful();

        $this->insertRawRow(800); // a slower transaction commits later with a lower id
        $this->artisan('audit:seal')->assertSuccessful();

        $this->assertSame(2, (int) DB::table('audit_logs')->where('id', 800)->value('seal_seq'));
        $this->artisan('audit:verify-chain')->assertSuccessful();
    }

    public function test_hindi_text_seals_and_verifies_without_false_alarms(): void
    {
        app(AuditLogger::class)->record('TEST.HINDI', null, null, ['shop_name' => 'राधे डिजिटल स्टोर', 'city' => 'रोहतक']);
        DB::table('audit_logs')->insert([
            'occurred_at' => now()->format('Y-m-d H:i:s.u'),
            'actor_type' => 'SYSTEM',
            'action' => 'TEST.HINDI_RAW',
            'after' => json_encode(['shop_name' => 'गुप्ता ग्राहक सेवा केंद्र'], JSON_UNESCAPED_UNICODE),
        ]);

        $this->artisan('audit:seal')->assertSuccessful();
        $this->artisan('audit:verify-chain')->assertSuccessful();
    }

    public function test_sealing_is_scheduled_every_minute(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('audit:seal')
            ->assertSuccessful();
    }

    private function insertRawRow(int $id): void
    {
        DB::table('audit_logs')->insert([
            'id' => $id,
            'occurred_at' => now()->format('Y-m-d H:i:s.u'),
            'actor_type' => 'SYSTEM',
            'action' => 'TEST.RAW',
            'after' => json_encode(['n' => $id]),
        ]);
    }
}
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --filter=Audit`
Expected: FAIL — `Class "App\Services\Audit\AuditLogger" not found`.

- [ ] **Step 4: Create the table, enum and model**

Create `database/migrations/2026_10_02_000100_create_audit_logs_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('occurred_at', 6);
            $table->foreignId('actor_user_id')->nullable()->constrained('users');
            $table->string('actor_role', 20)->nullable();
            $table->string('actor_type', 20);
            $table->string('action', 64);
            $table->string('entity_type', 64)->nullable();
            $table->string('entity_id', 40)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('seal_seq')->nullable()->unique();
            $table->char('prev_hash', 64)->nullable();
            $table->char('hash', 64)->nullable()->unique();
            $table->dateTime('sealed_at', 6)->nullable();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['actor_user_id', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
```

Create `app/Enums/AuditActorType.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditActorType: string
{
    case User = 'USER';
    case Anonymous = 'ANONYMOUS';
    case System = 'SYSTEM';
    case Scheduler = 'SCHEDULER';
    case Provider = 'PROVIDER';
}
```

Create `app/Models/AuditLog.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Append-only audit record (spec §18.7). Hash-chain columns are written only by AuditSealer
 * through the query builder; the model itself refuses every update and delete.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'occurred_at', 'actor_user_id', 'actor_role', 'actor_type', 'action', 'entity_type', 'entity_id',
        'before', 'after', 'ip', 'user_agent', 'device_id', 'request_id', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'sealed_at' => 'immutable_datetime',
            'before' => 'array',
            'after' => 'array',
            'meta' => 'array',
            'actor_user_id' => 'integer',
            'device_id' => 'integer',
            'seal_seq' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Audit records are append-only.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Audit records are append-only.');
        });
    }
}
```

- [ ] **Step 5: Implement the actor and the logger**

Create `app/Services/Audit/AuditActor.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\AuditActorType;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

final readonly class AuditActor
{
    private function __construct(
        public AuditActorType $type,
        public ?int $userId,
        public ?string $role,
    ) {}

    public static function user(User $user): self
    {
        return new self(AuditActorType::User, $user->id, $user->role->value);
    }

    public static function system(): self
    {
        return new self(AuditActorType::System, null, null);
    }

    public static function scheduler(): self
    {
        return new self(AuditActorType::Scheduler, null, null);
    }

    public static function anonymous(): self
    {
        return new self(AuditActorType::Anonymous, null, null);
    }

    /** The authenticated user; otherwise SYSTEM in console runs and ANONYMOUS for unauthenticated requests. */
    public static function current(): self
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return self::user($user);
        }

        return App::runningInConsole() && ! App::runningUnitTests() ? self::system() : self::anonymous();
    }

    public function isHttp(): bool
    {
        return $this->type === AuditActorType::User || $this->type === AuditActorType::Anonymous;
    }
}
```

Create `app/Services/Audit/AuditLogger.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Writes one audit row. Call it inside the same DB transaction as the change it describes,
 * so the change and its record commit or roll back together (spec §18.7).
 */
final class AuditLogger
{
    private const REDACTED = '[REDACTED]';

    /** Keys whose values must never reach the audit trail. */
    private const SECRET_KEYS = [
        'password', 'password_confirmation', 'current_password', 'remember_token', 'token', 'plain_text_token',
        'registration_token', 'two_factor_secret', 'secret', 'api_key', 'private_key', 'otp',
        'pan', 'pan_encrypted', 'account_number', 'account_number_encrypted',
    ];

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $meta
     */
    public function record(
        string $action,
        ?Model $entity = null,
        ?array $before = null,
        ?array $after = null,
        array $meta = [],
        ?AuditActor $actor = null,
    ): AuditLog {
        $actor ??= AuditActor::current();
        $request = request();
        $userAgent = $actor->isHttp() ? Str::limit((string) $request->userAgent(), 255, '') : '';

        return AuditLog::query()->create([
            'occurred_at' => Carbon::now('UTC'),
            'actor_user_id' => $actor->userId,
            'actor_role' => $actor->role,
            'actor_type' => $actor->type->value,
            'action' => $action,
            'entity_type' => $entity !== null ? class_basename($entity) : null,
            'entity_id' => $entity !== null ? $this->entityId($entity) : null,
            'before' => $this->redact($before),
            'after' => $this->redact($after),
            'ip' => $actor->isHttp() ? $request->ip() : null,
            'user_agent' => $userAgent !== '' ? $userAgent : null,
            'device_id' => $request->attributes->get('device_id'),
            'request_id' => Context::get('request_id'),
            'meta' => $meta === [] ? null : $this->redact($meta),
        ]);
    }

    private function entityId(Model $entity): string
    {
        $attributes = $entity->getAttributes();

        return (string) (array_key_exists('public_id', $attributes) ? $attributes['public_id'] : $entity->getKey());
    }

    /**
     * @param  array<array-key, mixed>|null  $data
     * @return array<array-key, mixed>|null
     */
    private function redact(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SECRET_KEYS, true)) {
                $data[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
```

- [ ] **Step 6: Implement hashing, sealing and verification**

Create `app/Services/Audit/AuditHasher.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Audit;

/**
 * Canonical form and hash of one audit row. JSON columns are decoded and re-encoded with
 * sorted keys and unescaped Unicode, so the hash does not depend on how the DB stored them.
 */
final class AuditHasher
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    private const FIELDS = [
        'id', 'occurred_at', 'actor_user_id', 'actor_role', 'actor_type', 'action', 'entity_type', 'entity_id',
        'before', 'after', 'ip', 'user_agent', 'device_id', 'request_id', 'meta',
    ];

    private const JSON_FIELDS = ['before', 'after', 'meta'];

    private const INT_FIELDS = ['id', 'actor_user_id', 'device_id'];

    /**
     * @param  array<string, mixed>  $row  a raw audit_logs row
     */
    public static function payload(array $row): string
    {
        $data = [];

        foreach (self::FIELDS as $field) {
            $value = $row[$field] ?? null;

            if ($value !== null && in_array($field, self::JSON_FIELDS, true)) {
                $value = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
            } elseif ($value !== null && in_array($field, self::INT_FIELDS, true)) {
                $value = (int) $value;
            } elseif ($value !== null) {
                $value = (string) $value;
            }

            $data[$field] = $value;
        }

        return json_encode(self::sortKeys($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    public static function hash(string $previousHash, string $payload): string
    {
        return hash('sha256', $previousHash."\n".$payload);
    }

    private static function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(self::sortKeys(...), $value);
    }
}
```

Create `app/Services/Audit/AuditSealer.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Audit;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Links committed, unsealed audit rows into the hash chain in the order it sees them.
 * Chain order is `seal_seq`, not `id`: a slow transaction may commit a lower id later.
 */
final class AuditSealer
{
    public function seal(int $batchSize = 500): int
    {
        return (int) Cache::lock('audit:seal', 120)->block(10, function () use ($batchSize): int {
            $last = DB::table('audit_logs')->whereNotNull('seal_seq')->orderByDesc('seal_seq')->first(['hash', 'seal_seq']);
            $previous = $last->hash ?? AuditHasher::GENESIS;
            $sequence = (int) ($last->seal_seq ?? 0);
            $sealed = 0;

            while (true) {
                $rows = DB::table('audit_logs')->whereNull('seal_seq')->orderBy('id')->limit($batchSize)->get();

                if ($rows->isEmpty()) {
                    return $sealed;
                }

                foreach ($rows as $row) {
                    $hash = AuditHasher::hash($previous, AuditHasher::payload((array) $row));

                    $updated = DB::table('audit_logs')
                        ->where('id', $row->id)
                        ->whereNull('seal_seq')
                        ->update([
                            'seal_seq' => $sequence + 1,
                            'prev_hash' => $previous,
                            'hash' => $hash,
                            'sealed_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
                        ]);

                    if ($updated === 1) {
                        $sequence++;
                        $previous = $hash;
                        $sealed++;
                    }
                }
            }
        });
    }
}
```

Create `app/Services/Audit/AuditChainResult.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Audit;

final readonly class AuditChainResult
{
    public function __construct(
        public bool $intact,
        public int $checked,
        public ?int $brokenAtId = null,
        public ?string $reason = null,
    ) {}
}
```

Create `app/Services/Audit/AuditChainVerifier.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Audit;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AuditChainVerifier
{
    public function verify(): AuditChainResult
    {
        $previous = AuditHasher::GENESIS;
        $expected = 1;
        $checked = 0;
        $brokenAt = null;
        $reason = null;

        DB::table('audit_logs')->whereNotNull('seal_seq')->chunkById(1000, function (Collection $rows) use (&$previous, &$expected, &$checked, &$brokenAt, &$reason): bool {
            foreach ($rows as $row) {
                $id = (int) $row->id;

                if ((int) $row->seal_seq !== $expected) {
                    [$brokenAt, $reason] = [$id, "sequence gap: expected seal_seq {$expected}, found {$row->seal_seq}"];

                    return false;
                }

                if ($row->prev_hash !== $previous) {
                    [$brokenAt, $reason] = [$id, 'the link to the previous row does not match'];

                    return false;
                }

                if (! hash_equals((string) $row->hash, AuditHasher::hash($previous, AuditHasher::payload((array) $row)))) {
                    [$brokenAt, $reason] = [$id, 'the row content does not match its hash'];

                    return false;
                }

                $previous = (string) $row->hash;
                $expected++;
                $checked++;
            }

            return true;
        }, 'seal_seq');

        return new AuditChainResult($brokenAt === null, $checked, $brokenAt, $reason);
    }
}
```

- [ ] **Step 7: Add the commands and the schedule**

Create `app/Console/Commands/AuditSealCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Audit\AuditSealer;
use Illuminate\Console\Command;

final class AuditSealCommand extends Command
{
    protected $signature = 'audit:seal {--batch=500 : Rows per batch}';

    protected $description = 'Link new audit rows into the tamper-evident hash chain';

    public function handle(AuditSealer $sealer): int
    {
        $count = $sealer->seal((int) $this->option('batch'));
        $this->info("Sealed {$count} audit row(s).");

        return self::SUCCESS;
    }
}
```

Create `app/Console/Commands/AuditVerifyChainCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Audit\AuditChainVerifier;
use Illuminate\Console\Command;

final class AuditVerifyChainCommand extends Command
{
    protected $signature = 'audit:verify-chain';

    protected $description = 'Recompute the audit hash chain and report the first break';

    public function handle(AuditChainVerifier $verifier): int
    {
        $result = $verifier->verify();

        if ($result->intact) {
            $this->info("Audit chain intact ({$result->checked} sealed rows checked).");

            return self::SUCCESS;
        }

        $this->error("Audit chain broken at audit_logs id={$result->brokenAtId}: {$result->reason}.");

        return self::FAILURE;
    }
}
```

Replace `routes/console.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('audit:seal')->everyMinute()->withoutOverlapping();
Schedule::command('audit:verify-chain')->weeklyOn(0, '03:00')->timezone('Asia/Kolkata');
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `php artisan test --filter=Audit`
Expected: PASS (11 tests).

- [ ] **Step 9: Run the gates and commit**

Run: `composer lint && composer analyse && php artisan test` → Expected: all green.

```bash
git add backend-laravel
git commit -m "feat(audit): add append-only audit logger with hash-chain sealing and verification"
```

---

### Task 5: Settings catalog, audited changes and app metadata

**Files:**
- Create: `database/migrations/2026_10_02_000200_create_settings_table.php`
- Create: `app/Enums/SettingType.php`, `app/Models/Setting.php`
- Create: `app/Support/Settings/SettingDefinition.php`, `app/Support/Settings/SettingsCatalog.php`, `app/Support/Settings/Settings.php`
- Create: `config/cms.php`
- Modify: `app/Http/Controllers/Api/V1/MetaController.php`
- Test: `tests/Feature/Settings/SettingsTest.php`, `tests/Feature/Api/MetaTest.php`

**Interfaces:**
- Consumes: `AuditLogger` (Task 4), `User` (Task 3), `ApiResponse` (Task 2).
- Produces:
  - `Settings::get(string $key): mixed` (stored value or catalog default; unknown key → `InvalidArgumentException`)
  - `Settings::set(string $key, mixed $value, ?User $by = null): void` (validates, persists, audits `SETTINGS.UPDATED`)
  - `SettingsCatalog::get(string $key): SettingDefinition`, `SettingsCatalog::all(): array<string, SettingDefinition>`
  - `config('cms.geofence_radius_m') === 100`
  - `/api/v1/meta` data keys: `api_version, server_time, min_app_version{collector,retailer}, denominations_paise, broadcast_window_seconds, geofence_radius_m`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Settings/SettingsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Settings\Settings;
use App\Support\Settings\SettingsCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): Settings
    {
        return app(Settings::class);
    }

    public function test_every_key_from_spec_appendix_a_has_a_definition(): void
    {
        $expected = [
            'broadcast_window_seconds', 'broadcast_rounds', 'broadcast_mode', 'broadcast_radius_km',
            'default_collector_head_start_seconds', 'pending_max_minutes', 'max_concurrent_jobs', 'approach_radius_m',
            'max_gps_accuracy_m', 'max_fix_age_seconds', 'max_implied_speed_kmh', 'offer_location_fresh_seconds',
            'unlock_token_minutes', 'offline_authorization_minutes', 'integrity_enforcement', 'float_default_limit_paise',
            'float_cap_mode', 'float_alert_percent', 'block_punch_in_with_carried_float', 'punch_in_geofence_mode',
            'free_cancel_seconds', 'penalty_amount_paise', 'penalty_collector_share_paise', 'penalty_requires_dispatch',
            'penalty_when_wallet_short', 'allow_over_collection', 'enabled_denominations_paise', 'min_request_paise',
            'confirmation_reminder_minutes', 'confirmation_admin_alert_minutes', 'outstanding_reminder_time',
            'outstanding_reminder_antispam_hours', 'sos_nearby_radius_km', 'gps_retention_days', 'deposit_slip_required',
            'deposit_unverified_alert_days', 'maker_checker_threshold_paise', 'device_auto_approve_first',
            'collector_token_hours', 'retailer_token_days', 'admin_idle_minutes', 'min_app_version_collector',
            'min_app_version_retailer',
        ];

        $this->assertEqualsCanonicalizing($expected, array_keys(SettingsCatalog::all()));
    }

    public function test_unset_settings_return_the_catalog_default(): void
    {
        $this->assertSame(90, $this->settings()->get('broadcast_window_seconds'));
        $this->assertSame('PROJECTED', $this->settings()->get('float_cap_mode'));
        $this->assertSame([50000, 20000, 10000, 5000, 2000, 1000], $this->settings()->get('enabled_denominations_paise'));
        $this->assertFalse($this->settings()->get('device_auto_approve_first'));
    }

    public function test_a_change_is_stored_returned_and_audited_with_before_and_after(): void
    {
        $admin = User::factory()->admin()->create();

        $this->settings()->set('broadcast_window_seconds', 120, $admin);

        $this->assertSame(120, $this->settings()->get('broadcast_window_seconds'));
        $log = AuditLog::query()->where('action', 'SETTINGS.UPDATED')->sole();
        $this->assertSame(['key' => 'broadcast_window_seconds', 'value' => 90], $log->before);
        $this->assertSame(['key' => 'broadcast_window_seconds', 'value' => 120], $log->after);
        $this->assertDatabaseHas('settings', ['key' => 'broadcast_window_seconds', 'updated_by_user_id' => $admin->id]);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function invalidValues(): array
    {
        return [
            'text for a number' => ['broadcast_window_seconds', '90'],
            'number below minimum' => ['broadcast_window_seconds', 5],
            'number above maximum' => ['broadcast_window_seconds', 3600],
            'unknown enum value' => ['float_cap_mode', 'SOMETIMES'],
            'string for a boolean' => ['device_auto_approve_first', 'yes'],
            'note not allowed' => ['enabled_denominations_paise', [50000, 30000]],
            'duplicate notes' => ['enabled_denominations_paise', [50000, 50000]],
            'empty note list' => ['enabled_denominations_paise', []],
            'bad time' => ['outstanding_reminder_time', '9am'],
            'bad version' => ['min_app_version_collector', 'v2'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_rejected(string $key, mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings()->set($key, $value);
    }

    public function test_unknown_keys_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings()->get('distributor_commission');
    }

    public function test_the_collector_share_can_never_exceed_the_penalty(): void
    {
        $this->settings()->set('penalty_amount_paise', 10000);
        $this->settings()->set('penalty_collector_share_paise', 7000);

        try {
            $this->settings()->set('penalty_collector_share_paise', 12000);
            $this->fail('A share above the penalty was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertSame(7000, $this->settings()->get('penalty_collector_share_paise'));
        }

        $this->expectException(InvalidArgumentException::class);
        $this->settings()->set('penalty_amount_paise', 6000);
    }

    public function test_valid_time_and_version_values_are_accepted(): void
    {
        $this->settings()->set('outstanding_reminder_time', '21:30');
        $this->settings()->set('min_app_version_retailer', '1.4.0');

        $this->assertSame('21:30', $this->settings()->get('outstanding_reminder_time'));
        $this->assertSame('1.4.0', $this->settings()->get('min_app_version_retailer'));
    }
}
```

Create `tests/Feature/Api/MetaTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_exposes_what_the_apps_need_at_start_up(): void
    {
        app(Settings::class)->set('min_app_version_collector', '1.2.0');

        $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertJsonPath('data.min_app_version.collector', '1.2.0')
            ->assertJsonPath('data.min_app_version.retailer', '1.0.0')
            ->assertJsonPath('data.denominations_paise', [50000, 20000, 10000, 5000, 2000, 1000])
            ->assertJsonPath('data.broadcast_window_seconds', 90)
            ->assertJsonPath('data.geofence_radius_m', 100);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter="SettingsTest|MetaTest"`
Expected: FAIL — `Class "App\Support\Settings\Settings" not found`.

- [ ] **Step 3: Create the table, model and enum**

Create `database/migrations/2026_10_02_000200_create_settings_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('key', 80)->primary();
            $table->json('value');
            $table->string('type', 20);
            $table->string('group', 40);
            $table->string('description', 255);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users');
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
```

Create `app/Enums/SettingType.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum SettingType: string
{
    case Int = 'int';
    case Bool = 'bool';
    case Enum = 'enum';
    case IntList = 'int_list';
    case Time = 'time';
    case Version = 'version';
}
```

Create `app/Models/Setting.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public const CREATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'type', 'group', 'description', 'updated_by_user_id'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
```

- [ ] **Step 4: Implement the definition, the catalog and the service**

Create `app/Support/Settings/SettingDefinition.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Enums\SettingType;
use InvalidArgumentException;

final readonly class SettingDefinition
{
    /**
     * @param  list<string|int>  $options  allowed values (ENUM) or allowed members (INT_LIST; empty = any within bounds)
     */
    public function __construct(
        public string $key,
        public SettingType $type,
        public mixed $default,
        public string $group,
        public string $description,
        public ?int $min = null,
        public ?int $max = null,
        public array $options = [],
    ) {}

    public function validate(mixed $value): void
    {
        $problem = match ($this->type) {
            SettingType::Int => $this->intProblem($value),
            SettingType::Bool => is_bool($value) ? null : 'must be true or false',
            SettingType::Enum => in_array($value, $this->options, true) ? null : 'must be one of: '.implode(', ', $this->options),
            SettingType::IntList => $this->intListProblem($value),
            SettingType::Time => is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1 ? null : 'must be a 24-hour time such as 09:00',
            SettingType::Version => is_string($value) && preg_match('/^\d+\.\d+\.\d+$/', $value) === 1 ? null : 'must be a version such as 1.4.0',
        };

        if ($problem !== null) {
            throw new InvalidArgumentException("Setting [{$this->key}] {$problem}.");
        }
    }

    private function intProblem(mixed $value): ?string
    {
        if (! is_int($value)) {
            return 'must be a whole number';
        }

        if ($this->min !== null && $value < $this->min) {
            return "must be at least {$this->min}";
        }

        if ($this->max !== null && $value > $this->max) {
            return "must be at most {$this->max}";
        }

        return null;
    }

    private function intListProblem(mixed $value): ?string
    {
        if (! is_array($value) || $value === [] || ! array_is_list($value)) {
            return 'must be a non-empty list';
        }

        foreach ($value as $item) {
            if ($this->options !== [] && ! in_array($item, $this->options, true)) {
                return 'contains a value that is not allowed';
            }

            $problem = $this->intProblem($item);
            if ($problem !== null) {
                return "has an item that {$problem}";
            }
        }

        return count($value) === count(array_unique($value)) ? null : 'must not repeat values';
    }
}
```

Create `app/Support/Settings/SettingsCatalog.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Enums\SettingType;
use InvalidArgumentException;

/** Every runtime setting with its default and bounds (spec Appendix A). The geofence radius is deliberately absent. */
final class SettingsCatalog
{
    /** @var array<string, SettingDefinition>|null */
    private static ?array $definitions = null;

    public static function get(string $key): SettingDefinition
    {
        return self::all()[$key] ?? throw new InvalidArgumentException("Unknown setting [{$key}].");
    }

    /** @return array<string, SettingDefinition> */
    public static function all(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        $int = SettingType::Int;
        $bool = SettingType::Bool;
        $enum = SettingType::Enum;

        $definitions = [
            new SettingDefinition('broadcast_window_seconds', $int, 90, 'operations', 'Seconds each pickup offer stays open for collectors.', 30, 300),
            new SettingDefinition('broadcast_rounds', $int, 2, 'operations', 'Broadcast rounds before a pickup waits for an admin.', 1, 5),
            new SettingDefinition('broadcast_mode', $enum, 'ZONE_OR_RADIUS', 'operations', 'Which collectors receive an offer.', options: ['ZONE', 'RADIUS', 'ZONE_OR_RADIUS', 'ZONE_AND_RADIUS']),
            new SettingDefinition('broadcast_radius_km', $int, 5, 'operations', 'First-round offer radius around the shop, in km.', 1, 50),
            new SettingDefinition('default_collector_head_start_seconds', $int, 0, 'operations', 'Seconds the default collector sees an offer before others.', 0, 60),
            new SettingDefinition('pending_max_minutes', $int, 60, 'operations', 'Minutes an unassigned pickup waits before it expires.', 5, 720),
            new SettingDefinition('max_concurrent_jobs', $int, 1, 'operations', 'Active jobs one collector may hold at a time.', 1, 5),
            new SettingDefinition('punch_in_geofence_mode', $enum, 'ANY_ASSIGNED_ZONE', 'operations', 'Where a collector may punch in.', options: ['NONE', 'ANY_ASSIGNED_ZONE', 'HUB']),
            new SettingDefinition('allow_over_collection', $bool, false, 'operations', 'Allow collecting more than the requested amount.'),
            new SettingDefinition('enabled_denominations_paise', SettingType::IntList, [50000, 20000, 10000, 5000, 2000, 1000], 'operations', 'Notes offered by the denomination calculator, in paise.', 1000, 200000, [200000, 50000, 20000, 10000, 5000, 2000, 1000]),
            new SettingDefinition('min_request_paise', $int, 100000, 'operations', 'Smallest pickup a retailer may request, in paise.', 10000, 10000000),
            new SettingDefinition('approach_radius_m', $int, 250, 'geofence', 'Distance at which the retailer is told the collector is arriving, in metres.', 120, 2000),
            new SettingDefinition('max_gps_accuracy_m', $int, 50, 'geofence', 'Worst GPS accuracy accepted for the cash-desk unlock, in metres.', 5, 100),
            new SettingDefinition('max_fix_age_seconds', $int, 30, 'geofence', 'Oldest GPS fix accepted for the cash-desk unlock, in seconds.', 5, 120),
            new SettingDefinition('max_implied_speed_kmh', $int, 150, 'geofence', 'Speed between fixes above which a jump is treated as spoofing, in km/h.', 60, 300),
            new SettingDefinition('offer_location_fresh_seconds', $int, 120, 'geofence', "A collector's last fix must be this recent to receive offers, in seconds.", 30, 600),
            new SettingDefinition('unlock_token_minutes', $int, 15, 'geofence', 'Minutes a cash-desk unlock stays valid.', 1, 60),
            new SettingDefinition('offline_authorization_minutes', $int, 60, 'geofence', 'Minutes an offline collection authorization stays valid.', 5, 240),
            new SettingDefinition('float_default_limit_paise', $int, 10000000, 'float', 'Default cash limit for new collectors, in paise.', 100000, 100000000),
            new SettingDefinition('float_cap_mode', $enum, 'PROJECTED', 'float', 'How the float cap is applied.', options: ['PROJECTED', 'CURRENT']),
            new SettingDefinition('float_alert_percent', $int, 90, 'float', 'Float level that raises a high-cash alert, in percent.', 50, 100),
            new SettingDefinition('block_punch_in_with_carried_float', $bool, false, 'float', 'Refuse punch-in while cash from a previous day is still held.'),
            new SettingDefinition('free_cancel_seconds', $int, 180, 'penalty', 'Seconds after creation during which a retailer can cancel free.', 0, 1800),
            new SettingDefinition('penalty_amount_paise', $int, 5000, 'penalty', 'Default late-cancellation charge, in paise.', 5000, 20000),
            new SettingDefinition('penalty_collector_share_paise', $int, 3500, 'penalty', "Collector's share of the charge, in paise.", 0, 20000),
            new SettingDefinition('penalty_requires_dispatch', $bool, true, 'penalty', 'Charge only if a collector had accepted the pickup.'),
            new SettingDefinition('penalty_when_wallet_short', $enum, 'CHARGE_TO_UDHAR', 'penalty', 'What happens when the wallet cannot cover the charge.', options: ['CHARGE_TO_UDHAR', 'WAIVE']),
            new SettingDefinition('confirmation_reminder_minutes', SettingType::IntList, [5, 30], 'notifications', 'Minutes after submission when the retailer is reminded to confirm.', 1, 240),
            new SettingDefinition('confirmation_admin_alert_minutes', $int, 60, 'notifications', 'Minutes after submission when admins are alerted.', 5, 1440),
            new SettingDefinition('outstanding_reminder_time', SettingType::Time, '09:00', 'notifications', 'Daily outstanding reminder time (IST).'),
            new SettingDefinition('outstanding_reminder_antispam_hours', $int, 24, 'notifications', 'Minimum hours between outstanding reminders to one retailer.', 1, 168),
            new SettingDefinition('sos_nearby_radius_km', $int, 3, 'notifications', 'Colleagues within this distance are alerted on SOS, in km.', 1, 20),
            new SettingDefinition('gps_retention_days', $int, 90, 'retention', 'Days raw GPS points are kept.', 7, 365),
            new SettingDefinition('deposit_slip_required', $bool, true, 'finance', 'A deposit slip upload is required to record a bank deposit.'),
            new SettingDefinition('deposit_unverified_alert_days', $int, 2, 'finance', 'Days after which an unverified deposit is flagged.', 1, 30),
            new SettingDefinition('maker_checker_threshold_paise', $int, 1000000, 'finance', 'Manual adjustments above this need a second admin, in paise.', 0, 100000000),
            new SettingDefinition('integrity_enforcement', $enum, 'LOG', 'security', 'Play Integrity handling.', options: ['OFF', 'LOG', 'ENFORCE']),
            new SettingDefinition('device_auto_approve_first', $bool, false, 'security', "Approve a collector's first phone automatically."),
            new SettingDefinition('collector_token_hours', $int, 14, 'security', 'Hours a collector session lasts.', 1, 24),
            new SettingDefinition('retailer_token_days', $int, 30, 'security', 'Days a retailer session lasts.', 1, 90),
            new SettingDefinition('admin_idle_minutes', $int, 30, 'security', 'Minutes of inactivity before an admin is logged out.', 5, 240),
            new SettingDefinition('min_app_version_collector', SettingType::Version, '1.0.0', 'app', 'Oldest collector app version allowed.'),
            new SettingDefinition('min_app_version_retailer', SettingType::Version, '1.0.0', 'app', 'Oldest retailer app version allowed.'),
        ];

        $map = [];
        foreach ($definitions as $definition) {
            $map[$definition->key] = $definition;
        }

        return self::$definitions = $map;
    }
}
```

Create `app/Support/Settings/Settings.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class Settings
{
    private const CACHE_KEY = 'settings.values';

    public function __construct(private readonly AuditLogger $audit) {}

    public function get(string $key): mixed
    {
        $definition = SettingsCatalog::get($key);
        $stored = $this->stored();

        return array_key_exists($key, $stored) ? $stored[$key] : $definition->default;
    }

    public function set(string $key, mixed $value, ?User $by = null): void
    {
        $definition = SettingsCatalog::get($key);
        $definition->validate($value);
        $this->assertConsistent($key, $value);

        DB::transaction(function () use ($key, $value, $definition, $by): void {
            $before = $this->get($key);

            Setting::query()->updateOrCreate(['key' => $key], [
                'value' => $value,
                'type' => $definition->type->value,
                'group' => $definition->group,
                'description' => $definition->description,
                'updated_by_user_id' => $by?->id,
            ]);

            $this->audit->record('SETTINGS.UPDATED', null, ['key' => $key, 'value' => $before], ['key' => $key, 'value' => $value]);
        });

        Cache::forget(self::CACHE_KEY);
        DB::afterCommit(static fn () => Cache::forget(self::CACHE_KEY));
    }

    /** Rules that span two settings. */
    private function assertConsistent(string $key, mixed $value): void
    {
        if ($key === 'penalty_collector_share_paise' && $value > $this->get('penalty_amount_paise')) {
            throw new InvalidArgumentException("Setting [{$key}] cannot exceed penalty_amount_paise.");
        }

        if ($key === 'penalty_amount_paise' && $value < $this->get('penalty_collector_share_paise')) {
            throw new InvalidArgumentException("Setting [{$key}] cannot be below penalty_collector_share_paise.");
        }
    }

    /**
     * Values are cached only outside transactions, so a rolled-back change can never be cached.
     *
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        $load = static fn (): array => Setting::query()->pluck('value', 'key')->all();

        return DB::transactionLevel() > 0 ? $load() : Cache::rememberForever(self::CACHE_KEY, $load);
    }
}
```

Create `config/cms.php`:

```php
<?php

declare(strict_types=1);

return [
    /*
     * Business rules that must not be changeable from the admin panel (spec §11.1).
     */
    'geofence_radius_m' => 100,
];
```

Replace `app/Http/Controllers/Api/V1/MetaController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Support\Api\ApiResponse;
use App\Support\Settings\Settings;
use Illuminate\Http\JsonResponse;

final class MetaController
{
    public function __invoke(Settings $settings): JsonResponse
    {
        return ApiResponse::success([
            'api_version' => 'v1',
            'server_time' => ApiResponse::serverTime(),
            'min_app_version' => [
                'collector' => $settings->get('min_app_version_collector'),
                'retailer' => $settings->get('min_app_version_retailer'),
            ],
            'denominations_paise' => $settings->get('enabled_denominations_paise'),
            'broadcast_window_seconds' => $settings->get('broadcast_window_seconds'),
            'geofence_radius_m' => config('cms.geofence_radius_m'),
        ]);
    }
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter="SettingsTest|MetaTest"`
Expected: PASS (17 tests, counting each data-provider case).

- [ ] **Step 6: Run the gates and commit**

Run: `composer lint && composer analyse && php artisan test` → Expected: all green.

```bash
git add backend-laravel
git commit -m "feat(settings): add validated, audited settings catalog and app metadata"
```

---

### Task 6: Collectors, retailers, shop logins and zones

**Files:**
- Create: `database/migrations/2026_10_02_000300_create_territories_and_zones_tables.php`, `2026_10_02_000400_create_collectors_tables.php`, `2026_10_02_000500_create_retailers_tables.php`
- Create enums: `app/Enums/RecordStatus.php`, `TerritoryType.php`, `CollectorStatus.php`, `RetailerStatus.php`, `KycStatus.php`, `ShopRole.php`, `LocationSource.php`
- Create models: `app/Models/Territory.php`, `Zone.php`, `Collector.php`, `Retailer.php`, `RetailerUser.php`, `RetailerLocation.php`
- Modify: `app/Models/User.php` (relations)
- Create factories: `database/factories/TerritoryFactory.php`, `ZoneFactory.php`, `CollectorFactory.php`, `RetailerFactory.php`, `RetailerUserFactory.php`
- Create: `app/Services/Geo/ZoneResolver.php`
- Test: `tests/Feature/Actors/ActorSchemaTest.php`, `tests/Feature/Geo/ZoneResolverTest.php`

**Interfaces:**
- Consumes: `User`, `HasPublicId` (Task 3).
- Produces:
  - Models with relations: `User::collector(): HasOne`, `User::shopMembership(): HasOne`; `Collector::user()`, `Collector::zones()`; `Retailer::members()`, `Retailer::owner()` (active OWNER membership), `Retailer::zone()`, `Retailer::territory()`, `Retailer::defaultCollector()`, `Retailer::locations()`; `RetailerUser::retailer()`, `RetailerUser::user()`; `Zone::territory()`.
  - `Zone::polygonExpression(array $ring): Expression` and `Zone::geoJson(array $ring): array` where `$ring` is a list of `[lat, lng]` pairs.
  - `ZoneResolver::resolve(float $lat, float $lng): ?Zone`
  - Factories: `Collector::factory()->suspended()`, `RetailerUser::factory()->staff()`, `->inactive()`, `Retailer::factory()->blocked()`, `Zone::factory()->ring(array $ring)`.
  - Enums: `RecordStatus{Active,Inactive}`, `CollectorStatus{Active,Suspended,Inactive}`, `RetailerStatus{Active,Blocked,Inactive}`, `KycStatus{Pending,Verified,Rejected}`, `ShopRole{Owner,Staff}`, `TerritoryType{City,Area,Ward}`, `LocationSource{Import,Admin,Onboarding}`.

Collector duty, GPS and float-balance columns arrive in M1 with the logic that writes them.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Actors/ActorSchemaTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Actors;

use App\Models\Collector;
use App\Models\Retailer;
use App\Models\RetailerUser;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ActorSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_shop_can_have_only_one_active_owner(): void
    {
        $owner = RetailerUser::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        RetailerUser::factory()->for($owner->retailer)->create();
    }

    public function test_a_new_owner_is_allowed_once_the_old_owner_is_inactive(): void
    {
        $old = RetailerUser::factory()->inactive()->create();

        $new = RetailerUser::factory()->for($old->retailer)->create();

        $this->assertTrue($old->retailer->owner->is($new));
    }

    public function test_a_shop_can_have_many_staff_logins(): void
    {
        $owner = RetailerUser::factory()->create();

        RetailerUser::factory()->count(3)->staff()->for($owner->retailer)->create();

        $this->assertSame(4, $owner->retailer->members()->count());
        $this->assertTrue($owner->retailer->owner->is($owner));
    }

    public function test_one_login_belongs_to_exactly_one_shop(): void
    {
        $membership = RetailerUser::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        RetailerUser::factory()->for($membership->user)->create();
    }

    public function test_staff_cannot_spend_by_default(): void
    {
        $staff = RetailerUser::factory()->staff()->create();

        $this->assertFalse($staff->can_spend);
        $this->assertFalse($staff->can_manage_staff);
        $this->assertTrue($staff->can_confirm);
    }

    public function test_collector_codes_are_unique(): void
    {
        Collector::factory()->create(['collector_code' => 'COL-104']);

        $this->expectException(UniqueConstraintViolationException::class);

        Collector::factory()->create(['collector_code' => 'COL-104']);
    }

    public function test_the_retailer_penalty_override_must_stay_between_50_and_200_rupees(): void
    {
        $this->expectException(QueryException::class);

        Retailer::factory()->create(['penalty_amount_paise' => 30000]);
    }

    public function test_the_retailer_collector_share_cannot_exceed_the_penalty(): void
    {
        $this->expectException(QueryException::class);

        Retailer::factory()->create(['penalty_amount_paise' => 5000, 'penalty_collector_share_paise' => 6000]);
    }

    public function test_a_collector_login_has_one_collector_profile(): void
    {
        $collector = Collector::factory()->create();

        $this->assertTrue($collector->user->collector->is($collector));
        $this->assertSame('collector', $collector->user->role->value);
    }
}
```

Create `tests/Feature/Geo/ZoneResolverTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use App\Enums\RecordStatus;
use App\Models\Zone;
use App\Services\Geo\ZoneResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ZoneResolverTest extends TestCase
{
    use RefreshDatabase;

    /** A rectangle 0.10° of latitude by 0.20° of longitude: a swap of x and y cannot go unnoticed. */
    private const RING = [[28.80, 76.50], [28.80, 76.70], [28.90, 76.70], [28.90, 76.50]];

    public function test_it_finds_the_active_zone_containing_a_point(): void
    {
        $ward = Zone::factory()->ring(self::RING)->create(['name' => 'Ward 4']);

        $this->assertTrue(app(ZoneResolver::class)->resolve(28.85, 76.65)?->is($ward));
    }

    public function test_points_outside_every_zone_resolve_to_null(): void
    {
        Zone::factory()->ring(self::RING)->create();

        $this->assertNull(app(ZoneResolver::class)->resolve(28.95, 76.65));
        $this->assertNull(app(ZoneResolver::class)->resolve(28.85, 76.75));
    }

    public function test_inactive_zones_are_ignored(): void
    {
        Zone::factory()->ring(self::RING)->create(['status' => RecordStatus::Inactive]);

        $this->assertNull(app(ZoneResolver::class)->resolve(28.85, 76.65));
    }

    public function test_polygons_are_stored_as_longitude_then_latitude(): void
    {
        $zone = Zone::factory()->ring(self::RING)->create();

        $wkt = (string) DB::scalar('select ST_AsText(boundary) from zones where id = ?', [$zone->id]);

        $this->assertStringStartsWith('POLYGON((76.5 28.8,76.7 28.8', $wkt);
        $this->assertSame([76.5, 28.8], $zone->boundary_geojson['coordinates'][0][0]);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter="ActorSchemaTest|ZoneResolverTest"`
Expected: FAIL — `Class "App\Models\RetailerUser" not found`.

- [ ] **Step 3: Create the migrations**

Create `database/migrations/2026_10_02_000300_create_territories_and_zones_tables.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('territories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('territories');
            $table->string('type', 20);
            $table->string('name', 120);
            $table->string('code', 40)->unique();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('territory_id')->constrained('territories');
            $table->string('name', 120);
            $table->string('code', 40)->unique();
            $table->geometry('boundary', subtype: 'polygon');
            $table->json('boundary_geojson');
            $table->string('color', 9)->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();

            $table->spatialIndex('boundary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
        Schema::dropIfExists('territories');
    }
};
```

Create `database/migrations/2026_10_02_000400_create_collectors_tables.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collectors', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('collector_code', 20)->unique();
            $table->string('employee_id', 40)->nullable()->unique();
            $table->string('vehicle_type', 20)->nullable();
            $table->string('vehicle_number', 20)->nullable();
            $table->unsignedBigInteger('float_limit_paise')->default(10000000);
            $table->string('status', 20)->default('ACTIVE');
            $table->string('emergency_contact_name', 120)->nullable();
            $table->string('emergency_contact_mobile', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('collector_zones', function (Blueprint $table): void {
            $table->foreignId('collector_id')->constrained('collectors');
            $table->foreignId('zone_id')->constrained('zones');
            $table->boolean('is_primary')->default(false);
            $table->primary(['collector_id', 'zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_zones');
        Schema::dropIfExists('collectors');
    }
};
```

Create `database/migrations/2026_10_02_000500_create_retailers_tables.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retailers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('retailer_code', 20)->unique();
            $table->string('shop_name', 160);
            $table->string('owner_name', 120);
            $table->string('mobile', 16)->unique();
            $table->string('alt_mobile', 16)->nullable();
            $table->string('address', 255);
            $table->string('landmark', 160)->nullable();
            $table->string('city', 80);
            $table->string('pincode', 6);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->foreignId('zone_id')->nullable()->constrained('zones');
            $table->foreignId('territory_id')->nullable()->constrained('territories');
            $table->foreignId('default_collector_id')->nullable()->constrained('collectors');
            $table->string('gstin', 15)->nullable()->unique();
            $table->text('pan_encrypted')->nullable();
            $table->string('kyc_status', 20)->default('PENDING');
            $table->string('status', 20)->default('ACTIVE');
            $table->unsignedInteger('penalty_amount_paise')->nullable();
            $table->unsignedInteger('penalty_collector_share_paise')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['zone_id', 'status']);
        });

        DB::statement('ALTER TABLE retailers ADD CONSTRAINT chk_retailers_penalty_amount CHECK (penalty_amount_paise IS NULL OR penalty_amount_paise BETWEEN 5000 AND 20000)');
        DB::statement('ALTER TABLE retailers ADD CONSTRAINT chk_retailers_penalty_share CHECK (penalty_collector_share_paise IS NULL OR penalty_collector_share_paise <= COALESCE(penalty_amount_paise, 20000))');

        Schema::create('retailer_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retailer_id')->constrained('retailers');
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('shop_role', 10);
            $table->boolean('can_request')->default(true);
            $table->boolean('can_confirm')->default(true);
            $table->boolean('can_spend')->default(false);
            $table->boolean('can_manage_staff')->default(false);
            $table->string('status', 20)->default('ACTIVE');
            $table->tinyInteger('owner_marker')->nullable()->storedAs("CASE WHEN shop_role = 'OWNER' AND status = 'ACTIVE' THEN 1 ELSE NULL END");
            $table->foreignId('created_by_user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['retailer_id', 'owner_marker']);
        });

        Schema::create('retailer_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retailer_id')->constrained('retailers');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('source', 20);
            $table->string('reason', 255)->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users');
            $table->dateTime('effective_from', 6);
            $table->timestamp('created_at')->nullable();

            $table->index(['retailer_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retailer_locations');
        Schema::dropIfExists('retailer_users');
        Schema::dropIfExists('retailers');
    }
};
```

- [ ] **Step 4: Add the enums**

Create each file in `app/Enums/` (all start with `<?php`, `declare(strict_types=1);`, `namespace App\Enums;`):

```php
// RecordStatus.php
enum RecordStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
}

// TerritoryType.php
enum TerritoryType: string
{
    case City = 'CITY';
    case Area = 'AREA';
    case Ward = 'WARD';
}

// CollectorStatus.php
enum CollectorStatus: string
{
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
    case Inactive = 'INACTIVE';
}

// RetailerStatus.php
enum RetailerStatus: string
{
    case Active = 'ACTIVE';
    case Blocked = 'BLOCKED';
    case Inactive = 'INACTIVE';
}

// KycStatus.php
enum KycStatus: string
{
    case Pending = 'PENDING';
    case Verified = 'VERIFIED';
    case Rejected = 'REJECTED';
}

// ShopRole.php
enum ShopRole: string
{
    case Owner = 'OWNER';
    case Staff = 'STAFF';
}

// LocationSource.php
enum LocationSource: string
{
    case Import = 'IMPORT';
    case Admin = 'ADMIN';
    case Onboarding = 'ONBOARDING';
}
```

- [ ] **Step 5: Add the models**

Create `app/Models/Territory.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use App\Enums\TerritoryType;
use Database\Factories\TerritoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Territory extends Model
{
    /** @use HasFactory<TerritoryFactory> */
    use HasFactory;

    protected $fillable = ['parent_id', 'type', 'name', 'code', 'status'];

    protected function casts(): array
    {
        return ['type' => TerritoryType::class, 'status' => RecordStatus::class];
    }

    /** @return BelongsTo<Territory, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Territory::class, 'parent_id');
    }

    /** @return HasMany<Territory, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Territory::class, 'parent_id');
    }

    /** @return HasMany<Zone, $this> */
    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }
}
```

Create `app/Models/Zone.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * An operational polygon such as "Ward 4" or "Rural East" (spec §11.7).
 * Stored as SRID-0 POLYGON with x = longitude and y = latitude.
 */
class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use HasFactory;

    protected $fillable = ['territory_id', 'name', 'code', 'boundary', 'boundary_geojson', 'color', 'status'];

    /** The raw WKB polygon is binary and never serialised. */
    protected $hidden = ['boundary'];

    protected function casts(): array
    {
        return ['boundary_geojson' => 'array', 'status' => RecordStatus::class];
    }

    /** @return BelongsTo<Territory, $this> */
    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    /**
     * SQL for a polygon built from [lat, lng] corners. Coordinates are formatted as numbers,
     * so the expression cannot carry injected SQL.
     *
     * @param  list<array{0: float, 1: float}>  $ring
     */
    public static function polygonExpression(array $ring): Expression
    {
        $points = array_map(
            static fn (array $corner): string => sprintf('%.7F %.7F', $corner[1], $corner[0]),
            self::closedRing($ring),
        );

        return DB::raw(sprintf("ST_GeomFromText('POLYGON((%s))')", implode(', ', $points)));
    }

    /**
     * @param  list<array{0: float, 1: float}>  $ring
     * @return array{type: string, coordinates: list<list<array{0: float, 1: float}>>}
     */
    public static function geoJson(array $ring): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [array_map(static fn (array $corner): array => [$corner[1], $corner[0]], self::closedRing($ring))],
        ];
    }

    /**
     * @param  list<array{0: float, 1: float}>  $ring
     * @return list<array{0: float, 1: float}>
     */
    private static function closedRing(array $ring): array
    {
        if (count($ring) < 3) {
            throw new InvalidArgumentException('A zone needs at least three corners.');
        }

        if ($ring[0] !== $ring[array_key_last($ring)]) {
            $ring[] = $ring[0];
        }

        return $ring;
    }
}
```

Create `app/Models/Collector.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CollectorStatus;
use App\Models\Concerns\HasPublicId;
use Database\Factories\CollectorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Collector extends Model
{
    /** @use HasFactory<CollectorFactory> */
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'user_id', 'collector_code', 'employee_id', 'vehicle_type', 'vehicle_number', 'float_limit_paise',
        'status', 'emergency_contact_name', 'emergency_contact_mobile',
    ];

    protected function casts(): array
    {
        return ['status' => CollectorStatus::class, 'float_limit_paise' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Zone, $this> */
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(Zone::class, 'collector_zones')->withPivot('is_primary');
    }
}
```

Create `app/Models/Retailer.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KycStatus;
use App\Enums\RecordStatus;
use App\Enums\RetailerStatus;
use App\Enums\ShopRole;
use App\Models\Concerns\HasPublicId;
use Database\Factories\RetailerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Retailer extends Model
{
    /** @use HasFactory<RetailerFactory> */
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'retailer_code', 'shop_name', 'owner_name', 'mobile', 'alt_mobile', 'address', 'landmark', 'city', 'pincode',
        'lat', 'lng', 'zone_id', 'territory_id', 'default_collector_id', 'gstin', 'pan_encrypted', 'kyc_status', 'status',
        'penalty_amount_paise', 'penalty_collector_share_paise',
    ];

    protected $hidden = ['pan_encrypted'];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'pan_encrypted' => 'encrypted',
            'kyc_status' => KycStatus::class,
            'status' => RetailerStatus::class,
            'penalty_amount_paise' => 'integer',
            'penalty_collector_share_paise' => 'integer',
        ];
    }

    /** @return HasMany<RetailerUser, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(RetailerUser::class);
    }

    /** @return HasOne<RetailerUser, $this> */
    public function owner(): HasOne
    {
        return $this->hasOne(RetailerUser::class)
            ->where('shop_role', ShopRole::Owner->value)
            ->where('status', RecordStatus::Active->value);
    }

    /** @return BelongsTo<Zone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /** @return BelongsTo<Territory, $this> */
    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    /** @return BelongsTo<Collector, $this> */
    public function defaultCollector(): BelongsTo
    {
        return $this->belongsTo(Collector::class, 'default_collector_id');
    }

    /** @return HasMany<RetailerLocation, $this> */
    public function locations(): HasMany
    {
        return $this->hasMany(RetailerLocation::class);
    }
}
```

Create `app/Models/RetailerUser.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use App\Enums\ShopRole;
use Database\Factories\RetailerUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A login attached to a shop as OWNER or STAFF (decision D4). Both have the RETAILER role. */
class RetailerUser extends Model
{
    /** @use HasFactory<RetailerUserFactory> */
    use HasFactory;

    protected $fillable = [
        'retailer_id', 'user_id', 'shop_role', 'can_request', 'can_confirm', 'can_spend', 'can_manage_staff',
        'status', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'shop_role' => ShopRole::class,
            'status' => RecordStatus::class,
            'can_request' => 'boolean',
            'can_confirm' => 'boolean',
            'can_spend' => 'boolean',
            'can_manage_staff' => 'boolean',
        ];
    }

    /** @return BelongsTo<Retailer, $this> */
    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

Create `app/Models/RetailerLocation.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LocationSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only history of a shop's GPS pin. */
class RetailerLocation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['retailer_id', 'lat', 'lng', 'source', 'reason', 'changed_by_user_id', 'effective_from'];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'source' => LocationSource::class,
            'effective_from' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Retailer, $this> */
    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }
}
```

In `app/Models/User.php`, add the imports `Illuminate\Database\Eloquent\Relations\HasOne` and these methods:

```php
    /** @return HasOne<Collector, $this> */
    public function collector(): HasOne
    {
        return $this->hasOne(Collector::class);
    }

    /** @return HasOne<RetailerUser, $this> */
    public function shopMembership(): HasOne
    {
        return $this->hasOne(RetailerUser::class);
    }
```

- [ ] **Step 6: Add the factories and the zone resolver**

Create `database/factories/TerritoryFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Enums\TerritoryType;
use App\Models\Territory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Territory>
 */
class TerritoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'type' => TerritoryType::City,
            'name' => 'Rohtak',
            'code' => 'T-'.fake()->unique()->bothify('####??'),
            'status' => RecordStatus::Active,
        ];
    }
}
```

Create `database/factories/ZoneFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Territory;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    /** A block around central Rohtak, as [lat, lng] corners. */
    private const ROHTAK = [[28.86, 76.56], [28.86, 76.66], [28.92, 76.66], [28.92, 76.56]];

    public function definition(): array
    {
        return [
            'territory_id' => Territory::factory(),
            'name' => 'Ward '.fake()->unique()->numberBetween(1, 9999),
            'code' => 'Z-'.fake()->unique()->bothify('####??'),
            'boundary' => Zone::polygonExpression(self::ROHTAK),
            'boundary_geojson' => Zone::geoJson(self::ROHTAK),
            'status' => RecordStatus::Active,
        ];
    }

    /**
     * @param  list<array{0: float, 1: float}>  $ring
     */
    public function ring(array $ring): static
    {
        return $this->state(fn (): array => [
            'boundary' => Zone::polygonExpression($ring),
            'boundary_geojson' => Zone::geoJson($ring),
        ]);
    }
}
```

Create `database/factories/CollectorFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CollectorStatus;
use App\Models\Collector;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Collector>
 */
class CollectorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->collector(),
            'collector_code' => 'COL-'.fake()->unique()->numerify('####'),
            'employee_id' => 'EMP-'.fake()->unique()->numerify('#####'),
            'vehicle_type' => 'BIKE',
            'vehicle_number' => 'HR12'.strtoupper(fake()->bothify('??####')),
            'float_limit_paise' => 10000000,
            'status' => CollectorStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => CollectorStatus::Suspended]);
    }
}
```

Create `database/factories/RetailerFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\KycStatus;
use App\Enums\RetailerStatus;
use App\Models\Retailer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Retailer>
 */
class RetailerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'retailer_code' => 'RET-'.fake()->unique()->numerify('#####'),
            'shop_name' => fake()->company().' Digital Store',
            'owner_name' => fake()->name(),
            'mobile' => '+91'.fake()->unique()->numerify('8#########'),
            'address' => fake()->streetAddress(),
            'city' => 'Rohtak',
            'pincode' => '124001',
            'lat' => fake()->latitude(28.86, 28.92),
            'lng' => fake()->longitude(76.56, 76.66),
            'kyc_status' => KycStatus::Pending,
            'status' => RetailerStatus::Active,
        ];
    }

    public function blocked(): static
    {
        return $this->state(fn (): array => ['status' => RetailerStatus::Blocked]);
    }
}
```

Create `database/factories/RetailerUserFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Enums\ShopRole;
use App\Models\Retailer;
use App\Models\RetailerUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RetailerUser>
 */
class RetailerUserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'retailer_id' => Retailer::factory(),
            'user_id' => User::factory()->retailer(),
            'shop_role' => ShopRole::Owner,
            'can_request' => true,
            'can_confirm' => true,
            'can_spend' => true,
            'can_manage_staff' => true,
            'status' => RecordStatus::Active,
        ];
    }

    public function staff(): static
    {
        return $this->state(fn (): array => [
            'shop_role' => ShopRole::Staff,
            'can_spend' => false,
            'can_manage_staff' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => RecordStatus::Inactive]);
    }
}
```

Create `app/Services/Geo/ZoneResolver.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Enums\RecordStatus;
use App\Models\Zone;

final class ZoneResolver
{
    /** The active zone containing the point, or null. POINT(x, y) takes longitude first. */
    public function resolve(float $lat, float $lng): ?Zone
    {
        return Zone::query()
            ->where('status', RecordStatus::Active->value)
            ->whereRaw('ST_Contains(boundary, POINT(?, ?))', [$lng, $lat])
            ->orderBy('id')
            ->first();
    }
}
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --filter="ActorSchemaTest|ZoneResolverTest"`
Expected: PASS (13 tests).

- [ ] **Step 8: Run the gates and commit**

Run: `composer lint && composer analyse && php artisan test` → Expected: all green.

```bash
git add backend-laravel
git commit -m "feat(actors): add collectors, retailers, shop logins and zone polygons"
```

---

### Task 7: Collector devices and request-signature verification

**Files:**
- Create: `database/migrations/2026_10_02_000600_create_devices_tables.php`
- Create: `app/Enums/DeviceStatus.php`, `app/Models/Device.php`, `database/factories/DeviceFactory.php`
- Create: `app/Services/Auth/DeviceSignatureVerifier.php`, `app/Console/Commands/PruneRequestNoncesCommand.php`
- Modify: `routes/console.php`
- Create: `tests/Support/DeviceKeyPair.php`, `tests/Support/SignsDeviceRequests.php`
- Test: `tests/Feature/Security/DeviceSignatureVerifierTest.php`, `tests/Feature/Security/DeviceSchemaTest.php`

**Interfaces:**
- Consumes: `User` (Task 3), `AuditLogger` (Task 4), `ApiException` (Task 2).
- Produces:
  - `enum DeviceStatus: string { PendingApproval='PENDING_APPROVAL'; Active='ACTIVE'; Revoked='REVOKED' }`
  - `Device` model (`public_id`, `user_id`, `fingerprint_hash`, `public_key_pem`, `status`, …) with `user()`; factory states `active()`, `pending()`, `revoked()`, `withPublicKey(string $pem)`
  - `DeviceSignatureVerifier::canonicalString(string $method, string $requestUri, string $body, string $timestamp, string $nonce, string $devicePublicId): string`
  - `DeviceSignatureVerifier::verify(Request $request, Device $device): void` — throws `ApiException` `SIGNATURE_INVALID` (401) or `REPLAY_DETECTED` (401); consumes the nonce
  - Test helpers `Tests\Support\DeviceKeyPair::generate()`, `->sign(string)`, `->publicKeyPem`; trait `Tests\Support\SignsDeviceRequests` with `deviceSignatureHeaders(...)` and `signedJson(...)`
  - Command `security:prune-nonces` (hourly)

- [ ] **Step 1: Add the test helpers**

Create `tests/Support/DeviceKeyPair.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/** An EC P-256 key pair standing in for a phone's Android Keystore key. */
final class DeviceKeyPair
{
    private function __construct(
        public readonly string $privateKeyPem,
        public readonly string $publicKeyPem,
    ) {}

    public static function generate(): self
    {
        $options = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];
        $config = self::opensslConfig();
        if ($config !== null) {
            $options['config'] = $config;
        }

        $key = openssl_pkey_new($options);
        if ($key === false) {
            throw new RuntimeException('Could not generate an EC key: '.openssl_error_string());
        }

        openssl_pkey_export($key, $private, null, $options);
        $details = openssl_pkey_get_details($key);

        return new self((string) $private, (string) $details['key']);
    }

    public function sign(string $data): string
    {
        openssl_sign($data, $signature, $this->privateKeyPem, OPENSSL_ALGO_SHA256);

        return base64_encode((string) $signature);
    }

    /** Windows PHP builds need an explicit openssl.cnf to generate keys. */
    private static function opensslConfig(): ?string
    {
        if (getenv('OPENSSL_CONF') !== false || PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        $candidate = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf';

        return is_file($candidate) ? $candidate : null;
    }
}
```

Create `tests/Support/SignsDeviceRequests.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Device;
use App\Services\Auth\DeviceSignatureVerifier;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait SignsDeviceRequests
{
    /**
     * @return array<string, string>
     */
    protected function deviceSignatureHeaders(
        DeviceKeyPair $keys,
        string $devicePublicId,
        string $method,
        string $uri,
        string $body,
        ?int $timestamp = null,
        ?string $nonce = null,
    ): array {
        $timestamp ??= now()->getTimestamp();
        $nonce ??= Str::random(32);
        $canonical = DeviceSignatureVerifier::canonicalString($method, $uri, $body, (string) $timestamp, $nonce, $devicePublicId);

        return [
            'X-Device-Id' => $devicePublicId,
            'X-Timestamp' => (string) $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => $keys->sign($canonical),
        ];
    }

    /**
     * Sends a JSON request whose method, URI and exact body are signed by the device key.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function signedJson(string $method, string $uri, array $data, DeviceKeyPair $keys, Device $device, array $headers = []): TestResponse
    {
        $body = $data === [] ? '' : (string) json_encode($data);
        $server = $this->transformHeadersToServerVars(array_merge(
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            $this->deviceSignatureHeaders($keys, $device->public_id, $method, $uri, $body),
            $headers,
        ));

        return $this->call($method, $uri, [], [], [], $server, $body);
    }
}
```

- [ ] **Step 2: Write the failing tests**

Create `tests/Feature/Security/DeviceSignatureVerifierTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Exceptions\ApiException;
use App\Models\AuditLog;
use App\Models\Device;
use App\Services\Auth\DeviceSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\DeviceKeyPair;
use Tests\Support\SignsDeviceRequests;
use Tests\TestCase;

final class DeviceSignatureVerifierTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    private const URI = '/api/v1/collector/duty/punch-in?source=app';

    private const BODY = '{"odometer_km":1200}';

    private DeviceKeyPair $keys;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keys = DeviceKeyPair::generate();
        $this->device = Device::factory()->active()->withPublicKey($this->keys->publicKeyPem)->create();
    }

    public function test_a_correctly_signed_request_is_accepted_and_its_nonce_is_consumed(): void
    {
        $request = $this->request(self::URI, self::BODY, $this->headers());

        app(DeviceSignatureVerifier::class)->verify($request, $this->device);

        $this->assertDatabaseHas('request_nonces', ['device_id' => $this->device->id, 'nonce' => $request->header('X-Nonce')]);
    }

    public function test_a_changed_body_is_rejected(): void
    {
        $this->assertRejected($this->request(self::URI, '{"odometer_km":9999}', $this->headers()), 'SIGNATURE_INVALID');
    }

    public function test_a_changed_uri_is_rejected(): void
    {
        $this->assertRejected($this->request('/api/v1/collector/duty/punch-out', self::BODY, $this->headers()), 'SIGNATURE_INVALID');
    }

    public function test_a_signature_from_another_key_is_rejected(): void
    {
        $headers = $this->deviceSignatureHeaders(DeviceKeyPair::generate(), $this->device->public_id, 'POST', self::URI, self::BODY);

        $this->assertRejected($this->request(self::URI, self::BODY, $headers), 'SIGNATURE_INVALID');
    }

    public function test_a_phone_clock_more_than_two_minutes_off_gets_a_clear_message(): void
    {
        $headers = $this->headers(timestamp: now()->subSeconds(121)->getTimestamp());

        $exception = $this->assertRejected($this->request(self::URI, self::BODY, $headers), 'SIGNATURE_INVALID');

        $this->assertStringContainsString('date and time', $exception->getMessage());
    }

    public function test_a_phone_clock_just_under_two_minutes_off_is_tolerated(): void
    {
        $headers = $this->headers(timestamp: now()->subSeconds(119)->getTimestamp());

        app(DeviceSignatureVerifier::class)->verify($this->request(self::URI, self::BODY, $headers), $this->device);

        $this->assertDatabaseCount('request_nonces', 1);
    }

    public function test_a_replayed_nonce_is_rejected_and_audited(): void
    {
        $headers = $this->headers();
        app(DeviceSignatureVerifier::class)->verify($this->request(self::URI, self::BODY, $headers), $this->device);

        $this->assertRejected($this->request(self::URI, self::BODY, $headers), 'REPLAY_DETECTED');
        $this->assertSame(1, AuditLog::query()->where('action', 'SECURITY.REPLAY_DETECTED')->count());
    }

    public function test_missing_or_malformed_headers_are_rejected(): void
    {
        $headers = $this->headers();
        unset($headers['X-Signature']);
        $this->assertRejected($this->request(self::URI, self::BODY, $headers), 'SIGNATURE_INVALID');

        $this->assertRejected($this->request(self::URI, self::BODY, $this->headers(nonce: 'short')), 'SIGNATURE_INVALID');
    }

    public function test_expired_nonces_are_pruned(): void
    {
        DB::table('request_nonces')->insert([
            ['device_id' => $this->device->id, 'nonce' => str_repeat('a', 32), 'expires_at' => now()->subMinute()],
            ['device_id' => $this->device->id, 'nonce' => str_repeat('b', 32), 'expires_at' => now()->addMinutes(5)],
        ]);

        $this->artisan('security:prune-nonces')->assertSuccessful();

        $this->assertSame([str_repeat('b', 32)], DB::table('request_nonces')->pluck('nonce')->all());
    }

    /**
     * @return array<string, string>
     */
    private function headers(?int $timestamp = null, ?string $nonce = null): array
    {
        return $this->deviceSignatureHeaders($this->keys, $this->device->public_id, 'POST', self::URI, self::BODY, $timestamp, $nonce);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function request(string $uri, string $body, array $headers): Request
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return Request::create($uri, 'POST', [], [], [], $server, $body);
    }

    private function assertRejected(Request $request, string $code): ApiException
    {
        try {
            app(DeviceSignatureVerifier::class)->verify($request, $this->device);
        } catch (ApiException $e) {
            $this->assertSame($code, $e->errorCode);
            $this->assertSame(401, $e->status);

            return $e;
        }

        $this->fail("Expected the request to be rejected with {$code}.");
    }
}
```

Create `tests/Feature/Security/DeviceSchemaTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeviceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_collector_can_have_only_one_active_phone(): void
    {
        $user = User::factory()->collector()->create();
        Device::factory()->active()->for($user)->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Device::factory()->active()->for($user)->create();
    }

    public function test_one_phone_cannot_be_active_for_two_collectors(): void
    {
        $first = Device::factory()->active()->create(['fingerprint_hash' => str_repeat('f', 64)]);

        $this->expectException(UniqueConstraintViolationException::class);

        Device::factory()->active()->create(['fingerprint_hash' => $first->fingerprint_hash]);
    }

    public function test_revoked_and_pending_phones_do_not_count_as_active(): void
    {
        $user = User::factory()->collector()->create();
        Device::factory()->revoked()->for($user)->create();
        Device::factory()->revoked()->for($user)->create();
        Device::factory()->pending()->for($user)->create();
        Device::factory()->active()->for($user)->create();

        $this->assertSame(4, Device::query()->where('user_id', $user->id)->count());
    }
}
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --filter="DeviceSignatureVerifierTest|DeviceSchemaTest"`
Expected: FAIL — `Class "App\Models\Device" not found`.

- [ ] **Step 4: Create the tables, enum, model and factory**

Create `database/migrations/2026_10_02_000600_create_devices_tables.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->string('platform', 10)->default('android');
            $table->char('fingerprint_hash', 64);
            $table->text('public_key_pem');
            $table->boolean('key_hardware_backed')->nullable();
            $table->boolean('attestation_ok')->nullable();
            $table->json('last_integrity_verdict')->nullable();
            $table->string('model', 80)->nullable();
            $table->string('manufacturer', 80)->nullable();
            $table->string('os_version', 20)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('status', 20);
            $table->tinyInteger('active_marker')->nullable()->storedAs("CASE WHEN status = 'ACTIVE' THEN 1 ELSE NULL END");
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users');
            $table->dateTime('revoked_at')->nullable();
            $table->string('revoke_reason', 120)->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'active_marker']);
            $table->unique(['fingerprint_hash', 'active_marker']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('request_nonces', function (Blueprint $table): void {
            $table->foreignId('device_id')->constrained('devices');
            $table->string('nonce', 64);
            $table->dateTime('expires_at');

            $table->primary(['device_id', 'nonce']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_nonces');
        Schema::dropIfExists('devices');
    }
};
```

Create `app/Enums/DeviceStatus.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum DeviceStatus: string
{
    case PendingApproval = 'PENDING_APPROVAL';
    case Active = 'ACTIVE';
    case Revoked = 'REVOKED';
}
```

Create `app/Models/Device.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Models\Concerns\HasPublicId;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A collector phone bound by its Keystore public key (spec §18.3). The server trusts
 * the registered key, never a device id the client reports.
 */
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'user_id', 'platform', 'fingerprint_hash', 'public_key_pem', 'key_hardware_backed', 'attestation_ok',
        'last_integrity_verdict', 'model', 'manufacturer', 'os_version', 'app_version', 'status',
    ];

    protected $hidden = ['public_key_pem', 'fingerprint_hash', 'active_marker'];

    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'key_hardware_backed' => 'boolean',
            'attestation_ok' => 'boolean',
            'last_integrity_verdict' => 'array',
            'approved_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

Create `database/factories/DeviceFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /** A valid P-256 public key for tests that never sign; signing tests pass their own via withPublicKey(). */
    private const FIXTURE_PUBLIC_KEY = <<<'PEM'
        -----BEGIN PUBLIC KEY-----
        MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEh8UQ+Env3Wg7jpXgk9VCoEgBGe8+
        3G/6/JG7k2VNIB/L9bS/UTz3F1MvM06U+iGYwi61YwSr8EWvtxFxn8TY6w==
        -----END PUBLIC KEY-----
        PEM;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->collector(),
            'platform' => 'android',
            'fingerprint_hash' => hash('sha256', fake()->unique()->uuid()),
            'public_key_pem' => self::FIXTURE_PUBLIC_KEY,
            'model' => 'Redmi Note 13',
            'manufacturer' => 'Xiaomi',
            'os_version' => '14',
            'app_version' => '1.0.0',
            'status' => DeviceStatus::PendingApproval,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => DeviceStatus::Active, 'approved_at' => now()]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => DeviceStatus::PendingApproval]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['status' => DeviceStatus::Revoked, 'revoked_at' => now(), 'revoke_reason' => 'test']);
    }

    public function withPublicKey(string $pem): static
    {
        return $this->state(fn (): array => ['public_key_pem' => $pem]);
    }
}
```

- [ ] **Step 5: Implement the verifier and the prune command**

Create `app/Services/Auth/DeviceSignatureVerifier.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Models\Device;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Verifies that a request was signed by a registered phone's Keystore key (spec §18.4)
 * and that it has not been seen before.
 */
final class DeviceSignatureVerifier
{
    public const MAX_CLOCK_SKEW_SECONDS = 120;

    public const NONCE_TTL_SECONDS = 600;

    public function __construct(private readonly AuditLogger $audit) {}

    public static function canonicalString(string $method, string $requestUri, string $body, string $timestamp, string $nonce, string $devicePublicId): string
    {
        return implode("\n", [strtoupper($method), $requestUri, hash('sha256', $body), $timestamp, $nonce, $devicePublicId]);
    }

    public function verify(Request $request, Device $device): void
    {
        $timestamp = (string) $request->header('X-Timestamp', '');
        $nonce = (string) $request->header('X-Nonce', '');
        $signature = base64_decode((string) $request->header('X-Signature', ''), true);

        if (! ctype_digit($timestamp) || preg_match('/^[A-Za-z0-9_-]{16,64}$/', $nonce) !== 1 || $signature === false || $signature === '') {
            throw self::invalid('The request signature is missing or malformed.');
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > self::MAX_CLOCK_SKEW_SECONDS) {
            throw self::invalid("The phone's clock is wrong. Turn on automatic date and time in the phone settings, then try again.");
        }

        $canonical = self::canonicalString(
            $request->getMethod(),
            $request->getRequestUri(),
            (string) $request->getContent(),
            $timestamp,
            $nonce,
            $device->public_id,
        );

        if (openssl_verify($canonical, $signature, $device->public_key_pem, OPENSSL_ALGO_SHA256) !== 1) {
            throw self::invalid('The request signature is not valid for this phone.');
        }

        try {
            DB::table('request_nonces')->insert([
                'device_id' => $device->id,
                'nonce' => $nonce,
                'expires_at' => now()->addSeconds(self::NONCE_TTL_SECONDS),
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->audit->record('SECURITY.REPLAY_DETECTED', $device, null, null, ['nonce' => $nonce]);

            throw new ApiException('REPLAY_DETECTED', 'This request was already processed.', 401);
        }
    }

    private static function invalid(string $message): ApiException
    {
        return new ApiException('SIGNATURE_INVALID', $message, 401);
    }
}
```

Create `app/Console/Commands/PruneRequestNoncesCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PruneRequestNoncesCommand extends Command
{
    protected $signature = 'security:prune-nonces';

    protected $description = 'Delete request nonces that can no longer be replayed';

    public function handle(): int
    {
        $deleted = DB::table('request_nonces')->where('expires_at', '<', now())->delete();
        $this->info("Deleted {$deleted} expired nonce(s).");

        return self::SUCCESS;
    }
}
```

Append to `routes/console.php`:

```php
Schedule::command('security:prune-nonces')->hourly();
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter="DeviceSignatureVerifierTest|DeviceSchemaTest"`
Expected: PASS (12 tests).

- [ ] **Step 7: Run the gates and commit**

Run: `composer lint && composer analyse && php artisan test` → Expected: all green.

```bash
git add backend-laravel
git commit -m "feat(security): add collector devices and signed-request verification with replay protection"
```

---

### Task 8: Retailer login, sessions and password change

**Files:**
- Create: `database/migrations/2026_10_02_000700_add_device_columns_to_personal_access_tokens_table.php`
- Create: `app/Models/PersonalAccessToken.php`, `app/Support/MobileNumber.php`, `app/Enums/AppClient.php`, `app/Enums/TokenAbility.php`
- Create: `app/Services/Auth/IssuedToken.php`, `app/Services/Auth/TokenIssuer.php`, `app/Services/Auth/RetailerMembershipGuard.php`, `app/Services/Auth/LoginService.php`
- Create: `app/Http/Requests/Auth/LoginRequest.php`, `app/Http/Requests/Auth/ChangePasswordRequest.php`, `app/Http/Resources/UserProfileResource.php`
- Create: `app/Http/Controllers/Api/V1/Auth/LoginController.php`, `LogoutController.php`, `MeController.php`, `ChangePasswordController.php`
- Create: `app/Http/Middleware/EnsurePasswordChanged.php`, `app/Http/Middleware/EnsureRetailerMembership.php`, `app/Listeners/RecordTokenIp.php`
- Modify: `app/Exceptions/ApiExceptionRenderer.php` (TOKEN_EXPIRED), `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `routes/api.php`
- Test: `tests/Unit/MobileNumberTest.php`, `tests/Feature/Auth/RetailerLoginTest.php`, `tests/Feature/Auth/SessionLifecycleTest.php`, `tests/Feature/Auth/PasswordChangeTest.php`, `tests/Feature/Auth/RetailerMembershipTest.php`

**Interfaces:**
- Consumes: `User`, `UserRole` (Task 3); `AuditLogger`, `AuditActor` (Task 4); `Settings` (Task 5); `RetailerUser`, `RecordStatus`, `RetailerStatus` (Task 6); `Device` (Task 7).
- Produces:
  - `MobileNumber::normalize(string): ?string` (→ `+91XXXXXXXXXX`), `MobileNumber::mask(string): string`
  - `enum AppClient: string { Retailer='retailer' }` (Task 9 adds `Collector`)
  - `enum TokenAbility: string { Retailer='retailer'; Collector='collector'; DeviceRegister='device:register' }`
  - `IssuedToken { string $plainText; CarbonImmutable $expiresAt; User $user; PersonalAccessToken $token }` with `IssuedToken::from(NewAccessToken, User)`
  - `TokenIssuer::issueRetailerToken(User): IssuedToken`
  - `RetailerMembershipGuard::activeMembershipFor(User): RetailerUser` (throws `FORBIDDEN` / `RETAILER_BLOCKED`)
  - `LoginService::login(string $mobile, string $password, AppClient $app, Request $request): IssuedToken`
  - `App\Models\PersonalAccessToken` with `device_id` and `device()`; registered as Sanctum's token model
  - Routes `POST /api/v1/auth/login` (throttle `auth-login`), `GET /auth/me`, `POST /auth/logout`, `POST /auth/password/change` (names `api.v1.auth.*`)
  - Middleware aliases `ability`, `password.changed`, `retailer.member` (sets request attribute `retailer_membership`)
  - Response data for login: `{token, expires_at, user}` where `user` is `UserProfileResource`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/MobileNumberTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MobileNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MobileNumberTest extends TestCase
{
    /**
     * @return array<string, array{string, string|null}>
     */
    public static function numbers(): array
    {
        return [
            'plain ten digits' => ['9812000004', '+919812000004'],
            'with +91 and spaces' => ['+91 98120 00004', '+919812000004'],
            'with trunk zero' => ['098120 00004', '+919812000004'],
            'with dashes' => ['91-98120-00004', '+919812000004'],
            'already normalised' => ['+919812000004', '+919812000004'],
            'landline-like start' => ['5812000004', null],
            'too short' => ['98120', null],
            'letters' => ['call me', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_indian_mobile_numbers_are_normalised(string $input, ?string $expected): void
    {
        $this->assertSame($expected, MobileNumber::normalize($input));
    }

    public function test_masking_keeps_only_the_last_four_digits(): void
    {
        $this->assertSame('+91******0004', MobileNumber::mask('+919812000004'));
    }
}
```

Create `tests/Feature/Auth/RetailerLoginTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\AppClient;
use App\Exceptions\ApiException;
use App\Models\AuditLog;
use App\Models\PersonalAccessToken;
use App\Models\Retailer;
use App\Models\RetailerUser;
use App\Models\User;
use App\Services\Auth\LoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RetailerLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Retailer@Pass1';

    public function test_a_shop_owner_logs_in_and_gets_a_30_day_token(): void
    {
        $owner = $this->member();

        $response = $this->login('9812000004')->assertOk()
            ->assertJsonPath('data.user.role', 'retailer')
            ->assertJsonPath('data.user.shop.shop_role', 'OWNER')
            ->assertJsonPath('data.user.shop.id', $owner->retailer->public_id);

        $token = PersonalAccessToken::findToken($response->json('data.token'));
        $this->assertNotNull($token);
        $this->assertSame(['retailer'], $token->abilities);
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), $token->expires_at->getTimestamp(), 5);
    }

    public function test_staff_log_in_but_cannot_spend(): void
    {
        $this->member(staff: true);

        $this->login('9812000004')->assertOk()
            ->assertJsonPath('data.user.shop.shop_role', 'STAFF')
            ->assertJsonPath('data.user.shop.can_spend', false)
            ->assertJsonPath('data.user.shop.can_confirm', true);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function everydayFormats(): array
    {
        return [
            'plain' => ['9812000004'],
            'spaced with +91' => ['+91 98120 00004'],
            'trunk zero' => ['098120 00004'],
            'dashes' => ['91-98120-00004'],
        ];
    }

    #[DataProvider('everydayFormats')]
    public function test_everyday_mobile_formats_reach_the_same_account(string $typed): void
    {
        $this->member();

        $this->login($typed)->assertOk();
    }

    public function test_a_wrong_password_and_an_unknown_mobile_get_the_same_answer(): void
    {
        $this->member();

        $wrong = $this->login('9812000004', 'not-the-password')->assertUnauthorized()->assertJsonPath('code', 'INVALID_CREDENTIALS');
        $unknown = $this->login('9812999999')->assertUnauthorized()->assertJsonPath('code', 'INVALID_CREDENTIALS');

        $this->assertSame($wrong->json('message'), $unknown->json('message'));
        $this->assertSame(2, AuditLog::query()->where('action', 'AUTH.LOGIN_FAILED')->count());
    }

    public function test_ten_wrong_passwords_lock_the_account_for_fifteen_minutes(): void
    {
        $this->member();
        $service = app(LoginService::class);

        for ($i = 0; $i < 10; $i++) {
            try {
                $service->login('9812000004', 'wrong', AppClient::Retailer, Request::create('/'));
            } catch (ApiException) {
                // expected
            }
        }

        try {
            $service->login('9812000004', self::PASSWORD, AppClient::Retailer, Request::create('/'));
            $this->fail('A locked account accepted the right password.');
        } catch (ApiException $e) {
            $this->assertSame('ACCOUNT_LOCKED', $e->errorCode);
            $this->assertStringContainsString('IST', $e->getMessage());
        }
        $this->assertSame(1, AuditLog::query()->where('action', 'AUTH.ACCOUNT_LOCKED')->count());

        $this->travel(16)->minutes();

        $this->assertNotSame('', $service->login('9812000004', self::PASSWORD, AppClient::Retailer, Request::create('/'))->plainText);
    }

    public function test_the_login_endpoint_is_rate_limited(): void
    {
        $this->member();

        for ($i = 0; $i < 5; $i++) {
            $this->login('9812000004', 'wrong')->assertUnauthorized();
        }

        $this->login('9812000004', 'wrong')->assertStatus(429)->assertJsonPath('code', 'RATE_LIMITED');
    }

    public function test_a_blocked_user_is_refused_only_after_the_right_password(): void
    {
        $this->member(userState: ['status' => 'BLOCKED']);

        $this->login('9812000004', 'wrong')->assertUnauthorized()->assertJsonPath('code', 'INVALID_CREDENTIALS');
        $this->login('9812000004')->assertForbidden()->assertJsonPath('code', 'ACCOUNT_BLOCKED');
    }

    public function test_a_blocked_shop_cannot_log_in(): void
    {
        $this->member(retailerState: ['status' => 'BLOCKED']);

        $this->login('9812000004')->assertForbidden()->assertJsonPath('code', 'RETAILER_BLOCKED');
    }

    public function test_an_inactive_shop_login_cannot_log_in(): void
    {
        $this->member(membershipState: ['status' => 'INACTIVE']);

        $this->login('9812000004')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_admins_and_collectors_cannot_use_the_retailer_app(): void
    {
        User::factory()->admin()->create(['mobile' => '+919812000001', 'password' => self::PASSWORD]);
        User::factory()->collector()->create(['mobile' => '+919812000003', 'password' => self::PASSWORD]);

        $this->login('9812000001')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
        $this->login('9812000003')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_a_successful_login_is_audited_and_the_password_never_stored(): void
    {
        $owner = $this->member();

        $this->login('9812000004')->assertOk();

        $log = AuditLog::query()->where('action', 'AUTH.LOGIN')->sole();
        $this->assertSame($owner->user->id, $log->actor_user_id);
        $this->assertSame(0, AuditLog::query()->where('before', 'like', '%'.self::PASSWORD.'%')
            ->orWhere('after', 'like', '%'.self::PASSWORD.'%')->orWhere('meta', 'like', '%'.self::PASSWORD.'%')->count());
    }

    /**
     * @param  array<string, mixed>  $userState
     * @param  array<string, mixed>  $retailerState
     * @param  array<string, mixed>  $membershipState
     */
    private function member(bool $staff = false, array $userState = [], array $retailerState = [], array $membershipState = []): RetailerUser
    {
        $factory = RetailerUser::factory()
            ->for(Retailer::factory()->state($retailerState))
            ->for(User::factory()->retailer()->state(array_merge(['mobile' => '+919812000004', 'password' => self::PASSWORD], $userState)))
            ->state($membershipState);

        return ($staff ? $factory->staff() : $factory)->create();
    }

    private function login(string $mobile, string $password = self::PASSWORD): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/auth/login', ['mobile' => $mobile, 'password' => $password, 'app' => 'retailer']);
    }
}
```

Create `tests/Feature/Auth/SessionLifecycleTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\PersonalAccessToken;
use App\Models\RetailerUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_the_profile_of_the_token_owner(): void
    {
        $owner = RetailerUser::factory()->create();
        $token = $owner->user->createToken('retailer-app', ['retailer'], now()->addDays(30))->plainTextToken;

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.id', $owner->user->public_id)
            ->assertJsonPath('data.shop.name', $owner->retailer->shop_name)
            ->assertJsonMissingPath('data.password');
    }

    public function test_logout_revokes_the_token(): void
    {
        $owner = RetailerUser::factory()->create();
        $token = $owner->user->createToken('retailer-app', ['retailer'], now()->addDays(30))->plainTextToken;

        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => "Bearer {$token}"])->assertOk();

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_an_expired_session_says_token_expired(): void
    {
        $owner = RetailerUser::factory()->create();
        $token = $owner->user->createToken('retailer-app', ['retailer'], now()->addDays(30))->plainTextToken;

        $this->travel(31)->days();

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'TOKEN_EXPIRED');
    }

    public function test_missing_and_garbage_tokens_are_unauthenticated(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer 1|garbage'])->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_a_registration_only_token_cannot_use_normal_endpoints(): void
    {
        $collector = User::factory()->collector()->create();
        $token = $collector->createToken('device-registration', ['device:register'], now()->addMinutes(10))->plainTextToken;

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_the_last_ip_that_used_a_token_is_recorded(): void
    {
        $owner = RetailerUser::factory()->create();
        $new = $owner->user->createToken('retailer-app', ['retailer'], now()->addDays(30));

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$new->plainTextToken}"])->assertOk();

        $this->assertSame('127.0.0.1', PersonalAccessToken::query()->findOrFail($new->accessToken->getKey())->last_used_ip);
    }
}
```

Create `tests/Feature/Auth/PasswordChangeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\RetailerUser;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'Retailer@Pass1';

    private const NEW = 'Brand-New-Pass-42';

    public function test_a_forced_password_change_blocks_other_endpoints_until_done(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'ability:retailer,collector', 'password.changed'])
            ->get('/api/v1/_test/protected', fn () => ApiResponse::success());
        [$user, $token] = $this->retailer(mustChange: true);
        $auth = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/v1/_test/protected', $auth)->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        $this->getJson('/api/v1/auth/me', $auth)->assertOk()->assertJsonPath('data.must_change_password', true);

        $this->changePassword($auth)->assertOk();

        $this->getJson('/api/v1/_test/protected', $auth)->assertOk();
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_the_current_password_must_be_right(): void
    {
        [, $token] = $this->retailer();

        $this->changePassword(['Authorization' => "Bearer {$token}"], current: 'wrong')
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'current_password');
    }

    public function test_short_new_passwords_are_rejected(): void
    {
        [, $token] = $this->retailer();

        $this->postJson('/api/v1/auth/password/change', [
            'current_password' => self::OLD, 'password' => 'short', 'password_confirmation' => 'short',
        ], ['Authorization' => "Bearer {$token}"])->assertStatus(422)->assertJsonPath('errors.0.field', 'password');
    }

    public function test_changing_the_password_signs_out_every_other_session(): void
    {
        [$user, $token] = $this->retailer();
        $other = $user->createToken('retailer-app', ['retailer'], now()->addDays(30))->plainTextToken;

        $this->changePassword(['Authorization' => "Bearer {$token}"])->assertOk();

        $this->assertTrue(Hash::check(self::NEW, (string) $user->fresh()->password));
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$other}"])->assertUnauthorized();
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])->assertOk();
        $this->assertSame(1, AuditLog::query()->where('action', 'AUTH.PASSWORD_CHANGED')->count());
    }

    /**
     * @return array{User, string}
     */
    private function retailer(bool $mustChange = false): array
    {
        $member = RetailerUser::factory()
            ->for(User::factory()->retailer()->state(['password' => self::OLD, 'must_change_password' => $mustChange]))
            ->create();

        return [$member->user, $member->user->createToken('retailer-app', ['retailer'], now()->addDays(30))->plainTextToken];
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function changePassword(array $headers, string $current = self::OLD): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/auth/password/change', [
            'current_password' => $current, 'password' => self::NEW, 'password_confirmation' => self::NEW,
        ], $headers);
    }
}
```

Create `tests/Feature/Auth/RetailerMembershipTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Retailer;
use App\Models\RetailerUser;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class RetailerMembershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'retailer.member'])->get('/api/v1/_test/shop', fn (Request $request) => ApiResponse::success([
            'shop' => $request->attributes->get('retailer_membership')->retailer->public_id,
        ]));
    }

    public function test_an_active_member_reaches_shop_endpoints_with_their_shop_attached(): void
    {
        $member = RetailerUser::factory()->staff()->create();
        Sanctum::actingAs($member->user, ['retailer']);

        $this->getJson('/api/v1/_test/shop')->assertOk()->assertJsonPath('data.shop', $member->retailer->public_id);
    }

    public function test_a_blocked_shop_is_refused(): void
    {
        $member = RetailerUser::factory()->for(Retailer::factory()->blocked())->create();
        Sanctum::actingAs($member->user, ['retailer']);

        $this->getJson('/api/v1/_test/shop')->assertForbidden()->assertJsonPath('code', 'RETAILER_BLOCKED');
    }

    public function test_an_inactive_membership_and_other_roles_are_refused(): void
    {
        Sanctum::actingAs(RetailerUser::factory()->inactive()->create()->user, ['retailer']);
        $this->getJson('/api/v1/_test/shop')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');

        Sanctum::actingAs(User::factory()->collector()->create(), ['collector']);
        $this->getJson('/api/v1/_test/shop')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter="MobileNumberTest|RetailerLoginTest|SessionLifecycleTest|PasswordChangeTest|RetailerMembershipTest"`
Expected: FAIL — `Class "App\Support\MobileNumber" not found`.

- [ ] **Step 3: Token model, enums and mobile numbers**

Create `database/migrations/2026_10_02_000700_add_device_columns_to_personal_access_tokens_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->foreignId('device_id')->nullable()->after('tokenable_id')->constrained('devices');
            $table->string('last_used_ip', 45)->nullable()->after('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('device_id');
            $table->dropColumn('last_used_ip');
        });
    }
};
```

Create `app/Models/PersonalAccessToken.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/** Sanctum token that remembers the phone it is bound to (collector sessions). */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected function casts(): array
    {
        return array_merge(parent::casts(), ['device_id' => 'integer']);
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
```

Create `app/Enums/AppClient.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

/** Which mobile app is logging in. */
enum AppClient: string
{
    case Retailer = 'retailer';
}
```

Create `app/Enums/TokenAbility.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum TokenAbility: string
{
    case Retailer = 'retailer';
    case Collector = 'collector';
    case DeviceRegister = 'device:register';
}
```

Create `app/Support/MobileNumber.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

final class MobileNumber
{
    /** Normalises an Indian mobile number to +91XXXXXXXXXX, or returns null when the input is not one. */
    public static function normalize(string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) === 1 ? '+91'.$digits : null;
    }

    /** "+919812000004" becomes "+91******0004" for logs and audit metadata. */
    public static function mask(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        return strlen($digits) < 4 ? '****' : '+91******'.substr($digits, -4);
    }
}
```

- [ ] **Step 4: Token issuing, membership guard and the login service**

Create `app/Services/Auth/IssuedToken.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\NewAccessToken;
use LogicException;

final readonly class IssuedToken
{
    public function __construct(
        public string $plainText,
        public CarbonImmutable $expiresAt,
        public User $user,
        public PersonalAccessToken $token,
    ) {}

    public static function from(NewAccessToken $new, User $user): self
    {
        $token = $new->accessToken;

        if (! $token instanceof PersonalAccessToken || $token->expires_at === null) {
            throw new LogicException('Every issued token must use the app token model and expire.');
        }

        return new self($new->plainTextToken, CarbonImmutable::instance($token->expires_at), $user, $token);
    }
}
```

Create `app/Services/Auth/TokenIssuer.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\TokenAbility;
use App\Models\User;
use App\Support\Settings\Settings;
use Carbon\CarbonImmutable;

final class TokenIssuer
{
    public function __construct(private readonly Settings $settings) {}

    public function issueRetailerToken(User $user): IssuedToken
    {
        $expiresAt = CarbonImmutable::now()->addDays((int) $this->settings->get('retailer_token_days'));

        return IssuedToken::from($user->createToken('retailer-app', [TokenAbility::Retailer->value], $expiresAt), $user);
    }
}
```

Create `app/Services/Auth/RetailerMembershipGuard.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\RecordStatus;
use App\Enums\RetailerStatus;
use App\Exceptions\ApiException;
use App\Models\RetailerUser;
use App\Models\User;

final class RetailerMembershipGuard
{
    /**
     * The login's active shop membership, with its retailer loaded.
     *
     * @throws ApiException FORBIDDEN when there is no active membership, RETAILER_BLOCKED when the shop is not active
     */
    public function activeMembershipFor(User $user): RetailerUser
    {
        $membership = RetailerUser::query()->with('retailer')->where('user_id', $user->id)->first();

        if ($membership === null || $membership->status !== RecordStatus::Active) {
            throw new ApiException('FORBIDDEN', 'This login is not linked to an active shop.', 403);
        }

        if ($membership->retailer->status !== RetailerStatus::Active) {
            throw new ApiException('RETAILER_BLOCKED', 'This shop is blocked. Contact the operations team.', 403);
        }

        return $membership;
    }
}
```

Create `app/Services/Auth/LoginService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\AppClient;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Audit\AuditActor;
use App\Services\Audit\AuditLogger;
use App\Support\MobileNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Mobile-app login (spec §18.2). Failures are audited; ten wrong passwords within
 * fifteen minutes lock the account for fifteen minutes.
 */
final class LoginService
{
    public const MAX_FAILURES = 10;

    public const FAILURE_WINDOW_SECONDS = 900;

    public const LOCK_MINUTES = 15;

    private static ?string $dummyHash = null;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TokenIssuer $tokens,
        private readonly RetailerMembershipGuard $memberships,
    ) {}

    public function login(string $mobile, string $password, AppClient $app, Request $request): IssuedToken
    {
        $normalized = MobileNumber::normalize($mobile);
        $user = $normalized === null ? null : User::query()->where('mobile', $normalized)->first();

        if ($user === null) {
            Hash::check($password, self::dummyHash()); // similar response time whether or not the mobile exists
            $this->reject(null, $app, 'unknown_mobile', self::invalidCredentials(), ['mobile' => MobileNumber::mask($normalized ?? $mobile)]);
        }

        if ($user->locked_until !== null && $user->locked_until->isFuture()) {
            $until = $user->locked_until->setTimezone('Asia/Kolkata')->format('h:i A');
            $this->reject($user, $app, 'locked', new ApiException('ACCOUNT_LOCKED', "Too many failed attempts. Try again after {$until} IST.", 401));
        }

        if (! Hash::check($password, $user->password)) {
            $this->recordFailure($user, $app);

            throw self::invalidCredentials();
        }

        if (! $user->isActive()) {
            $this->reject($user, $app, 'inactive', new ApiException('ACCOUNT_BLOCKED', 'This account is not active. Contact the operations team.', 403));
        }

        $this->clearFailures($user);

        $issued = match ($app) {
            AppClient::Retailer => $this->retailerLogin($user, $app),
        };

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $this->audit->record('AUTH.LOGIN', $user, null, null, ['app' => $app->value], AuditActor::user($user));

        return $issued;
    }

    private function retailerLogin(User $user, AppClient $app): IssuedToken
    {
        if ($user->role !== UserRole::Retailer) {
            $this->reject($user, $app, 'wrong_app', new ApiException('FORBIDDEN', 'This account cannot use the retailer app.', 403));
        }

        try {
            $this->memberships->activeMembershipFor($user);
        } catch (ApiException $e) {
            $this->reject($user, $app, strtolower($e->errorCode), $e);
        }

        return $this->tokens->issueRetailerToken($user);
    }

    private function recordFailure(User $user, AppClient $app): void
    {
        $key = self::failureKey($user);
        RateLimiter::hit($key, self::FAILURE_WINDOW_SECONDS);
        User::query()->whereKey($user->id)->increment('failed_login_count');
        $this->audit->record('AUTH.LOGIN_FAILED', $user, null, null, ['reason' => 'wrong_password', 'app' => $app->value], AuditActor::anonymous());

        if (RateLimiter::attempts($key) >= self::MAX_FAILURES) {
            User::query()->whereKey($user->id)->update(['locked_until' => now()->addMinutes(self::LOCK_MINUTES), 'failed_login_count' => 0]);
            RateLimiter::clear($key);
            $this->audit->record('AUTH.ACCOUNT_LOCKED', $user, null, ['locked_minutes' => self::LOCK_MINUTES], [], AuditActor::system());
        }
    }

    private function clearFailures(User $user): void
    {
        RateLimiter::clear(self::failureKey($user));

        if ($user->failed_login_count !== 0) {
            User::query()->whereKey($user->id)->update(['failed_login_count' => 0]);
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function reject(?User $user, AppClient $app, string $reason, ApiException $exception, array $meta = []): never
    {
        $this->audit->record('AUTH.LOGIN_FAILED', $user, null, null, array_merge(['reason' => $reason, 'app' => $app->value], $meta), AuditActor::anonymous());

        throw $exception;
    }

    private static function invalidCredentials(): ApiException
    {
        return new ApiException('INVALID_CREDENTIALS', 'Mobile number or password is incorrect.', 401);
    }

    private static function failureKey(User $user): string
    {
        return 'login-failures:'.$user->id;
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make(Str::random(40));
    }
}
```

- [ ] **Step 5: Requests, resource, controllers, middleware and listener**

Create `app/Http/Requests/Auth/LoginRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\AppClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'max:128'],
            'app' => ['required', Rule::enum(AppClient::class)],
        ];
    }
}
```

Create `app/Http/Requests/Auth/ChangePasswordRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:128'],
            'password' => ['required', 'string', 'min:10', 'max:128', 'confirmed', 'different:current_password'],
        ];
    }
}
```

Create `app/Http/Resources/UserProfileResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read User $resource
 */
final class UserProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $user->loadMissing(['collector', 'shopMembership.retailer']);
        $collector = $user->collector;
        $membership = $user->shopMembership;

        return [
            'id' => $user->public_id,
            'name' => $user->name,
            'mobile' => $user->mobile,
            'email' => $user->email,
            'role' => $user->role->value,
            'must_change_password' => $user->must_change_password,
            'collector' => $collector === null ? null : [
                'id' => $collector->public_id,
                'code' => $collector->collector_code,
                'vehicle_number' => $collector->vehicle_number,
                'float_limit_paise' => $collector->float_limit_paise,
                'status' => $collector->status->value,
            ],
            'shop' => $membership === null ? null : [
                'id' => $membership->retailer->public_id,
                'name' => $membership->retailer->shop_name,
                'shop_role' => $membership->shop_role->value,
                'can_request' => $membership->can_request,
                'can_confirm' => $membership->can_confirm,
                'can_spend' => $membership->can_spend,
                'can_manage_staff' => $membership->can_manage_staff,
            ],
        ];
    }
}
```

Create `app/Http/Controllers/Api/V1/Auth/LoginController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AppClient;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserProfileResource;
use App\Services\Auth\LoginService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

final class LoginController
{
    public function __invoke(LoginRequest $request, LoginService $login): JsonResponse
    {
        $issued = $login->login(
            $request->string('mobile')->toString(),
            $request->string('password')->toString(),
            AppClient::from($request->string('app')->toString()),
            $request,
        );

        return ApiResponse::success([
            'token' => $issued->plainText,
            'expires_at' => ApiResponse::formatTime($issued->expiresAt),
            'user' => (new UserProfileResource($issued->user))->resolve($request),
        ], 'Logged in.');
    }
}
```

Create `app/Http/Controllers/Api/V1/Auth/MeController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Resources\UserProfileResource;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeController
{
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::success((new UserProfileResource($request->user()))->resolve($request));
    }
}
```

Create `app/Http/Controllers/Api/V1/Auth/LogoutController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LogoutController
{
    public function __invoke(Request $request, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        $audit->record('AUTH.LOGOUT', $user);

        return ApiResponse::success(null, 'Logged out.');
    }
}
```

Create `app/Http/Controllers/Api/V1/Auth/ChangePasswordController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ChangePasswordController
{
    public function __invoke(ChangePasswordRequest $request, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }

        DB::transaction(function () use ($user, $request, $audit): void {
            $user->forceFill(['password' => $request->string('password')->toString(), 'must_change_password' => false])->save();

            $current = $user->currentAccessToken();
            $user->tokens()
                ->when($current instanceof PersonalAccessToken, fn ($query) => $query->whereKeyNot($current->getKey()))
                ->delete();

            $audit->record('AUTH.PASSWORD_CHANGED', $user, null, null, ['other_sessions_revoked' => true]);
        });

        return ApiResponse::success(null, 'Password changed.');
    }
}
```

Create `app/Http/Middleware/EnsurePasswordChanged.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** While a password change is pending, only profile, logout and the change itself are allowed. */
final class EnsurePasswordChanged
{
    private const ALLOWED_ROUTES = ['api.v1.auth.me', 'api.v1.auth.logout', 'api.v1.auth.password.change'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->must_change_password && ! in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)) {
            throw new ApiException('PASSWORD_CHANGE_REQUIRED', 'Please set a new password to continue.', 403);
        }

        return $next($request);
    }
}
```

Create `app/Http/Middleware/EnsureRetailerMembership.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Auth\RetailerMembershipGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Every shop endpoint runs behind this: it attaches `retailer_membership` to the request. */
final class EnsureRetailerMembership
{
    public function __construct(private readonly RetailerMembershipGuard $memberships) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->role !== UserRole::Retailer) {
            throw new ApiException('FORBIDDEN', 'You are not allowed to do this.', 403);
        }

        $request->attributes->set('retailer_membership', $this->memberships->activeMembershipFor($user));

        return $next($request);
    }
}
```

Create `app/Listeners/RecordTokenIp.php`:

```php
<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\PersonalAccessToken;
use Laravel\Sanctum\Events\TokenAuthenticated;

final class RecordTokenIp
{
    public function handle(TokenAuthenticated $event): void
    {
        $token = $event->token;
        $ip = request()->ip();

        if ($token instanceof PersonalAccessToken && $ip !== null && $token->last_used_ip !== $ip) {
            $token->forceFill(['last_used_ip' => $ip])->saveQuietly();
        }
    }
}
```

(Laravel discovers listeners in `app/Listeners` automatically; do not also register it by hand.)

- [ ] **Step 6: Wire routes, aliases, the token model and TOKEN_EXPIRED**

In `app/Exceptions/ApiExceptionRenderer.php`, replace `unauthenticated()` (add `use Laravel\Sanctum\Sanctum;`):

```php
    private function unauthenticated(Request $request): JsonResponse
    {
        $bearer = $request->bearerToken();

        if ($bearer !== null) {
            $model = Sanctum::personalAccessTokenModel();
            $token = $model::findToken($bearer);

            if ($token !== null && $token->expires_at !== null && $token->expires_at->isPast()) {
                return ApiResponse::error('TOKEN_EXPIRED', 'Your session has expired. Please log in again.', 401);
            }
        }

        return ApiResponse::error('UNAUTHENTICATED', 'Please log in.', 401);
    }
```

In `app/Providers/AppServiceProvider.php`, add to `boot()` (with imports `App\Models\PersonalAccessToken`, `App\Support\MobileNumber`, `Illuminate\Cache\RateLimiting\Limit`, `Illuminate\Http\Request`, `Illuminate\Support\Facades\RateLimiter`, `Illuminate\Support\Str`, `Laravel\Sanctum\Sanctum`):

```php
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        RateLimiter::for('auth-login', static function (Request $request): Limit {
            $mobile = (string) $request->input('mobile');

            return Limit::perMinute(5)->by((MobileNumber::normalize($mobile) ?? Str::lower($mobile)).'|'.$request->ip());
        });
```

In `bootstrap/app.php`, extend the alias list (with imports):

```php
        $middleware->alias([
            'role' => EnsureRole::class,
            'permission' => EnsureAdminPermission::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'password.changed' => EnsurePasswordChanged::class,
            'retailer.member' => EnsureRetailerMembership::class,
        ]);
```

Replace `routes/api.php`:

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\ChangePasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\MetaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('meta', MetaController::class)->name('meta');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('login', LoginController::class)->middleware('throttle:auth-login')->name('login');

        Route::middleware(['auth:sanctum', 'ability:retailer,collector', 'password.changed'])->group(function (): void {
            Route::get('me', MeController::class)->name('me');
            Route::post('logout', LogoutController::class)->name('logout');
            Route::post('password/change', ChangePasswordController::class)->name('password.change');
        });
    });
});
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --filter="MobileNumberTest|RetailerLoginTest|SessionLifecycleTest|PasswordChangeTest|RetailerMembershipTest"`
Expected: PASS.

- [ ] **Step 8: Run the gates and commit**

Run: `composer lint && composer analyse && php artisan test` → Expected: all green.

```bash
git add backend-laravel
git commit -m "feat(auth): add retailer login, sessions, lockout, password change and shop membership guard"
```

---

### Task 9: Collector sessions bound to an approved phone

**Files:**
- Modify: `app/Enums/AppClient.php` (add `Collector`), `app/Services/Auth/TokenIssuer.php`, `app/Services/Auth/LoginService.php`, `bootstrap/app.php`, `routes/api.php`
- Create: `app/Http/Middleware/VerifyDeviceSignature.php`, `app/Http/Controllers/Api/V1/Auth/RotateTokenController.php`
- Test: `tests/Feature/Auth/CollectorSessionTest.php`

**Interfaces:**
- Consumes: `DeviceSignatureVerifier`, `Device`, `DeviceStatus` (Task 7); `LoginService`, `TokenIssuer`, `IssuedToken`, `PersonalAccessToken` (Task 8); `Collector`, `CollectorStatus` (Task 6).
- Produces:
  - `AppClient::Collector = 'collector'`
  - `TokenIssuer::issueCollectorToken(User, Device): IssuedToken` (ability `collector`, `device_id` set, `collector_token_hours`)
  - `TokenIssuer::issueRegistrationToken(User): IssuedToken` (ability `device:register`, 10 minutes, one live at a time); constant `TokenIssuer::REGISTRATION_TOKEN_NAME = 'device-registration'`
  - `TokenIssuer::reissue(User, PersonalAccessToken): IssuedToken`
  - Middleware alias `device.signed`: for collector users it requires an ACTIVE device matching both the token and `X-Device-Id`, verifies the signature, and sets request attributes `device` and `device_id`; other roles pass through untouched. Every collector request is signed — reads included — so a stolen token cannot even read data.
  - Route `POST /api/v1/auth/token/rotate` (`api.v1.auth.token.rotate`)
  - Login error `DEVICE_REGISTRATION_REQUIRED` (403) carries `data.registration_token` and `data.expires_at`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Auth/CollectorSessionTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\CollectorStatus;
use App\Enums\DeviceStatus;
use App\Models\Collector;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use App\Models\RetailerUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\DeviceKeyPair;
use Tests\Support\SignsDeviceRequests;
use Tests\TestCase;

final class CollectorSessionTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    private const PASSWORD = 'Collector@Pass1';

    private DeviceKeyPair $keys;

    private Collector $collector;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keys = DeviceKeyPair::generate();
        $this->collector = Collector::factory()
            ->for(User::factory()->collector()->state(['mobile' => '+919812000003', 'password' => self::PASSWORD]))
            ->create(['collector_code' => 'COL-104']);
        $this->device = Device::factory()->active()->withPublicKey($this->keys->publicKeyPem)->for($this->collector->user)->create();
    }

    public function test_logging_in_from_the_approved_phone_gives_a_token_bound_to_it(): void
    {
        $response = $this->signedLogin()->assertOk()->assertJsonPath('data.user.collector.code', 'COL-104');

        $token = PersonalAccessToken::findToken($response->json('data.token'));
        $this->assertSame($this->device->id, $token->device_id);
        $this->assertSame(['collector'], $token->abilities);
        $this->assertEqualsWithDelta(now()->addHours(14)->getTimestamp(), $token->expires_at->getTimestamp(), 5);
    }

    public function test_logging_in_from_an_unregistered_phone_returns_a_short_lived_registration_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', $this->credentials())
            ->assertForbidden()
            ->assertJsonPath('code', 'DEVICE_REGISTRATION_REQUIRED');

        $token = PersonalAccessToken::findToken($response->json('data.registration_token'));
        $this->assertSame(['device:register'], $token->abilities);
        $this->assertNull($token->device_id);
        $this->assertTrue($token->expires_at->lessThanOrEqualTo(now()->addMinutes(10)));
    }

    public function test_a_login_signed_with_the_wrong_key_is_rejected(): void
    {
        $this->signedLogin(DeviceKeyPair::generate())->assertUnauthorized()->assertJsonPath('code', 'SIGNATURE_INVALID');
    }

    public function test_a_suspended_collector_cannot_log_in(): void
    {
        $this->collector->update(['status' => CollectorStatus::Suspended]);

        $this->signedLogin()->assertForbidden()->assertJsonPath('code', 'COLLECTOR_SUSPENDED');
    }

    public function test_a_retailer_cannot_use_the_collector_app(): void
    {
        RetailerUser::factory()->for(User::factory()->retailer()->state(['mobile' => '+919812000004', 'password' => self::PASSWORD]))->create();

        $this->postJson('/api/v1/auth/login', ['mobile' => '9812000004', 'password' => self::PASSWORD, 'app' => 'collector'])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_signed_requests_with_the_bound_token_are_accepted(): void
    {
        $token = $this->loginToken();

        $this->signedJson('GET', '/api/v1/auth/me', [], $this->keys, $this->device, ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.collector.code', 'COL-104');
    }

    public function test_an_unsigned_request_with_a_collector_token_is_refused(): void
    {
        $token = $this->loginToken();

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertForbidden()
            ->assertJsonPath('code', 'DEVICE_NOT_BOUND');
    }

    public function test_a_token_cannot_be_used_from_another_phone(): void
    {
        $token = $this->loginToken();
        $otherKeys = DeviceKeyPair::generate();
        $otherPhone = Device::factory()->active()->withPublicKey($otherKeys->publicKeyPem)->create();

        $this->signedJson('GET', '/api/v1/auth/me', [], $otherKeys, $otherPhone, ['Authorization' => "Bearer {$token}"])
            ->assertForbidden()
            ->assertJsonPath('code', 'DEVICE_NOT_BOUND');
    }

    public function test_a_revoked_phone_loses_access_immediately(): void
    {
        $token = $this->loginToken();
        $this->device->forceFill(['status' => DeviceStatus::Revoked])->save();

        $this->signedJson('GET', '/api/v1/auth/me', [], $this->keys, $this->device, ['Authorization' => "Bearer {$token}"])
            ->assertForbidden()
            ->assertJsonPath('code', 'DEVICE_NOT_BOUND');
    }

    public function test_a_replayed_request_is_refused(): void
    {
        $token = $this->loginToken();
        $headers = array_merge(
            ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'],
            $this->deviceSignatureHeaders($this->keys, $this->device->public_id, 'GET', '/api/v1/auth/me', ''),
        );
        $server = $this->transformHeadersToServerVars($headers);

        $this->call('GET', '/api/v1/auth/me', [], [], [], $server, '')->assertOk();
        $this->call('GET', '/api/v1/auth/me', [], [], [], $server, '')->assertUnauthorized()->assertJsonPath('code', 'REPLAY_DETECTED');
    }

    public function test_rotating_keeps_the_phone_binding_and_retires_the_old_token(): void
    {
        $old = $this->loginToken();

        $new = $this->signedJson('POST', '/api/v1/auth/token/rotate', [], $this->keys, $this->device, ['Authorization' => "Bearer {$old}"])
            ->assertOk()
            ->json('data.token');

        $this->assertNull(PersonalAccessToken::findToken($old));
        $this->assertSame($this->device->id, PersonalAccessToken::findToken($new)->device_id);
    }

    public function test_retailer_requests_never_need_signatures(): void
    {
        $member = RetailerUser::factory()->create();
        $token = $member->user->createToken('retailer-app', ['retailer'], now()->addDays(30))->plainTextToken;

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])->assertOk();
    }

    /** @return array<string, string> */
    private function credentials(): array
    {
        return ['mobile' => '9812000003', 'password' => self::PASSWORD, 'app' => 'collector'];
    }

    private function signedLogin(?DeviceKeyPair $keys = null): TestResponse
    {
        return $this->signedJson('POST', '/api/v1/auth/login', $this->credentials(), $keys ?? $this->keys, $this->device);
    }

    private function loginToken(): string
    {
        return (string) $this->signedLogin()->assertOk()->json('data.token');
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=CollectorSessionTest`
Expected: FAIL — the login request is rejected with `VALIDATION_FAILED` because `app=collector` is not yet a valid `AppClient`.

- [ ] **Step 3: Extend the app enum and the token issuer**

In `app/Enums/AppClient.php`, add the case:

```php
    case Collector = 'collector';
```

Replace `app/Services/Auth/TokenIssuer.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\TokenAbility;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\Settings\Settings;
use Carbon\CarbonImmutable;

final class TokenIssuer
{
    public const REGISTRATION_TOKEN_NAME = 'device-registration';

    public function __construct(private readonly Settings $settings) {}

    public function issueRetailerToken(User $user): IssuedToken
    {
        $expiresAt = CarbonImmutable::now()->addDays((int) $this->settings->get('retailer_token_days'));

        return IssuedToken::from($user->createToken('retailer-app', [TokenAbility::Retailer->value], $expiresAt), $user);
    }

    public function issueCollectorToken(User $user, Device $device): IssuedToken
    {
        $expiresAt = CarbonImmutable::now()->addHours((int) $this->settings->get('collector_token_hours'));
        $new = $user->createToken('collector-app', [TokenAbility::Collector->value], $expiresAt);
        $new->accessToken->forceFill(['device_id' => $device->id])->save();

        return IssuedToken::from($new, $user);
    }

    /** A ten-minute token that can only register a phone and poll its approval. One is live at a time. */
    public function issueRegistrationToken(User $user): IssuedToken
    {
        $user->tokens()->where('name', self::REGISTRATION_TOKEN_NAME)->delete();

        $new = $user->createToken(self::REGISTRATION_TOKEN_NAME, [TokenAbility::DeviceRegister->value], CarbonImmutable::now()->addMinutes(10));

        return IssuedToken::from($new, $user);
    }

    /** Replaces the current token with a fresh one carrying the same binding. */
    public function reissue(User $user, PersonalAccessToken $current): IssuedToken
    {
        $issued = match ($user->role) {
            UserRole::Collector => $this->issueCollectorToken(
                $user,
                $current->device ?? throw new ApiException('DEVICE_NOT_BOUND', 'This phone is not registered for your account.', 403),
            ),
            UserRole::Retailer => $this->issueRetailerToken($user),
            UserRole::Admin => throw new ApiException('FORBIDDEN', 'Admin sessions do not use app tokens.', 403),
        };

        $current->delete();

        return $issued;
    }
}
```

- [ ] **Step 4: Add the collector branch to the login service**

In `app/Services/Auth/LoginService.php`:

1. Add imports: `App\Enums\CollectorStatus`, `App\Enums\DeviceStatus`, `App\Models\Collector`, `App\Models\Device`, `App\Support\Api\ApiResponse`.
2. Add the constructor dependency `private readonly DeviceSignatureVerifier $signatures,`.
3. Replace the `match` in `login()` with:

```php
        $issued = match ($app) {
            AppClient::Retailer => $this->retailerLogin($user, $app),
            AppClient::Collector => $this->collectorLogin($user, $app, $request),
        };
```

4. Add the method:

```php
    /**
     * A collector gets a session only on a phone whose Keystore key signed this login.
     * Without one, the collector receives a registration token instead (spec §18.3).
     */
    private function collectorLogin(User $user, AppClient $app, Request $request): IssuedToken
    {
        if ($user->role !== UserRole::Collector) {
            $this->reject($user, $app, 'wrong_app', new ApiException('FORBIDDEN', 'This account cannot use the collector app.', 403));
        }

        $collector = Collector::query()->where('user_id', $user->id)->first();

        if ($collector === null || $collector->status !== CollectorStatus::Active) {
            $this->reject($user, $app, 'collector_suspended', new ApiException('COLLECTOR_SUSPENDED', 'Your collector account is suspended. Contact the operations team.', 403));
        }

        $devicePublicId = (string) $request->header('X-Device-Id', '');
        $device = $devicePublicId === '' ? null : Device::query()
            ->where('public_id', $devicePublicId)
            ->where('user_id', $user->id)
            ->where('status', DeviceStatus::Active->value)
            ->first();

        if ($device === null) {
            $registration = $this->tokens->issueRegistrationToken($user);
            $this->audit->record('AUTH.DEVICE_REGISTRATION_REQUIRED', $user, null, null, ['app' => $app->value], AuditActor::user($user));

            throw new ApiException('DEVICE_REGISTRATION_REQUIRED', 'Register this phone to continue.', 403, [], [
                'registration_token' => $registration->plainText,
                'expires_at' => ApiResponse::formatTime($registration->expiresAt),
            ]);
        }

        $this->signatures->verify($request, $device);

        return $this->tokens->issueCollectorToken($user, $device);
    }
```

- [ ] **Step 5: Add the signature middleware and the rotate endpoint**

Create `app/Http/Middleware/VerifyDeviceSignature.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\DeviceStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Auth\DeviceSignatureVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every collector request must come from the phone its token is bound to and carry
 * that phone's signature. Retailers and admins pass through.
 */
final class VerifyDeviceSignature
{
    public function __construct(private readonly DeviceSignatureVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->role !== UserRole::Collector) {
            return $next($request);
        }

        $token = $user->currentAccessToken();
        $device = $token instanceof PersonalAccessToken && $token->device_id !== null
            ? Device::query()->find($token->device_id)
            : null;

        if ($device === null
            || $device->status !== DeviceStatus::Active
            || $device->user_id !== $user->id
            || $device->public_id !== $request->header('X-Device-Id')) {
            throw new ApiException('DEVICE_NOT_BOUND', 'This phone is not registered for your account.', 403);
        }

        $this->verifier->verify($request, $device);

        $request->attributes->set('device', $device);
        $request->attributes->set('device_id', $device->id);

        if ($device->last_seen_at === null || $device->last_seen_at->lt(now()->subMinute())) {
            $device->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
```

Create `app/Http/Controllers/Api/V1/Auth/RotateTokenController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\ApiException;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\IssuedToken;
use App\Services\Auth\TokenIssuer;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RotateTokenController
{
    public function __invoke(Request $request, TokenIssuer $tokens, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        $current = $user->currentAccessToken();

        if (! $current instanceof PersonalAccessToken) {
            throw new ApiException('FORBIDDEN', 'Only app sessions can be renewed.', 403);
        }

        $issued = DB::transaction(function () use ($user, $current, $tokens, $audit): IssuedToken {
            $issued = $tokens->reissue($user, $current);
            $audit->record('AUTH.TOKEN_ROTATED', $user);

            return $issued;
        });

        return ApiResponse::success([
            'token' => $issued->plainText,
            'expires_at' => ApiResponse::formatTime($issued->expiresAt),
        ], 'Session renewed.');
    }
}
```

In `bootstrap/app.php`, add the alias `'device.signed' => VerifyDeviceSignature::class,` (with its import).

In `routes/api.php`, import `RotateTokenController` and change the authenticated auth group to:

```php
        Route::middleware(['auth:sanctum', 'ability:retailer,collector', 'device.signed', 'password.changed'])->group(function (): void {
            Route::get('me', MeController::class)->name('me');
            Route::post('logout', LogoutController::class)->name('logout');
            Route::post('token/rotate', RotateTokenController::class)->name('token.rotate');
            Route::post('password/change', ChangePasswordController::class)->name('password.change');
        });
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter="CollectorSessionTest|RetailerLoginTest|SessionLifecycleTest|PasswordChangeTest"`
Expected: PASS (the retailer suites prove signatures are not required for retailers).

- [ ] **Step 7: Run the gates and commit**

Run: `composer lint && composer analyse && php artisan test` → Expected: all green.

```bash
git add backend-laravel
git commit -m "feat(auth): bind collector sessions to an approved phone and sign every collector request"
```

---

### Task 10: Phone registration and admin approval

**Files:**
- Create: `app/Services/Auth/DevicePublicKey.php`, `app/Services/Auth/DeviceRegistrationService.php`, `app/Services/Auth/DeviceBindingService.php`
- Create: `app/Services/Auth/Integrity/IntegrityVerifier.php`, `IntegrityVerdict.php`, `UncheckedIntegrityVerifier.php`
- Create: `app/Http/Requests/Auth/RegisterDeviceRequest.php`, `app/Http/Requests/Admin/RevokeDeviceRequest.php`
- Create: `app/Http/Controllers/Api/V1/Auth/RegisterDeviceController.php`, `DeviceStatusController.php`
- Create: `app/Http/Controllers/Api/V1/Admin/ApproveDeviceController.php`, `RevokeDeviceController.php`
- Create: `app/Console/Commands/ListPendingDevicesCommand.php`, `ApproveDeviceCommand.php`, `RevokeDeviceCommand.php`
- Modify: `app/Providers/AppServiceProvider.php`, `routes/api.php`, `tests/Support/DeviceKeyPair.php` (curve parameter)
- Test: `tests/Feature/Devices/DeviceRegistrationTest.php`, `tests/Feature/Devices/DeviceApprovalTest.php`

**Interfaces:**
- Consumes: `TokenIssuer` (Task 9), `DeviceSignatureVerifier` (Task 7), `Settings` (Task 5), `AuditLogger` (Task 4), `UserProfileResource` (Task 8).
- Produces:
  - `DevicePublicKey::assertValidP256(string $pem): void` (throws `ValidationException` on field `public_key`)
  - `DeviceRegistrationService::register(User $user, array $data): Device` and `DeviceRegistrationService::fingerprint(string $androidId): string`
  - `DeviceBindingService::approve(Device $device, ?User $admin): Device` (revokes any other ACTIVE phone and deletes its tokens) and `DeviceBindingService::revoke(Device $device, ?User $admin, string $reason): Device`
  - `interface IntegrityVerifier { verify(?string $integrityToken, User $user): IntegrityVerdict }`; `UncheckedIntegrityVerifier` bound until the Google verifier arrives in M2
  - Routes: `POST /api/v1/auth/device/register` (201), `GET /api/v1/auth/device/status`, `POST /api/v1/admin/devices/{device}/approve`, `POST /api/v1/admin/devices/{device}/revoke`
  - Commands: `devices:pending`, `devices:approve {device}`, `devices:revoke {device} {--reason=}`
  - New error codes: `DEVICE_ALREADY_BOUND` (409), `INVALID_DEVICE_STATE` (409), `INTEGRITY_CHECK_FAILED` (403)

- [ ] **Step 1: Let the test key helper use other curves**

In `tests/Support/DeviceKeyPair.php`, change `generate()` to accept the curve:

```php
    public static function generate(string $curve = 'prime256v1'): self
    {
        $options = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => $curve];
```

(the rest of the method is unchanged).

- [ ] **Step 2: Write the failing tests**

Create `tests/Feature/Devices/DeviceRegistrationTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Models\AuditLog;
use App\Models\Collector;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use App\Models\RetailerUser;
use App\Models\User;
use App\Services\Auth\DeviceBindingService;
use App\Services\Auth\DeviceRegistrationService;
use App\Services\Auth\TokenIssuer;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\DeviceKeyPair;
use Tests\Support\SignsDeviceRequests;
use Tests\TestCase;

final class DeviceRegistrationTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    private const ANDROID_ID = 'a1b2c3d4e5f60718';

    private User $user;

    private string $registrationToken;

    private DeviceKeyPair $keys;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = Collector::factory()->create()->user;
        $this->registrationToken = app(TokenIssuer::class)->issueRegistrationToken($this->user)->plainText;
        $this->keys = DeviceKeyPair::generate();
    }

    public function test_a_collector_registers_a_phone_and_waits_for_approval(): void
    {
        $id = $this->register()->assertCreated()->assertJsonPath('data.device.status', 'PENDING_APPROVAL')->json('data.device.id');

        $device = Device::query()->where('public_id', $id)->sole();
        $this->assertSame(DeviceRegistrationService::fingerprint(self::ANDROID_ID), $device->fingerprint_hash);
        $this->assertStringNotContainsString(self::ANDROID_ID, $device->fingerprint_hash);
        $this->assertSame(1, AuditLog::query()->where('action', 'DEVICE.REGISTERED')->count());
    }

    public function test_only_ec_p256_public_keys_are_accepted(): void
    {
        $this->register(['public_key' => DeviceKeyPair::generate('secp384r1')->publicKeyPem])
            ->assertStatus(422)->assertJsonPath('errors.0.field', 'public_key');

        $this->register(['public_key' => 'not a key'])
            ->assertStatus(422)->assertJsonPath('errors.0.field', 'public_key');
    }

    public function test_only_a_registration_token_can_register_a_phone(): void
    {
        $retailer = RetailerUser::factory()->create()->user->createToken('retailer-app', ['retailer'], now()->addDay())->plainTextToken;
        $collectorSession = $this->user->createToken('collector-app', ['collector'], now()->addHour())->plainTextToken;

        $this->register([], $retailer)->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
        $this->register([], $collectorSession)->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_a_new_registration_supersedes_an_older_pending_one(): void
    {
        $first = $this->register()->json('data.device.id');
        $second = $this->register(['public_key' => DeviceKeyPair::generate()->publicKeyPem, 'android_id' => 'ffeeddccbbaa0099'])->json('data.device.id');

        $this->assertSame('REVOKED', Device::query()->where('public_id', $first)->sole()->status->value);
        $this->assertSame('PENDING_APPROVAL', Device::query()->where('public_id', $second)->sole()->status->value);
    }

    public function test_a_phone_active_for_another_collector_is_refused(): void
    {
        Device::factory()->active()->create(['fingerprint_hash' => DeviceRegistrationService::fingerprint(self::ANDROID_ID)]);

        $this->register()->assertStatus(409)->assertJsonPath('code', 'DEVICE_ALREADY_BOUND');
    }

    public function test_polling_the_status_requires_proof_of_the_registered_key(): void
    {
        $id = $this->register()->json('data.device.id');

        $this->getJson('/api/v1/auth/device/status', ['Authorization' => "Bearer {$this->registrationToken}", 'X-Device-Id' => $id])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'SIGNATURE_INVALID');
    }

    public function test_status_reports_pending_then_hands_over_a_bound_session_after_approval(): void
    {
        $device = Device::query()->where('public_id', $this->register()->json('data.device.id'))->sole();
        $auth = ['Authorization' => "Bearer {$this->registrationToken}"];

        $this->signedJson('GET', '/api/v1/auth/device/status', [], $this->keys, $device, $auth)
            ->assertOk()->assertJsonPath('data.status', 'PENDING_APPROVAL');

        app(DeviceBindingService::class)->approve($device, User::factory()->admin()->create());

        $session = $this->signedJson('GET', '/api/v1/auth/device/status', [], $this->keys, $device, $auth)
            ->assertOk()->assertJsonPath('data.status', 'ACTIVE')->json('data.token');

        $this->assertSame($device->id, PersonalAccessToken::findToken($session)->device_id);
        $this->assertNull(PersonalAccessToken::findToken($this->registrationToken));
        $this->signedJson('GET', '/api/v1/auth/me', [], $this->keys, $device->fresh(), ['Authorization' => "Bearer {$session}"])->assertOk();
    }

    public function test_the_first_phone_is_approved_automatically_when_enabled(): void
    {
        app(Settings::class)->set('device_auto_approve_first', true);

        $this->register()->assertCreated()->assertJsonPath('data.device.status', 'ACTIVE');
        $this->assertSame('SYSTEM', AuditLog::query()->where('action', 'DEVICE.APPROVED')->sole()->actor_type);
    }

    public function test_automatic_approval_never_applies_to_a_replacement_phone(): void
    {
        app(Settings::class)->set('device_auto_approve_first', true);
        Device::factory()->active()->for($this->user)->create();

        $this->register()->assertCreated()->assertJsonPath('data.device.status', 'PENDING_APPROVAL');
    }

    public function test_enforced_integrity_refuses_phones_that_cannot_be_verified(): void
    {
        app(Settings::class)->set('integrity_enforcement', 'ENFORCE');

        $this->register()->assertForbidden()->assertJsonPath('code', 'INTEGRITY_CHECK_FAILED');
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function register(array $overrides = [], ?string $token = null): TestResponse
    {
        return $this->postJson('/api/v1/auth/device/register', array_merge([
            'public_key' => $this->keys->publicKeyPem,
            'android_id' => self::ANDROID_ID,
            'model' => 'Redmi Note 13',
            'manufacturer' => 'Xiaomi',
            'os_version' => '14',
            'app_version' => '1.0.0',
        ], $overrides), [
            'Authorization' => 'Bearer '.($token ?? $this->registrationToken),
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
    }
}
```

Create `tests/Feature/Devices/DeviceApprovalTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Enums\AdminPermission;
use App\Exceptions\ApiException;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Auth\DeviceBindingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class DeviceApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_a_phone_replaces_the_previous_one_and_ends_its_sessions(): void
    {
        $user = User::factory()->collector()->create();
        $old = Device::factory()->active()->for($user)->create();
        $oldToken = $user->createToken('collector-app', ['collector'], now()->addHour());
        $oldToken->accessToken->forceFill(['device_id' => $old->id])->save();
        $new = Device::factory()->pending()->for($user)->create();

        app(DeviceBindingService::class)->approve($new, User::factory()->admin()->create());

        $this->assertSame('REVOKED', $old->fresh()->status->value);
        $this->assertSame('ACTIVE', $new->fresh()->status->value);
        $this->assertNull(PersonalAccessToken::findToken($oldToken->plainTextToken));
        $this->assertSame(1, AuditLog::query()->where('action', 'DEVICE.APPROVED')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'DEVICE.REVOKED')->count());
    }

    public function test_only_a_phone_waiting_for_approval_can_be_approved(): void
    {
        $device = Device::factory()->active()->create();

        try {
            app(DeviceBindingService::class)->approve($device, null);
            $this->fail('An active phone was approved again.');
        } catch (ApiException $e) {
            $this->assertSame('INVALID_DEVICE_STATE', $e->errorCode);
        }
    }

    public function test_revoking_a_phone_ends_its_sessions(): void
    {
        $device = Device::factory()->active()->create();
        $token = $device->user->createToken('collector-app', ['collector'], now()->addHour());
        $token->accessToken->forceFill(['device_id' => $device->id])->save();

        app(DeviceBindingService::class)->revoke($device, null, 'phone lost');

        $this->assertSame('REVOKED', $device->fresh()->status->value);
        $this->assertSame('phone lost', $device->fresh()->revoke_reason);
        $this->assertNull(PersonalAccessToken::findToken($token->plainTextToken));
    }

    public function test_admin_endpoints_need_the_devices_permission(): void
    {
        $device = Device::factory()->pending()->create();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->postJson("/api/v1/admin/devices/{$device->public_id}/approve")->assertForbidden();

        Sanctum::actingAs(User::factory()->collector()->create());
        $this->postJson("/api/v1/admin/devices/{$device->public_id}/approve")->assertForbidden();

        $admin = User::factory()->admin()->create();
        $admin->givePermissionTo(AdminPermission::DevicesManage->value);
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/devices/{$device->public_id}/approve")->assertOk()->assertJsonPath('data.device.status', 'ACTIVE');
    }

    public function test_revoking_through_the_api_requires_a_reason(): void
    {
        $device = Device::factory()->active()->create();
        Sanctum::actingAs(User::factory()->admin(super: true)->create());

        $this->postJson("/api/v1/admin/devices/{$device->public_id}/revoke")->assertStatus(422)->assertJsonPath('errors.0.field', 'reason');
        $this->postJson("/api/v1/admin/devices/{$device->public_id}/revoke", ['reason' => 'phone stolen'])->assertOk()->assertJsonPath('data.device.status', 'REVOKED');
    }

    public function test_ops_commands_list_approve_and_revoke_phones(): void
    {
        $device = Device::factory()->pending()->create();

        $this->artisan('devices:pending')->expectsOutputToContain($device->public_id)->assertSuccessful();
        $this->artisan('devices:approve', ['device' => $device->public_id])->assertSuccessful();
        $this->assertSame('ACTIVE', $device->fresh()->status->value);
        $this->assertSame('SYSTEM', AuditLog::query()->where('action', 'DEVICE.APPROVED')->sole()->actor_type);

        $this->artisan('devices:revoke', ['device' => $device->public_id, '--reason' => 'test'])->assertSuccessful();
        $this->assertSame('REVOKED', $device->fresh()->status->value);
    }
}
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --filter="DeviceRegistrationTest|DeviceApprovalTest"`
Expected: FAIL — `Class "App\Services\Auth\DeviceRegistrationService" not found`.

- [ ] **Step 4: Key validation and the integrity seam**

Create `app/Services/Auth/DevicePublicKey.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Validation\ValidationException;

final class DevicePublicKey
{
    /** Accepts only a PEM EC public key on the P-256 curve, as produced by the Android Keystore. */
    public static function assertValidP256(string $pem): void
    {
        $key = openssl_pkey_get_public($pem);

        if ($key === false) {
            throw ValidationException::withMessages(['public_key' => 'The public key is not a valid PEM public key.']);
        }

        $details = openssl_pkey_get_details($key);

        if (($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC || ($details['ec']['curve_name'] ?? null) !== 'prime256v1') {
            throw ValidationException::withMessages(['public_key' => 'The public key must be an EC P-256 key.']);
        }
    }
}
```

Create `app/Services/Auth/Integrity/IntegrityVerdict.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Auth\Integrity;

final readonly class IntegrityVerdict
{
    /**
     * @param  'PASS'|'FAIL'|'UNCHECKED'  $status
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $status,
        public array $details = [],
    ) {}

    public static function unchecked(string $reason): self
    {
        return new self('UNCHECKED', ['reason' => $reason]);
    }

    public function passed(): bool
    {
        return $this->status === 'PASS';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['status' => $this->status, 'details' => $this->details, 'checked_at' => now()->toIso8601String()];
    }
}
```

Create `app/Services/Auth/Integrity/IntegrityVerifier.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Auth\Integrity;

use App\Models\User;

/** Checks a Google Play Integrity token for a collector phone (spec §18.3). */
interface IntegrityVerifier
{
    public function verify(?string $integrityToken, User $user): IntegrityVerdict;
}
```

Create `app/Services/Auth/Integrity/UncheckedIntegrityVerifier.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Auth\Integrity;

use App\Models\User;

/**
 * Used until the Play Integrity verifier ships with the collector app (M2). It never claims
 * a pass, so `integrity_enforcement = ENFORCE` correctly refuses every phone until then.
 */
final class UncheckedIntegrityVerifier implements IntegrityVerifier
{
    public function verify(?string $integrityToken, User $user): IntegrityVerdict
    {
        return IntegrityVerdict::unchecked('Play Integrity verification is not configured yet.');
    }
}
```

In `app/Providers/AppServiceProvider.php`, in `register()`:

```php
        $this->app->bind(\App\Services\Auth\Integrity\IntegrityVerifier::class, \App\Services\Auth\Integrity\UncheckedIntegrityVerifier::class);
```

and in `boot()`:

```php
        RateLimiter::for('device-register', static fn (Request $request): Limit => Limit::perHour(10)->by((string) $request->user()?->getAuthIdentifier()));
```

- [ ] **Step 5: Binding and registration services**

Create `app/Services/Auth/DeviceBindingService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\DeviceStatus;
use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Audit\AuditActor;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/** Approves and revokes collector phones. A collector has at most one active phone. */
final class DeviceBindingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function approve(Device $device, ?User $admin): Device
    {
        return DB::transaction(function () use ($device, $admin): Device {
            User::query()->whereKey($device->user_id)->lockForUpdate()->first();
            $device = Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();

            if ($device->status !== DeviceStatus::PendingApproval) {
                throw new ApiException('INVALID_DEVICE_STATE', 'Only a phone waiting for approval can be approved.', 409);
            }

            $actor = $admin !== null ? AuditActor::user($admin) : AuditActor::system();

            Device::query()
                ->where('user_id', $device->user_id)
                ->where('status', DeviceStatus::Active->value)
                ->get()
                ->each(fn (Device $old) => $this->markRevoked($old, $admin, 'replaced by a newly approved phone', $actor));

            $device->forceFill([
                'status' => DeviceStatus::Active,
                'approved_by_user_id' => $admin?->id,
                'approved_at' => now(),
            ])->save();

            $this->audit->record('DEVICE.APPROVED', $device, ['status' => DeviceStatus::PendingApproval->value], ['status' => DeviceStatus::Active->value], [], $actor);

            return $device;
        });
    }

    public function revoke(Device $device, ?User $admin, string $reason): Device
    {
        return DB::transaction(function () use ($device, $admin, $reason): Device {
            $device = Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();

            if ($device->status !== DeviceStatus::Revoked) {
                $this->markRevoked($device, $admin, $reason, $admin !== null ? AuditActor::user($admin) : AuditActor::system());
            }

            return $device;
        });
    }

    private function markRevoked(Device $device, ?User $admin, string $reason, AuditActor $actor): void
    {
        $before = ['status' => $device->status->value];

        $device->forceFill([
            'status' => DeviceStatus::Revoked,
            'revoked_by_user_id' => $admin?->id,
            'revoked_at' => now(),
            'revoke_reason' => $reason,
        ])->save();

        PersonalAccessToken::query()->where('device_id', $device->id)->delete();

        $this->audit->record('DEVICE.REVOKED', $device, $before, ['status' => DeviceStatus::Revoked->value, 'reason' => $reason], [], $actor);
    }
}
```

Create `app/Services/Auth/DeviceRegistrationService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\DeviceStatus;
use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\Integrity\IntegrityVerdict;
use App\Services\Auth\Integrity\IntegrityVerifier;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\DB;

final class DeviceRegistrationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Settings $settings,
        private readonly IntegrityVerifier $integrity,
        private readonly DeviceBindingService $binding,
    ) {}

    /** Keyed hash of ANDROID_ID: detects "one phone, two collectors" without storing the raw id. */
    public static function fingerprint(string $androidId): string
    {
        return hash_hmac('sha256', strtolower(trim($androidId)), (string) config('app.key'));
    }

    /**
     * @param  array{public_key: string, android_id: string, model?: string|null, manufacturer?: string|null, os_version?: string|null, app_version?: string|null, integrity_token?: string|null}  $data
     */
    public function register(User $user, array $data): Device
    {
        DevicePublicKey::assertValidP256($data['public_key']);

        $verdict = $this->checkIntegrity($user, $data['integrity_token'] ?? null);
        $fingerprint = self::fingerprint($data['android_id']);

        $device = DB::transaction(function () use ($user, $data, $fingerprint, $verdict): Device {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $boundElsewhere = Device::query()
                ->where('fingerprint_hash', $fingerprint)
                ->where('status', DeviceStatus::Active->value)
                ->where('user_id', '!=', $user->id)
                ->exists();

            if ($boundElsewhere) {
                throw new ApiException('DEVICE_ALREADY_BOUND', 'This phone is already registered to another collector.', 409);
            }

            $superseded = Device::query()
                ->where('user_id', $user->id)
                ->where('status', DeviceStatus::PendingApproval->value)
                ->update(['status' => DeviceStatus::Revoked->value, 'revoked_at' => now(), 'revoke_reason' => 'superseded by a newer registration']);

            $device = Device::query()->create([
                'user_id' => $user->id,
                'platform' => 'android',
                'fingerprint_hash' => $fingerprint,
                'public_key_pem' => $data['public_key'],
                'last_integrity_verdict' => $verdict->toArray(),
                'model' => $data['model'] ?? null,
                'manufacturer' => $data['manufacturer'] ?? null,
                'os_version' => $data['os_version'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'status' => DeviceStatus::PendingApproval,
            ]);

            $this->audit->record('DEVICE.REGISTERED', $device, null, ['status' => $device->status->value, 'model' => $device->model], [
                'integrity' => $verdict->status,
                'superseded_pending' => $superseded,
            ]);

            return $device;
        });

        $neverApproved = Device::query()->where('user_id', $user->id)->whereNotNull('approved_at')->doesntExist();

        if ($this->settings->get('device_auto_approve_first') === true && $neverApproved) {
            return $this->binding->approve($device, null);
        }

        return $device;
    }

    private function checkIntegrity(User $user, ?string $token): IntegrityVerdict
    {
        $mode = $this->settings->get('integrity_enforcement');

        if ($mode === 'OFF') {
            return IntegrityVerdict::unchecked('Integrity checks are switched off.');
        }

        $verdict = $this->integrity->verify($token, $user);

        if ($mode === 'ENFORCE' && ! $verdict->passed()) {
            $this->audit->record('DEVICE.INTEGRITY_REJECTED', $user, null, null, ['verdict' => $verdict->toArray()]);

            throw new ApiException('INTEGRITY_CHECK_FAILED', 'This phone did not pass the security check.', 403);
        }

        return $verdict;
    }
}
```

- [ ] **Step 6: Requests, controllers, commands and routes**

Create `app/Http/Requests/Auth/RegisterDeviceRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'public_key' => ['required', 'string', 'max:1024'],
            'android_id' => ['required', 'string', 'regex:/^[A-Za-z0-9]{8,64}$/'],
            'model' => ['nullable', 'string', 'max:80'],
            'manufacturer' => ['nullable', 'string', 'max:80'],
            'os_version' => ['nullable', 'string', 'max:20'],
            'app_version' => ['nullable', 'string', 'max:20'],
            'integrity_token' => ['nullable', 'string', 'max:8192'],
        ];
    }
}
```

Create `app/Http/Requests/Admin/RevokeDeviceRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class RevokeDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:120']];
    }
}
```

Create `app/Http/Controllers/Api/V1/Auth/RegisterDeviceController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Auth\RegisterDeviceRequest;
use App\Models\User;
use App\Services\Auth\DeviceRegistrationService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RegisterDeviceController
{
    public function __invoke(RegisterDeviceRequest $request, DeviceRegistrationService $registrations): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        /** @var array{public_key: string, android_id: string, model?: string|null, manufacturer?: string|null, os_version?: string|null, app_version?: string|null, integrity_token?: string|null} $data */
        $data = $request->validated();
        $device = $registrations->register($user, $data);

        return ApiResponse::success(
            ['device' => ['id' => $device->public_id, 'status' => $device->status->value]],
            'Phone registered. Waiting for approval.',
            201,
        );
    }
}
```

Create `app/Http/Controllers/Api/V1/Auth/DeviceStatusController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\DeviceStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\UserProfileResource;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\DeviceSignatureVerifier;
use App\Services\Auth\IssuedToken;
use App\Services\Auth\TokenIssuer;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Polled by the phone after registering. Signed, so only the phone holding the key can collect the session. */
final class DeviceStatusController
{
    public function __invoke(Request $request, DeviceSignatureVerifier $signatures, TokenIssuer $tokens, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $device = Device::query()
            ->where('public_id', (string) $request->header('X-Device-Id', ''))
            ->where('user_id', $user->id)
            ->first();

        if ($device === null) {
            throw new ApiException('NOT_FOUND', 'This phone has not been registered.', 404);
        }

        $signatures->verify($request, $device);

        if ($device->status !== DeviceStatus::Active) {
            return ApiResponse::success(['status' => $device->status->value, 'reason' => $device->revoke_reason]);
        }

        $issued = DB::transaction(function () use ($user, $device, $tokens, $request, $audit): IssuedToken {
            $issued = $tokens->issueCollectorToken($user, $device);

            $registration = $user->currentAccessToken();
            if ($registration instanceof PersonalAccessToken) {
                $registration->delete();
            }

            $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
            $audit->record('AUTH.LOGIN', $user, null, null, ['app' => 'collector', 'via' => 'device_approval', 'device' => $device->public_id]);

            return $issued;
        });

        return ApiResponse::success([
            'status' => DeviceStatus::Active->value,
            'token' => $issued->plainText,
            'expires_at' => ApiResponse::formatTime($issued->expiresAt),
            'user' => (new UserProfileResource($user))->resolve($request),
        ], 'Phone approved.');
    }
}
```

Create `app/Http/Controllers/Api/V1/Admin/ApproveDeviceController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Device;
use App\Models\User;
use App\Services\Auth\DeviceBindingService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApproveDeviceController
{
    public function __invoke(Request $request, Device $device, DeviceBindingService $binding): JsonResponse
    {
        $admin = $request->user();
        assert($admin instanceof User);
        $device = $binding->approve($device, $admin);

        return ApiResponse::success(['device' => ['id' => $device->public_id, 'status' => $device->status->value]], 'Phone approved.');
    }
}
```

Create `app/Http/Controllers/Api/V1/Admin/RevokeDeviceController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\RevokeDeviceRequest;
use App\Models\Device;
use App\Models\User;
use App\Services\Auth\DeviceBindingService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RevokeDeviceController
{
    public function __invoke(RevokeDeviceRequest $request, Device $device, DeviceBindingService $binding): JsonResponse
    {
        $admin = $request->user();
        assert($admin instanceof User);
        $device = $binding->revoke($device, $admin, $request->string('reason')->toString());

        return ApiResponse::success(['device' => ['id' => $device->public_id, 'status' => $device->status->value]], 'Phone revoked.');
    }
}
```

Create `app/Console/Commands/ListPendingDevicesCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Illuminate\Console\Command;

final class ListPendingDevicesCommand extends Command
{
    protected $signature = 'devices:pending';

    protected $description = 'List collector phones waiting for approval';

    public function handle(): int
    {
        $rows = Device::query()
            ->with('user')
            ->where('status', DeviceStatus::PendingApproval->value)
            ->orderBy('created_at')
            ->get()
            ->map(fn (Device $device): array => [
                $device->public_id,
                $device->user->name,
                $device->user->mobile,
                trim(($device->manufacturer ?? '').' '.($device->model ?? '')),
                $device->created_at?->setTimezone('Asia/Kolkata')->format('d M Y h:i A'),
            ]);

        $this->table(['Device', 'Collector', 'Mobile', 'Phone', 'Registered (IST)'], $rows->all());

        return self::SUCCESS;
    }
}
```

Create `app/Console/Commands/ApproveDeviceCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Auth\DeviceBindingService;
use Illuminate\Console\Command;

final class ApproveDeviceCommand extends Command
{
    protected $signature = 'devices:approve {device : Device public id}';

    protected $description = 'Approve a collector phone (for use until the admin panel ships)';

    public function handle(DeviceBindingService $binding): int
    {
        $device = Device::query()->where('public_id', (string) $this->argument('device'))->first();

        if ($device === null) {
            $this->error('No phone with that id.');

            return self::FAILURE;
        }

        $binding->approve($device, null);
        $this->info("Phone {$device->public_id} approved.");

        return self::SUCCESS;
    }
}
```

Create `app/Console/Commands/RevokeDeviceCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Auth\DeviceBindingService;
use Illuminate\Console\Command;

final class RevokeDeviceCommand extends Command
{
    protected $signature = 'devices:revoke {device : Device public id} {--reason= : Why the phone is being revoked}';

    protected $description = 'Revoke a collector phone and end its sessions';

    public function handle(DeviceBindingService $binding): int
    {
        $device = Device::query()->where('public_id', (string) $this->argument('device'))->first();
        $reason = trim((string) $this->option('reason'));

        if ($device === null || $reason === '') {
            $this->error('Give an existing phone id and a --reason.');

            return self::FAILURE;
        }

        $binding->revoke($device, null, $reason);
        $this->info("Phone {$device->public_id} revoked.");

        return self::SUCCESS;
    }
}
```

In `routes/api.php`, import the four new controllers and add, inside the `auth` group after the authenticated group:

```php
        Route::middleware(['auth:sanctum', 'ability:device:register', 'role:collector'])->prefix('device')->name('device.')->group(function (): void {
            Route::post('register', RegisterDeviceController::class)->middleware('throttle:device-register')->name('register');
            Route::get('status', DeviceStatusController::class)->name('status');
        });
```

and, inside the `v1` group after the `auth` group:

```php
    Route::middleware(['auth:sanctum', 'role:admin', 'password.changed'])->prefix('admin')->name('admin.')->group(function (): void {
        Route::post('devices/{device}/approve', ApproveDeviceController::class)->middleware('permission:devices.manage')->name('devices.approve');
        Route::post('devices/{device}/revoke', RevokeDeviceController::class)->middleware('permission:devices.manage')->name('devices.revoke');
    });
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --filter="DeviceRegistrationTest|DeviceApprovalTest"`
Expected: PASS (16 tests).

- [ ] **Step 8: Run the gates and commit**

Run: `composer lint && composer analyse && php artisan test` → Expected: all green.

```bash
git add backend-laravel
git commit -m "feat(devices): add phone registration, admin approval and revocation with audit"
```

---

### Task 11: Idempotency for every state-changing mobile call

**Files:**
- Create: `database/migrations/2026_10_02_000800_create_idempotency_keys_table.php`
- Create: `app/Enums/IdempotencyStatus.php`, `app/Models/IdempotencyKey.php`, `app/Http/Middleware/EnforceIdempotency.php`, `app/Console/Commands/PruneIdempotencyKeysCommand.php`
- Modify: `bootstrap/app.php` (alias), `routes/api.php` (device registration), `routes/console.php`
- Test: `tests/Feature/Http/IdempotencyTest.php`

**Interfaces:**
- Consumes: `User` (Task 3), `ApiException`, `ApiResponse` (Task 2).
- Produces:
  - Middleware alias `idempotent` (place after `auth:sanctum`): POST/PUT/PATCH/DELETE from an authenticated user must carry `Idempotency-Key` (8–100 chars `[A-Za-z0-9_-]`)
  - `EnforceIdempotency::hashFor(string $method, string $path, string $body): string`
  - Behaviour (spec §5.3): first call runs and stores the response; same key + same request replays it with header `Idempotent-Replayed: true`; same key + different method/path/body → 422 `IDEMPOTENCY_KEY_REUSED`; still running → 409 `REQUEST_IN_PROGRESS`; abandoned (lock older than 60 s) → runs again; 5xx responses are not stored; keys are per user; kept 48 h
  - Command `idempotency:prune` (hourly)
  - New error code `IDEMPOTENCY_KEY_REQUIRED` (422)

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Http/IdempotencyTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Enums\IdempotencyStatus;
use App\Exceptions\ApiException;
use App\Http\Middleware\EnforceIdempotency;
use App\Models\IdempotencyKey;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

final class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'a3f1c2d4-9b8e-4f10-8c22-6d5e4f3a2b1c';

    private static int $runs = 0;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        self::$runs = 0;

        Route::middleware(['api', 'auth:sanctum', 'idempotent'])->prefix('api/v1/_test')->group(function (): void {
            Route::post('collect', function (Request $request) {
                self::$runs++;

                return ApiResponse::success(['run' => self::$runs, 'amount_paise' => $request->integer('amount_paise')], 'Collected.', 201);
            });
            Route::post('refund', function () {
                self::$runs++;

                return ApiResponse::success(['run' => self::$runs]);
            });
            Route::post('crash', function () {
                self::$runs++;

                throw new RuntimeException('database went away');
            });
            Route::post('refuse', function () {
                self::$runs++;

                throw new ApiException('FLOAT_LIMIT_REACHED', 'Cash limit reached. Hand over cash at the vault.', 422);
            });
            Route::get('read', fn () => ApiResponse::success(['run' => ++self::$runs]));
        });

        $this->user = User::factory()->collector()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_state_changing_calls_must_carry_a_key(): void
    {
        $this->postJson('/api/v1/_test/collect', ['amount_paise' => 4000000])
            ->assertStatus(422)
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REQUIRED');

        $this->assertSame(0, self::$runs);
    }

    public function test_a_repeated_call_replays_the_first_response_without_running_again(): void
    {
        $first = $this->collect()->assertCreated()->assertJsonPath('data.run', 1);
        $second = $this->collect()->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json('data'), $second->json('data'));
        $this->assertSame(1, self::$runs);
        $this->assertSame(IdempotencyStatus::Completed, IdempotencyKey::query()->sole()->status);
    }

    public function test_the_same_key_with_a_different_body_is_refused(): void
    {
        $this->collect();

        $this->collect(['amount_paise' => 9900000])->assertStatus(422)->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');
        $this->assertSame(1, self::$runs);
    }

    public function test_the_same_key_on_a_different_endpoint_is_refused_not_replayed(): void
    {
        $this->collect();

        $this->postJson('/api/v1/_test/refund', ['amount_paise' => 4000000], ['Idempotency-Key' => self::KEY])
            ->assertStatus(422)
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');
        $this->assertSame(1, self::$runs);
    }

    public function test_a_call_that_is_still_running_answers_in_progress(): void
    {
        $this->seedKey(lockedUntil: now()->addSeconds(30));

        $this->collect()->assertStatus(409)->assertJsonPath('code', 'REQUEST_IN_PROGRESS');
        $this->assertSame(0, self::$runs);
    }

    public function test_an_abandoned_call_can_be_retried(): void
    {
        $this->seedKey(lockedUntil: now()->subSeconds(5));

        $this->collect()->assertCreated()->assertJsonPath('data.run', 1);
    }

    public function test_server_errors_are_not_stored_so_a_retry_runs_again(): void
    {
        $this->postJson('/api/v1/_test/crash', [], ['Idempotency-Key' => self::KEY])->assertStatus(500);
        $this->postJson('/api/v1/_test/crash', [], ['Idempotency-Key' => self::KEY])->assertStatus(500);

        $this->assertSame(2, self::$runs);
        $this->assertSame(0, IdempotencyKey::query()->count());
    }

    public function test_business_refusals_are_stored_and_replayed(): void
    {
        $this->postJson('/api/v1/_test/refuse', [], ['Idempotency-Key' => self::KEY])->assertStatus(422)->assertJsonPath('code', 'FLOAT_LIMIT_REACHED');
        $this->postJson('/api/v1/_test/refuse', [], ['Idempotency-Key' => self::KEY])->assertStatus(422)->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame(1, self::$runs);
    }

    public function test_keys_belong_to_one_user(): void
    {
        $this->collect()->assertJsonPath('data.run', 1);

        Sanctum::actingAs(User::factory()->collector()->create());

        $this->collect()->assertJsonPath('data.run', 2);
    }

    public function test_reads_need_no_key(): void
    {
        $this->getJson('/api/v1/_test/read')->assertOk();
    }

    public function test_expired_keys_are_pruned(): void
    {
        $this->seedKey(lockedUntil: null, expiresAt: now()->subHour(), key: 'expired-key-0001');
        $this->seedKey(lockedUntil: null, expiresAt: now()->addHour(), key: 'current-key-0001');

        $this->artisan('idempotency:prune')->assertSuccessful();

        $this->assertSame(['current-key-0001'], IdempotencyKey::query()->pluck('idem_key')->all());
    }

    /**
     * @param  array<string, int>  $body
     */
    private function collect(array $body = ['amount_paise' => 4000000]): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/_test/collect', $body, ['Idempotency-Key' => self::KEY]);
    }

    private function seedKey(?\DateTimeInterface $lockedUntil, ?\DateTimeInterface $expiresAt = null, string $key = self::KEY): void
    {
        IdempotencyKey::query()->create([
            'user_id' => $this->user->id,
            'idem_key' => $key,
            'method' => 'POST',
            'route' => '/api/v1/_test/collect',
            'request_hash' => EnforceIdempotency::hashFor('POST', '/api/v1/_test/collect', (string) json_encode(['amount_paise' => 4000000])),
            'status' => IdempotencyStatus::InProgress,
            'locked_until' => $lockedUntil,
            'expires_at' => $expiresAt ?? now()->addHours(48),
        ]);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=IdempotencyTest`
Expected: FAIL — `Class "App\Enums\IdempotencyStatus" not found`.

- [ ] **Step 3: Table, enum and model**

Create `database/migrations/2026_10_02_000800_create_idempotency_keys_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('idem_key', 100);
            $table->string('method', 10);
            $table->string('route', 191);
            $table->char('request_hash', 64);
            $table->string('status', 20);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->mediumText('response_body')->nullable();
            $table->dateTime('locked_until')->nullable();
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->unique(['user_id', 'idem_key']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
```

Create `app/Enums/IdempotencyStatus.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum IdempotencyStatus: string
{
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
}
```

Create `app/Models/IdempotencyKey.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IdempotencyStatus;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'user_id', 'idem_key', 'method', 'route', 'request_hash', 'status', 'response_status', 'response_body',
        'locked_until', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => IdempotencyStatus::class,
            'response_status' => 'integer',
            'locked_until' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
```

- [ ] **Step 4: The middleware and the prune command**

Create `app/Http/Middleware/EnforceIdempotency.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\IdempotencyStatus;
use App\Exceptions\ApiException;
use App\Models\IdempotencyKey;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Makes a state-changing call safe to retry (spec §5.3). A double tap or a network retry
 * with the same Idempotency-Key gets the first answer back instead of a second execution.
 */
final class EnforceIdempotency
{
    public const HEADER = 'Idempotency-Key';

    private const LOCK_SECONDS = 60;

    private const RETENTION_HOURS = 48;

    public static function hashFor(string $method, string $path, string $body): string
    {
        return hash('sha256', strtoupper($method)."\n".$path."\n".$body);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $key = (string) $request->header(self::HEADER, '');

        if (preg_match('/^[A-Za-z0-9_-]{8,100}$/', $key) !== 1) {
            throw new ApiException('IDEMPOTENCY_KEY_REQUIRED', 'Send a unique Idempotency-Key header with this request.', 422);
        }

        $claim = $this->claim($user, $key, $request);

        if ($claim instanceof Response) {
            return $claim;
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $claim->delete();

            throw $e;
        }

        if ($response->getStatusCode() >= 500) {
            $claim->delete();
        } else {
            $claim->forceFill([
                'status' => IdempotencyStatus::Completed,
                'response_status' => $response->getStatusCode(),
                'response_body' => (string) $response->getContent(),
                'locked_until' => null,
            ])->save();
        }

        return $response;
    }

    private function claim(User $user, string $key, Request $request): IdempotencyKey|Response
    {
        $path = $request->getPathInfo();
        $hash = self::hashFor($request->getMethod(), $path, (string) $request->getContent());

        try {
            return IdempotencyKey::query()->create([
                'user_id' => $user->id,
                'idem_key' => $key,
                'method' => $request->getMethod(),
                'route' => mb_substr($path, 0, 191),
                'request_hash' => $hash,
                'status' => IdempotencyStatus::InProgress,
                'locked_until' => now()->addSeconds(self::LOCK_SECONDS),
                'expires_at' => now()->addHours(self::RETENTION_HOURS),
            ]);
        } catch (UniqueConstraintViolationException) {
            // The key was used before: decide below whether to replay, refuse or take over.
        }

        $existing = IdempotencyKey::query()->where('user_id', $user->id)->where('idem_key', $key)->firstOrFail();

        if (! hash_equals($existing->request_hash, $hash)) {
            return ApiResponse::error('IDEMPOTENCY_KEY_REUSED', 'This Idempotency-Key was already used for a different request.', 422);
        }

        if ($existing->status === IdempotencyStatus::Completed) {
            return new HttpResponse((string) $existing->response_body, (int) $existing->response_status, [
                'Content-Type' => 'application/json',
                'Idempotent-Replayed' => 'true',
            ]);
        }

        $taken = IdempotencyKey::query()
            ->whereKey($existing->id)
            ->where('status', IdempotencyStatus::InProgress->value)
            ->where(fn ($query) => $query->whereNull('locked_until')->orWhere('locked_until', '<', now()))
            ->update(['locked_until' => now()->addSeconds(self::LOCK_SECONDS)]);

        if ($taken === 1) {
            return $existing->refresh();
        }

        return ApiResponse::error('REQUEST_IN_PROGRESS', 'This request is still being processed. Try again in a moment.', 409);
    }
}
```

Create `app/Console/Commands/PruneIdempotencyKeysCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IdempotencyKey;
use Illuminate\Console\Command;

final class PruneIdempotencyKeysCommand extends Command
{
    protected $signature = 'idempotency:prune';

    protected $description = 'Delete idempotency records past their retention window';

    public function handle(): int
    {
        $deleted = IdempotencyKey::query()->where('expires_at', '<', now())->delete();
        $this->info("Deleted {$deleted} expired idempotency record(s).");

        return self::SUCCESS;
    }
}
```

In `bootstrap/app.php`, add the alias `'idempotent' => EnforceIdempotency::class,` (with its import).

In `routes/api.php`, change the device registration route middleware to:

```php
            Route::post('register', RegisterDeviceController::class)->middleware(['throttle:device-register', 'idempotent'])->name('register');
```

Append to `routes/console.php`:

```php
Schedule::command('idempotency:prune')->hourly();
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter="IdempotencyTest|DeviceRegistrationTest"`
Expected: PASS.

- [ ] **Step 6: Run the gates and commit**

Run: `composer lint && composer analyse && php artisan test` → Expected: all green.

```bash
git add backend-laravel
git commit -m "feat(api): add idempotency keys with replay, conflict and abandonment handling"
```

---

### Task 12: Admin bootstrap, scheduler wiring, developer docs and M0 sign-off

**Files:**
- Create: `app/Console/Commands/CreateAdminCommand.php`
- Modify: `routes/console.php`
- Create: `docs/development.md`
- Modify: `docs/architecture.md` (Appendix B codes, §5.2 signing scope, §20 migration note)
- Test: `tests/Feature/Admin/CreateAdminCommandTest.php`, `tests/Feature/Platform/SchedulerTest.php`

**Interfaces:**
- Consumes: everything above.
- Produces: `php artisan cms:create-admin {name} {mobile} {email} {--super} {--preset=*}` — creates an ADMIN with a random 16-character one-time password (printed once, never stored in plain text or audited), `must_change_password = true`, permissions from the presets, audit `USER.CREATED` by SYSTEM.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/CreateAdminCommandTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin_with_a_one_time_password_that_must_be_changed(): void
    {
        $this->artisan('cms:create-admin', ['name' => 'Suriya', 'mobile' => '98120 00001', 'email' => 'Owner@Example.com', '--super' => true])
            ->expectsOutputToContain('One-time password:')
            ->assertSuccessful();

        $admin = User::query()->where('email', 'owner@example.com')->sole();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertSame('+919812000001', $admin->mobile);
        $this->assertTrue($admin->is_super_admin);
        $this->assertTrue($admin->must_change_password);
        $this->assertSame(1, AuditLog::query()->where('action', 'USER.CREATED')->where('actor_type', 'SYSTEM')->count());
    }

    public function test_presets_grant_exactly_their_permissions(): void
    {
        $this->artisan('cms:create-admin', ['name' => 'Vault Clerk', 'mobile' => '9812000009', 'email' => 'vault@example.com', '--preset' => ['vault_finance']])
            ->assertSuccessful();

        $admin = User::query()->where('email', 'vault@example.com')->sole();
        $this->assertTrue($admin->can('vault.signoff'));
        $this->assertFalse($admin->can('devices.manage'));
        $this->assertFalse($admin->is_super_admin);
    }

    public function test_bad_input_is_refused_and_nobody_is_created(): void
    {
        $this->artisan('cms:create-admin', ['name' => 'X', 'mobile' => '12345', 'email' => 'x@example.com'])->assertFailed();
        $this->artisan('cms:create-admin', ['name' => 'X', 'mobile' => '9812000001', 'email' => 'not-an-email'])->assertFailed();
        $this->artisan('cms:create-admin', ['name' => 'X', 'mobile' => '9812000001', 'email' => 'x@example.com', '--preset' => ['distributor']])->assertFailed();

        $this->assertSame(0, User::query()->count());
    }

    public function test_duplicate_mobile_or_email_is_refused(): void
    {
        User::factory()->admin()->create(['mobile' => '+919812000001', 'email' => 'taken@example.com']);

        $this->artisan('cms:create-admin', ['name' => 'X', 'mobile' => '9812000001', 'email' => 'new@example.com'])->assertFailed();
        $this->artisan('cms:create-admin', ['name' => 'X', 'mobile' => '9812000002', 'email' => 'taken@example.com'])->assertFailed();
    }
}
```

Create `tests/Feature/Platform/SchedulerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Tests\TestCase;

final class SchedulerTest extends TestCase
{
    public function test_every_m0_maintenance_job_is_scheduled(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('queue:work')
            ->expectsOutputToContain('audit:seal')
            ->expectsOutputToContain('audit:verify-chain')
            ->expectsOutputToContain('security:prune-nonces')
            ->expectsOutputToContain('idempotency:prune')
            ->expectsOutputToContain('sanctum:prune-expired')
            ->assertSuccessful();
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter="CreateAdminCommandTest|SchedulerTest"`
Expected: FAIL — `The command "cms:create-admin" does not exist.` and missing `queue:work` in the schedule.

- [ ] **Step 3: Implement the command and finish the schedule**

Create `app/Console/Commands/CreateAdminCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AdminPermissionPreset;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Audit\AuditActor;
use App\Services\Audit\AuditLogger;
use App\Support\MobileNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** Bootstraps admin logins (the PDF's sample passwords are never used — spec §21 A32). */
final class CreateAdminCommand extends Command
{
    protected $signature = 'cms:create-admin
        {name : Full name}
        {mobile : Indian mobile number}
        {email : Login email}
        {--super : Grant every admin permission}
        {--preset=* : Permission preset: operations, vault_finance, viewer}';

    protected $description = 'Create an admin login with a one-time password';

    public function handle(AuditLogger $audit): int
    {
        $name = trim((string) $this->argument('name'));
        $email = strtolower(trim((string) $this->argument('email')));
        $mobile = MobileNumber::normalize((string) $this->argument('mobile'));

        $presets = [];
        foreach ((array) $this->option('preset') as $value) {
            $preset = AdminPermissionPreset::tryFrom((string) $value);
            if ($preset === null) {
                $this->error("Unknown preset [{$value}]. Use operations, vault_finance or viewer.");

                return self::FAILURE;
            }
            $presets[] = $preset;
        }

        $validator = Validator::make(['name' => $name, 'email' => $email], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191'],
        ]);

        if ($mobile === null) {
            $this->error('That is not a valid Indian mobile number.');

            return self::FAILURE;
        }

        if ($validator->fails()) {
            $this->error((string) $validator->errors()->first());

            return self::FAILURE;
        }

        if (User::withTrashed()->where('mobile', $mobile)->orWhere('email', $email)->exists()) {
            $this->error('A user with that mobile number or email already exists.');

            return self::FAILURE;
        }

        $password = Str::password(16, symbols: false);
        $super = (bool) $this->option('super');

        $admin = DB::transaction(function () use ($name, $mobile, $email, $password, $super, $presets, $audit): User {
            $admin = new User([
                'name' => $name,
                'mobile' => $mobile,
                'email' => $email,
                'password' => $password,
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
                'must_change_password' => true,
            ]);
            $admin->is_super_admin = $super;
            $admin->save();

            $permissions = [];
            foreach ($presets as $preset) {
                foreach ($preset->permissions() as $permission) {
                    $permissions[$permission->value] = $permission->value;
                }
            }

            if ($permissions !== []) {
                $admin->givePermissionTo(array_values($permissions));
            }

            $audit->record('USER.CREATED', $admin, null, [
                'role' => UserRole::Admin->value,
                'is_super_admin' => $super,
                'permissions' => array_values($permissions),
            ], ['via' => 'cms:create-admin'], AuditActor::system());

            return $admin;
        });

        $this->info("Admin {$admin->name} created ({$admin->mobile}, {$admin->email}).");
        $this->warn("One-time password: {$password}");
        $this->line('Share it privately. It must be changed at first login.');

        return self::SUCCESS;
    }
}
```

Replace `routes/console.php` with the complete M0 schedule (spec §19.4):

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Hostinger Cloud has no supervisor: the scheduler drains the database queue every minute.
Schedule::command('queue:work database --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping()->runInBackground();

Schedule::command('audit:seal')->everyMinute()->withoutOverlapping();
Schedule::command('audit:verify-chain')->weeklyOn(0, '03:00')->timezone('Asia/Kolkata');

Schedule::command('security:prune-nonces')->hourly();
Schedule::command('idempotency:prune')->hourly();
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('02:00')->timezone('Asia/Kolkata');
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter="CreateAdminCommandTest|SchedulerTest"`
Expected: PASS (5 tests).

- [ ] **Step 5: Write the developer guide**

Create `docs/development.md`:

````markdown
# Local development

## Prerequisites (Windows)

- PHP 8.3+ on `PATH` (`php -v`), with bcmath, curl, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, sodium, zip.
- Composer 2 (`composer -V`).
- The portable MariaDB 11.4 in `C:\tools\mariadb-11.4.13-winx64` (see the M0 plan, Task 1, for the one-time install).
  XAMPP's own MariaDB on port 3306 is not used by this project.

## Daily commands

```powershell
powershell -File tools/dev/mariadb.ps1 start     # MariaDB on 127.0.0.1:3307
cd backend-laravel
php artisan migrate                               # dev database `cms`
php artisan serve                                 # http://localhost:8000
composer lint; composer analyse; php artisan test # the three quality gates (CI runs the same)
powershell -File tools/dev/mariadb.ps1 stop
```

Tests always run against the `cms_test` database on MariaDB, never SQLite, because locking and constraint behaviour must match production.

## Accounts

- First admin: `php artisan cms:create-admin "Full Name" 98XXXXXXXX you@example.com --super`. The one-time password is printed once and must be changed at first login.
- Collector phones: after a collector registers a phone in the app, list and approve it with `php artisan devices:pending` and `php artisan devices:approve <device-id>` until the admin panel ships (M3).

## Conventions (see `docs/architecture.md`)

- Money: integer paise in `*_paise` columns. Never floats.
- Time: stored in UTC; shown in Asia/Kolkata. Use the server clock, never the phone's.
- IDs: APIs expose `public_id` (ULID) only.
- Every state-changing mobile call carries an `Idempotency-Key`; every collector call is signed by the phone's key.
- Every security- or money-relevant action writes an audit row inside the same database transaction.
- Exactly three roles: admin, collector, retailer.
````

- [ ] **Step 6: Bring the architecture spec in line with what M0 built**

In `docs/architecture.md`:

1. In **§5.2**, change the signature row's "Who" cell from `Collector writes (marked **S** below)` to `Every collector request, reads included (writes are marked **S** below)`.
2. In **Appendix B**, add to the **Generic** row: `UNAUTHENTICATED` (401), `METHOD_NOT_ALLOWED` (405), `HTTP_ERROR` (other 4xx), `IDEMPOTENCY_KEY_REQUIRED` (422); add to the **Auth and device** row: `DEVICE_ALREADY_BOUND` (409), `INVALID_DEVICE_STATE` (409), `COLLECTOR_SUSPENDED` (403).
3. At the end of the **§20** introduction paragraph, add: `Each milestone creates the tables it implements, with their tests; M0 creates the identity, audit, settings, actor, zone, device and idempotency tables.`

- [ ] **Step 7: Full verification**

Run, from `backend-laravel`:

```bash
php artisan migrate:fresh --seed
composer lint
composer analyse
php artisan test
```

Expected: migrations and seeding succeed on the dev database; Pint and PHPStan report nothing; every test passes. Record the final test count in the commit message.

- [ ] **Step 8: Commit**

```bash
cd /c/xampp/htdocs/guruji
git add backend-laravel docs
git commit -m "feat(m0): add admin bootstrap command, full maintenance schedule and developer guide"
```

---

## Self-review (run before execution)

**Spec coverage (M0 row of spec §20):** monorepo, Laravel 13 scaffold, standards and CI → Task 1 · migrations on MariaDB → Tasks 1, 3–8, 11 (domain tables move to their milestones, noted in the header) · seeders → Tasks 3, 12 · auth for three roles → Tasks 3, 8, 9 · device binding and request signing → Tasks 7, 9, 10 · AuditLogger → Task 4 · idempotency middleware → Task 11 · settings → Task 5. Security items from §18 that M0 owns: rate limits (Task 8), lockout (Task 8), token expiry and TOKEN_EXPIRED (Task 8), replay protection (Task 7), secure headers (Task 2), audit chain (Task 4). Admin TOTP 2FA and the admin session UI arrive with the admin panel in M3.

**Placeholder scan:** no "TBD", "TODO", "handle edge cases" or "similar to Task N"; every code step contains the code.

**Type consistency:** `ApiException(errorCode, message, status, errors, data)` everywhere; `AuditLogger::record(action, entity, before, after, meta, actor)` everywhere; `IssuedToken::from(NewAccessToken, User)`; `DeviceSignatureVerifier::verify(Request, Device)`; `DeviceBindingService::approve(Device, ?User)` / `revoke(Device, ?User, string)`; route names `api.v1.auth.me|logout|password.change|token.rotate` match `EnsurePasswordChanged::ALLOWED_ROUTES`.

**Review Focus coverage:** clock skew message (Task 7), mobile formats (Task 8), Hindi audit text (Task 4), cross-endpoint key reuse (Task 11), TOKEN_EXPIRED (Task 8).
