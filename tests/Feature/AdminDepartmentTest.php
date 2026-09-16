<?php

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

function actingDepartmentAdmin(): User
{
    Permission::firstOrCreate(['name' => 'manage users']);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $actor = User::factory()->create();
    $actor->assignRole('super_admin');

    return $actor;
}

it('creates and updates departments', function () {
    $actor = actingDepartmentAdmin();

    actingAs($actor)
        ->post(route('admin.departments.store'), [
            'name' => 'Volunteer Desk',
            'description' => 'Front desk volunteers',
            'is_active' => true,
            'sort_order' => 5,
        ])
        ->assertRedirect(route('admin.departments.index'));

    assertDatabaseHas('departments', [
        'name' => 'Volunteer Desk',
        'slug' => 'volunteer-desk',
    ]);

    $department = Department::query()->where('slug', 'volunteer-desk')->firstOrFail();

    actingAs($actor)
        ->put(route('admin.departments.update', $department), [
            'name' => 'Volunteer Support',
            'slug' => 'volunteer-support',
            'description' => 'Updated description',
            'is_active' => true,
            'sort_order' => 6,
        ])
        ->assertRedirect(route('admin.departments.index'));

    expect($department->fresh())
        ->name->toBe('Volunteer Support')
        ->slug->toBe('volunteer-support');
});

it('detects assigned users for a department', function () {
    $department = Department::factory()->create();
    User::factory()->create(['department_id' => $department->id]);

    expect(User::withTrashed()->where('department_id', $department->id)->exists())->toBeTrue();
});

it('prevents deleting departments with assigned users', function () {
    $actor = actingDepartmentAdmin();
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);

    assertDatabaseHas('users', [
        'id' => $user->id,
        'department_id' => $department->id,
    ]);

    actingAs($actor)
        ->delete(route('admin.departments.destroy', $department))
        ->assertRedirect(route('admin.departments.index'));

    expect(Department::query()->find($department->id))->not->toBeNull()
        ->and($user->fresh()->department_id)->toBe($department->id);
});

it('deletes empty departments', function () {
    $actor = actingDepartmentAdmin();
    $department = Department::factory()->create();

    actingAs($actor)
        ->delete(route('admin.departments.destroy', $department))
        ->assertRedirect(route('admin.departments.index'))
        ->assertSessionHas('status');

    expect(Department::query()->find($department->id))->toBeNull();
});
