<?php

use App\Models\Cause;
use App\Models\CausePackage;
use App\Support\BrandingStore;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedBrandingCanonicalUrl(string $canonicalUrl, ?string $homeDescription = null): void
{
    $seo = ['canonical_url' => $canonicalUrl];

    if ($homeDescription !== null) {
        $seo['home_description'] = $homeDescription;
    }

    BrandingStore::persist(['seo' => $seo]);
}

it('renders the donation amount input without decimal points for the default amount', function () {
    $cause = Cause::factory()->create([
        'is_active' => true,
        'default_amount' => '1500.00',
        'default_title' => 'Morning Breakfast',
        'cta_text' => 'Donate Now',
        'allow_custom_amount' => true,
        'pan_required' => false,
    ]);

    $response = $this->get(route('donate.show', $cause->slug));

    $response->assertOk();
    $response->assertSee('value="1500"', false);
    $response->assertSee('step="1"', false);
    $response->assertSee('data-amount="500"', false);
    $response->assertSee('data-amount="2500"', false);
    $response->assertDontSee('data-amount="100"', false);
    $response->assertDontSee('1500.00');
});

it('renders a styled flatpickr date of birth field on the donate form', function () {
    $cause = Cause::factory()->create(['is_active' => true]);

    $response = $this->get(route('donate.show', $cause->slug));

    $response->assertOk();
    $response->assertSee('id="donorDateOfBirth"', false);
    $response->assertSee('class="dob-datepicker"', false);
    $response->assertSee('placeholder="DD-MM-YYYY"', false);
    $response->assertSee('flatpickr.min.js', false);
    $response->assertSee('flatpickr.min.css', false);
    $response->assertDontSee('type="date" name="date_of_birth"', false);
});

it('uses the package marked as default for the pre-selected amount and title', function () {
    $cause = Cause::factory()->create([
        'is_active' => true,
        'allow_custom_amount' => true,
        'pan_required' => false,
    ]);

    CausePackage::factory()->for($cause)->create([
        'title' => 'First Package',
        'amount' => '500.00',
        'sort_order' => 1,
        'is_active' => true,
        'is_default' => false,
    ]);

    $default = CausePackage::factory()->for($cause)->create([
        'title' => 'Highlighted Package',
        'amount' => '2500.00',
        'sort_order' => 2,
        'is_active' => true,
        'is_default' => true,
    ]);

    $response = $this->get(route('donate.show', $cause->slug));

    $response->assertOk();
    $response->assertSee('value="2500"', false);
    $response->assertSee('id="donationTitle" name="title" value="Highlighted Package"', false);
    $response->assertSee('id="donationPackageId" name="package_id" value="'.$default->id.'"', false);
});

it('falls back to the first package when none is marked as default', function () {
    $cause = Cause::factory()->create([
        'is_active' => true,
        'allow_custom_amount' => true,
        'pan_required' => false,
    ]);

    $first = CausePackage::factory()->for($cause)->create([
        'title' => 'First Package',
        'amount' => '750.00',
        'sort_order' => 1,
        'is_active' => true,
        'is_default' => false,
    ]);

    CausePackage::factory()->for($cause)->create([
        'title' => 'Second Package',
        'amount' => '999.00',
        'sort_order' => 2,
        'is_active' => true,
        'is_default' => false,
    ]);

    $response = $this->get(route('donate.show', $cause->slug));

    $response->assertOk();
    $response->assertSee('value="750"', false);
    $response->assertSee('id="donationPackageId" name="package_id" value="'.$first->id.'"', false);
});

it('rejects WordPress Razorpay requests when the API token is not configured', function (?string $configuredToken) {
    config(['app.wp_api_token' => $configuredToken]);

    $response = $this->postJson('/api/wp-razorpay', []);

    $response->assertForbidden()
        ->assertJson(['error' => 'Unauthorized']);
})->with([
    'missing token' => null,
    'empty token' => '',
    'example placeholder token' => 'YOUR_WP_API_TOKEN_HERE',
]);

it('rejects WordPress Razorpay requests with an invalid API token before validation', function () {
    config(['app.wp_api_token' => 'configured-secret-token']);

    $response = $this->withHeader('X-WP-TOKEN', 'wrong-token')
        ->postJson('/api/wp-razorpay', []);

    $response->assertForbidden()
        ->assertJson(['error' => 'Unauthorized']);
});

it('allows WordPress Razorpay requests with the configured token to reach validation', function () {
    config(['app.wp_api_token' => 'configured-secret-token']);

    $response = $this->withHeader('X-WP-TOKEN', 'configured-secret-token')
        ->postJson('/api/wp-razorpay', []);

    $response->assertUnprocessable();
});

