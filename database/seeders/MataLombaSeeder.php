<?php

namespace Database\Seeders;

use App\Models\Cabor;
use App\Models\MataLomba;
use Illuminate\Database\Seeder;

/**
 * Katalog 92 mata lomba JMC dari database/seeders/data/mata_lomba.php.
 * Butuh CaborSeeder. Aman dijalankan ulang (kunci: cabor + nama + jenjang + kategori).
 * Pengelola mata lomba GTK ditebak dari namanya (MataLomba::tebakPengelolaGtk).
 */
class MataLombaSeeder extends Seeder
{
    public function run(): void
    {
        $cabor = Cabor::query()->pluck('id', 'slug');

        foreach (require database_path('seeders/data/mata_lomba.php') as $m) {
            MataLomba::updateOrCreate([
                'cabor_id' => $cabor[$m['cabor']],
                'nama' => $m['nama'],
                'jenjang' => $m['jenjang'],
                'kategori' => $m['kategori'],
            ], [
                'jumlah_anggota' => $m['jumlah_anggota'],
                'kuota_juara_1' => $m['kuota'][0],
                'kuota_juara_2' => $m['kuota'][1],
                'kuota_juara_3' => $m['kuota'][2],
                'urutan' => $m['urutan'],
                'is_aktif' => $m['is_aktif'],
            ]);
        }
    }
}
