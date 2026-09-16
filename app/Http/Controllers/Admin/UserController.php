<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Models\MarketerMonthlyBudget;
use App\Support\AdminInertiaResources;
use App\Support\AdminPermissions;
use App\Support\AdminRoleGuard;
use App\Support\MarketerMonthlyBudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $status = in_array($request->string('status')->toString(), ['active', 'archived', 'all'], true)
            ? $request->string('status')->toString()
            : 'active';

        $query = User::query()->with(['roles', 'department']);

        match ($status) {
            'archived' => $query->onlyTrashed(),
            'all' => $query->withTrashed(),
            default => null,
        };

        if ($search = trim($request->string('search')->toString())) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('referral_code', 'like', $like);
            });
        }

        if ($role = trim($request->string('role')->toString())) {
            $query->role($role);
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        $sort = $request->string('sort')->toString();
        $dir = $request->string('dir')->toString() === 'desc' ? 'desc' : 'asc';
        $sortColumn = in_array($sort, ['name', 'email', 'created_at'], true) ? $sort : 'name';
        $query->orderBy($sortColumn, $dir);

        $users = $query->paginate(AdminInertiaResources::LIST_PER_PAGE)->withQueryString();

        $roleNames = Role::query()->orderBy('name')->pluck('name')->all();

        $activeFilters = collect([
            $request->string('search')->toString(),
            $request->string('role')->toString(),
            $request->input('department_id'),
        ])->filter(fn ($value) => filled($value))->count();

        if ($status !== 'active') {
            $activeFilters++;
        }

        return Inertia::render('Admin/Users/Index', [
            'users' => AdminInertiaResources::paginatedUsers($users, $actor),
            'stats' => [
                'active' => User::count(),
                'archived' => User::onlyTrashed()->count(),
                'super_admins' => User::role(AdminRoleGuard::SUPER_ADMIN)->count(),
                'with_referral' => User::whereNotNull('referral_code')->count(),
            ],
            'filters' => [
                'search' => $request->string('search')->toString(),
                'role' => $request->string('role')->toString(),
                'department_id' => $request->input('department_id', ''),
                'status' => $status,
                'sort' => $sortColumn,
                'dir' => $dir,
            ],
            'roleOptions' => collect($roleNames)
                ->map(fn (string $name) => ['value' => $name, 'label' => $name])
                ->values()
                ->all(),
            'departmentOptions' => AdminInertiaResources::departmentOptions(),
            'activeFilterCount' => $activeFilters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'user' => null,
            'roles' => AdminRoleGuard::assignableRoles(auth()->user())
                ->map(fn (Role $role) => ['id' => $role->id, 'name' => $role->name])
                ->values()
                ->all(),
            'departmentOptions' => AdminInertiaResources::departmentOptions(includeInactive: true),
            'permissionGroups' => AdminPermissions::groupedDefinitions(),
            'isEdit' => false,
            'currentYearMonth' => MarketerMonthlyBudget::currentYearMonth(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        AdminRoleGuard::assertCanAssignRoles($request->user(), $request->input('roles', []));

        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'referral_code' => $request->input('referral_code'),
            'donation_target' => $request->input('donation_target'),
            'department_id' => $request->input('department_id'),
            'password' => Hash::make($request->string('password')->toString()),
            'email_verified_at' => now(),
        ]);

        $user->syncRoles($request->input('roles', []));
        $user->syncPermissions($request->input('permissions', []));

        MarketerMonthlyBudgetService::upsertForCurrentMonth(
            $user,
            $request->filled('monthly_target_amount') ? $request->integer('monthly_target_amount') : null,
            $request->input('monthly_spend_amount'),
        );

        return redirect()->route('admin.users.index')->with('status', 'User created successfully.');
    }

    public function edit(User $user): Response
    {
        AdminRoleGuard::assertCanManageUser(auth()->user(), $user);

        if ($user->trashed()) {
            abort(404);
        }

        $user->load(['roles', 'department', 'permissions']);

        return Inertia::render('Admin/Users/Form', [
            'user' => AdminInertiaResources::user($user),
            'roles' => AdminRoleGuard::assignableRoles(auth()->user())
                ->map(fn (Role $role) => ['id' => $role->id, 'name' => $role->name])
                ->values()
                ->all(),
            'departmentOptions' => AdminInertiaResources::departmentOptions(includeInactive: true),
            'permissionGroups' => AdminPermissions::groupedDefinitions(),
            'isEdit' => true,
            'currentYearMonth' => MarketerMonthlyBudget::currentYearMonth(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        AdminRoleGuard::assertCanManageUser($request->user(), $user);

        if ($user->trashed()) {
            abort(404);
        }

        AdminRoleGuard::assertCanAssignRoles($request->user(), $request->input('roles', []));
        AdminRoleGuard::assertCanRemoveSuperAdminRole($user, $request->input('roles', []));

        $data = [
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'referral_code' => $request->input('referral_code'),
            'donation_target' => $request->input('donation_target'),
            'department_id' => $request->input('department_id'),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->string('password')->toString());
        }

        $user->update($data);
        $user->syncRoles($request->input('roles', []));
        $user->syncPermissions($request->input('permissions', []));

        MarketerMonthlyBudgetService::upsertForCurrentMonth(
            $user,
            $request->filled('monthly_target_amount') ? $request->integer('monthly_target_amount') : null,
            $request->input('monthly_spend_amount'),
        );

        return redirect()->route('admin.users.index')->with('status', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        AdminRoleGuard::assertCanDeleteUser(auth()->user(), $user);

        $user->delete();

        return back()->with('status', 'User archived successfully. You can restore them from the Archived tab.');
    }

    public function restore(Request $request, int $userId): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($userId);

        AdminRoleGuard::assertCanRestoreUser($request->user(), $user);

        $user->restore();

        return back()->with('status', 'User restored successfully.');
    }
}
