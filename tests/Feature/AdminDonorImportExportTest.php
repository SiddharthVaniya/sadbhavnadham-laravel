<?php

use App\Models\DonationOrder;
use App\Models\Donor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createDonorExportUser(): User
{
    foreach (['view donors', 'export donors'] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['view donors', 'export donors']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('exports filtered donors as csv', function () {
    $user = createDonorExportUser();

    $donor = Donor::factory()->create([
        'name' => 'Exportable Donor',
        'email' => 'exportable@example.com',
        'phone' => '9800000001',
        'city' => 'Rajkot',
    ]);

    DonationOrder::create([
        'payment_provider' => 'razorpay',
        'provider_order_id' => 'order_export_donor',
        'donor_id' => $donor->id,
        'donor_name' => $donor->name,
        'donor_email' => $donor->email,
        'donor_phone' => $donor->phone,
        'currency' => 'INR',
        'total_amount' => 700,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    $response = actingAs($user)
        ->get(route('admin.donors.export-all', ['search' => 'Exportable']))
        ->assertOk();

    $csv = $response->streamedContent();

    expect($csv)->toContain('Exportable Donor')
        ->and($csv)->toContain('exportable@example.com');
});

it('imports donors from csv and lists them without donations', function () {
    $user = createDonorExportUser();

    $csv = implode("\n", [
        'name,email,phone,city,state',
        'Imported Person,imported@example.com,9800000099,Rajkot,Gujarat',
    ]);

    $file = UploadedFile::fake()->createWithContent('donors.csv', $csv);

    actingAs($user)
        ->post(route('admin.donors.import'), ['file' => $file])
        ->assertRedirect(route('admin.donors.index'));

    $donor = Donor::query()->where('email', 'imported@example.com')->first();

    expect($donor)->not->toBeNull()
        ->and($donor->name)->toBe('Imported Person')
        ->and($donor->city)->toBe('Rajkot');

    actingAs($user)
        ->get(route('admin.donors.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donors/Index')
            ->where('can_export', true)
            ->has('donors.data', 1)
            ->where('donors.data.0.email', 'imported@example.com'));

    actingAs($user)
        ->get(route('admin.donors.show', $donor))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donors/Show')
            ->where('donor.name', 'Imported Person')
            ->where('has_donation_history', false));
});

it('forbids donor import without export donors permission', function () {
    Permission::firstOrCreate(['name' => 'view donors']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['view donors']);
    $user = User::factory()->create();
    $user->assignRole($role);

    $file = UploadedFile::fake()->createWithContent('donors.csv', "name,email,phone\nA,a@example.com,9800000002");

    actingAs($user)
        ->post(route('admin.donors.import'), ['file' => $file])
        ->assertForbidden();
});
