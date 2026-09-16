<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCausePackageRequest;
use App\Http\Requests\Admin\UpdateCausePackageRequest;
use App\Models\Cause;
use App\Models\CausePackage;
use App\Support\AdminInertiaResources;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminCausePackageController extends Controller
{
    public function index(): Response
    {
        $viewer = auth()->user();
        $packages = CausePackage::query()
            ->with('cause')
            ->orderBy('cause_id')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(AdminInertiaResources::LIST_PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Packages/Index', [
            'packages' => AdminInertiaResources::paginated(
                $packages,
                fn (CausePackage $package) => AdminInertiaResources::packageListRow($package, $viewer)
            ),
            'abilities' => AdminPermissions::packageAbilities($viewer),
        ]);
    }

    public function create(Cause $cause): Response
    {
        return Inertia::render('Admin/Packages/Form', [
            'cause' => [
                'id' => $cause->id,
                'title' => $cause->title,
                'allow_recurring' => (bool) $cause->allow_recurring,
                'allow_weekly_recurring' => (bool) $cause->allow_weekly_recurring,
            ],
            'package' => null,
            'isEdit' => false,
            'subscriptionsEnabled' => (bool) config('payments.razorpay.subscriptions_enabled', false),
        ]);
    }

    public function store(StoreCausePackageRequest $request, Cause $cause): RedirectResponse
    {
        $package = $cause->packages()->create($this->buildPayload($request));

        $this->syncDefaultPackage($cause, $package);

        return redirect()
            ->route('admin.causes.edit', $cause)
            ->with('status', 'Package created.');
    }

    public function edit(Cause $cause, CausePackage $package): Response
    {
        $this->ensurePackageBelongsToCause($cause, $package);

        return Inertia::render('Admin/Packages/Form', [
            'cause' => [
                'id' => $cause->id,
                'title' => $cause->title,
                'allow_recurring' => (bool) $cause->allow_recurring,
                'allow_weekly_recurring' => (bool) $cause->allow_weekly_recurring,
            ],
            'package' => AdminInertiaResources::package($package),
            'isEdit' => true,
            'subscriptionsEnabled' => (bool) config('payments.razorpay.subscriptions_enabled', false),
        ]);
    }

    public function update(UpdateCausePackageRequest $request, Cause $cause, CausePackage $package): RedirectResponse
    {
        $this->ensurePackageBelongsToCause($cause, $package);

        $package->update($this->buildPayload($request));

        $this->syncDefaultPackage($cause, $package);

        return redirect()
            ->route('admin.causes.edit', $cause)
            ->with('status', 'Package updated.');
    }

    public function toggleActive(Cause $cause, CausePackage $package): \Illuminate\Http\JsonResponse
    {
        $this->ensurePackageBelongsToCause($cause, $package);

        $package->update(['is_active' => ! $package->is_active]);

        return response()->json(['is_active' => $package->is_active]);
    }

    public function toggleDefault(Cause $cause, CausePackage $package): RedirectResponse
    {
        $this->ensurePackageBelongsToCause($cause, $package);

        $package->update(['is_default' => ! $package->is_default]);

        $this->syncDefaultPackage($cause, $package);

        return redirect()
            ->route('admin.causes.edit', $cause)
            ->with('status', $package->is_default ? 'Default package updated.' : 'Default package cleared.');
    }

    public function destroy(Cause $cause, CausePackage $package): RedirectResponse
    {
        $this->ensurePackageBelongsToCause($cause, $package);

        $package->delete();

        return redirect()
            ->route('admin.causes.edit', $cause)
            ->with('status', 'Package deleted.');
    }

    private function ensurePackageBelongsToCause(Cause $cause, CausePackage $package): void
    {
        abort_if($package->cause_id !== $cause->id, 404);
    }

    private function buildPayload(StoreCausePackageRequest|UpdateCausePackageRequest $request): array
    {
        $validated = $request->validated();

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_default'] = $request->boolean('is_default');
        $validated['allow_recurring'] = $request->boolean('allow_recurring');
        $validated['image'] = $this->handlePackageImage($request);
        unset($validated['image_existing']);

        return $validated;
    }

    private function syncDefaultPackage(Cause $cause, CausePackage $package): void
    {
        if (! $package->is_default) {
            return;
        }

        $cause->packages()
            ->whereKeyNot($package->getKey())
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    private function handlePackageImage(StoreCausePackageRequest|UpdateCausePackageRequest $request): ?string
    {
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('causes/packages', 'public');

            return 'storage/'.$path;
        }

        return $request->input('image_existing');
    }
}
