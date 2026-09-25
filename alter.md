# Database alter queries (do not run automatically)

Apply these manually after reviewing. Agents must **not** change the database directly.

## Payment-fail WhatsApp campaign (`payment_failed_retry_payment`)

Template body variables:

1. `{{1}}` — donor first/full name  
2. `{{2}}` — order reference (`provider_order_id`)  
3. `{{3}}` — amount (number only; template already has `₹`)  
4. `{{4}}` — Razorpay payment-link URL  

### Preview current values

```sql
SELECT id, slug, title, aisensy_payment_link_campaign, aisensy_account_id
FROM causes
ORDER BY id;
```

### Point all causes at the new AiSensy campaign

```sql
UPDATE causes
SET aisensy_payment_link_campaign = 'payment_failed_retry_payment',
    updated_at = NOW()
WHERE aisensy_payment_link_campaign IS NULL
   OR aisensy_payment_link_campaign IN (
        'Failed_payment_Link_send',
        'PaymentLink_Created_LIVE',
        'payment-link-campaign'
   )
   OR aisensy_payment_link_campaign <> 'payment_failed_retry_payment';
```

### Optional: only Old Age Home (if others already correct)

```sql
UPDATE causes
SET aisensy_payment_link_campaign = 'payment_failed_retry_payment',
    updated_at = NOW()
WHERE slug = 'old-age-home';
```

### Env reminder (not SQL)

Update `.env` (then `php artisan config:clear` if config is cached):

```env
AISENSY_PAYMENT_LINK_CAMPAIGN=payment_failed_retry_payment
```

Code uses cause `aisensy_payment_link_campaign` first, then falls back to this env value.

---

## Stale pending → failed (2026-09-04)

No database schema change required.

Behaviour (code + schedule only):

- Command: `php artisan donations:nudge-stale-pending`
- Schedule: every 5 minutes (`routes/console.php`)
- Pending checkouts older than **5 minutes** (and within the last **7 days**) are marked **failed**, then payment-link + **fail** WhatsApp (`payment_failed_retry_payment`) is queued
- Admin donation details also has **Send link instant** for pending orders (marks failed immediately and queues the same WhatsApp)

Ensure cron / scheduler is running:

```bash
* * * * * cd /home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org && php artisan schedule:run >> /dev/null 2>&1
```

Optional dry-run:

```bash
php artisan donations:nudge-stale-pending --dry-run
```

---

## English certificate template on causes (2026-09-22)

Per-cause English sanman patra artwork (Admin → Causes → WhatsApp tab). Gujarat donors keep `certificate_template`.

### Preview

```sql
SELECT id, slug, title, certificate_template, certificate_template_english
FROM causes
ORDER BY id;
```

### Schema

```sql
ALTER TABLE causes
    ADD COLUMN certificate_template_english VARCHAR(255) NULL
    AFTER certificate_template;
```

### Rollback

```sql
ALTER TABLE causes
    DROP COLUMN certificate_template_english;
```

---

## Certificate WhatsApp campaign (`certificate_of_donation_old_age_home_uty`) (2026-09-22)

AiSensy IMAGE template after a paid donation. Body variables:

1. `{{1}}` — donor name  
2. `{{2}}` — amount (number only; template already has `₹`)  
3. `{{3}}` — cause title  
4. `{{4}}` — donation date (`d-m-Y`)  
5. `{{5}}` — certificate / receipt number (e.g. `MSCT-RZP-123`)

Media: generated sanman-patra image URL.

### Preview current values

```sql
SELECT id, slug, title, aisensy_certificate_campaign, aisensy_account_id
FROM causes
ORDER BY id;
```

### Point causes at the new AiSensy campaign

```sql
UPDATE causes
SET aisensy_certificate_campaign = 'certificate_of_donation_old_age_home_uty',
    updated_at = NOW()
WHERE aisensy_certificate_campaign IS NULL
   OR aisensy_certificate_campaign IN (
        'certificate_of_donation_old_age_home',
        'certificate-campaign'
   )
   OR aisensy_certificate_campaign <> 'certificate_of_donation_old_age_home_uty';
```

