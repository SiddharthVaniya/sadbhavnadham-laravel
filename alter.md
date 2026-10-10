# Database alter queries (do not run automatically)

Apply these manually after reviewing. Agents must **not** change the database directly.

**Agent rule (always):** Never run `php artisan migrate`, seeders, or live `ALTER` / `UPDATE` / `INSERT` / `DELETE`. For every DB change, append preview + apply + rollback SQL here and wait for a human to run it (phpMyAdmin / MySQL client).

## Payment-fail WhatsApp campaign (`failed_payment_qr_of_paymet_link`)

Template body variables:

1. `{{1}}` — donor first/full name
2. `{{2}}` — amount (number with ₹)
3. `{{3}}` — Razorpay payment-link URL

### Preview current values

```sql
SELECT id, slug, title, aisensy_payment_link_campaign, aisensy_account_id
FROM causes
ORDER BY id;
```

### Point all causes at the new AiSensy campaign

```sql
UPDATE causes
SET aisensy_payment_link_campaign = 'failed_payment_qr_of_paymet_link',
    updated_at = NOW()
WHERE aisensy_payment_link_campaign IS NULL
   OR aisensy_payment_link_campaign IN (
        'Failed_payment_Link_send',
        'PaymentLink_Created_LIVE',
        'payment-link-campaign',
        'payment_failed_retry_payment'
   )
   OR aisensy_payment_link_campaign <> 'failed_payment_qr_of_paymet_link';
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

## 2026-09-28 - Danamojo tracking table

Stores every Danamojo callback and API row (pending, failed, verified) and links verified rows to `donation_orders`. Migration file (do **not** auto-run): `database/migrations/2026_09_28_154106_create_danamojo_donations_table.php`

### Preview

```sql
SHOW TABLES LIKE 'danamojo_donations';
```

### Alter

```sql
CREATE TABLE danamojo_donations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    donation_info_id BIGINT UNSIGNED NOT NULL,
    donation_order_id BIGINT UNSIGNED NULL,
    payment_status VARCHAR(40) NULL,
    dm_status VARCHAR(40) NULL,
    sync_state VARCHAR(32) NOT NULL DEFAULT 'notified',
    donor_name VARCHAR(255) NULL,
    donor_email VARCHAR(255) NULL,
    donor_phone VARCHAR(40) NULL,
    nationality VARCHAR(80) NULL,
    country VARCHAR(80) NULL,
    currency VARCHAR(8) NULL,
    amount_local DECIMAL(12,2) NULL,
    amount_inr DECIMAL(12,2) NULL,
    payment_option VARCHAR(40) NULL,
    product_name VARCHAR(255) NULL,
    receipt_number VARCHAR(80) NULL,
    receipt_link VARCHAR(512) NULL,
    referer_url TEXT NULL,
    landing_url TEXT NULL,
    sid VARCHAR(40) NULL,
    utm_source VARCHAR(120) NULL,
    utm_medium VARCHAR(120) NULL,
    utm_campaign VARCHAR(120) NULL,
    utm_content VARCHAR(120) NULL,
    utm_term VARCHAR(120) NULL,
    utm_id VARCHAR(40) NULL,
    aid VARCHAR(40) NULL,
    partner_code VARCHAR(40) NULL,
    partner_user_id BIGINT UNSIGNED NULL,
    device VARCHAR(32) NULL,
    recurring TINYINT(1) NOT NULL DEFAULT 0,
    fcra TINYINT(1) NULL,
    international TINYINT(1) NULL,
    donated_at TIMESTAMP NULL,
    notified_at TIMESTAMP NULL,
    last_synced_at TIMESTAMP NULL,
    imported_at TIMESTAMP NULL,
    next_retry_at TIMESTAMP NULL,
    retry_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(255) NULL,
    raw_payload JSON NULL,
    notify_payload JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY danamojo_donations_donation_info_id_unique (donation_info_id),
    KEY danamojo_donations_donation_order_id_foreign (donation_order_id),
    KEY danamojo_donations_sync_state_index (sync_state),
    KEY danamojo_donations_payment_status_index (payment_status),
    KEY danamojo_donations_next_retry_at_index (next_retry_at),
    KEY danamojo_donations_partner_user_id_index (partner_user_id),
    CONSTRAINT danamojo_donations_donation_order_id_foreign
        FOREIGN KEY (donation_order_id) REFERENCES donation_orders (id) ON DELETE SET NULL
);
```

### Rollback

```sql
DROP TABLE IF EXISTS danamojo_donations;
```

## 2026-09-29 - Razorpay refund columns

Full refunds from donation details. Migration file (do **not** auto-run): `database/migrations/2026_09_29_153640_add_refund_columns_to_donation_orders_table.php`

### Preview

```sql
SHOW COLUMNS FROM donation_orders LIKE 'refund%';
SHOW COLUMNS FROM donation_orders LIKE 'razorpay_refund_id';
```

### Alter

```sql
ALTER TABLE donation_orders
    ADD COLUMN refunded_at TIMESTAMP NULL AFTER failed_at,
    ADD COLUMN razorpay_refund_id VARCHAR(64) NULL AFTER refunded_at,
    ADD COLUMN refund_amount DECIMAL(12,2) NULL AFTER razorpay_refund_id;
