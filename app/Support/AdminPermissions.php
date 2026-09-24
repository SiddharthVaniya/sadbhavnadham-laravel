<?php

namespace App\Support;

use App\Models\User;

class AdminPermissions
{
    // Causes & campaigns
    public const MANAGE_CAUSES = 'manage causes';

    public const CAUSE_VIEW = 'view causes';

    public const CAUSE_CREATE = 'create causes';

    public const CAUSE_EDIT = 'edit causes';

    public const CAUSE_DELETE = 'delete causes';

    public const CAUSE_COPY_LINKS = 'copy cause links';

    public const CAMPAIGN_VIEW = 'view campaigns';

    public const CAMPAIGN_CREATE = 'create campaigns';

    public const CAMPAIGN_EDIT = 'edit campaigns';

    public const CAMPAIGN_DELETE = 'delete campaigns';

    public const CAMPAIGN_COPY_LINKS = 'copy campaign links';

    // Packages
    public const MANAGE_PACKAGES = 'manage packages';

    public const PACKAGE_VIEW = 'view packages';

    public const PACKAGE_CREATE = 'create packages';

    public const PACKAGE_EDIT = 'edit packages';

    public const PACKAGE_DELETE = 'delete packages';

    public const PACKAGE_COPY_LINKS = 'copy package links';

    // Donations
    public const DONATION_VIEW = 'view donations';

    public const DONATION_VIEW_ALL = 'view all donations';

    public const DONATION_CREATE = 'create donations';

    public const DONATION_EDIT = 'edit donations';

    public const DONATION_EXPORT = 'export donations';

    public const DONATION_RECOVERY_VIEW = 'view donation recovery';

    public const MANAGE_DONATIONS = 'manage donations';

    // Receipts & delivery
    public const RECEIPT_PREVIEW = 'preview receipts';

    public const RECEIPT_PRINT = 'print receipts';

    public const RECEIPT_RESEND = 'resend receipts';

    public const RECEIPT_GENERATE = 'generate receipts';

    public const RECEIPT_RESEND_NOTIFICATIONS = 'resend donation notifications';

    public const RECEIPT_RECOVERY_NUDGE = 'nudge donation recovery';

    public const MANAGE_RECEIPTS = 'manage receipts';

    // Donors
    public const DONOR_VIEW = 'view donors';

    public const DONOR_EXPORT = 'export donors';

    public const DONOR_IMPORT = 'import donors';

    public const DONOR_MANAGE_COMMUNICATION = 'manage donor communication';

    public const DONOR_CRM_OWNERS = 'manage donor owners';

    public const DONOR_CRM_NOTES = 'manage donor notes';

    public const DONOR_CRM_TASKS = 'manage donor tasks';

    public const MANAGE_DONOR_CRM = 'manage donor crm';

    // Insights
    public const ANALYTICS_VIEW = 'view analytics';

    public const ANALYTICS_EXPORT = 'export analytics';

    public const REPORT_VIEW = 'view reports';

    public const REPORT_EXPORT = 'export reports';

    public const REFERRAL_VIEW = 'view staff referrals';

    public const SUBSCRIPTION_VIEW = 'view subscriptions';

    public const SUBSCRIPTION_EXPORT = 'export subscriptions';

    public const SUBSCRIPTION_CANCEL = 'cancel subscriptions';

    public const SUBSCRIPTION_SYNC = 'sync subscriptions';

    public const MANAGE_SUBSCRIPTIONS = 'manage subscriptions';

    public const QR_CODE_VIEW = 'view qr codes';

    public const QR_CODE_CREATE = 'create qr codes';

    public const QR_CODE_CLOSE = 'close qr codes';

    public const QR_CODE_SYNC = 'sync qr codes';

    public const MANAGE_QR_CODES = 'manage qr codes';

    // Engagement
    public const AISENSY_VIEW = 'view aisensy accounts';

    public const AISENSY_CREATE = 'create aisensy accounts';

    public const AISENSY_EDIT = 'edit aisensy accounts';

    public const AISENSY_DELETE = 'delete aisensy accounts';

    public const MANAGE_AISENSY = 'manage aisensy accounts';

    public const WHATSAPP_BROADCAST_VIEW = 'view whatsapp broadcasts';

    public const WHATSAPP_BROADCAST_CREATE = 'create whatsapp broadcasts';

