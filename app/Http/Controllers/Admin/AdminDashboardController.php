<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminDashboardData;
use App\Support\AdminInertiaResources;
use App\Support\AdminPermissions;
use App\Support\DonationVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        // Receipt clerks / limited donation staff land on receipts instead of org-wide analytics.
        if (
            ! DonationVisibility::userCanViewAll($user)
            && ! $user->can('view analytics')
            && ! $user->can('view reports')
            && ($user->can('view donations') || $user->can('manage donations'))
        ) {
            if ($user->can('manage donations')) {
                return redirect()->route('admin.donations.create');
            }

            return redirect()->route('admin.donations.offline');
        }

        if (! $this->canViewOrgDashboard($user)) {
            return $this->redirectToFirstAllowedPage($user);
        }

        $monthKey = $request->input('month');
        $monthRange = AdminDashboardData::monthRange($monthKey);
        $birthdays = AdminDashboardData::birthdays();
        $recentPage = max(1, (int) $request->input('recent_page', 1));
        $birthdayPage = max(1, (int) $request->input('birthday_page', 1));

        return Inertia::render('Admin/Dashboard', [
            'stats' => AdminDashboardData::stats(),
            'monthlyTrend' => AdminDashboardData::monthlyTrend(),
            'recentDonations' => AdminDashboardData::recentDonationsPaginated($recentPage, $request->except('recent_page')),
            'topDonors' => AdminDashboardData::topDonors(),
            'topCauses' => AdminDashboardData::topCausesForRange($monthRange['start'], $monthRange['end']),
            'staffLeaderboard' => AdminDashboardData::staffLeaderboard($monthRange['start'], $monthRange['end']),
            'dailyPartnerReferrals' => AdminInertiaResources::canViewStaffReferrals($user)
                ? AdminDashboardData::dailyPartnerReferrals()
                : null,
            'monthlyPartnerReferrals' => AdminInertiaResources::canViewStaffReferrals($user)
                ? AdminDashboardData::monthlyPartnerReferrals()
                : null,
            'monthFilter' => [
                'options' => AdminDashboardData::monthOptions(),
                'selectedKey' => $monthRange['key'],
                'selectedLabel' => $monthRange['label'],
            ],
            'todaysBirthdays' => $birthdays['today'],
            'upcomingBirthdays' => AdminDashboardData::paginateCollection(
                collect($birthdays['upcoming']),
                AdminDashboardData::UPCOMING_BIRTHDAYS_PER_PAGE,
                'birthday_page',
                $birthdayPage,
                $request->except('birthday_page')
            ),
        ]);
    }

    private function canViewOrgDashboard(User $user): bool
    {
        return $user->can('view analytics')
            || $user->can('view reports')
            || DonationVisibility::userCanViewAll($user);
    }

    private function redirectToFirstAllowedPage(User $user): RedirectResponse
    {
        if (AdminPermissions::userCanAny($user, [AdminPermissions::CAUSE_VIEW, AdminPermissions::CAMPAIGN_VIEW, AdminPermissions::MANAGE_CAUSES])) {
            return redirect()->route(
                AdminPermissions::userCan($user, AdminPermissions::CAUSE_VIEW)
                    ? 'admin.causes.index'
                    : 'admin.campaigns.index'
            );
        }

        if ($user->can('view subscriptions')) {
            return redirect()->route('admin.subscriptions.index');
        }

        if ($user->can('view donors')) {
            return redirect()->route('admin.donors.index');
        }

        if ($user->can('manage users')) {
            return redirect()->route('admin.users.index');
        }

        if ($user->can('manage settings')) {
            return redirect()->route('admin.settings.index');
        }

        if ($user->can('manage whatsapp campaigns')) {
            return redirect()->route('admin.whatsapp-campaigns.index');
        }

        if ($user->can('manage aisensy accounts')) {
            return redirect()->route('admin.aisensy-accounts.index');
        }

        abort(403, 'USER DOES NOT HAVE THE RIGHT ROLES.');
    }
}
