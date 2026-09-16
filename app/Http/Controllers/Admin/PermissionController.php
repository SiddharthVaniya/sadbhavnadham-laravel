<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminInertiaResources;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): Response
    {
        $permissions = Permission::query()
            ->orderBy('name')
            ->paginate(AdminInertiaResources::LIST_PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Permissions/Index', [
            'permissions' => AdminInertiaResources::paginated(
                $permissions,
                fn (Permission $permission) => AdminInertiaResources::permission($permission)
            ),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Permissions/Form', [
            'permission' => null,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|unique:permissions,name',
        ]);

        Permission::create(['name' => $request->name]);

        return redirect()->route('admin.permissions.index')->with('status', 'Permission created.');
    }

    public function edit(Permission $permission): Response
    {
        return Inertia::render('Admin/Permissions/Form', [
            'permission' => AdminInertiaResources::permission($permission),
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $request->validate([
            'name' => 'required|unique:permissions,name,'.$permission->id,
        ]);

        $permission->update(['name' => $request->name]);

        return redirect()->route('admin.permissions.index')->with('status', 'Permission updated.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $permission->delete();

        return back()->with('status', 'Permission deleted.');
    }
}
