<?php

use App\Jobs\ProcessWhatsappCampaignRunJob;
use App\Jobs\SendWhatsappCampaignRecipientJob;
use App\Models\AisensyAccount;
use App\Models\AisensyWaTemplate;
use App\Models\Donor;
use App\Models\User;
use App\Models\WhatsappCampaignRecipient;
use App\Models\WhatsappCampaignRun;
use App\Services\AiSensy\AiSensyProjectClient;
use App\Services\WhatsappCampaignLauncher;
use App\Support\DonorAudienceQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createWhatsappCampaignAdmin(array $permissions = ['manage whatsapp campaigns']): User
{
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function createCampaignFixtures(): array
{
    $account = AisensyAccount::create([
        'name' => 'Main',
        'api_key' => 'campaign-api-key',
        'project_api_password' => 'project-pwd',
        'project_id' => 'proj_123',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $template = AisensyWaTemplate::create([
        'aisensy_account_id' => $account->id,
        'external_id' => 'tpl-1',
        'name' => 'festival_offer',
        'live_campaign_name' => 'festival_offer',
        'status' => 'APPROVED',
        'param_count' => 1,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $donor = Donor::create([
        'name' => 'Eligible Donor',
        'email' => 'eligible@example.com',
        'phone' => '9876543210',
        'city' => 'Ahmedabad',
        'state' => 'Gujarat',
        'whatsapp_opt_out' => false,
    ]);

    Donor::create([
        'name' => 'Opted Out',
        'email' => 'optout@example.com',
        'phone' => '9123456780',
        'city' => 'Ahmedabad',
        'whatsapp_opt_out' => true,
    ]);

    Donor::create([
        'name' => 'Invalid Phone',
        'email' => 'badphone@example.com',
        'phone' => 'upi-123',
        'city' => 'Ahmedabad',
        'whatsapp_opt_out' => false,
    ]);

    return compact('account', 'template', 'donor');
}

it('syncs approved templates from the project api', function () {
    Http::fake([
        'apis.aisensy.com/project-apis/v1/project/proj_123/wa_template_messages' => Http::response([
            'data' => [
                [
                    'id' => 'abc',
                    'name' => 'Thank You Fest',
                    'status' => 'APPROVED',
                    'language' => 'en',
                    'components' => [
                        ['type' => 'BODY', 'text' => 'Hello {{1}}'],
                    ],
                ],
                [
                    'id' => 'pending',
                    'name' => 'Pending Tpl',
                    'status' => 'PENDING',
                ],
            ],
        ], 200),
    ]);

    $account = AisensyAccount::create([
        'name' => 'Main',
        'api_key' => 'key',
        'project_api_password' => 'pwd',
        'project_id' => 'proj_123',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $synced = app(AiSensyProjectClient::class)->syncApprovedTemplates($account);

    expect($synced)->toBe(1)
        ->and(AisensyWaTemplate::query()->where('name', 'Thank You Fest')->exists())->toBeTrue()
        ->and(AisensyWaTemplate::query()->where('name', 'Pending Tpl')->exists())->toBeFalse();
});

it('syncs approved templates from existing campaigns when direct list 404s', function () {
    Http::fake(function (Request $request) {
        $url = $request->url();

        if (str_contains($url, '/wa_template') || str_ends_with($url, '/templates')) {
            return Http::response('<!DOCTYPE html><pre>Cannot GET</pre>', 404);
        }

        if (str_contains($url, '/campaign/api') && $request->method() === 'GET') {
            return Http::response([
                'campaign' => [
                    [
                        'name' => 'birtday_message_final',
                        'message_payload' => [
                            'template' => [
                                'id' => 'tpl_birthday',
                                'name' => 'birthday_template_with_link_final',
                                'status' => 'APPROVED',
                                'type' => 'IMAGE',
                                'text' => 'Dear {{1}}',
                                'total_parameters' => 1,
                            ],
                        ],
                    ],
                ],
            ], 200);
        }

        if (str_contains($url, '/campaigns')) {
            return Http::response(['campaigns' => []], 200);
        }

        return Http::response(['ok' => true], 200);
    });

    $account = AisensyAccount::create([
        'name' => 'Main',
        'api_key' => 'key',
        'project_api_password' => 'pwd',
        'project_id' => 'proj_123',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $synced = app(AiSensyProjectClient::class)->syncApprovedTemplates($account);

    expect($synced)->toBe(1)
        ->and(AisensyWaTemplate::query()->where('name', 'birthday_template_with_link_final')->exists())->toBeTrue()
        ->and(AisensyWaTemplate::query()->where('name', 'birthday_template_with_link_final')->value('header_type'))->toBe('IMAGE');
});

it('excludes opted-out donors from campaign eligibility', function () {
    createCampaignFixtures();

    $ids = DonorAudienceQuery::campaignEligible(['city' => 'Ahmedabad'])->pluck('id');
    $donors = Donor::query()->whereIn('id', $ids)->get();

    expect($donors)->toHaveCount(2)
        ->and($donors->every(fn (Donor $donor) => ! $donor->whatsapp_opt_out))->toBeTrue();

    $sendable = $donors->filter(fn (Donor $donor) => DonorAudienceQuery::isSendableDonor($donor));

    expect($sendable)->toHaveCount(1)
        ->and($sendable->first()->name)->toBe('Eligible Donor');
});

it('creates a unique AiSensy campaign then queues recipient processing', function () {
    Queue::fake();

    Http::fake(function (Request $request) {
        $url = $request->url();

        if (str_contains($url, '/campaigns') && $request->method() === 'POST') {
            return Http::response(['campaigns' => []], 200);
        }

        if (str_ends_with($url, '/campaign/api') && $request->method() === 'GET') {
            return Http::response(['campaign' => []], 200);
        }

        if (str_ends_with($url, '/campaign/api') && $request->method() === 'POST') {
            return Http::response(['id' => 'camp_1', 'name' => $request['campaign_name'] ?? 'created'], 200);
        }

        return Http::response(['ok' => true], 200);
    });

    ['account' => $account, 'template' => $template, 'donor' => $donor] = createCampaignFixtures();
    $user = createWhatsappCampaignAdmin();

    $run = app(WhatsappCampaignLauncher::class)->launch(
        user: $user,
        account: $account,
        name: 'Fest blast',
        template: $template,
        filters: ['city' => 'Ahmedabad'],
        paramMap: ['donor.name'],
    );

    expect($run->audience_count)->toBe(1)
        ->and($run->status)->toBe(WhatsappCampaignRun::STATUS_QUEUED)
        ->and($run->live_campaign_name)->not->toBe('')
        ->and(WhatsappCampaignRecipient::query()->where('donor_id', $donor->id)->exists())->toBeTrue();

    Queue::assertPushed(ProcessWhatsappCampaignRunJob::class);

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'POST'
            && str_ends_with($request->url(), '/project/proj_123/campaign/api')
            && ($request['template_name'] ?? null) === 'festival_offer';
    });
});

it('requires media upload for image header templates', function () {
    ['account' => $account, 'template' => $template] = createCampaignFixtures();
    $template->forceFill(['header_type' => AisensyWaTemplate::HEADER_IMAGE])->save();
    $user = createWhatsappCampaignAdmin();

    expect(fn () => app(WhatsappCampaignLauncher::class)->launch(
        user: $user,
        account: $account,
        name: 'Needs media',
        template: $template->fresh(),
        filters: ['city' => 'Ahmedabad'],
        paramMap: ['donor.name'],
        dryRun: true,
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('requires latitude and longitude for location templates', function () {
    ['account' => $account, 'template' => $template] = createCampaignFixtures();
    $template->forceFill(['header_type' => AisensyWaTemplate::HEADER_LOCATION])->save();
    $user = createWhatsappCampaignAdmin();

    expect(fn () => app(WhatsappCampaignLauncher::class)->launch(
        user: $user,
        account: $account,
        name: 'Needs location',
        template: $template->fresh(),
        filters: ['city' => 'Ahmedabad'],
        paramMap: ['donor.name'],
        dryRun: true,
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('normalizes all AiSensy template header types', function () {
    expect(AisensyWaTemplate::headerTypes())->toBe([
        'TEXT',
        'IMAGE',
        'VIDEO',
        'DOCUMENT',
        'LOCATION',
        'CAROUSEL',
        'LIMITED TIME OFFER',
    ]);

    $template = new AisensyWaTemplate(['header_type' => 'LIMITED_TIME_OFFER']);
    expect($template->normalizedHeaderType())->toBe('LIMITED TIME OFFER')
        ->and($template->requiresMediaHeader())->toBeTrue();
});

it('stores media on the run and sends it with each recipient', function () {
    Http::fake([
        'apis.aisensy.com/project-apis/v1/project/proj_123/campaign/api/send' => Http::response(['success' => true], 200),
    ]);

    ['account' => $account, 'template' => $template, 'donor' => $donor] = createCampaignFixtures();
    $template->forceFill(['header_type' => AisensyWaTemplate::HEADER_IMAGE])->save();
    $user = createWhatsappCampaignAdmin();

    $run = WhatsappCampaignRun::create([
        'aisensy_account_id' => $account->id,
        'aisensy_wa_template_id' => $template->id,
        'name' => 'Fest',
        'live_campaign_name' => 'fest-blast-live',
        'status' => WhatsappCampaignRun::STATUS_RUNNING,
        'param_map_json' => ['donor.name'],
        'media_path' => 'whatsapp-campaigns/media/birthday.jpg',
        'media_filename' => 'birthday.jpg',
        'audience_count' => 1,
        'created_by' => $user->id,
        'started_at' => now(),
    ]);

    $recipient = WhatsappCampaignRecipient::create([
        'whatsapp_campaign_run_id' => $run->id,
        'donor_id' => $donor->id,
        'phone' => $donor->phone,
        'status' => WhatsappCampaignRecipient::STATUS_PENDING,
    ]);

    (new SendWhatsappCampaignRecipientJob($recipient->id))->handle(app(AiSensyProjectClient::class));

    Http::assertSent(function (Request $request): bool {
        $data = $request->data();

        return str_ends_with($request->url(), '/project/proj_123/campaign/api/send')
            && ($data['media']['filename'] ?? null) === 'birthday.jpg'
            && filled($data['media']['url'] ?? null);
    });
});

it('sends a recipient via Project API send endpoint', function () {
    Http::fake([
        'apis.aisensy.com/project-apis/v1/project/proj_123/campaign/api/send' => Http::response(['success' => true], 200),
    ]);

    ['account' => $account, 'template' => $template, 'donor' => $donor] = createCampaignFixtures();
    $user = createWhatsappCampaignAdmin();

    $run = WhatsappCampaignRun::create([
        'aisensy_account_id' => $account->id,
        'aisensy_wa_template_id' => $template->id,
        'name' => 'Fest',
        'live_campaign_name' => 'fest-blast-live',
        'status' => WhatsappCampaignRun::STATUS_RUNNING,
        'param_map_json' => ['donor.name'],
        'audience_count' => 1,
        'created_by' => $user->id,
        'started_at' => now(),
    ]);

    $recipient = WhatsappCampaignRecipient::create([
        'whatsapp_campaign_run_id' => $run->id,
        'donor_id' => $donor->id,
        'phone' => $donor->phone,
        'status' => WhatsappCampaignRecipient::STATUS_PENDING,
    ]);

    (new SendWhatsappCampaignRecipientJob($recipient->id))->handle(app(AiSensyProjectClient::class));

    expect($recipient->fresh()->status)->toBe(WhatsappCampaignRecipient::STATUS_SENT)
        ->and($run->fresh()->sent_count)->toBe(1)
        ->and($run->fresh()->status)->toBe(WhatsappCampaignRun::STATUS_COMPLETED);

    Http::assertSent(function (Request $request): bool {
        $data = $request->data();

        return str_ends_with($request->url(), '/project/proj_123/campaign/api/send')
            && ($data['campaign_name'] ?? null) === 'fest-blast-live'
            && ($data['phone_number'] ?? null) === '919876543210'
            && ($data['name'] ?? null) === 'Eligible Donor';
    });
});

it('stops sending when a campaign is cancelled', function () {
    Http::fake();

    ['account' => $account, 'template' => $template, 'donor' => $donor] = createCampaignFixtures();
    $user = createWhatsappCampaignAdmin();

    $run = WhatsappCampaignRun::create([
        'aisensy_account_id' => $account->id,
        'aisensy_wa_template_id' => $template->id,
        'name' => 'Fest',
        'live_campaign_name' => 'fest-blast-live',
        'status' => WhatsappCampaignRun::STATUS_CANCELLED,
        'param_map_json' => ['donor.name'],
        'audience_count' => 1,
        'created_by' => $user->id,
        'finished_at' => now(),
    ]);

    $recipient = WhatsappCampaignRecipient::create([
        'whatsapp_campaign_run_id' => $run->id,
        'donor_id' => $donor->id,
        'phone' => $donor->phone,
        'status' => WhatsappCampaignRecipient::STATUS_PENDING,
    ]);

    (new SendWhatsappCampaignRecipientJob($recipient->id))->handle(app(AiSensyProjectClient::class));

    expect($recipient->fresh()->status)->toBe(WhatsappCampaignRecipient::STATUS_PENDING);
    Http::assertNothingSent();
});

it('allows authorized users to open campaign pages', function () {
    $user = createWhatsappCampaignAdmin();
    ['account' => $account] = createCampaignFixtures();

    actingAs($user)
        ->get(route('admin.whatsapp-campaigns.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/WhatsappCampaigns/Index'));

    actingAs($user)
        ->get(route('admin.whatsapp-campaigns.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/WhatsappCampaigns/Create')
            ->has('accounts')
            ->has('templates'));

    $run = WhatsappCampaignRun::create([
        'aisensy_account_id' => $account->id,
        'name' => 'Show me',
        'live_campaign_name' => 'show-me',
        'status' => WhatsappCampaignRun::STATUS_COMPLETED,
        'audience_count' => 0,
        'created_by' => $user->id,
    ]);

    actingAs($user)
        ->get(route('admin.whatsapp-campaigns.show', $run))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/WhatsappCampaigns/Show'));
});

it('blocks users without manage whatsapp campaigns permission', function () {
    $user = createWhatsappCampaignAdmin(['view donations']);

    actingAs($user)
        ->get(route('admin.whatsapp-campaigns.index'))
        ->assertForbidden();
});

it('syncs AiSensy delivery failed counts onto the campaign run', function () {
    Http::fake(function (Request $request) {
        $url = $request->url();

        if (str_contains($url, '/campaigns') && $request->method() === 'POST') {
            return Http::response([
                'campaigns' => [
                    [
                        'id' => 'camp_analytics_1',
                        'name' => 'test-campaign-20260729172724',
                        'sent' => 3,
                        'delivered' => 3,
                        'read' => 3,
                        'failed' => 3,
                    ],
                ],
            ], 200);
        }

        return Http::response(['ok' => true], 200);
    });

    $user = createWhatsappCampaignAdmin();
    ['account' => $account] = createCampaignFixtures();

    $run = WhatsappCampaignRun::create([
        'aisensy_account_id' => $account->id,
        'name' => 'Test Campaign',
        'live_campaign_name' => 'test-campaign-20260729172724',
        'status' => WhatsappCampaignRun::STATUS_COMPLETED,
        'audience_count' => 6,
        'sent_count' => 6,
        'failed_count' => 0,
        'created_by' => $user->id,
    ]);

    actingAs($user)
        ->post(route('admin.whatsapp-campaigns.sync-delivery', $run))
        ->assertRedirect();

    $run->refresh();

    expect($run->delivery_sent_count)->toBe(3)
        ->and($run->delivery_delivered_count)->toBe(3)
        ->and($run->delivery_read_count)->toBe(3)
        ->and($run->delivery_failed_count)->toBe(3)
        ->and($run->delivery_synced_at)->not->toBeNull()
        ->and($run->failed_count)->toBe(0);
});
