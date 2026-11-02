<?php

namespace Modules\CoreAi\Drivers;

use Modules\CoreAi\DTOs\AiResolvedModelDto;
use Modules\CoreAi\Exceptions\AiGatewayException;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Driver cho các cổng nói chuẩn Anthropic API.
 * =====================================================================
 *
 * Kế thừa `AnthropicDriver` — dùng chung /v1/messages + tool_use.
 * Khác biệt:
 * - `key()` trả 'anthropic-compatible'.
 * - `test()` thử GET {base}/v1/models; 404 vẫn coi ok.
 * - `headers()` bỏ x-api-key khi apiKey rỗng (self-host).
 *
 * Phù hợp: LiteLLM /v1/messages, Bedrock Anthropic, OpenRouter
 * /anthropic/v1/messages, Azure Anthropic, proxy self-host...
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : AiResolvedModelDto + Request DTO (như AnthropicDriver).
 * - OUTPUT: Response DTO chuẩn.
 * =====================================================================
 */
class AnthropicCompatibleDriver extends AnthropicDriver
{
    /** INPUT: không. OUTPUT: 'anthropic-compatible'. */
    public function key(): string
    {
        return 'anthropic-compatible';
    }

    /** INPUT: apiKey (có thể rỗng). OUTPUT: headers — bỏ auth khi không key. */
    protected function headers(string $apiKey): array
    {
        $extra = [
            'anthropic-version' => '2023-06-01',
            'anthropic-beta' => 'tools-2024-05-16',
        ];
        if ($apiKey === '') {
            $h = [];
            foreach ($extra as $n => $v) {
                $h[] = $n . ': ' . $v;
            }
            return $h;
        }

        return $this->client()->buildAuthHeaders('x-api-key', $apiKey, '', $extra);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ping — GET {base}/v1/models; 404 coi như ok.
     * =====================================================================
     * INPUT: resolved. OUTPUT: array{ok,latency_ms,message,models?}.
     * =====================================================================
     */
    public function test(AiResolvedModelDto $resolved): array
    {
        $start = microtime(true);
        try {
            $res = $this->client()->request(
                $resolved->provider,
                'GET',
                $this->url($resolved->provider, 'v1/models'),
                $this->headers($resolved->apiKey),
                null,
                ['timeout' => 15]
            );
            $data = json_decode($res->body, true);
            $count = is_array($data['data'] ?? null) ? count($data['data']) : null;
            $ms = (int) round((microtime(true) - $start) * 1000);

            return [
                'ok' => true,
                'latency_ms' => $ms,
                'message' => 'Kết nối thành công' . ($count !== null ? " — {$count} models" : ''),
                'models' => is_array($data['data'] ?? null) ? array_column($data['data'], 'id') : [],
            ];
        } catch (AiGatewayException $e) {
            $ms = (int) round((microtime(true) - $start) * 1000);

            // Chỉ tha 404 — 400/422 là lỗi thật.
            if ($e->errorCode() === 'not_found') {
                return [
                    'ok' => true,
                    'latency_ms' => $ms,
                    'message' => 'Kết nối được (endpoint /v1/models không khả dụng)',
                    'models' => [],
                ];
            }

            return [
                'ok' => false,
                'latency_ms' => $ms,
                'message' => $e->getMessage(),
                'error_code' => $e->errorCode(),
            ];
        }
    }
}