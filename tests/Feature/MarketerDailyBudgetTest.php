<?php

use App\Models\MarketerDailyBudget;
use App\Models\MarketerMonthlyBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function dailyBudgetAdmin(): User
{
    Permission::firstOrCreate(['name' => 'edit users', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['edit users']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

it('lets the main admin set today’s spending limit and keeps the month target visible', function () {
    $admin = dailyBudgetAdmin();
    $marketer = User::factory()->create([
        'name' => 'Urvi Soni',
        'referral_code' => 'cpufaju',
    ]);

    MarketerMonthlyBudget::query()->create([
        'user_id' => $marketer->id,
        'year_month' => now()->format('Y-m'),
        'target_amount' => 50000,
        'limit_amount' => 40000,
        'spend_amount' => 12000,
    ]);

    actingAs($admin)
        ->get(route('admin.marketers.today'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Marketers/Today')
            ->where('marketers.0.user_id', $marketer->id)
            ->where('marketers.0.month_spend_amount', 0)
            ->where('marketers.0.remaining_limit_amount', 40000));

    actingAs($admin)
        ->put(route('admin.marketers.today.update'), [
            'marketers' => [
                [
                    'user_id' => $marketer->id,
                    'spend_amount' => 750,
                ],
            ],
        ])
        ->assertRedirect(route('admin.marketers.today', ['date' => now()->toDateString()]));

    $daily = MarketerDailyBudget::query()
        ->where('user_id', $marketer->id)
        ->whereDate('spend_date', now()->toDateString())
        ->first();

    expect($daily)->not->toBeNull()
        ->and($daily->limit_amount)->toBeNull()
        ->and((float) $daily->spend_amount)->toBe(750.0);

    $month = MarketerMonthlyBudget::query()
        ->where('user_id', $marketer->id)
        ->where('year_month', now()->format('Y-m'))
        ->first();

    expect((float) $month->spend_amount)->toBe(750.0)
        ->and($month->target_amount)->toBe(50000)
        ->and($month->limit_amount)->toBe(40000);
});

it('loads and saves the daily limit for a calendar date', function () {
    $admin = dailyBudgetAdmin();
    $marketer = User::factory()->create([
        'name' => 'Ashvini',
        'referral_code' => 'xvjsrg',
    ]);
    $selected = now()->subDays(4)->toDateString();

    MarketerDailyBudget::query()->create([
        'user_id' => $marketer->id,
        'spend_date' => $selected,
        'limit_amount' => 400,
        'spend_amount' => 120,
    ]);

    actingAs($admin)
        ->get(route('admin.marketers.today', ['date' => $selected]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('spendDate', $selected)
            ->where('marketers.0.spend_amount', 120)
            ->where('marketers.0.month_spend_amount', 120)
            ->where('marketers.0.remaining_limit_amount', null));

    actingAs($admin)
        ->get(route('admin.marketers.today', ['date' => now()->addDay()->toDateString()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('spendDate', now()->toDateString()));

    actingAs($admin)
        ->put(route('admin.marketers.today.update'), [
            'spend_date' => $selected,
            'marketers' => [
                [
                    'user_id' => $marketer->id,
                    'spend_amount' => 300,
                ],
            ],
        ])
        ->assertRedirect(route('admin.marketers.today', ['date' => $selected]));

    actingAs($admin)
        ->put(route('admin.marketers.today.update'), [
            'spend_date' => now()->addDay()->toDateString(),
            'marketers' => [
                [
                    'user_id' => $marketer->id,
                    'spend_amount' => 1,
                ],
            ],
        ])
        ->assertSessionHasErrors('spend_date');

    $daily = MarketerDailyBudget::query()
        ->where('user_id', $marketer->id)
        ->whereDate('spend_date', $selected)
        ->first();

    expect($daily)->not->toBeNull()
        ->and((float) $daily->limit_amount)->toBe(400.0)
        ->and((float) $daily->spend_amount)->toBe(300.0);

    $month = MarketerMonthlyBudget::query()
        ->where('user_id', $marketer->id)
        ->where('year_month', now()->subDays(4)->format('Y-m'))
        ->first();

    expect($month)->not->toBeNull()
        ->and($month->target_amount)->toBeNull()
        ->and($month->limit_amount)->toBeNull()
        ->and((float) $month->spend_amount)->toBe(300.0);
});

it('lists spending history with the month target and archive filters', function () {
    $admin = dailyBudgetAdmin();
    $marketer = User::factory()->create([
        'name' => 'Divyajeet Vala',
        'email' => 'divyjeet@sadbhavnadham.org',
        'referral_code' => 'jpoxrr',
    ]);
    $other = User::factory()->create([
        'name' => 'Ashvini',
        'referral_code' => 'xvjsrg',
    ]);

    MarketerMonthlyBudget::query()->create([
        'user_id' => $marketer->id,
        'year_month' => now()->format('Y-m'),
        'target_amount' => 40000,
        'limit_amount' => 30000,
        'spend_amount' => 9000,
    ]);

    MarketerDailyBudget::query()->create([
        'user_id' => $marketer->id,
        'spend_date' => now()->toDateString(),
        'limit_amount' => 1500,
        'spend_amount' => 400,
    ]);

    MarketerDailyBudget::query()->create([
        'user_id' => $marketer->id,
        'spend_date' => now()->subDay()->toDateString(),
        'limit_amount' => 1800,
        'spend_amount' => 1800,
    ]);

    MarketerDailyBudget::query()->create([
        'user_id' => $other->id,
        'spend_date' => now()->subDays(3)->toDateString(),
        'limit_amount' => 500,
        'spend_amount' => 100,
    ]);

    actingAs($admin)
        ->get(route('admin.marketers.history', [
            'q' => 'jpoxrr',
            'archive' => 'archived',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Marketers/History')
            ->has('rows.data', 1)
            ->where('rows.data.0.code', 'jpoxrr')
            ->where('rows.data.0.archive', 'archived')
            ->where('rows.data.0.month_target_amount', 40000)
            ->where('rows.data.0.month_limit_amount', 30000)
            ->where('rows.data.0.month_spend_amount', 2200)
            ->where('rows.data.0.remaining_limit_amount', 27800)
            ->where('rows.data.0.spend_amount', 1800));

    actingAs($admin)
        ->get(route('admin.marketers.history', [
            'user_id' => $other->id,
            'from_date' => now()->subDays(4)->toDateString(),
            'to_date' => now()->subDays(2)->toDateString(),
            'year_month' => now()->format('Y-m'),
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 1)
            ->where('rows.data.0.code', 'xvjsrg')
            ->where('rows.data.0.archive', 'archived'));
});
