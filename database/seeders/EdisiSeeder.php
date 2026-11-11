<?php

namespace Database\Seeders;

use App\Models\Edisi;
use App\Services\LombaService;
use Illuminate\Database\Seeder;

/**
 * Edisi aktif JMC 2026 beserta lombanya (semua mata lomba aktif).
 * Butuh MataLombaSeeder. Aman dijalankan ulang.
 */
class EdisiSeeder extends Seeder
{
    public function run(): void
    {
        $edisi = Edisi::updateOrCreate(['tahun' => 2026], [
            'nama' => 'JMC 2026',
            'tanggal_mulai' => '2026-11-01',
            'tanggal_selesai' => '2026-12-31',
            'lokasi' => 'Jakarta',
            'pendaftaran_buka' => '2026-11-09',
            'pendaftaran_tutup' => '2026-11-16',
            'is_aktif' => true,
        ]);

        app(LombaService::class)->sinkronEdisiAktif();

        $this->command?->info("{$edisi->nama}: {$edisi->lomba()->count()} lomba.");
    }
}
