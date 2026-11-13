<?php

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Prompt Pack V1 — bộ cấu hình prompt tự chứa cho
 * hệ thống Giải mã DNA Ảnh & Sinh ảnh 7 lớp (VisualStyle).
 * =====================================================================
 *
 * Mọi phiên bản prompt (v1, v2, v3...) đều đóng gói theo KHUNG KEY THỐNG
 * NHẤT (Prompt Pack) để code rẽ nhánh theo NĂNG LỰC khai báo
 * (`capabilities`), không theo số version. Thêm phiên bản mới chỉ cần
 * copy file này → đổi `pack_version` → chỉnh câu chữ/thêm key → đổi env.
 *
 * Khung key chuẩn:
 *   - pack_version / extends      : định danh + kế thừa (deep-merge)
 *   - capabilities                : năng lực của pack — code check
 *                                    `cap('tên')` thay vì so version
 *   - limits                      : giới hạn context/output chung
 *   - temperatures                : nhiệt độ riêng theo vai trò gọi AI
 *   - dna_sections                : 5 nhóm DNA bắt buộc (thứ tự cố định)
 *   - aspect_ratios               : tỷ lệ khung hình hỗ trợ
 *   - prompts                     : 2 vai trò gọi AI (mỗi vai trò 1 chuỗi system)
 *   - specialties                 : overlay theo mục đích (thumbnail|hero|b_roll|portrait)
 *
 * Nạp vào config qua `config('visualstyle.v1.*')` — xem config/config.php.
 * =====================================================================
 */

