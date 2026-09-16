<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCauseRequest;
use App\Http\Requests\Admin\UpdateCauseRequest;
use App\Models\AisensyAccount;
use App\Models\Cause;
use App\Support\AdminInertiaResources;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AdminCauseController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(AdminPermissions::middleware(AdminPermissions::CAUSE_VIEW), only: ['index']),
            new Middleware(AdminPermissions::middleware(AdminPermissions::CAUSE_CREATE), only: ['create', 'store']),
            new Middleware(AdminPermissions::middleware(AdminPermissions::CAUSE_EDIT), only: ['edit', 'update', 'toggleActive', 'reorder']),
            new Middleware(AdminPermissions::middleware(AdminPermissions::CAUSE_DELETE), only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $causes = Cause::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(AdminInertiaResources::LIST_PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Causes/Index', [
            'causes' => AdminInertiaResources::paginated(
                $causes,
                fn (Cause $cause) => AdminInertiaResources::causeListRow($cause, auth()->user())
            ),
            'stats' => [
                'total' => Cause::count(),
                'active' => Cause::where('is_active', true)->count(),
            ],
            'abilities' => AdminPermissions::causeAbilities(auth()->user()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Causes/Create', [
            'aisensyAccounts' => AisensyAccount::where('is_active', true)->get(['id', 'name']),
            'subscriptionsEnabled' => (bool) config('payments.razorpay.subscriptions_enabled', false),
        ]);
    }

    public function store(StoreCauseRequest $request): RedirectResponse
    {
        $cause = Cause::create($this->buildPayload($request));

        return redirect()
            ->route('admin.causes.edit', $cause)
            ->with('status', 'Cause created.');
    }

    public function edit(Cause $cause): Response
    {
        $cause->load('packages');

        return Inertia::render('Admin/Causes/Edit', [
            'cause' => AdminInertiaResources::cause($cause),
            'aisensyAccounts' => AisensyAccount::where('is_active', true)->get(['id', 'name']),
            'subscriptionsEnabled' => (bool) config('payments.razorpay.subscriptions_enabled', false),
        ]);
    }

    public function update(UpdateCauseRequest $request, Cause $cause): RedirectResponse
    {
        $cause->update($this->buildPayload($request));

        return redirect()
            ->route('admin.causes.edit', $cause)
            ->with('status', 'Cause updated.');
    }

    public function toggleActive(Cause $cause): \Illuminate\Http\JsonResponse
    {
        $cause->update(['is_active' => ! $cause->is_active]);

        return response()->json(['is_active' => $cause->is_active]);
    }

    public function reorder(Cause $cause, \Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $direction = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ])['direction'];

        $causes = Cause::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $index = $causes->search(fn (Cause $item): bool => $item->id === $cause->id);

        if ($index === false) {
            return back();
        }

        $neighborIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if (! isset($causes[$neighborIndex])) {
            return back();
        }

        $neighbor = $causes[$neighborIndex];
        $currentOrder = (int) $cause->sort_order;
        $neighborOrder = (int) $neighbor->sort_order;

        $cause->update(['sort_order' => $neighborOrder]);
        $neighbor->update(['sort_order' => $currentOrder]);

        return back();
    }

    public function destroy(Cause $cause): RedirectResponse
    {
        $cause->delete();

        return redirect()
            ->route('admin.causes.index')
            ->with('status', 'Cause deleted.');
    }

    private function buildPayload(StoreCauseRequest|UpdateCauseRequest $request): array
    {
        $validated = $request->validated();

        $validated['allow_custom_amount'] = $request->boolean('allow_custom_amount');
        $validated['allow_recurring'] = $request->boolean('allow_recurring');
        $validated['allow_weekly_recurring'] = $request->boolean('allow_weekly_recurring');
        $validated['pan_required'] = $request->boolean('pan_required');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['aisensy_thank_you_message_mode'] = $request->input('aisensy_thank_you_message_mode', 'template');
        $validated['aisensy_thank_you_message_template'] = $request->input('aisensy_thank_you_message_template');
        $validated['aisensy_thank_you_include_name'] = $request->boolean('aisensy_thank_you_include_name');
        $validated['aisensy_thank_you_include_amount'] = $request->boolean('aisensy_thank_you_include_amount');
        $validated['aisensy_thank_you_include_cause'] = $request->boolean('aisensy_thank_you_include_cause');
        $validated['aisensy_thank_you_include_receipt'] = $request->boolean('aisensy_thank_you_include_receipt');
        $validated['aisensy_send_thank_you'] = $request->boolean('aisensy_send_thank_you');
        $validated['aisensy_send_certificate'] = $request->boolean('aisensy_send_certificate');
        $validated['images'] = $this->handleImagesUpload($request);

        $validated['details'] = $this->linesToArray($request->input('details_text'));

        $validated['hero_image'] = $this->handleHeroImage($request);
        $validated['certificate_template'] = $this->handleCertificateTemplate($request);
        $validated['aisensy_thank_you_image'] = $this->handleAiSensyImage($request);
        $validated['icon_uri'] = $this->handleIconUri($request);
        $validated['icon_uri_active'] = $this->handleActiveIconUri($request);

        if ($validated['aisensy_thank_you_message_template'] !== null) {
            $validated['aisensy_thank_you_message_template'] = trim((string) $validated['aisensy_thank_you_message_template']);
        }

        unset(
            $validated['hero_image_existing'],
            $validated['certificate_template_existing'],
            $validated['remove_certificate_template'],
            $validated['aisensy_thank_you_image_existing'],
            $validated['remove_aisensy_thank_you_image'],
            $validated['icon_uri_existing'],
            $validated['icon_uri_active_existing']
        );

        return $validated;
    }

    private function handleImagesUpload(StoreCauseRequest|UpdateCauseRequest $request): array
    {
        $existingImages = $request->input('images_existing', []);

        if (! is_array($existingImages)) {
            $existingImages = [];
        }

        $uploadedImages = [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                if ($file->isValid()) {
                    $path = $file->store('causes/images', 'public');
                    $uploadedImages[] = 'storage/'.$path;
                }
            }
        }

        return array_values(array_filter(array_merge($existingImages, $uploadedImages)));
    }

    private function handleHeroImage(StoreCauseRequest|UpdateCauseRequest $request): ?string
    {
        if ($request->hasFile('hero_image')) {
            $path = $request->file('hero_image')->store('causes/hero', 'public');

            return 'storage/'.$path;
        }

        return $request->input('hero_image_existing');
    }

    private function handleCertificateTemplate(StoreCauseRequest|UpdateCauseRequest $request): ?string
    {
        if ($request->hasFile('certificate_template')) {
            $this->deleteStoredPublicImage($request->input('certificate_template_existing'));

            $path = $request->file('certificate_template')->store('causes/certificates', 'public');

            return 'storage/'.$path;
        }

        if ($request->boolean('remove_certificate_template')) {
            $this->deleteStoredPublicImage($request->input('certificate_template_existing'));

            return null;
        }

        return $request->input('certificate_template_existing');
    }

    private function handleAiSensyImage(StoreCauseRequest|UpdateCauseRequest $request): ?string
    {
        if ($request->hasFile('aisensy_thank_you_image')) {
            $this->deleteStoredPublicImage($request->input('aisensy_thank_you_image_existing'));

            $path = $request->file('aisensy_thank_you_image')->store('aisensy', 'public');

            return 'storage/'.$path;
        }

        if ($request->boolean('remove_aisensy_thank_you_image')) {
            $this->deleteStoredPublicImage($request->input('aisensy_thank_you_image_existing'));

            return null;
        }

        return $request->input('aisensy_thank_you_image_existing');
    }

    private function deleteStoredPublicImage(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        $relativePath = str_starts_with($path, 'storage/')
            ? substr($path, strlen('storage/'))
            : ltrim($path, '/');

        if ($relativePath !== '') {
            Storage::disk('public')->delete($relativePath);
        }
    }

    private function handleIconUri(StoreCauseRequest|UpdateCauseRequest $request): ?string
    {
        if ($request->hasFile('icon_uri_file')) {
            $path = $request->file('icon_uri_file')->store('causes/icons', 'public');

            return 'storage/'.$path;
        }

        return $request->input('icon_uri_existing');
    }

    private function handleActiveIconUri(StoreCauseRequest|UpdateCauseRequest $request): ?string
    {
        if ($request->hasFile('icon_uri_active_file')) {
            $path = $request->file('icon_uri_active_file')->store('causes/icons', 'public');

            return 'storage/'.$path;
        }

        return $request->input('icon_uri_active_existing');
    }

    private function linesToArray(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $value);

        return array_values(array_filter(array_map('trim', $lines)));
    }
}
