<?php

namespace Modules\CoreAi\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Services\CredentialVault;
use Modules\CoreAi\Models\AiModel;
use Modules\CoreAi\Models\AiProvider;
use Modules\Identity\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: HTTP test cho API admin CoreAi (providers + models).
 * =====================================================================
 * Kiểm chứng CRUD + phân quyền (view/manage), che api_key, encode
 * extra_keys, test-ping (driver fake), unique (provider_id,remote_model_id).
 * =====================================================================
 */
class CoreAiHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['coreai.view', 'coreai.manage', 'coreai.execute'] as $p) {
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

    private function providerPayload(array $over = []): array
    {
        return array_merge([
            'key' => 'openai-main',
            'name' => 'OpenAI Chính',
            'kind' => 'official',
            'driver' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test-12345678',
            'request_timeout' => 120,
            'is_active' => true,
        ], $over);
    }

    private function makeProvider(array $over = []): AiProvider
    {
        $p = array_merge([
            'key' => 'prov', 'name' => 'P', 'kind' => 'official', 'driver' => 'fake',
            'base_url' => 'https://api.x', 'api_key_encrypted' => app(CredentialVault::class)->encrypt('key-1'),
            'is_active' => true,
        ], $over);
        return AiProvider::create($p);
    }

    private function makeModel(AiProvider $p, array $over = []): AiModel
    {
        return AiModel::create(array_merge([
            'ai_provider_id' => $p->id,
            'remote_model_id' => 'm-'.uniqid(),
            'label' => 'M',
            'capabilities_json' => ['text_generation'],
            'is_enabled' => true, 'is_available' => true,
        ], $over));
    }

    /* =========================== AUTH =========================== */

    public function test_chua_dang_nhap_bi_401(): void
    {
        $this->getJson('/api/v1/admin/core-ai/providers')->assertStatus(401);
    }

    public function test_khong_quyen_bi_403(): void
    {
        $u = $this->admin([]);
        $this->getJson('/api/v1/admin/core-ai/providers', $this->token($u))->assertStatus(403);
    }

    public function test_view_doc_duoc_nhung_khong_ghi(): void
    {
        $u = $this->admin(['coreai.view']);
        $h = $this->token($u);
        $this->getJson('/api/v1/admin/core-ai/providers', $h)->assertOk();
        $this->postJson('/api/v1/admin/core-ai/providers', $this->providerPayload(), $h)->assertStatus(403);
    }

    /* =========================== PROVIDER CRUD =========================== */

    public function test_store_provider_ma_hoa_key(): void
    {
        $u = $this->admin(['coreai.manage']);
        $res = $this->postJson('/api/v1/admin/core-ai/providers', $this->providerPayload([
            'api_key' => 'sk-secret-999',
            'extra_keys' => ['ek-1-abc', 'ek-2-def'],
        ]), $this->token($u));

        $res->assertStatus(201);
        $this->assertSame('openai-main', $res->json('data.key'));
        $this->assertTrue($res->json('data.api_key_configured'));
        $this->assertSame(2, $res->json('data.extra_keys_count'));

        // api_key_encrypted phải là ciphertext, không chứa plaintext.
        $p = AiProvider::where('key', 'openai-main')->first();
        $this->assertStringNotContainsString('sk-secret-999', $p->api_key_encrypted);
        $this->assertSame('sk-secret-999', app(CredentialVault::class)->decrypt($p->api_key_encrypted));
        // extra keys cũng mã hóa + decrypt được.
        $this->assertCount(2, $p->extraKeys());
    }

    public function test_store_provider_validate_key_unique_va_api_key(): void
    {
        $u = $this->admin(['coreai.manage']);
        $this->makeProvider(['key' => 'dup-key']);

        // Trùng key.
        $res = $this->postJson('/api/v1/admin/core-ai/providers', $this->providerPayload(['key' => 'dup-key']), $this->token($u));
        $res->assertStatus(422)->assertJsonValidationErrors('key');

        // Thiếu api_key.
        $res2 = $this->postJson('/api/v1/admin/core-ai/providers', array_merge($this->providerPayload(['key' => 'ok']), ['api_key' => '']), $this->token($u));
        $res2->assertStatus(422)->assertJsonValidationErrors('api_key');

        // base_url không phải http/https.
        $res3 = $this->postJson('/api/v1/admin/core-ai/providers', $this->providerPayload(['key' => 'x', 'base_url' => 'ftp://bad']), $this->token($u));
        $res3->assertStatus(422)->assertJsonValidationErrors('base_url');
    }

