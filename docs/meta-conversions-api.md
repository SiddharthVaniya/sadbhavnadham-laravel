# Meta Conversions API (server-side)

Browser Meta pixels on the public donate site are **not** changed. Donation **Purchase** and **InitiateCheckout** are sent from this Laravel app via the [Conversions API](https://developers.facebook.com/docs/marketing-api/conversions-api).

## Credentials (.env)

Pixel IDs and access tokens belong in **`.env`** only (see `.env.example` → `META_CAPI_*`).

**One pixel per donation** (same as the public site): link/query `pixel_id=sadbhavna_d` or `sadbhavna_1` is stored on `donation_orders.meta_pixel_code` at checkout; CAPI Purchase / InitiateCheckout go only to that pixel.

| Variable | Purpose |
| --- | --- |
| `META_CAPI_ENABLED` | Master switch (default `true`) |
| `META_CAPI_ALLOW_DATABASE_PIXELS` | Allow extra pixels saved in admin DB (default `false`) |
| `META_CAPI_PIXEL_1_ID` / `_TOKEN` | First pixel |
| `META_CAPI_PIXEL_2_ID` / `_TOKEN` | Second pixel (optional) |
| `META_CAPI_PIXEL_*_SEND_PURCHASE` | Send Purchase when paid |
| `META_CAPI_PIXEL_*_SEND_INITIATE_CHECKOUT` | Send on checkout create |
| `META_CAPI_PIXEL_*_TEST_EVENT_CODE` | Meta Test events (optional) |

After editing `.env` on production:

```bash
php artisan config:clear
php artisan config:cache
```

Tokens are **not** stored in the database when configured via env (DB rows hold label/settings for logs only).

## Admin UI

**Admin → Meta → Pixels (CAPI)** shows:

- Read-only **Environment pixels** (from config / `.env`)
- Delivery **logs** and last status per pixel row

## When events fire

| Event | Trigger |
| --- | --- |
| `InitiateCheckout` | Razorpay checkout order created (`DonationController::razorpay`) |
| `Purchase` | Payment captured (`DonationPaymentService::completePaidOrder`) |

Queued jobs: `SendMetaCapiInitiateCheckoutJob`, `SendMetaCapiPurchaseJob`.

## User data

Hashed email, phone, name, location; client IP; user agent; `fbc` from `fbclid` on visit or landing URL. Stable `event_id` per order to avoid duplicate sends.

## Database

`meta_pixels` / `meta_capi_event_logs` — see `alter.md` (Meta Conversions API section).
