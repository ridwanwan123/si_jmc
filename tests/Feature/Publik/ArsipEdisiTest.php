<?php

use App\Enums\Jenjang;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\Lomba;
use App\Models\Madrasah;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(Carbon::create(2026, 11, 6, 10, 0, 0, 'Asia/Jakarta'));

    $this->aktif = Edisi::factory()->aktif()->create([
        'tahun' => 2026, 'nama' => 'JMC 2026', 'tanggal_mulai' => '2026-11-03', 'tanggal_selesai' => '2026-11-07', 'jumlah_madrasah_peserta' => 148,
    ]);
    $this->lama = Edisi::factory()->create([
        'tahun' => 2025, 'nama' => 'JMC 2025', 'tanggal_mulai' => '2025-11-04', 'tanggal_selesai' => '2025-11-08', 'jumlah_madrasah_peserta' => 131,
    ]);

    $this->cabor = Cabor::factory()->create();
    $this->a = Madrasah::factory()->jenjang(Jenjang::MA)->create(['nama' => 'MAN 4 Jakarta']);
    $this->b = Madrasah::factory()->jenjang(Jenjang::MA)->create(['nama' => 'MAN 2 Jakarta']);
});

describe('status edisi', function () {
    it('menghitung status dari tanggal', function () {
        expect($this->aktif->status())->toBe('BERLANGSUNG')
            ->and($this->aktif->selesai())->toBeFalse()
            ->and($this->lama->status())->toBe('SELESAI')
            ->and($this->lama->selesai())->toBeTrue()
            ->and(Edisi::factory()->make(['tanggal_mulai' => '2027-11-01'])->status())->toBe('AKAN DATANG')
            ->and(Edisi::factory()->make(['tanggal_mulai' => null, 'tanggal_selesai' => null, 'is_aktif' => false])->selesai())->toBeTrue();
    });

    it('menampilkan HASIL AKHIR untuk edisi selesai dan DIPERBARUI untuk yang berlangsung', function () {
        juara(Lomba::factory()->for($this->aktif)->for($this->cabor)->create(), $this->a, 1);
        juara(Lomba::factory()->for($this->lama)->for($this->cabor)->create(), $this->a, 1);

        $this->get('/klasemen')->assertOk()->assertSee('DIPERBARUI')->assertDontSee('HASIL AKHIR');
        $this->get('/klasemen?edisi=2025')->assertOk()->assertSee('HASIL AKHIR')->assertDontSee('DIPERBARUI');
        $this->get('/?edisi=2025')->assertOk()->assertSee('SELESAI')->assertSee('HASIL AKHIR');
        $this->get('/')->assertOk()->assertSee('BERLANGSUNG');
    });
});

describe('URL kanonik', function () {
    it('mengarahkan ?edisi=<edisi aktif> ke alamat tanpa parameter, parameter lain tetap', function () {
        $this->get('/klasemen?edisi=2026&jenjang=MA&tab=peraih')
            ->assertStatus(301)
            ->assertRedirect('/klasemen?jenjang=MA&tab=peraih');

        $this->get('/?edisi=2026')->assertRedirect('/');
    });

    it('tidak mengarahkan edisi arsip', function () {
        $this->get('/klasemen?edisi=2025')->assertOk();
    });
});

describe('halaman arsip', function () {
    it('menampilkan semua edisi dengan ringkasan dan juara umum', function () {
        $l1 = Lomba::factory()->for($this->lama)->for($this->cabor)->create();
        $l2 = Lomba::factory()->for($this->lama)->for($this->cabor)->create();
        juara($l1, $this->a, 1);
        juara($l2, $this->a, 1);
        juara($l1, $this->b, 2, 2);
        juara(Lomba::factory()->for($this->aktif)->for($this->cabor)->create(), $this->b, 1);

        $this->get('/arsip')->assertOk()
            ->assertSee('2 EDISI')
            ->assertSeeInOrder(['2026', 'AKTIF', 'JMC 2026', 'PEMUNCAK SEMENTARA', 'MAN 2 Jakarta', '2025', 'HASIL AKHIR', 'JMC 2025', 'JUARA UMUM', 'MAN 4 Jakarta'])
            ->assertSee('2 E · 0 P · 0 P')
            ->assertSee('/klasemen?edisi=2025', false);
    });

    it('menampilkan semua madrasah yang seri di peringkat 1', function () {
        $l = Lomba::factory()->for($this->lama)->for($this->cabor)->kuota(2, 2, 3)->create();
        juara($l, $this->a, 1);
        juara($l, $this->b, 1);

        $this->get('/arsip')->assertOk()->assertSee('MAN 4 Jakarta')->assertSee('MAN 2 Jakarta');
    });

    it('menandai edisi yang belum punya data', function () {
        Edisi::factory()->create(['tahun' => 2027, 'nama' => 'JMC 2027', 'tanggal_mulai' => '2027-11-02']);

        $this->get('/arsip')->assertOk()->assertSee('JMC 2027')->assertSee('BELUM ADA DATA')->assertSee('Belum ada hasil.');
    });

    it('bisa dibuka dari footer dan dropdown edisi', function () {
        $this->get('/')->assertOk()->assertSee('Semua edisi →')->assertSee(route('arsip'), false);
    });

    it('tidak menjalankan query per baris klasemen', function () {
        $hitung = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get('/arsip')->assertOk();

            return tap(count(DB::getQueryLog()), fn () => DB::disableQueryLog());
        };

        $sebelum = $hitung();

        $banyak = Madrasah::factory()->count(10)->jenjang(Jenjang::MA)->create();
        foreach ($banyak as $m) {
            juara(Lomba::factory()->for($this->lama)->for($this->cabor)->create(), $m, 1);
        }

        expect($hitung())->toBe($sebelum);
    });
});

describe('edisi kosong', function () {
    it('menampilkan pesan belum ada data di semua halaman edisi itu', function () {
        Edisi::factory()->create(['tahun' => 2027, 'nama' => 'JMC 2027']);

        foreach (['/', '/klasemen', '/cabor', '/madrasah', '/galeri'] as $url) {
            $this->get($url.'?edisi=2027')->assertOk()
                ->assertSee('Belum ada data lomba untuk JMC 2027.');
        }

        // Edisi aktif yang sudah punya lomba tidak menampilkan pesan itu.
        Lomba::factory()->for($this->aktif)->for($this->cabor)->create();
        $this->get('/klasemen')->assertOk()->assertDontSee('Belum ada data lomba untuk');
    });
});
