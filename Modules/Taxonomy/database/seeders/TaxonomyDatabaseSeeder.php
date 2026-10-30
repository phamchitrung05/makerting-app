<?php

namespace Modules\Taxonomy\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Seeder gốc của module Taxonomy — permission nền.
 * =====================================================================
 */
class TaxonomyDatabaseSeeder extends Seeder
{
    /**
     * Đồng bộ permission taxonomy.
     * INPUT: không. OUTPUT: void.
     * SIDE EFFECT: INSERT permissions taxonomy.view/manage nếu thiếu.
     */
    public function run(): void
    {
        foreach (['taxonomy.view', 'taxonomy.manage'] as $perm) {
            Permission::findOrCreate($perm, 'admin');
        }
    }
}