it('renders homepage SEO meta tags', function () {
    seedBrandingCanonicalUrl('https://donate.example.org', 'Give generously to support our elders.');

    $response = $this->get(route('donate.index'));

    $response->assertOk();
    $response->assertSee('<meta name="description" content="Give generously to support our elders."', false);
    $response->assertSee('<link rel="canonical" href="https://sadbhavnadham.org/"', false);
    $response->assertSee('<meta property="og:title"', false);
    $response->assertSee('<meta property="og:image"', false);
    $response->assertSee('<meta name="twitter:card" content="summary_large_image"', false);
});

it('renders cause page SEO meta tags and heading', function () {
    seedBrandingCanonicalUrl('https://donate.example.org');

    $cause = Cause::factory()->create([
        'is_active' => true,
        'title' => 'Morning Breakfast Seva',
        'slug' => 'morning-breakfast',
        'excerpt' => 'Provide a nourishing breakfast for our residents every morning.',
    ]);

    $response = $this->get(route('donate.show', $cause->slug));

    $response->assertOk();
    $response->assertSee('<h1 class="cause-page-title mb-3">Morning Breakfast Seva</h1>', false);
    $response->assertSee('Provide a nourishing breakfast for our residents every morning.', false);
    $response->assertSee('<meta name="description" content="Provide a nourishing breakfast for our residents every morning."', false);
    $response->assertSee('<link rel="canonical" href="https://sadbhavnadham.org/donate/morning-breakfast"', false);
    $response->assertSee('<title>Morning Breakfast Seva |', false);
});

