<?php

namespace Modules\WritingStyle\Tests\Feature;

use Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Modules\WritingStyle\Jobs\AnalyzeWritingProfileJob;
use Modules\WritingStyle\Models\WritingProfile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Feature test HTTP API WritingStyle (Task 9).
 */
class WritingStyleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['writing.profile.view', 'writing.profile.create', 'writing.profile.manage', 'writing.evaluate'] as $p) {
            Permission::findOrCreate($p, 'admin');
        }
    }

    private function admin(array $perms): User
    {
        $u = User::create(['name' => 'A', 'email' => 'a'.uniqid().'@t.local', 'password' => Hash::make('x12345678')]);
        $u->givePermissionTo($perms);
        return $u;
    }

    private function token(User $u): array
    {
        return ['Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken];
    }

    public function test_options_tra_tiers_va_rules(): void
    {
        $u = $this->admin(['writing.profile.view']);
        $res = $this->getJson('/api/v1/admin/writing-style/writing-profiles/options', $this->token($u));
        $res->assertOk()
            ->assertJsonCount(4, 'data.tiers')
            ->assertJsonCount(16, 'data.rules');
    }

    public function test_crud_profile(): void
    {
        $u = $this->admin(['writing.profile.view', 'writing.profile.create', 'writing.profile.manage']);
        $t = $this->token($u);

        // store
        $res = $this->postJson('/api/v1/admin/writing-style/writing-profiles', [
            'name' => 'Giọng Tay', 'dominant_tier' => 'short',
            'rules_json' => ['tone' => 'ấm'],
        ], $t);
        $res->assertCreated()->assertJsonPath('data.name', 'Giọng Tay');
        $id = $res->json('data.id');
        $this->assertSame('manual', WritingProfile::find($id)->origin);

        // index
        $this->getJson('/api/v1/admin/writing-style/writing-profiles', $t)->assertOk();

        // update
        $this->putJson("/api/v1/admin/writing-style/writing-profiles/{$id}", ['name' => 'Giọng Mới'], $t)
            ->assertOk()->assertJsonPath('data.name', 'Giọng Mới');
        $this->assertSame(2, WritingProfile::find($id)->version);

        // delete
        $this->deleteJson("/api/v1/admin/writing-style/writing-profiles/{$id}", [], $t)->assertNoContent();
        $this->assertNull(WritingProfile::find($id));
    }

    public function test_analyze_tra_202_va_dispatch_job(): void
    {
        Queue::fake();
        $u = $this->admin(['writing.evaluate']);

        $res = $this->postJson('/api/v1/admin/writing-style/writing-profiles/analyze', [
            'source_type' => 'paste',
            'reference_text' => str_repeat('Bài mẫu phân tích DNA văn phong đủ dài. ', 4),
            'name' => 'Mẫu X',
        ], $this->token($u));

        $res->assertAccepted()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data' => ['analysis_id', 'task_run_id']]);

        Queue::assertPushed(AnalyzeWritingProfileJob::class);
    }

    public function test_analyze_thieu_reference_text_422(): void
    {
        $u = $this->admin(['writing.evaluate']);
        $this->postJson('/api/v1/admin/writing-style/writing-profiles/analyze', [
            'source_type' => 'url',
            'source_url' => 'https://x.com/a',
        ], $this->token($u))->assertUnprocessable();
    }

    public function test_can_thieu_quyen_bi_403(): void
    {
        $u = $this->admin([]); // không quyền
        $this->getJson('/api/v1/admin/writing-style/writing-profiles', $this->token($u))->assertForbidden();
    }
    public function test_update_partial_khong_can_name(): void
    {
        $u = $this->admin(['writing.profile.create']);
        $t = $this->token($u);
        $p = WritingProfile::create([
            'name' => 'Gốc', 'dominant_tier' => 'long', 'origin' => 'manual',
            'status' => 'draft', 'is_enabled' => false,
            'rules_json' => [], 'evidence_json' => [],
        ]);

        // PUT chỉ bật is_enabled + duyệt status — KHÔNG gửi name -> trước đây 422.
        $this->putJson("/api/v1/admin/writing-style/writing-profiles/{$p->id}", [
            'is_enabled' => true,
            'status' => 'active',
        ], $t)->assertOk();

        $fresh = $p->fresh();
        $this->assertTrue((bool) $fresh->is_enabled);
        $this->assertSame('active', $fresh->status);
        $this->assertSame('Gốc', $fresh->name);
        $this->assertSame(2, $fresh->version);
    }

    public function test_resource_tra_version_va_updated_at(): void
    {
        $u = $this->admin(['writing.profile.view']);
        $t = $this->token($u);
        $p = WritingProfile::create([
            'name' => 'X', 'dominant_tier' => 'short', 'origin' => 'manual', 'status' => 'draft', 'rules_json' => [], 'evidence_json' => [],
        ]);

        $res = $this->getJson("/api/v1/admin/writing-style/writing-profiles/{$p->id}", $t);
        $res->assertOk()->assertJsonStructure(['data' => ['version', 'updated_at', 'created_at']]);
    }
}