```

### Rollback

```sql
ALTER TABLE donation_orders
    DROP COLUMN refunded_at,
    DROP COLUMN razorpay_refund_id,
    DROP COLUMN refund_amount;
```

## 2026-09-29 - Move three Divyjeet clicks off Urvi

These visits stored Divyajeet's ad with Urvi's old `sid`. Requested correction. `unique_visitors` stays as-is.

### Preview

```sql
SELECT id, sid, utm_campaign, converted
FROM link_tracking_visits
WHERE id IN (192497, 192512, 192515);

SELECT sid, total_clicks, unique_visitors
FROM link_tracking_summary
WHERE sid IN ('cpufaju', 'jpoxrr');
```

### Update

```sql
UPDATE link_tracking_visits
SET sid = 'jpoxrr'
WHERE id IN (192497, 192512, 192515)
  AND sid = 'cpufaju';

UPDATE link_tracking_summary
SET total_clicks = total_clicks - 3
WHERE sid = 'cpufaju'
  AND total_clicks >= 3;

UPDATE link_tracking_summary
SET total_clicks = total_clicks + 3
WHERE sid = 'jpoxrr';
```

## 2026-09-29 - Marketer daily spending limits

Today’s limit and spend for each marketer. Past days stay in this table as spending history. Migration file (do **not** auto-run): `database/migrations/2026_09_29_162008_create_marketer_daily_budgets_table.php`

### Preview

```sql
SHOW TABLES LIKE 'marketer_daily_budgets';
```

### Create

```sql
CREATE TABLE marketer_daily_budgets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    spend_date DATE NOT NULL,
    limit_amount DECIMAL(14,2) NULL,
    spend_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY marketer_daily_budgets_user_date_unique (user_id, spend_date),
    KEY marketer_daily_budgets_spend_date_index (spend_date),
    CONSTRAINT marketer_daily_budgets_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);
```

### Rollback

```sql
DROP TABLE IF EXISTS marketer_daily_budgets;
```

## 2026-09-29 - Monthly spending limit

Separate this month’s target from the monthly spending limit. Existing `target_amount` values stay as targets. The new limit starts empty until it is set on the marketers month page. Migration file (do **not** auto-run): `database/migrations/2026_09_29_172444_add_limit_amount_to_marketer_monthly_budgets_table.php`

### Preview

```sql
SHOW COLUMNS FROM marketer_monthly_budgets LIKE 'limit_amount';
```

### Add

```sql
ALTER TABLE marketer_monthly_budgets
    ADD COLUMN limit_amount INT UNSIGNED NULL AFTER target_amount;
