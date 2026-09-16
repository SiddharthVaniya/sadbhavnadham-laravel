<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminInertiaResources;
use App\Support\AdminPermissions;
use App\Support\AdminRoleGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::with('permissions')->paginate(AdminInertiaResources::LIST_PER_PAGE)->withQueryString();

        return Inertia::render('Admin/Roles/Index', [
            'roles' => AdminInertiaResources::paginatedRoles($roles),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Roles/Form', [
            'role' => null,
            'permissionGroups' => AdminPermissions::groupedDefinitions(),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|unique:roles,name',
            'permissions' => 'array',
        ]);

        AdminRoleGuard::assertCanCreateRoleName($request->user(), (string) $request->input('name'));

        $role = Role::create(['name' => $request->name]);
        $role->syncPermissions($request->permissions ?? []);

        return redirect()->route('admin.roles.index')->with('status', 'Role created successfully.');
    }

    public function edit(Role $role): Response
    {
        AdminRoleGuard::assertCanManageRole(auth()->user(), $role);

        $role->load('permissions');

        return Inertia::render('Admin/Roles/Form', [
            'role' => AdminInertiaResources::role($role),
            'permissionGroups' => AdminPermissions::groupedDefinitions(),
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        AdminRoleGuard::assertCanManageRole($request->user(), $role);

        $request->validate([
            'name' => 'required|unique:roles,name,'.$role->id,
            'permissions' => 'array',
        ]);

        AdminRoleGuard::assertCanCreateRoleName($request->user(), (string) $request->input('name'));

        $role->update(['name' => $request->name]);
        $role->syncPermissions($request->permissions ?? []);

        return redirect()->route('admin.roles.index')->with('status', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === AdminRoleGuard::SUPER_ADMIN) {
            return back()->with('error', 'Cannot delete Super Admin.');
        }

        AdminRoleGuard::assertCanManageRole(auth()->user(), $role);

        $role->delete();

        return back()->with('status', 'Role deleted.');
    }
}
