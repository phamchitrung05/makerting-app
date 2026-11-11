<?php

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Cấu hình chung module WritingStyle + chuyển phiên bản prompt.
 * =====================================================================
 * Hệ thống Giải mã & Quản lý DNA Văn phong. File này chỉ giữ SETUP CHUNG —
 * toàn bộ câu chữ prompt của từng phiên bản nằm gọn trong thư mục prompts/:
 *
 *   prompts/v3.php   → Prompt Pack V3 (đang dùng)
 *   prompts/v4.php   → (tương lai — copy v3.php, đổi pack_version, chỉnh chữ)
 *
 * Chọn phiên bản chạy bằng env WRITING_STYLE_PROMPT_VERSION; bảng
 * `prompt_packs` map số phiên bản → key config. Code đọc pack qua
 * App\Services\PromptPack (một cửa, rẽ nhánh theo capabilities).
 *
 * Truy cập bằng config('writingstyle.*') (alias module = 'writingstyle').
 * =====================================================================
 */

return [
    'name' => 'WritingStyle',

    /* ---------------------------------------------------------------------
     * CHUYỂN PHIÊN BẢN PROMPT (Prompt Pack).
     * `prompt_version` — số phiên bản hiện hành (env, mặc định 3).
     * `prompt_packs`   — map số phiên bản → key config nạp từ prompts/.
     * --------------------------------------------------------------------- */
    'prompt_version' => (int) env('WRITING_STYLE_PROMPT_VERSION', 3),
    'prompt_packs' => [
        3 => 'v3',
        // 4 => 'v4',  // bỏ comment khi tạo prompts/v4.php
    ],
    'v3' => require __DIR__.'/../prompts/v3.php',

    /* ---------------------------------------------------------------------
     * Thứ tự 15 khóa DNA (+ uncertainties) — dùng khi lặp/validate options
     * và map FE. Câu hỏi từng khóa nằm trong prompt pack (v3.questions).
     * --------------------------------------------------------------------- */
    'rule_keys' => [
        'tone', 'pronouns', 'emotion', 'opening', 'sentence_rhythm',
        'paragraph_rhythm', 'transitions', 'vocabulary', 'technical_terms',
        'structure_patterns', 'headings', 'bullets', 'examples', 'ending',
        'avoid', 'uncertainties',
    ],

    /* ---------------------------------------------------------------------
     * 4 cấp độ độ dài (LengthTier) — bảng định nghĩa dùng chung.
     * label = tên hiển thị tiếng Việt trả cho FE (GET /options.tiers).
     * Khoảng độ dài ĐÍCH khi viết nằm trong prompt pack (v3.lengths).
     * --------------------------------------------------------------------- */
    'tiers' => [
        'micro'  => ['label' => 'Micro — Cực ngắn'],
        'short'  => ['label' => 'Short — Ngắn'],
        'medium' => ['label' => 'Medium — Trung bình'],
        'long'   => ['label' => 'Long — Dài'],
    ],

    /* Số ngày giữ bản ghi phân tích trước khi cleanup (bảo mật text mẫu). */
    'analysis_retention_days' => 30,

    /* Giới hạn độ dài style_instructions (ký tự). */
    'style_instructions_max' => 10000,

    /* =====================================================================
     * KIẾN TRÚC LUỒNG NHỎ LẮP GHÉP CHO PHÒNG THỬ DNA (SANDBOX PIPELINE)
     * =====================================================================
     *
     * Thay vì viết cứng 1 hàm sinh bài cho mọi cấp độ độ dài (LengthTier),
     * quá trình sáng tạo nội dung được chia thành các "Luồng nhỏ" (Step).
     * Mỗi Step là một class độc lập cài đặt `SandboxStepContract`.
     *
     * ---------------------------------------------------------------------
     * QUY TẮC THỨ TỰ TẦNG (STAGE) & PHỤ THUỘC (DEPENDS_ON):
     * ---------------------------------------------------------------------
     * Mỗi Step có một trọng số `stage` (số nguyên tăng dần từ 10 -> 100)
     * đại diện cho vị trí tự nhiên của nó trong tư duy viết bài:
     *   - Stage 10..19 (Tầng Mở đầu / Hook)     : vd `hook_analyzer` (#3)
     *   - Stage 20..29 (Tầng Dàn ý chủ đạo)     : vd `topic_outline` (#1)
     *   - Stage 30..49 (Tầng Phân rã chi tiết)  : vd `section_expander` (#4)
     *   - Stage 100    (Tầng Chấp bút viết bài) : vd `article_writer` (#2, is_terminal = true)
     *
     * Bộ kiểm định `PipelineTopologyValidator` sẽ tự động chặn và báo lỗi nếu:
     *   1. Sai thứ tự tầng: Bước có `stage` lớn hơn lại đặt TRƯỚC bước có
     *      `stage` nhỏ hơn (VD: đặt `article_writer` [100] trước `topic_outline` [20]).
     *   2. Vi phạm bước kết thúc: Bước có `is_terminal = true` (`article_writer`)
     *      không nằm ở vị trí cuối cùng của mảng pipeline.
     *   3. Thiếu bước phụ thuộc (`depends_on`): Bước sau khai báo phụ thuộc vào
     *      một bước trước (VD: `section_expander` cần `topic_outline`), nhưng
     *      bước đó chưa xuất hiện ở các vị trí đứng trước trong cùng Tier,
     *      hoặc khi chạy thực tế (runtime) mà client chưa gửi kèm kết quả
     *      (`artifacts`) của bước phụ thuộc đó.
     *
     * ---------------------------------------------------------------------
     * HƯỚNG DẪN CÁCH THÊM MỘT LUỒNG NHỎ MỚI TRONG TƯƠNG LAI (3 BƯỚC):
     * ---------------------------------------------------------------------
     * Ví dụ muốn thêm luồng #4 "Phân tách chi tiết các dàn ý nhỏ" (`section_expander`)
     * vào giữa luồng #1 (`topic_outline`) và luồng #2 (`article_writer`) của tier `long`:
     *
     *   - Bước 1: Tạo class `Modules\WritingStyle\Pipelines\Steps\SectionExpanderStep`
     *     implements `SandboxStepContract` (khai báo stage = 30, intrinsicDependsOn = ['topic_outline']).
     *   - Bước 2: Đăng ký `'section_expander' => SectionExpanderStep::class` vào mảng `sandbox_steps` bên dưới.
     *   - Bước 3: Chèn vào `tier_pipelines['long']` đúng vị trí sau `topic_outline`:
     *       ['step' => 'topic_outline',    'depends_on' => []],
     *       ['step' => 'section_expander', 'depends_on' => ['topic_outline']],
     *       ['step' => 'article_writer',   'depends_on' => ['topic_outline', 'section_expander']],
     *     (`article_writer` sẽ tự động gom PromptBlock của cả `topic_outline` và
     *     `section_expander` mà KHÔNG cần sửa code của `ArticleWriterStep`).
     * ===================================================================== */

    'sandbox_steps' => [
        // Luồng #1 (Stage 20): Phân tích chủ đề -> Luận điểm cốt lõi & Dàn ý H2/H3
        'topic_outline'  => \Modules\WritingStyle\Pipelines\Steps\TopicOutlineStep::class,

        // Luồng #2 (Stage 100 - Terminal): Áp dụng DNA Văn phong + Kết quả các bước trước -> Viết bài
        'article_writer' => \Modules\WritingStyle\Pipelines\Steps\ArticleWriterStep::class,

        // --- CÁC LUỒNG MỞ RỘNG TRONG TƯƠNG LAI (Bỏ comment khi triển khai class) ---
        // 'hook_analyzer'    => \Modules\WritingStyle\Pipelines\Steps\HookAnalyzerStep::class,    // Luồng #3 (Stage 10): Phân tích câu Hook FB
        // 'section_expander' => \Modules\WritingStyle\Pipelines\Steps\SectionExpanderStep::class, // Luồng #4 (Stage 30): Phân tách dàn ý nhỏ
    ],

    /* ---------------------------------------------------------------------
     * Cấu hình chuỗi luồng chạy (Pipeline) cho từng cấp độ độ dài (Tier).
     *
     * Cấu trúc mỗi phần tử trong mảng của 1 tier:
     *   - 'step'       : (string) Mã khóa đã đăng ký trong `sandbox_steps`.
     *   - 'depends_on' : (string[]) Danh sách mã `step` BẮT BUỘC phải chạy xong
     *                    và có dữ liệu (`artifacts[step]`) trước khi bước này
     *                    được phép kích hoạt.
     *
     * Lưu ý:
     *   - Mảng chạy tuần tự từ trên xuống dưới (index 0 -> N).
     *   - Phần tử cuối cùng của mọi tier BẮT BUỘC phải là bước kết thúc (`article_writer`).
     * --------------------------------------------------------------------- */
    'tier_pipelines' => [
        // Cấp độ Micro (1–3 câu): Sinh trực tiếp 1 bước
        'micro' => [
            [
                'step'       => 'article_writer',
                'depends_on' => [],
            ],
        ],

        // Cấp độ Short (4–10 câu): Hiện tại 1 bước (sau này có thể thêm 'hook_analyzer' lên trước)
        'short' => [
            [
                'step'       => 'article_writer',
                'depends_on' => [],
            ],
        ],

        // Cấp độ Medium (11–30 câu): Hiện tại 1 bước (sau này có thể thêm bước khung câu chuyện lên trước)
        'medium' => [
            [
                'step'       => 'article_writer',
                'depends_on' => [],
            ],
        ],

        // Cấp độ Long (> 30 câu, > 600 từ): Bắt buộc qua 2 bước tuần tự:
        // Bước 1: Phân tích chủ đề, lập dàn ý & luận điểm chủ đạo (`topic_outline`).
        // Bước 2: Nhận dàn ý đã duyệt từ Bước 1 + DNA văn phong để viết toàn bài (`article_writer`).
        'long' => [
            [
                'step'       => 'topic_outline',
                'depends_on' => [],
            ],
            [
                'step'       => 'article_writer',
                'depends_on' => ['topic_outline'],
            ],
        ],
    ],
];
