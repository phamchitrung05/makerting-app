<?php

namespace Modules\CoreAi\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Services\CredentialVault;
use Modules\CoreAi\Contracts\AiProviderDriverContract;
use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\DTOs\AiImageRequest;
use Modules\CoreAi\DTOs\AiImageResponse;
use Modules\CoreAi\DTOs\AiResolvedModelDto;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\CoreAi\DTOs\AiStructuredResponse;
use Modules\CoreAi\Exceptions\AiGatewayException;
use Modules\CoreAi\Models\AiModel;
use Modules\CoreAi\Models\AiProvider;
use Modules\CoreAi\Models\AiRun;
use Modules\CoreAi\Registries\AiDriverRegistry;
use Modules\CoreAi\Services\CoreAiGateway;
use Modules\CoreAi\Services\ModelResolver;
use Modules\TaskCenter\Models\AiUsageLog;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Feature test ModelResolver + CoreAiGateway.
 * =====================================================================
 * Kiểm chứng: resolve model theo capability + tôn trọng hint provider/
 * model, lỗi khi thiếu model/key, và pipeline gateway ghi AiRun/AiRunStep/
 * AiUsageLog đúng (driver được fake — không gọi HTTP thật).
 * =====================================================================
 */
class CoreAiGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function vault(): CredentialVault
    {
        return app(CredentialVault::class);
    }

    /**
     * Tạo provider với api key đã mã hóa + N key phụ (cho rotation).
     */
    private function provider(string $key = 'openai', array $extraKeys = [], string $driver = 'fake'): AiProvider
    {
        return AiProvider::create([
            'key' => $key,
            'name' => 'Provider '.$key,
            'kind' => AiProvider::KIND_OFFICIAL,
            'driver' => $driver,
            'base_url' => 'https://api.example.test/v1',
            'api_key_encrypted' => $this->vault()->encrypt('main-key-'.$key),
            'extra_keys_json' => array_map(
                fn (string $k) => $this->vault()->encrypt($k),
                $extraKeys
            ),
            'request_timeout' => 60,
            'is_active' => true,
        ]);
    }

    private function model(AiProvider $p, string $remoteId, array $caps, array $pricing = []): AiModel
    {
        return AiModel::create([
            'ai_provider_id' => $p->id,
            'remote_model_id' => $remoteId,
            'label' => $remoteId,
            'capabilities_json' => $caps,
            'pricing_json' => $pricing === [] ? null : $pricing,
            'is_enabled' => true,
            'is_available' => true,
        ]);
    }

    /* =================================================================
     * ModelResolver
     * ================================================================= */

    public function test_resolver_chon_model_theo_capability(): void
    {
        $p = $this->provider();
        $this->model($p, 'cheap-model', [AiModel::CAP_TEXT_GENERATION], ['input_per_mtok' => 1]);
        $this->model($p, 'expensive', [AiModel::CAP_TEXT_GENERATION]);

        $resolved = app(ModelResolver::class)->resolve(AiModel::CAP_TEXT_GENERATION);

        $this->assertSame($p->id, $resolved->provider->id);
        // Model có pricing rẻ được ưu tiên trước.
        $this->assertSame('cheap-model', $resolved->model->remote_model_id);
        $this->assertSame('main-key-'.$p->key, $resolved->apiKey);
        $this->assertSame(0, $resolved->keyIndex);
    }

    public function test_resolver_ton_trong_hint_provider_va_model(): void
    {
        $pA = $this->provider('prov-a');
        $pB = $this->provider('prov-b');
        $this->model($pA, 'm1', [AiModel::CAP_TEXT_GENERATION]);
        $mB = $this->model($pB, 'target', [AiModel::CAP_TEXT_GENERATION]);

        $resolved = app(ModelResolver::class)->resolve(
            AiModel::CAP_TEXT_GENERATION,
            providerKey: 'prov-b',
            modelId: 'target',
        );

        $this->assertSame($mB->id, $resolved->model->id);
        $this->assertSame('prov-b', $resolved->provider->key);
    }

    public function test_resolver_loc_theo_capability(): void
    {
        $p = $this->provider();
        $this->model($p, 'text-only', [AiModel::CAP_TEXT_GENERATION]);
        $this->model($p, 'vision', [AiModel::CAP_VISION]);

        $resolved = app(ModelResolver::class)->resolve(AiModel::CAP_VISION);
        $this->assertSame('vision', $resolved->model->remote_model_id);
    }

    public function test_resolver_bo_qua_model_disabled_va_provider_inactive(): void
    {
        $pInactive = AiProvider::create([
            'key' => 'inactive', 'name' => 'x', 'kind' => 'official', 'driver' => 'fake',
            'base_url' => 'https://x', 'api_key_encrypted' => $this->vault()->encrypt('k'),
            'is_active' => false,
        ]);
        $this->model($pInactive, 'dead', [AiModel::CAP_TEXT_GENERATION]);

        $pActive = $this->provider('active');
        $disabled = $this->model($pActive, 'off', [AiModel::CAP_TEXT_GENERATION]);
        $disabled->update(['is_enabled' => false]);

        $this->expectException(AiGatewayException::class);
        app(ModelResolver::class)->resolve(AiModel::CAP_TEXT_GENERATION);
    }

    public function test_resolver_loi_khi_khong_co_model(): void
    {
        $this->expectException(AiGatewayException::class);
        $this->expectExceptionMessageMatches('/không có model khả dụng/');
        app(ModelResolver::class)->resolve(AiModel::CAP_IMAGE_GENERATION);
    }

    public function test_resolver_loi_auth_khi_provider_khong_co_key(): void
    {
        $p = AiProvider::create([
            'key' => 'no-key', 'name' => 'x', 'kind' => 'official', 'driver' => 'fake',
            'base_url' => 'https://x', 'api_key_encrypted' => $this->vault()->encrypt(''),
            'is_active' => true,
        ]);
        // api_key_encrypted của '' → decrypt '' → '' → allKeys rỗng.
        $this->model($p, 'm', [AiModel::CAP_TEXT_GENERATION]);

        $this->expectException(AiGatewayException::class);
        app(ModelResolver::class)->resolve(AiModel::CAP_TEXT_GENERATION);
    }

    /* =================================================================
     * CoreAiGateway — pipeline + AiRun tracking (driver fake)
     * ================================================================= */

    /**
     * Đăng ký fake driver trả response định sẵn — không HTTP.
     */
    private function fakeDriver(callable $onStructured): void
    {
        $registry = app(AiDriverRegistry::class);
        $registry->register('fake', fn () => new class($onStructured) implements AiProviderDriverContract {
            public function __construct(private $fn) {}
            public function key(): string { return 'fake'; }
            public function supports(string $operation): bool { return in_array($operation, ['structured', 'image', 'speech', 'transcribe'], true); }
            public function test(AiResolvedModelDto $r): array { return ['ok' => true, 'message' => 'fake']; }
            public function testModel(AiResolvedModelDto $r): array { return ['ok' => true, 'latency_ms' => 1, 'message' => 'fake model', 'model_id' => $r->model->remote_model_id]; }
            public function structured(AiResolvedModelDto $r, AiStructuredRequest $req): AiStructuredResponse
            {
                return ($this->fn)($r, $req);
            }
            public function image(AiResolvedModelDto $r, AiImageRequest $req): AiImageResponse
            {
                return new AiImageResponse(images: [['mime' => 'image/png', 'data' => 'BIN']]);
            }
            public function speech(AiResolvedModelDto $r, \Modules\CoreAi\DTOs\AiSpeechRequest $req): \Modules\CoreAi\DTOs\AiSpeechResponse
            {
                return new \Modules\CoreAi\DTOs\AiSpeechResponse(audio: 'AUD', mime: 'audio/mpeg');
            }
            public function transcribe(AiResolvedModelDto $r, \Modules\CoreAi\DTOs\AiTranscribeRequest $req): \Modules\CoreAi\DTOs\AiTranscribeResponse
            {
                return new \Modules\CoreAi\DTOs\AiTranscribeResponse(text: 'xin chào');
            }
        });
    }

    private function gateway(): CoreAiGateway
    {
        return app(CoreAiGatewayContract::class);
    }

    public function test_gateway_structured_ghi_run_step_usage(): void
    {
        $p = $this->provider();
        $this->model($p, 'gpt-test', [AiModel::CAP_STRUCTURED_OUTPUT], ['input_per_mtok' => 2, 'output_per_mtok' => 4]);

        $this->fakeDriver(fn ($r, $req) => new AiStructuredResponse(
            text: '{"a":1}',
            data: ['a' => 1],
            usage: ['input_tokens' => 100, 'output_tokens' => 50],
            remoteModelId: 'gpt-test',
            providerKey: $r->provider->key,
            latencyMs: 12,
        ));

        $res = $this->gateway()->executeStructured(
            AiStructuredRequest::fromPrompt('hi', schema: ['type' => 'object'], module: 'blog', operation: 'write')
        );

        $this->assertTrue($res->hasStructuredData());

        // AiRun được markReady với connection_snapshot không chứa key.
        $run = $this->gateway()->lastRun();
        $this->assertNotNull($run);
        $this->assertSame(AiRun::STATUS_READY, $run->fresh()->status);
        $this->assertSame(100, $run->fresh()->progress);
        $this->assertSame('blog', $run->module);
        $this->assertSame('write', $run->operation);
        $snap = $run->connection_snapshot_json;
        $this->assertSame('gpt-test', $snap['remote_model_id']);
        $this->assertArrayNotHasKey('api_key_encrypted', $snap);
        $this->assertArrayNotHasKey('apiKey', $snap);

        // AiRunStep ready.
        $step = $run->steps()->first();
        $this->assertSame('structured', $step->step_key);
        $this->assertSame('ready', $step->status);

        // AiUsageLog ghi cost ước tính.
        $usage = AiUsageLog::where('ai_run_id', $run->id)->first();
        $this->assertNotNull($usage);
        $this->assertSame('gpt-test', $usage->model);
        $this->assertSame(100, (int) $usage->input_units);
        $this->assertSame(50, (int) $usage->output_units);
        // cost = (2*100 + 4*50)/1e6 = 0.0004
        $this->assertEqualsWithDelta(0.0004, (float) $usage->estimated_cost_usd, 0.000001);
    }

    public function test_gateway_mark_failed_khi_driver_loi(): void
    {
        $p = $this->provider();
        $this->model($p, 'm', [AiModel::CAP_STRUCTURED_OUTPUT]);

        $this->fakeDriver(function () {
            throw AiGatewayException::rateLimit('qua RPM', ['x' => 1]);
        });

        try {
            $this->gateway()->executeStructured(
                AiStructuredRequest::fromPrompt('hi', schema: ['type' => 'object'])
            );
            $this->fail('phải ném AiGatewayException');
        } catch (AiGatewayException $e) {
            $this->assertSame('rate_limit', $e->errorCode());
        }

        $run = $this->gateway()->lastRun();
        $this->assertSame(AiRun::STATUS_FAILED, $run->fresh()->status);
        $this->assertSame('rate_limit', $run->fresh()->error_code);
        $this->assertSame('failed', $run->steps()->first()->status);
    }

    public function test_gateway_nem_loi_unsupported_khi_khong_co_model(): void
    {
        // Không seed model nào -> resolve ném unsupported trước khi gọi driver.
        $this->fakeDriver(fn () => new AiStructuredResponse(text: 'x'));

        $this->expectException(AiGatewayException::class);
        try {
            $this->gateway()->executeStructured(AiStructuredRequest::fromPrompt('hi', schema: ['type' => 'object']));
        } finally {
            $run = $this->gateway()->lastRun();
            $this->assertSame(AiRun::STATUS_FAILED, $run->fresh()->status);
        }
    }

    public function test_gateway_dung_hint_modelId(): void
    {
        $pA = $this->provider('pa');
        $pB = $this->provider('pb');
        $this->model($pA, 'first', [AiModel::CAP_TEXT_GENERATION]);
        $this->model($pB, 'chosen', [AiModel::CAP_TEXT_GENERATION]);

        $captured = null;
        $this->fakeDriver(function ($r, $req) use (&$captured) {
            $captured = $r->model->remote_model_id;
            return new AiStructuredResponse(text: 'ok', remoteModelId: $r->model->remote_model_id);
        });

        // Không schema -> text_generation, hint chọn model 'chosen' của pb.
        $req = new AiStructuredRequest(messages: [['role' => 'user', 'content' => 'x']], providerKey: 'pb', modelId: 'chosen');
        $this->gateway()->executeStructured($req);

        $this->assertSame('chosen', $captured);
        $this->assertSame('pb', $this->gateway()->lastRun()->connection_snapshot_json['provider_key']);
    }
}
