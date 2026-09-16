<?php

use App\Models\AisensyAccount;
use App\Models\User;
use App\Support\AdminInertiaResources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createAisensySecretsAdmin(): User
{
    Permission::firstOrCreate(['name' => 'manage aisensy accounts']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions(['manage aisensy accounts']);

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

it('encrypts api keys at rest and never exposes the raw key over inertia', function () {
    $account = AisensyAccount::create([
        'name' => 'Main',
        'api_key' => 'live-secret-key',
        'project_api_password' => 'project-pwd',
        'project_id' => 'proj_1',
        'country_code' => '91',
        'is_active' => true,
    ]);

    $raw = DB::table('aisensy_accounts')->where('id', $account->id)->value('api_key');

    expect($raw)->not->toBe('live-secret-key')
        ->and(Crypt::decryptString((string) $raw))->toBe('live-secret-key')
        ->and($account->fresh()->api_key)->toBe('live-secret-key');

    $payload = AdminInertiaResources::aisensyAccount($account->fresh());

    expect($payload['api_key'])->toBe('********')
        ->and($payload['has_api_key'])->toBeTrue()
        ->and($payload['project_api_password'])->toBe('********')
        ->and(json_encode($payload))->not->toContain('live-secret-key')
        ->and(json_encode($payload))->not->toContain('project-pwd');

    actingAs(createAisensySecretsAdmin())
        ->get(route('admin.aisensy-accounts.edit', $account))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AisensyAccounts/Form')
            ->where('account.api_key', '********')
            ->where('account.has_api_key', true));
});

it('keeps the existing encrypted api key when the masked placeholder is submitted', function () {
    $account = AisensyAccount::create([
        'name' => 'Main',
        'api_key' => 'original-key',
        'project_id' => 'proj_1',
        'country_code' => '91',
        'is_active' => true,
    ]);

    actingAs(createAisensySecretsAdmin())
        ->put(route('admin.aisensy-accounts.update', $account), [
            'name' => 'Main Updated',
            'api_key' => '********',
            'project_api_password' => '',
            'project_id' => 'proj_1',
            'country_code' => '91',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.aisensy-accounts.index'));

    expect($account->fresh()->api_key)->toBe('original-key')
        ->and($account->fresh()->name)->toBe('Main Updated');
});

it('does not throw when an aisensy secret cannot be decrypted', function () {
    $id = DB::table('aisensy_accounts')->insertGetId([
        'name' => 'Broken key',
        'api_key' => 'not-a-valid-laravel-payload',
        'project_api_password' => 'also-invalid',
        'project_id' => 'proj_broken',
        'country_code' => '91',
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $account = AisensyAccount::query()->findOrFail($id);

    expect($account->api_key)->toBeNull()
        ->and($account->project_api_password)->toBeNull()
        ->and($account->hasApiKey())->toBeFalse()
        ->and($account->hasProjectApiPassword())->toBeFalse();
});

it('encrypts legacy plaintext project api passwords left in the database', function () {
    $id = DB::table('aisensy_accounts')->insertGetId([
        'name' => 'Legacy',
        'api_key' => Crypt::encryptString('already-encrypted-key'),
        'project_api_password' => 'legacy-project-password',
        'project_id' => 'proj_legacy',
        'country_code' => '91',
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_07_29_142840_encrypt_aisensy_account_project_api_passwords.php');
    $migration->up();

    $raw = (string) DB::table('aisensy_accounts')->where('id', $id)->value('project_api_password');

    expect($raw)->not->toBe('legacy-project-password')
        ->and(Crypt::decryptString($raw))->toBe('legacy-project-password')
        ->and(AisensyAccount::query()->findOrFail($id)->project_api_password)->toBe('legacy-project-password');
});