it('ships robots.txt that blocks admin and api routes', function () {
    seedBrandingCanonicalUrl('https://donate.example.org');

    $response = $this->get(route('seo.robots'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    $response->assertSee('Disallow: /admin/');
    $response->assertSee('Disallow: /api/');
    $response->assertSee('Disallow: /receipts/demo');
    $response->assertSee('Sitemap: https://donate.example.org/sitemap.xml');
});

it('serves sitemap xml with homepage and active causes', function () {
    seedBrandingCanonicalUrl('https://donate.example.org');

    $active = Cause::factory()->create([
        'is_active' => true,
        'slug' => 'active-cause',
    ]);

    Cause::factory()->create([
        'is_active' => false,
        'slug' => 'hidden-cause',
    ]);

    $response = $this->get(route('seo.sitemap'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
    $response->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false);
    $response->assertSee('<loc>https://sadbhavnadham.org/</loc>', false);
    $response->assertSee('<loc>https://sadbhavnadham.org/donate/active-cause</loc>', false);
    $response->assertDontSee('hidden-cause');
});

it('renders json ld on the homepage', function () {
    seedBrandingCanonicalUrl('https://donate.example.org');

    $response = $this->get(route('donate.index'));

    $response->assertOk();
    $response->assertSee('<script type="application/ld+json">', false);
    $response->assertSee('"@type":"NGO"', false);
    $response->assertSee('"@type":"WebSite"', false);
    $response->assertSee('"@type":"WebPage"', false);
});

it('renders json ld on cause pages', function () {
    seedBrandingCanonicalUrl('https://donate.example.org');

    $cause = Cause::factory()->create([
        'is_active' => true,
        'title' => 'Evening Meal Seva',
        'slug' => 'evening-meal',
    ]);

    $response = $this->get(route('donate.show', $cause->slug));

    $response->assertOk();
    $response->assertSee('"@type":"DonateAction"', false);
    $response->assertSee('"@type":"BreadcrumbList"', false);
    $response->assertSee('Donate to Evening Meal Seva', false);
});

it('renders breadcrumb navigation on cause pages', function () {
    $cause = Cause::factory()->create([
        'is_active' => true,
        'title' => 'Medical Support',
        'slug' => 'medical-support',
    ]);

    $response = $this->get(route('donate.show', $cause->slug));

    $response->assertOk();
    $response->assertSee('<nav aria-label="Breadcrumb"', false);
    $response->assertSee('Medical Support', false);
});

it('renders item list structured data on the homepage', function () {
    seedBrandingCanonicalUrl('https://donate.example.org');

    Cause::factory()->create([
        'is_active' => true,
        'title' => 'Food Seva',
        'slug' => 'food-seva',
    ]);

    $response = $this->get(route('donate.index'));

    $response->assertOk();
    $response->assertSee('"@type":"ItemList"', false);
    $response->assertSee('Food Seva', false);
});

it('uses semantic headings on cause cards', function () {
    Cause::factory()->create([
        'is_active' => true,
        'title' => 'Shelter Support',
    ]);

    $response = $this->get(route('donate.index'));

    $response->assertOk();
    $response->assertSee('<h2 class="cause-card-title h4">Shelter Support</h2>', false);
});

it('defers razorpay checkout scripts to cause pages only', function () {
    $cause = Cause::factory()->create(['is_active' => true]);

    $this->get(route('donate.index'))
        ->assertOk()
        ->assertDontSee('checkout.razorpay.com/v1/checkout.js', false);

    $this->get(route('donate.show', $cause->slug))
        ->assertOk()
        ->assertSee('checkout.razorpay.com/v1/checkout.js', false);
});

it('embeds csrf tokens and disables caching on cause checkout pages', function () {
    $cause = Cause::factory()->create(['is_active' => true]);

    $response = $this->get(route('donate.show', $cause->slug));

    $response->assertOk();
    $response->assertSee('name="csrf-token"', false);
    $response->assertSee('name="_token"', false);
    $response->assertSee('data-csrf="', false);

    $cacheControl = (string) $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('no-store');
    expect($cacheControl)->toContain('no-cache');
});

it('embeds csrf tokens and disables caching on campaign checkout pages', function () {
    config(['payments.razorpay.subscriptions_enabled' => true]);

    $cause = Cause::factory()->create([
        'is_active' => true,
        'allow_recurring' => true,
    ]);
    $campaign = \App\Models\DonationCampaign::factory()->create([
        'cause_id' => $cause->id,
        'is_active' => true,
        'recurring_only' => true,
        'amount' => 500,
        'title' => 'Monthly Support',
    ]);

    $response = $this->get(route('donate.campaign', $campaign->slug));

    $response->assertOk();
    $response->assertSee('name="csrf-token"', false);
    $response->assertSee('name="_token"', false);

    $cacheControl = (string) $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('no-store');
});

it('noindexes payment return urls on the homepage', function () {
    $response = $this->get('/?donation=success');

    $response->assertOk();
    $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    $response->assertSee('<link rel="canonical" href="', false);
});

it('renders a branded 404 page for missing causes', function () {
    Cause::factory()->create([
        'is_active' => true,
        'title' => 'Active Cause',
        'slug' => 'active-cause',
    ]);

    $response = $this->get('/donate/does-not-exist');

    $response->assertNotFound();
    $response->assertSee('<h1 class="mb-3">Page Not Found</h1>', false);
    $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    $response->assertSee('Active Cause', false);
    $response->assertSee('View All Causes', false);
});

it('renders homepage preconnect hints', function () {
    $response = $this->get(route('donate.index'));

    $response->assertOk();
    $response->assertSee('rel="preconnect" href="https://fonts.googleapis.com"', false);
    $response->assertSee('rel="dns-prefetch" href="https://checkout.razorpay.com"', false);
});

it('lazy loads non-first cause images on the homepage', function () {
    Cause::factory()->create([
        'is_active' => true,
        'hero_image' => '/images/cause-one.jpg',
        'sort_order' => 1,
    ]);

    Cause::factory()->create([
        'is_active' => true,
        'hero_image' => '/images/cause-two.jpg',
        'sort_order' => 2,
    ]);

    $response = $this->get(route('donate.index'));

    $response->assertOk();
    $response->assertSee('loading="lazy"', false);
    $response->assertSee('loading="eager"', false);
});

it('marks admin pages as noindex', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('shows optional tree dedication name fields on tree plantation', function () {
    $cause = Cause::factory()->create([
        'slug' => 'tree-plantation',
        'title' => 'Tree Plantation',
        'is_active' => true,
        'allow_custom_amount' => true,
        'pan_required' => false,
    ]);

    CausePackage::factory()->for($cause)->create([
        'title' => 'Tree',
        'amount' => 3000,
        'is_active' => true,
        'is_default' => true,
    ]);

    $this->get(route('donate.show', $cause->slug))
        ->assertOk()
        ->assertSee('id="honoreeNamesBox"', false)
        ->assertSee('class="honoree-names-box"', false)
        ->assertSee('name="honoree_names[]"', false)
        ->assertSee('Name on this tree', false)
        ->assertSee('Write the name you want on the tree nameplate', false);
});

it('does not show tree dedication name fields on other causes', function () {
    $cause = Cause::factory()->create([
        'slug' => 'old-age-home',
        'is_active' => true,
        'allow_custom_amount' => true,
        'pan_required' => false,
    ]);

    $this->get(route('donate.show', $cause->slug))
        ->assertOk()
        ->assertDontSee('id="honoreeNamesBox"', false)
        ->assertDontSee('name="honoree_names[]"', false);
});
