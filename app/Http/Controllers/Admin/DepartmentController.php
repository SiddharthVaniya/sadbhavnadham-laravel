<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Http\Requests\Admin\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\User;
use App\Support\AdminInertiaResources;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(): Response
    {
        $departments = Department::query()
            ->withCount('users')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(AdminInertiaResources::LIST_PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Departments/Index', [
            'departments' => AdminInertiaResources::paginatedDepartments($departments),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Departments/Form', [
            'department' => null,
            'isEdit' => false,
        ]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        Department::query()->create([
            'name' => $request->string('name')->toString(),
            'slug' => $request->string('slug')->toString(),
            'description' => $request->input('description'),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) $request->input('sort_order', 0),
        ]);

        return redirect()->route('admin.departments.index')->with('status', 'Department created successfully.');
    }

    public function edit(Department $department): Response
    {
        return Inertia::render('Admin/Departments/Form', [
            'department' => AdminInertiaResources::department($department),
            'isEdit' => true,
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update([
            'name' => $request->string('name')->toString(),
            'slug' => $request->string('slug')->toString(),
            'description' => $request->input('description'),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) $request->input('sort_order', 0),
        ]);

        return redirect()->route('admin.departments.index')->with('status', 'Department updated successfully.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        if (User::withTrashed()->where('department_id', $department->id)->exists()) {
            return redirect()
                ->route('admin.departments.index')
                ->with('error', 'Cannot delete a department that still has assigned users. Reassign users first.');
        }

        $department->delete();

        return redirect()
            ->route('admin.departments.index')
            ->with('status', 'Department deleted successfully.');
    }
}
