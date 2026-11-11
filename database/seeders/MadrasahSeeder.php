<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data madrasah DKI Jakarta (sumber: si_presma) dari database/seeders/data/madrasah.sql.
 * Aman dijalankan ulang: madrasah dengan NPSN yang sama diperbarui, bukan diduplikasi.
 *
 * php artisan db:seed --class=MadrasahSeeder
 */
class MadrasahSeeder extends Seeder
{
    public function run(): void
    {
        DB::unprepared(file_get_contents(database_path('seeders/data/madrasah.sql')));
    }
}
