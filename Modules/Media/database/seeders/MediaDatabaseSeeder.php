<?php

namespace Modules\Media\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Seeder gốc của module Media — permission nền.
 * =====================================================================
 * Đồng bộ 2 quyền `media.view`/`media.manage` vào bảng permissions
 * (guard 'admin') — chạy qua module:seed hoặc db:seed khi cần.
 * =====================================================================
 */
class MediaDatabaseSeeder extends Seeder
{
    /**
     * Đồng bộ permission media.
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: INSERT permissions media.view/manage nếu thiếu.
     */
    public function run(): void
    {
        foreach (['media.view', 'media.manage'] as $perm) {
            Permission::findOrCreate($perm, 'admin');
        }
    }
}
