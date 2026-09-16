<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('can rerun the razorpay plans unique index migration when cause_id foreign key is missing', function () {
    $foreign = collect(Schema::getForeignKeys('razorpay_plans'))
        ->first(fn (array $foreignKey): bool => in_array('cause_id', $foreignKey['columns'] ?? [], true));

    if ($foreign && ! empty($foreign['name'])) {
        try {
            Schema::table('razorpay_plans', function (Blueprint $table) use ($foreign) {
                $table->dropForeign($foreign['name']);
            });
        } catch (Throwable) {
            // SQLite may not support dropping the named foreign key the same way as MySQL.
        }
    }

    DB::table('migrations')
        ->where('migration', '2026_08_03_115511_fix_razorpay_plans_custom_amount_unique_index')
        ->delete();

    $exitCode = Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_08_03_115511_fix_razorpay_plans_custom_amount_unique_index.php',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    expect($exitCode)->toBe(0);

    if (Schema::getConnection()->getDriverName() === 'mysql') {
        expect(
            collect(Schema::getForeignKeys('razorpay_plans'))
                ->contains(fn (array $foreignKey): bool => in_array('cause_id', $foreignKey['columns'] ?? [], true))
        )->toBeTrue()
            ->and(Schema::hasColumn('razorpay_plans', 'custom_plan_key'))->toBeTrue();
    }
});
