# APIs.md — Sadbhavna (Next.js + Laravel)

This is the complete API map for **both** projects in this repo.

| Project | Folder | Role | Typical host |
|---|---|---|---|
| **Next.js frontend** | `/home/sadbhavnadham/htdocs/sadbhavnadham.org` | Website, donate UI, `/api/donate/*` **proxies only** | `https://sadbhavnadham.org` |
| **Laravel backend** | `/home/sadbhavnadham-admin/htdocs/admin.sadbhavnadham.org` | Source of truth for causes, checkout, payments, tracking DB | `https://admin.sadbhavnadham.org` |

Next.js has **no donation database**. Laravel owns MySQL. WordPress is used only for **blog/SEO** (server-side fetch, not a donate API).

```text
Browser  →  Next.js /api/donate/*   →  Laravel /api/donate/*     (proxied, token added for checkout)
Browser  →  Laravel /api/donate/track                            (axios direct, no token)
Razorpay →  Laravel /api/webhook/razorpay                        (webhook only)
```

---

## 1. Environment

### Next.js (root `.env.local`)

| Variable | Who sees it | Purpose |
|---|---|---|
| `NEXT_PUBLIC_SITE_URL` | browser | Site origin |
| `NEXT_PUBLIC_DONATION_API_URL` | browser | Laravel origin for **track** (axios) |
| `DONATION_API_URL` | server | Laravel origin for proxies |
| `DONATION_API_TOKEN` | server only | Same value as Laravel `WP_API_TOKEN` |
| `WORDPRESS_SITE_URL` / `WORDPRESS_API_URL` | server | Blog |
| `NEXT_PUBLIC_DANAMOJO_URL` | browser | International donate widget |

### Laravel (`SadbhavnaDonation/.env`)

| Variable | Purpose |
|---|---|
| `APP_URL` | Laravel public URL |
| `FRONTEND_URL` | Next.js origin |
| `WP_API_TOKEN` | Checkout header `X-WP-TOKEN` |
| `DB_*` | MySQL |
| `RAZORPAY_KEY` | Public Razorpay key (checkout). Also returned by `GET /api/donate/config` as `razorpay_key`. |
| `RAZORPAY_SECRET` | Private key. Laravel server only. Never `NEXT_PUBLIC_*`. |
| `WEBHOOK_SECRET` | Razorpay webhook signature secret. |

On a new Laravel server:

```bash
cd SadbhavnaDonation
php artisan migrate
```

Or phpMyAdmin: `SadbhavnaDonation/database/sql/link_tracking.sql`

---

## 2. Auth

| Type | Header / query | Used by |
|---|---|---|
| Public | none | causes, config, pincode, bank-details, pan-requirement, track, OTP send/verify |
| Server token | `X-WP-TOKEN: <WP_API_TOKEN>` | checkout, subscription checkout, wp-* aliases |
| Signed thank-you | `?signature=&expires=` from `thank_you_url`, **or** `X-WP-TOKEN` | thank-you GETs |
| Donor Sanctum | `Authorization: Bearer <token>` | `/donate/me`, `/donate/logout` |
| Razorpay | `X-Razorpay-Signature` | webhook |

**Never** put `WP_API_TOKEN` / `DONATION_API_TOKEN` in browser JS. Next.js adds `X-WP-TOKEN` only in the **server** proxy for checkout.

---

## 3. Full mapping (both projects)

