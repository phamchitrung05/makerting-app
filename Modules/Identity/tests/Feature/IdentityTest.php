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
        // Seed đủ các quyền mà test sẽ cấp cho user.
        foreach (['channel-groups.view', 'channel-groups.manage', 'roles.view', 'roles.manage'] as $perm) {
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
        // Cấp đủ quyền để test vượt qua middleware permission.
        $user->givePermissionTo([
            'channel-groups.view',
            'channel-groups.manage',
            'roles.view',
            'roles.manage',
        ]);

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

    public function test_channel_group_update_khong_xoa_defaults_json_khi_partial(): void
    {
        $user = $this->admin();
        $token = $user->createToken('admin')->plainTextToken;
        $h = ['Authorization' => 'Bearer '.$token];

        // Tạo nhóm có defaults_json.
        $create = $this->postJson('/api/v1/admin/identity/channel-groups', [
            'name' => 'Cụm Kênh',
            'defaults_json' => ['writing_profile' => 'seo', 'tts_voice' => 'vi-VN-1'],
            'description' => 'Mô tả ban đầu',
        ], $h);
        $create->assertStatus(201);
        $id = $create->json('data.id');

        // PUT chỉ đổi name — KHÔNG gửi defaults_json/description/color/is_active.
        $update = $this->putJson("/api/v1/admin/identity/channel-groups/{$id}", [
            'name' => 'Cụm Kênh Mới',
        ], $h);
        $update->assertOk();

        $fresh = ChannelGroup::find($id);
        // defaults_json và description phải GIỮ NGUYÊN (không bị wipe về null).
        $this->assertSame(['writing_profile' => 'seo', 'tts_voice' => 'vi-VN-1'], $fresh->defaults_json);
        $this->assertSame('Mô tả ban đầu', $fresh->description);
        $this->assertSame('cum-kenh', $fresh->code);
    }

    public function test_channel_group_partial_update_chi_gui_is_active_hoac_defaults(): void
    {
        $user = $this->admin();
        $token = $user->createToken('admin')->plainTextToken;
        $h = ['Authorization' => 'Bearer '.$token];

        $create = $this->postJson('/api/v1/admin/identity/channel-groups', [
            'name' => 'Nhóm A',
        ], $h);
        $id = $create->json('data.id');

        // PUT chỉ gửi is_active (không name) — trước đây sẽ lỗi undefined 'name'.
        $this->putJson("/api/v1/admin/identity/channel-groups/{$id}", [
            'is_active' => false,
        ], $h)->assertOk();
        $this->assertFalse((bool) ChannelGroup::find($id)->is_active);

        // PUT chỉ gửi defaults_json.
        $this->putJson("/api/v1/admin/identity/channel-groups/{$id}", [
            'defaults_json' => ['motif' => 'm1'],
        ], $h)->assertOk();
        $this->assertSame(['motif' => 'm1'], ChannelGroup::find($id)->defaults_json);
    }

    public function test_channel_group_code_trung_sinh_hau_to_thay_vi_500(): void
    {
        $user = $this->admin();
        $token = $user->createToken('admin')->plainTextToken;
        $h = ['Authorization' => 'Bearer '.$token];

        // Tạo 'cum-kenh' trước.
        $this->postJson('/api/v1/admin/identity/channel-groups', ['name' => 'Cum Kenh'], $h)
            ->assertStatus(201);

        // Tạo 'Cum Kenh' khác — slug trùng -> phải sinh 'cum-kenh-1' chứ không 500.
        $create = $this->postJson('/api/v1/admin/identity/channel-groups', ['name' => 'Cum Kenh'], $h);
        $create->assertStatus(201);
        $this->assertSame('cum-kenh-1', $create->json('data.code'));
    }

    public function test_channel_group_store_thieu_name_tra_422(): void
    {
        $user = $this->admin();
        $token = $user->createToken('admin')->plainTextToken;
        $h = ['Authorization' => 'Bearer '.$token];

        // Gửi payload không name (bypass FormRequest để chắc chắn action cũng bắt).
        $res = $this->postJson('/api/v1/admin/identity/channel-groups', [
            'description' => 'Không name',
        ], $h);
        // FormRequest required sẽ trả 422 trước; nếu lọt vào action cũng 422.
        $res->assertStatus(422);
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

    public function test_store_role_khong_tao_permission_wildcard_hoac_rac(): void
    {
        $user = $this->admin();
        $token = $user->createToken('admin')->plainTextToken;
        $h = ['Authorization' => 'Bearer '.$token];

        // Gửi '*' (wildcard super-admin) + chuỗi rác chưa đăng ký.
        $create = $this->postJson('/api/v1/admin/identity/roles', [
            'name' => 'attacker',
            'permissions' => ['*', 'not-a-real-permission'],
        ], $h);

        // 422 vì Rule::in từ registry chặn cả '*' lẫn chuỗi rác.
        $create->assertStatus(422)->assertJsonValidationErrors('permissions.0');
        $this->assertDatabaseMissing('permissions', ['name' => '*']);
        $this->assertDatabaseMissing('permissions', ['name' => 'not-a-real-permission']);
    }

    public function test_update_role_chan_sua_super_admin(): void
    {
        $user = $this->admin();
        $token = $user->createToken('admin')->plainTextToken;
        $h = ['Authorization' => 'Bearer '.$token];

        $super = Role::create(['name' => 'super-admin', 'guard_name' => 'admin']);

        // Chặn đổi tên super-admin.
        $rename = $this->putJson("/api/v1/admin/identity/roles/{$super->id}", [
            'name' => 'renamed-admin',
        ], $h);
        $rename->assertStatus(409);
        $this->assertSame('super-admin', $super->fresh()->name);

        // Chặn rút quyền khỏi super-admin (syncPermissions []).
        $strip = $this->putJson("/api/v1/admin/identity/roles/{$super->id}", [
            'name' => 'super-admin',
            'permissions' => [],
        ], $h);
        $strip->assertStatus(409);
    }
}
