<?php

use App\Enums\KategoriBerita;
use App\Enums\StatusPendaftaran;
use App\Models\Berita;
use App\Models\Hasil;
use App\Models\Lomba;
use App\Models\Pendaftaran;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoJmcSeeder;
use Illuminate\Support\Facades\DB;

it('mengisi juara semua lomba dari pendaftaran terverifikasi, plus berita rekap per cabor', function () {
    $this->withoutVite();
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoJmcSeeder::class);
    $this->seed(DemoJmcSeeder::class); // aman dijalankan ulang

    $kuota = (int) Lomba::query()->sum(DB::raw('kuota_juara_1 + kuota_juara_2 + kuota_juara_3'));

    expect(Hasil::count())->toBe($kuota)
        ->and(Hasil::whereNull('pendaftaran_id')->count())->toBe(0)
        ->and(Pendaftaran::count())->toBe($kuota)
        ->and(Pendaftaran::where('status', '!=', StatusPendaftaran::Diverifikasi->value)->count())->toBe(0)
        // Setiap lomba terisi penuh sesuai kuota masing-masing.
        ->and(Lomba::withCount('hasil')->get()->every(fn (Lomba $l) => $l->hasil_count === $l->kuota_juara_1 + $l->kuota_juara_2 + $l->kuota_juara_3))->toBeTrue()
        // Juara dari madrasah yang jenjangnya sama dengan lomba.
        ->and(Hasil::with(['lomba', 'madrasah'])->get()->every(fn (Hasil $h) => $h->lomba->jenjang === $h->madrasah->jenjang))->toBeTrue()
        ->and(Berita::where('kategori', KategoriBerita::Hasil->value)->count())->toBe(7);

    $this->get('/')->assertOk()->assertSee('Rekap Juara Cabor');
    $this->get('/klasemen')->assertOk();
    $this->get('/berita/rekap-juara-olahraga-2026')->assertOk()->assertSee('Peraih medali emas');
});
