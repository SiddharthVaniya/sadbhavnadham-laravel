<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DonationCampaignRequest;
use App\Http\Requests\Admin\StoreDonationCampaignRequest;
use App\Http\Requests\Admin\UpdateDonationCampaignRequest;
use App\Models\DonationCampaign;
use App\Support\AdminCampaignStatsData;
use App\Support\AdminInertiaResources;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AdminDonationCampaignController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(AdminPermissions::middleware(AdminPermissions::CAMPAIGN_VIEW), only: ['index', 'show']),
            new Middleware(AdminPermissions::middleware(AdminPermissions::CAMPAIGN_CREATE), only: ['create', 'store']),
            new Middleware(AdminPermissions::middleware(AdminPermissions::CAMPAIGN_EDIT), only: ['edit', 'update', 'toggleActive']),
            new Middleware(AdminPermissions::middleware(AdminPermissions::CAMPAIGN_DELETE), only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $campaigns = DonationCampaign::query()
            ->with('cause:id,title,slug')
            ->latest()
            ->paginate(AdminInertiaResources::LIST_PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Campaigns/Index', [
            'campaigns' => AdminInertiaResources::paginated(
                $campaigns,
                fn (DonationCampaign $campaign) => AdminInertiaResources::donationCampaignListRow(
                    $campaign,
                    AdminCampaignStatsData::metricsForCampaign($campaign),
                    auth()->user(),
                )
            ),
            'multiCampaignDonors' => AdminCampaignStatsData::donorsWithMultipleLiveCampaigns(),
            'abilities' => AdminPermissions::campaignAbilities(auth()->user()),
        ]);
    }

    public function show(DonationCampaign $campaign, Request $request): Response
    {
        return Inertia::render('Admin/Campaigns/Show', AdminCampaignStatsData::reportForCampaign($campaign, $request));
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Campaigns/Form', [
            'campaign' => null,
            'isEdit' => false,
            'causes' => AdminInertiaResources::causeOptionsForCampaigns(),
        ]);
    }

    public function store(StoreDonationCampaignRequest $request): RedirectResponse
    {
        DonationCampaign::query()->create($this->buildCampaignPayload($request));

        return redirect()
            ->route('admin.campaigns.index')
            ->with('status', 'Campaign created.');
    }

    public function edit(DonationCampaign $campaign): Response
    {
        $campaign->load(['cause', 'package']);

        return Inertia::render('Admin/Campaigns/Form', [
            'campaign' => AdminInertiaResources::donationCampaign($campaign),
            'isEdit' => true,
            'causes' => AdminInertiaResources::causeOptionsForCampaigns(),
        ]);
    }

    public function update(UpdateDonationCampaignRequest $request, DonationCampaign $campaign): RedirectResponse
    {
        $campaign->update($this->buildCampaignPayload($request));

        return redirect()
            ->route('admin.campaigns.index')
            ->with('status', 'Campaign updated.');
    }

    public function toggleActive(DonationCampaign $campaign): \Illuminate\Http\JsonResponse
    {
        $campaign->update(['is_active' => ! $campaign->is_active]);

        return response()->json(['is_active' => $campaign->is_active]);
    }

    public function destroy(DonationCampaign $campaign): RedirectResponse
    {
        $this->deleteStoredPublicImage($campaign->image);

        $campaign->delete();

        return redirect()
            ->route('admin.campaigns.index')
            ->with('status', 'Campaign deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCampaignPayload(DonationCampaignRequest $request): array
    {
        $payload = $request->validatedPayload();
        $payload['image'] = $this->handleCampaignImage($request);

        return $payload;
    }

    private function handleCampaignImage(DonationCampaignRequest $request): ?string
    {
        if ($request->hasFile('image')) {
            $this->deleteStoredPublicImage($request->input('image_existing'));

            $path = $request->file('image')->store('campaigns/images', 'public');

            return 'storage/'.$path;
        }

        if ($request->boolean('remove_image')) {
            $this->deleteStoredPublicImage($request->input('image_existing'));

            return null;
        }

        return $request->input('image_existing');
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
}
