# 🚨 NewTask.md — Security Activity & Threat Detection System

## 🎯 Tujuan

Membangun sistem **Security Activity + Threat Detection** untuk:

1. Mendeteksi percobaan serangan **sebelum login (guest)**
2. Mendeteksi penyalahgunaan **setelah login (user internal)**
3. Menyediakan **audit trail lengkap + risk scoring + response otomatis**

---

# 🧱 1. Blueprint Database

## 📌 1.1 request_activity

**Fungsi:** log semua request penting sebagai dasar analisis

**Field utama:**

- request_id (unique)
- occurred_at
- user_id (nullable)
- ip_address
- user_agent
- method, path, route_name
- status_code
- response_time_ms
- query_fingerprint, body_fingerprint
- is_suspicious
- risk_score

---

## 📌 1.2 security_events

**Fungsi:** menyimpan semua event mencurigakan / serangan

**Field utama:**

- event_code
- category
- severity (low/medium/high/critical)
- risk_score
- actor_type (guest/user)
- user_id (nullable)
- ip_address
- route_name
- detection_stage (before_login / after_login)
- detection_source
- payload_summary
- action_taken

---

## 📌 1.3 auth_activity

**Fungsi:** log autentikasi

**Field utama:**

- auth_event (LOGIN_SUCCESS, LOGIN_FAILED, dll)
- result
- user_id (nullable)
- identifier_masked
- ip_address
- session_hash
- attempt_count
- risk_score

---

## 📌 1.4 admin_audit_logs

**Fungsi:** audit aktivitas user internal

**Field utama:**

- user_id
- module_name
- action_name (CREATE/UPDATE/DELETE/etc)
- target_type + target_id
- before_snapshot
- after_snapshot
- diff_summary
- is_sensitive
- risk_score

---

## 📌 1.5 security_blocks

**Fungsi:** menyimpan IP/user yang diblok

**Field utama:**

- block_type (ip/user/session)
- block_value
- reason_code
- severity
- expires_at
- is_active

---

## 📌 1.6 security_event_rules

**Fungsi:** rule deteksi

**Field utama:**

- rule_code
- category
- severity
- detection_type
- threshold_count
- pattern_text
- default_action

---

# 🔄 2. Flow Middleware

## 📊 Alur Request

```
Request Masuk
 ↓
[1] Request Context
 ↓
[2] Block Checker
 ↓
[3] Request Inspection
 ↓
[4] Rate Limit Detector
 ↓
[5] Auth Middleware
 ↓
[6] Authorization Audit
 ↓
[7] Controller / Service
 ↓
[8] Business Audit Logger
 ↓
[9] Response Recorder
```

---

## 📌 Detail Per Layer

### [1] Request Context

- Generate `request_id`
- Catat waktu & metadata awal

---

### [2] Block Checker

- Cek IP / user diblok
- Jika iya → stop + log event

---

### [3] Request Inspection

Deteksi:

- SQL Injection
- XSS
- Path Traversal
- Header aneh
- Route scanning

Output:

- tambah risk_score
- tandai suspicious
- create security_event jika perlu

---

### [4] Rate Limit Detector

Deteksi:

- brute force login
- spam request
- flood 404

Action:

- throttle
- block sementara

---

### [5] Auth Middleware

- login success/fail log
- isi user_id
- session validation

---

### [6] Authorization Audit

- tangkap akses tanpa izin
- log forbidden access

---

### [7] Controller

- validasi tampering
- validasi upload
- validasi data integrity

---

### [8] Business Audit Logger

- log CRUD
- simpan before/after
- tandai aksi sensitif

---

### [9] Response Recorder

- simpan status_code
- simpan response_time
- persist request_activity

---

# 📚 3. Event List Detail

## 🔴 Guest / Pre-login

### Recon

- GUEST_ROUTE_SCAN
- GUEST_404_FLOOD
- GUEST_SENSITIVE_FILE_PROBE

### Injection

- GUEST_SQLI_PATTERN
- GUEST_XSS_PATTERN
- GUEST_PATH_TRAVERSAL

### Auth Abuse

- GUEST_LOGIN_FAILED
- GUEST_BRUTEFORCE_IP
- GUEST_CREDENTIAL_STUFFING_PATTERN

---

## 🟡 Auth / Session

