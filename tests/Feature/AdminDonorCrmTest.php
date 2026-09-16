<?php

use App\Models\DonationOrder;
use App\Models\Donor;
use App\Models\DonorNote;
use App\Models\DonorTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createCrmViewer(): User
{
    Permission::firstOrCreate(['name' => 'view donors']);

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo('view donors');

    $user = User::factory()->create(['name' => 'Viewer Staff']);
    $user->assignRole($role);

    return $user;
}

function createCrmManager(): User
{
    foreach (['view donors', 'manage donor crm'] as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['view donors', 'manage donor crm']);

    $user = User::factory()->create(['name' => 'CRM Manager']);
    $user->assignRole($role);

    return $user;
}

function createCrmDonorWithOrder(array $donorOverrides = []): Donor
{
    $donor = Donor::factory()->create(array_merge([
        'name' => 'CRM Donor',
        'email' => 'crm-donor@example.com',
        'phone' => '9898111222',
    ], $donorOverrides));

    DonationOrder::create([
        'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
        'provider_order_id' => 'order_crm_'.fake()->unique()->numerify('#####'),
        'donor_id' => $donor->id,
        'donor_name' => $donor->name,
        'donor_email' => $donor->email,
        'donor_phone' => $donor->phone,
        'currency' => 'INR',
        'total_amount' => 1500,
        'status' => DonationOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    return $donor->fresh();
}

it('exposes crm payload on the donor show page for viewers', function () {
    $user = createCrmViewer();
    $owner = User::factory()->create(['name' => 'Owner Person']);
    $donor = createCrmDonorWithOrder(['owner_user_id' => $owner->id]);

    DonorNote::factory()->for($donor)->create([
        'user_id' => $user->id,
        'body' => 'Called donor about monthly seva.',
    ]);

    DonorTask::factory()->for($donor)->open()->create([
        'created_by' => $user->id,
        'assigned_to' => $owner->id,
        'title' => 'Follow up after Holi',
        'due_at' => now()->addDays(2),
    ]);

    actingAs($user)
        ->get(route('admin.donors.show', $donor))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donors/Show')
            ->where('crm.can_manage', false)
            ->where('crm.owner.name', 'Owner Person')
            ->where('crm.open_tasks_count', 1)
            ->has('crm.notes', 1)
            ->where('crm.notes.0.body', 'Called donor about monthly seva.')
            ->has('crm.tasks', 1)
            ->where('crm.tasks.0.title', 'Follow up after Holi')
            ->where('crm.staff_options', []));
});

it('forbids crm writes without manage donor crm permission', function () {
    $user = createCrmViewer();
    $donor = createCrmDonorWithOrder();

    actingAs($user)
        ->put(route('admin.donors.owner.update', $donor), ['owner_user_id' => $user->id])
        ->assertForbidden();

    actingAs($user)
        ->post(route('admin.donors.notes.store', $donor), ['body' => 'Secret note'])
        ->assertForbidden();

    actingAs($user)
        ->post(route('admin.donors.tasks.store', $donor), ['title' => 'Secret task'])
        ->assertForbidden();

    expect(DonorNote::query()->count())->toBe(0)
        ->and(DonorTask::query()->count())->toBe(0)
        ->and($donor->fresh()->owner_user_id)->toBeNull();
});

it('assigns and clears a donor owner', function () {
    $manager = createCrmManager();
    $owner = User::factory()->create(['name' => 'Riya']);
    $donor = createCrmDonorWithOrder();

    actingAs($manager)
        ->from(route('admin.donors.show', $donor))
        ->put(route('admin.donors.owner.update', $donor), [
            'owner_user_id' => $owner->id,
        ])
        ->assertRedirect(route('admin.donors.show', $donor))
        ->assertSessionHas('status', 'Donor owner updated.');

    expect($donor->fresh()->owner_user_id)->toBe($owner->id);

    actingAs($manager)
        ->put(route('admin.donors.owner.update', $donor), [
            'owner_user_id' => null,
        ])
        ->assertRedirect(route('admin.donors.show', $donor));

    expect($donor->fresh()->owner_user_id)->toBeNull();
});

it('rejects an invalid owner user id', function () {
    $manager = createCrmManager();
    $donor = createCrmDonorWithOrder();

    actingAs($manager)
        ->from(route('admin.donors.show', $donor))
        ->put(route('admin.donors.owner.update', $donor), [
            'owner_user_id' => 999999,
        ])
        ->assertRedirect(route('admin.donors.show', $donor))
        ->assertSessionHasErrors('owner_user_id');
});

it('creates and deletes donor notes with author attribution', function () {
    $manager = createCrmManager();
    $donor = createCrmDonorWithOrder();

    actingAs($manager)
        ->post(route('admin.donors.notes.store', $donor), [
            'body' => 'Prefers phone calls after 6pm.',
        ])
        ->assertRedirect(route('admin.donors.show', $donor));

    $note = DonorNote::query()->first();

    expect($note)->not->toBeNull()
        ->and($note->donor_id)->toBe($donor->id)
        ->and($note->user_id)->toBe($manager->id)
        ->and($note->body)->toBe('Prefers phone calls after 6pm.');

    actingAs($manager)
        ->delete(route('admin.donors.notes.destroy', [$donor, $note]))
        ->assertRedirect(route('admin.donors.show', $donor));

    expect(DonorNote::query()->count())->toBe(0);
});

it('validates note body is required', function () {
    $manager = createCrmManager();
    $donor = createCrmDonorWithOrder();

    actingAs($manager)
        ->from(route('admin.donors.show', $donor))
        ->post(route('admin.donors.notes.store', $donor), [
            'body' => ' ',
        ])
        ->assertRedirect(route('admin.donors.show', $donor))
        ->assertSessionHasErrors('body');
});

it('creates updates and deletes donor tasks including done status', function () {
    $manager = createCrmManager();
    $assignee = User::factory()->create(['name' => 'Amit']);
    $donor = createCrmDonorWithOrder();

    actingAs($manager)
        ->post(route('admin.donors.tasks.store', $donor), [
            'title' => 'Ask for monthly upgrade',
            'body' => 'Donor mentioned interest in ₹2,500 monthly.',
            'assigned_to' => $assignee->id,
            'due_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
        ])
        ->assertRedirect(route('admin.donors.show', $donor));

    $task = DonorTask::query()->first();

    expect($task)->not->toBeNull()
        ->and($task->status)->toBe(DonorTask::STATUS_OPEN)
        ->and($task->created_by)->toBe($manager->id)
        ->and($task->assigned_to)->toBe($assignee->id)
        ->and($task->title)->toBe('Ask for monthly upgrade')
        ->and($task->completed_at)->toBeNull();

    actingAs($manager)
        ->put(route('admin.donors.tasks.update', [$donor, $task]), [
            'title' => 'Ask for monthly upgrade',
            'body' => 'Confirmed interest.',
            'assigned_to' => $assignee->id,
            'due_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'status' => DonorTask::STATUS_DONE,
        ])
        ->assertRedirect(route('admin.donors.show', $donor));

    $task->refresh();

    expect($task->status)->toBe(DonorTask::STATUS_DONE)
        ->and($task->completed_at)->not->toBeNull()
        ->and($task->body)->toBe('Confirmed interest.');

    actingAs($manager)
        ->put(route('admin.donors.tasks.update', [$donor, $task]), [
            'status' => DonorTask::STATUS_OPEN,
            'title' => 'Ask for monthly upgrade',
        ])
        ->assertRedirect(route('admin.donors.show', $donor));

    expect($task->fresh()->completed_at)->toBeNull()
        ->and($task->fresh()->status)->toBe(DonorTask::STATUS_OPEN);

    actingAs($manager)
        ->delete(route('admin.donors.tasks.destroy', [$donor, $task]))
        ->assertRedirect(route('admin.donors.show', $donor));

    expect(DonorTask::query()->count())->toBe(0);
});

it('rejects invalid task status values', function () {
    $manager = createCrmManager();
    $donor = createCrmDonorWithOrder();
    $task = DonorTask::factory()->for($donor)->open()->create([
        'created_by' => $manager->id,
    ]);

    actingAs($manager)
        ->from(route('admin.donors.show', $donor))
        ->put(route('admin.donors.tasks.update', [$donor, $task]), [
            'title' => $task->title,
            'status' => 'archived',
        ])
        ->assertRedirect(route('admin.donors.show', $donor))
        ->assertSessionHasErrors('status');
});

it('prevents cross-donor note and task mutations', function () {
    $manager = createCrmManager();
    $donorA = createCrmDonorWithOrder(['email' => 'a@example.com', 'phone' => '9000000001']);
    $donorB = createCrmDonorWithOrder(['email' => 'b@example.com', 'phone' => '9000000002']);

    $note = DonorNote::factory()->for($donorA)->create(['user_id' => $manager->id]);
    $task = DonorTask::factory()->for($donorA)->create(['created_by' => $manager->id]);

    actingAs($manager)
        ->delete(route('admin.donors.notes.destroy', [$donorB, $note]))
        ->assertNotFound();

    actingAs($manager)
        ->put(route('admin.donors.tasks.update', [$donorB, $task]), [
            'title' => 'Hijack',
            'status' => DonorTask::STATUS_DONE,
        ])
        ->assertNotFound();

    expect($note->fresh())->not->toBeNull()
        ->and($task->fresh()->title)->not->toBe('Hijack');
});

it('allows crm notes on donors without donation history', function () {
    $manager = createCrmManager();
    $orphan = Donor::factory()->create([
        'email' => 'orphan@example.com',
        'phone' => '9111111111',
    ]);

    actingAs($manager)
        ->post(route('admin.donors.notes.store', $orphan), [
            'body' => 'Imported contact follow-up.',
        ])
        ->assertRedirect(route('admin.donors.show', $orphan));

    expect(DonorNote::query()->where('donor_id', $orphan->id)->value('body'))
        ->toBe('Imported contact follow-up.');
});

it('filters donors index by owner and shows open task counts', function () {
    $manager = createCrmManager();
    $owner = User::factory()->create(['name' => 'Owner Filter']);

    $owned = createCrmDonorWithOrder([
        'name' => 'Owned Donor',
        'email' => 'owned@example.com',
        'phone' => '9222222222',
        'owner_user_id' => $owner->id,
    ]);

    createCrmDonorWithOrder([
        'name' => 'Other Donor',
        'email' => 'other@example.com',
        'phone' => '9333333333',
    ]);

    DonorTask::factory()->for($owned)->open()->count(2)->create([
        'created_by' => $manager->id,
        'assigned_to' => $owner->id,
    ]);

    DonorTask::factory()->for($owned)->done()->create([
        'created_by' => $manager->id,
    ]);

    actingAs($manager)
        ->get(route('admin.donors.index', ['owner_user_id' => $owner->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Donors/Index')
            ->has('donors.data', 1)
            ->where('donors.data.0.id', $owned->id)
            ->where('donors.data.0.owner.name', 'Owner Filter')
            ->where('donors.data.0.open_tasks_count', 2)
            ->has('staffOptions'));
});

it('marks overdue open tasks on the show payload', function () {
    $user = createCrmViewer();
    $donor = createCrmDonorWithOrder();

    DonorTask::factory()->for($donor)->overdue()->create([
        'title' => 'Overdue call',
        'created_by' => $user->id,
    ]);

    actingAs($user)
        ->get(route('admin.donors.show', $donor))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('crm.overdue_tasks_count', 1)
            ->where('crm.tasks.0.is_overdue', true)
            ->where('crm.tasks.0.title', 'Overdue call'));
});

it('computes overdue helper on the donor task model', function () {
    $task = DonorTask::factory()->overdue()->make();

    expect($task->isOpen())->toBeTrue()
        ->and($task->isOverdue())->toBeTrue();

    $done = DonorTask::factory()->done()->make([
        'due_at' => now()->subDay(),
    ]);

    expect($done->isOverdue())->toBeFalse();
});

it('redirects guests away from crm write endpoints', function () {
    $donor = createCrmDonorWithOrder();

    $this->put(route('admin.donors.owner.update', $donor), ['owner_user_id' => 1])
        ->assertRedirect();

    $this->post(route('admin.donors.notes.store', $donor), ['body' => 'Guest note'])
        ->assertRedirect();

    expect(DonorNote::query()->count())->toBe(0);
});

it('requires a task title when creating a follow-up', function () {
    $manager = createCrmManager();
    $donor = createCrmDonorWithOrder();

    actingAs($manager)
        ->from(route('admin.donors.show', $donor))
        ->post(route('admin.donors.tasks.store', $donor), [
            'title' => '',
            'body' => 'Missing title should fail',
        ])
        ->assertRedirect(route('admin.donors.show', $donor))
        ->assertSessionHasErrors('title');

    expect(DonorTask::query()->count())->toBe(0);
});

it('cancels a task and clears completed_at', function () {
    $manager = createCrmManager();
    $donor = createCrmDonorWithOrder();
    $task = DonorTask::factory()->for($donor)->done()->create([
        'created_by' => $manager->id,
        'title' => 'Cancel me',
    ]);

    actingAs($manager)
        ->put(route('admin.donors.tasks.update', [$donor, $task]), [
            'title' => 'Cancel me',
            'status' => DonorTask::STATUS_CANCELLED,
        ])
        ->assertRedirect(route('admin.donors.show', $donor));

    expect($task->fresh()->status)->toBe(DonorTask::STATUS_CANCELLED)
        ->and($task->fresh()->completed_at)->toBeNull();
});

it('rejects an invalid task assignee', function () {
    $manager = createCrmManager();
    $donor = createCrmDonorWithOrder();

    actingAs($manager)
        ->from(route('admin.donors.show', $donor))
        ->post(route('admin.donors.tasks.store', $donor), [
            'title' => 'Call donor',
            'assigned_to' => 999999,
        ])
        ->assertRedirect(route('admin.donors.show', $donor))
        ->assertSessionHasErrors('assigned_to');
});

it('forbids deleting notes without manage donor crm permission', function () {
    $viewer = createCrmViewer();
    $donor = createCrmDonorWithOrder();
    $note = DonorNote::factory()->for($donor)->create([
        'user_id' => $viewer->id,
        'body' => 'Protected note',
    ]);

    actingAs($viewer)
        ->delete(route('admin.donors.notes.destroy', [$donor, $note]))
        ->assertForbidden();

    expect(DonorNote::query()->whereKey($note->id)->exists())->toBeTrue();
});

it('shows unassigned owner as null for viewers without manage access', function () {
    $viewer = createCrmViewer();
    $donor = createCrmDonorWithOrder();

    actingAs($viewer)
        ->get(route('admin.donors.show', $donor))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('crm.can_manage', false)
            ->where('crm.owner', null)
            ->where('crm.owner_user_id', null)
            ->where('crm.open_tasks_count', 0)
            ->where('crm.overdue_tasks_count', 0)
            ->has('crm.notes', 0)
            ->has('crm.tasks', 0));
});
