<?php

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Cấu hình quy tắc validate và giới hạn của Writing Profile (Hồ sơ văn phong).
 * =====================================================================
 *
 * Gom toàn bộ giới hạn dữ liệu khi tạo (POST) và cập nhật (PUT) hồ sơ văn phong.
 * Mỗi key trong file tương ứng rõ ràng với từng trường (field) trong request và DB.
 *
 * Truy cập bằng: config('writingstyle.profile.*')
 *
 * Sau khi thay đổi trên môi trường production đã cache config, chạy:
 *   php artisan config:clear
 * =====================================================================
 */

return [

    /* ---------------------------------------------------------------------
     * 1. THÔNG TIN CƠ BẢN CỦA HỒ SƠ
     * --------------------------------------------------------------------- */

    // Field: 'name' — Tên hồ sơ văn phong (VD: "Giọng Chuyên gia Bất động sản")
    'name' => [
        'max' => 160,
    ],

    // Field: 'description' — Mô tả ngắn / mục đích sử dụng của hồ sơ văn phong
    'description' => [
        'max' => 2000,
    ],

    // Field: 'slug' — Đường dẫn tĩnh định danh hồ sơ (duy nhất, chuẩn slug url)
    'slug' => [
        'max'   => 250,
        'regex' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
    ],

    // Field: 'temperature' — Độ sáng tạo AI mặc định của hồ sơ (0.0 = chặt chẽ -> 2.0 = tối đa)
    'temperature' => [
        'min' => 0.0,
        'max' => 2.0,
    ],

    // Field: 'dominant_tier' — Cấp độ độ dài chủ đạo được phép chọn
    'dominant_tiers' => [
        'micro',
        'short',
        'medium',
        'long',
    ],

    // Field: 'status' — Trạng thái vòng đời của hồ sơ
    'statuses' => [
        'draft',
        'active',
    ],

    /* ---------------------------------------------------------------------
     * 2. BA Ô CHỈ DẪN HÀNH VĂN (PROMPT INSTRUCTION BOXES)
     * --------------------------------------------------------------------- */

    'instructions' => [
        // Field: 'style_instructions' (Ô 1) — Chỉ dẫn phong cách cốt lõi & vai trò tác giả
        'style_min'         => 1,
        'style_max'         => 10000,

        // Field: 'voice_texture_instructions' (Ô 2) — Chỉ dẫn chất văn, nhịp điệu & từ vựng
        'voice_texture_max' => 10000,

        // Field: 'tone_instructions' (Ô 3) — Chỉ dẫn sắc thái cảm xúc & phối âm (người dùng tự soạn)
        'tone_max'          => 10000,
    ],

    /* ---------------------------------------------------------------------
     * 3. BỘ 16 QUY TẮC DNA VĂN PHONG (rules_json)
     * Gồm 15 thuộc tính văn phong (tone, pronouns, emotion, opening...) + 1 mảng uncertainties.
     * --------------------------------------------------------------------- */

    'rules' => [
        // Field: 'rules_json.<15_khoa_dna>' — Độ dài chuỗi mô tả của từng quy tắc đơn
        'item_min' => 1,
        'item_max' => 500,

        // Field: 'rules_json.uncertainties.*' — Độ dài từng mục điểm chưa xác định
        'uncertainties_item_min' => 1,
        'uncertainties_item_max' => 500,
    ],

    /* ---------------------------------------------------------------------
     * 4. BẰNG CHỨNG TRÍCH DẪN TỪ BÀI MẪU (evidence_json)
     * Danh sách câu trích nguyên văn từ bài mẫu để làm bằng chứng cho DNA.
     * --------------------------------------------------------------------- */

    'evidence' => [
        // Field: 'evidence_json.*.rule_key' — Khóa quy tắc DNA được trích dẫn (tone, pronouns...)
        'rule_key_max' => 50,

        // Field: 'evidence_json.*.excerpt' — Câu văn trích dẫn nguyên văn
        'excerpt_min'  => 1,
        'excerpt_max'  => 2000,

        // Field: 'evidence_json.*.note' — Ghi chú ngữ cảnh của câu trích dẫn
        'note_max'     => 500,
    ],

    /* ---------------------------------------------------------------------
     * 5. ĐIỂM THANH TRƯỢT VÀ PRESET CỦA HỒ SƠ (slider_preferences_json)
     * --------------------------------------------------------------------- */

    'sliders' => [
        // Điểm số giá trị của từng thanh trượt (0 -> 100)
        'min_score' => 0,
        'max_score' => 100,

        // Field: 'slider_preferences_json.voice' — Tối đa 5 trục Chất văn
        'voice_max_axes' => 5,

        // Field: 'slider_preferences_json.tone' — Tối đa 8 trục Sắc thái cảm xúc
        'tone_max_axes'  => 8,

        // Field: 'slider_preferences_json.voice_presets' — Tối đa số preset chất văn đã lưu
        'voice_presets_max_items' => 8,

        // Field: 'slider_preferences_json.tone_presets' — Tối đa số preset cảm xúc đã lưu
        'tone_presets_max_items'  => 8,

        // Field: 'slider_preferences_json.*_presets.*' — Độ dài tên định danh của từng preset
        'preset_name_max'         => 60,
    ],

    /* ---------------------------------------------------------------------
     * 6. PHÂN LOẠI TAXONOMY TẠO MỚI (new_categories, new_tags)
     * --------------------------------------------------------------------- */

    'taxonomy' => [
        // Field: 'new_categories.*' — Tên danh mục mới khi người dùng nhập trực tiếp
        'new_category_name_max' => 160,

        // Field: 'new_tags.*' — Tên nhãn (tag) mới khi người dùng nhập trực tiếp
        'new_tag_name_max'      => 120,
    ],
];
