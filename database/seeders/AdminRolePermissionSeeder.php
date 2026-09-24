<?php

namespace Database\Seeders;

use App\Support\AdminPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminRolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (AdminPermissions::allPermissionNames() as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $accountant = Role::firstOrCreate(['name' => 'accountant']);
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $user = Role::firstOrCreate(['name' => 'user']);
        $digitalMarketer = Role::firstOrCreate(['name' => 'digital_marketer']);

        $superAdmin->syncPermissions(Permission::all());

        $admin->syncPermissions([
            AdminPermissions::MANAGE_CAUSES,
            AdminPermissions::MANAGE_PACKAGES,
            AdminPermissions::DONATION_VIEW,
            AdminPermissions::DONATION_VIEW_ALL,
            AdminPermissions::REPORT_VIEW,
            AdminPermissions::MANAGE_DONATIONS,
            AdminPermissions::SUBSCRIPTION_VIEW,
            AdminPermissions::MANAGE_SUBSCRIPTIONS,
            AdminPermissions::QR_CODE_VIEW,
            AdminPermissions::MANAGE_QR_CODES,
            AdminPermissions::MANAGE_RECEIPTS,
            AdminPermissions::DONOR_VIEW,
            AdminPermissions::DONOR_EXPORT,
            AdminPermissions::MANAGE_DONOR_CRM,
            AdminPermissions::ANALYTICS_VIEW,
            AdminPermissions::REFERRAL_VIEW,
            AdminPermissions::MANAGE_AISENSY,
            AdminPermissions::MANAGE_WHATSAPP_CAMPAIGNS,
        ]);

        $accountant->syncPermissions([
            AdminPermissions::DONATION_VIEW,
            AdminPermissions::DONATION_VIEW_ALL,
            AdminPermissions::REPORT_VIEW,
            AdminPermissions::SUBSCRIPTION_VIEW,
            AdminPermissions::QR_CODE_VIEW,
            AdminPermissions::MANAGE_RECEIPTS,
            AdminPermissions::REFERRAL_VIEW,
        ]);

        $manager->syncPermissions([
            AdminPermissions::DONATION_VIEW,
            AdminPermissions::DONATION_VIEW_ALL,
            AdminPermissions::REPORT_VIEW,
            AdminPermissions::SUBSCRIPTION_VIEW,
            AdminPermissions::QR_CODE_VIEW,
            AdminPermissions::DONOR_VIEW,
            AdminPermissions::MANAGE_DONOR_CRM,
            AdminPermissions::ANALYTICS_VIEW,
            AdminPermissions::REFERRAL_VIEW,
            AdminPermissions::MANAGE_WHATSAPP_CAMPAIGNS,
        ]);

        $user->syncPermissions([
            AdminPermissions::DONATION_VIEW,
            AdminPermissions::MANAGE_DONATIONS,
            AdminPermissions::MANAGE_RECEIPTS,
            AdminPermissions::REFERRAL_VIEW,
        ]);

        $digitalMarketer->syncPermissions([
            AdminPermissions::CAUSE_VIEW,
            AdminPermissions::CAUSE_COPY_LINKS,
            AdminPermissions::CAMPAIGN_VIEW,
            AdminPermissions::CAMPAIGN_COPY_LINKS,
            AdminPermissions::PACKAGE_VIEW,
            AdminPermissions::PACKAGE_COPY_LINKS,
            AdminPermissions::REFERRAL_VIEW,
        ]);
    }
}
