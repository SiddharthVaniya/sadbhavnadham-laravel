<?php

namespace App\Support;

use App\Models\User;

class MarketerNavigation
{
    /**
     * @return list<array{label: string, route: string, href: string, section?: string}>
     */
    public static function build(User $user): array
    {
        $items = [
            [
                'label' => 'Performance',
                'route' => 'marketer.dashboard',
            ],
            [
                'label' => 'Donations',
                'route' => 'marketer.donations',
            ],
            [
                'label' => 'Campaigns',
                'route' => 'marketer.campaigns',
                'section' => 'Tracking',
            ],
            [
                'label' => 'Clicks',
                'route' => 'marketer.visits',
            ],
            [
                'label' => 'Meta analytics',
                'route' => 'marketer.meta',
                'section' => 'Tracking',
            ],
        ];

        if (AdminPermissions::userCan($user, AdminPermissions::PACKAGE_VIEW)) {
            $items[] = [
                'label' => 'Packages',
                'route' => 'admin.packages.index',
                'section' => 'Catalog',
            ];
        }

        return collect($items)
            ->map(function (array $item) {
                $item['href'] = route($item['route']);

                return $item;
            })
            ->values()
            ->all();
    }
}
