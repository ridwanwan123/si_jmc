<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Data awal yang wajib ada: 7 cabor dan akun admin.
 * Edisi, mata lomba, madrasah, lomba, hasil, dan galeri diisi lewat panel admin.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CaborSeeder::class,
            AdminSeeder::class,
        ]);
    }
}
