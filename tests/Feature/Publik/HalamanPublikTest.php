<?php

use App\Enums\Jenjang;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\Hasil;
use App\Models\Lomba;
use App\Models\Madrasah;
use Database\Seeders\CaborSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(CaborSeeder::class);

    $this->edisi = Edisi::factory()->aktif()->create(['tahun' => 2026, 'nama' => 'JMC 2026']);
    $this->lama = Edisi::factory()->create(['tahun' => 2025, 'nama' => 'JMC 2025']);
    $this->olahraga = Cabor::where('slug', 'olahraga')->first();
    $this->sains = Cabor::where('slug', 'sains')->first();

    $this->man4 = Madrasah::factory()->jenjang(Jenjang::MA)->create(['nama' => 'MAN 4 Jakarta', 'npsn' => '131131710004']);
    $this->mtsn3 = Madrasah::factory()->jenjang(Jenjang::MTs)->create(['nama' => 'MTsN 3 Jakarta', 'npsn' => '121131710003']);
    $this->min1 = Madrasah::factory()->jenjang(Jenjang::MI)->create(['nama' => 'MIN 1 Jakarta', 'npsn' => '111131710001']);

    $this->tenisMeja = Lomba::factory()->for($this->edisi)->for($this->olahraga)->create(['nama' => 'Tenis Meja', 'jenjang' => 'MTs', 'kategori' => 'Putri']);
    $this->ksm = Lomba::factory()->for($this->edisi)->for($this->sains)->create(['nama' => 'KSM Matematika', 'jenjang' => 'MA', 'kategori' => 'Umum']);
    $this->belum = Lomba::factory()->for($this->edisi)->for($this->sains)->create(['nama' => 'KSM Biologi', 'jenjang' => 'MA', 'kategori' => 'Umum']);
});

/** Juara ganda: 1 emas, 2 perak, 1 perunggu di Tenis Meja; 1 emas di KSM. */
function isiHasilContoh($t): void
{
    juara($t->tenisMeja, $t->mtsn3, 1, peserta: 'Siti Aminah');
    juara($t->tenisMeja, $t->man4, 2, peserta: 'Ani');
    juara($t->tenisMeja, $t->min1, 2, peserta: 'Rina');
    juara($t->tenisMeja, $t->man4, 3, peserta: 'Dewi');
    juara($t->ksm, $t->man4, 1, peserta: 'Ahmad Fauzi');
}

describe('edisi', function () {
    it('memakai edisi aktif tanpa parameter dan arsip dengan ?edisi=', function () {
        $this->get('/klasemen')->assertOk()->assertSee('JMC 2026')->assertDontSee('Anda melihat hasil');
        $this->get('/klasemen?edisi=2025')->assertOk()->assertSee('Anda melihat hasil JMC 2025');
    });

    it('menampilkan 404 untuk tahun edisi yang tidak dikenal', function () {
        $this->get('/klasemen?edisi=1999')->assertNotFound();
        $this->get('/?edisi=abc')->assertNotFound();
    });

    it('membawa ?edisi= di link saat melihat arsip', function () {
        $this->get('/cabor?edisi=2025')->assertOk()->assertSee('/klasemen?edisi=2025', false);
    });

    it('tetap menampilkan semua halaman jika belum ada edisi sama sekali', function () {
        Edisi::query()->delete();
        $tahun = now(config('jmc.timezone'))->year;

        foreach (['/', '/klasemen', '/klasemen?tab=peraih', '/cabor', '/cabor/sains', '/madrasah', '/galeri', '/arsip', '/daftar'] as $url) {
            $this->get($url)->assertOk()->assertSee('JMC '.$tahun);
        }

        $this->get('/')
            ->assertSee('PENDAFTARAN SEGERA DIBUKA')
            ->assertSee('Daftar sekarang')
            ->assertSee('Belum ada hasil yang diumumkan.')
            ->assertSee('SEGERA')
            ->assertDontSee('PODIUM SEMENTARA');
        $this->get('/cabor/sains')->assertSee('akan tampil di sini setelah diumumkan panitia');
        // ?edisi= yang tidak dikenal tetap 404.
        $this->get('/?edisi=2020')->assertNotFound();
    });
});

