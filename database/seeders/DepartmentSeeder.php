<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DepartmentSeeder extends Seeder
{
    /**
     * @var list<array{name: string, description: string, sort_order: int}>
     */
    private const DEFAULT_DEPARTMENTS = [
        [
            'name' => 'Operations',
            'description' => 'Day-to-day ashram operations and coordination.',
            'sort_order' => 10,
        ],
        [
            'name' => 'Finance & Accounts',
            'description' => 'Donations, receipts, subscriptions, and accounting.',
            'sort_order' => 20,
        ],
        [
            'name' => 'Fundraising',
            'description' => 'Campaigns, causes, packages, and donor outreach.',
            'sort_order' => 30,
        ],
        [
            'name' => 'Engagement',
            'description' => 'WhatsApp, broadcasts, and donor communication.',
            'sort_order' => 40,
        ],
    ];

    public function run(): void
    {
        foreach (self::DEFAULT_DEPARTMENTS as $department) {
            Department::query()->updateOrCreate(
                ['slug' => Str::slug($department['name'])],
                [
                    'name' => $department['name'],
                    'description' => $department['description'],
                    'is_active' => true,
                    'sort_order' => $department['sort_order'],
                ],
            );
        }
    }
}
