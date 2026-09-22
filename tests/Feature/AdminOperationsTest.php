<?php

use App\Jobs\SendDonationReceiptJob;
use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationOrder;
use App\Models\Donor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createAdminWithPermissions(array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('uploads a package image when updating', function () {
    Storage::fake('public');

    $user = createAdminWithPermissions(['manage packages', 'manage causes']);
    $cause = Cause::factory()->create();
    $package = CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'title' => 'Plant A Peepal Tree',
        'amount' => 3000,
        'image' => null,
    ]);

    $file = UploadedFile::fake()->create('peepal-tree.jpg', 100, 'image/jpeg');

    actingAs($user)
        ->post("/admin/causes/{$cause->id}/packages/{$package->id}", [
            '_method' => 'put',
            'title' => 'Plant A Peepal Tree',
            'amount' => 3000,
            'sort_order' => 1,
            'is_active' => true,
            'is_default' => true,
            'allow_recurring' => true,
            'image' => $file,
        ])
        ->assertRedirect(route('admin.causes.edit', $cause));

    $package->refresh();

    expect($package->image)->not->toBeNull()
        ->and($package->image)->toStartWith('storage/causes/packages/');

    Storage::disk('public')->assertExists(str_replace('storage/', '', $package->image));
});

it('preserves an existing package image when no new file is uploaded', function () {
    $user = createAdminWithPermissions(['manage packages', 'manage causes']);
    $cause = Cause::factory()->create();
    $package = CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'image' => 'storage/causes/packages/existing.jpg',
    ]);

    actingAs($user)
        ->post("/admin/causes/{$cause->id}/packages/{$package->id}", [
            '_method' => 'put',
            'title' => 'Updated title',
            'amount' => 2500,
            'sort_order' => 1,
            'image_existing' => 'storage/causes/packages/existing.jpg',
            'is_active' => true,
            'is_default' => false,
            'allow_recurring' => false,
        ])
        ->assertRedirect(route('admin.causes.edit', $cause));

    expect($package->fresh()->image)->toBe('storage/causes/packages/existing.jpg');
});

it('updates a package using post method spoofing', function () {
    $user = createAdminWithPermissions(['manage packages', 'manage causes']);
    $cause = Cause::factory()->create();
    $package = CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'title' => 'Original',
        'amount' => 100,
    ]);

    actingAs($user)
        ->post("/admin/causes/{$cause->id}/packages/{$package->id}", [
            '_method' => 'put',
            'title' => 'Updated package',
            'amount' => 250,
            'sort_order' => 2,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.causes.edit', $cause));

    expect($package->fresh())
        ->title->toBe('Updated package')
        ->amount->toEqual(250.0);
});

it('rejects package update when package does not belong to cause', function () {
    $user = createAdminWithPermissions(['manage packages', 'manage causes']);
    $cause = Cause::factory()->create();
    $otherCause = Cause::factory()->create();
    $package = CausePackage::factory()->create(['cause_id' => $otherCause->id]);

    actingAs($user)
        ->post("/admin/causes/{$cause->id}/packages/{$package->id}", [
            '_method' => 'put',
            'title' => 'Should fail',
            'amount' => 100,
            'is_active' => true,
        ])
        ->assertNotFound();
});

it('deletes a package from cause edit flow', function () {
    $user = createAdminWithPermissions(['manage packages', 'manage causes']);
    $cause = Cause::factory()->create();
    $package = CausePackage::factory()->create(['cause_id' => $cause->id]);

    actingAs($user)
        ->delete("/admin/causes/{$cause->id}/packages/{$package->id}")
        ->assertRedirect(route('admin.causes.edit', $cause));

    expect(CausePackage::find($package->id))->toBeNull();
});

it('marks a package as default and clears the previous default in the same cause', function () {
    $user = createAdminWithPermissions(['manage packages', 'manage causes']);
    $cause = Cause::factory()->create();
    $current = CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'is_default' => true,
    ]);
    $next = CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'is_default' => false,
    ]);

    actingAs($user)
        ->patch("/admin/causes/{$cause->id}/packages/{$next->id}/toggle-default")
        ->assertRedirect(route('admin.causes.edit', $cause));

    expect($next->fresh()->is_default)->toBeTrue();
    expect($current->fresh()->is_default)->toBeFalse();
});

