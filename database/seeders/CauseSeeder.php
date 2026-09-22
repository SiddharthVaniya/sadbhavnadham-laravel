<?php

namespace Database\Seeders;

use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationItem;
use Illuminate\Database\Seeder;

class CauseSeeder extends Seeder
{
    public function run(): void
    {
        $causes = [
            [
                'slug' => 'old-age-home',
                'title' => 'Old Age Home',
                'excerpt' => 'Support daily meals and care for elderly residents.',
                'images' => [
                    'images/old-age/1.jpg',
                    'images/old-age/2.jpg',
                ],
                'hero_image' => 'images/old-age-home/default.jpg',
                'allow_custom_amount' => true,
                'pan_required' => true,
                'default_amount' => 1500,
                'default_title' => 'Morning Breakfast',
                'cta_text' => 'Donate & Serve a Meal',
                'sort_order' => 1,
                'packages' => [
                    [
                        'title' => 'Morning Breakfast',
                        'amount' => 1500,
                        'image' => 'images/old-age-home/breakfast.JPG',
                        'meta' => ['seva_type' => 'Morning Breakfast'],
                        'sort_order' => 1,
                    ],
                    [
                        'title' => 'Tea & Snacks (3:30 PM)',
                        'amount' => 500,
                        'image' => 'images/old-age-home/lunch.JPG',
                        'meta' => ['seva_type' => 'Tea & Snacks (3:30 PM)'],
                        'sort_order' => 2,
                    ],
                    [
                        'title' => 'Simple Meal',
                        'amount' => 2500,
                        'image' => 'images/old-age-home/dinner.JPG',
                        'meta' => ['seva_type' => 'Simple Meal'],
                        'sort_order' => 3,
                    ],
                    [
                        'title' => 'Special Sweet Meal',
                        'amount' => 5000,
                        'image' => 'images/old-age-home/breakfast.JPG',
                        'meta' => ['seva_type' => 'Special Sweet Meal'],
                        'sort_order' => 4,
                    ],
                    [
                        'title' => 'Simple Meal (Full Day)',
                        'amount' => 10000,
                        'image' => 'images/old-age-home/lunch.JPG',
                        'meta' => ['seva_type' => 'Simple Meal (Full Day)'],
                        'sort_order' => 5,
                    ],
                    [
                        'title' => 'Full Day Complete Expense',
                        'amount' => 15000,
                        'image' => 'images/old-age-home/dinner.JPG',
                        'meta' => ['seva_type' => 'Full Day Complete Expense'],
                        'sort_order' => 6,
                    ],
                    [
                        'title' => 'Lifetime Tithi Breakfast',
                        'amount' => 31000,
                        'image' => 'images/old-age-home/breakfast.JPG',
                        'meta' => ['seva_type' => 'Lifetime Tithi Breakfast'],
                        'sort_order' => 7,
                    ],
                    [
                        'title' => 'Lifetime Tithi Meal',
                        'amount' => 51000,
                        'image' => 'images/old-age-home/lunch.JPG',
                        'meta' => ['seva_type' => 'Lifetime Tithi Meal'],
                        'sort_order' => 8,
                    ],
                    [
                        'title' => 'Lifetime Tithi Sweet Meal',
                        'amount' => 71000,
                        'image' => 'images/old-age-home/dinner.JPG',
                        'meta' => ['seva_type' => 'Lifetime Tithi Sweet Meal'],
                        'sort_order' => 9,
                    ],
                ],
            ],

            [
                'slug' => 'tree-plantation',
                'title' => 'Tree Plantation',
                'excerpt' => 'Help us plant and nurture trees for a greener future.',
                'images' => [
                    'images/tree-plantation/1.jpg',
                    'images/tree-plantation/2.jpg',
                    'images/tree-plantation/3.jpg',
                ],
                'hero_image' => 'images/tree-plantation/1.jpg',
                'allow_custom_amount' => true,
                'pan_required' => true,
                'default_amount' => 3000,
                'default_title' => 'Tree',
                'cta_text' => 'Donate',
                'details' => [
                    'The cost of planting and maintaining one tree for 3 years is ₹3000.',
                    'Fertilizer and water will be provided to the tree for three years.',
                    'One tree becomes a habitat for thousands of insects and birds.',
                    'The lifespan of a tree is approximately 150 to 200 years.',
                    'You can dedicate a tree in memory of birthdays, anniversaries, or death anniversaries of loved ones.',
                    'A nameplate with your name will be placed on the tree.',
                ],
                'sort_order' => 2,
                'packages' => [
                    [
                        'title' => 'Tree',
                        'amount' => 1500,
                        'image' => 'images/tree-plantation/2.JPG',
                        'meta' => ['seva_type' => 'Tree'],
                        'sort_order' => 1,
                    ],
                    [
                        'title' => 'Tree',
                        'amount' => 3000,
                        'image' => 'images/tree-plantation/2.JPG',
                        'meta' => ['seva_type' => 'Tree'],
                        'sort_order' => 2,
                    ],
                ],
            ],

            [
                'slug' => 'animal-hospital',
                'title' => 'Animal Hospital',
                'excerpt' => 'Support medical treatment and emergency care for animals.',
                'images' => [
                    'images/animal-hospital/1.jpeg',
                    'images/animal-hospital/2.jpg',
                    'images/animal-hospital/3.jpeg',
                    'images/animal-hospital/4.jpg',
                ],
                'hero_image' => 'images/animal-hospital/1.jpeg',
                'allow_custom_amount' => true,
                'pan_required' => true,
                'default_amount' => 500,
                'default_title' => 'One Day Bird Feeding',
                'cta_text' => 'Donate',
                'sort_order' => 3,
                'packages' => [
                    [
                        'title' => 'One Day Bird Feeding',
                        'amount' => 500,
                        'image' => 'images/animal-hospital/2.JPG',
                        'meta' => ['seva_type' => 'One Day Bird Feeding'],
                        'sort_order' => 1,
                    ],
                    [
                        'title' => 'One Day Animal Feed',
                        'amount' => 50000,
                        'image' => 'images/animal-hospital/3.JPEG',
                        'meta' => ['seva_type' => 'One Day Animal Feed'],
                        'sort_order' => 2,
                    ],
                    [
                        'title' => 'Animal Surgery',
                        'amount' => 5000,
                        'image' => 'images/animal-hospital/4.JPG',
                        'meta' => ['seva_type' => 'Animal Surgery'],
                        'sort_order' => 3,
                    ],
                    [
                        'title' => 'Adopt a Sick Animal (1 Month)',
                        'amount' => 2000,
                        'image' => 'images/animal-hospital/2.JPG',
                        'meta' => ['seva_type' => 'Adopt a Sick Animal (1 Month)'],
                        'sort_order' => 4,
                    ],
                    [
                        'title' => 'Adopt a Sick Animal (1 Year)',
                        'amount' => 24000,
                        'image' => 'images/animal-hospital/3.JPEG',
                        'meta' => ['seva_type' => 'Adopt a Sick Animal (1 Year)'],
                        'sort_order' => 5,
                    ],
                ],
            ],

            [
                'slug' => 'swan-ashram',
                'title' => 'Swan Ashram',
                'excerpt' => 'Help provide food and shelter to rescued dogs.',
                'images' => [
                    'images/swan-ashram/1.jpg',
                    'images/swan-ashram/2.jpg',
                    'images/swan-ashram/3.jpg',
                    'images/swan-ashram/4.jpg',
                ],
                'hero_image' => 'images/swan-ashram/1.jpg',
                'allow_custom_amount' => true,
                'pan_required' => true,
                'default_amount' => 3000,
                'default_title' => 'One Day Milk',
                'cta_text' => 'Donate',
                'sort_order' => 4,
                'packages' => [
                    [
                        'title' => 'One Day Milk',
                        'amount' => 3000,
                        'image' => 'images/swan-ashram/2.JPG',
                        'meta' => ['seva_type' => 'One Day Milk'],
                        'sort_order' => 1,
                    ],
                    [
                        'title' => 'One Day Dog Food',
                        'amount' => 3000,
                        'image' => 'images/swan-ashram/3.JPG',
                        'meta' => ['seva_type' => 'One Day Dog Food'],
                        'sort_order' => 2,
                    ],
                    [
                        'title' => 'One Month Medicine Expense',
                        'amount' => 50000,
                        'image' => 'images/swan-ashram/4.JPG',
                        'meta' => ['seva_type' => 'One Month Medicine Expense'],
                        'sort_order' => 3,
                    ],
                ],
            ],

            [
                'slug' => 'badad-ashram',
                'title' => 'Badad Ashram',
                'excerpt' => 'Support the care, feeding, and medical needs of bulls.',
                'images' => [
                    'images/badad-ashram/1.jpg',
                    'images/badad-ashram/2.jpg',
                    'images/badad-ashram/3.jpg',
                    'images/badad-ashram/4.jpeg',
                ],
                'hero_image' => 'images/badad-ashram/1.jpg',
                'allow_custom_amount' => true,
                'pan_required' => true,
                'default_amount' => 2100,
                'default_title' => 'Adopt a Bull (1 Month)',
                'cta_text' => 'Donate',
                'sort_order' => 5,
                'packages' => [
                    [
                        'title' => 'Adopt a Bull (1 Month)',
                        'amount' => 2100,
                        'image' => 'images/badad-ashram/2.JPG',
                        'meta' => ['seva_type' => 'Adopt a Bull (1 Month)'],
                        'sort_order' => 1,
                    ],
                    [
                        'title' => 'Adopt a Bull (1 Year)',
                        'amount' => 25000,
                        'image' => 'images/badad-ashram/3.JPG',
                        'meta' => ['seva_type' => 'Adopt a Bull (1 Year)'],
                        'sort_order' => 2,
                    ],
                    [
                        'title' => 'Donate for One Shelter',
                        'amount' => 11000,
                        'image' => 'images/badad-ashram/4.JPEG',
                        'meta' => ['seva_type' => 'Donate for One Shelter'],
                        'sort_order' => 3,
                    ],
                    [
                        'title' => 'One Time Animal Feed',
                        'amount' => 50000,
                        'image' => 'images/badad-ashram/2.JPG',
                        'meta' => ['seva_type' => 'One Time Animal Feed'],
                        'sort_order' => 4,
                    ],
                    [
                        'title' => 'One Month Medicine Expense',
                        'amount' => 250000,
                        'image' => 'images/badad-ashram/3.JPG',
                        'meta' => ['seva_type' => 'One Month Medicine Expense'],
                        'sort_order' => 5,
                    ],
                ],
            ],
        ];

        foreach ($causes as $causeData) {
            $packages = $causeData['packages'] ?? [];
            unset($causeData['packages']);

            $cause = Cause::query()->updateOrCreate(
                ['slug' => $causeData['slug']],
                $causeData
            );

            foreach ($packages as $package) {
                $cause->packages()->updateOrCreate(
                    [
                        'title' => $package['title'],
                        'amount' => $package['amount'],
                    ],
                    $package
                );
            }
        }

        $this->seedDailyNeedsOnly();
    }

