<?php

namespace Tests\Unit;

use App\Http\Requests\StoreRecurringDonationRequest;
use Tests\TestCase;

class StoreRecurringDonationRequestRulesTest extends TestCase
{
    public function test_recurring_rules_require_package_consent_and_frequency(): void
    {
        $rules = (new StoreRecurringDonationRequest)->rules();

        $this->assertArrayHasKey('package_id', $rules);
        $this->assertArrayHasKey('amount', $rules);
        $this->assertArrayHasKey('frequency', $rules);
        $this->assertArrayHasKey('consent_recurring', $rules);
        $this->assertContains('nullable', $rules['package_id']);
        $this->assertContains('nullable', $rules['amount']);
        $this->assertContains('accepted', $rules['consent_recurring']);
    }

    public function test_recurring_quantity_is_limited_to_one(): void
    {
        $rules = (new StoreRecurringDonationRequest)->rules();

        $this->assertContains('in:1', $rules['quantity']);
    }
}