    public function test_provider_resource_khong_lo_secret(): void
    {
        $u = $this->admin(['coreai.view']);
        $this->makeProvider(['key' => 'pv']);

        $res = $this->getJson('/api/v1/admin/core-ai/providers', $this->token($u));
        $res->assertOk();
        $raw = $res->getContent();
        $this->assertStringNotContainsString('api_key_encrypted', $raw);
        $this->assertStringNotContainsString('key-1', $raw);
    }

    public function test_update_provider_giu_key_khi_bo_trong(): void
    {
        $u = $this->admin(['coreai.manage']);
        $p = $this->makeProvider(['key' => 'pv']);
        $orig = $p->api_key_encrypted;

        // api_key rỗng -> giữ nguyên api_key_encrypted.
        $res = $this->putJson("/api/v1/admin/core-ai/providers/{$p->id}", [
            'name' => 'Tên mới',
            'api_key' => '',
        ], $this->token($u));

        $res->assertOk();
        $this->assertSame('Tên mới', $p->fresh()->name);
        $this->assertSame($orig, $p->fresh()->api_key_encrypted);
    }

    public function test_update_provider_extra_keys_thay_het(): void
    {
        $u = $this->admin(['coreai.manage']);
        $p = $this->makeProvider(['key' => 'pv']);
        $p->update(['extra_keys_json' => [app(CredentialVault::class)->encrypt('old1')]]);

        $res = $this->putJson("/api/v1/admin/core-ai/providers/{$p->id}", [
            'extra_keys' => ['new-key-aaa', 'new-key-bbb'],
        ], $this->token($u));

        $res->assertOk();
        $extra = $p->fresh()->extraKeys();
        $this->assertSame(['new-key-aaa', 'new-key-bbb'], $extra);
    }

    public function test_destroy_provider(): void
    {
        $u = $this->admin(['coreai.manage']);
        $p = $this->makeProvider(['key' => 'pv']);
        $this->makeModel($p);

        $this->deleteJson("/api/v1/admin/core-ai/providers/{$p->id}", [], $this->token($u))->assertStatus(204);
        $this->assertNull(AiProvider::find($p->id));
        // models cascade.
        $this->assertSame(0, AiModel::where('ai_provider_id', $p->id)->count());
    }

    public function test_providers_drivers_endpoint(): void
    {
        $u = $this->admin(['coreai.view']);
        $res = $this->getJson('/api/v1/admin/core-ai/providers/drivers', $this->token($u));
        $res->assertOk();
        $this->assertContains('openai', $res->json('data.drivers'));
        $this->assertContains('anthropic', $res->json('data.drivers'));
        $this->assertContains('official', $res->json('data.kinds'));
    }

    /* =========================== MODEL CRUD =========================== */

    public function test_store_model_validate_capability_va_unique(): void
    {
        $u = $this->admin(['coreai.manage']);
        $p = $this->makeProvider(['key' => 'pv']);
        $this->makeModel($p, ['remote_model_id' => 'dup-m']);

        // capability lạ.
        $res = $this->postJson('/api/v1/admin/core-ai/models', [
            'ai_provider_id' => $p->id, 'remote_model_id' => 'm2', 'label' => 'L',
            'capabilities' => ['bogus_cap'],
        ], $this->token($u));
        $res->assertStatus(422)->assertJsonValidationErrors('capabilities.0');

        // trùng (provider, remote_model_id).
        $res2 = $this->postJson('/api/v1/admin/core-ai/models', [
            'ai_provider_id' => $p->id, 'remote_model_id' => 'dup-m', 'label' => 'L',
            'capabilities' => ['vision'],
        ], $this->token($u));
        $res2->assertStatus(422)->assertJsonValidationErrors('remote_model_id');
    }

