<?php

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Cấu hình quy tắc validate và thông số kỹ thuật cho
 * luồng Giải mã bài mẫu (Sample Analysis & Reverse Engineering).
 * =====================================================================
 *
 * Gom toàn bộ quy tắc validate đầu vào (POST /analyze), giới hạn fetcher bóc
 * tách văn bản từ URL công khai, thông số gọi AI giải mã (Prompt Pack V3),
 * quy tắc chuẩn hóa kết quả AI và cấu hình job xử lý bất đồng bộ.
 *
 * Truy cập bằng: config('writingstyle.analysis.*')
 *
 * Cách đọc comment trong file này:
 *   Field: 'tên_trường' — đây là trường THẬT trong request, DB hoặc artifact
 *   mà giá trị cấu hình này ảnh hưởng đến. Thay đổi = đổi giới hạn/tham số
 *   của đúng field đó.
 *
 * Sau khi thay đổi trên môi trường production đã cache config, chạy:
 *   php artisan config:clear
 * =====================================================================
 */

return [

    /* ---------------------------------------------------------------------
     * 1. VALIDATE ĐẦU VÀO YÊU CẦU GIẢI MÃ (AnalyzeTextRequest)
     * Áp dụng khi client gửi payload lên endpoint POST /writing-profiles/analyze.
     * --------------------------------------------------------------------- */

    'input' => [
        // Field: 'source_type' — Các loại nguồn bài mẫu được hệ thống hỗ trợ
        'source_types' => [
            'paste', // Dán trực tiếp nội dung văn bản
            'url',   // Nhập link bài viết công khai để hệ thống tự cào & bóc tách
            'file',  // Tải file tài liệu lên (dự phòng giai đoạn mở rộng)
        ],

        // Field: 'source_url' — Độ dài tối đa của đường dẫn URL bài viết mẫu
        'source_url_max' => 2000,

        // Field: 'reference_text' — Độ dài ký tự tối thiểu của bài mẫu dán trực tiếp
        'reference_text_min' => 50,

        // Field: 'reference_text' — Độ dài ký tự tối đa của bài mẫu tiếp nhận (200.000 ký tự)
        'reference_text_max' => 200000,

        // Field: 'name' — Độ dài tối đa tên gợi ý cho hồ sơ khi lưu kết quả
        'name_max' => 160,
    ],

    /* ---------------------------------------------------------------------
     * 2. BỘ THU THẬP & BÓC TÁCH NỘI DUNG TỪ URL (ReferenceSourceFetcher)
     * Áp dụng khi client chọn source_type = 'url'.
     * --------------------------------------------------------------------- */

    'fetcher' => [
        // Số lần chuyển hướng (301, 302, 303, 307, 308) tối đa được phép theo dõi
        'max_redirects' => 3,

        // Dung lượng tối đa của HTTP response body nhận về để xử lý (2 MB)
        'max_bytes' => 2 * 1024 * 1024,

        // Độ dài ký tự tối thiểu của nội dung sau khi strip HTML (dưới ngưỡng coi như lỗi)
        'min_chars' => 50,

        // Thời gian chờ tối đa cho mỗi lượt fetch mạng (tính bằng giây)
        'timeout' => 15,

        // Ngưỡng cắt mềm nội dung nếu trang web quá dài (giữ đoạn đầu đại diện cho giọng)
        'soft_truncate_chars' => 180000,
    ],

    /* ---------------------------------------------------------------------
     * 3. THÔNG SỐ GỌI AI GATEWAY (WritingStyleAnalysisV3 - request)
     * Cấu hình gửi sang CoreAiGateway để thực thi prompt phân tích DNA.
     * --------------------------------------------------------------------- */

    'ai_request' => [
        // Field: request 'options.timeout' — Thời gian chờ HTTP của lời gọi AI giải mã (giây)
        'timeout' => 180,

        // Hệ số nhân cho max_output_tokens so với limit cơ sở của PromptPack (4096 * 2 = 8192)
        'max_tokens_multiplier' => 2,
    ],

    /* ---------------------------------------------------------------------
     * 4. CHUẨN HÓA & KIỂM DUYỆT ĐẦU RA TỪ AI (WritingStyleAnalysisV3 - normalize)
     * Xác thực cấu trúc dữ liệu JSON Schema do AI trả về trước khi ghi vào kết quả.
     * --------------------------------------------------------------------- */

    'normalization' => [
        // Field: 'features.*.description' — Độ dài chuỗi mô tả từng đặc tính DNA
        'feature_description_max' => 500,

        // Field: 'features.*.evidence_ids' — Số lượng ID bằng chứng tối đa liên kết với 1 feature
        'feature_evidence_ids_max' => 20,

        // Field: 'evidence.*.id' & 'features.*.evidence_ids.*' — Độ dài tối đa của mã định danh bằng chứng (vd: ev_1)
        'evidence_id_max' => 40,

        // Field: 'evidence' — Số lượng trích dẫn bằng chứng tối đa trích xuất từ bài mẫu
        'evidence_max_items' => 20,

        // Field: 'evidence.*.block_ids' — Số lượng khối đoạn văn tối đa mà 1 bằng chứng tham chiếu
        'evidence_block_ids_max' => 20,

        // Field: 'evidence.*.text' — Độ dài tối đa câu văn trích dẫn hoặc nhận xét cấu trúc
        'evidence_text_max' => 500,

        // Field: 'signature_language.*' — Số từ tối đa trong mỗi nhóm ngôn ngữ đặc trưng (xưng hô, thán từ...)
        'signature_language_max_items' => 8,

        // Field: 'signature_language.*.*' — Độ dài tối đa của từng từ/cụm từ xưng hô đặc trưng
        'signature_language_item_max' => 60,

        // Field: 'source_specific_items' — Số mục tối đa các yếu tố đặc thù ngữ cảnh bài viết
        'source_specific_max_items' => 30,

        // Field: 'source_specific_items.*' — Độ dài tối đa của từng yếu tố ngữ cảnh đặc thù
        'source_specific_item_max' => 200,

        // Field: 'uncertainties' — Số lượng điểm chưa chắc chắn / cần kiểm chứng tối đa
        'uncertainties_max_items' => 15,

        // Field: 'uncertainties.*' — Độ dài chuỗi mô tả từng điểm chưa chắc chắn
        'uncertainties_item_max' => 500,

        // Field: 'style_instructions' (Ô 1) — Độ dài tối đa chỉ dẫn phong cách cốt lõi AI sinh ra
        'style_instructions_max' => 2500,

        // Field: 'voice_texture_instructions' (Ô 2) — Độ dài tối đa chỉ dẫn chất văn AI sinh ra
        'voice_texture_instructions_max' => 2500,
    ],

    /* ---------------------------------------------------------------------
     * 5. JOB XỬ LÝ BẤT ĐỒNG BỘ VÀ LƯU TRỮ KẾT QUẢ PHÂN TÍCH
     * --------------------------------------------------------------------- */

    'job' => [
        // Field: job $timeout — Timeout tối đa của job giải mã trong hàng đợi (giây)
        'timeout' => 300,

        // Field: job $tries — Số lần thử lại tối đa khi gặp lỗi tạm thời (transient error)
        'tries' => 2,
    ],

    'storage' => [
        // Số ngày lưu trữ bản ghi phân tích trước khi dọn dẹp để bảo mật văn bản mẫu
        'retention_days' => 30,
    ],
];
