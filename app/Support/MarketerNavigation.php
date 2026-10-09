<?php

namespace App\Support;

use App\Models\User;

class MarketerNavigation
{
    /**
     * @return list<array{label: string, route: string, href: string, section?: string, children?: list<array{label: string, route: string, href: string}>}>
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
                'label' => 'Meta',
                'route' => 'marketer.meta',
                'section' => 'Tracking',
                'children' => [
                    ['label' => 'Overview', 'route' => 'marketer.meta'],
                    ['label' => 'Analytics', 'route' => 'marketer.meta.analytics'],
                    ['label' => 'Your ads', 'route' => 'marketer.meta.ads'],
                ],
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

                if (! isset($item['children'])) {
                    return $item;
                }

                $item['children'] = collect($item['children'])
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