    public function test_store_model_ok(): void
    {
        $u = $this->admin(['coreai.manage']);
        $p = $this->makeProvider(['key' => 'pv']);

        $res = $this->postJson('/api/v1/admin/core-ai/models', [
            'ai_provider_id' => $p->id, 'remote_model_id' => 'gpt-4o', 'label' => 'GPT-4o',
            'capabilities' => ['text_generation', 'vision'],
            'pricing' => ['input_per_mtok' => 2.5, 'output_per_mtok' => 10],
        ], $this->token($u));

        $res->assertStatus(201);
        $this->assertSame('gpt-4o', $res->json('data.remote_model_id'));
        $this->assertSame(['text_generation', 'vision'], $res->json('data.capabilities'));
        $this->assertEqualsWithDelta(2.5, (float) $res->json('data.input_price_per_mtok'), 0.001);
    }

    public function test_models_options_loc_theo_provider_va_capability(): void
    {
        $u = $this->admin(['coreai.view']);
        $pa = $this->makeProvider(['key' => 'pa']);
        $pb = $this->makeProvider(['key' => 'pb']);
        $this->makeModel($pa, ['remote_model_id' => 'a-text', 'capabilities_json' => ['text_generation']]);
        $this->makeModel($pb, ['remote_model_id' => 'b-img', 'capabilities_json' => ['image_generation']]);

        // lọc theo provider.
        $res = $this->getJson("/api/v1/admin/core-ai/models/options?ai_provider_id={$pa->id}", $this->token($u));
        $ids = array_column($res->json('data'), 'remote_model_id');
        $this->assertContains('a-text', $ids);
        $this->assertNotContains('b-img', $ids);

        // lọc theo capability.
        $res2 = $this->getJson('/api/v1/admin/core-ai/models/options?capability=image_generation', $this->token($u));
        $this->assertContains('b-img', array_column($res2->json('data'), 'remote_model_id'));
        $this->assertNotContains('a-text', array_column($res2->json('data'), 'remote_model_id'));
    }

    public function test_models_capabilities_endpoint(): void
    {
        $u = $this->admin(['coreai.view']);
        $res = $this->getJson('/api/v1/admin/core-ai/models/capabilities', $this->token($u));
        $res->assertOk();

        $values = array_column($res->json('data'), 'value');
        $this->assertContains('text_generation', $values);
        $this->assertContains('image_generation', $values);
        $this->assertContains('tts', $values);
        $this->assertContains('vision', $values);

        // Mỗi item có đủ slug + nhãn tiếng Việt + icon + color.
        $first = $res->json('data')[0];
        $this->assertArrayHasKey('label', $first);
        $this->assertNotEmpty($first['label']);
        $this->assertSame('Sinh văn bản', $res->json('data')[0]['label']);
    }

    /* =========================== TEST-PING =========================== */

