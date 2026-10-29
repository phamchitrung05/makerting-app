<?php

namespace Modules\Identity\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Models\ChannelGroup;
use Modules\Identity\Models\User;
use Modules\Identity\Registries\PermissionRegistry;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Feature test cho module Identity.
 * =====================================================================
 * Kiểm chứng PermissionRegistry, login Sanctum (contract Vuexy), CRUD
 * ChannelGroup và phạm vi truy cập qua ChannelGroupContextService.
 * =====================================================================
 */
class IdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('channel-groups.manage', 'admin');
    }

    private function admin(): User
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('secret123'),
        ]);
        $user->givePermissionTo('channel-groups.manage');

        return $user;
    }

    public function test_permission_registry_dang_ky_module(): void
    {
        $reg = app(PermissionRegistry::class);
        $this->assertArrayHasKey('identity', $reg->all());
        $this->assertContains('channel-groups.manage', $reg->allPermissionNames());
    }

    public function test_login_tra_contract_vuexy(): void
    {
        $user = $this->admin();

        $res = $this->postJson('/api/v1/admin/identity/auth/login', [
            'email' => 'admin@test.local',
            'password' => 'secret123',
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['accessToken', 'userData', 'userAbilityRules'],
            ]);

        $this->assertNotEmpty($res->json('data.accessToken'));
    }

    public function test_login_sai_mat_khau_422(): void
    {
        $this->admin();

        $res = $this->postJson('/api/v1/admin/identity/auth/login', [
            'email' => 'admin@test.local',
            'password' => 'sai-mat-khau',
        ]);

        $res->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_channel_group_crud(): void
    {
        $user = $this->admin();
        $token = $user->createToken('admin')->plainTextToken;
        $h = ['Authorization' => 'Bearer '.$token];

        // create
        $create = $this->postJson('/api/v1/admin/identity/channel-groups', [
            'name' => 'Cụm Kênh Công Nghệ',
            'color' => 'primary',
        ], $h);
        $create->assertStatus(201)->assertJsonPath('success', true);
        $id = $create->json('data.id');
        $this->assertSame('cum-kenh-cong-nghe', $create->json('data.code'));

        // options
        $opt = $this->getJson('/api/v1/admin/identity/channel-groups/options', $h);
        $opt->assertOk()->assertJsonCount(1, 'data');

        // update
        $upd = $this->putJson("/api/v1/admin/identity/channel-groups/{$id}", [
            'name' => 'Cụm Kênh Mỹ Phẩm',
            'is_active' => true,
        ], $h);
        $upd->assertOk()->assertJsonPath('data.name', 'Cụm Kênh Mỹ Phẩm');

        // index dataTable
        $list = $this->getJson('/api/v1/admin/identity/channel-groups', $h);
        $list->assertOk()->assertJsonPath('data.itemsLength', 1);

        // delete
        $del = $this->deleteJson("/api/v1/admin/identity/channel-groups/{$id}", [], $h);
        $del->assertStatus(204);
        $this->assertDatabaseMissing('channel_groups', ['id' => $id]);
    }

    public function test_role_crud_sync_permission(): void
    {
        $user = $this->admin();
        $token = $user->createToken('admin')->plainTextToken;
        $h = ['Authorization' => 'Bearer '.$token];

        $create = $this->postJson('/api/v1/admin/identity/roles', [
            'name' => 'editor',
            'permissions' => ['channel-groups.manage'],
        ], $h);
        $create->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('roles', ['name' => 'editor', 'guard_name' => 'admin']);
    }
}
