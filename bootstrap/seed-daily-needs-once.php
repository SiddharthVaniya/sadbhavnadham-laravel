<?php

use App\Models\Cause;
use App\Models\DonationItem;
use Database\Seeders\CauseSeeder;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

(new CauseSeeder)->seedDailyNeedsOnly();

$cause = Cause::query()->where('slug', Cause::SLUG_DAILY_NEEDS)->first();
echo 'cause: '.($cause?->id).' '.($cause?->title).PHP_EOL;

$item = DonationItem::query()
    ->whereHas('order', fn ($query) => $query->where('provider_order_id', 'order_TULOPS8Fkrxe6V'))
    ->first();

echo 'item: '.($item?->cause).' '.($item?->causeModel?->title).' '.($item?->title).PHP_EOL;
echo 'updated: '.DonationItem::query()->where('cause', Cause::SLUG_DAILY_NEEDS)->count().PHP_EOL;