    public function test_test_ping_ghi_status_ok(): void
    {
        $u = $this->admin(['coreai.manage']);
        $p = $this->makeProvider(['key' => 'pv', 'driver' => 'fake']);

        // Fake driver trả ok.
        app(\Modules\CoreAi\Registries\AiDriverRegistry::class)->register('fake', fn () => new class implements \Modules\CoreAi\Contracts\AiProviderDriverContract {
            public function key(): string { return 'fake'; }
            public function supports(string $o): bool { return true; }
            public function test(\Modules\CoreAi\DTOs\AiResolvedModelDto $r): array { return ['ok' => true, 'latency_ms' => 5, 'message' => 'Kết nối tốt']; }
            public function testModel(\Modules\CoreAi\DTOs\AiResolvedModelDto $r): array { return ['ok' => true, 'latency_ms' => 5, 'message' => 'Model ok']; }
            public function structured(\Modules\CoreAi\DTOs\AiResolvedModelDto $r, \Modules\CoreAi\DTOs\AiStructuredRequest $q): \Modules\CoreAi\DTOs\AiStructuredResponse { return new \Modules\CoreAi\DTOs\AiStructuredResponse(text: 'x'); }
            public function image(\Modules\CoreAi\DTOs\AiResolvedModelDto $r, \Modules\CoreAi\DTOs\AiImageRequest $q): \Modules\CoreAi\DTOs\AiImageResponse { return new \Modules\CoreAi\DTOs\AiImageResponse(); }
            public function speech(\Modules\CoreAi\DTOs\AiResolvedModelDto $r, \Modules\CoreAi\DTOs\AiSpeechRequest $q): \Modules\CoreAi\DTOs\AiSpeechResponse { return new \Modules\CoreAi\DTOs\AiSpeechResponse(); }
            public function transcribe(\Modules\CoreAi\DTOs\AiResolvedModelDto $r, \Modules\CoreAi\DTOs\AiTranscribeRequest $q): \Modules\CoreAi\DTOs\AiTranscribeResponse { return new \Modules\CoreAi\DTOs\AiTranscribeResponse(); }
        });

        $res = $this->postJson("/api/v1/admin/core-ai/providers/{$p->id}/test", [], $this->token($u));
        $res->assertOk();
        $this->assertTrue($res->json('data.ok'));
        $this->assertSame('ok', $p->fresh()->test_status);
        $this->assertNotNull($p->fresh()->last_tested_at);
    }

    public function test_test_ping_loi_khi_provider_khong_key(): void
    {
        $u = $this->admin(['coreai.manage']);
        $p = AiProvider::create([
            'key' => 'nokey', 'name' => 'x', 'kind' => 'official', 'driver' => 'fake',
            'base_url' => 'https://x', 'api_key_encrypted' => app(CredentialVault::class)->encrypt(''),
            'is_active' => true,
        ]);

        $res = $this->postJson("/api/v1/admin/core-ai/providers/{$p->id}/test", [], $this->token($u));
        $res->assertStatus(422);
    }

    /* =========================== SYNC-MODELS =========================== */

    private function registerFakeDriverReturningModels(array $modelIds): void
    {
        app(\Modules\CoreAi\Registries\AiDriverRegistry::class)->register('fake', fn () => new class($modelIds) implements \Modules\CoreAi\Contracts\AiProviderDriverContract {
            public function __construct(private array $ids) {}
            public function key(): string { return 'fake'; }
            public function supports(string $o): bool { return true; }
            public function test(\Modules\CoreAi\DTOs\AiResolvedModelDto $r): array
            {
                return ['ok' => true, 'latency_ms' => 5, 'message' => 'ok', 'models' => $this->ids];
            }
            public function testModel(\Modules\CoreAi\DTOs\AiResolvedModelDto $r): array { return ['ok' => true, 'latency_ms' => 5, 'message' => 'Model ok']; }
            public function structured(\Modules\CoreAi\DTOs\AiResolvedModelDto $r, \Modules\CoreAi\DTOs\AiStructuredRequest $q): \Modules\CoreAi\DTOs\AiStructuredResponse { return new \Modules\CoreAi\DTOs\AiStructuredResponse(text: 'x'); }
            public function image(\Modules\CoreAi\DTOs\AiResolvedModelDto $r, \Modules\CoreAi\DTOs\AiImageRequest $q): \Modules\CoreAi\DTOs\AiImageResponse { return new \Modules\CoreAi\DTOs\AiImageResponse(); }
            public function speech(\Modules\CoreAi\DTOs\AiResolvedModelDto $r, \Modules\CoreAi\DTOs\AiSpeechRequest $q): \Modules\CoreAi\DTOs\AiSpeechResponse { return new \Modules\CoreAi\DTOs\AiSpeechResponse(); }
            public function transcribe(\Modules\CoreAi\DTOs\AiResolvedModelDto $r, \Modules\CoreAi\DTOs\AiTranscribeRequest $q): \Modules\CoreAi\DTOs\AiTranscribeResponse { return new \Modules\CoreAi\DTOs\AiTranscribeResponse(); }
        });
    }

