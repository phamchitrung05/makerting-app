<?php

namespace Modules\Seo\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Seeder gốc của module Seo.
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
class SeoDatabaseSeeder extends Seeder
{
    /**
     * =====================================================================
     * CHỨC NĂNG: Điều phối các seeder con của module Seo
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
        // $this->call([]);
    }
}
