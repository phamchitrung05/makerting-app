<?php

namespace Modules\CoreAi\Drivers;

use Modules\CoreAi\Contracts\AiProviderDriverContract;
use Modules\CoreAi\DTOs\AiResolvedModelDto;
use Modules\CoreAi\Exceptions\AiGatewayException;
use Modules\CoreAi\Models\AiProvider;
use Modules\CoreAi\Services\AiProviderClient;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Lớp trừu tượng nền cho mọi driver nhà cung cấp AI.
 * =====================================================================
 *
 * Gom phần chung của các driver cụ thể (OpenAI/Anthropic/Gemini...):
 * - Vòng lặp xoay vòng API key (`withKeyRotation`): thử key hiện tại, nếu
 *   401/403/429 và còn key phụ thì tăng `keyIndex` rồi gọi lại callable —
 *   caller chỉ viết logic 1 lần, key phụ tự động thay khi hết RPM/quota.
 * - `url()` ghép base_url + endpoint an toàn (khử trùng slash).
 * - `client()` resolve AiProviderClient từ container.
 * - `headers()` build auth headers theo chuẩn của từng driver.
 * - `unsupported()` ném exception chuẩn cho op driver không làm được.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - withKeyRotation() : bọc callable với retry theo key phụ.
 * - url()             : ghép endpoint vào base_url của provider.
 * - client()          : resolve AiProviderClient.
 * - headers()         : headers mặc định của driver (override ở con).
 * - authScheme()      : header auth tương ứng driver (override ở con).
 * - unsupported()     : ném AiGatewayException::unsupported.
 * - ops()             : mảng op driver hỗ trợ (override ở con).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : AiResolvedModelDto + Request DTO.
 * - OUTPUT: Response DTO — con cụ thể implement.
 * =====================================================================
 */
abstract class AbstractAiDriver implements AiProviderDriverContract
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Mảng operation driver hỗ trợ — con override.
     * INPUT: không. OUTPUT: string[] subset of [structured,image,speech,transcribe].
     * =====================================================================
     */
    abstract protected function ops(): array;

    /**
     * =====================================================================
     * CHỨC NĂNG: Header auth + scheme của driver — con override.
     * INPUT: apiKey plaintext. OUTPUT: string[] headers ('Name: value').
     * =====================================================================
     */
    abstract protected function headers(string $apiKey): array;

    /** INPUT: không. OUTPUT: bool driver hỗ trợ op cho trước. */
    public function supports(string $operation): bool
    {
        return in_array($operation, $this->ops(), true);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Resolve AiProviderClient từ container.
     * INPUT: không. OUTPUT: AiProviderClient.
     * =====================================================================
     */
    protected function client(): AiProviderClient
    {
        return app(AiProviderClient::class);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ghép endpoint vào base_url của provider (khử trùng slash).
     * =====================================================================
     * INPUT: AiProvider + endpoint (có thể bắt đầu '/' hoặc không).
     * OUTPUT: string URL đầy đủ.
     * =====================================================================
     */
    protected function url(AiProvider $provider, string $endpoint): string
    {
        return rtrim($provider->base_url, '/') . '/' . ltrim($endpoint, '/');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Bọc callable với vòng lặp xoay vòng API key.
     * =====================================================================
     * Khi callable ném AiGatewayException có errorCode 'auth'|'rate_limit'
     * và provider còn key phụ (extra_keys_json) chưa thử → tăng keyIndex
     * rồi gọi lại. Giới hạn bởi số key thực có trong provider->allKeys().
     *
     * INPUT :
     *   - AiResolvedModelDto $resolved — provider+model+keyIndex hiện tại.
     *   - callable(AiResolvedModelDto):T $fn — logic gọi HTTP thực tế,
     *     nhận resolved mới (keyIndex đã đổi + apiKey tương ứng).
     *   - bool $retryAuth — có retry khi auth fail không (mặc định false —
     *     key sai thì key phụ khả năng cũng sai; chỉ retry rate_limit/quota).
     *
     * OUTPUT: kết quả của callable thành công cuối cùng.
     * EXCEPTION: AiGatewayException cuối cùng nếu hết key mà vẫn lỗi.
     * =====================================================================
     */
    protected function withKeyRotation(AiResolvedModelDto $resolved, callable $fn, bool $retryAuth = false): mixed
    {
        $client = $this->client();
        $maxIdx = $client->maxKeyIndex($resolved->provider);
        $idx = $resolved->keyIndex;
        $last = null;

        while ($idx <= $maxIdx) {
            try {
                // resolved mới với keyIndex + apiKey tương ứng lần thử này.
                $attempt = new AiResolvedModelDto(
                    provider: $resolved->provider,
                    model: $resolved->model,
                    apiKey: $client->keyAt($resolved->provider, $idx) ?? $resolved->apiKey,
                    keyIndex: $idx,
                );

                return $fn($attempt);
            } catch (AiGatewayException $e) {
                $last = $e;
                $code = $e->errorCode();
                $shouldRotate = $code === 'rate_limit' || ($retryAuth && $code === 'auth');
                if (! $shouldRotate || $idx >= $maxIdx) {
                    throw $e;
                }
                $idx++;
            }
        }

        throw $last ?? AiGatewayException::providerError('het key thu', ['provider' => $resolved->provider->key]);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ném exception cho operation driver không hỗ trợ.
     * =====================================================================
     * INPUT: operation string. OUTPUT: không trả về.
     * EXCEPTION: AiGatewayException::unsupported.
     * =====================================================================
     */
    protected function unsupported(string $operation): never
    {
        throw AiGatewayException::unsupported(
            "driver '{$this->key()}' không hỗ trợ '{$operation}'",
            ['driver' => $this->key(), 'operation' => $operation]
        );
    }
}