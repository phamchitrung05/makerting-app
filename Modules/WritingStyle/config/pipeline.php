<?php

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Cấu hình giới hạn và thông số của các Step pipeline
 * trong Phòng thử DNA (Sandbox Pipeline).
 * =====================================================================
 *
 * `WritingContentPlan::validate()` và các Step (TopicOutlineStep,
 * ArticleWriterStep, WritingBriefSuggestService) đọc giới hạn từ đây
 * thay vì hard-code trong code — chỉnh không phải sửa class.
 *
 * Truy cập bằng: config('writingstyle.pipeline.*')
 *
 * Cách đọc comment trong file này:
 *   Field: 'tên_trường' — đây là trường THẬT trong artifact/request mà giá
 *   trị cấu hình này ảnh hưởng đến (ví dụ mảng sections của dàn ý).
 *
 * Sau khi thay đổi trên môi trường production đã cache config, chạy:
 *   php artisan config:clear
 * =====================================================================
 */

return [

    /* ---------------------------------------------------------------------
     * 1. CONTENT PLAN (dàn ý từ topic_outline → WritingContentPlan::validate)
     * --------------------------------------------------------------------- */

    'plan' => [
        // Field: 'central_message' — Thông điệp cốt lõi của plan
        'central_message_max' => 800,

        // Field: 'target_angle' — Góc tiếp cận độc giả của plan
        'target_angle_max' => 800,

        // Field: 'structure_type' — Nhãn kiểu cấu trúc (storytelling, listicle...)
        // Giữ nhãn ngắn (không phải câu mô tả) — planner được nhắc trong prompts/v3.php
        'structure_type_max' => 120,

        // Field: 'sections' — Số mục dàn ý tối đa được phép
        'sections_max' => 12,

        // Field: 'sections.*.id' & 'parent_id' — Độ dài mã phần mục
        'section_id_max' => 40,

        // Field: 'sections.*.role' — Vai trò nội dung của phần (mở bài, thân, kết)
        'role_max' => 80,

        // Field: 'sections.*.title' — Tiêu đề phần
        'title_max' => 200,

        // Field: 'sections.*.key_points' — Số luận cứ trong một phần
        'key_points_max' => 8,

        // Field: 'sections.*.key_points.*' — Độ dài một luận cứ
        'key_point_max' => 350,

        // Field: 'sections.*.fact_ids' — Số tham chiếu dữ kiện trong phần
        'fact_ids_max' => 30,

        // Field: 'sections.*.fact_ids.*' — Độ dài mã fact (giống brief.provided_facts.id)
        'fact_id_max' => 40,

        // Field: 'sections.*.length_budget' — Ngân sách độ dài (theo unit của brief)
        'length_budget' => ['min' => 1, 'max' => 2000],

        // Field: 'evidence_requests' — Số đề xuất dẫn chứng thiếu tối đa
        'evidence_requests_max' => 12,

        // Field: 'missing_fields' — Số cảnh báo trường thiếu tối đa
        'missing_fields_max' => 12,

        // Field: 'warnings' — Số cảnh báo khác tối đa
        'warnings_max' => 12,

        // Độ dài mỗi mục trong 3 trường meta trên — được TRUNCATE an toàn
        // (không reject plan khi AI trả text dài, chỉ cắt ngắn mỗi mục).
        'meta_item_max' => 500,
    ],

    /* ---------------------------------------------------------------------
     * 2. THÔNG SỐ AI CỦA CÁC STEP
     * --------------------------------------------------------------------- */

    'ai' => [
        // Field: 'options.timeout' của từng step pipeline — HTTP timeout gọi AI
        // (đủ lớn cho planner/writer max_tokens dài; analyzer dùng ai_request.timeout)
        'topic_outline_timeout' => 180,
        'article_writer_timeout' => 180,

        // Field: 'options.timeout' của brief_writer (nút ✨ nhanh)
        'brief_writer_timeout' => 60,
    ],
];
