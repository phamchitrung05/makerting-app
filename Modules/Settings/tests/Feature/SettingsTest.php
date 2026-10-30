<?php

namespace Modules\Settings\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Models\User;
use Modules\Settings\Registries\SettingsRegistry;
use Modules\Settings\Services\ModularSettingsService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Feature test cho module Settings.
 * =====================================================================
 * Kiểm chứng SettingsRegistry, GET/PUT /v1/admin/settings/{group}, khóa
 * version 409, che secret *_configured và phân quyền động theo group.
 * =====================================================================
 */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->runSettingsMigrations(); // Chạy settings migration của module.
        foreach ([
            'settings.site.view', 'settings.site.manage',
            'settings.security.view', 'settings.security.manage',
            'settings.mail.view', 'settings.mail.manage',
        ] as $perm) {
            Permission::findOrCreate($perm, 'admin');
        }
    }

    private function runSettingsMigrations(): void
    {
        // Chạy settings migration của module (seed 3 nhóm).
        $this->artisan('migrate', ['--path' => 'Modules/Settings/database/settings', '--realpath' => true]);
    }

    private function admin(array $perms): User
    {
        $user = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local',
            'password' => Hash::make('secret123'),
        ]);
        $user->givePermissionTo($perms);

        return $user;
    }

    private function token(User $u): array
    {
        return ['Authorization' => 'Bearer '.$u->createToken('admin')->plainTextToken];
    }

    public function test_registry_nap_3_nhom(): void
    {
        $reg = app(SettingsRegistry::class);
        $this->assertEqualsCanonicalizing(['site', 'security', 'mail'], $reg->groups());
    }

    public function test_index_tra_danh_sach_nhom(): void
    {
        $user = $this->admin(['settings.site.view']);
        $res = $this->getJson('/api/v1/admin/settings', $this->token($user));
        $res->assertOk()->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['groups']]);
        $groups = collect($res->json('data.groups'))->pluck('group');
        $this->assertContains('site', $groups);
    }

    public function test_get_nhom_che_secret(): void
    {
        $user = $this->admin(['settings.mail.view', 'settings.mail.manage']);
        // Đặt mật khẩu SMTP trước.
        app(ModularSettingsService::class)->update('mail', ['mail_password' => 's3cret'], 1);

        $res = $this->getJson('/api/v1/admin/settings/mail', $this->token($user));
        $res->assertOk();
        $values = $res->json('data.values');
        $this->assertArrayNotHasKey('mail_password', $values, 'không được lộ giá trị secret');
        $this->assertTrue($values['mail_password_configured']);
    }

    public function test_update_tang_version_va_409(): void
    {
        $user = $this->admin(['settings.site.view', 'settings.site.manage']);
        $h = $this->token($user);

        // Lấy version hiện tại.
        $cur = $this->getJson('/api/v1/admin/settings/site', $h)->json('data.version');
        $this->assertSame(1, $cur);

        // Cập nhật đúng version.
        $ok = $this->putJson('/api/v1/admin/settings/site', [
            'expected_version' => 1,
            'values' => ['site_name' => 'Makerting Pro'],
        ], $h);
        $ok->assertOk()->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.values.site_name', 'Makerting Pro');

        // Gửi lại version cũ -> 409.
        $stale = $this->putJson('/api/v1/admin/settings/site', [
            'expected_version' => 1,
            'values' => ['site_name' => 'Hack'],
        ], $h);
        $stale->assertStatus(409);
    }

    public function test_update_giu_secret_khi_bo_trong(): void
    {
        $user = $this->admin(['settings.mail.view', 'settings.mail.manage']);
        $svc = app(ModularSettingsService::class);
        $svc->update('mail', ['mail_password' => 'keep-me'], 1);

        $h = $this->token($user);
        // Gửi mail_password rỗng -> phải giữ 'keep-me'.
        $res = $this->putJson('/api/v1/admin/settings/mail', [
            'expected_version' => 2,
            'values' => ['mail_host' => 'smtp.test', 'mail_password' => ''],
        ], $h);
        $res->assertOk();
        $this->assertSame('smtp.test', $res->json('data.values.mail_host'));
        $this->assertTrue($res->json('data.values.mail_password_configured'));
    }

    public function test_user_khong_quyen_bi_403(): void
    {
        $user = $this->admin([]); // Không cấp quyền.
        $res = $this->getJson('/api/v1/admin/settings/site', $this->token($user));
        $res->assertStatus(403);
    }

    public function test_group_khong_ton_tai_404(): void
    {
        $user = $this->admin(['settings.site.view']);
        $res = $this->getJson('/api/v1/admin/settings/khong-ton-tai', $this->token($user));
        $res->assertStatus(404);
    }

    public function test_mail_password_duoc_ma_hoa_trong_db(): void
    {
        $user = $this->admin(['settings.mail.view', 'settings.mail.manage']);
        $svc = app(ModularSettingsService::class);
        $svc->update('mail', ['mail_password' => 'my-smtp-secret'], 1);

        $payload = \DB::table('settings')
            ->where('group', 'mail')->where('name', 'mail_password')
            ->value('payload');

        // Phải là chuỗi mã hóa (Laravel Crypto base64 json) chứ không plaintext.
        $this->assertNotSame('"my-smtp-secret"', $payload);
        $this->assertStringNotContainsString('my-smtp-secret', (string) $payload);
        // Giải mã lại phải trả đúng giá trị (Spatie serialize khi encrypt).
        $this->assertSame('my-smtp-secret', unserialize(\Crypt::decryptString(json_decode($payload, true))));
    }

    public function test_update_tu_choi_key_la_khong_khai_bao(): void
    {
        $user = $this->admin(['settings.site.view', 'settings.site.manage']);
        $h = $this->token($user);

        // Gửi key lạ trùng tên property nội bộ của Settings + key rác.
        $res = $this->putJson('/api/v1/admin/settings/site', [
            'expected_version' => 1,
            'values' => [
                'site_name' => 'Hợp lệ',
                'config' => ['x' => 1],
                'mapper' => 'hack',
                'loaded' => true,
                'mail_password_configured' => true,
                'khong_khai_bao' => 'abc',
            ],
        ], $h);

        $res->assertOk();
        // Key lạ bị lọc — chỉ site_name ghi.
        $this->assertSame('Hợp lệ', $res->json('data.values.site_name'));
        $this->assertArrayNotHasKey('config', $res->json('data.values'));
        $this->assertArrayNotHasKey('mapper', $res->json('data.values'));
    }
}