    public function seedDailyNeedsOnly(): void
    {
        Cause::query()->updateOrCreate(
            ['slug' => Cause::SLUG_DAILY_NEEDS],
            [
                'title' => 'Daily Need',
                'excerpt' => 'Support daily groceries, pulses, oil, ghee, and essentials for elders at Sadbhavna Vrudhashram.',
                'images' => [
                    'images/old-age/1.jpg',
                    'images/old-age/2.jpg',
                ],
                'hero_image' => 'images/old-age-home/default.jpg',
                'allow_custom_amount' => true,
                'allow_recurring' => false,
                'allow_weekly_recurring' => false,
                'pan_required' => false,
                'default_amount' => 123,
                'default_title' => 'Daily Needs',
                'cta_text' => 'Donate Now',
                'sort_order' => 6,
                'is_active' => true,
            ]
        );

        $this->copyOldAgeHomeMessagingToDailyNeeds();
        $this->seedDailyNeedsPackages();
        $this->reassignDailyNeedsDonationItems();
    }

    private function seedDailyNeedsPackages(): void
    {
        $dailyNeeds = Cause::query()->where('slug', Cause::SLUG_DAILY_NEEDS)->first();

        if ($dailyNeeds === null) {
            return;
        }

        $packages = [
            ['title' => 'Moong Beans', 'amount' => 123, 'sort_order' => 1, 'is_default' => true],
            ['title' => 'Moth Beans', 'amount' => 152, 'sort_order' => 2],
            ['title' => 'Chickpeas', 'amount' => 103, 'sort_order' => 3],
            ['title' => 'Val / Field Beans', 'amount' => 103, 'sort_order' => 4],
            ['title' => 'Dried Peas', 'amount' => 90, 'sort_order' => 5],
            ['title' => 'Rice', 'amount' => 45, 'sort_order' => 6],
            ['title' => 'Kidney Beans / Rajma', 'amount' => 100, 'sort_order' => 7],
            ['title' => 'Cooking Oil Tin', 'amount' => 2100, 'sort_order' => 8],
            ['title' => 'Ghee Tin', 'amount' => 1100, 'sort_order' => 9],
            ['title' => 'Diaper (1 piece)', 'amount' => 17, 'sort_order' => 10],
            ['title' => 'Tuver Dal', 'amount' => 173, 'sort_order' => 11],
            ['title' => 'Chana Dal', 'amount' => 84, 'sort_order' => 12],
            ['title' => 'Chole / Garbanzo Beans', 'amount' => 105, 'sort_order' => 13],
            ['title' => 'Urad Dal', 'amount' => 130, 'sort_order' => 14],
        ];

        foreach ($packages as $package) {
            $legacyTitle = $package['title'].' (1 kg)';

            CausePackage::query()
                ->where('cause_id', $dailyNeeds->id)
                ->where('title', $legacyTitle)
                ->update(['title' => $package['title']]);

            CausePackage::query()->updateOrCreate(
                [
                    'cause_id' => $dailyNeeds->id,
                    'title' => $package['title'],
                ],
                [
                    'amount' => $package['amount'],
                    'sort_order' => $package['sort_order'],
                    'is_active' => true,
                    'is_default' => (bool) ($package['is_default'] ?? false),
                    'allow_recurring' => false,
                ]
            );
        }
    }

