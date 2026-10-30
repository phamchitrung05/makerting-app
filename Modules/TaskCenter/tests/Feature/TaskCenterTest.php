<?php

namespace Modules\TaskCenter\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Models\User;
use Modules\TaskCenter\DTOs\CreateTaskRunDto;
use Modules\TaskCenter\DTOs\TaskProgressUpdateDto;
use Modules\TaskCenter\Models\SystemAlert;
use Modules\TaskCenter\Models\SystemTaskRun;
use Modules\TaskCenter\Registries\TaskRunAdapterRegistry;
use Modules\TaskCenter\Services\AccountHealthAlertService;
use Modules\TaskCenter\Services\TaskTrackerService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Feature test cho module TaskCenter.
 * =====================================================================
 * Kiểm chứng dedupe, vòng đời task, API active/cancel, alert + permission.
 * =====================================================================
 */
class TaskCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['task-center.view', 'task-center.manage'] as $perm) {
            Permission::findOrCreate($perm, 'admin');
        }
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
        return ['Authorization' => 'Bearer '.$u->createToken('t')->plainTextToken];
    }

    public function test_dedupe_tra_task_cu(): void
    {
        $s = app(TaskTrackerService::class);
        $dto = new CreateTaskRunDto('k1', 'VideoStudio', 'video_render', 'Render A');

        $t1 = $s->create($dto);
        $t2 = $s->create($dto);

        $this->assertTrue($t1->wasRecentlyCreated);
        $this->assertFalse($t2->wasRecentlyCreated);
        $this->assertSame($t1->id, $t2->id);
        $this->assertSame(1, SystemTaskRun::count());
    }

    public function test_vong_doi_task_progress_complete(): void
    {
        $s = app(TaskTrackerService::class);
        $t = $s->create(new CreateTaskRunDto('k2', 'CoreAi', 'ai_analysis', 'Phân tích'));

        $s->start($t->id);
        $s->progress($t->id, new TaskProgressUpdateDto(45, 'Đang chạy'));
        $s->complete($t->id);

        $done = $s->get($t->id);
        $this->assertSame('completed', $done->status);
        $this->assertSame(100, $done->progress);
        $this->assertSame('Đang chạy', $done->meta_json['last_message']);
        $this->assertNotNull($done->completed_at);
    }

    public function test_api_tasks_active_va_cancel(): void
    {
        $user = $this->admin(['task-center.view', 'task-center.manage']);
        $s = app(TaskTrackerService::class);
        $t = $s->create(new CreateTaskRunDto('k3', 'CoreAi', 'ai_analysis', 'Active Task'));
        $s->start($t->id);

        // active list
        $res = $this->getJson('/api/v1/admin/task-center/tasks/active', $this->token($user));
        $res->assertOk()->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.status', 'running');

        // cancel
        $c = $this->postJson("/api/v1/admin/task-center/tasks/{$t->id}/cancel", [], $this->token($user));
        $c->assertOk()->assertJsonPath('data.status', 'cancelled');

        // cancel lại -> 409
        $again = $this->postJson("/api/v1/admin/task-center/tasks/{$t->id}/cancel", [], $this->token($user));
        $again->assertStatus(409);
    }

    public function test_post_tasks_dedupe_202(): void
    {
        $user = $this->admin(['task-center.view', 'task-center.manage']);
        $h = $this->token($user);

        // lần 1 -> 201
        $r1 = $this->postJson('/api/v1/admin/task-center/tasks', [
            'dedupe_key' => 'dup-1', 'module' => 'CoreAi',
            'task_type' => 'ai_analysis', 'title' => 'Job 1',
        ], $h);
        $r1->assertStatus(201);

        // lần 2 cùng dedupe_key -> 202
        $r2 = $this->postJson('/api/v1/admin/task-center/tasks', [
            'dedupe_key' => 'dup-1', 'module' => 'CoreAi',
            'task_type' => 'ai_analysis', 'title' => 'Job 1',
        ], $h);
        $r2->assertStatus(202);
    }

    public function test_alert_raise_va_read(): void
    {
        $user = $this->admin(['task-center.view', 'task-center.manage']);
        $a = app(AccountHealthAlertService::class);
        $al = $a->raiseTokenExpiry('Fanpage Test', 'facebook', 'hết hạn sau 2 ngày');

        $this->assertSame(1, SystemAlert::unread()->count());

        // unread count endpoint
        $c = $this->getJson('/api/v1/admin/task-center/alerts/unread-count', $this->token($user));
        $c->assertOk()->assertJsonPath('data.count', 1);

        // mark read
        $r = $this->postJson("/api/v1/admin/task-center/alerts/{$al->id}/read", [], $this->token($user));
        $r->assertOk();
        $this->assertSame(0, SystemAlert::unread()->count());
    }

    public function test_user_khong_quyen_bi_403(): void
    {
        $user = $this->admin([]);
        $res = $this->getJson('/api/v1/admin/task-center/tasks', $this->token($user));
        $res->assertStatus(403);
    }

    public function test_adapter_registry_meta(): void
    {
        $reg = app(TaskRunAdapterRegistry::class);
        $meta = $reg->meta('CoreAi', 'ai_analysis');
        $this->assertSame('ai_analysis', $meta['taskType']);
        $this->assertSame('ai_analysis', $meta['label']); // fallback
        $this->assertArrayHasKey('icon', $meta);
    }
}
