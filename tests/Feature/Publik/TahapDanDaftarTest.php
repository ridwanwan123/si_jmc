<?php

use App\Enums\Jenjang;
use App\Enums\Tahap;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\Hasil;
use App\Models\Lomba;
use App\Models\Madrasah;
use Database\Seeders\CaborSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(CaborSeeder::class);
    $this->keagamaan = Cabor::where('slug', 'keagamaan')->first();
    $this->mtsn3 = Madrasah::factory()->jenjang(Jenjang::MTs)->create(['nama' => 'MTsN 3 Jakarta', 'nsm' => '121131710003']);
});

afterEach(fn () => Carbon::setTestNow());

describe('tahapan edisi', function () {
    it('registrasi sebelum tanggal mulai, tanding saat lomba, klasemen setelah ada hasil', function () {
        Carbon::setTestNow('2026-11-01 10:00:00');
        $edisi = Edisi::factory()->aktif()->create(['tahun' => 2026, 'tanggal_mulai' => '2026-11-04', 'tanggal_selesai' => '2026-11-06']);

        expect($edisi->tahap())->toBe(Tahap::Registrasi);

        Carbon::setTestNow('2026-11-05 10:00:00');
        expect($edisi->fresh()->tahap())->toBe(Tahap::Tanding);

        juara(Lomba::factory()->for($edisi)->for($this->keagamaan)->create(), $this->mtsn3, 1);
        expect($edisi->fresh()->tahap())->toBe(Tahap::Klasemen);
    });

    it('menganggap edisi tanpa tanggal dan tanpa hasil sebagai registrasi', function () {
        expect(Edisi::factory()->create(['tanggal_mulai' => null, 'tanggal_selesai' => null])->tahap())->toBe(Tahap::Registrasi)
            ->and(Edisi::sementara()->tahap())->toBe(Tahap::Registrasi);
    });

    it('beranda tahap registrasi: tombol Daftar sekarang, garis tahapan, tanpa podium', function () {
        Edisi::factory()->aktif()->create(['tahun' => 2026, 'nama' => 'JMC 2026', 'tanggal_mulai' => now()->addMonth(), 'tanggal_selesai' => now()->addMonth()->addDays(2)]);

        $this->get('/')->assertOk()
            ->assertSee('PENDAFTARAN DIBUKA')
            ->assertSee('Daftar sekarang')
            ->assertSee(route('daftar'), false)
            ->assertSeeInOrder(['01 · SEKARANG', 'Registrasi', '02', 'Tanding', '03', 'Klasemen'])
            ->assertDontSee('PODIUM SEMENTARA');
    });

    it('beranda tahap klasemen: podium dan tombol klasemen medali', function () {
        $edisi = Edisi::factory()->aktif()->create(['tahun' => 2026, 'tanggal_mulai' => now()->subDay(), 'tanggal_selesai' => now()->addDay()]);
        juara(Lomba::factory()->for($edisi)->for($this->keagamaan)->create(), $this->mtsn3, 1);

        $this->get('/')->assertOk()
            ->assertSee('PODIUM SEMENTARA')
            ->assertSee('Klasemen medali')
            ->assertSee('03 · SEKARANG')
            ->assertDontSee('Daftar sekarang');
    });

    it('menampilkan tombol Daftar di navbar semua halaman', function () {
        Edisi::factory()->aktif()->create(['tahun' => 2026]);

        foreach (['/', '/klasemen', '/cabor', '/madrasah', '/galeri'] as $url) {
            $this->get($url)->assertOk()->assertSee('href="'.route('daftar').'"', false);
        }
    });
});

describe('tahapan dari tanggal pendaftaran', function () {
    it('registrasi sampai tanggal tutup, lalu tanding', function () {
        Carbon::setTestNow('2026-11-09 10:00:00');
        $edisi = Edisi::factory()->aktif()->create([
            'tahun' => 2026, 'pendaftaran_buka' => '2026-10-01', 'pendaftaran_tutup' => '2026-11-20',
            'tanggal_mulai' => '2026-11-30', 'tanggal_selesai' => '2026-12-04',
        ]);

        expect($edisi->tahap())->toBe(Tahap::Registrasi)->and($edisi->pendaftaranDibuka())->toBeTrue();

        Carbon::setTestNow('2026-11-20 23:00:00');
        expect($edisi->fresh()->pendaftaranDibuka())->toBeTrue();

        Carbon::setTestNow('2026-11-21 08:00:00');
        expect($edisi->fresh()->tahap())->toBe(Tahap::Tanding)->and($edisi->fresh()->pendaftaranDibuka())->toBeFalse();
    });

    it('belum dibuka sebelum tanggal buka', function () {
        Carbon::setTestNow('2026-09-15 10:00:00');
        $edisi = Edisi::factory()->aktif()->create(['tahun' => 2026, 'pendaftaran_buka' => '2026-10-01', 'pendaftaran_tutup' => '2026-11-20']);

        expect($edisi->tahap())->toBe(Tahap::Registrasi)->and($edisi->pendaftaranDibuka())->toBeFalse();
        $this->get('/daftar')->assertOk()->assertSee('Pendaftaran belum dibuka');
    });
});

