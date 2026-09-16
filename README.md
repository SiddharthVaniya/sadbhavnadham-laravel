# Sadbhavna Donation Platform

Donation portal for **Sadbhavna Vrudhashram / Manav Seva Cheritable Trust** (`donate.sadbhavnadham.org`). Donors give to causes and campaigns via Razorpay; staff manage donations, CRM, receipts, WhatsApp, and analytics from an admin portal.

## Features

- Public cause pages and campaign landings (`/give/{slug}`)
- Razorpay one-time payments and optional recurring subscriptions
- Bank-transfer details and Danamojo (foreign/alternate) checkout
- PDF receipts (DomPDF), Gujarati sanman-patra certificates, thank-you pages
- AiSensy WhatsApp (thank-you, certificate, payment links, birthdays, broadcast campaigns)
- Google Sheets donation logging
- WordPress embed API (`X-WP-TOKEN`) and iframe support for `sadbhavnadham.org`
- Admin CRM: donations, donors, subscriptions, causes/packages/campaigns, analytics, reports, checkout recovery, branding, RBAC

## Stack

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.2, Laravel 12 |
| Auth | Session (admin) + Spatie Permission; Sanctum for `/api/user` |
| Payments | Razorpay SDK; Danamojo API sync |
| Public UI | Blade + vanilla JS (`public/js/donate.js`, `donation-razorpay.js`) |
| Admin UI | Inertia.js + Vue 3 + Tailwind CSS v4 + shadcn-vue |
| Build | Vite 7 |
| PDF / mail | barryvdh/laravel-dompdf, queued receipt emails |
| Tests | Pest 3 |

Timezone: `Asia/Kolkata`.

## Quick start

```bash
composer setup
# or manually:
cp .env.example .env
php artisan key:generate
# configure MySQL (DB_DATABASE=sadbhavnadonation) and Razorpay keys
php artisan migrate --seed
npm install && npm run build
```

Run the full local stack (HTTP server, queue worker, Vite):

```bash
composer dev
```

Or separately:

