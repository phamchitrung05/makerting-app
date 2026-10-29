<?php

namespace Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Seeder gốc của module Identity — điều phối seeder con.
 * =====================================================================
 */
class IdentityDatabaseSeeder extends Seeder
{
    /**
     * Gọi IdentityDefaultSeeder để seed role/permission/admin.
     * INPUT: không có tham số. OUTPUT: void.
     * SIDE EFFECT: gọi seeder con.
     */
    public function run(): void
    {
        $this->call(IdentityDefaultSeeder::class);
    }
}
