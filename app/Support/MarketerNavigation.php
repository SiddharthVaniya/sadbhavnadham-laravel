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
        return collect([
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
        ])
            ->map(function (array $item) {
                $item['href'] = route($item['route']);

                return $item;
            })
            ->values()
            ->all();
    }
}
