<?php

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Cấu hình quy tắc validate và giới hạn của WritingBrief
 * (Yêu cầu bài viết trong Phòng thử DNA / Pipeline).
 * =====================================================================
 *
 * `WritingBrief::rules()` đọc trực tiếp các key dưới đây và trả map rule
 * cho các FormRequest nơi brief có thể xuất hiện: SandboxRequest,
 * RunSandboxStepRequest, SuggestBriefRequest và WritingSandboxController::saveSample.
 *
 * Truy cập bằng: config('writingstyle.brief.*')
 *
 * Cách đọc comment trong file này:
 *   Field: 'brief.<tên trường>' hoặc '<trường khác>' — đây là trường/field
 *   THẬT trong request JSON mà giá trị cấu hình này ảnh hưởng. Thay đổi
 *   giá trị = thay đổi giới hạn/ngữ nghĩa của đúng field đó trong API.
 *
 * Sau khi thay đổi trên môi trường production đã cache config, chạy:
 *   php artisan config:clear
 * =====================================================================
 */

return [

    /* ---------------------------------------------------------------------
     * 1. LOẠI NỘI DUNG & TIERS
     * --------------------------------------------------------------------- */

    // Enum loại nội dung — MỘT NƠI DUY NHẤT để thêm/bỏ loại bài.
    // Field: 'brief.content_type' — giá trị chọn trong request (vd: "storytelling").
    // Dùng chung: WritingBrief::rules() (rule `in:`), WritingBriefSuggestService
    // (schema AI + whitelist), và FE khi render dropdown Loại bài.
    'content_types' => ['sales_post', 'announcement', 'storytelling', 'technical_article', 'blog'],

    /* ---------------------------------------------------------------------
     * 2. TRƯỜNG CHÍNH CỦA BRIEF — giới hạn chuỗi của từng field trong request
     * --------------------------------------------------------------------- */

    'fields' => [
        // Field: 'brief.topic' — Chủ đề viết thử/bài viết mà AI cần bám theo
        'topic' => [
            'min' => 3,     // Ký tự tối thiểu — ngăn chủ đề quá ngắn không đủ context AI
            'max' => 2000,  // Ký tự tối đa — chặn chủ đề dài đẩy quá context model
        ],

        // Field: 'brief.platform' — Kênh đăng mục tiêu (vd: "facebook", "blog")
        'platform' => 80,

        // Field: 'brief.audience' — Người đọc mục tiêu bài viết
        'audience' => 500,

        // Field: 'brief.objective' — Mục tiêu đạt được của bài viết (CTA phụ)
        'objective' => 500,

        // Field: 'brief.offer' — Ưu đãi/khuyến mãi nêu trong bài
        'offer' => 500,

        // Field: 'brief.cta' — Lời kêu gọi hành động chính
        'cta' => 500,
    ],

    /* ---------------------------------------------------------------------
     * 3. DỮ KIỆN ĐƯỢC CUNG CẤP (provided_facts)
     * Dữ kiện người dùng đưa vào — AI bắt buộc dùng đúng, không tự bịa.
     * --------------------------------------------------------------------- */

    'provided_facts' => [
        // Field: 'brief.provided_facts' — Số fact tối đa được phép khai báo trong một brief
        'max_items' => 30,

        // Field: 'brief.provided_facts.*.id' — Độ dài tối đa của mã fact (vd: "fact_01")
        'id_max' => 40,

        // Field: 'brief.provided_facts.*.id' — Định dạng cho phép của mã fact (ASCII an toàn)
        'id_regex' => '/^[a-zA-Z0-9_-]+$/',

        // Field: 'brief.provided_facts.*.text' — Độ dài nội dung một fact
        'text_max' => 500,

        // Giới hạn fact do AI sinh bằng nút ✨ — khác với max_items của người dùng
        // (AI chỉ cần gợi ý vài dữ kiện có trong chủ đề, không được lấp đầy).
        'suggest_max' => 10,
    ],

    /* ---------------------------------------------------------------------
     * 4. CHI TIẾT BẮT BUỘC & TRƯỜNG BẮT BUỘC
     * --------------------------------------------------------------------- */

    'required_details' => [
        // Field: 'brief.required_details' — Số chi tiết bắt buộc xuất hiện trong bài
        'max_items' => 30,

        // Field: 'brief.required_details.*' — Độ dài mỗi dòng chi tiết
        'item_max' => 500,
    ],

    'required_fields' => [
        // Danh sách key của brief mà user được phép đánh dấu "bắt buộc phải có".
        // Field: 'brief.required_fields.*' — enum key được chọn
        'allowed_keys' => ['audience', 'objective', 'offer', 'cta', 'provided_facts', 'platform', 'content_type'],
    ],

    /* ---------------------------------------------------------------------
     * 5. ĐỘ DÀI ĐÍCH (length) — giới hạn giữa brief.length và target_sentences
     * --------------------------------------------------------------------- */

    'length' => [
        // Đơn vị độ dài được phép: 'words' (số từ) hoặc 'sentences' (số câu)
        'units' => ['words', 'sentences'],

        // Giới hạn tối thiểu của brief.length.min/max (mọi đơn vị)
        'min' => 1,

        // Giới hạn tối đa của brief.length khi unit = 'words'
        'max_words' => 2000,

        // Giới hạn tối đa của brief.length khi unit = 'sentences'
        // (rule tĩnh chỉ chặn theo max_words; riêng sentences cần trần cao hơn
        // vì một câu Việt Nam có thể rất dài — 200 câu đã là rất dài).
        'max_sentences' => 200,

        // Giới hạn khi gọi trực tiếp target_sentences (form cũ)
        'target_sentences' => ['min' => 1, 'max' => 200],
    ],

    /* ---------------------------------------------------------------------
     * 6. ĐỊNH DẠNG & RÀNG BUỘC THÊM (format + constraints)
     * --------------------------------------------------------------------- */

    'constraints' => [
        // Field: 'brief.constraints' — Số ràng buộc nghiệp vụ tối đa
        'max_items' => 12,

        // Field: 'brief.constraints.*' — Độ dài mỗi dòng ràng buộc
        'item_max' => 300,
    ],

    /* ---------------------------------------------------------------------
     * 7. THÔNG SỐ AI GỌI brief_writer (nút ✨ sinh brief)
     * --------------------------------------------------------------------- */

    'suggest' => [
        // Field: request 'target_sentences' — giới hạn dùng chung khi client
        // gửi số câu cần (dùng cho cả sandbox và brief suggest).
        'max_tokens' => 800,   // token đầu ra — brief chỉ cần JSON ngắn
        'timeout' => 60,       // giây — cuộc gọi nhanh, không phải viết bài dài
    ],

    /* ---------------------------------------------------------------------
     * 8. PHẦN BÀI VIẾT THỬ ĐÃ LƯU (sandbox_samples_json)
     * --------------------------------------------------------------------- */

    'sandbox_samples' => [
        // Số bản viết thử tối đa được giữ trong hồ sơ (unshift, cắt bớt cũ)
        'max_items' => 30,

        // Field: 'saveSample.topic' — Chủ đề của bản thử
        'topic_max' => 500,

        // Field: 'saveSample.text' / 'system_prompt' — Độ dài nội dung và prompt
        'text_max' => 50000,

        // Field: 'saveSample.id' — Mã định danh bản thử (uuid/local-xxx)
        'id_max' => 100,
    ],
];