```bash
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

Production also needs the scheduler (`* * * * * php artisan schedule:run`). Example nginx/supervisor configs live under `deploy/`.

### Default admin (after seed)

- Email: `info@sadbhavnadham.org`
- Password: `Admin@12345`

Change this immediately in production.

## Environment

Copy `.env.example`. Important groups:

| Group | Keys |
|-------|------|
| App / DB | `APP_URL`, `DB_*`, `QUEUE_CONNECTION`, `SESSION_*` |
| Branding | `BRAND_*` (name, logos, bank, contact, social) |
| Razorpay | `RAZORPAY_KEY`, `RAZORPAY_SECRET`, `WEBHOOK_SECRET`, subscription flags |
| WordPress | `WP_API_TOKEN` |
| Google Sheets | `GOOGLE_CLIENT_*`, `GOOGLE_SHEET_ID` |
| Danamojo | `DANAMOJO_*` |
| AiSensy | `AISENSY_*` (prefer admin AiSensy Accounts) |
| Certificates | `DONATION_CERTIFICATE_*`, `PDFTOPPM_BINARY` |

Webhook URL: `POST /api/webhook/razorpay` (signature verified with `WEBHOOK_SECRET`).

For WordPress iframe embeds, production often needs `SESSION_SAME_SITE=none` over HTTPS.

## Project structure

```
app/
  Console/Commands/     Artisan: reconcile, sync, certificates, rollups
  Http/Controllers/     Public donate + Webhook; Admin/* portal
  Http/Middleware/      Branding, WP embed/token, admin portal
  Http/Requests/        Form validation (public + Admin/)
  Jobs/                 Queued receipt, WhatsApp, sheets, campaigns
  Mail/                 DonationReceiptMail
  Models/               Orders, donors, causes, analytics, WhatsApp…
  Policies/             DonationOrderPolicy
  Services/             Payments, Razorpay, AiSensy, Danamojo, analytics…
  Support/              Branding, SEO, admin Inertia data helpers
config/                 branding, donation, payments, danamojo, analytics…
database/               migrations, seeders, factories
deploy/                 nginx + supervisor examples
public/js/              Donate + Razorpay checkout scripts
resources/
  views/donate/         Public Blade pages
  js/Pages/Admin/       Inertia Vue admin screens
routes/                 web.php, api.php, console.php
scripts/wordpress/      MU-plugin for Forminator bridge
tests/                  Pest Feature + Unit
```

## Domain model (core)

| Model | Role |
|-------|------|
| `DonationOrder` | Primary donation record (UUID route key); providers: razorpay, razorpay_qr, cashfree, danamojo, offline |
| `DonationItem` | Line items (cause / package / campaign) |
| `Donor` | Unique email+phone; CRM owner, notes, tasks, WhatsApp opt-out |
| `Cause` / `CausePackage` | Causes and amount packages; recurring & PAN flags |
| `DonationCampaign` | Public `/give/{slug}` goals and schedules |
| `DonationSubscription` | Razorpay recurring mandates |
| `RazorpayPlan` | Cached Razorpay plans |
| `PaymentEvent` | Webhook audit log |
| `Setting` | Feature toggles (receipts, WA, sheets, retries) |
| `AisensyAccount` / templates / campaign runs | WhatsApp delivery |
| `AnalyticsEvent` + daily rollup models | Visits, checkout, paid/failed |

`Donation` is a legacy stub; active flow uses `DonationOrder`.

## Public routes

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/` | Cause list |
| GET | `/donate/{cause}` | Cause donate page |
| GET | `/give/{campaign}` | Campaign landing |
| GET | `/bank-details` | Bank transfer info |
| POST | `/donate/razorpay` | Create one-time Razorpay order |
| POST | `/donate/razorpay/subscription` | Start recurring checkout |
| POST | `/donate/pan-requirement` | PAN threshold check |
| GET | `/donate/thank-you/{order}` | Signed thank-you |
| GET | `/donate/danamojo/{cause?}` | Danamojo redirect/widget |
| POST | `/presence/heartbeat` | Live visitor tracking |
| GET | `/sitemap.xml`, `/robots.txt` | SEO |

## API routes

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/webhook/razorpay` | Razorpay signature |
| POST | `/api/wp-razorpay` | `X-WP-TOKEN` |
| POST | `/api/wp-pan-requirement` | `X-WP-TOKEN` |
| GET | `/api/user` | Sanctum |

## Admin portal (`/admin`)

Session login at `/admin/login`. Access gated by Spatie permissions (`admin.portal` and finer gates).

Areas include:

- Dashboard + live visitors
- Analytics / reports (export)
- Causes, packages, campaigns CRUD
- Donations (list, export, offline create, edit, receipt preview/print/resend, delivery retries)
- Checkout recovery (WhatsApp payment-link nudge)
- Subscriptions (view, cancel, sync)
- Donors CRM (notes, tasks, owner, CSV import/export, opt-out)
- AiSensy accounts + broadcast WhatsApp campaigns
- Settings toggles + branding overrides
- Users / roles / permissions
- Google OAuth for Sheets (settings permission)

Roles (seeded): `super_admin`, `admin`, `accountant`, `manager`, `user` (receipt clerk). Super admin bypasses all gates.

## Key controllers

**Public**

- `DonatePageController` — index, cause, campaign, bank, thank-you
- `DonationController` — Razorpay order/subscription, PAN, Danamojo, WP API
- `WebhookController` — Razorpay events (payment, QR, subscription)
- `PresenceController`, `GoogleOAuthController`, `SitemapController`, `RobotsController`

**Admin** (`app/Http/Controllers/Admin/`)

- Auth, dashboard, analytics, reports
- Causes / packages / campaigns
- Donations, receipts, delivery resends, checkout recovery
- Subscriptions, donors + CRM
- AiSensy accounts, WhatsApp campaigns
- Settings, branding, users/roles/permissions

## Services & jobs

**Services** (selection): `DonationPaymentService`, `RazorpaySubscriptionService`, `DonationSubscriptionWebhookService`, `RazorpayQrPaymentService`, `DonationReceiptPdfService`, `DonationCertificateService`, `BirthdayImageService`, `AiSensyService`, WhatsApp campaign launcher/sync, `GoogleSheetsLogger`, Danamojo client/importer, analytics + geo, attribution, postal lookup, donor CSV import.

**Queued jobs**: receipt email, thank-you / certificate / payment-link / birthday WhatsApp, payment link create, sheet log/update, WhatsApp campaign run/recipients.

## Scheduled commands

| Schedule | Command |
|----------|---------|
| Every 15 min | `donations:reconcile`, `analytics:rollup` |
| Daily 00:15 | `analytics:rollup --yesterday` |
| Daily 09:00 | `donors:send-birthday-whatsapp` |
| Hourly | `danamojo:sync`, `aisensy:sync-templates` |

Other Artisan helpers: `donations:certificate`, `donations:send-whatsapp`, sheet/attribution backfills, legacy import, `razorpay:sync-plans`, birthday image preview.

## Seeded causes (examples)

Old Age Home, Tree Plantation, Animal Hospital, and related ashram causes — see `database/seeders/CauseSeeder.php` for packages and amounts.

PAN is required when a cause enables it and the amount / FY total meets `DONATION_PAN_THRESHOLD_INR` (default ₹1,00,000).

## Testing

```bash
php artisan test --compact
# or
composer test

# Critical regression subset:
composer test:regression-critical
```

Feature coverage includes donate pages, recurring checkout, webhooks, admin RBAC, receipts, donors CRM, WhatsApp campaigns, Danamojo, analytics, and WordPress API. Unit tests cover validation rules, subscriptions, branding, receipt numbers, and sheets logging.

After changing PHP style, run:

```bash
vendor/bin/pint --dirty
```

## WordPress integration

See `scripts/wordpress/` for the MU-plugin that bridges Forminator forms to this app’s WP API (`/api/wp-razorpay`). Pass token via `X-WP-TOKEN` matching `WP_API_TOKEN`.

## Production notes

1. Set strong `APP_KEY`, DB credentials, and rotate the seeded admin password.
2. Configure Razorpay live keys and webhook secret; point webhook to `/api/webhook/razorpay`.
3. Run queue workers and the Laravel scheduler (see `deploy/`).
4. Build front-end assets: `npm run build` (Vite output under `public/vite`).
5. For certificates/WhatsApp media, set a public HTTPS base (`DONATION_CERTIFICATE_PUBLIC_BASE_URL`) reachable by AiSensy.
6. Optional: Google OAuth for Sheets, Danamojo keys, AiSensy accounts in admin.

## License

Application code is project-specific for Manav Seva Cheritable Trust / Sadbhavna. Laravel framework components remain under the [MIT license](https://opensource.org/licenses/MIT).