```

### Rollback

```sql
ALTER TABLE marketer_monthly_budgets DROP COLUMN limit_amount;
```

## 2026-10-07 - Marketer attribution on QR codes

Assign a marketer (user with a `referral_code`) to a Razorpay QR so QR donations are credited to that marketer (`donation_orders.partner_user_id` + `partner_code`), visible in Donations and the marketer portal. Migration file (do **not** auto-run): `database/migrations/2026_10_07_120000_add_partner_to_razorpay_qr_codes_table.php`

### Preview

```sql
SHOW COLUMNS FROM razorpay_qr_codes LIKE 'partner%';
```

### Alter

```sql
ALTER TABLE razorpay_qr_codes
    ADD COLUMN partner_user_id BIGINT UNSIGNED NULL AFTER cause_package_id,
    ADD COLUMN partner_code VARCHAR(40) NULL AFTER partner_user_id,
    ADD CONSTRAINT razorpay_qr_codes_partner_user_id_foreign
        FOREIGN KEY (partner_user_id) REFERENCES users (id) ON DELETE SET NULL;
```

### Optional: backfill partner_code from the linked user

```sql
UPDATE razorpay_qr_codes qr
INNER JOIN users u ON u.id = qr.partner_user_id
SET qr.partner_code = u.referral_code
WHERE qr.partner_user_id IS NOT NULL
  AND (qr.partner_code IS NULL OR qr.partner_code = '');
```

### Rollback

```sql
ALTER TABLE razorpay_qr_codes
    DROP FOREIGN KEY razorpay_qr_codes_partner_user_id_foreign,
    DROP COLUMN partner_code,
    DROP COLUMN partner_user_id;
```

## 2026-10-09 - Marketer view-only access to Packages

Lets `digital_marketer` open `/admin/packages` (view + copy package links only). No schema change. Code already allows this route for marketers with `view packages`.

### Preview

```sql
SELECT r.id AS role_id, r.name AS role_name, p.id AS permission_id, p.name AS permission_name
FROM roles r
LEFT JOIN role_has_permissions rhp ON rhp.role_id = r.id
LEFT JOIN permissions p ON p.id = rhp.permission_id
    AND p.name IN ('view packages', 'copy package links')
WHERE r.name = 'digital_marketer';

SELECT id, name, guard_name
FROM permissions
WHERE name IN ('view packages', 'copy package links');
```

### Apply (idempotent)

```sql
INSERT INTO permissions (name, guard_name, created_at, updated_at)
SELECT 'view packages', 'web', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE name = 'view packages' AND guard_name = 'web'
);

INSERT INTO permissions (name, guard_name, created_at, updated_at)
SELECT 'copy package links', 'web', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE name = 'copy package links' AND guard_name = 'web'
);

INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
CROSS JOIN roles r
WHERE r.name = 'digital_marketer'
  AND p.name IN ('view packages', 'copy package links')
  AND p.guard_name = 'web'
  AND NOT EXISTS (
      SELECT 1
      FROM role_has_permissions rhp
      WHERE rhp.permission_id = p.id
        AND rhp.role_id = r.id
  );
```

### After apply

Clear Spatie permission cache on the app server (code deploy / artisan, not SQL):

```bash
php artisan permission:cache-reset
# or
php artisan cache:clear
```

### Rollback

```sql
DELETE rhp
FROM role_has_permissions rhp
INNER JOIN roles r ON r.id = rhp.role_id
INNER JOIN permissions p ON p.id = rhp.permission_id
WHERE r.name = 'digital_marketer'
  AND p.name IN ('view packages', 'copy package links');
