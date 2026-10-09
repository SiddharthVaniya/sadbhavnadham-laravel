# Meta Ads daily spend sync

Pulls ad-level Insights from one or more Meta ad accounts (spend, impressions, clicks, reach, inline link clicks), stores snapshots in `meta_ad_spend_daily`, matches marketers by ad naming rules, and **overwrites** `marketer_daily_budgets.spend_amount` for days that have matched Meta rows. Monthly spend is re-rolled from daily rows afterward. UI charts and tables show full engagement metrics; only spend drives marketer daily budgets.

## Naming (required for matching)

Keep the marketer’s first name (or full name) before `|` in the Meta **ad name**:

```text
Ashvini | 09/10 | Sadbhavna | Pitru Amas | Old age
```

Matching uses `MarketerNameMatcher`: first pipe prefix (legacy), then **full ad + campaign + ad set text** — marketer **full name**, **first name** (whole word), or **referral code** anywhere in the string. Ambiguous ties (e.g. two Ashvinis) stay unmatched. Unmatched rows do not change daily budgets.

## Credentials

Admin sidebar → **Meta**: **Overview** (KPIs + trends), **Analytics** (breakdown charts), **Ad insights** (table), **Accounts** (credentials).

| Field | Notes |
| --- | --- |
| Label | Display name |
| App ID | Meta app id |
| App secret | Encrypted at rest; never sent to Inertia after save |
| Access token | Encrypted; needs `ads_read` + access to the ad account |
| Ad account id | Digits or `act_…` (normalized without prefix) |
| Active | Inactive accounts are skipped by sync |

Live SQL for tables and new Insights columns: `alter.md` section **Meta ad accounts + daily Insights spend**. Do not run migrate on production. After adding columns, run **Sync from live Meta** to backfill impressions/clicks on existing rows.

## Sync triggers

1. **Schedule** — `meta:sync-ad-spend --sync` every 2 hours (today + yesterday, Asia/Kolkata).
2. **Admin Sync from live Meta** — Today, This month, Spending history, Meta tab (all / per account). Throttle: 1 full sync / 60s per admin.
3. **Marketer Refresh from Meta** — `/marketer/meta` (overview), `/marketer/meta/analytics`, `/marketer/meta/ads`. Throttle: 1 / 5 minutes. UI shows only that marketer’s rows; sync still pulls active accounts.

Optional CLI:

```bash
php artisan meta:sync-ad-spend --sync
php artisan meta:sync-ad-spend --sync --account=1 --from=2026-10-01 --to=2026-10-09
```

## Overwrite rules

- For each marketer with at least one matched Meta row on a day in the sync window: `SUM(spend)` across all accounts → upsert daily `spend_amount` (keeps existing `limit_amount`).
- No matched Meta rows that day → leave existing daily spend unchanged (do not zero).
- Then `MarketerMonthlyBudgetService::syncSpendFromDaily` for affected months.

## Smart filters (admin Meta pages)

Filters are shared across Overview, Analytics, and Ad insights (query string preserved when switching tabs).

- Free text across campaign / ad set / ad names
- Marketer (resolved `user_id` or `{FirstName} |` prefix)
- Meta account, date range (default today Kolkata), campaign, ad set, cause keyword, matched/unmatched

## Out of scope

Creating ads in Ads Manager, OAuth refresh UI, Pixel/CAPI, changing donation `sid` attribution, multi-currency conversion.