describe('nama siswa juara', function () {
    beforeEach(function () {
        $this->edisi = Edisi::factory()->aktif()->create(['tahun' => 2026]);
    });

    it('memecah nama anggota tim tanpa memecah gelar', function () {
        $beregu = Lomba::factory()->for($this->edisi)->for($this->keagamaan)->beregu()->create();
        $futsal = Lomba::factory()->for($this->edisi)->for($this->keagamaan)->create(['nama' => 'Futsal', 'kategori' => 'Putra']);
        $perorangan = Lomba::factory()->for($this->edisi)->for($this->keagamaan)->create(['kategori' => 'Putra']);

        $baris = Hasil::factory()->for($beregu)->for($this->mtsn3)->create(['nama_peserta' => "Rizky Pratama\nAnnisa Rahma, S.Pd.\nFarhan Hakim;"]);
        $koma = Hasil::factory()->for($futsal)->for($this->mtsn3)->create(['nama_peserta' => 'Rizal Fahmi, Dafa Pratama, Iqbal Ramadhan']);
        $guru = Hasil::factory()->for($perorangan)->for($this->mtsn3)->create(['nama_peserta' => 'Budi Santoso, S.Pd., M.Pd.']);
        $satuTim = Hasil::factory()->for($beregu)->for($this->mtsn3)->create(['nama_peserta' => 'Tim Al-Fatih']);
        $kosong = Hasil::factory()->for($perorangan)->for($this->mtsn3)->create(['nama_peserta' => null]);

        expect($baris->anggota())->toBe(['Rizky Pratama', 'Annisa Rahma, S.Pd.', 'Farhan Hakim'])
            ->and($baris->tim())->toBeTrue()
            // Lomba kategori Putra dengan beberapa nama (mis. futsal) tetap tampil sebagai tim.
            ->and($koma->anggota())->toBe(['Rizal Fahmi', 'Dafa Pratama', 'Iqbal Ramadhan'])
            ->and($koma->tim())->toBeTrue()
            ->and($guru->anggota())->toBe(['Budi Santoso, S.Pd., M.Pd.'])
            ->and($guru->tim())->toBeFalse()
            ->and($satuTim->tim())->toBeTrue()
            ->and($kosong->anggota())->toBe([]);
    });

    it('menampilkan nama siswa dan anggota tim di halaman cabor', function () {
        $cc = Lomba::factory()->for($this->edisi)->for($this->keagamaan)->beregu()->create(['nama' => 'Cerdas Cermat PAI', 'jenjang' => 'MTs']);
        $mtq = Lomba::factory()->for($this->edisi)->for($this->keagamaan)->create(['nama' => 'MTQ', 'jenjang' => 'MTs', 'kategori' => 'Putri']);
        Lomba::factory()->for($this->edisi)->for($this->keagamaan)->create(['nama' => 'Pidato Bahasa Arab', 'jenjang' => 'MTs', 'kategori' => 'Umum']);

        juara($cc, $this->mtsn3, 1, peserta: 'Rizky Pratama, Annisa Rahma, Farhan Hakim');
        juara($mtq, $this->mtsn3, 1, peserta: 'Fatimah Az-Zahra');

        $this->get('/cabor/keagamaan')->assertOk()
            ->assertSee('Lihat juara')
            ->assertSee('TIM · 3 ANGGOTA')
            ->assertSeeInOrder(['Rizky Pratama', 'Annisa Rahma', 'Farhan Hakim'])
            ->assertSee('Fatimah Az-Zahra')
            ->assertSee('MENUNGGU HASIL')
            ->assertSee('Tidak ada lomba di jenjang ini.') // RA, MI, MA kosong
            ->assertSee('Tidak ada'); // juara 2 & 3 belum ada
    });

    it('menampilkan nama siswa di daftar juara profil madrasah', function () {
        $cc = Lomba::factory()->for($this->edisi)->for($this->keagamaan)->beregu()->create(['nama' => 'Cerdas Cermat PAI', 'jenjang' => 'MTs']);
        juara($cc, $this->mtsn3, 2, peserta: "Dimas Saputra\nLaila Fitri");

        $this->get('/madrasah/121131710003')->assertOk()
            ->assertSee('TIM · 2 ANGGOTA')
            ->assertSee('Dimas Saputra, Laila Fitri');
    });
});
