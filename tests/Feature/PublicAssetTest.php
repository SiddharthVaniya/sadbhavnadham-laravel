<?php

use App\Models\Cause;
use App\Support\PublicAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('appends filemtime version to existing public assets', function () {
    $path = 'css/donate.css';
    $fullPath = public_path($path);

    expect(is_file($fullPath))->toBeTrue();

    $url = PublicAsset::url($path);

    expect($url)->toContain('css/donate.css?v=');
    expect($url)->toEndWith((string) filemtime($fullPath));
});

it('returns asset url without version when file is missing', function () {
    $url = PublicAsset::url('css/does-not-exist.css');

    expect($url)->toBe(asset('css/does-not-exist.css'));
    expect($url)->not->toContain('?v=');
});

it('versioned donate css is referenced on cause pages', function () {
    $cause = Cause::factory()->create([
        'is_active' => true,
        'contact_address' => 'Test address',
    ]);

    $response = $this->get(route('donate.show', $cause->slug));

    $response->assertOk();
    $response->assertSee('css/donate.css?v='.filemtime(public_path('css/donate.css')), false);
});
