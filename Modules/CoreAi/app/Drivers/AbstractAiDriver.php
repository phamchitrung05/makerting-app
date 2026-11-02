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
        $base = rtrim($provider->base_url, '/');
        $ep = ltrim($endpoint, '/');

        // Khử trùng version segment: nếu base_url đã kết thúc bằng cùng một
        // segment đầu của endpoint (v1, v1beta, v2, ...) thì bỏ segment trùng
        // ở endpoint. VD base 'https://x/v1' + 'v1/models' -> 'https://x/v1/models'
        // (không phải 'https://x/v1/v1/models'). Cho phép admin nhập origin
        // hoặc origin+version đều đúng.
        $baseSegs = explode('/', $base);
        $epSegs = explode('/', $ep);
        $lastBase = strtolower((string) end($baseSegs));
        $firstEp = strtolower((string) ($epSegs[0] ?? ''));

        if ($lastBase !== '' && $lastBase === $firstEp && preg_match('/^v\d+[a-z]*$/i', $firstEp)) {
            array_shift($epSegs);
            $ep = implode('/', $epSegs);
        }

        return $base . '/' . $ep;
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
     * CHỨC NĂNG: Deep-ping một model — gọi generate tối thiểu (không chỉ /models).
     * =====================================================================
     * Dùng cho nút "Test ping" trên TỪNG model ở UI: chứng minh endpoint
     * generate thực sự chạy được ở base_url hiện tại (phát hiện path sai
     * như /v2/22 — chỗ /models vẫn pass nhưng chat không tồn tại).
     *
     * Mặc định gọi `structured()` với 1 user message "ping" + max_tokens nhỏ;
     * driver nào không supports 'structured' (ví dụ chỉ image/stt) override
     * `pingOp()` để chọn op phù hợp. Trả array chuẩn {ok,latency_ms,message}.
     *
     * INPUT: resolved. OUTPUT: array{ok,latency_ms,message,error_code?}.
     * SIDE EFFECT: gọi HTTP thật — tốn ~1 vài token.
     * =====================================================================
     */
    public function testModel(AiResolvedModelDto $resolved): array
    {
        $start = microtime(true);
        $modelId = (string) $resolved->model->remote_model_id;
        $caps = $resolved->model->capabilities();

        try {
            // Phương án A (gộp 1 request): text/structured/vision (hoặc caps
            // rỗng mới sync về) -> 1 structured() duy nhất kèm fixture gộp;
            // model chuyên biệt image/tts/stt -> op riêng với fixture mẫu.
            $result = $this->probeByCaps($caps, $resolved);
            $ms = (int) round((microtime(true) - $start) * 1000);

            // Model Identity Check — chống proxy tự ý đổi sang model khác họ
            // (ví dụ gửi gpt-6.1-sol bằng key deepseek nhưng proxy trả
            // deepseek-v4-1-flash). Chỉ so khi response trả tên model thật.
            $actual = $this->extractResponseModel($result);
            if ($actual !== null && ! $this->sameModelFamily($modelId, $actual)) {
                return [
                    'ok' => false,
                    'latency_ms' => $ms,
                    'message' => "Key hiện tại không hỗ trợ model '{$modelId}' (provider tự chuyển hướng sang '{$actual}')",
                    'error_code' => 'model_mismatch',
                    'requested_model' => $modelId,
                    'actual_model' => $actual,
                    'model_id' => $resolved->model->remote_model_id,
                ];
            }

            return [
                'ok' => true,
                'latency_ms' => $ms,
                'message' => 'Model phản hồi được',
                'model_id' => $resolved->model->remote_model_id,
            ];
        } catch (\Throwable $e) {
            $ms = (int) round((microtime(true) - $start) * 1000);

            return [
                'ok' => false,
                'latency_ms' => $ms,
                'message' => $e->getMessage(),
                'error_code' => $e instanceof \Modules\CoreAi\Exceptions\AiGatewayException ? $e->errorCode() : 'provider_error',
            ];
        }
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chọn op + gọi 1 request probe theo capability (Phương án A).
     * =====================================================================
     * - caps chứa text/structured/vision HOẶC rỗng -> 1 structured() gộp
     *   (fixture textRequest đã gắn schema + ảnh 1x1 theo caps).
     * - caps chỉ image/tts/stt -> gọi op tương ứng với fixture riêng.
     * Trả về Response DTO để caller đọc raw['model'] check identity.
     *
     * INPUT: caps[] + resolved. OUTPUT: Response DTO.
     * EXCEPTION: AiGatewayException khi không op nào ping được.
     * =====================================================================
     */
    protected function probeByCaps(array $caps, AiResolvedModelDto $resolved): object
    {
        $modelId = (string) $resolved->model->remote_model_id;

        // Gộp text/structured/vision (hoặc rỗng) -> structured().
        $texty = $caps === []
            || in_array('text_generation', $caps, true)
            || in_array('structured_output', $caps, true)
            || in_array('vision', $caps, true);

        if ($texty && $this->supports('structured')) {
            return $this->structured($resolved, \Modules\CoreAi\Services\AiModelProbeFixtures::textRequest($caps, $modelId));
        }
        if (in_array('image_generation', $caps, true) && $this->supports('image')) {
            return $this->image($resolved, \Modules\CoreAi\Services\AiModelProbeFixtures::imageRequest($modelId));
        }
        if (in_array('tts', $caps, true) && $this->supports('speech')) {
            return $this->speech($resolved, \Modules\CoreAi\Services\AiModelProbeFixtures::speechRequest($modelId));
        }
        if (in_array('stt', $caps, true) && $this->supports('transcribe')) {
            return $this->transcribe($resolved, \Modules\CoreAi\Services\AiModelProbeFixtures::transcribeRequest($modelId));
        }

        throw AiGatewayException::unsupported(
            "model '{$modelId}' không có capability nào driver hỗ trợ để ping",
            ['model' => $modelId, 'capabilities' => $caps]
        );
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Rút tên model thực tế từ response của provider.
     * =====================================================================
     * OpenAI/Anthropic/Gemini đều trả `model` (hoặc `data.model`) trong JSON;
     * đọc từ raw response. Trả null nếu không tìm thấy (không check được).
     * INPUT: Response DTO. OUTPUT: ?string model id provider thực chạy.
     * =====================================================================
     */
    protected function extractResponseModel(object $response): ?string
    {
        $raw = $response->raw ?? [];
        $m = $raw['model'] ?? $raw['data']['model'] ?? null;

        return is_string($m) && $m !== '' ? $m : null;
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: So model yêu cầu vs model thực trả về — cùng "họ" không.
     * =====================================================================
     * Hợp lệ: trùng khớp, hoặc thực trả bắt đầu bằng prefix họ của model yêu
     * cầu rồi đến hậu tố phiên bản/ngày (ví dụ deepseek-flash -> deepseek-
     * v4-1-flash-261017; gpt-4o -> gpt-4o-2024-08-06). Khác họ -> mismatch.
     *
     * Cách so: lấy "family" = phần đầu đến trước chữ số/dấu '-' đầu tiên của
     * mỗi model id, so lowercase. 'gpt-6.1-sol' family='gpt', 'deepseek-...'
     * family='deepseek' -> khác -> false.
     *
     * INPUT: requested + actual model id. OUTPUT: bool.
     * =====================================================================
     */
    protected function sameModelFamily(string $requested, string $actual): bool
    {
        if ($requested === $actual) {
            return true;
        }
        $fam = static fn (string $id): string => strtolower((string) preg_replace('/[-_\.0-9].*$/', '', $id));

        $rf = $fam($requested);
        $af = $fam($actual);
        if ($rf === $af && $rf !== '') {
            return true;
        }

        // Thực trả bắt đầu bằng requested + hậu tố (gpt-4o -> gpt-4o-2024-08-06).
        return str_starts_with(strtolower($actual), strtolower($requested) . '-')
            || str_starts_with(strtolower($actual), strtolower($requested) . '.');
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