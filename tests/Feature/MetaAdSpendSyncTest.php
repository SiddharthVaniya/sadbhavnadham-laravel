<?php

use App\Models\MarketerDailyBudget;
use App\Models\MarketerMonthlyBudget;
use App\Models\MetaAdAccount;
use App\Models\MetaAdSpendDaily;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function metaAdmin(): User
{
    Permission::firstOrCreate(['name' => 'edit users', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['edit users']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function metaMarketer(string $name = 'Ashvini Patel', string $code = 'ashvini1'): User
{
    Role::firstOrCreate(['name' => 'digital_marketer', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'name' => $name,
        'referral_code' => $code,
    ]);
    $user->assignRole('digital_marketer');

    return $user;
}

function createMetaAccount(array $overrides = []): MetaAdAccount
{
    return MetaAdAccount::query()->create(array_merge([
        'label' => 'Main ads',
        'app_id' => '123456',
        'app_secret' => 'secret-value',
        'access_token' => 'token-value',
        'ad_account_id' => '999888777',
        'is_active' => true,
    ], $overrides));
}

function fakeMetaInsights(array $rows): void
{
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'data' => $rows,
            'paging' => [],
        ], 200),
    ]);
}

beforeEach(function () {
    RateLimiter::clear('meta-sync:admin:1');
});

