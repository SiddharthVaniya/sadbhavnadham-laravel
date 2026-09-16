<?php

use App\Models\Cause;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows the wordpress site to embed public donate pages', function () {
    $cause = Cause::factory()->create([
        'slug' => 'old-age-home',
        'is_active' => true,
    ]);

    $response = $this->get(route('donate.show', $cause));

    $response->assertSuccessful();
    $response->assertHeaderMissing('X-Frame-Options');
    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("frame-ancestors 'self'")
        ->toContain('https://sadbhavnadham.org')
        ->toContain('https://www.sadbhavnadham.org');
});

it('keeps the admin portal from being embedded on other sites', function () {
    $response = $this->get(route('login'));

    $response->assertSuccessful();
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    expect($response->headers->get('Content-Security-Policy'))
        ->toBe("frame-ancestors 'self'");
});
