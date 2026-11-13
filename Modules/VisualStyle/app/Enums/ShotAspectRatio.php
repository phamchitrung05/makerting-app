<?php

namespace Modules\VisualStyle\Enums;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Enum tỷ lệ khung hình gợi ý của góc chụp (shot).
 * =====================================================================
 *
 * Đọc được đồng bộ từ prompt pack (`aspect_ratios`) qua `values()`; các
 * case cố định phản ánh bộ tỷ lệ hỗ trợ mặc định. Driver sinh ảnh map
 * 'W:H' sang kích thước provider cụ thể.
 *
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : string 'W:H' hoặc case.
 * - OUTPUT: enum backed + danh sách giá trị hợp lệ.
 * =====================================================================
 */
enum ShotAspectRatio: string
{
    case Landscape = '16:9'; // YouTube thumbnail, blog hero
    case Portrait  = '9:16'; // Shorts / TikTok / Reels
    case Square    = '1:1';  // Feed vuông, avatar
    case Feed      = '4:5';  // Facebook/Instagram feed dọc nhẹ

    /**
     * CHỨC NĂNG: Mảng giá trị chuỗi hợp lệ.
     * INPUT: không. OUTPUT: string[].
     */
    public static function values(): array
    {
        return array_map(static fn (self $r): string => $r->value, self::cases());
    }

    /**
     * CHỨC NĂNG: Kiểm tra string có phải tỷ lệ hỗ trợ.
     * INPUT: string $ratio. OUTPUT: bool.
     */
    public static function isValid(string $ratio): bool
    {
        return in_array($ratio, self::values(), true);
    }

    /**
     * CHỨC NĂNG: Label hiển thị UI.
     * INPUT: không. OUTPUT: string.
     */
    public function label(): string
    {
        return match ($this) {
            self::Landscape => 'Ngang 16:9',
            self::Portrait  => 'Dọc 9:16',
            self::Square    => 'Vuông 1:1',
            self::Feed      => 'Feed 4:5',
        };
    }
}
