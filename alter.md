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
