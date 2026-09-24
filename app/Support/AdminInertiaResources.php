<?php

namespace App\Support;

use App\Models\AisensyAccount;
use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\Department;
use App\Models\DonationCampaign;
use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use App\Models\Donor;
use App\Models\DonorNote;
use App\Models\DonorTask;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappCampaignRun;
use App\Services\DonationAttributionService;
use App\Services\DonationWhatsAppPolicy;
use App\Services\RazorpayPaymentLinkService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminInertiaResources
{
    public const LIST_PER_PAGE = 15;

    public static function cause(Cause $cause): array
    {
        return [
            'id' => $cause->id,
            'title' => $cause->title,
            'slug' => $cause->slug,
            'excerpt' => $cause->excerpt,
            'description' => $cause->description,
            'cta_text' => $cause->cta_text,
            'contact_heading' => $cause->contact_heading,
            'contact_address' => $cause->contact_address,
            'contact_phone' => $cause->contact_phone,
            'contact_email' => $cause->contact_email,
            'hero_image' => $cause->hero_image,
            'icon_uri' => $cause->icon_uri,
            'icon_uri_active' => $cause->icon_uri_active,
            'images' => $cause->images ?? [],
            'details_text' => implode("\n", $cause->details ?? []),
            'aisensy_account_id' => $cause->aisensy_account_id,
            'aisensy_payment_link_campaign' => $cause->aisensy_payment_link_campaign,
            'aisensy_thank_you_campaign' => $cause->aisensy_thank_you_campaign,
            'aisensy_certificate_campaign' => $cause->aisensy_certificate_campaign,
            'aisensy_receipt_campaign' => $cause->aisensy_receipt_campaign,
            'certificate_template' => $cause->certificate_template,
            'certificate_template_english' => $cause->certificate_template_english,
            'aisensy_send_thank_you' => (bool) ($cause->aisensy_send_thank_you ?? true),
            'aisensy_send_certificate' => (bool) ($cause->aisensy_send_certificate ?? true),
            'aisensy_send_receipt' => (bool) ($cause->aisensy_send_receipt ?? true),
            'aisensy_thank_you_image' => $cause->aisensy_thank_you_image,
            'aisensy_thank_you_message_mode' => $cause->aisensy_thank_you_message_mode ?? 'template',
            'aisensy_thank_you_message_template' => $cause->aisensy_thank_you_message_template,
            'aisensy_thank_you_include_name' => (bool) ($cause->aisensy_thank_you_include_name ?? true),
            'aisensy_thank_you_include_amount' => (bool) ($cause->aisensy_thank_you_include_amount ?? true),
            'aisensy_thank_you_include_cause' => (bool) ($cause->aisensy_thank_you_include_cause ?? true),
            'aisensy_thank_you_include_receipt' => (bool) ($cause->aisensy_thank_you_include_receipt ?? false),
            'sort_order' => (int) ($cause->sort_order ?? 0),
            'default_amount' => $cause->default_amount,
            'default_title' => $cause->default_title,
            'allow_custom_amount' => (bool) ($cause->allow_custom_amount ?? true),
            'allow_recurring' => (bool) ($cause->allow_recurring ?? false),
            'allow_weekly_recurring' => (bool) ($cause->allow_weekly_recurring ?? false),
            'pan_required' => (bool) ($cause->pan_required ?? true),
            'is_active' => (bool) ($cause->is_active ?? true),
            'packages' => $cause->relationLoaded('packages')
                ? $cause->packages->map(fn (CausePackage $package) => self::package($package))->values()
                : [],
        ];
    }

    public static function package(CausePackage $package): array
    {
        return [
            'id' => $package->id,
            'cause_id' => $package->cause_id,
            'title' => $package->title,
            'amount' => (float) $package->amount,
            'image' => $package->image,
            'sort_order' => (int) ($package->sort_order ?? 0),
            'is_active' => (bool) ($package->is_active ?? true),
            'is_default' => (bool) ($package->is_default ?? false),
            'allow_recurring' => (bool) ($package->allow_recurring ?? false),
        ];
    }

    public static function aisensyAccount(AisensyAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'api_key' => $account->hasApiKey() ? '********' : '',
            'has_api_key' => $account->hasApiKey(),
            'project_api_password' => $account->hasProjectApiPassword() ? '********' : '',
            'has_project_api_password' => $account->hasProjectApiPassword(),
            'project_id' => $account->project_id,
            'country_code' => $account->country_code,
            'is_active' => (bool) $account->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function whatsappCampaignRun(WhatsappCampaignRun $run, bool $detailed = false): array
    {
        $payload = [
            'id' => $run->id,
            'uuid' => $run->uuid,
            'name' => $run->name,
            'status' => $run->status,
            'live_campaign_name' => $run->live_campaign_name,
            'audience_count' => $run->audience_count,
            'sent_count' => $run->sent_count,
            'failed_count' => $run->failed_count,
            'skipped_count' => $run->skipped_count,
            'delivery_sent_count' => $run->delivery_sent_count,
            'delivery_delivered_count' => $run->delivery_delivered_count,
            'delivery_read_count' => $run->delivery_read_count,
            'delivery_failed_count' => $run->delivery_failed_count,
            'delivery_synced_at' => $run->delivery_synced_at?->format('d M Y, h:i A'),
            'account_name' => $run->account?->name,
            'template_name' => $run->template?->name,
            'created_by' => $run->creator?->name,
            'started_at' => $run->started_at?->format('d M Y, h:i A'),
            'finished_at' => $run->finished_at?->format('d M Y, h:i A'),
            'created_at' => $run->created_at?->format('d M Y, h:i A'),
            'show_url' => route('admin.whatsapp-campaigns.show', $run),
            'cancel_url' => route('admin.whatsapp-campaigns.cancel', $run),
            'retry_failed_url' => route('admin.whatsapp-campaigns.retry-failed', $run),
            'sync_delivery_url' => route('admin.whatsapp-campaigns.sync-delivery', $run),
            'is_cancellable' => $run->isCancellable(),
            'can_retry_failed' => $run->failed_count > 0 && $run->status !== WhatsappCampaignRun::STATUS_CANCELLED,
            'can_sync_delivery' => filled($run->live_campaign_name) && $run->aisensy_account_id,
        ];

        if ($detailed) {
            $payload['filters'] = $run->filters_json ?? [];
            $payload['param_map'] = $run->param_map_json ?? [];
            $payload['media_filename'] = $run->media_filename;
            $payload['location'] = $run->location_json ?? null;
            $payload['last_error'] = $run->last_error;
        }

        return $payload;
    }

    public static function user(User $user): array
    {
        $monthlyBudget = MarketerMonthlyBudgetService::forUserMonth($user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'referral_code' => $user->referral_code,
            'donation_target' => $user->donation_target,
            'monthly_target_amount' => $monthlyBudget['target_amount'],
            'monthly_spend_amount' => $monthlyBudget['spend_amount'],
            'monthly_budget_year_month' => $monthlyBudget['year_month'],
            'department_id' => $user->department_id,
            'department_label' => $user->relationLoaded('department')
                ? $user->department?->name
                : null,
            'roles' => $user->relationLoaded('roles')
                ? $user->roles->pluck('name')->values()->all()
                : $user->getRoleNames()->values()->all(),
            'direct_permissions' => $user->relationLoaded('permissions')
                ? $user->permissions->pluck('name')->values()->all()
                : $user->getDirectPermissions()->pluck('name')->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function userListRow(User $user, ?User $actor = null): array
    {
        $actor ??= auth()->user();

        return [
            ...self::user($user),
            'department_label' => $user->department?->name,
            'deleted_at' => $user->deleted_at?->toIso8601String(),
            'created_at_label' => $user->created_at?->format('d M Y'),
            'is_archived' => $user->trashed(),
            'is_super_admin' => $user->hasRole(AdminRoleGuard::SUPER_ADMIN),
            'can_edit' => AdminRoleGuard::canManageUser($actor, $user) && ! $user->trashed(),
            'can_delete' => AdminRoleGuard::canDeleteUser($actor, $user),
            'can_restore' => AdminRoleGuard::canRestoreUser($actor, $user),
            'referrals_href' => filled($user->referral_code) && self::canViewStaffReferrals($actor)
                ? route('admin.referrals.index', [
                    'partner_user_id' => $user->id,
                    'duration' => 'all',
                ])
                : null,
        ];
    }

    public static function canViewStaffReferrals(?User $actor): bool
    {
        return AdminPermissions::userCan($actor, AdminPermissions::REFERRAL_VIEW)
            || AdminStaffReferralsData::userCanViewAll($actor);
    }

    public static function department(Department $department): array
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'slug' => $department->slug,
            'description' => $department->description,
            'is_active' => (bool) $department->is_active,
            'sort_order' => (int) $department->sort_order,
            'users_count' => (int) ($department->users_count ?? $department->users()->count()),
        ];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public static function departmentOptions(bool $includeInactive = false): array
    {
        return Department::query()
            ->when(! $includeInactive, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $department) => [
                'value' => $department->id,
                'label' => $department->name,
            ])
            ->values()
            ->all();
    }

    public static function role(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->relationLoaded('permissions')
                ? $role->permissions->pluck('name')->values()->all()
                : $role->getPermissionNames()->values()->all(),
        ];
    }

    public static function permission(Permission $permission): array
    {
        return [
            'id' => $permission->id,
            'name' => $permission->name,
        ];
    }

    public static function causeListRow(Cause $cause, ?User $viewer = null): array
    {
        $viewer ??= auth()->user();
        $publicUrl = DonationPublicFrontend::donateCauseUrl($cause->slug);
        $canCopyLinks = AdminPermissions::userCan($viewer, AdminPermissions::CAUSE_COPY_LINKS);

        return [
            'id' => $cause->id,
            'title' => $cause->title,
            'slug' => $cause->slug,
            'is_active' => (bool) $cause->is_active,
            'sort_order' => (int) $cause->sort_order,
            'public_url' => route('donate.show', $cause->slug, false),
            'share_url' => $canCopyLinks
                ? StaffReferral::trackedShareUrl($publicUrl, $viewer?->referral_code)
                : null,
            'can_edit' => AdminPermissions::userCan($viewer, AdminPermissions::CAUSE_EDIT),
            'can_delete' => AdminPermissions::userCan($viewer, AdminPermissions::CAUSE_DELETE),
            'can_copy_links' => $canCopyLinks,
        ];
    }

    public static function packageListRow(CausePackage $package, ?User $viewer = null): array
    {
        $viewer ??= auth()->user();
        $causeSlug = $package->relationLoaded('cause')
            ? $package->cause?->slug
            : $package->cause()->value('slug');

        $canCopyLinks = AdminPermissions::userCan($viewer, AdminPermissions::PACKAGE_COPY_LINKS);
        $shareUrl = null;

        if ($canCopyLinks && is_string($causeSlug) && $causeSlug !== '') {
            $baseUrl = DonationPublicFrontend::donateCauseUrl($causeSlug);
            $separator = str_contains($baseUrl, '?') ? '&' : '?';
            $publicUrl = $baseUrl.$separator.'package_id='.$package->id;
            $shareUrl = StaffReferral::trackedShareUrl($publicUrl, $viewer?->referral_code);
        }

        return [
            ...self::package($package),
            'cause_title' => $package->relationLoaded('cause') ? $package->cause?->title : null,
            'cause_slug' => $causeSlug,
            'share_url' => $shareUrl,
            'can_edit' => AdminPermissions::userCan($viewer, AdminPermissions::PACKAGE_EDIT),
            'can_delete' => AdminPermissions::userCan($viewer, AdminPermissions::PACKAGE_DELETE),
            'can_copy_links' => $canCopyLinks,
        ];
    }

    public static function donorListRow(Donor $donor): array
    {
        $latestPaid = $donor->relationLoaded('latestPaidDonationOrder')
            ? $donor->latestPaidDonationOrder
            : null;

        return [
            'id' => $donor->id,
            'name' => $donor->name,
            'email' => $donor->email,
            'phone' => $donor->phone,
            'location' => implode(', ', array_filter([$donor->city, $donor->state])),
            'source' => $latestPaid
                ? DonationAttributionService::trafficSourceLabel($latestPaid)
                : 'Unknown',
            'utm_campaign' => $latestPaid?->utm_campaign,
            'paid_donations' => (int) ($donor->paid_donations ?? 0),
            'paid_amount' => (float) ($donor->paid_amount ?? 0),
            'total_attempts' => (int) ($donor->total_attempts ?? 0),
            'last_paid_at' => $donor->last_paid_donation_at
                ? \Illuminate\Support\Carbon::parse($donor->last_paid_donation_at)->format('d M Y')
                : null,
            'owner' => $donor->relationLoaded('owner') && $donor->owner
                ? [
                    'id' => $donor->owner->id,
                    'name' => $donor->owner->name,
                ]
                : null,
            'open_tasks_count' => (int) ($donor->open_tasks_count ?? 0),
        ];
    }

    /**
     * @return array{id: int, body: string, author: ?string, created_at: ?string, created_at_human: ?string}
     */
    public static function donorNote(DonorNote $note): array
    {
        $note->loadMissing('author:id,name');

        return [
            'id' => $note->id,
            'body' => $note->body,
            'author' => $note->author?->name,
            'created_at' => $note->created_at?->format('d M Y, h:i A'),
            'created_at_human' => $note->created_at?->diffForHumans(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     body: ?string,
     *     status: string,
     *     is_overdue: bool,
     *     due_at: ?string,
     *     due_at_input: ?string,
     *     completed_at: ?string,
     *     assignee: ?array{id: int, name: string},
     *     creator: ?string,
     *     created_at: ?string
     * }
     */
    public static function donorTask(DonorTask $task): array
    {
        $task->loadMissing(['assignee:id,name', 'creator:id,name']);

        return [
            'id' => $task->id,
            'title' => $task->title,
            'body' => $task->body,
            'status' => $task->status,
            'is_overdue' => $task->isOverdue(),
            'due_at' => $task->due_at?->format('d M Y, h:i A'),
            'due_at_input' => $task->due_at?->format('Y-m-d\TH:i'),
            'completed_at' => $task->completed_at?->format('d M Y, h:i A'),
            'assignee' => $task->assignee ? [
                'id' => $task->assignee->id,
                'name' => $task->assignee->name,
            ] : null,
            'creator' => $task->creator?->name,
            'created_at' => $task->created_at?->format('d M Y, h:i A'),
        ];
    }

    public static function staffOptions(): array
    {
        $permissionNames = Permission::query()
            ->whereIn('name', AdminPermissions::portalPermissions())
            ->pluck('name')
            ->all();

        return User::query()
            ->where(function ($query) use ($permissionNames): void {
                if ($permissionNames !== []) {
                    $query->permission($permissionNames);
                }

                $query->orWhereHas('roles', fn ($roles) => $roles->where('name', 'super_admin'));
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->unique('id')
            ->values()
            ->map(fn (User $user) => [
                'value' => $user->id,
                'label' => $user->name,
            ])
            ->all();
    }

    public static function paginated(LengthAwarePaginator $paginator, callable $mapper, int $onEachSide = 3): array
    {
        return [
            'data' => collect($paginator->items())->map($mapper)->values()->all(),
            'links' => $paginator->onEachSide($onEachSide)->linkCollection()->toArray(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public static function setting(Setting $setting): array
    {
        $isToggle = Setting::isToggleKey($setting->key);
        $isInteger = Setting::isIntegerKey($setting->key);
        $integerRules = $isInteger ? Setting::integerSettingRules()[$setting->key] : null;

        return [
            'id' => $setting->id,
            'key' => $setting->key,
            'label' => $setting->label,
            'description' => $setting->description,
            'group' => $setting->group,
            'is_toggle' => $isToggle,
            'is_integer' => $isInteger,
            'min' => $integerRules['min'] ?? null,
            'max' => $integerRules['max'] ?? null,
            'enabled' => $isToggle ? (bool) $setting->value : null,
            'value' => $isToggle ? null : (string) $setting->value,
            'toggle_url' => $isToggle ? route('admin.settings.toggle', $setting) : null,
            'update_url' => $isToggle ? null : route('admin.settings.update', $setting),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function brandingForm(): array
    {
        $values = BrandingStore::merged();
        $defaults = BrandingStore::envDefaults();

        return [
            'name' => (string) ($values['name'] ?? ''),
            'short_name' => (string) ($values['short_name'] ?? ''),
            'legal_name' => (string) ($values['legal_name'] ?? ''),
            'tagline' => (string) ($values['tagline'] ?? ''),
            'admin_label' => (string) ($values['admin_label'] ?? ''),
            'razorpay_name' => (string) ($values['razorpay_name'] ?? ''),
            'recurring_mandate' => (string) ($values['recurring_mandate'] ?? ''),
            'receipt_thank_you' => (string) ($values['receipt_thank_you'] ?? ''),
            'payment_link_description' => (string) ($values['payment_link_description'] ?? ''),
            'website_url' => (string) ($values['urls']['website'] ?? ''),
            'privacy_url' => (string) ($values['urls']['privacy'] ?? ''),
            'refund_url' => (string) ($values['urls']['refund'] ?? ''),
            'terms_url' => (string) ($values['urls']['terms'] ?? ''),
            'canonical_url' => (string) ($values['seo']['canonical_url'] ?? ''),
            'seo_home_description' => (string) ($values['seo']['home_description'] ?? ''),
            'logo' => (string) ($values['assets']['logo'] ?? ''),
            'logo_public' => (string) ($values['assets']['logo_public'] ?? ''),
            'favicon' => (string) ($values['assets']['favicon'] ?? ''),
            'og_image' => (string) ($values['assets']['og_image'] ?? ''),
            'footer_about' => (string) ($values['footer']['about'] ?? ''),
            'contact_address' => (string) ($values['contact']['address'] ?? ''),
            'contact_phone_primary' => (string) ($values['contact']['phone_primary'] ?? ''),
            'contact_phone_secondary' => (string) ($values['contact']['phone_secondary'] ?? ''),
            'contact_email' => (string) ($values['contact']['email'] ?? ''),
            'social_facebook' => (string) ($values['social']['facebook'] ?? ''),
            'social_instagram' => (string) ($values['social']['instagram'] ?? ''),
            'social_youtube' => (string) ($values['social']['youtube'] ?? ''),
            'social_whatsapp' => (string) ($values['social']['whatsapp'] ?? ''),
            'bank_account_name' => (string) ($values['bank']['account_name'] ?? ''),
            'bank_account_number' => (string) ($values['bank']['account_number'] ?? ''),
            'bank_ifsc' => (string) ($values['bank']['ifsc'] ?? ''),
            'bank_name' => (string) ($values['bank']['bank_name'] ?? ''),
            'bank_branch' => (string) ($values['bank']['branch'] ?? ''),
            'bank_account_type' => (string) ($values['bank']['account_type'] ?? ''),
            'bank_upi_id' => (string) ($values['bank']['upi_id'] ?? ''),
            'bank_note' => (string) ($values['bank']['note'] ?? ''),
            'defaults' => [
                'name' => (string) ($defaults['name'] ?? ''),
                'short_name' => (string) ($defaults['short_name'] ?? ''),
                'tagline' => (string) ($defaults['tagline'] ?? ''),
            ],
            'has_overrides' => BrandingStore::overrides() !== [],
        ];
    }

    public static function subscriptionListRow(DonationSubscription $subscription): array
    {
        $subscription->loadMissing(['cause:id,title', 'package:id,title']);

        return [
            'uuid' => $subscription->subscription_uuid,
            'donor_name' => $subscription->donor_name,
            'donor_email' => $subscription->donor_email,
            'donor_phone' => $subscription->donor_phone,
            'cause' => $subscription->cause?->title ?? '—',
            'package' => $subscription->package?->title ?? $subscription->item_title,
            'item_title' => $subscription->item_title,
            'frequency' => $subscription->frequency,
            'frequency_label' => $subscription->frequencyLabel(),
            'total_amount' => (float) $subscription->total_amount,
            'status' => $subscription->status,
            'status_label' => $subscription->statusLabel(),
            'billing_cycle_count' => (int) $subscription->billing_cycle_count,
            'razorpay_subscription_id' => $subscription->razorpay_subscription_id,
            'started_at' => $subscription->started_at?->format('d M Y'),
            'next_charge_at' => $subscription->next_charge_at?->format('d M Y'),
            'source' => DonationAttributionService::trafficSourceLabel($subscription),
            'utm_campaign' => $subscription->utm_campaign,
            'utm_content' => $subscription->utm_content,
            'created_at' => $subscription->created_at?->format('d M Y, h:i A'),
            'created_at_ts' => $subscription->created_at?->timestamp ?? 0,
        ];
    }

    public static function subscriptionDetail(DonationSubscription $subscription): array
    {
        $subscription->loadMissing(['cause', 'package', 'donor']);

        $paidOrders = $subscription->relationLoaded('donationOrders')
            ? $subscription->donationOrders->where('status', DonationOrder::STATUS_PAID)
            : collect();

        return [
            'uuid' => $subscription->subscription_uuid,
            'status' => $subscription->status,
            'status_label' => $subscription->statusLabel(),
            'frequency' => $subscription->frequency,
            'frequency_label' => $subscription->frequencyLabel(),
            'quantity' => (int) $subscription->quantity,
            'unit_amount' => (float) $subscription->unit_amount,
            'total_amount' => (float) $subscription->total_amount,
            'currency' => $subscription->currency,
            'item_title' => $subscription->item_title,
            'billing_cycle_count' => (int) $subscription->billing_cycle_count,
            'total_count' => $subscription->total_count,
            'razorpay_plan_id' => $subscription->razorpay_plan_id,
            'razorpay_subscription_id' => $subscription->razorpay_subscription_id,
            'started_at' => $subscription->started_at?->format('d M Y, h:i A'),
            'next_charge_at' => $subscription->next_charge_at?->format('d M Y, h:i A'),
            'ended_at' => $subscription->ended_at?->format('d M Y, h:i A'),
            'cancelled_at' => $subscription->cancelled_at?->format('d M Y, h:i A'),
            'cancel_reason' => $subscription->cancel_reason,
            'created_at' => $subscription->created_at?->format('d M Y, h:i A'),
            'cause' => [
                'id' => $subscription->cause?->id,
                'title' => $subscription->cause?->title,
                'slug' => $subscription->cause?->slug,
            ],
            'package' => [
                'id' => $subscription->package?->id,
                'title' => $subscription->package?->title ?? $subscription->item_title,
                'amount' => $subscription->package ? (float) $subscription->package->amount : (float) $subscription->unit_amount,
            ],
            'donor' => [
                'id' => $subscription->donor_id,
                'name' => $subscription->donor_name,
                'email' => $subscription->donor_email,
                'phone' => $subscription->donor_phone,
                'date_of_birth' => $subscription->date_of_birth?->format('d M Y'),
                'pan_number' => $subscription->pan_number,
                'address' => $subscription->address,
                'pincode' => $subscription->pincode,
                'city' => $subscription->city,
                'state' => $subscription->state,
                'country' => $subscription->country,
                'consent_indian_citizen' => (bool) $subscription->consent_indian_citizen,
                'consent_recurring' => (bool) $subscription->consent_recurring,
                'profile_url' => $subscription->donor_id
                    ? route('admin.donors.show', $subscription->donor_id)
                    : null,
            ],
            'totals' => [
                'paid_cycles' => $paidOrders->count(),
                'collected_amount' => (float) $paidOrders->sum('total_amount'),
            ],
            'source' => app(DonationAttributionService::class)->detailPayload($subscription),
            'can_cancel' => $subscription->canBeCancelled(),
            'cancel_url' => route('admin.subscriptions.cancel', $subscription),
            'sync_url' => route('admin.subscriptions.sync', $subscription),
        ];
    }

    public static function subscriptionOrderRow(DonationOrder $order): array
    {
        $order->loadMissing('items');

        return [
            'uuid' => $order->order_uuid,
            'billing_cycle_number' => $order->billing_cycle_number,
            'total_amount' => (float) $order->total_amount,
            'status' => $order->status,
            'provider' => ucfirst((string) ($order->payment_provider ?: 'Unknown')),
            'provider_payment_id' => $order->provider_payment_id,
            'receipt_number' => $order->hasReceipt() ? $order->receiptNumberFormatted() : null,
            'paid_at' => $order->paid_at?->format('d M Y, h:i A') ?? $order->created_at?->format('d M Y, h:i A'),
            'created_at' => $order->created_at?->format('d M Y, h:i A'),
            'detail_url' => route('admin.donations.show', $order),
        ];
    }

    public static function qrCodeListRow(\App\Models\RazorpayQrCode $qr): array
    {
        $qr->loadMissing(['cause:id,title,slug', 'package:id,title,amount']);

        return [
            'uuid' => $qr->qr_uuid,
            'razorpay_qr_code_id' => $qr->razorpay_qr_code_id,
            'name' => $qr->name,
            'description' => $qr->description,
            'usage' => $qr->usage,
            'usage_label' => $qr->usageLabel(),
            'fixed_amount' => (bool) $qr->fixed_amount,
            'payment_amount' => $qr->paymentAmountRupees(),
            'status' => $qr->status,
            'status_label' => $qr->statusLabel(),
            'image_url' => $qr->image_url,
            'payments_count_received' => (int) $qr->payments_count_received,
            'payments_amount_received' => $qr->paymentsAmountReceivedRupees(),
            'cause_id' => $qr->cause_id,
            'cause_title' => $qr->cause?->title,
            'cause_package_id' => $qr->cause_package_id,
            'package_title' => $qr->package?->title,
            'created_at' => $qr->created_at?->format('d M Y, h:i A'),
            'created_at_ts' => $qr->created_at?->timestamp ?? 0,
        ];
    }

    public static function qrCodeDetail(\App\Models\RazorpayQrCode $qr): array
    {
        return [
            ...self::qrCodeListRow($qr),
            'type' => $qr->type,
            'close_reason' => $qr->close_reason,
            'closed_at' => $qr->closed_at?->format('d M Y, h:i A'),
            'razorpay_created_at' => $qr->razorpay_created_at?->format('d M Y, h:i A'),
            'can_close' => $qr->isActive(),
            'close_url' => route('admin.qr-codes.close', $qr),
            'sync_url' => route('admin.qr-codes.sync', $qr),
            'update_url' => route('admin.qr-codes.update', $qr),
            'donations_url' => route('admin.donations.index', [
                'duration' => 'all',
                'provider' => DonationOrder::PROVIDER_RAZORPAY_QR,
            ]),
        ];
    }

    public static function donationDetail(DonationOrder $order): array
    {
        $order->loadMissing('items.causeModel');

        $row = AdminInertiaData::offlineDonationRow($order);

        return [
            ...$row,
            'receipt_label' => $row['receipt_email_label'],
            'uuid' => $order->order_uuid,
            'is_offline' => $order->isManualAdminEntry(),
            'edit_url' => $order->isPaid()
                ? route('admin.donations.edit', $order)
                : null,
            'pan_number' => $order->pan_number,
            'address' => $order->address,
            'pincode' => $order->pincode,
            'city' => $order->city,
            'state' => $order->state,
            'country' => $order->country,
            'paid_at' => $order->paid_at?->format('d M Y, h:i A'),
            'receipt_number' => $order->hasReceipt() ? $order->receiptNumberFormatted() : null,
            'can_resend_receipt_email' => $order->isPaid() && filled($order->donor_email),
            'can_resend_sheet' => $order->isPaid(),
            'can_resend_thank_you_whatsapp' => self::canResendDonationWhatsApp($order),
            'can_resend_certificate_whatsapp' => self::canResendDonationWhatsApp($order),
            'can_resend_receipt_whatsapp' => self::canResendDonationWhatsApp($order),
            'can_resend_payment_link_whatsapp' => self::canResendPaymentLinkWhatsApp($order),
            'can_notify_payment_link_email' => self::canNotifyPaymentLinkEmail($order),
            'can_notify_payment_link_sms' => self::canNotifyPaymentLinkSms($order),
            'sheet_resend_url' => route('admin.donations.sheet.resend', $order),
            'whatsapp_thank_you_url' => route('admin.donations.whatsapp.thank-you', $order),
            'whatsapp_certificate_url' => route('admin.donations.whatsapp.certificate', $order),
            'whatsapp_receipt_url' => route('admin.donations.whatsapp.receipt', $order),
            'whatsapp_payment_link_url' => route('admin.donations.whatsapp.payment-link', $order),
            'payment_link_notify_email_url' => route('admin.donations.payment-link.notify', [$order, 'email']),
            'payment_link_notify_sms_url' => route('admin.donations.payment-link.notify', [$order, 'sms']),
            'certificate_url' => $order->isPaid()
                ? app(\App\Services\DonationCertificateService::class)->existingPublicUrl($order)
                : null,
            'certificate_show_url' => $order->isPaid()
                ? route('admin.donations.certificate.show', $order)
                : null,
            'certificate_regenerate_url' => $order->isPaid()
                ? route('admin.donations.certificate.regenerate', $order)
                : null,
            'can_regenerate_certificate' => $order->isPaid(),
            'payment_link_url' => $order->payment_link_url,
            'payment_link_id' => $order->payment_link_id,
            'is_failed' => $order->isFailed(),
            'is_pending' => $order->isPending(),
            'is_paid' => $order->isPaid(),
            'is_recurring' => (bool) ($row['is_recurring'] ?? false),
            'is_qr' => (bool) ($row['is_qr'] ?? false),
            'qr_code_id' => $row['qr_code_id'] ?? null,
            'qr_code_name' => $row['qr_code_name'] ?? null,
            'qr_code_url' => $row['qr_code_url'] ?? null,
            'billing_cycle_number' => $row['billing_cycle_number'] ?? null,
            'subscription' => $row['subscription'] ?? AdminInertiaData::donationSubscriptionSummary($order),
            'delivery' => self::donationDeliveryStatus($order),
            'source' => app(DonationAttributionService::class)->detailPayload($order),
            'items' => $order->items->map(function ($item) {
                $meta = is_array($item->meta) ? $item->meta : [];
                $dailyNeedsSummary = trim((string) ($meta['daily_needs_summary'] ?? ''));
                $title = $dailyNeedsSummary !== '' ? $dailyNeedsSummary : (string) $item->title;
                $isDailyNeeds = ($item->causeModel?->slug ?? ($meta['cause_slug'] ?? $item->cause)) === Cause::SLUG_DAILY_NEEDS
                    || $dailyNeedsSummary !== ''
                    || self::looksLikeDailyNeedsSource($title);
                $dailyNeedsLines = self::dailyNeedsLinesFromMeta($meta, $title);

                return [
                    'cause' => $item->causeModel?->title ?? $item->cause,
                    'cause_slug' => $item->causeModel?->slug ?? ($meta['cause_slug'] ?? $item->cause),
                    'title' => $title,
                    'campaign' => is_array($meta['campaign'] ?? null)
                        ? ($meta['campaign']['name'] ?? null)
                        : null,
                    'honoree_names' => \App\Support\TreeDedication::displayLines(
                        $meta['honoree_names'] ?? null
                    ),
                    'amount' => (float) $item->amount,
                    'quantity' => (int) $item->quantity,
                    'is_daily_needs' => $isDailyNeeds,
                    'daily_needs_lines' => $isDailyNeeds ? $dailyNeedsLines : [],
                ];
            })->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array{title: string, qty: int, unit: string, qty_label: string, unit_price: float, amount: float}>
     */
    public static function dailyNeedsLinesFromMeta(array $meta, string $fallbackTitle = ''): array
    {
        $stored = $meta['daily_needs_lines'] ?? null;
        if (is_array($stored) && $stored !== []) {
            return collect($stored)
                ->filter(fn ($line) => is_array($line) && filled($line['title'] ?? null))
                ->map(fn (array $line) => [
                    'title' => (string) $line['title'],
                    'qty' => max(1, (int) ($line['qty'] ?? 1)),
                    'unit' => (string) ($line['unit'] ?? ''),
                    'qty_label' => (string) ($line['qty_label'] ?? ($line['qty'] ?? '1')),
                    'unit_price' => (float) ($line['unit_price'] ?? 0),
                    'amount' => (float) ($line['amount'] ?? 0),
                ])
                ->values()
                ->all();
        }

        $source = trim((string) ($meta['daily_needs_summary'] ?? $fallbackTitle));

        return self::parseDailyNeedsSource($source);
    }

    public static function looksLikeDailyNeedsSource(string $source): bool
    {
        $source = trim($source);
        if ($source === '') {
            return false;
        }

        if (str_contains($source, '= ₹') || str_contains($source, '₹')) {
            return true;
        }

        if (preg_match('/\brs\.?\s*[\d]/i', $source)) {
            return true;
        }

        return (bool) preg_match(
            '/\d+\s*(kg|kgs|liter|litre|ltr|piece|pieces|pcs)\b/i',
            $source
        );
    }

    /**
     * Parse concatenated Daily Needs titles such as:
     * "12kg Chickpeas = ₹ 1,236, 6liter Cooking Oil Tin = ₹ 12,600"
     * "1kg Moth Beans rs 152, 2kg Rice rs 90, 3 Diaper (1 piece) rs 51 -- total 293"
     *
     * @return list<array{title: string, qty: int, unit: string, qty_label: string, unit_price: float, amount: float}>
     */
    public static function parseDailyNeedsSource(string $source): array
    {
        $source = trim(preg_replace('/[\r\n]+/', ' ', $source) ?? $source);
        $source = trim(preg_replace('/\s+/', ' ', $source) ?? $source);
        if ($source === '') {
            return [];
        }

        $source = trim((string) preg_replace('/\s*(?:—|--|-|–)\s*total\b.*$/iu', '', $source));
        $source = trim($source, " \t,;");
        if ($source === '' || ! self::looksLikeDailyNeedsSource($source)) {
            return [];
        }

        $segments = preg_split(
            '/\s*,\s*(?=\d+\s*(?:kg|kgs|liter|litre|liters|litres|ltr|ltrs|piece|pieces|pcs|pc)?\s*[A-Za-z])/iu',
            $source
        ) ?: [$source];
        $lines = [];

        foreach ($segments as $segment) {
            $parsed = self::parseDailyNeedsSegment(trim((string) $segment));
            if ($parsed !== null) {
                $lines[] = $parsed;
            }
        }

        return $lines;
    }

    /**
     * @return array{title: string, qty: int, unit: string, qty_label: string, unit_price: float, amount: float}|null
     */
    private static function parseDailyNeedsSegment(string $segment): ?array
    {
        if ($segment === '') {
            return null;
        }

        if (! preg_match(
            '/^(\d+)\s*(kg|kgs|kilogram|kilograms|liter|litre|liters|litres|ltr|ltrs|piece|pieces|pcs|pc)?\s+(.+?)(?:\s*=?\s*(?:₹|rs\.?|inr)\s*([\d,]+(?:\.\d+)?))?\s*$/iu',
            $segment,
            $match
        )) {
            return null;
        }

        $qty = max(1, (int) $match[1]);
        $title = trim((string) $match[3]);
        $amount = isset($match[4]) && $match[4] !== ''
            ? (float) str_replace(',', '', $match[4])
            : 0.0;
        $unit = self::normalizeDailyNeedUnit(strtolower(trim((string) ($match[2] ?? ''))), $title);

        if ($title === '') {
            return null;
        }

        return [
            'title' => $title,
            'qty' => $qty,
            'unit' => $unit,
            'qty_label' => $unit !== '' ? $qty.$unit : (string) $qty,
            'unit_price' => $qty > 0 && $amount > 0 ? round($amount / $qty, 2) : 0.0,
            'amount' => $amount,
        ];
    }

    private static function normalizeDailyNeedUnit(string $unit, string $title): string
    {
        $unit = match ($unit) {
            'kgs', 'kilogram', 'kilograms' => 'kg',
            'litre', 'liters', 'litres', 'ltr', 'ltrs' => 'liter',
            'pieces', 'pcs', 'pc' => 'piece',
            default => $unit,
        };

        if ($unit !== '') {
            return $unit;
        }

        $normalizedTitle = strtolower($title);
        if (preg_match('/\b(oil|liter|litre|ltrs?)\b/', $normalizedTitle)) {
            return 'liter';
        }
        if (preg_match('/\b(tin|piece|pieces|pcs|diaper|ghee)\b/', $normalizedTitle)) {
            return 'piece';
        }

        return 'kg';
    }

    /**
     * @return array{
     *     email: array{status: string, label: string, at: ?string, error: ?string},
     *     whatsapp: array{status: string, label: string, at: ?string},
     *     certificate_whatsapp: array{status: string, label: string, at: ?string},
     *     receipt_whatsapp: array{status: string, label: string, at: ?string},
     *     payment_link_whatsapp: array{status: string, label: string, at: ?string, url: ?string},
     *     payment_link_email: array{status: string, label: string, at: ?string},
     *     payment_link_sms: array{status: string, label: string, at: ?string},
     *     sheet: array{status: string, label: string, at: ?string},
     *     follow_up_sheet: array{status: string, label: string, at: ?string}
     * }
     */
    public static function donationDeliveryStatus(DonationOrder $order): array
    {
        $emailStatus = 'not_sent';
        $emailLabel = 'Not sent';

        if ($order->receipt_sent_at) {
            $emailStatus = 'sent';
            $emailLabel = 'Sent';
        } elseif ($order->receipt_failed_at) {
            $emailStatus = 'failed';
            $emailLabel = 'Failed';
        }

        $paymentLinkStatus = 'not_applicable';
        $paymentLinkLabel = '—';

        if ($order->isFailed() || $order->isPending() || filled($order->payment_link_url) || filled($order->payment_link_sent_at)) {
            if ($order->payment_link_sent_at) {
                $paymentLinkStatus = 'sent';
                $paymentLinkLabel = 'Sent';
            } elseif (filled($order->payment_link_url)) {
                $paymentLinkStatus = 'not_sent';
                $paymentLinkLabel = 'Link ready · not sent';
            } else {
                $paymentLinkStatus = 'not_sent';
                $paymentLinkLabel = $order->isPending() ? 'Pending · send link' : 'Not created';
            }
        }

        $paymentLinkEmailStatus = 'not_applicable';
        $paymentLinkEmailLabel = '—';
        $paymentLinkSmsStatus = 'not_applicable';
        $paymentLinkSmsLabel = '—';

        if ($order->isFailed() || filled($order->payment_link_url) || filled($order->payment_link_email_sent_at) || filled($order->payment_link_sms_sent_at)) {
            if ($order->payment_link_email_sent_at) {
                $paymentLinkEmailStatus = 'sent';
                $paymentLinkEmailLabel = 'Sent';
            } elseif (filled($order->payment_link_url)) {
                $paymentLinkEmailStatus = 'not_sent';
                $paymentLinkEmailLabel = 'Not sent';
            } else {
                $paymentLinkEmailStatus = 'not_sent';
                $paymentLinkEmailLabel = 'Not created';
            }

            if ($order->payment_link_sms_sent_at) {
                $paymentLinkSmsStatus = 'sent';
                $paymentLinkSmsLabel = 'Sent';
            } elseif (filled($order->payment_link_url)) {
                $paymentLinkSmsStatus = 'not_sent';
                $paymentLinkSmsLabel = 'Not sent';
            } else {
                $paymentLinkSmsStatus = 'not_sent';
                $paymentLinkSmsLabel = 'Not created';
            }
        }

        return [
            'email' => [
                'status' => $emailStatus,
                'label' => $emailLabel,
                'at' => $order->receipt_sent_at?->format('d M Y, h:i A')
                    ?? $order->receipt_failed_at?->format('d M Y, h:i A'),
                'error' => $order->receipt_failed_at ? ($order->receipt_last_error ?: null) : null,
            ],
            'whatsapp' => [
                'status' => $order->whatsapp_sent_at ? 'sent' : 'not_sent',
                'label' => $order->whatsapp_sent_at ? 'Sent' : 'Not sent',
                'at' => $order->whatsapp_sent_at?->format('d M Y, h:i A'),
            ],
            'certificate_whatsapp' => [
                'status' => $order->certificate_whatsapp_sent_at ? 'sent' : 'not_sent',
                'label' => $order->certificate_whatsapp_sent_at ? 'Sent' : 'Not sent',
                'at' => $order->certificate_whatsapp_sent_at?->format('d M Y, h:i A'),
            ],
            'receipt_whatsapp' => [
                'status' => $order->receipt_whatsapp_sent_at ? 'sent' : 'not_sent',
                'label' => $order->receipt_whatsapp_sent_at ? 'Sent' : 'Not sent',
                'at' => $order->receipt_whatsapp_sent_at?->format('d M Y, h:i A'),
            ],
            'payment_link_whatsapp' => [
                'status' => $paymentLinkStatus,
                'label' => $paymentLinkLabel,
                'at' => $order->payment_link_sent_at?->format('d M Y, h:i A'),
                'url' => $order->payment_link_url,
            ],
            'payment_link_email' => [
                'status' => $paymentLinkEmailStatus,
                'label' => $paymentLinkEmailLabel,
                'at' => $order->payment_link_email_sent_at?->format('d M Y, h:i A'),
            ],
            'payment_link_sms' => [
                'status' => $paymentLinkSmsStatus,
                'label' => $paymentLinkSmsLabel,
                'at' => $order->payment_link_sms_sent_at?->format('d M Y, h:i A'),
            ],
            'sheet' => [
                'status' => $order->sheet_logged_at ? 'logged' : 'not_logged',
                'label' => $order->sheet_logged_at ? 'Logged' : 'Not logged',
                'at' => $order->sheet_logged_at?->format('d M Y, h:i A'),
            ],
            'follow_up_sheet' => self::followUpSheetStatus($order),
        ];
    }

    /**
     * @return array{status: string, label: string, at: ?string}
     */
    private static function followUpSheetStatus(DonationOrder $order): array
    {
        if ($order->failed_sheet_logged_at) {
            return [
                'status' => 'logged',
                'label' => 'Logged',
                'at' => $order->failed_sheet_logged_at->format('d M Y, h:i A'),
            ];
        }

        if ($order->isFailed()) {
            return [
                'status' => 'not_logged',
                'label' => 'Not logged',
                'at' => null,
            ];
        }

        return [
            'status' => 'not_applicable',
            'label' => '—',
            'at' => null,
        ];
    }

    private static function canResendDonationWhatsApp(DonationOrder $order): bool
    {
        if (! $order->isPaid()) {
            return false;
        }

        return app(DonationWhatsAppPolicy::class)->hasSendablePhoneNumber($order->donor_phone);
    }

    private static function canResendPaymentLinkWhatsApp(DonationOrder $order): bool
    {
        if (! $order->isFailed() && ! $order->isPending()) {
            return false;
        }

        return app(DonationWhatsAppPolicy::class)->hasSendablePhoneNumber($order->donor_phone);
    }

    private static function canNotifyPaymentLinkEmail(DonationOrder $order): bool
    {
        if (! $order->isFailed()) {
            return false;
        }

        return app(RazorpayPaymentLinkService::class)->hasSendableEmail($order->donor_email);
    }

    private static function canNotifyPaymentLinkSms(DonationOrder $order): bool
    {
        if (! $order->isFailed()) {
            return false;
        }

        return app(DonationWhatsAppPolicy::class)->hasSendablePhoneNumber($order->donor_phone);
    }

    public static function donationEditForm(DonationOrder $order): array
    {
        $order->loadMissing('items');

        $item = $order->items->first();

        return [
            'uuid' => $order->order_uuid,
            'receipt_number' => $order->hasReceipt() ? $order->receiptNumberFormatted() : null,
            'is_offline' => $order->isManualAdminEntry(),
            'can_edit_donor_details' => $order->allowsAdminDonorEdit(),
            'can_edit_amount' => $order->allowsAdminAmountEdit(),
            'provider' => $order->payment_provider,
            'item_count' => $order->items->count(),
            'donor_name' => $order->donor_name,
            'donor_email' => $order->donor_email,
            'donor_phone' => $order->donor_phone,
            'pan_number' => $order->pan_number,
            'address' => $order->address,
            'pincode' => $order->pincode,
            'city' => $order->city,
            'state' => $order->state,
            'country' => $order->country ?: 'INDIA',
            'donor_country_code' => $order->donor_country_code ?: 'IN',
            'date_of_birth' => $order->date_of_birth?->format('Y-m-d') ?? '',
            'donation_date' => ($order->paid_at ?? $order->created_at)?->format('Y-m-d') ?? '',
            'total_amount' => (float) $order->total_amount,
            'cause_id' => $item?->cause_id ?? '',
            'cause_package_id' => $item?->cause_package_id ?? '',
            'item_title' => $item?->title ?? '',
            'quantity' => max(1, (int) ($item?->quantity ?? 1)),
        ];
    }

    public static function offlineDonationEditForm(DonationOrder $order): array
    {
        return self::donationEditForm($order);
    }

    public static function donationCampaign(DonationCampaign $campaign): array
    {
        $campaign->loadMissing(['cause:id,title,slug', 'package:id,cause_id,title,amount']);

        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'slug' => $campaign->slug,
            'cause_id' => $campaign->cause_id,
            'cause_package_id' => $campaign->cause_package_id,
            'amount' => $campaign->amount !== null ? (float) $campaign->amount : null,
            'goal_amount' => $campaign->goal_amount !== null ? (float) $campaign->goal_amount : null,
            'title' => $campaign->title,
            'headline' => $campaign->headline,
            'subheadline' => $campaign->subheadline,
            'image' => $campaign->image,
            'recurring_only' => (bool) $campaign->recurring_only,
            'frequency' => $campaign->billingFrequency(),
            'frequency_label' => $campaign->frequencyLabel(),
            'is_active' => (bool) $campaign->is_active,
            'starts_at' => $campaign->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => $campaign->ends_at?->format('Y-m-d\TH:i'),
            'public_url' => $campaign->publicUrl(),
            'share_url' => StaffReferral::trackedShareUrl(
                Seo::canonicalUrl($campaign->publicUrl()),
                auth()->user()?->referral_code
            ),
            'stats_url' => route('admin.campaigns.show', $campaign),
            'cause' => $campaign->cause ? [
                'id' => $campaign->cause->id,
                'title' => $campaign->cause->title,
                'slug' => $campaign->cause->slug,
            ] : null,
            'package' => $campaign->package ? [
                'id' => $campaign->package->id,
                'title' => $campaign->package->title,
                'amount' => (float) $campaign->package->amount,
            ] : null,
        ];
    }

    public static function donationCampaignListRow(DonationCampaign $campaign, ?array $metrics = null, ?User $viewer = null): array
    {
        $viewer ??= auth()->user();
        $campaign->loadMissing('cause:id,title,slug');
        $canCopyLinks = AdminPermissions::userCan($viewer, AdminPermissions::CAMPAIGN_COPY_LINKS);
        $canView = AdminPermissions::userCan($viewer, AdminPermissions::CAMPAIGN_VIEW);

        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'slug' => $campaign->slug,
            'cause_title' => $campaign->cause?->title,
            'amount' => $campaign->resolvedAmount(),
            'frequency' => $campaign->billingFrequency(),
            'frequency_label' => $campaign->recurring_only ? $campaign->frequencyLabel() : 'One-time',
            'recurring_only' => (bool) $campaign->recurring_only,
            'goal_amount' => $campaign->goal_amount !== null ? (float) $campaign->goal_amount : null,
            'goal_progress_percent' => isset($metrics['goal_progress_percent'])
                ? $metrics['goal_progress_percent']
                : null,
            'is_active' => (bool) $campaign->is_active,
            'is_ready_for_checkout' => $campaign->isReadyForCheckout(),
            'public_url' => $campaign->publicUrl(),
            'share_url' => $canCopyLinks
                ? StaffReferral::trackedShareUrl(
                    Seo::canonicalUrl($campaign->publicUrl()),
                    $viewer?->referral_code
                )
                : null,
            'edit_url' => route('admin.campaigns.edit', $campaign),
            'stats_url' => route('admin.campaigns.show', $campaign),
            'can_edit' => AdminPermissions::userCan($viewer, AdminPermissions::CAMPAIGN_EDIT),
            'can_delete' => AdminPermissions::userCan($viewer, AdminPermissions::CAMPAIGN_DELETE),
            'can_copy_links' => $canCopyLinks,
            'can_view_stats' => $canView,
            'stats' => $metrics ?? [
                'revenue' => 0,
                'paid_count' => 0,
                'active_subscriptions' => 0,
                'page_views' => 0,
                'goal_amount' => $campaign->goal_amount !== null ? (float) $campaign->goal_amount : null,
                'goal_progress_percent' => null,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function causeOptionsForCampaigns(): array
    {
        return Cause::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->with(['packages' => function ($query): void {
                $query->where('is_active', true)->orderBy('sort_order');
            }])
            ->get()
            ->map(fn (Cause $cause): array => [
                'id' => $cause->id,
                'title' => $cause->title,
                'slug' => $cause->slug,
                'hero_image' => $cause->hero_image,
                'allow_recurring' => (bool) $cause->allow_recurring,
                'allow_weekly_recurring' => (bool) $cause->allow_weekly_recurring,
                'is_active' => (bool) $cause->is_active,
                'packages' => $cause->packages->map(fn (CausePackage $package): array => [
                    'id' => $package->id,
                    'title' => $package->title,
                    'amount' => (float) $package->amount,
                    'allow_recurring' => (bool) $package->allow_recurring,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    public static function paginatedUsers(LengthAwarePaginator $paginator, ?User $actor = null): array
    {
        $actor ??= auth()->user();

        return self::paginated($paginator, fn (User $user) => self::userListRow($user, $actor));
    }

    public static function paginatedDepartments(LengthAwarePaginator $paginator): array
    {
        return self::paginated($paginator, fn (Department $department) => self::department($department));
    }

    public static function paginatedRoles(LengthAwarePaginator $paginator): array
    {
        return self::paginated($paginator, fn (Role $role) => self::role($role));
    }
}
