<?php

namespace Modules\VisualStyle\DTOs;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: DTO snapshot DNA ảnh — cổng đọc cho module Tầng 3.
 * =====================================================================
 *
 * Cấu trúc `dna_json` 5 nhóm enum bắt buộc + shots đã map sẵn; không chứa
 * `compiled_prompt_preview` hay các trường nội bộ khác — module tiêu dùng
 * (VideoStudio, SocialPublisher, Blog, MediaPicker) không được đọc model
 * trực tiếp mà phải qua `VisualStyleSnapshotService::snapshot()`.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : id, name, schemaVersion, dnaJson, shots, referenceMediaId,
 *          negativePrompt, status.
 * - OUTPUT: readonly object → consumer serialize/dùng để sinh ảnh.
 * =====================================================================
 */
final readonly class VisualStyleSnapshotDto
{
    /**
     * CHỨC NĂNG: khởi tạo snapshot DNA.
     * INPUT: id, name, schemaVersion, dnaJson, shots, referenceMediaId,
     *        negativePrompt, status.
     * OUTPUT: instance bất biến.
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $schemaVersion,
        public array $dnaJson,
        public array $shots,
        public ?int $referenceMediaId,
        public ?string $negativePrompt,
        public string $status,
    ) {}

    /**
     * =====================================================================
     * CHỨC NĂNG: Nhóm DNA cụ thể (composition|camera|lighting|environment|color_mood).
     * =====================================================================
     * INPUT: string $group. OUTPUT: array (rỗng nếu thiếu).
     * =====================================================================
     */
    public function dnaGroup(string $group): array
    {
        return is_array($this->dnaJson[$group] ?? null) ? $this->dnaJson[$group] : [];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Subject rules (preserve/forbidden) từ DNA.
     * =====================================================================
     * INPUT: không. OUTPUT: array{preserve?:string[], forbidden?:string[]}.
     * =====================================================================
     */
    public function subjectRules(): array
    {
        return is_array($this->dnaJson['subject_rules'] ?? null)
            ? $this->dnaJson['subject_rules']
            : [];
    }

    /**
     * =====================================================================
     * CHỨC NĂNG: Confidence score của DNA (0..1).
     * =====================================================================
     * INPUT: không. OUTPUT: float.
     * =====================================================================
     */
    public function confidence(): float
    {
        return (float) ($this->dnaJson['confidence'] ?? 0.0);
    }
}
