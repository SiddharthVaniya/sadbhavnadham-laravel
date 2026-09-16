<?php

use App\Models\User;
use App\Support\LiveVisitorTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('records a public website heartbeat with coordinates and exposes live visitors on the dashboard', function () {
    app(LiveVisitorTracker::class)->heartbeat('test-session', [
        'path' => '/donate/old-age-home',
        'city' => 'Rajkot',
        'country_code' => 'IN',
        'country_name' => 'India',
        'lat' => 22.3039,
        'lng' => 70.8022,
        'label' => 'Rajkot, India',
        'precision' => 'exact',
    ]);

    postJson(route('presence.heartbeat'), [
        'path' => '/donate/old-age-home',
    ])->assertOk()->assertJson(['ok' => true]);

    $tracker = app(LiveVisitorTracker::class);
    expect($tracker->count())->toBeGreaterThanOrEqual(1);

    $user = User::factory()->create();
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user->assignRole('super_admin');

    actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('liveVisitors.count', fn ($count) => $count >= 1)
            ->has('todaysGifts'));

    actingAs($user)
        ->getJson(route('admin.dashboard.live-visitors'))
        ->assertOk()
        ->assertJsonPath('count', fn ($count) => $count >= 1);
});

it('expires stale live visitors after the presence ttl', function () {
    $tracker = app(LiveVisitorTracker::class);
    $tracker->heartbeat('session-a', [
        'path' => '/',
        'lat' => 20.5,
        'lng' => 78.9,
        'label' => 'India',
    ]);

    expect($tracker->count())->toBe(1);

    Cache::put('live_visitors:index', [
        'session-a' => now()->subSeconds(LiveVisitorTracker::TTL_SECONDS + 5)->getTimestamp(),
    ], 120);

    expect($tracker->count())->toBe(0);
});
