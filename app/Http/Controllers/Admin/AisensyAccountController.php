<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AisensyAccount;
use App\Support\AdminInertiaResources;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AisensyAccountController extends Controller
{
    public function index(): Response
    {
        $accounts = AisensyAccount::query()
            ->latest()
            ->paginate(AdminInertiaResources::LIST_PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/AisensyAccounts/Index', [
            'accounts' => AdminInertiaResources::paginated(
                $accounts,
                fn (AisensyAccount $account) => AdminInertiaResources::aisensyAccount($account)
            ),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/AisensyAccounts/Form', [
            'account' => null,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'api_key' => 'required|string',
            'project_api_password' => 'nullable|string',
            'project_id' => 'required|string|max:255',
            'country_code' => 'required|string|max:10',
            'is_active' => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        if (! filled($data['project_api_password'] ?? null)) {
            unset($data['project_api_password']);
        }

        AisensyAccount::create($data);

        return redirect()
            ->route('admin.aisensy-accounts.index')
            ->with('status', 'AiSensy account created successfully.');
    }

    public function edit(AisensyAccount $aisensy_account): Response
    {
        return Inertia::render('Admin/AisensyAccounts/Form', [
            'account' => AdminInertiaResources::aisensyAccount($aisensy_account),
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, AisensyAccount $aisensy_account): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'api_key' => 'nullable|string',
            'project_api_password' => 'nullable|string',
            'project_id' => 'required|string|max:255',
            'country_code' => 'required|string|max:10',
            'is_active' => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        // Keep existing encrypted secrets when the masked placeholder is submitted.
        if (! filled($data['api_key'] ?? null) || $data['api_key'] === '********') {
            unset($data['api_key']);
        }

        if (! filled($data['project_api_password'] ?? null) || $data['project_api_password'] === '********') {
            unset($data['project_api_password']);
        }

        if (! isset($data['api_key']) && ! $aisensy_account->hasApiKey()) {
            return back()->withErrors(['api_key' => 'API Campaign Key is required.'])->withInput();
        }

        $aisensy_account->update($data);

        return redirect()
            ->route('admin.aisensy-accounts.index')
            ->with('status', 'AiSensy account updated successfully.');
    }

    public function toggleActive(AisensyAccount $aisensy_account): \Illuminate\Http\JsonResponse
    {
        $aisensy_account->update(['is_active' => ! $aisensy_account->is_active]);

        return response()->json(['is_active' => $aisensy_account->is_active]);
    }

    public function destroy(AisensyAccount $aisensy_account): RedirectResponse
    {
        $aisensy_account->delete();

        return redirect()
            ->route('admin.aisensy-accounts.index')
            ->with('status', 'AiSensy account deleted.');
    }
}