| Browser / Next path | Next file | Laravel path | Method | Auth | Notes |
|---|---|---|---|---|---|
| `/api/donate/causes` | `src/app/api/donate/causes/route.ts` | `/api/donate/causes` | GET | public | Proxy |
| `/api/donate/causes/{slug}` | `src/app/api/donate/causes/[slug]/route.ts` | `/api/donate/causes/{slug}` | GET | public | Proxy |
| `/api/donate/config` | `src/app/api/donate/config/route.ts` | `/api/donate/config` | GET | public | Proxy |
| `/api/donate/pincode/{pincode}` | `src/app/api/donate/pincode/[pincode]/route.ts` | `/api/donate/pincode/{pincode}` | GET | public | Proxy, 60/min |
| `/api/donate/bank-details` | `src/app/api/donate/bank-details/route.ts` | `/api/donate/bank-details` | GET | public | Proxy |
| `/api/donate/thank-you/{order}` | `src/app/api/donate/thank-you/[order]/route.ts` | `/api/donate/thank-you/{order}` | GET | signed URL query | Proxy + query string |
| `/api/donate/thank-you/subscription/{subscription}` | `…/thank-you/subscription/[subscription]/route.ts` | `/api/donate/thank-you/subscription/{subscription}` | GET | signed URL query | Proxy + query string |
| `/api/donate/pan-requirement` | `src/app/api/donate/pan-requirement/route.ts` | `/api/donate/pan-requirement` | POST | public | Proxy, 30/min |
| `/api/donate/checkout` | `src/app/api/donate/checkout/route.ts` | `/api/donate/checkout` | POST | Next adds `X-WP-TOKEN` | 10/min |
| `/api/donate/subscription` | `src/app/api/donate/subscription/route.ts` | `/api/donate/checkout/subscription` | POST | Next adds `X-WP-TOKEN` | 10/min |
| `/api/donate/otp/send` | `src/app/api/donate/otp/send/route.ts` | `/api/donate/otp/send` | POST | public | Proxy |
| `/api/donate/otp/verify` | `src/app/api/donate/otp/verify/route.ts` | `/api/donate/otp/verify` | POST | public | Proxy |
| `/api/donate/me` | `src/app/api/donate/me/route.ts` | `/api/donate/me` | GET | forwards `Authorization` | Sanctum |
| `/api/donate/logout` | `src/app/api/donate/logout/route.ts` | `/api/donate/logout` | POST | forwards `Authorization` | Sanctum |
| **axios** `{LARAVEL}/api/donate/track` | `src/lib/link-tracking-client.ts` | `/api/donate/track` | POST | public | **Not proxied** |
| — | — | `/api/wp-razorpay` | POST | `X-WP-TOKEN` | WordPress alias of checkout |
| — | — | `/api/wp-razorpay/subscription` | POST | `X-WP-TOKEN` | WordPress alias |
| — | — | `/api/wp-pan-requirement` | POST | `X-WP-TOKEN` | WordPress alias |
| — | — | `/api/webhook/razorpay` | POST | Razorpay signature | Payments |
| — | — | `/api/user` | GET | Sanctum | Generic user |

JSON: `Accept: application/json`, `Content-Type: application/json`.

---

## 4. Meta / campaign tracking (complete)

### 4.1 Landing GET params (Next.js URL)

Captured on **every page** by `LinkTrackingTracker`. At least one of these must be present to save a visit:

| Query param | Example | Sent to Laravel | Max length | Meaning |
|---|---|---|---|---|
| `sid` | `zmupe` | yes | 255 | Marketer referral code |
| `utm_source` | `meta` | yes | 120 | Source |
| `utm_medium` | `siddharth` | yes | 120 | Medium |
| `utm_campaign` | `Pritesh - 1908 Old age home` | yes | 120 | Campaign name |
| `utm_content` | `100 Rs bhojan seva - Gujarat` | yes | 120 | Ad / creative |
| `utm_id` | `120252324315880236` | yes | 120 | Meta campaign id |
| `utm_term` | `120252324315890236` | yes | 120 | Meta ad set id |
| `fbclid` | `IwY2xjawTyeBp…` | yes | 255 | Facebook click id |
| `amt` | `100` | yes | 32 | Prefills donate amount |
| `ptype` | `et` | yes | 32 | Payment type hint |

Example:

```text
https://madspire.site/donate/old-age-home
  ?utm_source=meta
  &utm_medium=siddharth
  &utm_campaign=Pritesh+-+1908+Old+age+home
  &utm_content=100+Rs+bhojan+seva+-+Gujarat
  &sid=zmupe
  &amt=100
  &ptype=et
  &utm_id=120252324315880236
  &utm_term=120252324315890236
  &fbclid=IwY2xjawTyeBp...
```

