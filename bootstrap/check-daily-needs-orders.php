<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$orders = App\Models\DonationOrder::query()
    ->whereHas('items', fn ($q) => $q->where('cause', 'daily-needs'))
    ->latest()
    ->take(5)
    ->get(['id', 'donor_email', 'status', 'receipt_sent_at', 'receipt_failed_at', 'receipt_last_error', 'paid_at', 'created_at']);

echo $orders->toJson(JSON_PRETTY_PRINT).PHP_EOL;
