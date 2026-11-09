<?php

use App\Models\Hasil;
use App\Models\Lomba;
use App\Models\Madrasah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Buat $n juara (baris hasil) untuk madrasah di lomba tertentu.
 */
function juara(Lomba $lomba, Madrasah $madrasah, int $peringkat, int $n = 1, ?string $peserta = null): void
{
    Hasil::factory()->count($n)->for($lomba)->for($madrasah)->create([
        'peringkat' => $peringkat,
        'nama_peserta' => $peserta ?? fake()->name(),
    ]);
}
