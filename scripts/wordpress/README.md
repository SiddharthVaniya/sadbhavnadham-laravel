# WordPress → Laravel donation checkout (Forminator + API)

No iframe. WordPress keeps its own header/footer. Payment still goes through Laravel + Razorpay, so admin donations / receipts / WhatsApp keep working.

## Flow

1. Donor fills Forminator form on `sadbhavnadham.org`
2. Browser JS posts to **WordPress AJAX proxy** (token stays on server)
3. Proxy calls `POST https://donate.sadbhavnadham.org/api/wp-razorpay`
4. Laravel creates pending `donation_orders` + Razorpay order
5. Browser opens Razorpay Checkout
6. Razorpay webhook marks order paid in Laravel

## Laravel checklist

1. Set a strong `WP_API_TOKEN` in donate `.env` (not the placeholder).
2. Deploy the latest code (WordPress channel + landing path support).
3. Confirm CORS already allows `https://sadbhavnadham.org` (see `config/cors.php`).

## WordPress checklist

### 1) `wp-config.php` (above “That’s all, stop editing!”)

```php
define('SADBHAVNA_DONATE_API_URL', 'https://donate.sadbhavnadham.org/api/wp-razorpay');
define('SADBHAVNA_DONATE_API_TOKEN', 'paste-same-token-as-laravel-WP_API_TOKEN');
define('SADBHAVNA_DONATE_BRAND_NAME', 'Sadbhavna');
```

### 2) Install PHP proxy + JS

Copy **contents** of `scripts/wordpress/mu-plugin/` into `wp-content/mu-plugins/`:

```text
wp-content/mu-plugins/sadbhavna-donate-api.php
wp-content/mu-plugins/assets/sadbhavna-forminator-donate.js
```

(Create `mu-plugins` if missing. Only PHP files directly in `mu-plugins/` auto-load — do not nest another folder.)

### 3) Forminator form

Create/publish your Donation Form with fields. Note each field **ID** (Forminator shows e.g. `name-1`, `email-1`, `phone-1`, `number-1`, `textarea-1`).

Required for Laravel validation:

| Purpose | Example Forminator ID | Laravel key |
|---------|----------------------|-------------|
| Full name | `name-1` | `donor_name` |
| Email | `email-1` | `donor_email` |
| Phone (10 digits) | `phone-1` | `donor_phone` |
| Amount | `number-1` | `amount` |
| Address | `textarea-1` | `address` |
| Pincode (6 digits) | `text-1` | `pincode` |
| City | `text-2` | `city` |
| State | `text-3` | `state` |
| Consent checkbox | `consent-1` | `consent_indian_citizen` |
| PAN (optional) | `text-4` | `pan_number` |

Also add a **hidden** field for cause slug (e.g. `hidden-1` = `old-age-home`), or set cause in the page config below.

In Forminator → **Behavior**: prefer turning off “Enable AJAX” for this form (our script handles submit). If AJAX stays on, the script still intercepts `submit` with capture.

Do **not** use Forminator → Integrations → Webhook for payment.

### 4) Page HTML (Elementor HTML widget) — below the form

Your current Forminator IDs:

| Label | ID |
|-------|----|
| Name | `name-1` |
| Email | `email-1` |
| Phone | `phone-1` |
| Address | `address-1` |
| Donation Options | `radio-1` (500 / 1000 / Other) |
| Other Amount | `currency-1` |
| Calculations | `calculation-1` |
| Consent | `consent-1` |
| PAN (add Text field) | e.g. `text-1` → `pan_number` |

```html
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
  window.SadbhavnaDonateConfig = {
    formId: 22550,
    cause: 'old-age-home',
    utmCampaign: 'wp-old-age-home',
    fields: {
      donor_name: 'name-1',
      donor_email: 'email-1',
      donor_phone: 'phone-1',
      address: 'address-1',
      amount: 'radio-1',
      amount_other: 'currency-1',
      calculation: 'calculation-1',
      pan_number: 'text-1',
      consent_indian_citizen: 'consent-1'
    }
  };
</script>
```

**PAN rules (same as Laravel):**

- Below ₹1,00,000 → optional  
- ₹1,00,000+ **or** FY total + this gift ≥ ₹1,00,000 → required  
- Uses Laravel `/api/wp-pan-requirement` (FY-aware), not only browser amount  

In Forminator: add **Text** field label `PAN Number`, optional by default; our script enforces when required. Set ID in config (`text-1` or whatever badge shows).

Change only `formId`, `cause` (Laravel slug), and `utmCampaign` per page.

The MU plugin already enqueues `sadbhavna-forminator-donate.js`.

Phone = **10-digit Indian**. PIN = **6 digits**. Country preferably **India**.

### 5) Test

1. Submit a small live/test payment.
2. Razorpay popup must open.
3. After pay → thank-you URL from Laravel.
4. Admin → Donations → status **paid**, `source_channel` ≈ **wordpress**.

## Security notes

- Never put `WP_API_TOKEN` / `SADBHAVNA_DONATE_API_TOKEN` in browser JS.
- Proxy only accepts same-origin WordPress AJAX + nonce.
- Rotate the token if it was ever pasted into Forminator Webhook UI.
