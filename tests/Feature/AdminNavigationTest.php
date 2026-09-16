<?php

use App\Models\User;
use App\Support\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function navigationAdminWithPermissions(array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    $role = Role::findOrCreate('nav-tester');
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user->fresh();
}

it('groups admin navigation into operational sections', function () {
    $user = navigationAdminWithPermissions([
        'view analytics',
        'view reports',
        'view donations',
        'view subscriptions',
        'view donors',
        'manage causes',
        'manage packages',
        'manage aisensy accounts',
        'manage whatsapp campaigns',
        'manage users',
        'manage settings',
    ]);

    $navigation = AdminNavigation::build($user);
    $labels = collect($navigation)->pluck('label')->all();
    $byLabel = collect($navigation)->keyBy('label');

    expect($labels)->toContain('Dashboard', 'WhatsApp accounts', 'Broadcasts')
        ->and($labels)->not->toContain('Aisensy', 'WA Campaigns')
        ->and($byLabel['Analytics']['section'])->toBe('Insights')
        ->and($byLabel['Donations']['section'])->toBe('Finance')
        ->and($byLabel['Donors']['section'])->toBe('People')
        ->and($byLabel['Causes']['section'])->toBe('Fundraising')
        ->and($byLabel['WhatsApp accounts']['section'])->toBe('Engagement')
        ->and($byLabel['Users']['section'])->toBe('System');
});

it('hides navigation items the user cannot access', function () {
    $user = navigationAdminWithPermissions(['view donations']);

    $labels = collect(AdminNavigation::build($user))->pluck('label')->all();

    expect($labels)->toContain('Donations')
        ->and($labels)->not->toContain('Dashboard', 'Users', 'Settings', 'Broadcasts');
});

it('shows my receipts instead of all donations for clerks without view all', function () {
    $user = navigationAdminWithPermissions([
        'view donations',
        'manage donations',
        'manage receipts',
    ]);

    $donations = collect(AdminNavigation::build($user))->firstWhere('label', 'Donations');
    $childLabels = collect($donations['children'] ?? [])->pluck('label')->all();

    expect($childLabels)->toContain('My receipts', 'Record offline')
        ->and($childLabels)->not->toContain('All donations', 'Recovery queue');
});