    public function test_sync_models_upsert_ai_models(): void
    {
        $u = $this->admin(['coreai.manage']);
        $p = $this->makeProvider(['key' => 'pv', 'driver' => 'fake']);

        // Model sẵn có — sau sync phải vẫn enabled + is_available.
        $existing = $this->makeModel($p, ['remote_model_id' => 'gpt-4o', 'is_enabled' => false]);

        $this->registerFakeDriverReturningModels(['gpt-4o', 'dall-e-3', 'whisper-1']);

        $res = $this->postJson("/api/v1/admin/core-ai/providers/{$p->id}/sync-models", [], $this->token($u));

        $res->assertOk();
        $this->assertSame(3, $res->json('data.synced'));
        $this->assertSame(2, $res->json('data.created'));   // dall-e-3 + whisper-1
        $this->assertSame(1, $res->json('data.updated'));   // gpt-4o đã có

        $models = AiModel::where('ai_provider_id', $p->id)->pluck('remote_model_id')->sort()->values()->all();
        $this->assertSame(['dall-e-3', 'gpt-4o', 'whisper-1'], $models);

        // Model cũ giữ is_enabled=false; is_available = true.
        $this->assertFalse((bool) $existing->fresh()->is_enabled);
        $this->assertTrue((bool) $existing->fresh()->is_available);
        // Quan trọng: capabilities admin đã gán KHÔNG bị sync ghi đè.
        $this->assertSame(['text_generation'], $existing->fresh()->capabilities());
        // Model mới sync về -> capabilities = [] (admin gán tay, không đoán).
        $this->assertSame([], AiModel::where('remote_model_id','dall-e-3')->first()->capabilities());
        $this->assertSame([], AiModel::where('remote_model_id','whisper-1')->first()->capabilities());
    }

    /* ================= TASK 2: XÓA MODEL CŨ KHI ĐỔI KEY / SYNC ================= */

    public function test_update_doi_api_key_xoa_model_cu(): void
    {
        $u = $this->admin(['coreai.manage']);
        $p = $this->makeProvider(['key' => 'pv', 'api_key_encrypted' => app(CredentialVault::class)->encrypt('key-cu')]);
        $this->makeModel($p, ['remote_model_id' => 'm-a']);
        $this->makeModel($p, ['remote_model_id' => 'm-b']);
        $this->assertSame(2, AiModel::where('ai_provider_id', $p->id)->count());
        $p->update(['test_status' => 'ok']);

        // Đổi sang key MỚI khác -> toàn bộ model cũ bị xóa + reset test_status.
        $res = $this->putJson("/api/v1/admin/core-ai/providers/{$p->id}", [
            'api_key' => 'sk-moi-khac-12345',
        ], $this->token($u));
        $res->assertOk();
        $this->assertSame(0, AiModel::where('ai_provider_id', $p->id)->count());
        $this->assertSame(2, (int) $res->json('data.deleted_models'));
        $this->assertSame('untested', $p->fresh()->test_status);
    }

    public function test_update_giu_nguyen_key_khong_xoa_model(): void
    {
        $u = $this->admin(['coreai.manage']);
        $p = $this->makeProvider(['api_key_encrypted' => app(CredentialVault::class)->encrypt('sk-cung-mot-key-123')]);
        $this->makeModel($p, ['remote_model_id' => 'm-a']);
        $p->update(['test_status' => 'ok']);

        // PUT nhưng api_key giống key cũ -> KHÔNG xóa model.
        $res = $this->putJson("/api/v1/admin/core-ai/providers/{$p->id}", [
            'name' => 'Đổi tên', 'api_key' => 'sk-cung-mot-key-123',
        ], $this->token($u));
        $res->assertOk();
        $this->assertSame(1, AiModel::where('ai_provider_id', $p->id)->count());
        $this->assertSame(0, (int) $res->json('data.deleted_models'));
        $this->assertSame('ok', $p->fresh()->test_status); // không reset
    }

