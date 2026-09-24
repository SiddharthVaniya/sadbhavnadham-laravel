<?php

use App\Http\Controllers\Admin\AdminAnalyticsController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminBirthdayMessageController;
use App\Http\Controllers\Admin\AdminBrandingController;
use App\Http\Controllers\Admin\AdminCauseController;
use App\Http\Controllers\Admin\AdminCausePackageController;
use App\Http\Controllers\Admin\AdminCheckoutRecoveryController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDonationCampaignController;
use App\Http\Controllers\Admin\AdminDonationController;
use App\Http\Controllers\Admin\AdminDonorController;
use App\Http\Controllers\Admin\AdminDonorCrmController;
use App\Http\Controllers\Admin\AdminMarketerBudgetController;
use App\Http\Controllers\Admin\AdminPartnerReportsController;
use App\Http\Controllers\Admin\AdminRazorpayQrCodeController;
use App\Http\Controllers\Admin\AdminReportsController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminStaffReferralsController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\AisensyAccountController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DonationDeliveryController;
use App\Http\Controllers\Admin\DonationReceiptController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Admin\WhatsappCampaignController;
use App\Http\Controllers\DonatePageController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\DonorOtpController;
use App\Http\Controllers\DonorPortalController;
use App\Http\Controllers\GoogleOAuthController;
use App\Http\Controllers\Marketer\MarketerCampaignController;
use App\Http\Controllers\Marketer\MarketerDashboardController;
use App\Http\Controllers\Marketer\MarketerDonationController;
use App\Http\Controllers\Marketer\MarketerVisitController;
use App\Http\Controllers\ReceiptDemoController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StaffReferralController;
use App\Support\AdminPermissions;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', RobotsController::class)->name('seo.robots');
Route::get('/sitemap.xml', SitemapController::class)->name('seo.sitemap');
Route::get('/receipts/demo', [ReceiptDemoController::class, 'show'])->name('receipts.demo');

Route::get('/', [DonatePageController::class, 'index'])->name('donate.index');
Route::get('/bank-details', [DonatePageController::class, 'bankDetails'])->name('donate.bank-details');
Route::get('/donate/thank-you/subscription/{subscription}', [DonatePageController::class, 'thankYouSubscription'])
    ->middleware('signed')
    ->name('donate.thank-you.subscription');
Route::get('/donate/thank-you/{order}', [DonatePageController::class, 'thankYou'])
    ->middleware('signed')
    ->name('donate.thank-you');
Route::get('/donate/danamojo/{causeSlug?}', [DonationController::class, 'danamojoRedirect'])->name('donate.danamojo');
Route::get('/donate/danamojo-widget/{causeSlug?}', [DonationController::class, 'danamojoWidget'])->name('donate.danamojo.widget');
Route::get('/donate/{cause:slug}', [DonatePageController::class, 'show'])->name('donate.show');
Route::get('/give/{campaign:slug}', [DonatePageController::class, 'campaign'])->name('donate.campaign');

Route::post('/donate/pan-requirement', [DonationController::class, 'panRequirement'])->name('donate.pan-requirement')->middleware('throttle:30,1');
Route::post('/donate/otp/send', [DonorOtpController::class, 'send'])
    ->name('donate.otp.send')
    ->middleware('throttle:donor-otp-send');
Route::post('/donate/otp/verify', [DonorOtpController::class, 'verify'])
    ->name('donate.otp.verify')
    ->middleware('throttle:donor-otp-verify');
Route::get('/donate/otp/session', [DonorOtpController::class, 'session'])
    ->name('donate.otp.session')
    ->middleware('throttle:60,1');
Route::post('/donate/otp/logout', [DonorOtpController::class, 'logout'])
    ->name('donate.otp.logout')
    ->middleware('throttle:30,1');

Route::middleware('donor.portal')->group(function () {
    Route::get('/my-donations', [DonorPortalController::class, 'index'])->name('donate.portal.index');
    Route::get('/my-donations/{order}/receipt', [DonorPortalController::class, 'receipt'])->name('donate.portal.receipt');
});