it('clears the default when toggled off', function () {
    $user = createAdminWithPermissions(['manage packages', 'manage causes']);
    $cause = Cause::factory()->create();
    $package = CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'is_default' => true,
    ]);

    actingAs($user)
        ->patch("/admin/causes/{$cause->id}/packages/{$package->id}/toggle-default")
        ->assertRedirect(route('admin.causes.edit', $cause));

    expect($package->fresh()->is_default)->toBeFalse();
});

it('enforces a single default when saving a package as default', function () {
    $user = createAdminWithPermissions(['manage packages', 'manage causes']);
    $cause = Cause::factory()->create();
    $existingDefault = CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'is_default' => true,
    ]);
    $package = CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'title' => 'Original',
        'amount' => 100,
        'is_default' => false,
    ]);

    actingAs($user)
        ->post("/admin/causes/{$cause->id}/packages/{$package->id}", [
            '_method' => 'put',
            'title' => 'Now default',
            'amount' => 250,
            'is_active' => true,
            'is_default' => true,
        ])
        ->assertRedirect(route('admin.causes.edit', $cause));

    expect($package->fresh()->is_default)->toBeTrue();
    expect($existingDefault->fresh()->is_default)->toBeFalse();
});

it('toggles package active status', function () {
    $user = createAdminWithPermissions(['manage packages', 'manage causes']);
    $cause = Cause::factory()->create();
    $package = CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'is_active' => true,
    ]);

    actingAs($user)
        ->patch("/admin/causes/{$cause->id}/packages/{$package->id}/toggle-active")
        ->assertOk()
        ->assertJson(['is_active' => false]);

    expect($package->fresh()->is_active)->toBeFalse();
});

it('updates a cause using post method spoofing', function () {
    $user = createAdminWithPermissions(['manage causes']);
    $cause = Cause::factory()->create([
        'title' => 'Old title',
        'allow_recurring' => false,
        'allow_weekly_recurring' => false,
    ]);

    actingAs($user)
        ->post("/admin/causes/{$cause->id}", [
            '_method' => 'put',
            'title' => 'New title',
            'slug' => $cause->slug,
            'is_active' => true,
            'allow_custom_amount' => true,
            'allow_recurring' => true,
            'allow_weekly_recurring' => true,
            'pan_required' => true,
            'sort_order' => 0,
        ])
        ->assertRedirect(route('admin.causes.edit', $cause));

    $cause->refresh();

    expect($cause->title)->toBe('New title')
        ->and($cause->allow_recurring)->toBeTrue()
        ->and($cause->allow_weekly_recurring)->toBeTrue();
});

it('exposes weekly recurring on the cause edit page', function () {
    $user = createAdminWithPermissions(['manage causes']);
    $cause = Cause::factory()->create(['allow_weekly_recurring' => true]);

    actingAs($user)
        ->get(route('admin.causes.edit', $cause))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Causes/Edit')
            ->where('cause.allow_weekly_recurring', true));
});

it('removes the aisensy thank you image when requested', function () {
    Storage::fake('public');
    Storage::disk('public')->put('aisensy/old-thank-you.png', 'fake-image');

    $user = createAdminWithPermissions(['manage causes']);
    $cause = Cause::factory()->create([
        'title' => 'Test cause',
        'aisensy_thank_you_image' => 'storage/aisensy/old-thank-you.png',
    ]);

    actingAs($user)
        ->post("/admin/causes/{$cause->id}", [
            '_method' => 'put',
            'title' => $cause->title,
            'slug' => $cause->slug,
            'is_active' => true,
            'allow_custom_amount' => true,
            'pan_required' => false,
            'sort_order' => 0,
            'aisensy_thank_you_image_existing' => 'storage/aisensy/old-thank-you.png',
            'remove_aisensy_thank_you_image' => true,
        ])
        ->assertRedirect(route('admin.causes.edit', $cause));

    expect($cause->fresh()->aisensy_thank_you_image)->toBeNull();
    Storage::disk('public')->assertMissing('aisensy/old-thank-you.png');
});

it('removes the cause certificate template when requested', function () {
    Storage::fake('public');
    Storage::disk('public')->put('causes/certificates/old-template.jpg', 'fake-image');

    $user = createAdminWithPermissions(['manage causes']);
    $cause = Cause::factory()->create([
        'title' => 'Test cause',
        'certificate_template' => 'storage/causes/certificates/old-template.jpg',
    ]);

    actingAs($user)
        ->post("/admin/causes/{$cause->id}", [
            '_method' => 'put',
            'title' => $cause->title,
            'slug' => $cause->slug,
            'is_active' => true,
            'allow_custom_amount' => true,
            'pan_required' => false,
            'sort_order' => 0,
            'certificate_template_existing' => 'storage/causes/certificates/old-template.jpg',
            'remove_certificate_template' => true,
        ])
        ->assertRedirect(route('admin.causes.edit', $cause));

    expect($cause->fresh()->certificate_template)->toBeNull();
    Storage::disk('public')->assertMissing('causes/certificates/old-template.jpg');
});

