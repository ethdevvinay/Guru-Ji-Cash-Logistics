# Enterprise Cybersecurity & Defense-in-Depth Specification
## Distributor Cash Collection & Retailer Operations Management Platform

---

### Executive Security Summary

This platform has been architected to financial-grade and VAPT (Vulnerability Assessment and Penetration Testing) standards. All operational, financial, and geographic actions operate on an **authoritative, zero-trust server validation model**.

Client claims (mobile application timestamps, coordinates, calculated balances, or transaction completion claims) are treated as untrusted inputs. The **Laravel backend and MySQL 8.0 database engine** are the sole arbiters of truth.

---

### 1. OWASP Top 10 Mitigation Matrix

| Vulnerability Category | Threat Vector in Cash Logistics | Implemented Countermeasure & Architecture |
| :--- | :--- | :--- |
| **A01: Broken Access Control (IDOR)** | Retailer A inspecting Retailer B's cash orders; Collector A submitting cash for Collector B's job. | **Strict Ownership Scoping & Policies:** Every database query enforces actor ownership (e.g. `where('retailer_id', $user->retailer->id)`). Collector endpoints reject unassigned jobs before executing counting logic. |
| **A02: Cryptographic Failures** | Token replay, cleartext credentials, exposed database backups. | **Bcrypt cost 12** for passwords, **Laravel Sanctum SHA-256** hashed tokens, HTTPS/TLS enforcement with HSTS (`max-age=31536000`), AES-256 encrypted sessions. |
| **A03: Injection (SQLi / XSS)** | Malicious SQL in search/filter bars, stored XSS in store remarks or broadcast modals. | **Strict Parameterized Queries (PDO)**, Eloquent ORM `$fillable` protection, Blade automatic HTML escaping (`{{ }}`), and strict `Content-Security-Policy` headers. |
| **A04: Insecure Design (Double-Spend / Race Conditions)** | 2 collectors tapping `[ACCEPT]` at the exact same millisecond; double-tap on wallet debit. | **Pessimistic Row-Level Locking:** `SELECT ... FOR UPDATE` wraps pickup acceptance. **Idempotency Engine:** `X-Idempotency-Key` tracking with database unique constraints prevents double transactions. |
| **A05: Security Misconfiguration** | Directory listing, server version banners, loose CORS/CSRF. | **OWASP Security Headers Middleware:** Disables `X-Powered-By`, sets `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`. Block access to all dotfiles (`.env`, `.git`). |
| **A06: Vulnerable Components** | Outdated packages, known CVEs in libraries. | Minimal production dependency footprint. Native Laravel 11.x, PHP 8.3+, and zero unnecessary node/npm dependencies in production. |
| **A07: Identification & Auth Failures** | Credential brute-forcing, password spraying, session fixation. | **Rate Limiting:** `throttle:10,1` on login. Hardware Device Binding (`EnsureDeviceBound`) prevents token usage from unauthorized phones. |
| **A08: Software & Data Integrity Failures** | Manipulation of currency denomination calculations or float limits. | **Double-Entry Minor Units:** All financial math uses integer paise (`BIGINT UNSIGNED`). Denomination engine validates $\sum(\text{note} \times \text{count}) \equiv \text{collected cash}$ on server. |
| **A09: Security Logging & Monitoring Failures** | Undetected GPS spoofing, tampered financial records. | **Immutable Audit Logs:** Eloquent model blocks all `update` and `delete` operations. Logs actor ID, IP, user-agent, correlation UUID, and before/after states. |
| **A10: Server-Side Request Forgery (SSRF)** | Malicious webhook or provider URL injection. | Strictly hardcoded whitelisted endpoints for Recharge (Jio/Airtel) and BBPS billers configured in server-side `.env`. |

---

### 2. Multi-Signal Anti-Mock GPS & Geofence Engine

A critical threat vector in field cash collection is an executive using Mock Location tools to claim arrivals from their residence.

#### Defensive Layers Implemented in `GeofenceVerificationService`:
1. **Haversine Proximity Barrier ($\le 100\text{m}$):**
   $$d = 2R \cdot \arcsin\left(\sqrt{\sin^2\left(\frac{\Delta\phi}{2}\right) + \cos\phi_1\cos\phi_2\sin^2\left(\frac{\Delta\lambda}{2}\right)}\right)$$
   Cash counting desk remains cryptographically locked until server evaluates $d \le 100.0\text{ meters}$.
2. **Teleportation & Velocity Anomaly Detection:**
   Compares current coordinate ping against the collector's last known position:
   $$\text{Velocity} = \frac{\Delta\text{Distance (km)}}{\Delta\text{Time (hours)}}$$
   If velocity exceeds $120\text{ km/h}$ over a distance $> 200\text{m}$, the request is rejected with `GPS_TELEPORTATION_ANOMALY` and logged.
3. **Hardware Accuracy Sanitization:**
   Rejects GPS coordinates with reported accuracy $> 30.0\text{m}$ (insufficient signal) or $\le 0.0\text{m}$ (classic emulator artifact).
4. **Timestamp Drift & Replay Window:**
   Location pings older than $30\text{ seconds}$ are rejected (`Stale GPS reading`).

---

### 3. Hardware Device Binding Architecture

- Collectors cannot log in or perform duties from an arbitrary or newly swapped mobile device.
- Mobile client supplies unique hardware UUID via `X-Device-Id`.
- Server-side middleware `EnsureDeviceBound` validates:
  1. The device record is active and bound to the authenticated user ID.
  2. The device is not flagged as stolen/revoked.
  3. Updates the device heartbeat timestamp.
- If a collector loses their phone, only an **Administrator** can issue an authorization reset from the Admin Collectors Desk.

---

### 4. Double-Entry Financial Ledger & Concurrency Safety

1. **Integer Minor Units (Paise):**
   Floating-point arithmetic (`0.1 + 0.2 != 0.3`) is completely avoided. ₹40,000.00 is stored and calculated as `4000000` paise.
2. **Atomic Row Locks:**
   All balance updates acquire exclusive row locks:
   ```php
   $wallet = Wallet::where('retailer_id', $retailerId)->lockForUpdate()->firstOrFail();
   ```
3. **Idempotency Keys (`X-Idempotency-Key`):**
   Recharge and BBPS operations require a client-generated UUID. If an executive double-taps the pay button or the mobile network retries a POST request, the server detects the existing key and returns the original transaction record without a second debit.

---

### 5. Document Management & Private Storage Security

- GST Certificates, Shop Photos, Aadhaar/PAN cards, and Bank Deposit Slips are stored in **private non-public storage** (`storage/app/private/`).
- Direct URL browsing is prevented (`.htaccess` rejects `/storage/app/` requests).
- Downloads require temporary signed cryptographic URLs with a 5-minute expiry window:
  ```php
  URL::temporarySignedRoute('documents.download', now()->addMinutes(5), ['id' => $doc->id]);
  ```
- File uploads are validated using `finfo` magic-byte MIME inspection (allowing only `image/jpeg`, `image/png`, `application/pdf`) and capped at $5\text{MB}$.
