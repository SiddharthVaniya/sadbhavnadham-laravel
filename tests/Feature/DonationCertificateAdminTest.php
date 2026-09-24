<?php

use App\Models\DonationOrder;
use App\Models\User;
use App\Services\DonationCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function seedCertificateAdmin(): User
{
    Permission::firstOrCreate(['name' => 'preview receipts']);
    Permission::firstOrCreate(['name' => 'generate receipts']);
    Permission::firstOrCreate(['name' => 'view all donations']);

    $role = Role::firstOrCreate(['name' => 'admin']);
    $role->givePermissionTo(['preview receipts', 'generate receipts', 'view all donations']);

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function paidOrderForCertificate(): DonationOrder
{
    return DonationOrder::create([
        'payment_provider' => 'razorpay',
        'donor_name' => 'Certificate Donor',
        'donor_email' => 'cert@example.com',
        'donor_phone' => '9999999999',
        'total_amount' => 1100.00,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);
}

it('redirects to the certificate public url when viewing', function () {
    $user = seedCertificateAdmin();
    $order = paidOrderForCertificate();

    $this->mock(DonationCertificateService::class, function ($mock) {
        $mock->shouldReceive('existingPublicUrl')
            ->once()
            ->andReturn('https://admin.sadbhavnadham.org/storage/certificates/sanman-1-gu.png?v=1');
        $mock->shouldReceive('whatsappMediaUrl')->never();
    });

    Auth::login($user);

    $this->get(route('admin.donations.certificate.show', $order))
        ->assertRedirect('https://admin.sadbhavnadham.org/storage/certificates/sanman-1-gu.png?v=1');
});

it('regenerates the certificate and returns json url', function () {
    $user = seedCertificateAdmin();
    $order = paidOrderForCertificate();

    $this->mock(DonationCertificateService::class, function ($mock) {
        $mock->shouldReceive('whatsappMediaUrl')
            ->once()
            ->withArgs(fn ($passedOrder, $force) => $passedOrder->is($order) && $force === true)
            ->andReturn('https://admin.sadbhavnadham.org/storage/certificates/sanman-1-gu.png?v=2');
    });

    Auth::login($user);

    $this->postJson(route('admin.donations.certificate.regenerate', $order))
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'url' => 'https://admin.sadbhavnadham.org/storage/certificates/sanman-1-gu.png?v=2',
            'message' => 'Certificate regenerated.',
        ]);
});

it('rejects certificate regenerate for unpaid donations', function () {
    $user = seedCertificateAdmin();
    $order = DonationOrder::create([
        'payment_provider' => 'razorpay',
        'donor_name' => 'Pending Donor',
        'donor_email' => 'pending@example.com',
        'donor_phone' => '9999999998',
        'total_amount' => 500.00,
        'status' => DonationOrder::STATUS_PENDING,
    ]);

    Auth::login($user);

    $this->postJson(route('admin.donations.certificate.regenerate', $order))
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'message' => 'Only paid donations can have a certificate.',
        ]);
});
