<?php

namespace Modules\CoreAi\Contracts;

use Modules\CoreAi\DTOs\AiImageRequest;
use Modules\CoreAi\DTOs\AiImageResponse;
use Modules\CoreAi\DTOs\AiResolvedModelDto;
use Modules\CoreAi\DTOs\AiSpeechRequest;
use Modules\CoreAi\DTOs\AiSpeechResponse;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\CoreAi\DTOs\AiStructuredResponse;
use Modules\CoreAi\DTOs\AiTranscribeRequest;
use Modules\CoreAi\DTOs\AiTranscribeResponse;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Hợp đồng một driver kết nối nhà cung cấp AI.
 * =====================================================================
 *
 * Mỗi driver biết cách dịch DTO chuẩn (`AiStructuredRequest`...) sang
 * request HTTP riêng của provider (OpenAI/Anthropic/Gemini...) và ngược
 * lại response HTTP về DTO chuẩn. Gateway gọi qua contract — không quan
 * tâm provider là ai.
 *
 * Driver KHÔNG tự resolve key — nhận `AiResolvedModelDto` (provider+model
 * +apiKey đã giải mã +keyIndex) từ `CoreAiGateway`/`ModelResolver`.
 *
 * Operation không hỗ trợ → ném `AiGatewayException::unsupported()` (VD:
 * Anthropic không có image_generation/tts/stt).
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - key()          : slug driver ('openai','anthropic','gemini',...).
 * - supports()     : kiểm driver có hỗ trợ operation (structured|image|
 *                    speech|transcribe) không.
 * - test()         : ping kết nối + key — trả ['ok'=>bool,'latency_ms'=>,
 *                    'message'=>,'models'=>[]?] cho nút Test Ping.
 * - structured()   : Text/Vision/JSON Schema → AiStructuredResponse.
 * - image()        : sinh ảnh → AiImageResponse.
 * - speech()       : TTS → AiSpeechResponse.
 * - transcribe()   : STT → AiTranscribeResponse.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : AiResolvedModelDto + Request DTO.
 * - OUTPUT: Response DTO / test result array; AiGatewayException khi lỗi.
 * =====================================================================
 */
interface AiProviderDriverContract
{
    /** Slug định danh driver (khớp ai_providers.driver). */
    public function key(): string;

    /** Kiểm driver hỗ trợ operation: structured|image|speech|transcribe. */
    public function supports(string $operation): bool;

    /**
     * =====================================================================
     * CHỨC NĂNG: Test kết nối + key (ping nhẹ — thường GET models).
     * =====================================================================
     * INPUT: resolved (provider+model+apiKey+keyIndex).
     * OUTPUT: array{ok:bool, latency_ms:int, message:string, models?:array}.
     * EXCEPTION: không ném — trả ok=false + message lỗi để UI hiển thị.
     * =====================================================================
     */
    public function test(AiResolvedModelDto $resolved): array;

    /** Text/Vision/Structured → AiStructuredResponse. */
    public function structured(AiResolvedModelDto $resolved, AiStructuredRequest $request): AiStructuredResponse;

    /** Sinh ảnh → AiImageResponse. */
    public function image(AiResolvedModelDto $resolved, AiImageRequest $request): AiImageResponse;

    /** TTS → AiSpeechResponse. */
    public function speech(AiResolvedModelDto $resolved, AiSpeechRequest $request): AiSpeechResponse;

    /** STT → AiTranscribeResponse. */
    public function transcribe(AiResolvedModelDto $resolved, AiTranscribeRequest $request): AiTranscribeResponse;
}