describe('beranda', function () {
    it('menampilkan podium, ticker, angka ringkas, klasemen, dan 7 cabor', function () {
        isiHasilContoh($this);

        $this->get('/')
            ->assertOk()
            ->assertSee('PODIUM SEMENTARA')
            ->assertSee('HASIL')
            ->assertSeeInOrder(['MAN 4 Jakarta', 'MTsN 3 Jakarta', 'MIN 1 Jakarta'])
            ->assertSee('Medali dibagikan')
            ->assertSee('7 Cabor')
            ->assertSee('Keagamaan')
            ->assertSee('GTK');
    });

    it('menampilkan daftar lomba per jenjang di baris cabor yang bisa dibuka (bukan pemuncak)', function () {
        $keagamaan = Cabor::where('slug', 'keagamaan')->first();
        Lomba::factory()->for($this->edisi)->for($keagamaan)->create(['nama' => 'MTQ', 'jenjang' => 'MA', 'kategori' => 'Putra']);
        Lomba::factory()->for($this->edisi)->for($keagamaan)->create(['nama' => 'Adzan', 'jenjang' => 'MI', 'kategori' => 'Putra']);
        isiHasilContoh($this);

        $this->get('/')
            ->assertOk()
            ->assertSee('Klik cabor untuk melihat daftar lombanya')
            ->assertSee('aria-controls="lomba-cabor-'.$keagamaan->id.'"', false)
            // Di dalam baris Keagamaan: jenjang terurut MI lalu MA, masing-masing dengan lombanya.
            ->assertSeeInOrder(['Keagamaan', 'MI · MA', '2 lomba', 'MI', 'Adzan', 'Putra', 'MA', 'MTQ', 'Putra', 'Lihat juara Keagamaan'])
            ->assertSeeInOrder(['Sains', 'MA', 'KSM Biologi', 'Umum', 'KSM Matematika'])
            ->assertSee('Belum ada lomba di edisi ini.') // cabor tanpa lomba, mis. Robotik
            ->assertDontSee('Pemuncak');
    });

    it('menampilkan pesan jika belum ada hasil', function () {
        $this->get('/')->assertOk()->assertSee('Belum ada hasil yang diumumkan.');
    });
});

describe('klasemen', function () {
    it('menampilkan klasemen dengan label peringkat sama', function () {
        // MTsN 3 dan MIN 1 sama-sama 1 perak → =02
        juara($this->ksm, $this->man4, 1);
        juara($this->tenisMeja, $this->mtsn3, 2);
        juara($this->tenisMeja, $this->min1, 2);

        $this->get('/klasemen')
            ->assertOk()
            ->assertSeeInOrder(['01', 'MAN 4 Jakarta', '02', 'MIN 1 Jakarta', '02', 'MTsN 3 Jakarta'])
            ->assertDontSee('=02');
    });

    it('memfilter per jenjang madrasah dan per cabor', function () {
        isiHasilContoh($this);

        $this->get('/klasemen?jenjang=MI')->assertOk()
            ->assertSee('MIN 1 Jakarta')->assertDontSee('MAN 4 Jakarta');

        $this->get('/klasemen?cabor=sains')->assertOk()
            ->assertSee('MAN 4 Jakarta')->assertDontSee('MTsN 3 Jakarta');
    });

    it('mengabaikan filter yang tidak valid', function () {
        isiHasilContoh($this);

        $this->get('/klasemen?jenjang=SD&tab=xyz&cabor=tidak-ada')->assertOk()->assertSee('MAN 4 Jakarta');
    });

    it('menampilkan rincian per cabor di baris klasemen', function () {
        isiHasilContoh($this);

        $this->get('/klasemen')->assertOk()
            ->assertSee('Profil madrasah &amp; daftar juara', false)
            ->assertSee('/madrasah/131131710004', false);
    });

    it('menumpuk beberapa juara dalam satu medali di tab Peraih Medali', function () {
        isiHasilContoh($this);

        $this->get('/klasemen?tab=peraih')->assertOk()
            ->assertSee('Tenis Meja MTs Putri')
            // emas, lalu dua nama perak, lalu perunggu
            ->assertSeeInOrder(['MTsN 3 Jakarta', 'MAN 4 Jakarta', 'MIN 1 Jakarta', 'MAN 4 Jakarta'])
            ->assertDontSee('KSM Biologi');
    });

    it('mencari nama peserta dan madrasah', function () {
        isiHasilContoh($this);

        $this->get('/klasemen?tab=cari&q=siti')->assertOk()
            ->assertSee('Siti Aminah')->assertSee('Tenis Meja · MTs · Putri');

        $this->get('/klasemen?tab=cari&q=MAN 4')->assertOk()
            ->assertSee('MAN 4 Jakarta');

        $this->get('/klasemen?tab=cari&q=s')->assertOk()->assertSee('Ketik minimal 2 huruf.');
    });
});

