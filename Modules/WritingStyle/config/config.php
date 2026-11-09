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
            'default'   => 50,
        ],
        'vernacular_flow' => [
            'label'     => 'Tính dân dã tự nhiên',
            'low_pole'  => 'Chuẩn mực, trau chuốt',
            'high_pole' => 'Đời thường, thuận tai người Việt',
            'default'   => 50,
        ],
        'emotional_nuance' => [
            'label'     => 'Màu sắc cảm xúc',
            'low_pole'  => 'Tiết chế, điềm tĩnh',
            'high_pole' => 'Sâu lắng, giàu dư âm',
            'default'   => 50,
        ],
        'rhythm_variation' => [
            'label'     => 'Biến thiên nhịp điệu hành văn',
            'low_pole'  => 'Nhịp đều, êm ả',
            'high_pole' => 'Co giãn mạnh (câu dài xen câu cực ngắn)',
            'default'   => 50,
        ],
        'emphasis_glide' => [
            'label'     => 'Tính nhấn và lướt',
            'low_pole'  => 'Phân bổ đồng đều',
            'high_pole' => 'Lướt nhanh ý phụ, zoom-in nhấn sâu chi tiết đắt',
            'default'   => 50,
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
            'tone_instructions_label' => 'Chỉ dẫn Sắc thái & Phối âm cảm xúc (Ô 3):',
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
            /* Nhãn dòng đầu khối prompt gom kết quả các bước phân tích đứng
             * trước (upstream PromptBlock) — ArticleWriterStep chèn phần này
             * trước các compileToPromptBlock() của các Step trước đó. */
            'upstream_blocks_header' =>
                'Kết quả phân tích các bước trước (đã được người dùng duyệt/chỉnh sửa trên UI):',
        ],

        /* 4. Prompt riêng của từng Luồng nhỏ (Sandbox Pipeline Steps). */
        'steps' => [
            'topic_outline' => [
                'temperature' => 0.4,
                'system' =>
                    'Bạn là Giám đốc Chiến lược Nội dung & Tổng biên tập. Phân tích chủ đề để rút ra luận điểm cốt lõi sắc bén (thesis), góc tiếp cận độc giả mục tiêu (target_angle) và cây dàn ý H2/H3 thực chiến, mạch lạc, không rập khuôn — đúng tầm vóc của hồ sơ văn phong được cấp. Chỉ trả JSON đúng schema, không thêm lời dẫn.',
                'user_prompt_template' =>
                    "Chủ đề cần phân tích: {topic}\nCấp độ độ dài đích: {tier_label} ({sentence_hint})\nHồ sơ văn phong: {profile_name}\n\nHãy trả luận điểm cốt lõi, góc tiếp cận độc giả và cây dàn ý H2/H3 kèm luận cứ + gợi ý dẫn chứng cho từng mục.",
            ],
        ],
    ],

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
