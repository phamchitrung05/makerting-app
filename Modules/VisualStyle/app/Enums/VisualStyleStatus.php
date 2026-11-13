<?php

namespace Modules\VisualStyle\Enums;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Enum vòng đời của một Visual Style (DNA ảnh).
 * =====================================================================
 *
 * Theo spec P2-03: draft → analyzing → analyzed → active → archived
 * (failed nếu job phân tích lỗi). Chỉ `active` + `is_enabled=true` được
 * dùng để sinh ảnh từ DNA. Trạng thái `analyzed` = đã giải mã xong nhưng
 * chưa được kích hoạt (chờ duyệt/lưu).
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : tên case hoặc string DB.
 * - OUTPUT: enum backed + helper trạng thái.
 * =====================================================================
 */
enum VisualStyleStatus: string
{
    case Draft     = 'draft';     // tạo tay, chưa giải mã
    case Analyzing = 'analyzing'; // job Vision đang chạy
    case Analyzed  = 'analyzed';  // giải mã xong, chờ duyệt/kích hoạt
    case Active    = 'active';    // sẵn sàng dùng sinh ảnh
    case Archived  = 'archived';  // lưu trữ, không dùng nữa
    case Failed    = 'failed';    // job giải mã lỗi vĩnh viễn

    /**
     * CHỨC NĂNG: Các trạng thái cho phép sinh ảnh (đã có DNA).
     * INPUT: không. OUTPUT: self[].
     */
    public static function usable(): array
    {
        return [self::Analyzed, self::Active];
    }

    /**
     * CHỨC NĂNG: Đã xong vòng đời (không còn chuyển trạng thái).
     * INPUT: không. OUTPUT: bool.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Archived, self::Failed], true);
    }

    /**
     * CHỨC NĂNG: Label hiển thị UI.
     * INPUT: không. OUTPUT: string tiếng Việt.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Nháp',
            self::Analyzing => 'Đang giải mã',
            self::Analyzed  => 'Đã giải mã',
            self::Active    => 'Hoạt động',
            self::Archived  => 'Lưu trữ',
            self::Failed    => 'Thất bại',
        };
    }
}
