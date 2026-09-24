<?php

use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\RazorpayQrCode;
use App\Models\User;
use App\Services\DonationPaymentService;
use App\Services\RazorpayQrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
    config(['payments.razorpay.qr_code_ids' => []]);
});

function createQrMappingAdmin(): User
{
    $permissions = ['view qr codes', 'create qr codes', 'close qr codes', 'sync qr codes'];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('includes collected amount and cause on the qr index', function () {
    $user = createQrMappingAdmin();
    $cause = Cause::factory()->create(['title' => 'Annadaan']);

    RazorpayQrCode::factory()->create([
        'name' => 'Counter QR',
        'cause_id' => $cause->id,
        'payments_count_received' => 3,
        'payments_amount_received_paise' => 150050,
    ]);

    actingAs($user)
        ->get(route('admin.qr-codes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/QrCodes/Index')
            ->where('qrCodes.data.0.payments_amount_received', 1500.5)
            ->where('qrCodes.data.0.cause_title', 'Annadaan'));
});

it('updates local cause mapping without calling razorpay', function () {
    $user = createQrMappingAdmin();
    $cause = Cause::factory()->create();
    $package = CausePackage::factory()->create(['cause_id' => $cause->id]);
    $qr = RazorpayQrCode::factory()->create();

    $this->mock(RazorpayQrCodeService::class, function ($mock) use ($qr, $cause, $package): void {
        $mock->shouldReceive('updateLocalMapping')
            ->once()
            ->withArgs(function (RazorpayQrCode $model, array $input) use ($qr, $cause, $package): bool {
                return $model->is($qr)
                    && $input['cause_id'] === $cause->id
                    && $input['cause_package_id'] === $package->id;
            })
            ->andReturn($qr);
    });

    actingAs($user)
        ->put(route('admin.qr-codes.update', $qr), [
            'cause_id' => $cause->id,
            'cause_package_id' => $package->id,
        ])
        ->assertRedirect();
});

it('persists cause mapping through the real service', function () {
    $user = createQrMappingAdmin();
    $cause = Cause::factory()->create();
    $package = CausePackage::factory()->create(['cause_id' => $cause->id]);
    $qr = RazorpayQrCode::factory()->create();

    actingAs($user)
        ->put(route('admin.qr-codes.update', $qr), [
            'cause_id' => $cause->id,
            'cause_package_id' => $package->id,
        ])
        ->assertRedirect();

    $qr->refresh();

    expect($qr->cause_id)->toBe($cause->id)
        ->and($qr->cause_package_id)->toBe($package->id);
});

it('rejects a package that does not belong to the cause', function () {
    $user = createQrMappingAdmin();
    $cause = Cause::factory()->create();
    $otherPackage = CausePackage::factory()->create();
    $qr = RazorpayQrCode::factory()->create();

    actingAs($user)
        ->put(route('admin.qr-codes.update', $qr), [
            'cause_id' => $cause->id,
            'cause_package_id' => $otherPackage->id,
        ])
        ->assertSessionHasErrors('cause_package_id');
});

it('creates a donation item when a mapped qr payment is captured', function () {
    $cause = Cause::factory()->create([
        'title' => 'Gau Seva',
        'slug' => 'gau-seva',
    ]);
    $package = CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'title' => 'Feed 10 cows',
    ]);

    RazorpayQrCode::factory()->create([
        'razorpay_qr_code_id' => 'qr_mapped_001',
        'name' => 'Gaushala Counter',
        'cause_id' => $cause->id,
        'cause_package_id' => $package->id,
    ]);

    app(DonationPaymentService::class)->handleCaptured([
        'id' => 'pay_qr_mapped_001',
        'entity' => 'payment',
        'amount' => 25000,
        'currency' => 'INR',
        'status' => 'captured',
        'order_id' => null,
        'method' => 'upi',
        'vpa' => 'donor@okaxis',
        'email' => null,
        'contact' => null,
        'qr_code_id' => 'qr_mapped_001',
        'created_at' => now()->timestamp,
        'notes' => [],
    ]);

    $order = DonationOrder::query()->where('provider_payment_id', 'pay_qr_mapped_001')->first();

    expect($order)->not->toBeNull();

    $item = DonationItem::query()->where('donation_order_id', $order->id)->first();

    expect($item)->not->toBeNull()
        ->and($item->cause_id)->toBe($cause->id)
        ->and($item->cause_package_id)->toBe($package->id)
        ->and($item->cause)->toBe('gau-seva')
        ->and($item->title)->toBe('Feed 10 cows')
        ->and((float) $item->amount)->toBe(250.0);
});

it('skips donation item when qr has no cause mapping', function () {
    RazorpayQrCode::factory()->create([
        'razorpay_qr_code_id' => 'qr_unmapped_001',
        'cause_id' => null,
    ]);

    app(DonationPaymentService::class)->handleCaptured([
        'id' => 'pay_qr_unmapped_001',
        'entity' => 'payment',
        'amount' => 10000,
        'currency' => 'INR',
        'status' => 'captured',
        'order_id' => null,
        'method' => 'upi',
        'vpa' => 'donor@okaxis',
        'qr_code_id' => 'qr_unmapped_001',
        'created_at' => now()->timestamp,
        'notes' => [],
    ]);

    $order = DonationOrder::query()->where('provider_payment_id', 'pay_qr_unmapped_001')->first();

    expect($order)->not->toBeNull()
        ->and(DonationItem::query()->where('donation_order_id', $order->id)->exists())->toBeFalse();
});
