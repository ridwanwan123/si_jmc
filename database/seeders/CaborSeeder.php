<?php

namespace Database\Seeders;

use App\Models\Cabor;
use Illuminate\Database\Seeder;

/**
 * 7 cabor tetap. Nilai `ikon` adalah nama ikon SVG garis (path-nya diambil dari mockup
 * docs/mockup/source/CaborB.dc.html dan akan dipetakan di komponen Blade).
 */
class CaborSeeder extends Seeder
{
    public const DATA = [
        ['urutan' => 1, 'nama' => 'Keagamaan', 'slug' => 'keagamaan', 'ikon' => 'bulan'],
        ['urutan' => 2, 'nama' => 'Sains', 'slug' => 'sains', 'ikon' => 'tabung'],
        ['urutan' => 3, 'nama' => 'Olahraga', 'slug' => 'olahraga', 'ikon' => 'bola'],
        ['urutan' => 4, 'nama' => 'Seni', 'slug' => 'seni', 'ikon' => 'palet'],
        ['urutan' => 5, 'nama' => 'Riset', 'slug' => 'riset', 'ikon' => 'kaca-pembesar'],
        ['urutan' => 6, 'nama' => 'Robotik', 'slug' => 'robotik', 'ikon' => 'robot'],
        ['urutan' => 7, 'nama' => 'GTK', 'slug' => 'gtk', 'ikon' => 'toga'],
    ];

    public function run(): void
    {
        foreach (self::DATA as $row) {
            Cabor::updateOrCreate(['slug' => $row['slug']], $row);
        }
    }
}
