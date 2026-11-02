<?php

namespace Modules\CoreAi\Contracts;

use Modules\CoreAi\DTOs\AiImageRequest;
use Modules\CoreAi\DTOs\AiImageResponse;
use Modules\CoreAi\DTOs\AiSpeechRequest;
use Modules\CoreAi\DTOs\AiSpeechResponse;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\CoreAi\DTOs\AiStructuredResponse;
use Modules\CoreAi\DTOs\AiTranscribeRequest;
use Modules\CoreAi\DTOs\AiTranscribeResponse;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Hợp đồng TRUNG TÂM — cổng gọi AI duy nhất toàn hệ thống.
 * =====================================================================
 *
 * Nguyên tắc kiến trúc (PLAN §Module 8): KHÔNG module nghiệp vụ nào
 * (Blog, VideoStudio, SocialPublisher, YoutubeIntelligence, WritingStyle,
 * VisualStyle...) được tự gọi HTTP tới OpenAI/Gemini/Anthropic/ElevenLabs.
 * Tất cả bắt buộc đi qua interface này.
 *
 * Gateway chịu trách nhiệm: ModelResolver chọn provider+model theo
 * capability, driver gọi HTTP (key rotation, SSRF, timeout), ghi AiRun
 * (+AiRunStep + AiUsageLog) cho audit/billing, và trả DTO chuẩn.
 *
 * Caller chỉ truyền Request DTO (kèm module/operation để truy vết) và
 * nhận Response DTO. Để truy cập AiRun vừa ghi (liên kết provenance),
 * dùng `->run` trên response hoặc `lastRun()`.
 *
 * INPUT/OUTPUT CỦA INTERFACE (tổng thể):
 * - INPUT : Request DTO (messages/prompt/audio + schema/options/module).
 * - OUTPUT: Response DTO chuẩn — driver/HTTP chi tiết bị che hoàn toàn.
 * =====================================================================
 */
interface CoreAiGatewayContract
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Sinh text/JSON/Vision (có kiểm chứng schema khi cần).
     * =====================================================================
     * INPUT: AiStructuredRequest (messages/prompt + ?schema + ?attachments).
     * OUTPUT: AiStructuredResponse — `data` đã parse nếu schema hợp lệ.
     * EXCEPTION: AiGatewayException|AiValidationException.
     * =====================================================================
     */
    public function executeStructured(AiStructuredRequest $req): AiStructuredResponse;

    /**
     * =====================================================================
     * CHỨC NĂNG: Sinh ảnh từ prompt (+negative +aspect +reference DNA).
     * =====================================================================
     * INPUT: AiImageRequest.
     * OUTPUT: AiImageResponse — `images` là binary chưa lưu Media.
     * EXCEPTION: AiGatewayException.
     * =====================================================================
     */
    public function generateImage(AiImageRequest $req): AiImageResponse;

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuyển văn bản → giọng nói (+ mốc thời gian từng từ).
     * =====================================================================
     * INPUT: AiSpeechRequest.
     * OUTPUT: AiSpeechResponse — audio binary + WordTimingDto[].
     * EXCEPTION: AiGatewayException.
     * =====================================================================
     */
    public function synthesizeSpeech(AiSpeechRequest $req): AiSpeechResponse;

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuyển âm thanh → văn bản (+ mốc thời gian).
     * =====================================================================
     * INPUT: AiTranscribeRequest.
     * OUTPUT: AiTranscribeResponse — text + WordTimingDto[] + language.
     * EXCEPTION: AiGatewayException.
     * =====================================================================
     */
    public function transcribeAudio(AiTranscribeRequest $req): AiTranscribeResponse;
}
