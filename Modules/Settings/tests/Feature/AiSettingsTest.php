<?php

namespace Modules\Settings\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Run settings migrations
        $this->artisan('migrate', ['--path' => 'Modules/Settings/database/settings', '--realpath' => true]);
        $this->artisan('migrate', ['--path' => 'Modules/CoreAi/database/settings', '--realpath' => true]);

        foreach ([
            'settings.ai.view', 'settings.ai.manage',
        ] as $perm) {
            Permission::findOrCreate($perm, 'admin');
        }
    }

    private function admin(): User
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('secret123'),
        ]);
        $user->givePermissionTo(['settings.ai.view', 'settings.ai.manage']);

        return $user;
    }

    public function test_can_get_and_update_ai_settings(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('admin')->plainTextToken;
        $headers = ['Authorization' => 'Bearer ' . $token];

        // 1. GET
        $res = $this->getJson('/api/v1/admin/settings/ai', $headers);
        $res->assertOk();
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('data.group', 'ai');
        $version = $res->json('data.version');

        // 2. PUT
        $putData = [
            'expected_version' => $version,
            'values' => [
                'default_text_model_id' => null,
                'default_image_model_id' => null,
                'default_video_model_id' => null,
                'default_language' => 'en',
            ],
        ];

        $putRes = $this->putJson('/api/v1/admin/settings/ai', $putData, $headers);
        $putRes->assertOk();
        $putRes->assertJsonPath('success', true);
        $putRes->assertJsonPath('data.version', $version + 1);
        $putRes->assertJsonPath('data.values.default_language', 'en');
    }
}
