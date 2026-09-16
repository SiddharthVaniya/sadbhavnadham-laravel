<?php

use App\Helpers\NumberHelper;
use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('renders the minimal english receipt with cause and package', function () {
    Permission::firstOrCreate(['name' => 'manage receipts']);
    Permission::firstOrCreate(['name' => 'view all donations']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo(['manage receipts', 'view all donations']);

    $admin = User::factory()->create();
    $admin->assignRole($role);

    $cause = Cause::factory()->create([
        'title' => 'Tree Plantation',
        'slug' => 'tree-plantation',
        'is_active' => true,
    ]);
    $package = CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'title' => 'Sapling Pack',
        'amount' => 1000,
        'is_active' => true,
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'donor_name' => 'John Doe',
        'donor_email' => 'john.doe@example.com',
        'donor_phone' => '9999999999',
        'total_amount' => 1000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now()->setDate(2026, 1, 1),
        'receipt_number' => 1234,
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause_package_id' => $package->id,
        'cause' => $cause->slug,
        'title' => $package->title,
        'quantity' => 1,
        'unit_amount' => 1000,
        'amount' => 1000,
    ]);

    actingAs($admin);

    $response = $this->get(route('admin.donations.receipt.preview', $order));

    $response->assertOk();
    $response->assertSee('DONATION RECEIPT', false);
    $response->assertSee('DONOR', false);
    $response->assertSee('Authorized Signatory', false);
    $response->assertSee('AADTM7770L', false);
    $response->assertSee('E-9897 Rajkot', false);
    $response->assertSee('AADTM7770LF20216', false);
    $response->assertSee('Tree Plantation', false);
    $response->assertSee('Package: Sapling Pack', false);
    $response->assertSee('john.doe@example.com', false);
    $response->assertSee('+91 9999999999', false);
    $response->assertSee(NumberHelper::formatInr(1000), false);
    $response->assertSee($order->receiptNumberFormatted(), false);
    $response->assertSee("font-family: 'Poppins'", false);
    $response->assertSee('Poppins-Regular.ttf', false);
    $response->assertDontSee('ORGANISATION', false);
    $response->assertDontSee('THANK YOU FOR THE DONATION!', false);
    $response->assertDontSee('>Signature<', false);
    $response->assertDontSee('ધર્માનુરાગીશ્રી', false);
});

it('shows dedicated tree names on the english receipt', function () {
    Permission::firstOrCreate(['name' => 'manage receipts']);
    Permission::firstOrCreate(['name' => 'view all donations']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo(['manage receipts', 'view all donations']);

    $admin = User::factory()->create();
    $admin->assignRole($role);

    $cause = Cause::factory()->create([
        'title' => 'Tree Plantation',
        'slug' => 'tree-plantation',
        'is_active' => true,
    ]);
    $package = CausePackage::factory()->create([
        'cause_id' => $cause->id,
        'title' => 'Tree',
        'amount' => 3000,
        'is_active' => true,
    ]);

    $order = DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'donor_name' => 'John Doe',
        'donor_email' => 'john.doe@example.com',
        'donor_phone' => '9999999999',
        'total_amount' => 6000,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now()->setDate(2026, 1, 1),
        'receipt_number' => 1235,
    ]);

    DonationItem::create([
        'donation_order_id' => $order->id,
        'cause_id' => $cause->id,
        'cause_package_id' => $package->id,
        'cause' => $cause->slug,
        'title' => $package->title,
        'quantity' => 2,
        'unit_amount' => 3000,
        'amount' => 6000,
        'meta' => [
            'cause_title' => 'Tree Plantation',
            'honoree_names' => ['Asha Patel', 'Ramesh Patel'],
        ],
    ]);

    actingAs($admin);

    $this->get(route('admin.donations.receipt.preview', $order))
        ->assertOk()
        ->assertSee('Tree 1: Asha Patel', false)
        ->assertSee('Tree 2: Ramesh Patel', false);
});

it('keeps the legacy gujarati receipt blade available', function () {
    expect(view()->exists('receipts.donation'))->toBeTrue()
        ->and(view()->exists('receipts.donation-minimal'))->toBeTrue();
});
