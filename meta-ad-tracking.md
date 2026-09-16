# Meta ad tracking

Use this when you build Facebook / Instagram ads for Sadbhavna. Every marketer keeps **one referral code**. That code is `sid`. It does **not** change per campaign.

One person can run many Meta campaigns. All of them use the same `sid`. Campaigns are told apart by `utm_campaign` and `utm_id`, not by `sid`.

## What `sid` is

| Parameter | Meaning | Unique per campaign? |
| --- | --- | --- |
| `sid` | The user's **referral code** (example: `zmupe`, `xvjsrg`) | **No.** Same value on every ad that person runs. |
| `utm_id` | Meta campaign ID (`{{campaign.id}}`) | Yes |
| `utm_term` | Meta ad set ID (`{{adset.id}}`) | Yes |
| `utm_campaign` | Campaign name (`{{campaign.name}}`) | Yes |
| `utm_content` | Ad name (`{{ad.name}}`) | Yes |

Do **not** put `{{adset.id}}` in `sid`. That used to be the template. It is wrong now.

The marketer panel (`/marketer`) attributes clicks and donations with:

```text
sid = your referral_code
```

Legacy links that still use `pid=` or staff `utm_content=` as the code also count until they expire.

## Copy-paste template

From **Your tracking links** on `/marketer`, copy **Meta URL parameters**. For Siddharth with code `zmupe` it looks like:

```text
utm_source=meta&utm_medium=siddharth&utm_campaign={{campaign.name}}&utm_content={{ad.name}}&sid=zmupe&utm_id={{campaign.id}}&utm_term={{adset.id}}
```

Paste that into Meta Ads Manager as **URL parameters** (Website URL → Build a URL parameter). Meta fills the `{{...}}` tokens when someone clicks. Leave the braces as they are. Do not URL-encode them.

Optional per-ad extras you can add yourself:

```text
&amt=100
&ptype=et
```

- `amt` — suggested donation amount (rupees)
- `ptype` — package / product type on the donate page

Meta always appends `fbclid` on click. You do not add that by hand.

## Full landing URL example

This is a real click URL shape (spaces shown as `+`):

```text
https://sadbhavnadham.org/donate/old-age-home
  ?utm_source=meta
  &utm_medium=siddharth
  &utm_campaign=Pritesh+-+1908+Old+age+home
  &utm_content=100+Rs+bhojan+seva+-+Gujarat
  &sid=zmupe
  &amt=100
  &ptype=et
  &utm_id=120252324315880236
  &utm_term=120252324315890236
  &fbclid=IwY2xjawTyeBpwZG9mAWV4dG4DYWVtATAAYWRpZAGrONpD9a08c3J0YwZhcHBfaWQQMjIyMDM5MTc4ODIwMDg5MgABHk-W9Eu7ygj0TvrQtv7suLnhOMYjnLa7GeL2zEgl3V5WVailSnHyEKugGlST_aem_MSqRq7r7g3j8nmqM-dF5Hg
```

Same person, second campaign — **keep `sid=zmupe`**, change the campaign fields:

```text
https://sadbhavnadham.org/donate/tree-plantation
  ?utm_source=meta
  &utm_medium=siddharth
  &utm_campaign=Monsoon+Peepal+Drive
  &utm_content=Plant+a+Peepal+tree
  &sid=zmupe
  &utm_id=120299999999999999
  &utm_term=120288888888888888
```

Both ads belong to Siddharth. The panel splits them by campaign name / campaign ID.

## Parameter dictionary

| Key | Who sets it | Example | Stored as |
| --- | --- | --- | --- |
| `utm_source` | Template | `meta` | `utm_source` |
| `utm_medium` | First name of the marketer, lowercase | `siddharth` | `utm_medium` |
| `utm_campaign` | Meta `{{campaign.name}}` | `Pritesh - 1908 Old age home` | `utm_campaign` |
| `utm_content` | Meta `{{ad.name}}` | `100 Rs bhojan seva - Gujarat` | `utm_content` |
| `sid` | User referral code | `zmupe` | `link_tracking_visits.sid` and partner code |
| `utm_id` | Meta `{{campaign.id}}` | `120252324315880236` | `meta_campaign_id` |
| `utm_term` | Meta `{{adset.id}}` | `120252324315890236` | `meta_adset_id` |
| `amt` | You, per ad | `100` | visit `amt` |
| `ptype` | You, per ad | `et` | visit `ptype` |
| `fbclid` | Meta | long click id | visit `fbclid` |

Staff share links (not Meta) use the same `sid` with `utm_source=staff`. Those are the **Share URL** on the same card.

## How performance is counted

1. Click hits the donate page with `sid=zmupe`.
2. Next.js / WordPress sends `/api/donate/track` (and checkout later).
3. A row is stored in `link_tracking_visits` with that `sid`.
4. Totals per marketer are rolled into `link_tracking_summary` keyed by `sid` (one summary row per person).
5. `/marketer` shows:
   - **totals** for that `sid`
   - **campaign table** grouped by `utm_id` + `utm_campaign` (many rows, same `sid`)
   - **click log** from `link_tracking_visits`

If `sid` is missing, tracking still tries a leftover `pid` and staff `utm_content`.

## Checklist for a new Meta campaign

1. Open `/marketer` and copy **Meta URL parameters**.
2. In Ads Manager, website URL = the cause or campaign landing page (no tracking query yet).
3. Paste the copied string into **URL parameters**.
4. Confirm `sid=` is your code, not `{{adset.id}}`.
5. Optionally add `&amt=` and `&ptype=` for that creative.
6. Publish. Clicks from every campaign with that `sid` show on your panel.

## Share URL vs Meta parameters

| | Share URL | Meta URL parameters |
| --- | --- | --- |
| Use | WhatsApp, email, staff links | Facebook / Instagram ads |
| Identifies you | `sid` | `sid` |
| Campaign fields | none | Meta `{{campaign.name}}` / `{{campaign.id}}` / `{{adset.id}}` / `{{ad.name}}` |