```

## 2026-10-09 - Meta ad accounts + daily Insights spend

Stores multiple Meta Marketing API credentials (encrypted at rest by the app) and per-ad daily spend snapshots used to overwrite `marketer_daily_budgets.spend_amount`. Do not run Laravel migrate on live — apply this SQL.

### Preview

```sql
SHOW TABLES LIKE 'meta_ad_accounts';
SHOW TABLES LIKE 'meta_ad_spend_daily';
SHOW COLUMNS FROM meta_ad_accounts;
SHOW COLUMNS FROM meta_ad_spend_daily;
```

### Apply (idempotent)

```sql
CREATE TABLE IF NOT EXISTS meta_ad_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(255) NOT NULL,
    app_id VARCHAR(255) NOT NULL,
    app_secret TEXT NOT NULL,
    access_token TEXT NOT NULL,
    ad_account_id VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_synced_at TIMESTAMP NULL DEFAULT NULL,
    last_sync_status VARCHAR(32) NULL DEFAULT NULL,
    last_sync_error TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX meta_ad_accounts_is_active_ad_account_id_index (is_active, ad_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meta_ad_spend_daily (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    meta_ad_account_id BIGINT UNSIGNED NOT NULL,
    spend_date DATE NOT NULL,
    campaign_id VARCHAR(255) NULL DEFAULT NULL,
    campaign_name VARCHAR(255) NULL DEFAULT NULL,
    adset_id VARCHAR(255) NULL DEFAULT NULL,
    adset_name VARCHAR(255) NULL DEFAULT NULL,
    ad_id VARCHAR(255) NOT NULL,
    ad_name VARCHAR(255) NULL DEFAULT NULL,
    spend_amount DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
    clicks INT UNSIGNED NOT NULL DEFAULT 0,
    reach BIGINT UNSIGNED NOT NULL DEFAULT 0,
    inline_link_clicks INT UNSIGNED NOT NULL DEFAULT 0,
    currency VARCHAR(16) NULL DEFAULT NULL,
    user_id BIGINT UNSIGNED NULL DEFAULT NULL,
    matched_via VARCHAR(32) NOT NULL DEFAULT 'unmatched',
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY meta_ad_spend_daily_acct_date_ad_unique (meta_ad_account_id, spend_date, ad_id),
    INDEX meta_ad_spend_daily_spend_date_user_id_index (spend_date, user_id),
    INDEX meta_ad_spend_daily_campaign_name_index (campaign_name),
    INDEX meta_ad_spend_daily_ad_name_index (ad_name),
    CONSTRAINT meta_ad_spend_daily_meta_ad_account_id_foreign
        FOREIGN KEY (meta_ad_account_id) REFERENCES meta_ad_accounts (id) ON DELETE CASCADE,
    CONSTRAINT meta_ad_spend_daily_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Existing DB (add Insights columns after deploy):**

```sql
ALTER TABLE meta_ad_spend_daily
    ADD COLUMN impressions BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER spend_amount,
    ADD COLUMN clicks INT UNSIGNED NOT NULL DEFAULT 0 AFTER impressions,
    ADD COLUMN reach BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER clicks,
    ADD COLUMN inline_link_clicks INT UNSIGNED NOT NULL DEFAULT 0 AFTER reach;
```

Then run **Sync from live Meta** to backfill impressions/clicks/reach on historical rows.

**Meta ad name aliases (marketer spelling in Ads Manager, e.g. Ashwini vs Ashvini):**

```sql
ALTER TABLE users
    ADD COLUMN meta_ad_aliases VARCHAR(255) NULL DEFAULT NULL AFTER referral_code;
```

Example: `UPDATE users SET meta_ad_aliases = 'Ashwini' WHERE id = 12;` then run Meta sync + re-attribute (sync does this automatically).

## 2026-10-10 - Meta ad delivery status (Active / Not active filter)

**Preview:**

```sql
SHOW COLUMNS FROM meta_ad_spend_daily LIKE 'ad_effective_status';
```

**Apply:**

```sql
ALTER TABLE meta_ad_spend_daily
    ADD COLUMN ad_effective_status VARCHAR(32) NULL DEFAULT NULL AFTER ad_name,
    ADD INDEX meta_ad_spend_daily_ad_effective_status_index (ad_effective_status);
```

Then run **Sync from live Meta** for your date range so `ad_effective_status` is filled from Meta Ads API.

**Rollback:**

```sql
ALTER TABLE meta_ad_spend_daily
    DROP INDEX meta_ad_spend_daily_ad_effective_status_index,
    DROP COLUMN ad_effective_status;
```

### Rollback

```sql
DROP TABLE IF EXISTS meta_ad_spend_daily;
DROP TABLE IF EXISTS meta_ad_accounts;
```
