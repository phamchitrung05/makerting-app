<?php

namespace Modules\WritingStyle\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Seeder gốc của module WritingStyle.
 * =====================================================================
 *
 * Điểm vào gọi seeder con khi chạy module:seed hoặc db:seed; đăng ký seeder
 * nghiệp vụ trong run() khi module có dữ liệu mẫu.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - run(): điều phối gọi các seeder con
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : không có tham số
 * - OUTPUT: void — các seeder con gọi tuần tự
 * =====================================================================
 */
class WritingStyleDatabaseSeeder extends Seeder
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Điều phối các seeder con của module WritingStyle
     * =====================================================================
     *
     * INPUT:
     * - không có tham số
     *
     * OUTPUT:
     * - void — gọi seeder con qua $this->call()
     *
     * SIDE EFFECT:
     * - ghi dữ liệu mẫu vào database khi seed
     * =====================================================================
     */
    public function run(): void
    {
        // Module không có bảng seed tĩnh — 16 RULE_KEYS + tiers nằm trong
        // config/writing-style.php (source of truth đọc bởi WritingProfileDefinition).
        // Thêm seeder nghiệp vụ vào đây khi cần.
    }
}
