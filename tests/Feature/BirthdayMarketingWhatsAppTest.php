<?php

use App\Jobs\SendBirthdayWhatsAppJob;
use App\Models\BirthdayMessageSend;
use App\Models\BirthdayMessageSetting;
use App\Models\BirthdayMessageStep;
use App\Models\DonationOrder;
use App\Models\Donor;
use App\Models\Setting;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function seedBirthdayPipeline(array $overrides = []): void
{
    $settings = BirthdayMessageSetting::current();
    $settings->update([
        'enabled' => $overrides['enabled'] ?? true,
        'aisensy_account_id' => null,
    ]);

    BirthdayMessageStep::query()->updateOrCreate(
        ['days_before' => 7, 'kind' => BirthdayMessageStep::KIND_MARKETING],
        ['enabled' => true, 'campaign_name' => 'birthday_7_days', 'sort_order' => 10],
    );
    BirthdayMessageStep::query()->updateOrCreate(
        ['days_before' => 3, 'kind' => BirthdayMessageStep::KIND_MARKETING],
        ['enabled' => true, 'campaign_name' => 'birthday_3_days', 'sort_order' => 20],
    );
    BirthdayMessageStep::query()->updateOrCreate(
        ['days_before' => 0, 'kind' => BirthdayMessageStep::KIND_MARKETING],
        ['enabled' => true, 'campaign_name' => 'birthday_day_marketing', 'sort_order' => 30],
    );
    BirthdayMessageStep::query()->updateOrCreate(
        ['days_before' => 0, 'kind' => BirthdayMessageStep::KIND_WARM_WISH],
        ['enabled' => true, 'campaign_name' => 'birthday_warm_wish', 'sort_order' => 40],
    );
}

it('dispatches 7-day marketing for matching DOB', function () {
    Bus::fake();
    seedBirthdayPipeline();

    $runDate = now()->startOfDay();
    Donor::factory()->create([
        'name' => 'Seven Day Donor',
        'phone' => '9876543210',
        'date_of_birth' => $runDate->copy()->addDays(7)->subYears(30)->toDateString(),
    ]);

    $this->artisan('donors:send-birthday-whatsapp', [
        '--date' => $runDate->toDateString(),
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('days_before=7')
        ->expectsOutputToContain('Would dispatch 1 birthday WhatsApp job(s)')
        ->assertSuccessful();
});

it('on birthday without prior marketing sends birthday-day marketing', function () {
    Bus::fake();
    seedBirthdayPipeline();

    $runDate = now()->startOfDay();
    $donor = Donor::factory()->create([
        'name' => 'Birthday Marketing',
        'phone' => '9876543211',
        'date_of_birth' => $runDate->copy()->subYears(28)->toDateString(),
    ]);

    $step = BirthdayMessageStep::query()
        ->where('days_before', 0)
        ->where('kind', BirthdayMessageStep::KIND_MARKETING)
        ->first();

    expect(app(\App\Services\BirthdayMessageService::class)->shouldSendStep($donor, $step, $runDate))->toBeTrue();

    $this->artisan('donors:send-birthday-whatsapp', [
        '--date' => $runDate->toDateString(),
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('days_before=0')
        ->expectsOutputToContain('Would dispatch 1 birthday WhatsApp job(s)')
        ->assertSuccessful();
});

it('on birthday after marketing then paid donation selects warm wish', function () {
    Bus::fake();
    seedBirthdayPipeline();

    $runDate = now()->startOfDay();
    $donor = Donor::factory()->create([
        'name' => 'Warm Wish Donor',
        'phone' => '9876543212',
        'date_of_birth' => $runDate->copy()->subYears(28)->toDateString(),
    ]);

    $marketingStep = BirthdayMessageStep::query()
        ->where('days_before', 7)
        ->where('kind', 'marketing')
        ->first();

    BirthdayMessageSend::query()->create([
        'donor_id' => $donor->id,
        'year' => (int) $runDate->year,
        'birthday_message_step_id' => $marketingStep->id,
        'days_before' => 7,
        'kind' => BirthdayMessageStep::KIND_MARKETING,
        'campaign_name' => 'birthday_7_days',
        'sent_at' => $runDate->copy()->subDays(7),
    ]);

    DonationOrder::query()->create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'bday-paid-1',
        'donor_id' => $donor->id,
        'donor_name' => $donor->name,
        'donor_email' => $donor->email,
        'donor_phone' => $donor->phone,
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => $runDate->copy()->subDays(2),
    ]);

    expect(app(\App\Services\BirthdayMessageService::class)->donorDonatedAfterFirstMarketing($donor, (int) $runDate->year))->toBeTrue();

    $warm = BirthdayMessageStep::query()
        ->where('days_before', 0)
        ->where('kind', BirthdayMessageStep::KIND_WARM_WISH)
        ->first();

    expect(app(\App\Services\BirthdayMessageService::class)->shouldSendStep($donor, $warm, $runDate))->toBeTrue();

    $this->artisan('donors:send-birthday-whatsapp', [
        '--date' => $runDate->toDateString(),
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('warm_wish')
        ->expectsOutputToContain('Would dispatch 1 birthday WhatsApp job(s)')
        ->assertSuccessful();
});

it('does not re-send the same marketing offset in the same year', function () {
    Bus::fake();
    seedBirthdayPipeline();

    $runDate = now()->startOfDay();
    $donor = Donor::factory()->create([
        'phone' => '9876543213',
        'date_of_birth' => $runDate->copy()->addDays(7)->subYears(30)->toDateString(),
    ]);

    BirthdayMessageSend::query()->create([
        'donor_id' => $donor->id,
        'year' => (int) $runDate->year,
        'birthday_message_step_id' => BirthdayMessageStep::query()->where('days_before', 7)->value('id'),
        'days_before' => 7,
        'kind' => BirthdayMessageStep::KIND_MARKETING,
        'campaign_name' => 'birthday_7_days',
        'sent_at' => $runDate->copy()->subHour(),
    ]);

    $this->artisan('donors:send-birthday-whatsapp', [
        '--date' => $runDate->toDateString(),
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Would dispatch 0 birthday WhatsApp job(s)')
        ->assertSuccessful();

    Bus::assertNothingDispatched();
});

it('lets settings editors open birthday messages admin page', function () {
    seedBirthdayPipeline();

    Permission::firstOrCreate(['name' => AdminPermissions::SETTINGS_EDIT]);
    $role = Role::findOrCreate('birthday-settings-editor');
    $role->givePermissionTo(AdminPermissions::SETTINGS_EDIT);

    $admin = User::factory()->create();
    $admin->assignRole($role);

    actingAs($admin)
        ->get(route('admin.birthday-messages.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/BirthdayMessages/Index')
            ->has('steps', 4)
            ->where('settings.enabled', true));
});
