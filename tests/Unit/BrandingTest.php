<?php

namespace Tests\Unit;

use App\Support\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_interpolate_replaces_placeholders(): void
    {
        config([
            'branding.short_name' => 'Test Org',
            'branding.name' => 'Test Organisation',
            'branding.legal_name' => 'Test Trust',
        ]);

        $text = Branding::interpolate('Donate to {brand} ({name}) from {legal_name}');

        $this->assertSame('Donate to Test Org (Test Organisation) from Test Trust', $text);
    }

    public function test_recurring_mandate_uses_brand_placeholder(): void
    {
        config([
            'branding.short_name' => 'Helping Hands',
            'branding.recurring_mandate' => 'I authorize {brand} to debit monthly.',
        ]);

        $this->assertSame('I authorize Helping Hands to debit monthly.', Branding::recurringMandateText());
    }

    public function test_to_array_exposes_frontend_keys(): void
    {
        config([
            'branding.name' => 'Acme Charity',
            'branding.short_name' => 'Acme',
            'branding.admin_label' => 'Acme Admin',
            'branding.assets.logo' => '/logo.png',
            'branding.bank.account_number' => '123456',
            'branding.bank.ifsc' => 'ABCD0123456',
            'branding.contact.email' => 'hello@example.com',
        ]);

        $branding = Branding::toArray();

        $this->assertSame('Acme Charity', $branding['name']);
        $this->assertSame('Acme', $branding['shortName']);
        $this->assertSame('Acme Admin', $branding['adminLabel']);
        $this->assertStringContainsString('/logo.png', $branding['logoUrl']);
        $this->assertTrue($branding['hasBankDetails']);
        $this->assertSame('123456', $branding['bank']['account_number']);
        $this->assertSame('hello@example.com', $branding['contact']['email']);
    }
}
