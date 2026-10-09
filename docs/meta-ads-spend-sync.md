# Meta Ads daily spend sync

Pulls ad-level Insights spend from one or more Meta ad accounts, stores snapshots, matches marketers by the first pipe segment of the ad name, and **overwrites** `marketer_daily_budgets.spend_amount` for days that have matched Meta rows. Monthly spend is re-rolled from daily rows afterward.

## Naming (required for matching)

Keep the marketer’s first name (or full name) before `|` in the Meta **ad name**:

```text
Ashvini | 09/10 | Sadbhavna | Pitru Amas | Old age
```

Matching uses the same rules as `StaffReferral::partnerFromMetaAdNamePrefix` / `MarketerNameMatcher`. Unmatched ads stay in `meta_ad_spend_daily` with `matched_via = unmatched` and do not change daily budgets.

## Credentials

Admin → Marketers → **Meta** tab.

| Field | Notes |
| --- | --- |
| Label | Display name |
| App ID | Meta app id |
| App secret | Encrypted at rest; never sent to Inertia after save |
| Access token | Encrypted; needs `ads_read` + access to the ad account |
| Ad account id | Digits or `act_…` (normalized without prefix) |
| Active | Inactive accounts are skipped by sync |

Live SQL for tables: `alter.md` section **Meta ad accounts + daily Insights spend**. Do not run migrate on production.

## Sync triggers

1. **Schedule** — `meta:sync-ad-spend --sync` every 2 hours (today + yesterday, Asia/Kolkata).
2. **Admin Sync from live Meta** — Today, This month, Spending history, Meta tab (all / per account). Throttle: 1 full sync / 60s per admin.
3. **Marketer Refresh from Meta** — `/marketer/meta`. Throttle: 1 / 5 minutes. UI shows only that marketer’s rows; sync still pulls active accounts.

Optional CLI:

```bash
php artisan meta:sync-ad-spend --sync
php artisan meta:sync-ad-spend --sync --account=1 --from=2026-10-01 --to=2026-10-09
```

## Overwrite rules

- For each marketer with at least one matched Meta row on a day in the sync window: `SUM(spend)` across all accounts → upsert daily `spend_amount` (keeps existing `limit_amount`).
- No matched Meta rows that day → leave existing daily spend unchanged (do not zero).
- Then `MarketerMonthlyBudgetService::syncSpendFromDaily` for affected months.

## Smart filters (admin Meta tab)

- Free text across campaign / ad set / ad names
- Marketer (resolved `user_id` or `{FirstName} |` prefix)
- Meta account, date range (default today Kolkata), campaign, ad set, cause keyword, matched/unmatched

## Out of scope

Creating ads in Ads Manager, OAuth refresh UI, Pixel/CAPI, changing donation `sid` attribution, multi-currency conversion.
