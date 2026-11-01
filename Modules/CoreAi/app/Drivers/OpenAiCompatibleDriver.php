<?php

namespace Modules\CoreAi\Drivers;

use Modules\CoreAi\DTOs\AiResolvedModelDto;
use Modules\CoreAi\Exceptions\AiGatewayException;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Driver cho các cổng nói chuẩn OpenAI API.
 * =====================================================================
 *
 * Kế thừa `OpenAiDriver` — dùng chung payload /chat/completions,
 * /images/generations, /audio/*. Khác biệt:
 * - `key()` trả 'openai-compatible'.
 * - `test()` thử GET {base}/models; 404 vẫn coi ok.
 * - `headers()` bỏ Authorization khi apiKey rỗng (Ollama/LM Studio).
 *
 * Phù hợp: Ollama, LM Studio, vLLM, LiteLLM, OpenRouter, Together,
 * Groq, Mistral-compatible, Azure OpenAI-compatible gateway...
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : AiResolvedModelDto + Request DTO (như OpenAiDriver).
 * - OUTPUT: Response DTO chuẩn.
 * =====================================================================
 */
class OpenAiCompatibleDriver extends OpenAiDriver
{
    /** INPUT: không. OUTPUT: 'openai-compatible'. */
    public function key(): string
    {
        return 'openai-compatible';
    }

    /** INPUT: apiKey (có thể rỗng). OUTPUT: headers — bỏ auth khi không key. */
    protected function headers(string $apiKey): array
    {
        if ($apiKey === '') {
            return [];
        }

        return $this->client()->buildAuthHeaders('Authorization', $apiKey, 'Bearer');
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Ping — GET {base}/models; 404 coi như ok.
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
                $this->url($resolved->provider, 'models'),
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

            if ($e->errorCode() === 'invalid_request') {
                return [
                    'ok' => true,
                    'latency_ms' => $ms,
                    'message' => 'Kết nối được (endpoint /models không khả dụng)',
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