describe('cabor', function () {
    it('menampilkan cabor pertama di /cabor', function () {
        $this->get('/cabor')->assertOk()->assertSee('Keagamaan')->assertSee('0 LOMBA · 0 SELESAI');
    });

    it('menampilkan lomba per jenjang dengan juara ganda dan menunggu hasil', function () {
        isiHasilContoh($this);

        $this->get('/cabor/sains')->assertOk()
            ->assertSee('2 LOMBA · 1 SELESAI')
            ->assertSee('KSM Matematika')
            ->assertSee('MENUNGGU HASIL');

        $this->get('/cabor/olahraga')->assertOk()
            ->assertSee('Tenis Meja')
            ->assertSeeInOrder(['MTsN 3 Jakarta', 'MAN 4 Jakarta', 'MIN 1 Jakarta']);
    });

    it('menampilkan 404 untuk cabor yang tidak ada', function () {
        $this->get('/cabor/tidak-ada')->assertNotFound();
    });
});

describe('madrasah', function () {
    it('menampilkan daftar madrasah dengan pencarian dan filter jenjang', function () {
        isiHasilContoh($this);

        $this->get('/madrasah')->assertOk()->assertSee('MAN 4 Jakarta')->assertSee('MIN 1 Jakarta');
        $this->get('/madrasah?q=MIN')->assertOk()->assertSee('MIN 1 Jakarta')->assertDontSee('MAN 4 Jakarta');
        $this->get('/madrasah?jenjang=MTs')->assertOk()->assertSee('MTsN 3 Jakarta')->assertDontSee('MIN 1 Jakarta');
    });

    it('menampilkan profil: peringkat, medali, daftar juara, per cabor, riwayat', function () {
        isiHasilContoh($this);
        juara(Lomba::factory()->for($this->lama)->for($this->sains)->create(), $this->man4, 3);

        $this->get('/madrasah/131131710004')->assertOk()
            ->assertSee('MAN 4 Jakarta')
            ->assertSee('NPSN 131131710004')
            ->assertSee('dari 3 madrasah')
            ->assertSeeInOrder(['KSM Matematika', 'Ahmad Fauzi', 'Tenis Meja', 'Ani', 'Tenis Meja', 'Dewi'])
            ->assertSee('Per Cabor')
            ->assertSeeInOrder(['2026', '2025']);
    });

    it('menampilkan profil madrasah tanpa medali', function () {
        $m = Madrasah::factory()->create(['npsn' => '999']);

        $this->get('/madrasah/999')->assertOk()->assertSee('Belum meraih medali');
    });

    it('menampilkan 404 untuk NPSN yang tidak ada', function () {
        $this->get('/madrasah/000')->assertNotFound();
    });
});

describe('tanpa N+1', function () {
    /** Hitung query untuk satu request. */
    function jumlahQuery($test, string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $test->get($url)->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }

    it('tidak menambah query saat jumlah lomba dan juara bertambah', function () {
        isiHasilContoh($this);

        $urls = ['/', '/klasemen', '/klasemen?tab=peraih', '/cabor/olahraga', '/madrasah', '/madrasah/131131710004', '/klasemen?tab=cari&q=a'];
        $sebelum = collect($urls)->mapWithKeys(fn ($u) => [$u => jumlahQuery($this, $u)]);

        // Tambah banyak lomba + juara + madrasah
        $extra = Madrasah::factory()->count(10)->jenjang(Jenjang::MTs)->create();
        foreach (range(1, 8) as $i) {
            $l = Lomba::factory()->for($this->edisi)->for($this->olahraga)->create(['jenjang' => 'MTs']);
            foreach ([1, 2, 2, 3, 3, 3] as $k => $p) {
                Hasil::factory()->for($l)->for($extra[$k])->create(['peringkat' => $p, 'nama_peserta' => 'Atlet '.$i]);
            }
            juara($l, $this->man4, 3, 0);
        }

        foreach ($urls as $u) {
            expect(jumlahQuery($this, $u))->toBe($sebelum[$u], "Jumlah query bertambah di {$u}");
        }
    });
});
