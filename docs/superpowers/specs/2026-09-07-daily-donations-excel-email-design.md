# Daily donations Excel email — design

**Date:** 2026-09-07  
**Status:** Implemented.

## Goal

Every night at **23:50 Asia/Kolkata**, email an Excel (`.xlsx`) of **all donation orders created that calendar day** (pending + paid + failed) to addresses configured in `.env`. The spreadsheet’s last column is **Check**, with Excel data validation so recipients can mark rows (`☐` / `☑`).

Admin **Export CSV** stays as CSV and is out of scope for this feature (already excludes Source / UTM Campaign / UTM Content / Receipt Status).

## Requirements (confirmed)

| Item | Choice |
|------|--------|
| Which donations | **A** — all statuses, `created_at` = today |
| File format | **C** — `.xlsx` with Check column |
| Admin export | **A** — nightly email only; CSV button unchanged |
| Time | 23:50 (`Asia/Kolkata`, app timezone) |
| Recipients | Comma-separated env list; primary first |

## Non-goals

- Changing admin Export CSV to Excel
- Real floating Form Control / ActiveX checkboxes (fragile across Excel Online / Sheets)
- Filtering by paid-only or by `paid_at`

## Configuration

```env
DONATION_DAILY_REPORT_EMAILS=siddharthvaniya123@gmail.com
# Later: siddharthvaniya123@gmail.com,other@example.com
```

- Read only via `config/` (e.g. `config/donations.php` or `config/mail.php` companion), never `env()` in app code.
- Parse: trim, split on commas, drop empties, validate email shape loosely.
- If empty / invalid after parse → log and **skip send** (command exits successfully).
- First address = **To**; remaining = **Cc** (all receive the same attachment).

## Behaviour

1. Artisan command: `donations:email-daily-report` (optional `--date=Y-m-d` for backfill/tests; default today in app TZ).
2. Query `donation_orders` where `created_at` is within that local calendar day; order newest-first (or by `id` desc). Apply no admin visibility filter (system report of all site donations).
3. Build `.xlsx` with PhpSpreadsheet (`phpoffice/phpspreadsheet`):
   - Columns (same as current admin CSV export): Order UUID, Payment ID, Donor Name, Donor Email, Donor Phone, Cause, Cause title, Amount (INR), Status, Payment Provider, Receipt Number, Created At, Paid At, **Check**
   - One row per cause/title line when an order expands like admin export (`AdminInertiaData::donationTableRows`), or one row per order if that helper is reused consistently with CSV export.
   - Header freeze row 1; Check column default `☐`; data validation list `☐,☑` on that column for all data rows.
4. Mailable with subject `Daily donations — {d M Y}`, short body (count of orders + date), attach the temp `.xlsx`, then delete temp file.
5. Schedule in `routes/console.php`: `Schedule::command('donations:email-daily-report')->dailyAt('23:50');`
6. Logging: recipients count, order count, success/failure; no secrets in logs.

## Dependencies

- Add `phpoffice/phpspreadsheet` via Composer (project does not have it yet). Needs explicit approval for dependency add — **approved as part of Approach 1**.

## Testing

- Feature/Pest: with `Mail::fake()`, create paid + pending + failed today and one yesterday; run command; assert mailable sent to primary (and CC if configured), attachment name/extension, yesterday excluded.
- Unit/feature: empty env → no mail sent.
- Optional: spreadsheet Check header present (parse with Spreadsheet or assert binary/xlsx zip contains sheet XML with Check).

## Ops notes

- Ensure server cron runs `php artisan schedule:run` every minute (already required for other daily jobs).
- Set `DONATION_DAILY_REPORT_EMAILS` on production `.env` before the first 23:50 run.
- Manual test: `php artisan donations:email-daily-report` (or `--date=`).

## Open decisions (locked)

- Check UI: data validation `☐`/`☑`, not Form Controls.
- To/Cc split: first = To, rest = Cc.