### 4.2 Browser cookies (Next.js domain)

Session cookies + `sessionStorage`. **Tab close destroys them.**

| Key | Value |
|---|---|
| `lt_vid` | UUID visitor id |
| `lt_attr` | JSON of campaign params |
| `sessionStorage lt_sent:{sid}` | Visit already posted for this SID |

Laravel does not read these cookies (cross-origin). Values are sent in JSON.

### 4.3 `POST /api/donate/track` (Laravel only)

Called by browser **axios** → `NEXT_PUBLIC_DONATION_API_URL`.

**Throttle:** 60/min  
**Auth:** none

#### Body

| Field | Required | Type | Max | Notes |
|---|---|---|---|---|
| `visitor_id` | **yes** | UUID | 36 | From `lt_vid` |
| `sid` | no | string | 255 | Stored as `''` if missing |
| `utm_source` | no* | string | 120 | |
| `utm_medium` | no* | string | 120 | |
| `utm_campaign` | no* | string | 120 | |
| `utm_content` | no* | string | 120 | |
| `utm_id` | no* | string | 120 | |
| `utm_term` | no* | string | 120 | |
| `fbclid` | no* | string | 255 | |
| `amt` | no* | string | 32 | |
| `ptype` | no* | string | 32 | |
| `landing_url` | no | string | 2048 | `window.location.href` |
| `referrer` | no | string | 512 | `document.referrer` |

\* At least one campaign field (`sid` / `utm_*` / `fbclid` / `amt` / `ptype`) required, or Laravel returns `recorded: false`.

Laravel also writes: `ip_address`, `user_agent`, `device_type` (`mobile` \| `desktop` \| `tablet`).

#### Unique rule

`UNIQUE (visitor_id, sid)` — same visitor + same SID is **ignored** (no extra click).

#### Responses

```json
{ "recorded": true, "unique": true,  "visit_id": 41, "sid": "zmupe" }
{ "recorded": true, "unique": false, "visit_id": 41, "sid": "zmupe" }
{ "recorded": false, "reason": "no_tracking_params" }
```

422 if `visitor_id` is missing / not a UUID.

#### curl

```bash
curl -X POST "https://admin.sadbhavnadham.org/api/donate/track" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "visitor_id": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
    "sid": "zmupe",
    "utm_source": "meta",
    "utm_medium": "siddharth",
    "utm_campaign": "Pritesh - 1908 Old age home",
    "utm_content": "100 Rs bhojan seva - Gujarat",
    "utm_id": "120252324315880236",
    "utm_term": "120252324315890236",
    "fbclid": "IwY2xjawTyeBpExample",
    "amt": "100",
    "ptype": "et",
    "landing_url": "https://madspire.site/donate/old-age-home?sid=zmupe&utm_source=meta",
    "referrer": "https://www.facebook.com/"
  }'
```

### 4.4 Conversion (automatic)

1. Track visit → `link_tracking_visits` + `link_tracking_summary` clicks  
2. Checkout with `visitor_id` + `sid` → sets `donation_order_id`  
3. Razorpay `payment.captured` → `converted=1`, `converted_amount`, `converted_at`, summary donations/amount

```sql
SELECT id, visitor_id, sid, utm_source, amt, converted, donation_order_id
FROM link_tracking_visits ORDER BY id DESC LIMIT 20;

SELECT sid, unique_visitors, total_donations, total_amount
FROM link_tracking_summary ORDER BY id DESC;
```

---

## 5. Next.js donate APIs (what the browser calls)

Base: `https://madspire.site` (same origin). All of these except **track** are implemented under `src/app/api/donate/`.

### GET `/api/donate/causes`

No params. Returns `{ "causes": [ ... ] }`.

### GET `/api/donate/causes/{slug}`

Path: cause slug (`old-age-home`, `animal-hospital`, …).

Returns cause, packages, `default_package_id`, `default_amount`.

### GET `/api/donate/config`

No params. Razorpay **public** key, min amounts, form copy. Secret is never exposed.