return [

    /* ---------------------------------------------------------------------
     * ĐỊNH DANH & KẾ THỪA
     * ---------------------------------------------------------------------
     * `pack_version`: số phiên bản của pack này.
     * `extends`: tên pack cha ('v1' | 'v2' | ...) để kế thừa/deep-merge;
     *            null = pack gốc không kế thừa.
     * --------------------------------------------------------------------- */
    'pack_version' => 1,
    'extends' => null,

    /* ---------------------------------------------------------------------
     * NĂNG LỰC (Capabilities) — code rẽ nhánh theo ĐÂY, không theo số.
     * Mỗi cờ báo pack này hỗ trợ hợp đồng dữ liệu nào:
     *   - structured_analysis : analyzer trả 5 nhóm DNA + confidence (JSON schema)
     *   - seven_layer_prompt  : builder ghép đúng 7 lớp theo thứ tự cố định
     *   - shot_overrides      : shot được ghi đè composition/camera lên DNA
     *   - reference_image     : gửi reference_media_id làm subject preservation
     * --------------------------------------------------------------------- */
    'capabilities' => [
        'structured_analysis' => true,
        'seven_layer_prompt'  => true,
        'shot_overrides'      => true,
        'reference_image'     => true,
    ],

    /* ---------------------------------------------------------------------
     * GIỚI HẠN CHUNG
     * --------------------------------------------------------------------- */
    'limits' => [
        'max_context_chars' => 40000,   // tổng ký tự prompt builder
        'max_output_tokens' => 4096,    // analyzer output tokens
    ],

    /* ---------------------------------------------------------------------
     * NHIỆT ĐỘ THEO VAI TRÒ — analyzer cần nhất quán (chấm DNA), builder
     * gợi mở nhẹ, image sáng tạo nhất.
     * --------------------------------------------------------------------- */
    'temperatures' => [
        'analyzer'       => 0.2,
        'prompt_builder' => 0.4,
        'image'          => 0.7,
    ],

    /* ---------------------------------------------------------------------
     * 5 NHÓM DNA BẮT BUỘC — thứ tự cố định khi ghép prompt 7 lớp.
     * Mỗi nhóm là enum có giá trị cho phép (validate ở
     * VisualStyleDnaDefinition::validateDna).
     * --------------------------------------------------------------------- */
    'dna_sections' => [
        'composition',
        'camera',
        'lighting',
        'environment',
        'color_mood',
    ],

    /* ---------------------------------------------------------------------
     * TỶ LỆ KHUNG HÌNH HỖ TRỢ — driver sinh ảnh map 'W:H' sang kích thước
     * provider. Đồng bộ enum ShotAspectRatio.
     * --------------------------------------------------------------------- */
    'aspect_ratios' => ['16:9', '9:16', '1:1', '4:5'],

    /* ---------------------------------------------------------------------
     * ĐỊNH NGHĨA 5 NHÓM DNA — key enum cho phép + nhãn tiếng Việt + mô tả
     * nhỏ phục vụ analyzer mô tả và builder biên dịch thành câu tự nhiên.
     * --------------------------------------------------------------------- */
    'dna_fields' => [
        'composition' => [
            'label' => 'Bố cục',
            'fields' => [
                'camera_angle' => [
                    'label'   => 'Góc máy',
                    'values'  => ['low_angle', 'eye_level', 'high_angle', 'overhead', 'dutch_angle', 'macro'],
                    'hint'    => 'low_angle: chụp từ thấp nâng chủ thể | eye_level: ngang mắt | overhead: từ trên xuống | dutch_angle: nghiêng tạo căng thẳng',
                ],
                'framing_rule' => [
                    'label'   => 'Quy tắc khung hình',
                    'values'  => ['rule_of_thirds', 'golden_ratio', 'centered', 'diagonal', 'frame_within_frame'],
                    'hint'    => 'rule_of_thirds: chủ thể ở 1/3 khung | centered: đối xứng tâm | frame_within_frame: có khung lồng khung',
                ],
                'depth_of_field' => [
                    'label'   => 'Độ sâu trường ảnh',
                    'values'  => ['shallow_dof', 'deep', 'bokeh', 'tilt_shift'],
                    'hint'    => 'shallow_dof: xóa phông hậu cảnh | deep: sắc nét toàn khung | bokeh: vòng tròn sáng hậu cảnh',
                ],
            ],
        ],
        'camera' => [
            'label' => 'Thiết bị & khung chụp',
            'fields' => [
                'lens_type' => [
                    'label'   => 'Ống kính',
                    'values'  => ['24mm', '35mm', '50mm', '85mm', '135mm', 'macro', 'fisheye'],
                    'hint'    => '35mm: nhìn tự nhiên | 85mm: chân dung nén hậu cảnh | macro: siêu gần',
                ],
                'shot_medium' => [
                    'label'   => 'Chất liệu ảnh',
                    'values'  => ['photograph', 'digital', 'film', 'editorial'],
                    'hint'    => 'photograph: ảnh thật | film: giấy ảnh analog hạt | editorial: ảnh tạp chí',
                ],
                'perspective' => [
                    'label'   => 'Khoảng cách chụp',
                    'values'  => ['extreme_close_up', 'close_up', 'medium', 'wide', 'extreme_wide'],
                    'hint'    => 'close_up: chi tiết khuôn mặt/sản phẩm | wide: toàn cảnh bối cảnh',
                ],
            ],
        ],
        'lighting' => [
            'label' => 'Ánh sáng',
            'fields' => [
                'tone' => [
                    'label'   => 'Tông ánh sáng',
                    'values'  => ['warm_golden', 'moody_cinematic', 'neon', 'studio_clean', 'soft_diffused', 'high_key', 'low_key'],
                    'hint'    => 'warm_golden: vàng ấm giờ hoàng hôn | moody_cinematic: u tối điện ảnh | studio_clean: sạch phòng thu',
                ],
                'key_light' => [
                    'label'   => 'Nguồn sáng chính',
                    'values'  => ['rim_light', 'backlight', 'softbox', 'natural', 'split_light', 'rembrandt'],
                    'hint'    => 'rim_light: viền sáng tách chủ thể | backlight: ngược sáng | softbox: khuếch tán đều',
                ],
            ],
        ],
        'environment' => [
            'label' => 'Bối cảnh',
            'fields' => [
                'setting' => [
                    'label'   => 'Môi trường',
                    'values'  => ['studio', 'outdoor', 'interior', 'abstract', 'urban', 'nature'],
                    'hint'    => 'studio: nền phẳng kiểm soát | outdoor: ngoài trời | interior: trong nhà',
                ],
                'backdrop' => [
                    'label'   => 'Hậu cảnh',
                    'values'  => [], // chuỗi tự do — mô tả của analyzer
                    'hint'    => 'mô tả nền phía sau chủ thể (màu sắc, vật thể, không gian)',
                ],
                'atmosphere' => [
                    'label'   => 'Bầu không khí không khí',
                    'values'  => ['volumetric_fog', 'haze', 'clean', 'smoke', 'dust_particles', 'rain'],
                    'hint'    => 'volumetric_fog: sương khối có tia sáng | clean: không khí trong trẻo',
                ],
            ],
        ],
        'color_mood' => [
            'label' => 'Màu sắc & cảm xúc màu',
            'fields' => [
                'palette_hex' => [
                    'label'   => 'Bảng màu',
                    'values'  => [], // mảng mã hex ['#...', ...] 3–6 màu
                    'hint'    => '3–6 mã màu chủ đạo dạng #RRGGBB trích từ ảnh',
                ],
                'temperature' => [
                    'label'   => 'Nhiệt độ màu',
                    'values'  => ['warm', 'cool', 'neutral'],
                    'hint'    => 'warm: vàng/cam | cool: xanh/lam | neutral: cân bằng',
                ],
                'contrast' => [
                    'label'   => 'Tương phản',
                    'values'  => ['high', 'low', 'muted'],
                    'hint'    => 'high: tương phản gắt nổi bật | muted: dịu, xám trung tính',
                ],
                'grade' => [
                    'label'   => 'Grade màu',
                    'values'  => ['teal_orange', 'desaturated', 'vivid', 'pastel', 'monochrome', 'sepia'],
                    'hint'    => 'teal_orange: xanh cam điện ảnh | desaturated: bão hòa thấp | vivid: rực rỡ',
                ],
            ],
        ],
    ],

    /* ---------------------------------------------------------------------
     * 2 VAI TRÒ GỌI AI — mỗi vai trò 1 chuỗi system prompt.
     * --------------------------------------------------------------------- */
    'prompts' => [

        /* ANALYZER — Vision: mổ xẻ ảnh tham chiếu → dna_json (JSON schema). */
        'analyzer' => <<<'PROMPT'
Bạn là chuyên gia nhiếp ảnh phân tích ảnh tham chiếu để trích xuất DNA nhiếp ảnh.
Quan sát kỹ ảnh và điền đầy đủ 5 nhóm: composition, camera, lighting, environment, color_mood.
Mỗi nhóm chỉ dùng giá trị enum được khai báo trong schema. Trường không đoán được phải trả null — không tự bịa.
palette_hex trích 3–6 mã màu chủ đạo (#RRGGBB) nhìn thấy thật trên ảnh, sắp theo diện tích giảm dần.
subject_rules liệt kê gì cần bảo toàn khi sinh ảnh mới (logo, khuôn mặt, hình dáng sản phẩm, chữ) và gì cấm xuất hiện (deformed_hands, watermark, extra_fingers...).
style_constraints là mô tả 1–3 câu về chất ảnh tổng thể cần giữ nguyên khi tái tạo.
confidence (0.0–1.0) đánh giá độ chắc chắn của toàn bộ kết quả — ảnh rõ, đúng chủ đề thì cao; mờ, nhiều ý thì thấp.
taxonomy_suggestion đề xuất tối đa 5 tag/category ngắn gọn (tiếng Việt) phân loại style này.
Chỉ trả JSON đúng schema. Không thêm nhận xét ngoài JSON.
PROMPT,

        /* PROMPT_BUILDER — Frame instruction mở đầu prompt ảnh (lớp định
         * hướng): chuỗi ngắn bằng tiếng Anh đặt trước chủ thể, ví dụ
         * "A professional photograph". Specialties có thể ghi đè theo
         * purpose (thumbnail → high-impact composition...). */
        'prompt_builder' => 'A professional photograph',
    ],

    /* ---------------------------------------------------------------------
     * SPECIALTIES — overlay theo mục đích sinh ảnh (purpose).
     * Mỗi nhánh chỉ ghi đè phần khác với base; key lạ được giữ lại để pack
     * con/v2 thêm năng lực mà không vỡ code cũ.
     *
     * Ví dụ:
     *   'specialties' => [
     *       'thumbnail' => [
     *           'prompts' => ['prompt_builder' => '...yêu cầu thêm: tương phản cao, chữ ngắn dễ đọc...'],
     *       ],
     *   ]
     * Resolver sẽ deep-merge specialty -> base khi caller truyền $purpose khớp.
     * --------------------------------------------------------------------- */
    'specialties' => [
        // 'thumbnail' => [...],
        // 'hero' => [...],
        // 'b_roll' => [...],
        // 'portrait' => [...],
    ],
];