### Optional: only Old Age Home

```sql
UPDATE causes
SET aisensy_certificate_campaign = 'certificate_of_donation_old_age_home_uty',
    updated_at = NOW()
WHERE slug = 'old-age-home';
```

### Env reminder (not SQL)

Update `.env` (then `php artisan config:clear` if config is cached):

```env
AISENSY_CERTIFICATE_CAMPAIGN=certificate_of_donation_old_age_home_uty
```

Code uses cause `aisensy_certificate_campaign` first, then falls back to this env / config default.

## 2026-09-22 — Certificate WhatsApp campaigns (`*_uty` → `*_new`)

AiSensy returns `Campaign does not exist` for retired `*_uty` names. Live IMAGE campaigns use `*_new` (0 body params).

### Preview

```sql
SELECT id, slug, title, aisensy_certificate_campaign
FROM causes
ORDER BY id;
```

### Update cause campaigns

```sql
UPDATE causes SET aisensy_certificate_campaign = 'certificate_of_donation_old_age_home_new', updated_at = NOW()
WHERE slug = 'old-age-home';

UPDATE causes SET aisensy_certificate_campaign = 'certificate_of_donation_tree_plantation_new', updated_at = NOW()
WHERE slug = 'tree-plantation';

UPDATE causes SET aisensy_certificate_campaign = 'certificate_of_donation_animal_hospital_new', updated_at = NOW()
WHERE slug = 'animal-hospital';

UPDATE causes SET aisensy_certificate_campaign = 'certificate_of_donation_dog_shelter_new', updated_at = NOW()
WHERE slug = 'dog-shelter';

UPDATE causes SET aisensy_certificate_campaign = 'certificate_of_donation_bull_shelter_new', updated_at = NOW()
WHERE slug = 'bull-shelter';

-- daily-needs: no approved *_new certificate campaign in aisensy_wa_templates yet — create in AiSensy first, then:
-- UPDATE causes SET aisensy_certificate_campaign = 'certificate_of_donation_daily_need_new', updated_at = NOW() WHERE slug = 'daily-needs';
```

### Env

```env
AISENSY_CERTIFICATE_CAMPAIGN=certificate_of_donation_old_age_home_new
```

Code also auto-maps `*_uty` → `*_new` at send time so WhatsApp works before this SQL is applied.

### Applied 2026-09-22 (live)

Also set `aisensy_send_certificate = 1` for the five causes above. `daily-needs` left off until AiSensy has `certificate_of_donation_daily_need_new`.
WhatsApp certificate media prefers JPEG (`DONATION_CERTIFICATE_WHATSAPP_PREFER_PNG=false`).

## 2026-09-23 — Birthday messages admin fixes

Live gaps found on `/admin/birthday-messages`:
- Day-0 marketing step had empty `campaign_name` (would never send).
- Warm wish used typo campaign `birtday_message_final` (not in AiSensy templates).

Applied:

```sql
UPDATE birthday_message_steps
SET campaign_name = 'birthday_marketing_on_birthday', updated_at = NOW()
WHERE days_before = 0 AND kind = 'marketing';

UPDATE birthday_message_steps
SET campaign_name = 'happy_birthday_current_day_warm_msg', updated_at = NOW()
WHERE days_before = 0 AND kind = 'warm_wish';

UPDATE settings SET value = 'happy_birthday_current_day_warm_msg', updated_at = NOW()
WHERE `key` = 'aisensy_birthday_campaign';
```


## 2026-09-23 — Birthday campaigns + stop marketing after donate

