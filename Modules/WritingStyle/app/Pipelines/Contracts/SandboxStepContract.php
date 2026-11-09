<?php

namespace Modules\WritingStyle\Pipelines\Contracts;

use Modules\WritingStyle\Models\WritingProfile;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Hợp đồng của 1 Luồng nhỏ (Pipeline Step) trong Sandbox.
 * =====================================================================
 *
 * Mỗi Step = 1 class độc lập implements interface này — tự khai báo `key`,
 * `stage`, `isTerminal`, `intrinsicDependsOn`, `meta`, `validateArtifact`,
 * `compileToPromptBlock` và `execute`. Đăng ký class vào
 * `config('writingstyle.sandbox_steps')` rồi cấu hình vào `tier_pipelines`.
 *
 * KHÔNG gọi trực tiếp Step từ ngoài — luôn đi qua `SandboxPipelineRunner`
 * để được kiểm định thứ tự + phụ thuộc trước khi thực thi.
 * =====================================================================
 */
interface SandboxStepContract
{
    /**
     * CHỨC NĂNG: Mã định danh duy nhất của bước (key trong `sandbox_steps`).
     * INPUT: không. OUTPUT: string — ví dụ 'topic_outline', 'article_writer'.
     */
    public function key(): string;

    /**
     * CHỨC NĂNG: Trọng số thứ tự tầng (10..100) — stage nhỏ chạy trước.
     * INPUT: không. OUTPUT: int — stage 10 Hook, 20 Dàn ý, 30 Chi tiết, 100 Viết bài.
     */
    public function stage(): int;

    /**
     * CHỨC NĂNG: Có phải bước chấp bút kết thúc chuỗi không.
     * INPUT: không. OUTPUT: bool — `true` chỉ với `article_writer`; phải ở cuối pipeline.
     */
    public function isTerminal(): bool;

    /**
     * CHỨC NĂNG: Các bước phụ thuộc NỘI TẠI của chính step này (không tính
     * `depends_on` khai báo trong `tier_pipelines`).
     * INPUT: không. OUTPUT: string[] — mã key của các step bắt buộc đã chạy trước.
     */
    public function intrinsicDependsOn(): array;

    /**
     * CHỨC NĂNG: Metadata hiển thị (label/description/icon/stage/is_terminal)
     * để API `GET /options` trả cho FE render Pipeline Stepper.
     * INPUT: không. OUTPUT: array {key,label,description,icon,stage,is_terminal}.
     */
    public function meta(): array;

    /**
     * CHỨC NĂNG: Kiểm tra artifact (kết quả step này đã lưu) có hợp lệ để
     * một step sau có thể dùng làm `depends_on` không.
     * INPUT: array $artifact — dữ liệu step này đã trả trước đó.
     * OUTPUT: bool — `true` nếu artifact đủ schema để step sau tin dùng.
     */
    public function validateArtifact(array $artifact): bool;

    /**
     * CHỨC NĂNG: Biên dịch artifact của step này thành 1 khối văn bản chỉ dẫn
     * chuẩn (`PromptBlock`) để nhúng vào User Prompt của các step đứng sau.
     * INPUT: array $artifact — kết quả step này (đã qua `validateArtifact`).
     * OUTPUT: string — đoạn chỉ dẫn đã format sẵn cho AI viết bài.
     */
    public function compileToPromptBlock(array $artifact): string;

    /**
     * CHỨC NĂNG: Thực thi step — trả artifact để FE lưu & step sau dùng.
     * INPUT :
     *   - WritingProfile $profile — hồ sơ văn phong đang dùng.
     *   - string $topic — chủ đề user nhập.
     *   - string $targetTier — cấp độ độ dài đích (micro|short|medium|long).
     *   - array $upstreamPromptBlocks — các khối PromptBlock của các step đứng
     *     trước step này trong pipeline (đã compileToPromptBlock sẵn).
     *   - array $overrides — tham số runtime (tone/temperature/style/voice...).
     * OUTPUT: array — artifact trả về FE (schema tùy step).
     */
    public function execute(
        WritingProfile $profile,
        string $topic,
        string $targetTier,
        array $upstreamPromptBlocks,
        array $overrides = [],
    ): array;
}
