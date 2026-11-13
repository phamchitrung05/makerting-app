<?php

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Cấu hình chung module VisualStyle + chuyển phiên bản prompt.
 * =====================================================================
 * Giải mã ảnh thành DNA nhiếp ảnh 5 nhóm + góc chụp, ghép prompt 7 lớp
 * sinh ảnh đồng bộ thương hiệu. File này chỉ giữ SETUP CHUNG — toàn bộ
 * câu chữ prompt của từng phiên bản nằm gọn trong thư mục prompts/:
 *
 *   prompts/v1.php   → Prompt Pack V1 (đang dùng)
 *   prompts/v2.php   → (tương lai — copy v1.php, đổi pack_version, chỉnh chữ)
 *
 * Chọn phiên bản chạy bằng env VISUAL_STYLE_PROMPT_VERSION; bảng
 * `prompt_packs` map số phiên bản → key config. Code đọc pack qua
 * Modules\VisualStyle\Services\VisualPromptPack (một cửa, rẽ nhánh theo
 * capabilities). Truy cập bằng config('visualstyle.*')
 * (alias module = 'visualstyle').
 * =====================================================================
 */

return [
    'name' => 'VisualStyle',

    /* ---------------------------------------------------------------------
     * CHUYỂN PHIÊN BẢN PROMPT (Prompt Pack).
     * `prompt_version` — số phiên bản hiện hành (env, mặc định 1).
     * `prompt_packs`   — map số phiên bản → key config nạp từ prompts/.
     * --------------------------------------------------------------------- */
    'prompt_version' => (int) env('VISUAL_STYLE_PROMPT_VERSION', 1),
    'prompt_packs' => [
        1 => 'v1',
        // 2 => 'v2',  // bỏ comment khi tạo prompts/v2.php
    ],
    'v1' => require __DIR__.'/../prompts/v1.php',

    /* ---------------------------------------------------------------------
     * 5 NHÓM DNA BẮT BUỘC — mirror pack (dùng khi pack chưa nạp được).
     * Định nghĩa chi tiết từng nhóm nằm trong prompt pack (v1.dna_fields).
     * --------------------------------------------------------------------- */
    'dna_sections' => [
        'composition',
        'camera',
        'lighting',
        'environment',
        'color_mood',
    ],

    /* ---------------------------------------------------------------------
     * TỶ LỆ KHUNG HÌNH HỖ TRỢ — mirror pack. Đồng bộ enum ShotAspectRatio.
     * --------------------------------------------------------------------- */
    'aspect_ratios' => ['16:9', '9:16', '1:1', '4:5'],

    /* Giới hạn kích thước ảnh tham chiếu nạp vào Vision (base64) — byte.
     * Vượt giới hạn nén lại hoặc từ chối (bảo vệ context + chi phí). */
    'analysis' => [
        'max_reference_bytes' => 8 * 1024 * 1024, // 8 MB
        'retention_days'      => 0, // dna lưu vĩnh viễn trên visual_styles
        'job' => [
            'timeout' => 300,
            'tries'   => 2,
        ],
    ],

    /* Giới hạn sinh ảnh (mặc định dùng pack limits cho prompt). */
    'generation' => [
        'default_aspect_ratio' => '16:9',
        'folder'               => 'root',
        'visibility'           => 'public',
    ],
];