```sql
UPDATE birthday_message_steps SET campaign_name = 'happy_birthday_current_day_ut_sid_new_v9', updated_at = NOW()
WHERE kind = 'marketing' AND days_before IN (7, 3);

UPDATE birthday_message_steps SET campaign_name = 'birthday_marketing_on_birthday', updated_at = NOW()
WHERE kind = 'marketing' AND days_before = 0;

UPDATE birthday_message_steps SET campaign_name = 'happy_birthday_current_day_warm_msg', updated_at = NOW()
WHERE kind = 'warm_wish' AND days_before = 0;
```

Logic: after first marketing send, if donor pays, skip remaining day-left marketing; on birthday send warm wish only.

## 2026-09-23 — Birthday day-left Live campaign mapping

WA template `happy_birthday_current_day_ut_sid_new_v9` is Live in AiSensy as API campaign `happy_birthday_reminder_plant_tree`.

```sql
UPDATE aisensy_wa_templates
SET live_campaign_name = 'happy_birthday_reminder_plant_tree', updated_at = NOW()
WHERE name = 'happy_birthday_current_day_ut_sid_new_v9';
```

Admin steps keep storing the WA template name; send-time resolves to Live campaign name.

## 2026-09-24 - Razorpay QR codes admin registry

Local cache of Razorpay UPI QR codes for Finance > QR Codes (create / close / sync).

### Preview

```sql
SHOW TABLES LIKE 'razorpay_qr_codes';
```

### Create table

```sql
CREATE TABLE razorpay_qr_codes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    qr_uuid CHAR(36) NOT NULL,
    razorpay_qr_code_id VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    type VARCHAR(32) NOT NULL DEFAULT 'upi_qr',
    `usage` VARCHAR(32) NOT NULL DEFAULT 'multiple_use',
    fixed_amount TINYINT(1) NOT NULL DEFAULT 0,
    payment_amount_paise BIGINT UNSIGNED NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    image_url VARCHAR(500) NULL,
    payments_count_received INT UNSIGNED NOT NULL DEFAULT 0,
    payments_amount_received_paise BIGINT UNSIGNED NOT NULL DEFAULT 0,
    close_reason VARCHAR(255) NULL,
    closed_at TIMESTAMP NULL,
    razorpay_created_at TIMESTAMP NULL,
    meta JSON NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY razorpay_qr_codes_qr_uuid_unique (qr_uuid),
    UNIQUE KEY razorpay_qr_codes_razorpay_qr_code_id_unique (razorpay_qr_code_id),
    KEY razorpay_qr_codes_status_index (status),
    CONSTRAINT razorpay_qr_codes_created_by_foreign
        FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Rollback

```sql
DROP TABLE IF EXISTS razorpay_qr_codes;
```

Also run `php artisan db:seed --class=AdminRolePermissionSeeder` (or grant new QR permissions) after deploy so roles get view/create/close/sync qr codes.

## 2026-09-24 - QR codes cause mapping (Phase 2)

Map each Razorpay QR to a cause (optional package) so auto-created QR donations get a line item.

### Preview

```sql
SHOW COLUMNS FROM razorpay_qr_codes LIKE 'cause%';
```

### Alter

```sql
ALTER TABLE razorpay_qr_codes
    ADD COLUMN cause_id BIGINT UNSIGNED NULL AFTER created_by,
    ADD COLUMN cause_package_id BIGINT UNSIGNED NULL AFTER cause_id,
    ADD CONSTRAINT razorpay_qr_codes_cause_id_foreign
        FOREIGN KEY (cause_id) REFERENCES causes (id) ON DELETE SET NULL,
    ADD CONSTRAINT razorpay_qr_codes_cause_package_id_foreign
        FOREIGN KEY (cause_package_id) REFERENCES cause_packages (id) ON DELETE SET NULL;
```

### Rollback

```sql
ALTER TABLE razorpay_qr_codes
    DROP FOREIGN KEY razorpay_qr_codes_cause_package_id_foreign,
    DROP FOREIGN KEY razorpay_qr_codes_cause_id_foreign,
    DROP COLUMN cause_package_id,
    DROP COLUMN cause_id;