    public function test_sync_models_xoa_model_khong_con_trong_remote(): void
    {
        $u = $this->admin(['coreai.manage']);
        $p = $this->makeProvider(['key' => 'pv', 'driver' => 'fake']);
        $this->makeModel($p, ['remote_model_id' => 'gpt-4o']);
        $this->makeModel($p, ['remote_model_id' => 'stale-model']); // không có trong remote -> xóa

        $this->registerFakeDriverReturningModels(['gpt-4o', 'new-model']);

        $res = $this->postJson("/api/v1/admin/core-ai/providers/{$p->id}/sync-models", [], $this->token($u));
        $res->assertOk();
        $this->assertSame(1, (int) $res->json('data.deleted'));

        $ids = AiModel::where('ai_provider_id', $p->id)->pluck('remote_model_id')->sort()->values()->all();
        $this->assertSame(['gpt-4o', 'new-model'], $ids);
        $this->assertNull(AiModel::where('remote_model_id', 'stale-model')->first());
    }
    public function test_sync_models_loi_khi_provider_khong_key(): void
    {
        $u = $this->admin(['coreai.manage']);
        $p = AiProvider::create([
            'key' => 'nokey', 'name' => 'x', 'kind' => 'official', 'driver' => 'fake',
            'base_url' => 'https://x', 'api_key_encrypted' => app(CredentialVault::class)->encrypt(''),
            'is_active' => true,
        ]);

        $res = $this->postJson("/api/v1/admin/core-ai/providers/{$p->id}/sync-models", [], $this->token($u));
        $res->assertStatus(422);
    }

    /* =========================== MODEL TEST =========================== */

    public function test_test_model_cap_nhat_is_available(): void
    {
        $u = $this->admin(['coreai.manage']);
        $p = $this->makeProvider(['key' => 'pv', 'driver' => 'fake']);
        $m = $this->makeModel($p, ['remote_model_id' => 'm1', 'is_available' => false]);

        // Fake driver trả ok -> is_available phải bật true.
        $this->registerFakeDriverReturningModels([]);

        $res = $this->postJson("/api/v1/admin/core-ai/models/{$m->id}/test", [], $this->token($u));

        $res->assertOk();
        $this->assertTrue($res->json('data.ok'));
        $this->assertTrue((bool) $res->json('data.is_available'));
        $this->assertTrue((bool) $m->fresh()->is_available);

        // Model-ping KHÔNG đụng test_status của provider (model riêng fail
        // không có nghĩa provider hỏng) — vẫn giữ giá trị trước đó.
        $this->assertNotSame('failed', $p->fresh()->test_status);
    }

    public function test_test_model_loi_khi_provider_khong_key(): void
    {
        $u = $this->admin(['coreai.manage']);
        $p = AiProvider::create([
            'key' => 'nokey', 'name' => 'x', 'kind' => 'official', 'driver' => 'fake',
            'base_url' => 'https://x', 'api_key_encrypted' => app(CredentialVault::class)->encrypt(''),
            'is_active' => true,
        ]);
        $m = $this->makeModel($p);

        $res = $this->postJson("/api/v1/admin/core-ai/models/{$m->id}/test", [], $this->token($u));
        $res->assertStatus(422);
    }

    public function test_update_model_thay_doi_capabilities(): void
    {
        $u = $this->admin(['coreai.manage']);
        $p = $this->makeProvider(['key' => 'pv']);
        $m = $this->makeModel($p, ['capabilities_json' => ['text_generation']]);

        $res = $this->putJson("/api/v1/admin/core-ai/models/{$m->id}", [
            'capabilities' => ['text_generation', 'vision'],
        ], $this->token($u));

        $res->assertOk();
        $this->assertSame(['text_generation', 'vision'], $res->json('data.capabilities'));
        $this->assertSame(['text_generation', 'vision'], $m->fresh()->capabilities());

        // Mảng rỗng = "Chưa có capabilities" — được phép lưu.
        $empty = $this->putJson("/api/v1/admin/core-ai/models/{$m->id}", [
            'capabilities' => [],
        ], $this->token($u));
        $empty->assertOk();
        $this->assertSame([], $m->fresh()->capabilities());

        // capability lạ -> 422.
        $bad = $this->putJson("/api/v1/admin/core-ai/models/{$m->id}", [
            'capabilities' => ['bogus'],
        ], $this->token($u));
        $bad->assertStatus(422)->assertJsonValidationErrors('capabilities.0');
    }
}
