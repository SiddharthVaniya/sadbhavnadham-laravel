# Admin Razorpay QR Codes (Phase 1) — Design

**Date:** 2026-09-24  
**Status:** Approved (Approach 2 — local registry + Razorpay API)

## Goal

Add a Finance sidebar **QR Codes** tab (like Subscriptions) so staff can **list, create, close, and sync** Razorpay UPI QR codes from admin, with QR image display.

## Non-goals (Phase 2)

- Auto-map QR → cause/package on payment
- Print templates / station labels productization
- Analytics dashboards beyond counts returned by Razorpay

## Architecture

- Local table `razorpay_qr_codes` mirrors Razorpay QR entities for fast admin UX.
- `RazorpayQrCodeService` calls Razorpay SDK (`qrCode->create|all|fetch|close`) and upserts local rows.
- Ingest whitelist (`RAZORPAY_QR_IDS`) is **merged** with locally **active** QR ids so newly created QRs are accepted by webhook/reconcile without editing `.env`.
- If both env list and local active ids are empty → keep current behaviour (accept any QR payment).

## Admin UX

| Screen | Behaviour |
|--------|-----------|
| Index | Tabs Active / Closed / All; search by name/id; Create button; Sync all |
| Create | Name, description, usage (`multiple_use` default), fixed amount toggle + amount |
| Show | Image, status, Razorpay id, counts, Close, Sync; link to donations filtered `provider=razorpay_qr` (best-effort) |

## Permissions

- `view qr codes`
- `create qr codes`
- `close qr codes`
- `sync qr codes`
- `manage qr codes` (legacy bundle → create/close/sync)

## Data

See migration / `alter.md`. Route key: `qr_uuid`.

## Testing

Feature tests with mocked `RazorpayQrCodeService` / Razorpay API wrapper for create, close, sync, list auth, and whitelist merge.
