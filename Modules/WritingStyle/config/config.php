<?php

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Cấu hình riêng của module WritingStyle.
 * =====================================================================
 * Hệ thống Giải mã & Quản lý DNA Văn phong — 16 khóa thuộc tính
 * (RULE_KEYS), 4 cấp độ độ dài (LengthTier), và ngưỡng phân tầng.
 * Truy cập bằng config('writingstyle.*') (alias module = 'writingstyle').
 * Mảng merge vào app config.
 * =====================================================================
 */

return [
    'name' => 'WritingStyle',

    /* ---------------------------------------------------------------------
     * 16 khóa thuộc tính DNA văn phong — mỗi key trả lời câu hỏi
     * "yếu tố này trong bài mẫu là gì" (PLAN.md §300).
     * key => mô tả câu hỏi giải mã (dùng khi prompt AI phân tích).
     * --------------------------------------------------------------------- */
    'rules' => [
        'tone' =>
            'Giọng điệu chung trong bài mẫu là gì? (trang trọng, thân mật, hài hước, kịch tính, lạnh lùng, chuyên gia, ngang hàng...)',
        'pronouns' =>
            'Cách xưng hô nhất quán trong bài mẫu là gì? (tôi/bạn, mình/các bạn, em/chị, anh/chị, chúng ta/họ, ...)',
        'emotion' =>
            'Cung bậc cảm xúc chủ đạo là gì? (tự tin, khẩn trương, đồng cảm, phẫn nộ, tò mò, hy vọng, thách thức...)',
        'opening' =>
            'Cách mở bài là gì? (nghịch lý, câu hỏi gây sốc, con số cụ thể, tình huống có thật, tuyên bố thẳng...)',
        'sentence_rhythm' =>
            'Nhịp câu trong bài mẫu như thế nào? (xen kẽ câu siêu ngắn 3–7 từ với câu dài 15–25 từ; hay toàn câu dài bình đều; tỷ lệ câu ngắn/dài)',
        'paragraph_rhythm' =>
            'Nhịp đoạn văn như thế nào? (đoạn 1–2 câu, đoạn 3–5 câu, đoạn dài khối; hay đoạn đơn xen đoạn đa câu; cách dãn đoạn trên mobile)',
        'transitions' =>
            'Cách chuyển đoạn/giữa các ý là gì? (từ nối máy móc "Mặt khác/Hơn nữa/Đầu tiên" hay chuyển tự nhiên theo câu chuyện; có điểm neo chuyển cảm xúc không)',
        'vocabulary' =>
            'Vốn từ đặc trưng là gì? (từ đời thường, chuyên môn, tiếng lóng, từ viết tắt/tốc ký như "ko/e/r/dc", emoji, onomatopoeia, từ nước ngoài...)',
        'technical_terms' =>
            'Thuật ngữ kỹ thuật/ngành được dùng ra sao? (có giải thích ngay sau term không, hay mặc định độc giả đã biết; tỷ lệ term/đoạn)',
        'structure_patterns' =>
            'Cấu trúc tổng thể bài viết là gì? (Hook → Vấn đề → Giải pháp → Chứng cứ → CTA; hay tuyến tính câu chuyện; hay listicle; hay hỏi–đáp)',
        'headings' =>
            'Tiêu đề con (H2/H3) được dùng thế nào? (tần suất, độ dài, câu hỏi hay khẳng định, có số thứ tự không, có chứa keyword không)',
        'bullets' =>
            'Gạch đầu dòng được dùng thế nào? (liệt kê tính năng, ví dụ, bước thực hiện; độ dài mỗi bullet; có chữ in đậm mở đầu không)',
        'examples' =>
            'Dẫn chứng/câu chuyện mẫu được dùng thế nào? (case study số liệu, anecdote ngắn 15–30 từ, ví dụ giả định, so sánh trước–sau)',
        'ending' =>
            'Cách kết bài là gì? (khép lại câu hỏi/nghịch lý mở đầu, CTA trực tiếp, lời khuyên cuối, câu hỏi mở cho bình luận, lời hứa)',
        'avoid' =>
            'Những thứ TUYỆT ĐỐI không xuất hiện trong bài mẫu là gì? (sáo rỗng doanh nghiệp, tính từ mơ hồ, câu bị động dài, từ nối cứng, khuyến mãi thổi phồng, ...)',
        'uncertainties' =>
            'Các yếu tố mẫu KHÔNG đủ để xác định là gì? (đưa rõ khóa nào không có trong mẫu — ví dụ micro không có headings/bullets — để AI không bịa đặt)',
    ],

    /* ---------------------------------------------------------------------
     * Thứ tự 16 khóa — dùng khi lặp/validate (giữ đúng thứ tự spec).
     * --------------------------------------------------------------------- */
    'rule_keys' => [
        'tone', 'pronouns', 'emotion', 'opening', 'sentence_rhythm',
        'paragraph_rhythm', 'transitions', 'vocabulary', 'technical_terms',
        'structure_patterns', 'headings', 'bullets', 'examples', 'ending',
        'avoid', 'uncertainties',
    ],

    /* ---------------------------------------------------------------------
     * 4 cấp độ độ dài (LengthTier) — phân tầng theo số câu/số từ.
     * tier => [min_sentences, max_sentences, min_words, max_words,
     *          sentence_hint, label]
     * sentence_hint = dùng trong System Prompt ("viết 2–3 câu", "bài dài").
     * label = tên hiển thị tiếng Việt trả cho FE (bỏ hằng TIER_META phía UI).
     * --------------------------------------------------------------------- */
    'tiers' => [
        'micro'  => ['min_sentences' => 1,  'max_sentences' => 3,  'min_words' => 0,   'max_words' => 59,   'sentence_hint' => '2–3 câu',   'label' => 'Micro — Cực ngắn'],
        'short'  => ['min_sentences' => 4,  'max_sentences' => 10, 'min_words' => 60,  'max_words' => 180,  'sentence_hint' => '3–4 câu',   'label' => 'Short — Ngắn'],
        'medium' => ['min_sentences' => 11, 'max_sentences' => 30, 'min_words' => 181, 'max_words' => 600,  'sentence_hint' => '20–30 câu', 'label' => 'Medium — Trung bình'],
        'long'   => ['min_sentences' => 31, 'max_sentences' => PHP_INT_MAX, 'min_words' => 601, 'max_words' => PHP_INT_MAX, 'sentence_hint' => 'bài dài (phân đoạn rõ ràng)', 'label' => 'Long — Dài'],
    ],

    /* ---------------------------------------------------------------------
     * Các khóa chỉ áp dụng cho văn bản dài — khi tier='micro' tự động đẩy
     * vào 'uncertainties' (không ép AI bịa headings/bullets trong 1–3 câu).
     * --------------------------------------------------------------------- */
    'long_only_rule_keys' => [
        'headings', 'bullets', 'paragraph_rhythm', 'structure_patterns',
    ],

    /* Số ngày giữ bản ghi phân tích trước khi cleanup (bảo mật text mẫu). */
    'analysis_retention_days' => 30,

    /* Giới hạn độ dài style_instructions (ký tự). */
    'style_instructions_max' => 10000,
];
