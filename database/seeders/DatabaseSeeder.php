<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Database\Seeders\IdentityDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Gọi seeder của module Identity để tạo super-admin + role/permission.
        $this->call(IdentityDatabaseSeeder::class);
    }
}
