<?php

namespace Modules\WritingStyle\Services;

use Illuminate\Validation\ValidationException;
use Modules\CoreAi\Contracts\CoreAiGatewayContract;
use Modules\CoreAi\DTOs\AiStructuredRequest;
use Modules\WritingStyle\DTOs\WritingBrief;
use Modules\WritingStyle\Models\WritingProfile;
use Modules\WritingStyle\Pipelines\Exceptions\InvalidPipelineException;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Sinh gợi ý Brief cho Phòng thử DNA từ một chủ đề.
 * =====================================================================
 *
 * Nhận `topic` (+ `target_tier`/`target_sentences`), gọi `brief_writer` role
 * trong Prompt Pack để AI đề xuất các trường brief phẳng đúng schema
 * `WritingBrief` (camelCase `content_type`, `provided_facts`, `format`...),
 * chuẩn hóa và trả về cho FE đổ vào form `SandboxWritingBrief`.
 *
 * Không tạo hay sửa `WritingProfile`; không dispatch job — đây là cuộc gọi
 * nhanh (~800 token) chạy đồng bộ trong request. Luôn đánh lại ID của
 * `provided_facts` thành `fact_01..fact_N` để client không phải tin ID AI.
 * =====================================================================
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - __construct(): Nhận dependency gateway AI.
 * - schema(): JSON Schema strict của brief trả về (khớp form FE).
 * - suggest(): Gọi AI, validate và trả brief đã chuẩn hóa.
 * - normalizeFacts(): Gán lại ID thứ tự fact_01..fact_N cho các dữ kiện.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : WritingProfile, topic, tier và overrides (target_sentences).
 * - OUTPUT: array brief phẳng (khớp WritingBrief::rules() phần chấp nhận).
 *
 * BỐI CẢNH SỬ DỤNG: WritingSandboxController::suggestBrief gọi để điền form.
 * =====================================================================
 */
