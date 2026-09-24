# Admin Razorpay QR Codes Implementation Plan

> **For agentic workers:** Implement task-by-task. Steps use checkbox syntax.

**Goal:** Finance admin module to manage Razorpay UPI QR codes (list/create/close/sync) with local registry.

**Architecture:** `razorpay_qr_codes` table + `RazorpayQrCodeService` (SDK) + Inertia pages mirroring Subscriptions; whitelist merges env + active local ids.

**Tech Stack:** Laravel 12, Razorpay PHP SDK, Inertia Vue, Pest, Spatie permissions.

## Global Constraints

- Do not run migrations; write migration file + `alter.md` SQL.
- Follow existing AdminSubscriptions patterns for nav/permissions/Inertia.
- Default create: `type=upi_qr`, `usage=multiple_use`, `fixed_amount=false`.

---

### Task 1: Schema + model + factory

- [ ] Migration `razorpay_qr_codes`
- [ ] Model `RazorpayQrCode` + factory
- [ ] Append CREATE TABLE to `alter.md`

### Task 2: Service

- [ ] `RazorpayQrCodeService`: create, close, syncOne, syncAll, upsertFromEntity, api()
- [ ] Unit/feature tests with mocked Api

### Task 3: Whitelist merge

- [ ] Update `RazorpayQrPaymentService::configuredQrCodeIds()` to merge active local ids
- [ ] Test merge behaviour

### Task 4: Permissions + nav + routes + controller

- [ ] AdminPermissions + seeder + navigation
- [ ] Form requests Store/Close
- [ ] `AdminRazorpayQrCodeController`
- [ ] Routes under admin

### Task 5: Inertia UI

- [ ] `Admin/QrCodes/Index.vue`, `Create.vue`, `Show.vue`
- [ ] Resource mappers in `AdminInertiaResources`

### Task 6: Feature tests

- [ ] Index/create/close/sync authorization + happy paths