- AUTH_LOGIN_SUCCESS
- AUTH_LOGIN_FAILED
- AUTH_SESSION_HIJACK_SUSPECTED
- AUTH_MULTI_IP_ANOMALY

---

## 🔵 Internal Abuse

### Authorization

- USER_FORBIDDEN_ROUTE_ACCESS
- USER_PRIVILEGE_ESCALATION_ATTEMPT

### Tampering

- USER_PARAMETER_TAMPERING
- USER_PRICE_MANIPULATION

### Upload

- USER_SUSPICIOUS_UPLOAD
- USER_MIME_EXTENSION_MISMATCH

### Sensitive Actions

- USER_BULK_DELETE_ATTEMPT
- USER_EXPORT_ABUSE
- USER_CONFIG_CHANGE
- USER_ROLE_CHANGE

---

## ⚫ System

- SYSTEM_CSRF_FAILED
- SYSTEM_SIGNATURE_INVALID
- SYSTEM_LOGGER_FAILED

---

# 📈 4. Risk Score & Severity

## Risk Level

- 0–29 → Normal
- 30–59 → Suspicious
- 60–89 → Dangerous
- 90+ → Critical

---

# ⚙️ 5. Checklist Implementasi

## 🧩 Phase 1 — Foundation ✅ SELESAI

- [✔] Generate request_id
- [✔] Tabel request_activity dibuat
- [✔] Tabel auth_activity dibuat
- [✔] Tabel security_events dibuat
- [✔] Logging request berjalan
- [✔] Logging login success/fail berjalan

**Bukti/Test:** `tests/security_phase1_verify.php` → **28/28 passed**

```
[1] request_activity     → [PASS] Row inserted, method, status_code, response_time_ms, request_id
[2] auth_activity LOGIN_SUCCESS → [PASS] event_code, result, user_id, identifier masked
[3] auth_activity LOGIN_FAILED  → [PASS] event_code, result, user_id=NULL, risk_score, masked
[4] security_events SQLI        → [PASS] event_code, severity, risk_score, payload, no raw password
[5] admin_audit_logs UPDATE     → [PASS] module, action, target_id, before/after snapshot, diff_summary
```

**File:**
- `database/update/20260412_000000_security_phase1_foundation.sql` — migration batch 15
- `app/Services/SecurityLogger.php`
- `app/Middleware/RequestActivityMiddleware.php`
- `app/Controllers/AuthController.php` — hook login/logout
- `app/Middleware/Authenticate.php` — hook session hijack

---

## 🔍 Phase 2 — Detection ✅ SELESAI

- [✔] Middleware request inspection aktif
- [✔] SQLi detection berjalan
- [✔] XSS detection berjalan
- [✔] Path traversal detection berjalan
- [✔] Brute force detection aktif
- [✔] Rate limit berjalan

**Bukti/Test:** `tests/security_phase2_verify.php` → **29/29 passed**

```
[1]  Migration security_blocks + security_event_rules → [PASS] exists, 8 rules seeded
[2]  ThreatInspector SQLi          → [PASS] detected, risk_score >= 70, event_code correct
[3]  ThreatInspector XSS           → [PASS] detected, risk_score >= 65, event_code correct
[4]  ThreatInspector Path Traversal → [PASS] detected, risk_score >= 65, event_code correct
[5]  ThreatInspector Sensitive probe → [PASS] detected, GUEST_SENSITIVE_FILE_PROBE
[6]  ThreatInspector clean request  → [PASS] not flagged, risk_score = 0 (no false positive)
[7]  RateLimitDetector 404 flood    → [PASS] threshold triggered at 10 hits
[8]  RateLimitDetector brute force  → [PASS] blocked after 5 failures
[9]  RateLimitDetector reset        → [PASS] counter reset, not blocked after reset
[10] SecurityLogger security_event  → [PASS] inserted, event_code, severity, detection_stage
[11] security_event_rules seeded    → [PASS] CSRF rule, brute force rule exist
```

**File:**
- `database/update/20260412_010000_security_phase2_detection.sql` — migration batch 16
- `app/Services/ThreatInspector.php`
- `app/Services/RateLimitDetector.php`
- `app/Middleware/RequestInspectionMiddleware.php`
- `app/Middleware/RateLimitMiddleware.php`
- `app/Middleware/VerifyCsrfToken.php` — hook CSRF log
- `app/Services/AuthService.php` — hook brute force counter
- `bootstrap/app.php` — register middleware stack

