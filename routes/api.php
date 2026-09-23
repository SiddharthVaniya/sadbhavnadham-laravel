<?php

/**
 * JSON API for the Next.js frontend at
 * /home/sadbhavnadham/htdocs/sadbhavnadham.org
 * (https://sadbhavnadham.org → https://admin.sadbhavnadham.org/api/...).
 *
 * Add new public/token APIs in this file, not in Next.js.
 */

use App\Http\Controllers\DonateApiController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\EnsureThankYouAccess;
use App\Http\Middleware\EnsureWordPressApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhook/razorpay', [WebhookController::class, 'razorpay']);

Route::post('/wp-razorpay', [DonationController::class, 'wpRazorpay'])->middleware([EnsureWordPressApiToken::class, 'throttle:donate-checkout']);
Route::post('/wp-razorpay/subscription', [DonationController::class, 'wpRazorpaySubscription'])->middleware([EnsureWordPressApiToken::class, 'throttle:donate-checkout']);
Route::post('/wp-pan-requirement', [DonationController::class, 'panRequirement'])->middleware([EnsureWordPressApiToken::class, 'throttle:30,1']);

Route::prefix('donate')->group(function (): void {
    Route::get('/causes', [DonateApiController::class, 'causes'])->name('donate.api.causes');
    Route::get('/causes/{slug}', [DonateApiController::class, 'cause'])->name('donate.api.cause');
    Route::get('/config', [DonateApiController::class, 'config'])->name('donate.api.config');
    Route::get('/pincode/{pincode}', [DonateApiController::class, 'pincode'])->middleware('throttle:60,1')->name('donate.api.pincode');
    Route::get('/bank-details', [DonateApiController::class, 'bankDetails'])->name('donate.api.bank-details');

    Route::get('/thank-you/subscription/{subscription}', [DonateApiController::class, 'thankYouSubscription'])
        ->middleware(EnsureThankYouAccess::class)
        ->name('donate.api.thank-you.subscription');
    Route::get('/thank-you/{order}', [DonateApiController::class, 'thankYou'])
        ->middleware(EnsureThankYouAccess::class)
        ->name('donate.api.thank-you');

    Route::post('/pan-requirement', [DonateApiController::class, 'panRequirement'])->middleware('throttle:30,1')->name('donate.api.pan-requirement');
    Route::post('/track', [DonateApiController::class, 'track'])->middleware('throttle:60,1')->name('donate.api.track');
    Route::post('/danamojo/notify', [DonateApiController::class, 'danamojoNotify'])->middleware('throttle:30,1')->name('donate.api.danamojo.notify');
    Route::post('/otp/send', [DonateApiController::class, 'sendOtp'])->middleware('throttle:donor-otp-send')->name('donate.api.otp.send');
    Route::post('/otp/verify', [DonateApiController::class, 'verifyOtp'])->middleware('throttle:donor-otp-verify')->name('donate.api.otp.verify');

    Route::get('/me', [DonateApiController::class, 'me'])->middleware('auth:sanctum')->name('donate.api.me');
    Route::post('/logout', [DonateApiController::class, 'logout'])->middleware('auth:sanctum')->name('donate.api.logout');

    Route::post('/checkout', [DonateApiController::class, 'checkout'])->middleware([EnsureWordPressApiToken::class, 'throttle:donate-checkout'])->name('donate.api.checkout');
    Route::post('/checkout/subscription', [DonateApiController::class, 'checkoutSubscription'])->middleware([EnsureWordPressApiToken::class, 'throttle:donate-checkout'])->name('donate.api.checkout.subscription');
});
