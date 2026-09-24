<?php

use App\Models\RazorpayQrCode;
use App\Models\User;
use App\Services\RazorpayQrCodeService;
use App\Services\RazorpayQrPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createQrCodesAdmin(array $permissions = ['view qr codes', 'create qr codes', 'close qr codes', 'sync qr codes']): User
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

it('lists razorpay qr codes for authorized admins', function () {
    $user = createQrCodesAdmin(['view qr codes']);

    RazorpayQrCode::factory()->create(['name' => 'Temple Counter']);

    actingAs($user)
        ->get(route('admin.qr-codes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/QrCodes/Index')
            ->where('qrCodes.data.0.name', 'Temple Counter'));
});

it('stores a qr code when service succeeds', function () {
    $user = createQrCodesAdmin();

    $created = RazorpayQrCode::factory()->create([
        'name' => 'Event Stall',
        'razorpay_qr_code_id' => 'qr_created_test',
    ]);

    $this->mock(RazorpayQrCodeService::class, function ($mock) use ($created): void {
        $mock->shouldReceive('create')->once()->andReturn($created);
    });

    actingAs($user)
        ->post(route('admin.qr-codes.store'), [
            'name' => 'Event Stall',
            'usage' => 'multiple_use',
            'fixed_amount' => false,
        ])
        ->assertRedirect(route('admin.qr-codes.show', $created));
});

it('closes a qr code', function () {
    $user = createQrCodesAdmin();
    $qr = RazorpayQrCode::factory()->create(['status' => 'active']);

    $closed = $qr->fresh();
    $closed->status = 'closed';

    $this->mock(RazorpayQrCodeService::class, function ($mock) use ($closed): void {
        $mock->shouldReceive('close')->once()->andReturn($closed);
    });

    actingAs($user)
        ->post(route('admin.qr-codes.close', $qr))
        ->assertRedirect();
});

it('merges local active qr ids into reconcile list', function () {
    config(['payments.razorpay.qr_code_ids' => ['qr_from_env']]);

    RazorpayQrCode::factory()->create(['razorpay_qr_code_id' => 'qr_from_admin', 'status' => 'active']);
    RazorpayQrCode::factory()->closed()->create(['razorpay_qr_code_id' => 'qr_closed']);

    $ids = app(RazorpayQrPaymentService::class)->reconcileQrCodeIds();

    expect($ids)->toContain('qr_from_env')
        ->and($ids)->toContain('qr_from_admin')
        ->and($ids)->not->toContain('qr_closed');
});

it('keeps accept-all webhook behaviour when env whitelist is empty', function () {
    config(['payments.razorpay.qr_code_ids' => []]);

    RazorpayQrCode::factory()->create(['razorpay_qr_code_id' => 'qr_local_only']);

    expect(app(RazorpayQrPaymentService::class)->configuredQrCodeIds())->toBe([]);
});

it('forbids qr index without permission', function () {
    Permission::firstOrCreate(['name' => 'view donations']);
    $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $role->syncPermissions(['view donations']);
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user)
        ->get(route('admin.qr-codes.index'))
        ->assertForbidden();
});