    private function copyOldAgeHomeMessagingToDailyNeeds(): void
    {
        $oldAgeHome = Cause::query()->where('slug', 'old-age-home')->first();
        $dailyNeeds = Cause::query()->where('slug', Cause::SLUG_DAILY_NEEDS)->first();

        if (! $oldAgeHome || ! $dailyNeeds) {
            return;
        }

        $dailyNeeds->fill([
            'contact_heading' => $oldAgeHome->contact_heading,
            'contact_address' => $oldAgeHome->contact_address,
            'contact_phone' => $oldAgeHome->contact_phone,
            'contact_email' => $oldAgeHome->contact_email,
            'aisensy_account_id' => $oldAgeHome->aisensy_account_id,
            'aisensy_payment_link_campaign' => $oldAgeHome->aisensy_payment_link_campaign,
            'aisensy_thank_you_campaign' => $oldAgeHome->aisensy_thank_you_campaign,
            'aisensy_certificate_campaign' => $oldAgeHome->aisensy_certificate_campaign,
            'certificate_template' => $oldAgeHome->certificate_template,
            'certificate_template_english' => $oldAgeHome->certificate_template_english,
            'aisensy_thank_you_image' => $oldAgeHome->aisensy_thank_you_image,
            'aisensy_thank_you_message_mode' => $oldAgeHome->aisensy_thank_you_message_mode,
            'aisensy_thank_you_message_template' => $oldAgeHome->aisensy_thank_you_message_template,
            'aisensy_thank_you_include_name' => $oldAgeHome->aisensy_thank_you_include_name,
            'aisensy_thank_you_include_amount' => $oldAgeHome->aisensy_thank_you_include_amount,
            'aisensy_thank_you_include_cause' => $oldAgeHome->aisensy_thank_you_include_cause,
            'aisensy_thank_you_include_receipt' => $oldAgeHome->aisensy_thank_you_include_receipt,
            'aisensy_send_thank_you' => $oldAgeHome->aisensy_send_thank_you,
            'aisensy_send_certificate' => $oldAgeHome->aisensy_send_certificate,
        ])->save();
    }

    private function reassignDailyNeedsDonationItems(): void
    {
        $dailyNeeds = Cause::query()->where('slug', Cause::SLUG_DAILY_NEEDS)->first();

        if (! $dailyNeeds) {
            return;
        }

        DonationItem::query()
            ->where('title', 'like', 'Daily Needs%')
            ->where(function ($query) use ($dailyNeeds): void {
                $query->where('cause', '!=', $dailyNeeds->slug)
                    ->orWhere('cause_id', '!=', $dailyNeeds->id)
                    ->orWhereNull('cause_id');
            })
            ->each(function (DonationItem $item) use ($dailyNeeds): void {
                $meta = is_array($item->meta) ? $item->meta : [];
                $meta['cause_title'] = $dailyNeeds->title;
                $meta['cause_slug'] = $dailyNeeds->slug;

                $item->update([
                    'cause_id' => $dailyNeeds->id,
                    'cause' => $dailyNeeds->slug,
                    'meta' => $meta,
                ]);
            });
    }
}
