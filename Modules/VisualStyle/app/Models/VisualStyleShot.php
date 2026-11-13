<?php

namespace Modules\VisualStyle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Model góc chụp con của một DNA ảnh (VisualStyle).
 * =====================================================================
 *
 * Mỗi shot là biến thể cách chụp: cho phép ghi đè `composition_override_json`
 * / `camera_override_json` lên DNA gốc của style, thêm `prompt_suffix` riêng
 * và gợi ý `suggested_aspect_ratio`. `code` định danh duy nhất trong phạm
 * vi 1 style (ví dụ 'hero', 'thumbnail', 'b_roll', 'portrait').
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - casts(): Khai báo kiểu cast JSON và số nguyên.
 * - style(): Khai báo quan hệ về VisualStyle cha.
 * - scopeActive(): Lọc shot đang bật (is_active=true).
 * - effectiveAspectRatio(): Trả tỷ lệ được override hoặc fallback.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Thuộc tính visual_style_shots và quan hệ Eloquent.
 * - OUTPUT: Model + scope + helper override.
 *
 * BỐI CẢNH SỬ DỤNG: VisualPromptBuilder đọc shot để lắp prompt 7 lớp và
 * sinh ảnh đúng tỷ lệ; controller CRUD quản lý shots theo style.
 * =====================================================================
 */
class VisualStyleShot extends Model
{
    protected $table = 'visual_style_shots';

    protected $fillable = [
        'visual_style_id',
        'code',
        'name',
        'description',
        'composition_override_json',
        'camera_override_json',
        'prompt_suffix',
        'suggested_aspect_ratio',
        'sort_order',
        'is_active',
    ];

    /**
     * =====================================================================
     * CHỨC NĂNG: Khai báo kiểu cast JSON và số nguyên
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - array tên thuộc tính => kiểu cast
     *
     * SIDE EFFECT:
     * - Được Eloquent dùng khi truy cập thuộc tính; không tự truy vấn DB.
     *
     * EXCEPTION/TRANSACTION:
     * - Không mở transaction.
     * =====================================================================
     */
    protected function casts(): array
    {
        return [
            'composition_override_json' => 'array',
            'camera_override_json'      => 'array',
            'sort_order'                => 'integer',
            'is_active'                 => 'boolean',
        ];
    }

    /* =====================================================================
     * QUAN HỆ
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Khai báo quan hệ về VisualStyle cha
     * =====================================================================
     *
     * INPUT:
     * - Không có tham số; sử dụng state hoặc config của lớp.
     *
     * OUTPUT:
     * - BelongsTo theo visual_style_id
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function style(): BelongsTo
    {
        return $this->belongsTo(VisualStyle::class, 'visual_style_id');
    }

    /* =====================================================================
     * SCOPE
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Lọc shot đang bật
     * =====================================================================
     *
     * INPUT:
     * - $q
     *
     * OUTPUT:
     * - Query builder đã lọc
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính/config và xử lý trong bộ nhớ.
     *
     * EXCEPTION/TRANSACTION:
     * - Không tự mở transaction. Lỗi từ dependency được truyền về caller.
     * =====================================================================
     */
    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /* =====================================================================
     * LOGIC
     * ===================================================================== */

    /**
     * =====================================================================
     * CHỨC NĂNG: Trả tỷ lệ khung hình được override hoặc fallback
     * =====================================================================
     *
     * INPUT:
     * - string $fallback — tỷ lệ mặc định khi shot không khai báo.
     *
     * OUTPUT:
     * - string 'W:H' — tỷ lệ có hiệu lực để sinh ảnh.
     *
     * SIDE EFFECT:
     * - Đọc dữ liệu thuộc tính; không ghi DB.
     *
     * EXCEPTION/TRANSACTION:
     * - Không mở transaction.
     * =====================================================================
     */
    public function effectiveAspectRatio(string $fallback = '16:9'): string
    {
        return $this->suggested_aspect_ratio ?: $fallback;
    }
}
