<?php

use App\Models\AisensyAccount;
use App\Services\AiSensy\AiSensyProjectClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('returns an empty list when project api password is missing', function () {
    $account = AisensyAccount::create([
        'name' => 'No Project',
        'api_key' => 'key',
        'project_id' => 'proj_1',
        'country_code' => '91',
        'is_active' => true,
    ]);

    expect(app(AiSensyProjectClient::class)->listWaTemplates($account))->toBe([]);
});

it('returns an empty list when project id is missing', function () {
    $account = AisensyAccount::create([
        'name' => 'No Project Id',
        'api_key' => 'key',
        'project_api_password' => 'pwd',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $client = app(AiSensyProjectClient::class);

    expect($client->listWaTemplates($account))->toBe([]);
    expect($client->lastError())->toContain('Project ID');
});

it('normalizes template payloads from project api', function () {
    Http::fake([
        'apis.aisensy.com/project-apis/v1/project/proj_1/wa_template_messages' => Http::response([
            'templates' => [
                [
                    'id' => '1',
                    'name' => 'Hello',
                    'status' => 'APPROVED',
                    'components' => [
                        ['type' => 'BODY', 'text' => 'Hi {{1}}, welcome {{2}}'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $account = AisensyAccount::create([
        'name' => 'Main',
        'api_key' => 'key',
        'project_api_password' => 'pwd',
        'project_id' => 'proj_1',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $list = app(AiSensyProjectClient::class)->listWaTemplates($account);

    expect($list)->toHaveCount(1)
        ->and($list[0]['name'])->toBe('Hello')
        ->and($list[0]['param_count'])->toBe(2);
});

it('falls back to templates embedded in existing campaigns when direct list is unavailable', function () {
    Http::fake([
        'apis.aisensy.com/project-apis/v1/project/proj_1/wa_template_messages' => Http::response(
            '<!DOCTYPE html><html><body><pre>Cannot GET /wa_template_messages</pre></body></html>',
            404,
        ),
        'apis.aisensy.com/project-apis/v1/project/proj_1/wa-template-messages' => Http::response(
            '<!DOCTYPE html><html><body><pre>Cannot GET</pre></body></html>',
            404,
        ),
        'apis.aisensy.com/project-apis/v1/project/proj_1/wa_template_message' => Http::response(
            '<!DOCTYPE html><html><body><pre>Cannot GET</pre></body></html>',
            404,
        ),
        'apis.aisensy.com/project-apis/v1/project/proj_1/templates' => Http::response(
            '<!DOCTYPE html><html><body><pre>Cannot GET</pre></body></html>',
            404,
        ),
        'apis.aisensy.com/project-apis/v1/project/proj_1/campaign/api' => Http::response([
            'campaign' => [
                [
                    'id' => 'camp_1',
                    'name' => 'birtday_message_final',
                    'type' => 'API',
                    'message_payload' => [
                        'template' => [
                            'id' => 'tpl_1',
                            'name' => 'birthday_template_with_link_final',
                            'status' => 'APPROVED',
                            'language' => 'English',
                            'category' => 'MARKETING',
                            'type' => 'IMAGE',
                            'text' => 'Dear *{{1}}*, happy birthday',
                            'total_parameters' => 1,
                        ],
                    ],
                ],
            ],
        ], 200),
        'apis.aisensy.com/project-apis/v1/project/proj_1/campaigns' => Http::response([
            'campaigns' => [],
        ], 200),
    ]);

    $account = AisensyAccount::create([
        'name' => 'Main',
        'api_key' => 'key',
        'project_api_password' => 'pwd',
        'project_id' => 'proj_1',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $client = app(AiSensyProjectClient::class);
    $list = $client->listWaTemplates($account);

    expect($list)->toHaveCount(1)
        ->and($list[0]['name'])->toBe('birthday_template_with_link_final')
        ->and($list[0]['param_count'])->toBe(1)
        ->and($list[0]['header_type'])->toBe('IMAGE')
        ->and($client->lastError())->toBeNull();
});

it('detects duplicate campaign names', function () {
    Http::fake([
        'apis.aisensy.com/project-apis/v1/project/proj_1/campaigns' => Http::response([
            'campaigns' => [
                ['id' => '1', 'name' => 'Fest Blast', 'type' => 'API'],
            ],
        ], 200),
        'apis.aisensy.com/project-apis/v1/project/proj_1/campaign/api' => Http::response([
            'campaign' => [],
        ], 200),
    ]);

    $account = AisensyAccount::create([
        'name' => 'Main',
        'api_key' => 'key',
        'project_api_password' => 'pwd',
        'project_id' => 'proj_1',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $client = app(AiSensyProjectClient::class);

    expect($client->campaignNameExists($account, 'fest blast'))->toBeTrue()
        ->and($client->campaignNameExists($account, 'brand-new'))->toBeFalse();
});
