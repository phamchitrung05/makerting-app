<?php

namespace Modules\CoreAi\Tests\Unit;

use Modules\Core\DTOs\RemoteFetchResultDto;
use Modules\CoreAi\Drivers\XpikiDriver;
use Modules\CoreAi\DTOs\AiImageRequest;
use Modules\CoreAi\DTOs\AiResolvedModelDto;
use Modules\CoreAi\Exceptions\AiGatewayException;
use Modules\CoreAi\Models\AiModel;
use Modules\CoreAi\Models\AiProvider;
use Modules\CoreAi\Services\AiProviderClient;
use Tests\TestCase;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Unit test xử lý lỗi async task của XpikiDriver::waitTask()
 * =====================================================================
 *
 * Đảm bảo khi XPIKI trả về task trạng thái thất bại, driver trích xuất
 * đúng `error_message` (không mất ngữ cảnh thành mỗi chữ `failed`) và
 * phân loại đúng `error_code` sang `AiGatewayException::rateLimit()` /
 * `auth()` / `providerError()`.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - test_wait_task_maps_upstream_rate_limited_and_preserves_error_message()
 * - test_wait_task_maps_auth_error_code()
 * - test_wait_task_falls_back_to_error_field_and_provider_error()
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Fake AiProviderClient trả task_id + payload trạng thái failed.
 * - OUTPUT: Assertions trên AiGatewayException ném ra; không gọi HTTP/DB thật.
 * =====================================================================
 */
class XpikiWaitTaskErrorTest extends TestCase
{
    /**
     * CHỨC NĂNG: Gắn fake AiProviderClient trả POST task và GET trạng thái lỗi.
     * INPUT: taskId (string), taskPayload (array JSON trả về khi poll GET).
     * OUTPUT: void.
     * SIDE EFFECT: Ghi đè binding AiProviderClient trong service container của test.
     */
    private function bindFakeClient(string $taskId, array $taskPayload): void
    {
        $fakeClient = new class($taskId, $taskPayload) extends AiProviderClient
        {
            public function __construct(
                private readonly string $taskId,
                private readonly array $taskPayload,
            ) {}

            public function request(
                AiProvider $provider,
                string $method,
                string $url,
                array $headers = [],
                mixed $body = null,
                array $options = [],
            ): RemoteFetchResultDto {
                if (strtoupper($method) === 'POST') {
                    return new RemoteFetchResultDto(
                        status: 200,
                        body: json_encode(['task_id' => $this->taskId, 'status' => 'queued']),
                        headers: [],
                        finalUrl: $url,
                    );
                }

                return new RemoteFetchResultDto(
                    status: 200,
                    body: json_encode($this->taskPayload),
                    headers: [],
                    finalUrl: $url,
                );
            }
        };

        $this->app->instance(AiProviderClient::class, $fakeClient);
    }

    /**
     * CHỨC NĂNG: Tạo DTO provider + model XPIKI trong bộ nhớ (không chạm DB).
     * INPUT: không.
     * OUTPUT: AiResolvedModelDto.
     */
    private function makeResolvedDto(): AiResolvedModelDto
    {
        $provider = new AiProvider([
            'key' => 'xpiki',
            'name' => 'XPIKI',
            'kind' => AiProvider::KIND_GATEWAY,
            'driver' => AiProvider::DRIVER_XPIKI,
            'base_url' => 'https://api.xpiki.com/v1',
            'request_timeout' => 30,
            'is_active' => true,
        ]);

        $model = new AiModel([
            'remote_model_id' => 'seedream-4-5-image',
            'label' => 'seedream-4-5-image',
            'capabilities_json' => [AiModel::CAP_IMAGE_GENERATION],
            'is_enabled' => true,
            'is_available' => true,
        ]);

        return new AiResolvedModelDto($provider, $model, 'sk-test-xpiki', 0);
    }

    /**
     * CHỨC NĂNG: Kiểm chứng UPSTREAM_RATE_LIMITED map sang rate_limit + giữ error_message.
     * INPUT: Mock task status=failed, error_code=UPSTREAM_RATE_LIMITED.
     * OUTPUT: void.
     */
    public function test_wait_task_maps_upstream_rate_limited_and_preserves_error_message(): void
    {
        $this->bindFakeClient('gen_rate_1', [
            'task_id' => 'gen_rate_1',
            'status' => 'failed',
            'error_code' => 'UPSTREAM_RATE_LIMITED',
            'error_message' => 'Rate limited by the upstream provider.',
        ]);

        try {
            $this->app->make(XpikiDriver::class)->image(
                $this->makeResolvedDto(),
                new AiImageRequest(prompt: 'A serene teacup', aspectRatio: '1:1', module: 'media')
            );
            $this->fail('Kỳ vọng XpikiDriver ném AiGatewayException khi task failed.');
        } catch (AiGatewayException $e) {
            $this->assertSame('rate_limit', $e->errorCode());
            $this->assertStringContainsString('Rate limited by the upstream provider.', $e->getMessage());
            $this->assertSame(['task' => 'gen_rate_1', 'error_code' => 'UPSTREAM_RATE_LIMITED'], $e->context());
        }
    }

    /**
     * CHỨC NĂNG: Kiểm chứng lỗi UNAUTHORIZED map sang auth + giữ error_message.
     * INPUT: Mock task status=failed, error_code=UNAUTHORIZED.
     * OUTPUT: void.
     */
    public function test_wait_task_maps_auth_error_code(): void
    {
        $this->bindFakeClient('gen_auth_1', [
            'task_id' => 'gen_auth_1',
            'status' => 'failed',
            'error_code' => 'UNAUTHORIZED',
            'error_message' => 'Invalid upstream token',
        ]);

        try {
            $this->app->make(XpikiDriver::class)->image(
                $this->makeResolvedDto(),
                new AiImageRequest(prompt: 'A serene teacup', aspectRatio: '1:1', module: 'media')
            );
            $this->fail('Kỳ vọng XpikiDriver ném AiGatewayException khi task unauthorized.');
        } catch (AiGatewayException $e) {
            $this->assertSame('auth', $e->errorCode());
            $this->assertStringContainsString('Invalid upstream token', $e->getMessage());
            $this->assertSame(['task' => 'gen_auth_1', 'error_code' => 'UNAUTHORIZED'], $e->context());
        }
    }

    /**
     * CHỨC NĂNG: Fallback về field error/status và provider_error khi thiếu error_message.
     * INPUT: Mock task status=failed, error="safety blocked", không có error_code.
     * OUTPUT: void.
     */
    public function test_wait_task_falls_back_to_error_field_and_provider_error(): void
    {
        $this->bindFakeClient('gen_err_1', [
            'task_id' => 'gen_err_1',
            'status' => 'failed',
            'error' => 'safety blocked',
        ]);

        try {
            $this->app->make(XpikiDriver::class)->image(
                $this->makeResolvedDto(),
                new AiImageRequest(prompt: 'A serene teacup', aspectRatio: '1:1', module: 'media')
            );
            $this->fail('Kỳ vọng XpikiDriver ném AiGatewayException khi task error.');
        } catch (AiGatewayException $e) {
            $this->assertSame('provider_error', $e->errorCode());
            $this->assertStringContainsString('safety blocked', $e->getMessage());
            $this->assertSame(['task' => 'gen_err_1'], $e->context());
        }
    }
}
