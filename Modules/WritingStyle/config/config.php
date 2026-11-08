<?php

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Cấu hình riêng của module WritingStyle.
 * =====================================================================
 * Hệ thống Giải mã & Quản lý DNA Văn phong — 16 khóa thuộc tính
 * (RULE_KEYS), 4 cấp độ độ dài (LengthTier), và ngưỡng phân tầng.
 * Truy cập bằng config('writingstyle.*') (alias module = 'writingstyle').
 * Mảng merge vào app config.
 *
 * PLAN-WRITING-STYLE-PROMPT-V2: toàn bộ câu chữ prompt (Analyzer,
 * Compiler, Sandbox) tập trung tại mảng 'prompts' — tinh chỉnh tiếng
 * Việt không cần sửa code PHP.
 * =====================================================================
 */

return [
    'name' => 'WritingStyle',

    /* ---------------------------------------------------------------------
     * 16 khóa thuộc tính DNA văn phong — mỗi key trả lời câu hỏi giải mã.
     * Đã viết lại theo hướng "Thủ pháp & Chất liệu" (CONTENT-AGNOSTIC):
     * hỏi công thức/nguyên lý hành văn, KHÔNG kiểm kê từ ngữ bài mẫu —
     * tránh AI bê nguyên danh từ/bối cảnh mẫu vào rules_json.
     * --------------------------------------------------------------------- */
    'rules' => [
        'tone' =>
            'Tâm thế đối thoại của người viết là gì? (thân tình kể khẽ, quiet luxury — trầm sang, hóm hỉnh tự trào, đanh thép, chuyên gia bình thản...)',
        'pronouns' =>
            'Cặp đại từ xưng hô và trợ từ làm mềm câu là gì? (tôi/bạn, mình/các bạn, em/chị; kèm trợ từ như nhé, nghen, nha, đó, ạ...)',
        'emotion' =>
            'Dư âm cảm xúc chủ đạo bài để lại là gì? (vỗ về êm ái, kích thích tò mò, chiêm nghiệm lắng đọng, khẩn trương thôi thúc...)',
        'opening' =>
            'Thủ pháp mở bài tổng quát là gì? (tương phản giác quan/không gian, khung hình điện ảnh, đặt nghịch lý, câu hỏi gợi — TUYỆT ĐỐI không nêu lại thời tiết hay sự vật cụ thể của bài mẫu)',
        'sentence_rhythm' =>
            'Nhịp thở câu văn là gì? (nhịp chậm êm ái, câu ghép nhiều vế ngắt bằng dấu phẩy, câu ngắn điểm xuyết câu dài — mô tả CÔNG THỨC nhịp, không đếm cứng số chữ của câu mẫu)',
        'paragraph_rhythm' =>
            'Công thức phân nhịp đoạn là gì? (đoạn đơn điểm nhấn xen đoạn nhiều câu, mật độ đoạn theo cảm xúc, cách tạo khoảng thở trên mobile)',
        'transitions' =>
            'Nghệ thuật chuyển ý là gì? (từ ngoại cảnh vào nội tâm, chuyển mạch cảm xúc tự nhiên, hay dùng từ nối công nghiệp; điểm neo chuyển cảm xúc ở đâu)',
        'vocabulary' =>
            'Chất liệu từ vựng & thủ pháp giác quan (Show, Don\'t Tell) là gì? (từ láy tượng thanh/tượng hình, tính từ xúc giác-nhiệt độ, tiếng lóng, từ tốc ký, phong cách chọn emoji theo ngữ cảnh)',
        'technical_terms' =>
            'Cách sử dụng và xử lý thuật ngữ chuyên ngành là gì? (đưa thuật ngữ xong giải thoại bằng ví dụ đời thường, hay mặc định độc giả chuyên; độ dày mật độ thuật ngữ)',
        'structure_patterns' =>
            'Khung cấu trúc tổng thể của văn phong là gì? (Hook → Vấn đề → Giải pháp → CTA, tuyến tính câu chuyện, listicle, hỏi–đáp, trò chuyện 1-1...)',
        'headings' =>
            'Phong cách đặt tiêu đề con là gì? (tần suất, câu hỏi hay khẳng định, đánh số hay cụm danh từ, độ dài nhịp tiêu đề)',
        'bullets' =>
            'Vai trò và hình thức gạch đầu dòng là gì? (liệt kê cảm xúc hay tính năng, mỗi bullet một nhịp ngắn, có in đậm mở đầu không)',
        'examples' =>
            'Hình thức dẫn chứng minh họa là gì? (anecdote ngắn giàu chi tiết giác quan, case study số liệu, ví dụ giả định, so sánh trước–sau)',
        'ending' =>
            'Triết lý kết bài là gì? (lời mời ân cần che chở, khoảng lặng lắng đọng để lại dư âm, câu hỏi gợi mở bình luận, CTA trực diện, lời hứa)',
        'avoid' =>
            'Những thói quen hành văn bài mẫu kiên trị tuyệt đối là gì? (sáo rỗng doanh nghiệp, tính từ mơ hồ, câu bị động dài, từ nối cứng, khuyến mãi thổi phồng...)',
        'uncertainties' =>
            'Những khía cạnh nào bài mẫu quá ngắn hoặc không đủ dữ liệu để kết luận chắc chắn? (liệt kê các điểm còn thiếu — để ngỏ, không bịa)',
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
     * Quy tắc lược bỏ theo Tier (PLAN-WRITING-STYLE-PROMPT-V2 §3).
     * Status 2 câu không thể gánh 15 quy tắc — ép sẽ công thức hóa văn
     * bản. Bản đồ:
     *   micro  (1–3 câu) : 7 quy tắc lõi (Hồn & Nhịp câu).
     *   short  (4–10)    : 10 quy tắc (+ Dẫn dắt ngắn).
     *   medium (11–30)   : 14 quy tắc (+ Cấu trúc & Dẫn chứng).
     *   long   (>30)     : 15 quy tắc (đầy đủ cấu trúc).
     * `uncertainties` luôn giữ lại (là báo cáo cho người đọc, KHÔNG được
     * nhúng vào prompt sinh bài — snapshotRules đã loại).
     * --------------------------------------------------------------------- */
    'tier_drop_rules' => [
        'micro'  => ['headings', 'bullets', 'paragraph_rhythm', 'structure_patterns', 'opening', 'transitions', 'technical_terms', 'examples'],
        'short'  => ['headings', 'bullets', 'structure_patterns', 'technical_terms', 'examples'],
        'medium' => ['headings'],
        'long'   => [],
    ],

    /* Số ngày giữ bản ghi phân tích trước khi cleanup (bảo mật text mẫu). */
    'analysis_retention_days' => 30,

    /* Giới hạn độ dài style_instructions (ký tự). */
    'style_instructions_max' => 10000,

    /* ---------------------------------------------------------------------
     * 8 trục sắc thái cảm xúc (Hiệu chỉnh tone) — slider trên UI.
     * Backend dùng để render section TONE CALIBRATION trong system prompt
     * khi Sandbox nhận tone_overrides. Thứ tự cố định theo UI.
     * --------------------------------------------------------------------- */
    'tone_axes' => [
        'humor'         => 'Vui vẻ & Hóm hỉnh',
        'empathy'       => 'Trân trọng & Đồng cảm',
        'assertiveness' => 'Sắc sảo & Quyết đoán',
        'formality'     => 'Trang trọng vs Thân mật',
        'inspiration'   => 'Nhiệt huyết & Truyền lửa',
        'candor'        => 'Chân thực & Bộc trực',
        'depth'         => 'Trí tuệ & Chuyên sâu',
        'urgency'       => 'Khẩn thiết & Kêu gọi',
    ],

    /* ---------------------------------------------------------------------
     * 5 thuộc tính Chất văn (Voice Texture Axes) — Ô 2 & 5 thanh trượt Tab 7.
     * Lưu cố định trong writing_profiles.voice_texture_sliders_json (0..100).
     * --------------------------------------------------------------------- */
    'voice_texture_axes' => [
        'voice_timbre' => [
            'label'     => 'Giọng văn',
            'low_pole'  => 'Khách quan, đĩnh đạc',
            'high_pole' => 'Trầm ấm, thủ thỉ tâm tình',
            'default'   => 70,
        ],
        'vernacular_flow' => [
            'label'     => 'Tính dân dã tự nhiên',
            'low_pole'  => 'Chuẩn mực, trau chuốt',
            'high_pole' => 'Đời thường, thuận tai người Việt',
            'default'   => 65,
        ],
        'emotional_nuance' => [
            'label'     => 'Màu sắc cảm xúc',
            'low_pole'  => 'Tiết chế, điềm tĩnh',
            'high_pole' => 'Sâu lắng, giàu dư âm',
            'default'   => 75,
        ],
        'rhythm_variation' => [
            'label'     => 'Biến thiên nhịp điệu hành văn',
            'low_pole'  => 'Nhịp đều, êm ả',
            'high_pole' => 'Co giãn mạnh (câu dài xen câu cực ngắn)',
            'default'   => 70,
        ],
        'emphasis_glide' => [
            'label'     => 'Tính nhấn và lướt',
            'low_pole'  => 'Phân bổ đồng đều',
            'high_pole' => 'Lướt nhanh ý phụ, zoom-in nhấn sâu chi tiết đắt',
            'default'   => 80,
        ],
    ],

    /* ---------------------------------------------------------------------
     * TOÀN BỘ CÂU CHỮ PROMPT (PLAN-WRITING-STYLE-PROMPT-V2 §2).
     * Tập trung hóa prompt của 3 giai đoạn: giải mã (analyzer), biên dịch
     * system prompt viết bài (compiler), phòng thử DNA (sandbox).
     * Placeholder: {name}, {sentence_hint}, {topic}.
     * --------------------------------------------------------------------- */
    'prompts' => [

        /* 1. Giai đoạn Giải mã bài mẫu — WritingStyleAnalyzerService. */
        'analyzer' => [
            'system' =>
                'Bạn là chuyên gia giải phẫu phong cách viết và phê bình văn học hàng đầu. Nhiệm vụ của bạn là bóc tách trọn vẹn "LINH HỒN & THỦ PHÁP" của tác giả (nhịp điệu, tâm thế, cách xử lý giác quan) và KHÔNG BAO GIỜ bị bám dính vào nội dung cụ thể của bài mẫu. Chỉ trả JSON đúng schema, không thêm lời dẫn.',
            'intro' => [
                'Hãy giải mã DNA văn phong của bài mẫu dưới đây theo phương pháp CONTENT-AGNOSTIC (Tách rời nội dung sự vật, chỉ giữ lại thủ pháp hành văn).',
                'NGUYÊN TẮC QUAN TRỌNG VỀ RULES_JSON:',
                '- Chỉ đúc kết thành nguyên lý, công thức nhịp điệu và thủ pháp gợi cảm xúc mở.',
                '- TUYỆT ĐỐI CẤM đưa tên riêng, bối cảnh bài mẫu (mưa, nắng, cà phê...), hoặc các từ đơn lẻ trong ngoặc kép vào rules_json.',
                '- Trả lời ngắn gọn, cô đọng theo từng khía cạnh dưới đây:',
            ],
            'evidence_instruction' =>
                'Với mỗi khía cạnh, trích 1 câu NGUYÊN VĂN trong bài mẫu làm bằng chứng (evidence_json[].excerpt) — PHẢI là chữ có thật trong bài, không được suy diễn.',
            'style_instructions_guide' =>
                'Hãy biên soạn một Bản chỉ dẫn phong cách (style_instructions - Ô 1) sâu sắc gồm: (1) Chân dung người kể chuyện (Persona Archetype); (2) Thủ pháp khơi gợi giác quan (Show, Don\'t Tell); (3) Sự tiết chế sang trọng (Quiet Luxury); (4) Khoảng cách đối thoại và cách tạo dư âm.',
            'voice_texture_guide' =>
                'Hãy biên soạn thêm Bản chỉ dẫn Chất văn & Độ tự nhiên (voice_texture_instructions - Ô 2) bóc tách sâu 5 thuộc tính cốt lõi của bài mẫu: (1) Giọng văn (âm sắc trò chuyện, độ trầm ấm/sắc sảo); (2) Tính dân dã tự nhiên (cách dùng từ thuận tai người Việt, khẩu ngữ có chọn lọc, tránh mùi văn dịch/AI); (3) Màu sắc cảm xúc (cách gợi cảm xúc qua trải nghiệm thực thay vì tính từ sáo rỗng); (4) Biến thiên nhịp điệu hành văn (quy luật co giãn câu dài - câu ngắn theo mạch cảm xúc); (5) Tính nhấn và lướt (cách lướt nhanh thông tin nền phụ và dừng lại zoom-in nhấn sâu vào 1-2 chi tiết đắt giá). Đồng thời chấm điểm định lượng (0-100) cho 5 thanh trượt trong voice_texture_sliders_json và 8 thanh trượt sắc thái cảm xúc trong tone_sliders_json.',
        ],

        /* 2. Giai đoạn Biên dịch System Prompt viết bài — SnapshotService. */
        'compiler' => [
            'role_template' => 'Bạn là người viết nội dung có văn phong "{name}".',
            'task_template' => 'Hãy viết {sentence_hint} mang trọn vẹn linh hồn, khẩu khí và nhịp thở của văn phong này.',
            'anti_plagiarism' => 'TUYỆT ĐỐI không sao chép nguyên văn bất kỳ câu nào từ bài mẫu — chỉ kế thừa chất liệu và thủ pháp hành văn.',
            'rules_header' => 'Đặc trưng văn phong cần tuân thủ:',
            'style_instructions_label' => 'Hướng dẫn bổ sung (Ô 1 - Hành văn & Chân dung):',
            'voice_texture_label' => 'Chỉ dẫn Chất văn & Nghệ thuật Nhấn – Lướt (Ô 2):',
            'transformation_directives' => [
                'NGUYÊN TẮC SÁNG TÁC (GIỮ CHẤT LIỆU — ĐỔI BỐI CẢNH):',
                '- Kế thừa trọn vẹn: Nhịp thở câu văn, độ sâu cảm xúc, cách xưng hô và thủ pháp gợi tả giác quan.',
                '- Làm mới hoàn toàn: Nếu trong quy tắc có nhắc từ ngữ hay hình ảnh ví dụ từ bài gốc (như mưa rơi, thức uống cụ thể, từ nối ví dụ...), đó CHỈ là minh họa cho thủ pháp. TUYỆT ĐỐI KHÔNG lặp lại khung cảnh hay từ vựng ví dụ đó; hãy tự sáng tạo bối cảnh, âm thanh, ánh sáng và cách mở câu mới phù hợp 100% với chủ đề được yêu cầu.',
            ],
        ],

        /* 3. Giai đoạn Phòng thử DNA — WritingStyleSandboxService. */
        'sandbox' => [
            'temperature' => 0.85,
            'user_prompt_template' =>
                "Chủ đề: {topic}\n\nHãy viết {sentence_hint} về chủ đề trên theo đúng chất liệu văn phong và tâm thế trong hướng dẫn hệ thống.\nHãy tự do sáng tạo hình ảnh giác quan, bối cảnh và cách mở đầu mới mẻ bám sát chủ đề này (không lặp lại cảnh vật hay từ ngữ ví dụ của bài mẫu).\nChỉ trả văn bản hoàn chỉnh, không thêm lời dẫn hay chú thích.",
        ],
    ],
];
