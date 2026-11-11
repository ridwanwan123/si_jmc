<?php

use App\Enums\Kota;
use App\Models\Madrasah;
use Database\Seeders\MadrasahSeeder;

it('mengisi data madrasah dari SQL dan aman dijalankan ulang', function () {
    $this->seed(MadrasahSeeder::class);
    $this->seed(MadrasahSeeder::class);

    $mts = Madrasah::where('npsn', '20178214')->first();

    expect(Madrasah::count())->toBe(1815)
        ->and($mts->nama)->toBe('MTSN 26 JAKARTA')
        ->and($mts->kota)->toBe(Kota::KepulauanSeribu)
        ->and($mts->kelurahan)->toBe('PULAU TIDUNG');
});