Route::post('/donate/razorpay', [DonationController::class, 'razorpay'])->name('donate.razorpay')->middleware('throttle:20,1');
Route::post('/donate/razorpay/subscription', [DonationController::class, 'razorpaySubscription'])->name('donate.razorpay.subscription')->middleware('throttle:20,1');

Route::middleware(['auth', AdminPermissions::middleware(AdminPermissions::SETTINGS_EDIT)])->group(function () {
    Route::get('/google-auth', [GoogleOAuthController::class, 'redirectToGoogle']);
    Route::get('/oauth2callback', [GoogleOAuthController::class, 'handleGoogleCallback']);
});

Route::prefix('admin')->group(function () {
    Route::get('login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AdminAuthController::class, 'login'])->name('admin.login.submit')->middleware('throttle:5,1');
    Route::post('logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
});

Route::prefix('marketer')->name('marketer.')->middleware(['auth', 'marketer.portal'])->group(function () {
    Route::get('/', [MarketerDashboardController::class, 'index'])->name('dashboard');
    Route::get('/export', [MarketerDashboardController::class, 'export'])->name('dashboard.export');
    Route::get('/donations', [MarketerDonationController::class, 'index'])->name('donations');
    Route::get('/donations/export', [MarketerDonationController::class, 'export'])->name('donations.export');
    Route::get('/campaigns', [MarketerCampaignController::class, 'index'])->name('campaigns');
    Route::get('/campaigns/export', [MarketerCampaignController::class, 'export'])->name('campaigns.export');
    Route::get('/visits', [MarketerVisitController::class, 'index'])->name('visits');
    Route::get('/visits/export', [MarketerVisitController::class, 'export'])->name('visits.export');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin.portal'])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/analytics', [AdminAnalyticsController::class, 'index'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::ANALYTICS_VIEW))
        ->name('analytics.index');
    Route::get('/analytics/export', [AdminAnalyticsController::class, 'export'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::ANALYTICS_EXPORT))
        ->name('analytics.export');

    Route::get('/referrals', [AdminStaffReferralsController::class, 'index'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::REFERRAL_VIEW))
        ->name('referrals.index');

    Route::resource('causes', AdminCauseController::class)->except(['show']);
    Route::patch('causes/{cause}/toggle-active', [AdminCauseController::class, 'toggleActive'])->name('causes.toggle-active');
    Route::patch('causes/{cause}/reorder', [AdminCauseController::class, 'reorder'])->name('causes.reorder');
    Route::resource('campaigns', AdminDonationCampaignController::class);
    Route::patch('campaigns/{campaign}/toggle-active', [AdminDonationCampaignController::class, 'toggleActive'])->name('campaigns.toggle-active');
    Route::get('aisensy-accounts', [AisensyAccountController::class, 'index'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::AISENSY_VIEW))
        ->name('aisensy-accounts.index');
    Route::get('aisensy-accounts/create', [AisensyAccountController::class, 'create'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::AISENSY_CREATE))
        ->name('aisensy-accounts.create');
    Route::post('aisensy-accounts', [AisensyAccountController::class, 'store'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::AISENSY_CREATE))
        ->name('aisensy-accounts.store');
    Route::get('aisensy-accounts/{aisensy_account}/edit', [AisensyAccountController::class, 'edit'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::AISENSY_EDIT))
        ->name('aisensy-accounts.edit');
    Route::put('aisensy-accounts/{aisensy_account}', [AisensyAccountController::class, 'update'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::AISENSY_EDIT))
        ->name('aisensy-accounts.update');
    Route::delete('aisensy-accounts/{aisensy_account}', [AisensyAccountController::class, 'destroy'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::AISENSY_DELETE))
        ->name('aisensy-accounts.destroy');
    Route::patch('aisensy-accounts/{aisensy_account}/toggle-active', [AisensyAccountController::class, 'toggleActive'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::AISENSY_EDIT))
        ->name('aisensy-accounts.toggle-active');

    Route::get('whatsapp-campaigns', [WhatsappCampaignController::class, 'index'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::WHATSAPP_BROADCAST_VIEW))
        ->name('whatsapp-campaigns.index');
    Route::get('whatsapp-campaigns/create', [WhatsappCampaignController::class, 'create'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::WHATSAPP_BROADCAST_CREATE))
        ->name('whatsapp-campaigns.create');
    Route::post('whatsapp-campaigns/preview-audience', [WhatsappCampaignController::class, 'previewAudience'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::WHATSAPP_BROADCAST_CREATE))
        ->name('whatsapp-campaigns.preview-audience');
    Route::post('whatsapp-campaigns', [WhatsappCampaignController::class, 'store'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::WHATSAPP_BROADCAST_CREATE))
        ->name('whatsapp-campaigns.store');
    Route::get('whatsapp-campaigns/{whatsapp_campaign}', [WhatsappCampaignController::class, 'show'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::WHATSAPP_BROADCAST_VIEW))
        ->name('whatsapp-campaigns.show');
    Route::post('whatsapp-campaigns/{whatsapp_campaign}/cancel', [WhatsappCampaignController::class, 'cancel'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::WHATSAPP_BROADCAST_MANAGE))
        ->name('whatsapp-campaigns.cancel');
    Route::post('whatsapp-campaigns/{whatsapp_campaign}/retry-failed', [WhatsappCampaignController::class, 'retryFailed'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::WHATSAPP_BROADCAST_MANAGE))
        ->name('whatsapp-campaigns.retry-failed');
    Route::post('whatsapp-campaigns/{whatsapp_campaign}/sync-delivery', [WhatsappCampaignController::class, 'syncDelivery'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::WHATSAPP_BROADCAST_MANAGE))
        ->name('whatsapp-campaigns.sync-delivery');
    Route::post('whatsapp-campaigns/sync-templates', [WhatsappCampaignController::class, 'syncTemplates'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::WHATSAPP_BROADCAST_MANAGE))
        ->name('whatsapp-campaigns.sync-templates');
    Route::post('whatsapp-campaigns/templates', [WhatsappCampaignController::class, 'storeTemplate'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::WHATSAPP_BROADCAST_MANAGE))
        ->name('whatsapp-campaigns.templates.store');

    Route::get('packages', [AdminCausePackageController::class, 'index'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::PACKAGE_VIEW))
        ->name('packages.index');
    Route::get('causes/{cause}/packages/create', [AdminCausePackageController::class, 'create'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::PACKAGE_CREATE))
        ->name('causes.packages.create');
    Route::post('causes/{cause}/packages', [AdminCausePackageController::class, 'store'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::PACKAGE_CREATE))
        ->name('causes.packages.store');
    Route::get('causes/{cause}/packages/{package}/edit', [AdminCausePackageController::class, 'edit'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::PACKAGE_EDIT))
        ->name('causes.packages.edit');
    Route::put('causes/{cause}/packages/{package}', [AdminCausePackageController::class, 'update'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::PACKAGE_EDIT))
        ->name('causes.packages.update');
    Route::delete('causes/{cause}/packages/{package}', [AdminCausePackageController::class, 'destroy'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::PACKAGE_DELETE))
        ->name('causes.packages.destroy');
    Route::patch('causes/{cause}/packages/{package}/toggle-active', [AdminCausePackageController::class, 'toggleActive'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::PACKAGE_EDIT))
        ->name('causes.packages.toggle-active');
    Route::patch('causes/{cause}/packages/{package}/toggle-default', [AdminCausePackageController::class, 'toggleDefault'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::PACKAGE_EDIT))
        ->name('causes.packages.toggle-default');

    Route::get('/donations/create', [AdminDonationController::class, 'create'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONATION_CREATE))
        ->name('donations.create');
    Route::post('/donations/pan-requirement', [AdminDonationController::class, 'panRequirement'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONATION_CREATE))
        ->name('donations.pan-requirement');
    Route::post('/donations/postal-lookup', [AdminDonationController::class, 'postalLookup'])
        ->middleware(AdminPermissions::middlewareAny([
            AdminPermissions::DONATION_CREATE,
            AdminPermissions::DONATION_EDIT,
        ]))
        ->name('donations.postal-lookup');
    Route::post('/donations', [AdminDonationController::class, 'store'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONATION_CREATE))
        ->name('donations.store');
    Route::get('/donations/{donationOrder}/edit', [AdminDonationController::class, 'edit'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONATION_EDIT))
        ->name('donations.edit');
    Route::put('/donations/{donationOrder}', [AdminDonationController::class, 'update'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONATION_EDIT))
        ->name('donations.update');

    Route::get('/reports', [AdminReportsController::class, 'index'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::REPORT_VIEW))
        ->name('reports.index');
    Route::get('/reports/export', [AdminReportsController::class, 'export'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::REPORT_EXPORT))
        ->name('reports.export');

    Route::get('/partner-reports', [AdminPartnerReportsController::class, 'index'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::REFERRAL_VIEW))
        ->name('partner-reports.index');

    Route::get('/donations', [AdminDonationController::class, 'index'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONATION_VIEW))
        ->name('donations.index');
    Route::get('/donations/export', [AdminDonationController::class, 'export'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONATION_EXPORT))
        ->name('donations.export');
    Route::get('/donations/offline', [AdminDonationController::class, 'offline'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONATION_VIEW))
        ->name('donations.offline');
    Route::get('/donations/recovery', [AdminCheckoutRecoveryController::class, 'index'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONATION_RECOVERY_VIEW))
        ->name('donations.recovery');
    Route::get('/donations/{donationOrder}', [AdminDonationController::class, 'show'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONATION_VIEW))
        ->name('donations.show');

    Route::get('/subscriptions', [AdminSubscriptionController::class, 'index'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::SUBSCRIPTION_VIEW))
        ->name('subscriptions.index');
    Route::get('/subscriptions/export', [AdminSubscriptionController::class, 'export'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::SUBSCRIPTION_EXPORT))
        ->name('subscriptions.export');
    Route::get('/subscriptions/{subscription}', [AdminSubscriptionController::class, 'show'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::SUBSCRIPTION_VIEW))
        ->name('subscriptions.show');

    Route::post('/subscriptions/{subscription}/cancel', [AdminSubscriptionController::class, 'cancel'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::SUBSCRIPTION_CANCEL))
        ->name('subscriptions.cancel');
    Route::post('/subscriptions/{subscription}/sync', [AdminSubscriptionController::class, 'sync'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::SUBSCRIPTION_SYNC))
        ->name('subscriptions.sync');

    Route::get('/qr-codes', [AdminRazorpayQrCodeController::class, 'index'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::QR_CODE_VIEW))
        ->name('qr-codes.index');
    Route::get('/qr-codes/create', [AdminRazorpayQrCodeController::class, 'create'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::QR_CODE_CREATE))
        ->name('qr-codes.create');
    Route::post('/qr-codes', [AdminRazorpayQrCodeController::class, 'store'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::QR_CODE_CREATE))
        ->name('qr-codes.store');
    Route::post('/qr-codes/sync', [AdminRazorpayQrCodeController::class, 'syncAll'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::QR_CODE_SYNC))
        ->name('qr-codes.sync-all');
    Route::get('/qr-codes/{qrCode}', [AdminRazorpayQrCodeController::class, 'show'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::QR_CODE_VIEW))
        ->name('qr-codes.show');
    Route::put('/qr-codes/{qrCode}', [AdminRazorpayQrCodeController::class, 'update'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::QR_CODE_CREATE))
        ->name('qr-codes.update');
    Route::post('/qr-codes/{qrCode}/close', [AdminRazorpayQrCodeController::class, 'close'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::QR_CODE_CLOSE))
        ->name('qr-codes.close');
    Route::post('/qr-codes/{qrCode}/sync', [AdminRazorpayQrCodeController::class, 'sync'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::QR_CODE_SYNC))
        ->name('qr-codes.sync');

    Route::get('/donors', [AdminDonorController::class, 'index'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONOR_VIEW))
        ->name('donors.index');
    Route::get('/donors/{donor}', [AdminDonorController::class, 'show'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONOR_VIEW))
        ->name('donors.show');
    Route::post('/donors/{donor}/whatsapp-opt-out', [AdminDonorController::class, 'updateWhatsappOptOut'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONOR_MANAGE_COMMUNICATION))
        ->name('donors.whatsapp-opt-out');
    Route::get('/donors/{donor}/export', [AdminDonorController::class, 'export'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONOR_EXPORT))
        ->name('donors.export');

    Route::get('/donors-export', [AdminDonorController::class, 'exportAll'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONOR_EXPORT))
        ->name('donors.export-all');
    Route::get('/donors-import/template', [AdminDonorController::class, 'importTemplate'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONOR_IMPORT))
        ->name('donors.import.template');
    Route::post('/donors-import', [AdminDonorController::class, 'import'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONOR_IMPORT))
        ->name('donors.import');

    Route::put('/donors/{donor}/owner', [AdminDonorCrmController::class, 'updateOwner'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONOR_CRM_OWNERS))
        ->name('donors.owner.update');
    Route::post('/donors/{donor}/notes', [AdminDonorCrmController::class, 'storeNote'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONOR_CRM_NOTES))
        ->name('donors.notes.store');
    Route::delete('/donors/{donor}/notes/{note}', [AdminDonorCrmController::class, 'destroyNote'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONOR_CRM_NOTES))
        ->name('donors.notes.destroy');
    Route::post('/donors/{donor}/tasks', [AdminDonorCrmController::class, 'storeTask'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONOR_CRM_TASKS))
        ->name('donors.tasks.store');
    Route::put('/donors/{donor}/tasks/{task}', [AdminDonorCrmController::class, 'updateTask'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONOR_CRM_TASKS))
        ->name('donors.tasks.update');
    Route::delete('/donors/{donor}/tasks/{task}', [AdminDonorCrmController::class, 'destroyTask'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DONOR_CRM_TASKS))
        ->name('donors.tasks.destroy');

    Route::post('/donations/recovery/nudge', [AdminCheckoutRecoveryController::class, 'nudge'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::RECEIPT_RECOVERY_NUDGE))
        ->name('donations.recovery.nudge');
    Route::get('donations/{order}/receipt/preview', [DonationReceiptController::class, 'preview'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::RECEIPT_PREVIEW))
        ->name('donations.receipt.preview');
    Route::get('/donations/{order}/receipt/print', [DonationReceiptController::class, 'print'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::RECEIPT_PRINT))
        ->name('donations.receipt.print');
    Route::post('/donations/{order}/receipt/resend', [DonationReceiptController::class, 'resend'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::RECEIPT_RESEND))
        ->name('donations.receipt.resend');
    Route::post('/donations/{order}/receipt/generate', [DonationReceiptController::class, 'generate'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::RECEIPT_GENERATE))
        ->name('donations.receipt.generate');
    Route::post('/donations/{order}/sheet/resend', [DonationDeliveryController::class, 'resendSheet'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::RECEIPT_RESEND_NOTIFICATIONS))
        ->name('donations.sheet.resend');
    Route::post('/donations/{order}/whatsapp/thank-you', [DonationDeliveryController::class, 'resendThankYouWhatsApp'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::RECEIPT_RESEND_NOTIFICATIONS))
        ->name('donations.whatsapp.thank-you');
    Route::post('/donations/{order}/whatsapp/certificate', [DonationDeliveryController::class, 'resendCertificateWhatsApp'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::RECEIPT_RESEND_NOTIFICATIONS))
        ->name('donations.whatsapp.certificate');
    Route::post('/donations/{order}/whatsapp/receipt', [DonationDeliveryController::class, 'resendReceiptWhatsApp'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::RECEIPT_RESEND_NOTIFICATIONS))
        ->name('donations.whatsapp.receipt');
    Route::post('/donations/{order}/whatsapp/payment-link', [DonationDeliveryController::class, 'resendPaymentLinkWhatsApp'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::RECEIPT_RESEND_NOTIFICATIONS))
        ->name('donations.whatsapp.payment-link');
    Route::post('/donations/{order}/payment-link/notify/{medium}', [DonationDeliveryController::class, 'notifyPaymentLink'])
        ->whereIn('medium', ['email', 'sms'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::RECEIPT_RESEND_NOTIFICATIONS))
        ->name('donations.payment-link.notify');

    Route::get('roles', [RoleController::class, 'index'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::ROLE_MANAGE))
        ->name('roles.index');
    Route::get('roles/create', [RoleController::class, 'create'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::ROLE_MANAGE))
        ->name('roles.create');
    Route::post('roles', [RoleController::class, 'store'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::ROLE_MANAGE))
        ->name('roles.store');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::ROLE_MANAGE))
        ->name('roles.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::ROLE_MANAGE))
        ->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::ROLE_MANAGE))
        ->name('roles.destroy');

    Route::get('permissions', [PermissionController::class, 'index'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::ROLE_MANAGE))
        ->name('permissions.index');
    Route::get('permissions/create', [PermissionController::class, 'create'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::ROLE_MANAGE))
        ->name('permissions.create');
    Route::post('permissions', [PermissionController::class, 'store'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::ROLE_MANAGE))
        ->name('permissions.store');
    Route::get('permissions/{permission}/edit', [PermissionController::class, 'edit'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::ROLE_MANAGE))
        ->name('permissions.edit');
    Route::put('permissions/{permission}', [PermissionController::class, 'update'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::ROLE_MANAGE))
        ->name('permissions.update');
    Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::ROLE_MANAGE))
        ->name('permissions.destroy');

    Route::get('departments', [DepartmentController::class, 'index'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DEPARTMENT_MANAGE))
        ->name('departments.index');
    Route::get('departments/create', [DepartmentController::class, 'create'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DEPARTMENT_MANAGE))
        ->name('departments.create');
    Route::post('departments', [DepartmentController::class, 'store'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DEPARTMENT_MANAGE))
        ->name('departments.store');
    Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DEPARTMENT_MANAGE))
        ->name('departments.edit');
    Route::put('departments/{department}', [DepartmentController::class, 'update'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DEPARTMENT_MANAGE))
        ->name('departments.update');
    Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::DEPARTMENT_MANAGE))
        ->name('departments.destroy');

    Route::get('users', [UserController::class, 'index'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::USER_VIEW))
        ->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::USER_CREATE))
        ->name('users.create');
    Route::post('users', [UserController::class, 'store'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::USER_CREATE))
        ->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::USER_EDIT))
        ->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::USER_EDIT))
        ->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::USER_DELETE))
        ->name('users.destroy');
    Route::post('users/{userId}/restore', [UserController::class, 'restore'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::USER_RESTORE))
        ->name('users.restore');
    Route::get('users/{user}/roles', [UserRoleController::class, 'edit'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::ROLE_MANAGE))
        ->name('users.roles.edit');
    Route::put('users/{user}/roles', [UserRoleController::class, 'update'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::ROLE_MANAGE))
        ->name('users.roles.update');

    Route::get('marketers', [AdminMarketerBudgetController::class, 'index'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::USER_EDIT))
        ->name('marketers.index');
    Route::put('marketers', [AdminMarketerBudgetController::class, 'update'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::USER_EDIT))
        ->name('marketers.update');

    Route::get('settings', [AdminSettingController::class, 'index'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::SETTINGS_VIEW))
        ->name('settings.index');
    Route::get('settings/branding', [AdminBrandingController::class, 'edit'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::SETTINGS_BRANDING))
        ->name('settings.branding.edit');
    Route::post('settings/branding', [AdminBrandingController::class, 'update'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::SETTINGS_BRANDING))
        ->name('settings.branding.update');
    Route::patch('settings/{setting}/toggle', [AdminSettingController::class, 'toggle'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::SETTINGS_EDIT))
        ->name('settings.toggle');
    Route::patch('settings/{setting}', [AdminSettingController::class, 'update'])
        ->middleware(AdminPermissions::middleware(AdminPermissions::SETTINGS_EDIT))
        ->name('settings.update');

    Route::get('birthday-messages', [AdminBirthdayMessageController::class, 'index'])
        ->name('birthday-messages.index');
    Route::post('birthday-messages', [AdminBirthdayMessageController::class, 'update'])
        ->name('birthday-messages.update');

});

Route::get('/{referral}', [StaffReferralController::class, 'home'])
    ->where('referral', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('staff.referral.home');
Route::get('/{referral}/donate/{cause:slug}', [StaffReferralController::class, 'cause'])
    ->where('referral', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('staff.referral.cause');
Route::get('/{referral}/give/{campaign:slug}', [StaffReferralController::class, 'campaign'])
    ->where('referral', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('staff.referral.campaign');
