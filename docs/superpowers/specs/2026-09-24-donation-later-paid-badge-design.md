# Failed donation “Later paid” soft link

Date: 2026-09-24  
Status: Approved (Approach 1)

## Problem

When a donor retries after a failed checkout, Razorpay creates a **new** order. The original **Failed** row stays on Donations → All. Telecallers see Failed and assume payment failed, even when a later attempt is **Paid**.

## Solution

Keep Failed visible. Soft-link to the earliest later Paid donation from the same donor within **24 hours**.

## UI

- Donations index: on Failed rows, badge **Later paid** next to status (or under payment id). Link opens the successful donation.
- Donation show: same badge + “View successful donation” when applicable.

## Match rules

1. Source order status = `failed`
2. Candidate status = `paid`, different id
3. Candidate `paid_at` (else `created_at`) is after the failed order’s `failed_at` (else `created_at`) and within +24 hours
4. Same donor: `donor_id` if set; else normalized phone; else lowercased email
5. If no identity fields, no badge
6. Prefer earliest matching Paid

## Non-goals

- No schema / webhook changes
- Do not hide or delete Failed rows
- Do not change Recovery queue counts

## Tests

- Index/Show: Failed + later Paid same phone → badge + url
- Outside 24h or different donor → no badge