---

## 🔍 Phase 3 — Audit ✅ SELESAI

- [✔] Tabel admin_audit_logs dibuat
- [✔] CRUD audit berjalan
- [✔] Before/after snapshot tersimpan
- [✔] Upload audit berjalan
- [✔] Export/delete audit berjalan

**Bukti/Test:** `tests/security_phase3_verify.php` → **24/24 passed**

```
[1]  admin_audit_logs table + is_sensitive column → [PASS]
[2]  logAudit CREATE — row, module, action, snapshot, diff → [PASS]
[3]  logAudit UPDATE — before/after snapshot, diff changed → [PASS]
[4]  logAudit DELETE — diff_summary Deleted → [PASS]
[5]  is_sensitive flag + risk_score tersimpan → [PASS]
[6]  audit rules seeded (10 rules) → [PASS]
[7]  USER_ROLE_CHANGE event — severity, risk_score → [PASS]
[8]  USER_DOUBLE_EXTENSION_UPLOAD — critical, blocked → [PASS]
[9]  No raw password in audit snapshot → [PASS]
```

**File:**
- `database/update/20260412_020000_security_phase3_audit.sql` — migration batch 17
- `app/Services/SecurityLogger.php` — redact sensitive fields di snapshot
- `app/Controllers/KeuanganController.php` — hook audit CREATE/UPDATE/DELETE akun & mutasi
- `app/Controllers/DiskonController.php` — hook audit CREATE/UPDATE/DELETE diskon
- `app/Controllers/FileManagerController.php` — double extension + MIME mismatch detection
- `app/Controllers/BarangController.php` — sudah ada dari sebelumnya
- `app/Controllers/UsersController.php` — sudah ada dari sebelumnya

---

## ⚡ Phase 4 — Response ✅ SELESAI

- [✔] Tabel security_blocks aktif (+ kolom blocked_by, unblocked_at)
- [✔] Auto block IP berjalan
- [✔] Session revoke berjalan
- [✔] Throttle request aktif
- [✔] Alert system aktif

**Bukti/Test:** `tests/security_phase4_verify.php` → **30/30 passed**

```
[1]  security_blocks table + blocked_by + unblocked_at → [PASS]
[2]  blockIp — row inserted, reason, blocked_by, is_active → [PASS]
[3]  isIpBlocked — blocked detected, clean not blocked → [PASS]
[4]  unblockIp — unblocked, unblocked_at set → [PASS]
[5]  blockUser + isUserBlocked + unblockUser → [PASS]
[6]  blockSession — session blocked → [PASS]
[7]  autoBlockFromBruteForce — IP blocked, reason, blocked_by → [PASS]
[8]  SYSTEM_IP_BLOCKED event — inserted, action_taken blocked → [PASS]
[9]  AlertService — log file, CRITICAL entry, event logged → [PASS]
[10] AlertService::tail — returns entries → [PASS]
[11] BlockCheckerMiddleware registered in web group → [PASS]
[12] Expired block not detected as active → [PASS]
```

**File:**
- `database/update/20260412_030000_security_phase4_response.sql` — migration batch 18
- `app/Services/BlockerService.php` — block IP/user/session, auto-block, session revoke
- `app/Services/AlertService.php` — alert log file + security_event
- `app/Middleware/BlockCheckerMiddleware.php` — Layer 2, cek block sebelum request
- `app/Services/RateLimitDetector.php` — auto-block IP saat brute force
- `app/Middleware/RateLimitMiddleware.php` — auto-block IP saat spam
- `app/Middleware/RequestInspectionMiddleware.php` — AlertService::critical saat risk >= 90
- `bootstrap/app.php` — register BlockCheckerMiddleware di web + api group

---

## 📊 Phase 5 — Monitoring ✅ SELESAI

- [✔] Dashboard security dibuat
- [✔] Filter by severity tersedia
- [✔] Filter by IP/user tersedia
- [✔] Event timeline tersedia

**Bukti/Test:** `tests/security_phase5_verify.php` → **24/24 passed**