it('creates a meta account without leaking secrets to inertia', function () {
    $admin = metaAdmin();

    actingAs($admin)
        ->post(route('admin.marketers.meta.accounts.store'), [
            'label' => 'Account A',
            'app_id' => 'app-1',
            'app_secret' => 'super-secret',
            'access_token' => 'super-token',
            'ad_account_id' => 'act_111',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.marketers.meta'));

    $account = MetaAdAccount::query()->first();
    expect($account)->not->toBeNull()
        ->and($account->ad_account_id)->toBe('111')
        ->and($account->access_token)->toBe('super-token');

    actingAs($admin)
        ->get(route('admin.marketers.meta'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Marketers/Meta')
            ->where('accounts.0.label', 'Account A')
            ->where('accounts.0.has_access_token', true)
            ->missing('accounts.0.access_token')
            ->missing('accounts.0.app_secret'));
});

it('syncs from live meta and overwrites daily spend plus monthly rollup', function () {
    $admin = metaAdmin();
    $marketer = metaMarketer();
    createMetaAccount();

    MarketerMonthlyBudget::query()->create([
        'user_id' => $marketer->id,
        'year_month' => now()->format('Y-m'),
        'target_amount' => 50000,
        'limit_amount' => 40000,
        'spend_amount' => 100,
    ]);

    MarketerDailyBudget::query()->create([
        'user_id' => $marketer->id,
        'spend_date' => now()->toDateString(),
        'limit_amount' => null,
        'spend_amount' => 100,
    ]);

    $today = now()->toDateString();

    fakeMetaInsights([
        [
            'ad_id' => 'ad-1',
            'ad_name' => 'Ashvini | 09/10 | Sadbhavna | Pitru Amas | Old age',
            'adset_id' => 'as-1',
            'adset_name' => 'Set A',
            'campaign_id' => 'c-1',
            'campaign_name' => 'Ashvini | Campaign',
            'spend' => '250.50',
            'account_currency' => 'INR',
            'date_start' => $today,
        ],
        [
            'ad_id' => 'ad-2',
            'ad_name' => 'Unknown Person | Theme',
            'adset_id' => 'as-2',
            'adset_name' => 'Set B',
            'campaign_id' => 'c-2',
            'campaign_name' => 'Other',
            'spend' => '99',
            'account_currency' => 'INR',
            'date_start' => $today,
        ],
    ]);

    RateLimiter::clear('meta-sync:admin:'.$admin->id);

    actingAs($admin)
        ->post(route('admin.marketers.meta.sync'), [
            'from' => $today,
            'to' => $today,
            'redirect' => 'today',
            'date' => $today,
        ])
        ->assertRedirect(route('admin.marketers.today', ['date' => $today]));

    $daily = MarketerDailyBudget::query()
        ->where('user_id', $marketer->id)
        ->whereDate('spend_date', $today)
        ->first();

    expect((float) $daily->spend_amount)->toBe(250.5);

    $month = MarketerMonthlyBudget::query()
        ->where('user_id', $marketer->id)
        ->where('year_month', now()->format('Y-m'))
        ->first();

    expect((float) $month->spend_amount)->toBe(250.5)
        ->and($month->target_amount)->toBe(50000);

    expect(MetaAdSpendDaily::query()->count())->toBe(2)
        ->and(MetaAdSpendDaily::query()->where('matched_via', 'unmatched')->count())->toBe(1)
        ->and(MetaAdSpendDaily::query()->where('user_id', $marketer->id)->count())->toBe(1);
});

it('does not zero daily spend when meta returns no matched rows for that day', function () {
    $admin = metaAdmin();
    $marketer = metaMarketer();
    createMetaAccount();

    MarketerDailyBudget::query()->create([
        'user_id' => $marketer->id,
        'spend_date' => now()->toDateString(),
        'spend_amount' => 420,
    ]);

    fakeMetaInsights([]);
    RateLimiter::clear('meta-sync:admin:'.$admin->id);

    actingAs($admin)
        ->post(route('admin.marketers.meta.sync'), [
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ])
        ->assertRedirect();

    $daily = MarketerDailyBudget::query()
        ->where('user_id', $marketer->id)
        ->whereDate('spend_date', now()->toDateString())
        ->first();

    expect((float) $daily->spend_amount)->toBe(420.0);
});

it('skips inactive meta accounts during sync', function () {
    $admin = metaAdmin();
    createMetaAccount(['is_active' => false, 'label' => 'Off']);

    Http::fake([
        'graph.facebook.com/*' => Http::response(['data' => []], 200),
    ]);

    RateLimiter::clear('meta-sync:admin:'.$admin->id);

    actingAs($admin)
        ->post(route('admin.marketers.meta.sync'), [
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ])
        ->assertRedirect();

    Http::assertNothingSent();
});

it('filters meta spend by marketer name inside the ad string', function () {
    $admin = metaAdmin();
    $marketer = metaMarketer();
    $account = createMetaAccount();
    $today = now()->toDateString();

    MetaAdSpendDaily::query()->create([
        'meta_ad_account_id' => $account->id,
        'spend_date' => $today,
        'ad_id' => '1',
        'ad_name' => 'Ashvini | 09/10 | Sadbhavna | Theme | Cause',
        'campaign_name' => 'Camp',
        'spend_amount' => 10,
        'user_id' => $marketer->id,
        'matched_via' => 'ad_name_prefix',
    ]);

    MetaAdSpendDaily::query()->create([
        'meta_ad_account_id' => $account->id,
        'spend_date' => $today,
        'ad_id' => '2',
        'ad_name' => 'Someone Else | Ad',
        'campaign_name' => 'Camp',
        'spend_amount' => 20,
        'user_id' => null,
        'matched_via' => 'unmatched',
    ]);

    actingAs($admin)
        ->get(route('admin.marketers.meta', ['q' => 'Ashvini', 'from_date' => $today, 'to_date' => $today]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Marketers/Meta')
            ->has('rows.data', 1)
            ->where('rows.data.0.ad_name', 'Ashvini | 09/10 | Sadbhavna | Theme | Cause'));
});

it('lets marketers view only their meta rows and refresh with throttle', function () {
    $ashvini = metaMarketer('Ashvini Patel', 'ash1');
    $other = metaMarketer('Urvi Soni', 'urvi1');
    $account = createMetaAccount();
    $today = now()->toDateString();

    MetaAdSpendDaily::query()->create([
        'meta_ad_account_id' => $account->id,
        'spend_date' => $today,
        'ad_id' => 'a1',
        'ad_name' => 'Ashvini | Ad',
        'spend_amount' => 15,
        'user_id' => $ashvini->id,
        'matched_via' => 'ad_name_prefix',
    ]);

    MetaAdSpendDaily::query()->create([
        'meta_ad_account_id' => $account->id,
        'spend_date' => $today,
        'ad_id' => 'u1',
        'ad_name' => 'Urvi | Ad',
        'spend_amount' => 40,
        'user_id' => $other->id,
        'matched_via' => 'ad_name_prefix',
    ]);

    actingAs($ashvini)
        ->get(route('marketer.meta', ['from_date' => $today, 'to_date' => $today]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Marketer/Meta')
            ->has('rows.data', 1)
            ->where('rows.data.0.ad_name', 'Ashvini | Ad')
            ->missing('accounts'));

    fakeMetaInsights([]);
    $throttleKey = 'meta-sync:marketer:'.$ashvini->id;
    RateLimiter::clear($throttleKey);

    actingAs($ashvini)
        ->post(route('marketer.meta.refresh'))
        ->assertRedirect();

    expect(RateLimiter::tooManyAttempts($throttleKey, 1))->toBeTrue();

    actingAs($ashvini)
        ->post(route('marketer.meta.refresh'))
        ->assertRedirect();

    $envelopes = session('flasher::envelopes', []);
    $messages = collect($envelopes)->map(fn ($envelope) => $envelope->getMessage())->implode(' ');
    expect($messages)->toContain('Please wait');
});

it('blocks marketers from meta credential routes', function () {
    $marketer = metaMarketer();

    actingAs($marketer)
        ->post(route('admin.marketers.meta.accounts.store'), [
            'label' => 'Hack',
            'app_id' => 'x',
            'app_secret' => 'y',
            'access_token' => 'z',
            'ad_account_id' => '1',
        ])
        ->assertRedirect(route('marketer.dashboard'));
});
