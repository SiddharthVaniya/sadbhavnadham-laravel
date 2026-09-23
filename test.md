# Birthday WhatsApp smoke tests

**Always send QA messages to:**

| Field | Value |
|---|---|
| Name | Tony |
| Phone | `+919426025598` (`9426025598`) |

Do **not** use random donor numbers for smoke tests.

---

## One command (all 3 messages)

```bash
cd /home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org
php artisan birthday:test-whatsapp
```

Defaults: `--phone=9426025598` `--name=Tony`

### Single campaign

```bash
php artisan birthday:test-whatsapp --only=day-left
php artisan birthday:test-whatsapp --only=birthday-marketing
php artisan birthday:test-whatsapp --only=warm
```

### Other handset (rare)

```bash
php artisan birthday:test-whatsapp --phone=98XXXXXXXX --name=Someone
```

---

## Expected campaigns

| Kind | Admin / WA template name | Live API campaign (auto-mapped) | Params | Media |
|---|---|---|---|---|
| Day-left (7 / 3) | `happy_birthday_current_day_ut_sid_new_v9` | `happy_birthday_reminder_plant_tree` | name + days | **Required** IMAGE URL |
| Birthday marketing | `birthday_marketing_on_birthday` | same | name | **Required** IMAGE URL |
| Warm wish (donated after first reminder) | `happy_birthday_current_day_warm_msg` | same | none | **Required** IMAGE URL |

Donate-after-first-reminder: remaining day-left marketing is skipped; on birthday only warm wish is sent.

---

## After send — verify

1. WhatsApp on Tony’s phone (`+919426025598`).
2. Log:

```bash
rg "api_campaign (sent|failed)" storage/logs/laravel.log | tail -20
```

Success looks like:

```text
campaign_name":"happy_birthday_reminder_plant_tree" ... "success":"true","submitted_message_id":"..."
campaign_name":"birthday_marketing_on_birthday" ...
campaign_name":"happy_birthday_current_day_warm_msg" ...
```

### Common failures

| AiSensy message | Cause | Fix |
|---|---|---|
| `Campaign does not exist` | Used WA template name that is not the Live campaign | Keep template name in admin; code maps via `aisensy_wa_templates.live_campaign_name` |
| `Media URL Missing` | IMAGE campaign without `media.url` | Upload step header image on `/admin/birthday-messages` (prefer ≤1200px JPEG) |
| HTTP 200 but no WhatsApp | Meta delivery / quality / blocked / spam | Check AiSensy dashboard → Campaigns → that campaign → recent sends / failed; confirm `9426025598` is on WhatsApp and has not blocked the business number |

### Control message (same phone)

If birthday tests still do not appear, send a known certificate to Tony’s last donation:

```bash
php artisan donations:send-whatsapp 13795 --certificate-only --force
```

If the certificate also does not arrive, the issue is AiSensy/Meta delivery for this number (not Laravel campaign selection).

---

## Admin panel

https://admin.sadbhavnadham.org/admin/birthday-messages

Cron: `donors:send-birthday-whatsapp` daily 09:00 Asia/Kolkata.
