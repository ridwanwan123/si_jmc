<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Data awal: 7 cabor, akun superadmin, madrasah DKI Jakarta, katalog 92 mata lomba,
 * dan edisi aktif JMC 2026 beserta lombanya.
 * Data contoh (juara, pendaftaran, berita) terpisah: php artisan db:seed --class=DemoJmcSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CaborSeeder::class,
            AdminSeeder::class,
            MadrasahSeeder::class,
            MataLombaSeeder::class,
            EdisiSeeder::class,
        ]);
    }
}