```
[1]  Routes /security + datatable + unblock → [PASS]
[2]  View security/index.php exists → [PASS]
[3]  SecurityController exists → [PASS]
[4]  Sidebar Security Monitor link + $isSecurity → [PASS]
[5]  Overview stats queries (events, blocks, logins, audit) → [PASS]
[6]  Timeline 24h query (GROUP BY fix) → [PASS]
[7]  Top event codes query → [PASS]
[8]  Events filter by severity + IP → [PASS]
[9]  Audit filter by module + action → [PASS]
[10] Blocks active/unblock query → [PASS]
[11] SecurityController role guard → [PASS]
[12] Distinct categories + modules → [PASS]
```

**File:**
- `app/Controllers/SecurityController.php` — overview, events, audit, blocks, unblock
- `app/Views/security/index.php` — dashboard 4 tab dengan DataTable + Chart.js
- `routes/web.php` — 5 route baru
- `app/Views/partials/dashboard/sidebar.php` — link Security Monitor

---

# 🧪 6. Bukti Sistem Berjalan (Verification)

**Test file:** `tests/security_e2e_verify.php` → **50/50 passed**

## ✅ Test Case 1 — SQL Injection

**Langkah:**
- akses URL dengan `' OR 1=1 UNION SELECT * FROM users--`

**Hasil:**
- `[PASS]` ThreatInspector detects SQLi, risk_score >= 70
- `[PASS]` security_event: `GUEST_SQLI_PATTERN`, severity: high
- `[PASS]` request_activity.is_suspicious = 1

---

## ✅ Test Case 2 — Brute Force Login

**Langkah:**
- 5x login gagal dari IP yang sama

**Hasil:**
- `[PASS]` After 5 failures: exceeded = true, blocked = true
- `[PASS]` IP auto-blocked in security_blocks (reason: GUEST_BRUTEFORCE_IP, severity: high)
- `[PASS]` Subsequent request from blocked IP is rejected

---

## ✅ Test Case 3 — Forbidden Access

**Langkah:**
- user role kasir mencoba akses route admin

**Hasil:**
- `[PASS]` event: `USER_FORBIDDEN_ROUTE_ACCESS`, severity: medium, action: blocked
- `[PASS]` AuditMiddleware logs 403 as USER_FORBIDDEN_ROUTE_ACCESS

---

## ✅ Test Case 4 — Parameter Tampering

**Langkah:**
- kirim harga Rp 1 untuk item yang harga DB-nya Rp 50.000

**Hasil:**
- `[PASS]` Price manipulation detected (input < 50% DB price)
- `[PASS]` event: `USER_PRICE_MANIPULATION`, severity: high, action: blocked
- `[PASS]` Payload berisi context, tidak ada raw password

---

## ✅ Test Case 5 — Upload Abuse

**Langkah:**
- upload `shell.php.jpg`

**Hasil:**
- `[PASS]` Double extension detected: inner=php, outer=jpg
- `[PASS]` event: `USER_DOUBLE_EXTENSION_UPLOAD`, severity: critical, action: blocked
- `[PASS]` FileManagerController has double extension + MIME mismatch detection

---

## ✅ Test Case 6 — Export Abuse

**Langkah:**
- 20x export request dalam 60 detik

**Hasil:**
- `[PASS]` After 20 exports: exceeded = true
- `[PASS]` event: `USER_EXPORT_ABUSE`, severity: medium, action: throttled
- `[PASS]` LaporanController has export abuse detection via RateLimitDetector

---

## ✅ Cross-cutting Checks

- `[PASS]` No raw password in any security_event payload
- `[PASS]` auth_activity identifiers are masked
- `[PASS]` Risk score present di semua 4 tabel
- `[PASS]` Semua 6 tabel aktif dan terisi
- `[PASS]` security_event_rules: 18 rules seeded

---

# 🧠 Catatan Penting

- ❌ Jangan simpan password / token mentah
- ❌ Jangan log full payload sensitif
- ✔ Gunakan fingerprint/hash
- ✔ Gunakan risk score, bukan rule kaku
- ✔ Pisahkan audit vs security event

---

# 🏁 Definition of Done

Sistem dianggap selesai jika:

- [✔] Semua tabel aktif dan terisi
- [✔] Event tercatat sesuai skenario test
- [✔] Risk score muncul
- [✔] Block/throttle berjalan
- [✔] Audit log tersimpan dengan benar
- [✔] Tidak ada sensitive data bocor di log

---

# 💬 Final Notes

Sistem ini bukan sekadar logging.

Ini:

- mata (detection)
- otak (scoring)
- tangan (response)

Kalau cuma logging → itu bukan security, itu diary.

---
