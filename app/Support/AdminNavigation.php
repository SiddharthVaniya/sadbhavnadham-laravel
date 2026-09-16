<?php

namespace App\Support;

use App\Models\User;

class AdminNavigation
{
    public static function build(User $user): array
    {
        $canViewAllDonations = AdminPermissions::userCan($user, AdminPermissions::DONATION_VIEW_ALL);
        $canViewDonations = AdminPermissions::userCan($user, AdminPermissions::DONATION_VIEW);
        $canCreateDonations = AdminPermissions::userCan($user, AdminPermissions::DONATION_CREATE);

        $donationChildren = [];

        if ($canViewAllDonations) {
            $donationChildren[] = ['label' => 'All donations', 'route' => 'admin.donations.index'];
            $donationChildren[] = ['label' => 'Recovery queue', 'route' => 'admin.donations.recovery'];
        } elseif ($canViewDonations) {
            $donationChildren[] = ['label' => 'My receipts', 'route' => 'admin.donations.offline'];
        }

        if ($canViewDonations) {
            $donationChildren[] = [
                'label' => 'Offline',
                'route' => 'admin.donations.offline',
                'permission' => null,
            ];
        }

        if ($canCreateDonations) {
            $donationChildren[] = [
                'label' => 'Record offline',
                'route' => 'admin.donations.create',
                'permission' => AdminPermissions::DONATION_CREATE,
            ];
        }

        if (! $canViewAllDonations) {
            $donationChildren = collect($donationChildren)
                ->unique('route')
                ->values()
                ->all();
        }

        $items = [
            [
                'label' => 'Dashboard',
                'route' => 'admin.dashboard',
                'permissions_any' => [
                    AdminPermissions::ANALYTICS_VIEW,
                    AdminPermissions::REPORT_VIEW,
                    AdminPermissions::DONATION_VIEW_ALL,
                ],
            ],
            [
                'label' => 'Analytics',
                'route' => 'admin.analytics.index',
                'permission' => AdminPermissions::ANALYTICS_VIEW,
                'section' => 'Insights',
            ],
            [
                'label' => 'Partner Reports',
                'route' => 'admin.partner-reports.index',
                'permission' => AdminPermissions::REFERRAL_VIEW,
                'section' => 'Insights',
            ],
            [
                'label' => 'Partner attribution',
                'route' => 'admin.referrals.index',
                'permission' => AdminPermissions::REFERRAL_VIEW,
                'section' => 'Insights',
            ],
            [
                'label' => 'Reports',
                'route' => 'admin.reports.index',
                'permission' => AdminPermissions::REPORT_VIEW,
                'section' => 'Insights',
            ],
            [
                'label' => 'Donations',
                'route' => $canViewAllDonations ? 'admin.donations.index' : 'admin.donations.offline',
                'permissions_any' => [
                    AdminPermissions::DONATION_VIEW,
                    AdminPermissions::DONATION_CREATE,
                    AdminPermissions::DONATION_EDIT,
                ],
                'section' => 'Finance',
                'children' => $donationChildren,
            ],
            [
                'label' => 'Subscriptions',
                'route' => 'admin.subscriptions.index',
                'permission' => AdminPermissions::SUBSCRIPTION_VIEW,
            ],
            [
                'label' => 'Donors',
                'route' => 'admin.donors.index',
                'permission' => AdminPermissions::DONOR_VIEW,
                'section' => 'People',
            ],
            [
                'label' => 'Causes',
                'route' => 'admin.causes.index',
                'permissions_any' => [AdminPermissions::CAUSE_VIEW, AdminPermissions::MANAGE_CAUSES],
                'section' => 'Fundraising',
            ],
            [
                'label' => 'Campaigns',
                'route' => 'admin.campaigns.index',
                'permissions_any' => [AdminPermissions::CAMPAIGN_VIEW, AdminPermissions::MANAGE_CAUSES],
            ],
            [
                'label' => 'Packages',
                'route' => 'admin.packages.index',
                'permissions_any' => [AdminPermissions::PACKAGE_VIEW, AdminPermissions::MANAGE_PACKAGES],
            ],
            [
                'label' => 'WhatsApp accounts',
                'route' => 'admin.aisensy-accounts.index',
                'permissions_any' => [AdminPermissions::AISENSY_VIEW, AdminPermissions::MANAGE_AISENSY],
                'section' => 'Engagement',
            ],
            [
                'label' => 'Broadcasts',
                'route' => 'admin.whatsapp-campaigns.index',
                'permissions_any' => [
                    AdminPermissions::WHATSAPP_BROADCAST_VIEW,
                    AdminPermissions::MANAGE_WHATSAPP_CAMPAIGNS,
                ],
            ],
            [
                'label' => 'Users',
                'route' => 'admin.users.index',
                'permissions_any' => [AdminPermissions::USER_VIEW, AdminPermissions::MANAGE_USERS],
                'section' => 'System',
            ],
            [
                'label' => 'Marketers',
                'route' => 'admin.marketers.index',
                'permissions_any' => [AdminPermissions::USER_EDIT, AdminPermissions::MANAGE_USERS],
            ],
            [
                'label' => 'Departments',
                'route' => 'admin.departments.index',
                'permissions_any' => [AdminPermissions::DEPARTMENT_MANAGE, AdminPermissions::MANAGE_USERS],
            ],
            [
                'label' => 'Roles',
                'route' => 'admin.roles.index',
                'permissions_any' => [AdminPermissions::ROLE_MANAGE, AdminPermissions::MANAGE_USERS],
            ],
            [
                'label' => 'Permissions',
                'route' => 'admin.permissions.index',
                'permissions_any' => [AdminPermissions::ROLE_MANAGE, AdminPermissions::MANAGE_USERS],
            ],
            [
                'label' => 'Settings',
                'route' => 'admin.settings.index',
                'permissions_any' => [
                    AdminPermissions::SETTINGS_VIEW,
                    AdminPermissions::SETTINGS_EDIT,
                    AdminPermissions::SETTINGS_BRANDING,
                    AdminPermissions::MANAGE_SETTINGS,
                ],
                'children' => [
                    ['label' => 'Notifications', 'route' => 'admin.settings.index'],
                    [
                        'label' => 'Branding',
                        'route' => 'admin.settings.branding.edit',
                        'permission' => AdminPermissions::SETTINGS_BRANDING,
                    ],
                ],
            ],
        ];

        return collect($items)
            ->filter(function (array $item) use ($user) {
                if (isset($item['permissions_any'])) {
                    return AdminPermissions::userCanAny($user, $item['permissions_any']);
                }

                if (! ($item['permission'] ?? null)) {
                    return true;
                }

                return AdminPermissions::userCan($user, $item['permission']);
            })
            ->map(function (array $item) use ($user) {
                $item['href'] = route($item['route']);

                if (! isset($item['children'])) {
                    return $item;
                }

                $item['children'] = collect($item['children'])
                    ->filter(fn (array $child) => ! ($child['permission'] ?? null) || AdminPermissions::userCan($user, $child['permission']))
                    ->map(function (array $child) {
                        $child['href'] = route($child['route']);

                        return $child;
                    })
                    ->values()
                    ->all();

                return $item;
            })
            ->values()
            ->all();
    }
}
