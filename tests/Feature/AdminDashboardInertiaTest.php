<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('renders dashboard with inertia page data in html', function () {
    $user = User::factory()->create();
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user->assignRole('super_admin');

    $html = $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Admin\/Dashboard');
    expect($html)->toContain('id="app"');
    expect($html)->toContain('type="application/json"');
    expect($html)->toContain('<script data-page="app"');

    expect(
        preg_match('/\/vite\/assets\/app-[A-Za-z0-9_-]+\.js/', $html) === 1
        || preg_match('/\/build\/assets\/app-[A-Za-z0-9_-]+\.js/', $html) === 1
        || str_contains($html, 'resources/js/app.js')
    )->toBeTrue();
});