    public const WHATSAPP_BROADCAST_MANAGE = 'manage whatsapp broadcasts';

    public const MANAGE_WHATSAPP_CAMPAIGNS = 'manage whatsapp campaigns';

    // System
    public const USER_VIEW = 'view users';

    public const USER_CREATE = 'create users';

    public const USER_EDIT = 'edit users';

    public const USER_DELETE = 'delete users';

    public const USER_RESTORE = 'restore users';

    public const ROLE_MANAGE = 'manage roles';

    public const DEPARTMENT_MANAGE = 'manage departments';

    public const MANAGE_USERS = 'manage users';

    public const SETTINGS_VIEW = 'view settings';

    public const SETTINGS_EDIT = 'edit settings';

    public const SETTINGS_BRANDING = 'edit branding';

    public const MANAGE_SETTINGS = 'manage settings';

    /**
     * @return array<string, list<string>>
     */
    public static function legacyPermissionMap(): array
    {
        return [
            self::MANAGE_CAUSES => [
                ...self::causePermissionNames(),
                ...self::campaignPermissionNames(),
            ],
            self::MANAGE_PACKAGES => self::packagePermissionNames(),
            self::MANAGE_DONATIONS => [
                self::DONATION_CREATE,
                self::DONATION_EDIT,
                self::DONATION_RECOVERY_VIEW,
            ],
            self::DONATION_VIEW => [
                self::DONATION_EXPORT,
                self::DONATION_RECOVERY_VIEW,
            ],
            self::MANAGE_RECEIPTS => [
                self::RECEIPT_PREVIEW,
                self::RECEIPT_PRINT,
                self::RECEIPT_RESEND,
                self::RECEIPT_GENERATE,
                self::RECEIPT_RESEND_NOTIFICATIONS,
                self::RECEIPT_RECOVERY_NUDGE,
            ],
            self::DONOR_EXPORT => [
                self::DONOR_EXPORT,
                self::DONOR_IMPORT,
            ],
            self::MANAGE_DONOR_CRM => [
                self::DONOR_CRM_OWNERS,
                self::DONOR_CRM_NOTES,
                self::DONOR_CRM_TASKS,
            ],
            self::ANALYTICS_VIEW => [
                self::ANALYTICS_VIEW,
                self::ANALYTICS_EXPORT,
            ],
            self::REPORT_VIEW => [
                self::REPORT_VIEW,
                self::REPORT_EXPORT,
            ],
            self::SUBSCRIPTION_VIEW => [
                self::SUBSCRIPTION_VIEW,
                self::SUBSCRIPTION_EXPORT,
            ],
            self::MANAGE_SUBSCRIPTIONS => [
                self::SUBSCRIPTION_CANCEL,
                self::SUBSCRIPTION_SYNC,
            ],
            self::QR_CODE_VIEW => [
                self::QR_CODE_VIEW,
            ],
            self::MANAGE_QR_CODES => [
                self::QR_CODE_CREATE,
                self::QR_CODE_CLOSE,
                self::QR_CODE_SYNC,
            ],
            self::MANAGE_AISENSY => self::aisensyPermissionNames(),
            self::MANAGE_WHATSAPP_CAMPAIGNS => self::whatsappBroadcastPermissionNames(),
            self::MANAGE_USERS => [
                ...self::userPermissionNames(),
                self::ROLE_MANAGE,
                self::DEPARTMENT_MANAGE,
            ],
            self::MANAGE_SETTINGS => [
                self::SETTINGS_VIEW,
                self::SETTINGS_EDIT,
                self::SETTINGS_BRANDING,
            ],
            self::SETTINGS_EDIT => [
                self::SETTINGS_VIEW,
            ],
            self::SETTINGS_BRANDING => [
                self::SETTINGS_VIEW,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allPermissionNames(): array
    {
        return array_values(array_unique([
            self::MANAGE_CAUSES,
            ...self::causePermissionNames(),
            ...self::campaignPermissionNames(),
            self::MANAGE_PACKAGES,
            ...self::packagePermissionNames(),
            self::DONATION_VIEW,
            self::DONATION_VIEW_ALL,
            self::DONATION_CREATE,
            self::DONATION_EDIT,
            self::DONATION_EXPORT,
            self::DONATION_RECOVERY_VIEW,
            self::MANAGE_DONATIONS,
            self::RECEIPT_PREVIEW,
            self::RECEIPT_PRINT,
            self::RECEIPT_RESEND,
            self::RECEIPT_GENERATE,
            self::RECEIPT_RESEND_NOTIFICATIONS,
            self::RECEIPT_RECOVERY_NUDGE,
            self::MANAGE_RECEIPTS,
            self::DONOR_VIEW,
            self::DONOR_EXPORT,
            self::DONOR_IMPORT,
            self::DONOR_MANAGE_COMMUNICATION,
            self::DONOR_CRM_OWNERS,
            self::DONOR_CRM_NOTES,
            self::DONOR_CRM_TASKS,
            self::MANAGE_DONOR_CRM,
            self::ANALYTICS_VIEW,
            self::ANALYTICS_EXPORT,
            self::REPORT_VIEW,
            self::REPORT_EXPORT,
            self::REFERRAL_VIEW,
            self::SUBSCRIPTION_VIEW,
            self::SUBSCRIPTION_EXPORT,
            self::SUBSCRIPTION_CANCEL,
            self::SUBSCRIPTION_SYNC,
            self::MANAGE_SUBSCRIPTIONS,
            self::QR_CODE_VIEW,
            self::QR_CODE_CREATE,
            self::QR_CODE_CLOSE,
            self::QR_CODE_SYNC,
            self::MANAGE_QR_CODES,
            ...self::aisensyPermissionNames(),
            self::MANAGE_AISENSY,
            ...self::whatsappBroadcastPermissionNames(),
            self::MANAGE_WHATSAPP_CAMPAIGNS,
            ...self::userPermissionNames(),
            self::ROLE_MANAGE,
            self::DEPARTMENT_MANAGE,
            self::MANAGE_USERS,
            self::SETTINGS_VIEW,
            self::SETTINGS_EDIT,
            self::SETTINGS_BRANDING,
            self::MANAGE_SETTINGS,
        ]));
    }

    /**
     * @return list<string>
     */
    public static function portalPermissions(): array
    {
        return self::allPermissionNames();
    }

    public static function userCan(?User $user, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->can($permission)) {
            return true;
        }

        foreach (self::legacyPermissionsFor($permission) as $legacy) {
            if ($user->can($legacy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $permissions
     */
    public static function userCanAny(?User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::userCan($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    public static function middleware(string $permission): string
    {
        return self::middlewareAny([$permission]);
    }

    /**
     * @param  list<string>  $permissions
     */
    public static function middlewareAny(array $permissions): string
    {
        $parts = [];

        foreach ($permissions as $permission) {
            $parts[] = $permission;
            $parts = array_merge($parts, self::legacyPermissionsFor($permission));
        }

        return 'permission:'.implode('|', array_unique($parts));
    }

    /**
     * @return list<string>
     */
    public static function legacyPermissionsFor(string $permission): array
    {
        $legacy = [];

        foreach (self::legacyPermissionMap() as $legacyPermission => $grants) {
            if (in_array($permission, $grants, true)) {
                $legacy[] = $legacyPermission;
            }
        }

        return array_values(array_unique($legacy));
    }

    /**
     * @return list<string>
     */
    public static function causePermissionNames(): array
    {
        return [
            self::CAUSE_VIEW,
            self::CAUSE_CREATE,
            self::CAUSE_EDIT,
            self::CAUSE_DELETE,
            self::CAUSE_COPY_LINKS,
        ];
    }

    /**
     * @return list<string>
     */
    public static function campaignPermissionNames(): array
    {
        return [
            self::CAMPAIGN_VIEW,
            self::CAMPAIGN_CREATE,
            self::CAMPAIGN_EDIT,
            self::CAMPAIGN_DELETE,
            self::CAMPAIGN_COPY_LINKS,
        ];
    }

    /**
     * @return list<string>
     */
    public static function packagePermissionNames(): array
    {
        return [
            self::PACKAGE_VIEW,
            self::PACKAGE_CREATE,
            self::PACKAGE_EDIT,
            self::PACKAGE_DELETE,
            self::PACKAGE_COPY_LINKS,
        ];
    }

    /**
     * @return list<string>
     */
    public static function aisensyPermissionNames(): array
    {
        return [
            self::AISENSY_VIEW,
            self::AISENSY_CREATE,
            self::AISENSY_EDIT,
            self::AISENSY_DELETE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function whatsappBroadcastPermissionNames(): array
    {
        return [
            self::WHATSAPP_BROADCAST_VIEW,
            self::WHATSAPP_BROADCAST_CREATE,
            self::WHATSAPP_BROADCAST_MANAGE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function userPermissionNames(): array
    {
        return [
            self::USER_VIEW,
            self::USER_CREATE,
            self::USER_EDIT,
            self::USER_DELETE,
            self::USER_RESTORE,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function causeAbilities(?User $user): array
    {
        return self::abilitiesFor($user, self::causePermissionNames(), [
            'can_view' => self::CAUSE_VIEW,
            'can_create' => self::CAUSE_CREATE,
            'can_edit' => self::CAUSE_EDIT,
            'can_delete' => self::CAUSE_DELETE,
            'can_copy_links' => self::CAUSE_COPY_LINKS,
        ]);
    }

    /**
     * @return array<string, bool>
     */
    public static function campaignAbilities(?User $user): array
    {
        return self::abilitiesFor($user, self::campaignPermissionNames(), [
            'can_view' => self::CAMPAIGN_VIEW,
            'can_create' => self::CAMPAIGN_CREATE,
            'can_edit' => self::CAMPAIGN_EDIT,
            'can_delete' => self::CAMPAIGN_DELETE,
            'can_copy_links' => self::CAMPAIGN_COPY_LINKS,
        ]);
    }

    /**
     * @return array<string, bool>
     */
    public static function packageAbilities(?User $user): array
    {
        return self::abilitiesFor($user, self::packagePermissionNames(), [
            'can_view' => self::PACKAGE_VIEW,
            'can_create' => self::PACKAGE_CREATE,
            'can_edit' => self::PACKAGE_EDIT,
            'can_delete' => self::PACKAGE_DELETE,
            'can_copy_links' => self::PACKAGE_COPY_LINKS,
        ]);
    }

    /**
     * @param  list<string>  $all
     * @param  array<string, string>  $map
     * @return array<string, bool>
     */
    private static function abilitiesFor(?User $user, array $all, array $map): array
    {
        $abilities = [];

        foreach ($map as $key => $permission) {
            $abilities[$key] = self::userCan($user, $permission);
        }

        return $abilities;
    }

    /**
     * @return list<array{key: string, label: string, description: string, permissions: list<array{name: string, label: string, help: string}>}>
     */
    public static function groupedDefinitions(): array
    {
        return [
            [
                'key' => 'causes',
                'label' => 'Causes',
                'description' => 'Fundraising cause pages and donate links.',
                'permissions' => self::defs([
                    [self::CAUSE_VIEW, 'View causes', 'Open the causes list.'],
                    [self::CAUSE_CREATE, 'Create causes', 'Add new causes.'],
                    [self::CAUSE_EDIT, 'Edit causes', 'Update, activate/deactivate, and reorder causes.'],
                    [self::CAUSE_DELETE, 'Delete causes', 'Remove causes permanently.'],
                    [self::CAUSE_COPY_LINKS, 'Copy cause links', 'Copy tracked donate links only.'],
                ]),
            ],
            [
                'key' => 'campaigns',
                'label' => 'Donation campaigns',
                'description' => 'Landing pages, recurring mandates, and campaign share links.',
                'permissions' => self::defs([
                    [self::CAMPAIGN_VIEW, 'View campaigns', 'Open campaign list and stats.'],
                    [self::CAMPAIGN_CREATE, 'Create campaigns', 'Add new donation campaigns.'],
                    [self::CAMPAIGN_EDIT, 'Edit campaigns', 'Update campaigns and activate/deactivate.'],
                    [self::CAMPAIGN_DELETE, 'Delete campaigns', 'Remove campaigns permanently.'],
                    [self::CAMPAIGN_COPY_LINKS, 'Copy campaign links', 'Copy tracked campaign links only.'],
                ]),
            ],
            [
                'key' => 'packages',
                'label' => 'Packages',
                'description' => 'Preset donation amounts linked to causes.',
                'permissions' => self::defs([
                    [self::PACKAGE_VIEW, 'View packages', 'Open the packages list.'],
                    [self::PACKAGE_CREATE, 'Create packages', 'Add new donation packages.'],
                    [self::PACKAGE_EDIT, 'Edit packages', 'Update packages and toggle active/default.'],
                    [self::PACKAGE_DELETE, 'Delete packages', 'Remove packages permanently.'],
                    [self::PACKAGE_COPY_LINKS, 'Copy package links', 'Copy tracked package donate links only.'],
                ]),
            ],
            [
                'key' => 'donations',
                'label' => 'Donations',
                'description' => 'Donation lists, offline entry, export, and recovery queue.',
                'permissions' => self::defs([
                    [self::DONATION_VIEW, 'View donations', 'Open donation lists (own receipts unless “view all” is also given).'],
                    [self::DONATION_VIEW_ALL, 'View all donations', 'See every staff member’s donations.'],
                    [self::DONATION_CREATE, 'Create donations', 'Record new offline donations.'],
                    [self::DONATION_EDIT, 'Edit donations', 'Update existing donation records.'],
                    [self::DONATION_EXPORT, 'Export donations', 'Download donation exports.'],
                    [self::DONATION_RECOVERY_VIEW, 'View donation recovery', 'Open the checkout recovery queue.'],
                ]),
            ],
            [
                'key' => 'receipts',
                'label' => 'Receipts & notifications',
                'description' => 'Receipt preview/print/resend and WhatsApp follow-ups.',
                'permissions' => self::defs([
                    [self::RECEIPT_PREVIEW, 'Preview receipts', 'Preview donation receipts.'],
                    [self::RECEIPT_PRINT, 'Print receipts', 'Open printable receipt views.'],
                    [self::RECEIPT_RESEND, 'Resend receipts', 'Resend receipt emails.'],
                    [self::RECEIPT_GENERATE, 'Generate receipts', 'Generate receipt PDFs/jobs.'],
                    [self::RECEIPT_RESEND_NOTIFICATIONS, 'Resend notifications', 'Resend thank-you, certificate, payment link, and sheet messages.'],
                    [self::RECEIPT_RECOVERY_NUDGE, 'Nudge recovery', 'Send recovery nudges for abandoned checkouts.'],
                ]),
            ],
            [
                'key' => 'donors',
                'label' => 'Donors',
                'description' => 'Donor directory, import/export, and communication preferences.',
                'permissions' => self::defs([
                    [self::DONOR_VIEW, 'View donors', 'Open donor list and profiles.'],
                    [self::DONOR_EXPORT, 'Export donors', 'Export donor lists.'],
                    [self::DONOR_IMPORT, 'Import donors', 'Import donor CSV contacts.'],
                    [self::DONOR_MANAGE_COMMUNICATION, 'Manage communication', 'Update WhatsApp opt-out and communication flags.'],
                ]),
            ],
            [
                'key' => 'donor_crm',
                'label' => 'Donor CRM',
                'description' => 'Ownership, notes, and follow-up tasks.',
                'permissions' => self::defs([
                    [self::DONOR_CRM_OWNERS, 'Manage owners', 'Assign donor owners.'],
                    [self::DONOR_CRM_NOTES, 'Manage notes', 'Add and delete donor notes.'],
                    [self::DONOR_CRM_TASKS, 'Manage tasks', 'Create, update, and complete donor tasks.'],
                ]),
            ],
            [
                'key' => 'insights',
                'label' => 'Analytics & reports',
                'description' => 'Dashboards, finance reports, referrals, and subscriptions.',
                'permissions' => self::defs([
                    [self::ANALYTICS_VIEW, 'View analytics', 'Open analytics dashboards.'],
                    [self::ANALYTICS_EXPORT, 'Export analytics', 'Download analytics exports.'],
                    [self::REPORT_VIEW, 'View reports', 'Open finance and donation reports.'],
                    [self::REPORT_EXPORT, 'Export reports', 'Download report exports.'],
                    [self::REFERRAL_VIEW, 'View staff referrals', 'Open partner attribution reporting.'],
                    [self::SUBSCRIPTION_VIEW, 'View subscriptions', 'Open recurring subscription list.'],
                    [self::SUBSCRIPTION_EXPORT, 'Export subscriptions', 'Download subscription exports.'],
                    [self::SUBSCRIPTION_CANCEL, 'Cancel subscriptions', 'Cancel recurring mandates.'],
                    [self::SUBSCRIPTION_SYNC, 'Sync subscriptions', 'Sync subscription status from gateway.'],
                    [self::QR_CODE_VIEW, 'View QR codes', 'Open Razorpay QR code list.'],
                    [self::QR_CODE_CREATE, 'Create QR codes', 'Create UPI QR codes in Razorpay.'],
                    [self::QR_CODE_CLOSE, 'Close QR codes', 'Close active Razorpay QR codes.'],
                    [self::QR_CODE_SYNC, 'Sync QR codes', 'Sync QR codes from Razorpay.'],
                ]),
            ],
            [
                'key' => 'engagement',
                'label' => 'Engagement',
                'description' => 'WhatsApp accounts and broadcast campaigns.',
                'permissions' => self::defs([
                    [self::AISENSY_VIEW, 'View WhatsApp accounts', 'Open AiSensy account list.'],
                    [self::AISENSY_CREATE, 'Create WhatsApp accounts', 'Add AiSensy accounts.'],
                    [self::AISENSY_EDIT, 'Edit WhatsApp accounts', 'Update accounts and toggle active.'],
                    [self::AISENSY_DELETE, 'Delete WhatsApp accounts', 'Remove AiSensy accounts.'],
                    [self::WHATSAPP_BROADCAST_VIEW, 'View broadcasts', 'Open WhatsApp broadcast list and details.'],
                    [self::WHATSAPP_BROADCAST_CREATE, 'Create broadcasts', 'Create and launch WhatsApp broadcasts.'],
                    [self::WHATSAPP_BROADCAST_MANAGE, 'Manage broadcasts', 'Cancel, retry, sync delivery, and manage templates.'],
                ]),
            ],
            [
                'key' => 'system',
                'label' => 'System',
                'description' => 'Staff users, roles, departments, and settings.',
                'permissions' => self::defs([
                    [self::USER_VIEW, 'View users', 'Open staff user list.'],
                    [self::USER_CREATE, 'Create users', 'Add new staff users.'],
                    [self::USER_EDIT, 'Edit users', 'Update users, roles, and departments.'],
                    [self::USER_DELETE, 'Delete users', 'Archive staff users.'],
                    [self::USER_RESTORE, 'Restore users', 'Restore archived users.'],
                    [self::ROLE_MANAGE, 'Manage roles', 'Create and edit roles/permissions.'],
                    [self::DEPARTMENT_MANAGE, 'Manage departments', 'Create and edit departments.'],
                    [self::SETTINGS_VIEW, 'View settings', 'Open settings pages.'],
                    [self::SETTINGS_EDIT, 'Edit settings', 'Update notification and system settings.'],
                    [self::SETTINGS_BRANDING, 'Edit branding', 'Update logos, branding, and public site settings.'],
                ]),
            ],
            [
                'key' => 'legacy',
                'label' => 'Legacy full access',
                'description' => 'Older broad permissions — still work and grant full module access.',
                'permissions' => self::defs([
                    [self::MANAGE_CAUSES, 'Manage causes (full)', 'All cause and donation campaign operations.'],
                    [self::MANAGE_PACKAGES, 'Manage packages (full)', 'All package operations.'],
                    [self::MANAGE_DONATIONS, 'Manage donations (full)', 'Create and edit donations.'],
                    [self::MANAGE_RECEIPTS, 'Manage receipts (full)', 'All receipt and notification operations.'],
                    [self::MANAGE_DONOR_CRM, 'Manage donor CRM (full)', 'All donor CRM operations.'],
                    [self::MANAGE_SUBSCRIPTIONS, 'Manage subscriptions (full)', 'Cancel and sync subscriptions.'],
                    [self::MANAGE_QR_CODES, 'Manage QR codes (full)', 'Create, close, and sync Razorpay QR codes.'],
                    [self::MANAGE_AISENSY, 'Manage WhatsApp accounts (full)', 'All AiSensy account operations.'],
                    [self::MANAGE_WHATSAPP_CAMPAIGNS, 'Manage WhatsApp broadcasts (full)', 'All broadcast operations.'],
                    [self::MANAGE_USERS, 'Manage users (full)', 'All user, role, and department operations.'],
                    [self::MANAGE_SETTINGS, 'Manage settings (full)', 'All settings and branding operations.'],
                ]),
            ],
        ];
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $rows
     * @return list<array{name: string, label: string, help: string}>
     */
    private static function defs(array $rows): array
    {
        return array_map(fn (array $row) => [
            'name' => $row[0],
            'label' => $row[1],
            'help' => $row[2],
        ], $rows);
    }
}