```

## 2026-09-25 - Admin login FingerprintJS + login logs

Device lock for admin login: first successful login binds FingerprintJS visitor ID on `users`. Later logins from a different fingerprint are **blocked**. Every login attempt is stored in `user_login_logs` with IP + geo location.

Migration file (do **not** auto-run): `database/migrations/2026_09_25_103500_add_device_fingerprint_and_user_login_logs.php`

### Preview

```sql
SHOW COLUMNS FROM users LIKE 'device_fingerprint%';
SHOW TABLES LIKE 'user_login_logs';
```

### Alter

```sql
ALTER TABLE users
    ADD COLUMN device_fingerprint VARCHAR(128) NULL AFTER remember_token,
    ADD COLUMN device_fingerprint_bound_at TIMESTAMP NULL AFTER device_fingerprint;

CREATE TABLE user_login_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    email VARCHAR(255) NULL,
    fingerprint VARCHAR(128) NULL,
    fingerprint_matched TINYINT(1) NULL,
    status VARCHAR(40) NOT NULL,
    ip_address VARCHAR(45) NULL,
    country VARCHAR(100) NULL,
    region VARCHAR(100) NULL,
    city VARCHAR(100) NULL,
    location VARCHAR(255) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT user_login_logs_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    INDEX user_login_logs_email_index (email),
    INDEX user_login_logs_ip_address_index (ip_address),
    INDEX user_login_logs_user_id_created_at_index (user_id, created_at),
    INDEX user_login_logs_status_index (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Reset a user's device lock (manual unlock)

```sql
-- Preview
SELECT id, email, device_fingerprint, device_fingerprint_bound_at
FROM users
WHERE email = 'admin@example.com';

-- Reset so the next successful login rebinds a new fingerprint
UPDATE users
SET device_fingerprint = NULL,
    device_fingerprint_bound_at = NULL,
    updated_at = NOW()
WHERE email = 'admin@example.com';
```

### Rollback

```sql
DROP TABLE IF EXISTS user_login_logs;

ALTER TABLE users
    DROP COLUMN device_fingerprint_bound_at,
    DROP COLUMN device_fingerprint;
```

## 2026-09-25 - Auto logout 30 min + logout all devices

Idle sessions expire after **30 minutes** (`SESSION_LIFETIME=30`). “Sign out everywhere” bumps `users.session_version` so every other open session is rejected on the next request (works with `SESSION_DRIVER=file`).

Also set in `.env` (then `php artisan config:clear`):

```env
SESSION_LIFETIME=30
```

Migration file (do **not** auto-run): `database/migrations/2026_09_25_110000_add_session_version_to_users_table.php`

### Preview

```sql
SHOW COLUMNS FROM users LIKE 'session_version';
```

### Alter

```sql
ALTER TABLE users
    ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 0;
```

### Rollback

```sql
ALTER TABLE users
    DROP COLUMN session_version;
```

## 2026-09-25 - Multiple fingerprints per super_admin

Super admins may have **multiple** trusted FingerprintJS devices in `user_device_fingerprints`.  
Login **never auto-adds** fingerprints — add rows manually in phpMyAdmin (or SQL below). Unknown devices are blocked and the fingerprint is shown in the error.

Migration file (do **not** auto-run): `database/migrations/2026_09_25_161900_create_user_device_fingerprints_table.php`

### Preview

```sql
SHOW TABLES LIKE 'user_device_fingerprints';
SELECT id, email, device_fingerprint FROM users WHERE device_fingerprint IS NOT NULL;
```

### Alter

```sql
CREATE TABLE user_device_fingerprints (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    fingerprint VARCHAR(128) NOT NULL,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT user_device_fingerprints_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE KEY user_device_fingerprints_user_id_fingerprint_unique (user_id, fingerprint),
    INDEX user_device_fingerprints_fingerprint_index (fingerprint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional one-time backfill from legacy single-column fingerprints
INSERT IGNORE INTO user_device_fingerprints (user_id, fingerprint, last_used_at, created_at, updated_at)
SELECT id, device_fingerprint, device_fingerprint_bound_at, COALESCE(device_fingerprint_bound_at, NOW()), NOW()
FROM users
WHERE device_fingerprint IS NOT NULL
  AND device_fingerprint <> '';
```

### Add a trusted fingerprint (manual)

Copy the fingerprint from the login error / browser console, then:

```sql
INSERT INTO user_device_fingerprints (user_id, fingerprint, last_used_at, created_at, updated_at)
SELECT u.id, 'PASTE_FINGERPRINT_HERE', NULL, NOW(), NOW()
FROM users u
WHERE u.email = 'sid@sadbhavnadham.org';
```

### List / remove fingerprints for a user

```sql
SELECT udf.*
FROM user_device_fingerprints udf
INNER JOIN users u ON u.id = udf.user_id
WHERE u.email = 'sid@sadbhavnadham.org';

-- Remove one device
DELETE udf
FROM user_device_fingerprints udf
INNER JOIN users u ON u.id = udf.user_id
WHERE u.email = 'sid@sadbhavnadham.org'
  AND udf.fingerprint = 'PASTE_FINGERPRINT_HERE';

-- Clear all trusted devices
DELETE udf
FROM user_device_fingerprints udf
INNER JOIN users u ON u.id = udf.user_id
WHERE u.email = 'sid@sadbhavnadham.org';
```

### Rollback

```sql
DROP TABLE IF EXISTS user_device_fingerprints;
```

## 2026-09-25 - Grant all permissions to sid@sadbhavnadham.org

User `sid@sadbhavnadham.org` (id `28`) currently has **no roles**, which causes admin `403 USER DOES NOT HAVE THE RIGHT ROLES.` Assign `super_admin` (has all 75 permissions).

### Preview

```sql
SELECT u.id, u.email, r.name AS role_name
FROM users u
LEFT JOIN model_has_roles mhr
    ON mhr.model_id = u.id
   AND mhr.model_type = 'App\\Models\\User'
LEFT JOIN roles r ON r.id = mhr.role_id
WHERE u.email = 'sid@sadbhavnadham.org';

SELECT id, name FROM roles WHERE name = 'super_admin';
```

### Alter (assign super_admin)

```sql
INSERT INTO model_has_roles (role_id, model_type, model_id)
SELECT r.id, 'App\\Models\\User', u.id
FROM users u
CROSS JOIN roles r
WHERE u.email = 'sid@sadbhavnadham.org'
  AND r.name = 'super_admin'
  AND NOT EXISTS (
      SELECT 1
      FROM model_has_roles mhr
      WHERE mhr.role_id = r.id
        AND mhr.model_type = 'App\\Models\\User'
        AND mhr.model_id = u.id
  );
```

After running, clear permission cache:

```bash
php artisan permission:cache-reset
```

### Rollback

```sql
DELETE mhr
FROM model_has_roles mhr
INNER JOIN users u ON u.id = mhr.model_id
INNER JOIN roles r ON r.id = mhr.role_id
WHERE u.email = 'sid@sadbhavnadham.org'
  AND r.name = 'super_admin'
  AND mhr.model_type = 'App\\Models\\User';
```

## 2026-09-25 - DB-IP local geolocation columns

Free local MMDB geo (City Lite + ASN Lite). Migration file (do **not** auto-run): `database/migrations/2026_09_25_180000_add_geoip_columns_to_tracking_and_analytics.php`

### Preview

```sql
SHOW COLUMNS FROM link_tracking_visits LIKE 'ip_%';
SHOW COLUMNS FROM analytics_events LIKE 'postal_code';
SHOW COLUMNS FROM donation_orders LIKE 'ip_postal%';
SHOW COLUMNS FROM user_login_logs LIKE 'isp';
```

### Alter

```sql
ALTER TABLE link_tracking_visits
    ADD COLUMN ip_country_code VARCHAR(2) NULL AFTER ip_address,
    ADD COLUMN ip_country_name VARCHAR(100) NULL AFTER ip_country_code,
    ADD COLUMN ip_region_name VARCHAR(100) NULL AFTER ip_country_name,
    ADD COLUMN ip_city VARCHAR(100) NULL AFTER ip_region_name,
    ADD COLUMN ip_postal_code VARCHAR(32) NULL AFTER ip_city,
    ADD COLUMN ip_lat DECIMAL(10,7) NULL AFTER ip_postal_code,
    ADD COLUMN ip_lng DECIMAL(10,7) NULL AFTER ip_lat,
    ADD COLUMN ip_timezone VARCHAR(64) NULL AFTER ip_lng,
    ADD COLUMN ip_asn INT UNSIGNED NULL AFTER ip_timezone,
    ADD COLUMN ip_isp VARCHAR(255) NULL AFTER ip_asn,
    ADD INDEX link_tracking_visits_ip_country_code_index (ip_country_code),
    ADD INDEX link_tracking_visits_ip_city_index (ip_city),
    ADD INDEX link_tracking_visits_ip_isp_index (ip_isp);

ALTER TABLE analytics_events
    ADD COLUMN postal_code VARCHAR(32) NULL AFTER city,
    ADD COLUMN latitude DECIMAL(10,7) NULL AFTER postal_code,
    ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude,
    ADD COLUMN timezone VARCHAR(64) NULL AFTER longitude,
    ADD COLUMN asn INT UNSIGNED NULL AFTER timezone,
    ADD COLUMN isp VARCHAR(255) NULL AFTER asn,
    ADD INDEX analytics_events_isp_index (isp);

ALTER TABLE donation_orders
    ADD COLUMN ip_postal_code VARCHAR(32) NULL AFTER ip_city,
    ADD COLUMN ip_timezone VARCHAR(64) NULL AFTER ip_lng,
    ADD COLUMN ip_asn INT UNSIGNED NULL AFTER ip_timezone,
    ADD COLUMN ip_isp VARCHAR(255) NULL AFTER ip_asn;

ALTER TABLE user_login_logs
    ADD COLUMN postal_code VARCHAR(32) NULL AFTER location,
    ADD COLUMN latitude DECIMAL(10,7) NULL AFTER postal_code,
    ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude,
    ADD COLUMN timezone VARCHAR(64) NULL AFTER longitude,
    ADD COLUMN asn INT UNSIGNED NULL AFTER timezone,
    ADD COLUMN isp VARCHAR(255) NULL AFTER asn;
```

### Ops

```bash
php artisan geoip:update
php artisan geoip:backfill-visits
php artisan config:clear
```

### Rollback

```sql
ALTER TABLE link_tracking_visits
    DROP INDEX link_tracking_visits_ip_country_code_index,
    DROP INDEX link_tracking_visits_ip_city_index,
    DROP INDEX link_tracking_visits_ip_isp_index,
    DROP COLUMN ip_country_code, DROP COLUMN ip_country_name, DROP COLUMN ip_region_name,
    DROP COLUMN ip_city, DROP COLUMN ip_postal_code, DROP COLUMN ip_lat, DROP COLUMN ip_lng,
    DROP COLUMN ip_timezone, DROP COLUMN ip_asn, DROP COLUMN ip_isp;

ALTER TABLE analytics_events
    DROP INDEX analytics_events_isp_index,
    DROP COLUMN postal_code, DROP COLUMN latitude, DROP COLUMN longitude,
    DROP COLUMN timezone, DROP COLUMN asn, DROP COLUMN isp;

ALTER TABLE donation_orders
    DROP COLUMN ip_postal_code, DROP COLUMN ip_timezone, DROP COLUMN ip_asn, DROP COLUMN ip_isp;

ALTER TABLE user_login_logs
    DROP COLUMN postal_code, DROP COLUMN latitude, DROP COLUMN longitude,
    DROP COLUMN timezone, DROP COLUMN asn, DROP COLUMN isp;
```
