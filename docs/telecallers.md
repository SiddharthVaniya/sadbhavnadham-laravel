# Telecaller portal

Guide for staff who follow up on **failed online donations** in the Sadbhavna Dham admin (`https://admin.sadbhavnadham.org`).

---

## Who is a telecaller?

You have the **Telecaller** role (the system accepts either `Telecaller` or `telecaller` in the database). After login you see a simplified **Donations** menu—not the full admin donations, offline entry, or analytics screens.

**Required permission:** `view donations` (you do **not** need `view all donations`; the app shows the right lists for telecallers automatically.)

Super admins who also have the Telecaller role still use the normal admin experience.

---

## Sign in and home screen

1. Open **https://admin.sadbhavnadham.org** and sign in with your admin account.
2. You land on **Failed donations** (default period: **Today**).
3. The sidebar under **Donations** has only:
   - **Failed donations**
   - **Search by mobile**

If you open the old “All donations” or “Offline” URLs, the app redirects you back to the telecaller pages.

---

## Failed donations

**Path:** `/admin/donations/telecaller`  
**Menu:** Donations → Failed donations

### What you see

- Only donations with status **failed** (abandoned or unsuccessful checkouts).
- A summary card: count of failed checkouts and total attempted amount for the selected period.
- A table with donor, cause, amount, failure reason, date, notes, and actions.

### Date range

Use the period dropdown (e.g. **Today**, **Yesterday**, **This week**, **All time**, **Custom**).

- For presets, dates use **when the payment failed** when that is recorded (`failed_at`), otherwise **when the order was created**.
- **Custom** lets you pick **From** and **To**, then **Search**.

### Search on this page

The search box filters **within the failed list** for the current period. You can search by:

- Donor name, email, or phone  
- Payment / order IDs  
- Receipt number  

Typing a search while on **Today** automatically widens the period to **All time** so matches are not hidden by the date filter.

**Reset** clears filters and sets the period to **All time**.

### Sorting and pagination

Click column headers to sort. The list is paginated (25 per page).

### Donation details

Use the **eye** icon to open the full donation record. **Back** returns you to the same list filters you had open.

### “Later paid” badge

If the donor completed payment on a **later** attempt, you may see a **Later paid** link on a failed row. Use it to open the successful donation—useful when explaining that the original checkout failed but they paid afterward.

---

## Search by mobile

**Path:** `/admin/donations/telecaller/search`  
**Menu:** Donations → Search by mobile

Use this when you have a phone number and need **any** donation tied to that number—not only failed ones.

1. Enter at least **8 digits** (country code optional; the app normalizes the number).
2. Click **Search**.

Results can include **paid**, **failed**, and other statuses for that mobile. You can sort columns and open details or log conversations the same way as on the failed list.

**Clear** empties the search.

---

## Logging conversations (notes)

On either list, use the **message** icon or click the latest note in the **Notes** column.

### Conversation modal

- **I said** — what you told the donor.  
- **Donor said** — what the donor replied (for a clear thread).  
- **Outcome** (optional) — pick from the predefined list.  
- **Follow-up** (optional) — date/time for a callback.  
- Press **Enter** to send (Shift+Enter for a new line).

Notes are saved on the donation and visible to other telecallers and admins with access. The list shows the **most recent** note snippet.

---

## What telecallers do not use

| Area | Behavior |
|------|----------|
| Admin dashboard | Redirects to Failed donations |
| All donations index | Redirects to telecaller failed list |
| Offline / My receipts | Redirected away |
| Record offline donation | Hidden in menu |
| Causes, campaigns, settings, etc. | Hidden unless your account has other roles |

If you need offline entry or refunds, ask an admin—they use roles with broader permissions.

---

## Tips for daily work

1. Start each shift on **Failed donations · Today** and work newest-first (default sort).
2. Log every call in **conversation notes** so the next shift sees context.
3. If the donor says they already paid, check **Search by mobile** for a **paid** row or use **Later paid** on the failed row.
4. If the list looks empty, switch to **All time** or confirm the donor’s number in **Search by mobile**.

---

## For administrators: adding a telecaller

Do **not** run SQL on production without review. Use the dated section in **`alter.md`** (*Telecaller role*) for:

- Creating the **Telecaller** role and `view donations` permission  
- Assigning the role to a user  
- Optional index on `donation_orders.failed_at` for faster date filters  

After deploy or route changes, refresh route cache on the server:

```bash
php artisan route:clear && php artisan route:cache
```

**Routes (for support / debugging):**

| Route name | URL |
|------------|-----|
| `admin.donations.telecaller` | `GET /admin/donations/telecaller` |
| `admin.donations.telecaller.search` | `GET /admin/donations/telecaller/search` |
| `admin.donations.telecaller-notes.index` | `GET /admin/donations/{uuid}/telecaller-notes` |
| `admin.donations.telecaller-notes.store` | `POST /admin/donations/{uuid}/telecaller-notes` |

Automated tests: `tests/Feature/AdminTelecallerDonationsTest.php`.

---

## Support

If you get **403 Forbidden** on telecaller pages, your account is missing the **Telecaller** role or `view donations` permission—contact an admin.

If telecaller URLs return **404**, routes may be stale on the server; an admin should run `php artisan route:clear && php artisan route:cache` after deployment.
