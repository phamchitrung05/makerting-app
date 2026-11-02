<?php

namespace Modules\WritingStyle\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO một vi phạm văn phong phát hiện trong văn bản.
 * =====================================================================
 *
 * Dùng cho `EvaluateTextQualityAction` (P2-02 mở rộng sau) và lưu trong
 * `writing_profile_analyses.result_json.violations`.
 *
 * `severity`: 'hint' (gợi ý nhẹ), 'warning' (nên sửa), 'blocking' (bắt buộc
 * sửa trước khi xuất bản).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : ruleKey, severity, message, excerpt?, suggestion?.
 * - OUTPUT: readonly object.
 * =====================================================================
 */
final readonly class StyleViolationDto
{
    public const SEVERITY_HINT     = 'hint';
    public const SEVERITY_WARNING  = 'warning';
    public const SEVERITY_BLOCKING = 'blocking';

    /**
     * CHỨC NĂNG: khởi tạo DTO vi phạm.
     * INPUT: các thuộc tính immutable.
     */
    public function __construct(
        public string $ruleKey,
        public string $severity,
        public string $message,
        public ?string $excerpt = null,
        public ?string $suggestion = null,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Mảng JSON-serializable để nhét vào result_json.
     * INPUT: không. OUTPUT: array.
     * =====================================================================
     */
    public function toArray(): array
    {
        return [
            'rule_key'   => $this->ruleKey,
            'severity'   => $this->severity,
            'message'    => $this->message,
            'excerpt'    => $this->excerpt,
            'suggestion' => $this->suggestion,
        ];
    }
}
