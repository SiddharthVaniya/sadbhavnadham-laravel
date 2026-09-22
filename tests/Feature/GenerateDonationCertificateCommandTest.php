<?php

use App\Models\DonationOrder;
use App\Services\DonationCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeCertificateCommandOrder(array $overrides = []): DonationOrder
{
    return DonationOrder::create(array_merge([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_artisan_cert_'.uniqid(),
        'donor_name' => 'વિજયભાઈ ડોબરીયા',
        'donor_email' => 'donor@example.com',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 1500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => Carbon::create(2026, 5, 26),
    ], $overrides));
}

it('generates a certificate from the artisan command', function () {
    Storage::fake('public');

    $order = makeCertificateCommandOrder(['state' => 'Gujarat']);
    $template = (string) config('donation.certificate.template');

    if (! is_file($template)) {
        $this->markTestSkipped('Global certificate template is not available.');
    }

    $this->artisan('donations:certificate', ['order' => $order->id])
        ->assertSuccessful()
        ->expectsOutputToContain('Certificate generated successfully.')
        ->expectsOutputToContain('વિજયભાઈ ડોબરીયા')
        ->expectsOutputToContain('Locale: gu')
        ->expectsOutputToContain('તારીખ : 26-05-2026');
});

it('uses english certificate locale for non-gujarat donors', function () {
    $order = makeCertificateCommandOrder([
        'donor_name' => 'Ravi Sharma',
        'state' => 'Maharashtra',
    ]);
    $service = app(DonationCertificateService::class);

    expect($service->certificateLocale($order))->toBe('en')
        ->and($service->formattedDateLine($order))->toBe('Date : 26-05-2026');
});
