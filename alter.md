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
- Pending checkouts older than **10 minutes** (and within the last **7 days**) are marked **failed**, then payment-link + **fail** WhatsApp (`payment_failed_retry_payment`) is queued

Ensure cron / scheduler is running:

```bash
* * * * * cd /home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org && php artisan schedule:run >> /dev/null 2>&1
```

Optional dry-run:

```bash
php artisan donations:nudge-stale-pending --dry-run
```