class WritingBriefSuggestService
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Khởi tạo service với gateway AI
     * =====================================================================
     *
     * INPUT:
     * - private readonly CoreAiGatewayContract $ai
     *
     * OUTPUT:
     * - Instance giữ gateway AI.
     *
     * SIDE EFFECT:
     * - Gán thuộc tính trong bộ nhớ; không gọi AI hoặc ghi DB.
     *
     * EXCEPTION/TRANSACTION:
     * - Lỗi kiểu dữ liệu truyền về caller; không mở transaction.
     * =====================================================================
     */
    public function __construct(
        private readonly CoreAiGatewayContract $ai,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: JSON Schema strict của brief trả về cho FE
     * =====================================================================
     *
     * Trường khớp `WritingBrief::rules()` (snake_case chuẩn), nhưng trả đủ
     * mảng cấu trúc để FE map ngược vào form phẳng của `SandboxWritingBrief`.
     * `required_details` và `length` KHÔNG sinh — chúng là ý định người dùng
     * đặt lên bài viết, AI đoán dễ khiến writer bị `missing_fields` lỗi.
     *
     * INPUT:
     * - Không có tham số.
     *
     * OUTPUT:
     * - array schema object strict (required + additionalProperties:false).
     *
     * SIDE EFFECT:
     * - Đọc cấu hình và dựng schema trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không mở transaction.
     * =====================================================================
     */
    private function schema(): array
    {
        $string = ['type' => 'string'];
        $nullableString = ['type' => ['string', 'null']];
        $nullableBoolean = ['type' => ['boolean', 'null']];
        $contentType = ['type' => ['string', 'null'], 'enum' => ['sales_post', 'announcement', 'storytelling', 'technical_article', 'blog', null]];

        return PromptPack::objectSchema([
            'content_type'    => $contentType,
            'platform'        => $nullableString,
            'audience'        => $nullableString,
            'objective'       => $nullableString,
            'offer'           => $nullableString,
            'cta'             => $nullableString,
            'provided_facts'  => [
                'type' => 'array',
                'items' => PromptPack::objectSchema([
                    'text' => $string,
                ]),
            ],
            'format'          => PromptPack::objectSchema([
                'headings' => $nullableBoolean,
                'bullets'  => $nullableBoolean,
            ]),
            'constraints'     => ['type' => 'array', 'items' => $string],
            'warnings'        => ['type' => 'array', 'items' => $string],
        ]);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Sinh brief từ chủ đề qua AI structured call
     * =====================================================================
     *
     * INPUT:
     * - WritingProfile $profile — để lấy tên hồ sơ (context cho AI).
     * - string $topic — chủ đề người dùng gõ.
     * - string $targetTier — cấp độ độ dài đích (chỉ dùng để chọn content_type,
     *   KHÔNG đưa số từ/câu vào prompt — xem chú thích trong thân hàm).
     * - int $targetSentences — giữ tham số theo hợp đồng controller; cố ý
     *   KHÔNG gửi vào prompt vì độ dài do user chọn ở giao diện.
     *
     * OUTPUT:
     * - array brief chuẩn hóa (camelCase keys khớp WritingBrief).
     *
     * SIDE EFFECT:
     * - Gọi AI gateway (ghi AiRun/usage). Không ghi DB.
     *
     * EXCEPTION/TRANSACTION:
     * - ValidationException khi topic quá dài hoặc AI trả sai enum/kiểu.
     * - InvalidPipelineException khi AI trả rỗng hoặc bị cắt giữa chừng.
     * - Lỗi gateway truyền lên. Không mở transaction.
     * =====================================================================
     */
    public function suggest(
        WritingProfile $profile,
        string $topic,
        string $targetTier = 'medium',
        int $targetSentences = 0,
    ): array {
        $topic = trim($topic);
        if ($topic === '') {
            throw ValidationException::withMessages(['topic' => 'Chủ đề không được để trống.']);
        }

        // KHÔNG gửi length_hint trong prompt — độ dài là quyết định của user,
        // nếu đưa vào prompt thì AI sẽ nhắc lại nó thành constraint ("1200 từ")
        // thay vì điền ràng buộc nghiệp vụ thực sự.
        $tier = in_array($targetTier, WritingProfile::TIERS, true) ? $targetTier : WritingProfile::TIER_MEDIUM;

        $system = PromptPack::role('brief_writer');
        $prompt = PromptPack::json([
            'topic'          => $topic,
            'target_tier'    => $tier,
            'profile_name'   => (string) $profile->name,
            'profile_tier'   => (string) ($profile->dominant_tier ?? $tier),
            'style_hint'     => trim((string) ($profile->style_instructions ?? '')),
        ]);

        PromptPack::checkContext($system, $prompt);

        $response = $this->ai->executeStructured(AiStructuredRequest::fromPrompt(
            $prompt,
            system: $system,
            schema: $this->schema(),
            options: [
                'temperature' => PromptPack::temperature('brief_writer', 0.4),
                'max_tokens'  => 800,
                'timeout'     => 60,
            ],
            module: 'writingstyle',
            operation: 'sandbox_brief_suggest',
        ));

        PromptPack::requireComplete($response);
        $data = $response->data ?? [];
        if ($data === []) {
            throw new InvalidPipelineException('AI chưa trả nội dung gợi ý brief. Hãy thử lại.');
        }

        return $this->normalize($data);
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Chuẩn hóa output AI về brief phẳng khớp WritingBrief
     * =====================================================================
     *
     * - `provided_facts`: chỉ giữ `text` hợp lệ, đánh lại ID `fact_01..fact_N`,
     *   `source` luôn 'user' (không tin AI gán ID — chống xung đột).
     * - `format`: giữ null khi AI trả null (theo hồ sơ), lọc bỏ key null khỏi
     *   array để `WritingBrief::fromInput` không nhầm thành "user đã chọn false".
     * - Trả snake_case đúng quy ước `WritingBrief::rules()`; FE map camelCase.
     *
     * INPUT:
     * - array $data — JSON structured output từ AI.
     *
     * OUTPUT:
     * - array brief đã chuẩn hóa.
     *
     * SIDE EFFECT:
     * - Đọc và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - ValidationException khi `content_type` ngoài enum (phòng AI bịa giá trị).
     * =====================================================================
     */
    private function normalize(array $data): array
    {
        $allowedTypes = ['sales_post', 'announcement', 'storytelling', 'technical_article', 'blog'];
        $contentType = $data['content_type'] ?? null;
        if ($contentType !== null && ! in_array($contentType, $allowedTypes, true)) {
            throw ValidationException::withMessages(['content_type' => "AI trả loại bài không hợp lệ: {$contentType}"]);
        }

        $facts = [];
        foreach ((array) ($data['provided_facts'] ?? []) as $item) {
            $text = is_array($item) ? trim((string) ($item['text'] ?? '')) : trim((string) $item);
            if ($text !== '') {
                $facts[] = $text;
            }
        }

        $facts = array_slice($facts, 0, 10);
        $providedFacts = [];
        foreach ($facts as $index => $text) {
            $providedFacts[] = [
                'id'     => 'fact_' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'text'   => $text,
                'source' => 'user',
            ];
        }

        $format = array_filter(
            [
                'headings' => isset($data['format']['headings']) ? (bool) $data['format']['headings'] : null,
                'bullets'  => isset($data['format']['bullets']) ? (bool) $data['format']['bullets'] : null,
            ],
            static fn ($v) => $v !== null,
        );

        $warnings = array_values(array_filter(
            array_map(static fn ($w) => trim((string) $w), (array) ($data['warnings'] ?? [])),
        ));

        // Lọc bỏ constraint chỉ nhắc đến ĐỘ DÀI (ví dụ "1200 từ", "tối đa 1200 từ")
        // — độ dài là quyết định của user, không phải ràng buộc nghiệp vụ AI nên
        // điền. Đây là nguồn gốc "Yêu cầu thêm" luôn cố định "1200 từ".
        $constraints = array_values(array_filter(
            array_map(static fn ($c) => trim((string) $c), (array) ($data['constraints'] ?? [])),
            static fn (string $c) => ! preg_match('/(\b\d+\s*(từ|words?|câu|sentences?)\b)/iu', $c),
        ));

        return [
            'content_type'   => $contentType,
            'platform'       => isset($data['platform']) ? trim((string) $data['platform']) : null,
            'audience'       => isset($data['audience']) ? trim((string) $data['audience']) : null,
            'objective'      => isset($data['objective']) ? trim((string) $data['objective']) : null,
            'offer'          => isset($data['offer']) ? trim((string) $data['offer']) : null,
            'cta'            => isset($data['cta']) ? trim((string) $data['cta']) : null,
            'provided_facts' => $providedFacts,
            'format'         => $format,
            'constraints'    => $constraints,
            'warnings'       => $warnings,
        ];
    }
}