### GET `/api/donate/pincode/{pincode}`

Path: 6-digit pincode.

| Status | Body |
|---|---|
| 200 | `{ "found": true, "city", "state", "country" }` |
| 404 | `{ "found": false, "message" }` |
| 422 | invalid length |
| 503 | Laravel timeout |

### GET `/api/donate/bank-details`

No params. Account / IFSC / UPI / contact.

### GET `/api/donate/thank-you/{orderUuid}?signature=&expires=`

Query comes from checkout `thank_you_url`. Forwards query to Laravel.

### GET `/api/donate/thank-you/subscription/{subscriptionUuid}?signature=&expires=`

Same for recurring.

### POST `/api/donate/pan-requirement`

Body:

| Field | Required | Type |
|---|---|---|
| `cause` | yes | slug |
| `package_id` | no | integer |
| `amount` | no | number 1–500000 |
| `quantity` | no | integer 1–100 |
| `donor_email` | no | email |
| `donor_phone` | no | 10 digits |

Response: `{ required, current_amount, fy_paid_total, combined_total, threshold, known_pan_number }`.

### POST `/api/donate/checkout`

Browser → Next (no token) → Laravel with `X-WP-TOKEN`.

See [§6 checkout body](#6-checkout-body-laravel--next-proxy). Next also injects defaults `source_channel: "web"`, `utm_source: "direct"`, `utm_medium: "website"` **only if** the body does not already send campaign UTMs.

### POST `/api/donate/subscription`

Same as checkout, plus `frequency`, `consent_recurring`. Proxies to Laravel `/api/donate/checkout/subscription`.

### POST `/api/donate/otp/send`

| Field | Required |
|---|---|
| `login_method` | no (`phone` default, or `email`) |
| `donor_phone` | if phone |
| `phone_dial_code` | if phone, default `91` |
| `donor_country_code` | no, default `IN` |
| `donor_email` | if email |

### POST `/api/donate/otp/verify`

Same identity fields + `otp` (usually 6 digits). Returns `{ token, token_type: "Bearer", profile }`.

### GET `/api/donate/me`

Header: `Authorization: Bearer <token>`.

### POST `/api/donate/logout`

Header: `Authorization: Bearer <token>`.

### 503 from Next proxy

`{ "error": "Donation API is temporarily unavailable." }` if Laravel times out (4s) or payment token is missing on checkout.

---

## 6. Checkout body (Laravel + Next proxy)

Used by `POST /api/donate/checkout` (Next) → `POST /api/donate/checkout` (Laravel).

### Donor / payment fields

| Field | Required | Type | Rules |
|---|---|---|---|
| `cause` | **yes** | string | Active slug |
| `package_id` | no | integer | Must belong to cause |
| `amount` | if no package | number | 1–500000 |
| `quantity` | no | integer | 1–100 (subscription must be `1`) |
| `donor_name` | **yes** | string | letters / spaces / `' . - ()` |
| `donor_email` | **yes** | email | max 255 |
| `donor_phone` | **yes** | string | 10 digits |
| `date_of_birth` | no | date | before today |
| `address` | **yes** | string | max 1000 |
| `pincode` | **yes** | string | 6 digits |
| `city` | **yes** | string | max 120 |
| `state` | **yes** | string | max 120 |
| `country` | **yes** | string | e.g. `INDIA` |
| `donor_country` | no | string | `IN` |
| `consent_indian_citizen` | **yes** | accepted | `1` / true |
| `pan_number` | if PAN required | string | `ABCDE1234F` |
| `title` | no | string | item title |
| `campaign_slug` | no | string | locked campaign |
| `amount_locked` | no | boolean | |
| `donation_type` | no | string | UI only |

### Recurring extra (`/api/donate/subscription`)

| Field | Required | Rules |
|---|---|---|
| `frequency` | **yes** | `monthly` or `weekly` |
| `consent_recurring` | **yes** | accepted |

### Tracking extra (from cookies)

| Field | Type | Max |
|---|---|---|
| `visitor_id` | UUID | 36 |
| `sid` | string | 255 |
| `utm_source` | string | 120 |
| `utm_medium` | string | 120 |
| `utm_campaign` | string | 120 |
| `utm_content` | string | 120 |
| `utm_id` | string | 120 |
| `utm_term` | string | 120 |
| `fbclid` | string | 255 |
| `amt` | string | 32 |
| `ptype` | string | 32 |
| `landing_url` | string | 2048 |
| `source_channel` | string | 32 (`web` from Next) |

### Checkout success (one-time)

```json
{
  "provider": "razorpay",
  "order": {
    "order_id": "order_xxx",
    "order_uuid": "uuid",
    "thank_you_url": "https://…/donate/thank-you/…?signature=…",
    "key": "rzp_live_or_test",
    "amount": 10000,
    "name": "…",
    "email": "…",
    "contact": "…",
    "description": "…"
  }
}
```

`amount` is **paise** (₹100 = `10000`).

### Subscription success

```json
{
  "provider": "razorpay",
  "mode": "subscription",
  "subscription": {
    "subscription_id": "sub_xxx",
    "subscription_uuid": "uuid",
    "thank_you_url": "https://…",
    "key": "rzp_…",
    "name": "…",
    "email": "…",
    "contact": "…",
    "description": "…"
  }
}
```

---

## 7. Laravel-only APIs (not on Next)

Use these on the **Laravel host**.

### `POST /api/wp-razorpay`

Same body as checkout. Header `X-WP-TOKEN`. WordPress / Forminator.

### `POST /api/wp-razorpay/subscription`

Same as subscription checkout.

### `POST /api/wp-pan-requirement`

Same as pan-requirement, with token.

### `POST /api/webhook/razorpay`

Razorpay dashboard URL. Header `X-Razorpay-Signature`.

Events: `payment.captured`, `payment.failed`, `qr_code.credited`, `subscription.*`.

Do not call from Next.js.

### `GET /api/user`

Sanctum user (generic Laravel).

---

## 8. Status codes

| Code | Meaning |
|---|---|
| 200 | OK |
| 401 | Missing/invalid Bearer token |
| 403 | Bad `X-WP-TOKEN`, bad thank-you signature, or bad webhook signature |
| 404 | Cause / pincode / order not found |
| 422 | Validation |
| 429 | Throttle |
| 500 | Laravel error |
| 503 | Next proxy: Laravel down or checkout token missing |

---

## 9. CORS (Laravel)

Allowed origins include:

- `https://madspire.site`
- `https://www.madspire.site`
- `https://admin.sadbhavnadham.org`
- `https://sadbhavnadham.org`
- `http://localhost:3000`
- `http://127.0.0.1:3000`

Required for browser **axios → `/api/donate/track`**. Add any new frontend domain in `SadbhavnaDonation/config/cors.php`.

---

## 10. WordPress (blog only)

Not a donate API. Next.js server fetches:

`{WORDPRESS_API_URL}/posts`, pages, media — e.g. `https://siddharth.sadbhavnadham.org/wp-json/wp/v2`.

No Next `/api/*` route wraps this.

---

## 11. Server checklist

**Laravel (`SadbhavnaDonation`)**

1. Deploy code  
2. Set `.env` (`APP_URL`, `DB_*`, `WP_API_TOKEN`, Razorpay, `FRONTEND_URL`)  
3. `php artisan migrate`  
4. Webhook: `https://admin.sadbhavnadham.org/api/webhook/razorpay`  
5. CORS includes Next origin  

**Next.js (root)**

1. `DONATION_API_URL` + `NEXT_PUBLIC_DONATION_API_URL` = Laravel origin  
2. `DONATION_API_TOKEN` = Laravel `WP_API_TOKEN`  
3. Do **not** expose the token as `NEXT_PUBLIC_*`  

**Test**

```text
https://madspire.site/donate/old-age-home?utm_source=meta&sid=zmupe&amt=100
```

Then confirm a row in `link_tracking_visits`. After a paid donation, `converted = 1`.