it('removes the cause english certificate template when requested', function () {
    Storage::fake('public');
    Storage::disk('public')->put('causes/certificates/old-english-template.jpg', 'fake-image');

    $user = createAdminWithPermissions(['manage causes']);
    $cause = Cause::factory()->create([
        'title' => 'Test cause',
        'certificate_template_english' => 'storage/causes/certificates/old-english-template.jpg',
    ]);

    actingAs($user)
        ->post("/admin/causes/{$cause->id}", [
            '_method' => 'put',
            'title' => $cause->title,
            'slug' => $cause->slug,
            'is_active' => true,
            'allow_custom_amount' => true,
            'pan_required' => false,
            'sort_order' => 0,
            'certificate_template_english_existing' => 'storage/causes/certificates/old-english-template.jpg',
            'remove_certificate_template_english' => true,
        ])
        ->assertRedirect(route('admin.causes.edit', $cause));

    expect($cause->fresh()->certificate_template_english)->toBeNull();
    Storage::disk('public')->assertMissing('causes/certificates/old-english-template.jpg');
});

it('toggles cause active status and reorders causes', function () {
    $user = createAdminWithPermissions(['manage causes']);
    $first = Cause::factory()->create(['sort_order' => 1]);
    $second = Cause::factory()->create(['sort_order' => 2]);

    actingAs($user)
        ->patch("/admin/causes/{$first->id}/toggle-active")
        ->assertOk();

    actingAs($user)
        ->patch("/admin/causes/{$second->id}/reorder", ['direction' => 'up'])
        ->assertRedirect();

    expect($second->fresh()->sort_order)->toBeLessThan($first->fresh()->sort_order);
});

it('shows donation details by uuid and supports receipt actions', function () {
    Bus::fake();

    $user = createAdminWithPermissions(['view donations', 'view all donations', 'manage receipts']);
    $donor = Donor::factory()->create();
    $order = DonationOrder::create([
        'payment_provider' => 'offline',
        'provider_order_id' => 'offline-1',
        'donor_id' => $donor->id,
        'donor_name' => $donor->name,
        'donor_email' => $donor->email,
        'donor_phone' => $donor->phone,
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    actingAs($user)
        ->get(route('admin.donations.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Donations/Show')
            ->has('donation.receipt_generate_url')
            ->has('donation.delivery')
            ->where('donation.can_resend_receipt_email', true));

    actingAs($user)
        ->from(route('admin.donations.show', $order))
        ->post(route('admin.donations.receipt.resend', $order))
        ->assertRedirect(route('admin.donations.show', $order))
        ->assertSessionHas('status');

    Bus::assertDispatched(SendDonationReceiptJob::class);

    actingAs($user)
        ->post(route('admin.donations.receipt.generate', $order))
        ->assertRedirect(route('admin.donations.show', $order));
});

it('blocks receipt resend when donor email is missing', function () {
    Bus::fake();

    $user = createAdminWithPermissions(['view donations', 'view all donations', 'manage receipts']);
    $order = DonationOrder::create([
        'payment_provider' => 'offline',
        'provider_order_id' => 'offline-no-email',
        'donor_name' => 'No Email Donor',
        'donor_email' => 'not-a-valid-email',
        'donor_phone' => '9876543210',
        'currency' => 'INR',
        'total_amount' => 500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    $response = actingAs($user)
        ->from(route('admin.donations.show', $order))
        ->post(route('admin.donations.receipt.resend', $order));

    $response
        ->assertRedirect(route('admin.donations.show', $order))
        ->assertSessionHas('status', 'Add a valid donor email on this donation before resending the receipt.')
        ->assertSessionHas('flash_tone', 'warning');

    Bus::assertNotDispatched(SendDonationReceiptJob::class);
});

it('returns not found or method not allowed for removed admin resource show routes', function () {
    $user = createAdminWithPermissions(['manage users', 'manage causes']);

    actingAs($user)->get('/admin/users/999')->assertStatus(405);
    actingAs($user)->get('/admin/roles/999')->assertStatus(405);
    actingAs($user)->get('/admin/permissions/999')->assertStatus(405);
});
