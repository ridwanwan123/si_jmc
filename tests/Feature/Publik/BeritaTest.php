<?php

use App\Enums\KategoriBerita;
use App\Models\Berita;
use App\Models\Cabor;
use App\Models\Edisi;
use Database\Seeders\BeritaContohSeeder;
use Database\Seeders\CaborSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(Carbon::create(2026, 11, 10, 10, 0, 0, 'Asia/Jakarta'));

    $this->edisi = Edisi::factory()->aktif()->create([
        'tahun' => 2026, 'nama' => 'JMC 2026', 'pendaftaran_buka' => '2026-10-01', 'pendaftaran_tutup' => '2026-11-20',
    ]);
    $this->lama = Edisi::factory()->create(['tahun' => 2025, 'nama' => 'JMC 2025']);

    $this->berita = fn (string $judul, array $atribut = []) => Berita::factory()->for($this->edisi)->create(
        ['judul' => $judul, 'slug' => str($judul)->slug()->value()] + $atribut
    );
});

describe('halaman /berita', function () {
    it('menampilkan berita unggulan, terbaru, dan pengumuman tanpa draf', function () {
        ($this->berita)('Berita Unggulan', ['unggulan' => true, 'terbit_pada' => now()->subDays(5)]);
        ($this->berita)('Liputan Baru', ['terbit_pada' => now()->subDay()]);
        ($this->berita)('Pengumuman Lokasi', ['kategori' => KategoriBerita::Pengumuman, 'terbit_pada' => now()->subDays(2)]);
        ($this->berita)('Masih Draf', ['terbit_pada' => null]);
        ($this->berita)('Terjadwal Besok', ['terbit_pada' => now()->addDay()]);

        $this->get('/berita')->assertOk()
            ->assertSeeInOrder(['KABAR TERBARU', 'Berita Unggulan', 'Terbaru', 'Liputan Baru', 'Pengumuman', 'Pengumuman Lokasi'])
            ->assertSee('Daftarkan peserta madrasahmu')
            ->assertDontSee('Masih Draf')
            ->assertDontSee('Terjadwal Besok');
    });

    it('memakai berita terbaru sebagai unggulan jika tidak ada yang ditandai', function () {
        ($this->berita)('Lama', ['terbit_pada' => now()->subDays(3)]);
        ($this->berita)('Paling Baru', ['terbit_pada' => now()->subHour()]);

        expect($this->get('/berita')->viewData('unggulan')->judul)->toBe('Paling Baru');
    });

    it('memfilter berdasarkan kategori', function () {
        ($this->berita)('Ini Liputan');
        ($this->berita)('Ini Hasil Lomba', ['kategori' => KategoriBerita::Hasil]);

        $this->get('/berita?kategori=hasil')->assertOk()->assertSee('Ini Hasil Lomba')->assertDontSee('Ini Liputan');
        $this->get('/berita?kategori=ngawur')->assertOk()->assertSee('Ini Liputan')->assertSee('Ini Hasil Lomba');
    });

    it('hanya menampilkan berita edisi terpilih', function () {
        ($this->berita)('Berita 2026');
        Berita::factory()->for($this->lama)->create(['judul' => 'Berita 2025']);

        $this->get('/berita')->assertSee('Berita 2026')->assertDontSee('Berita 2025');
        $this->get('/berita?edisi=2025')->assertSee('Berita 2025')->assertDontSee('Berita 2026')
            ->assertDontSee('Daftarkan peserta madrasahmu'); // arsip: tanpa ajakan daftar
    });

    it('memuat berita lainnya per enam', function () {
        foreach (range(1, 9) as $i) {
            ($this->berita)("Berita Nomor {$i}", ['terbit_pada' => now()->subHours($i)]);
        }

        // 1 unggulan + 6 terbaru, sisa 2 lewat "Muat berita lainnya".
        $this->get('/berita')->assertSee('Muat berita lainnya')->assertSee('tampil=12', false)
            ->assertSee('Berita Nomor 7')->assertDontSee('Berita Nomor 8');
        $this->get('/berita?tampil=12')->assertSee('Berita Nomor 9')->assertDontSee('Muat berita lainnya');
    });

    it('menampilkan pita pengumuman yang disematkan', function () {
        ($this->berita)('Batas pendaftaran diperpanjang', ['kategori' => KategoriBerita::Pengumuman, 'sematkan_di_pita' => true]);

        $this->get('/berita')->assertSeeInOrder(['PENGUMUMAN', 'Batas pendaftaran diperpanjang', 'Baca']);
    });

    it('menampilkan pesan kosong jika belum ada berita', function () {
        $this->get('/berita')->assertOk()->assertSee('Belum ada berita untuk JMC 2026.');
    });
});

describe('detail berita', function () {
    it('menampilkan isi, meta, tag, dan berita lainnya sekategori lebih dulu', function () {
        $this->seed(CaborSeeder::class);
        $b = ($this->berita)('Juknis Robotik', [
            'kategori' => KategoriBerita::Pengumuman,
            'cabor_id' => Cabor::where('slug', 'robotik')->value('id'),
            'isi' => '<h2>Cara mendaftar</h2><ol><li>Pilih madrasah.</li></ol><blockquote><p>Kutipan panitia.</p></blockquote>',
            'tag' => ['Robotik', 'JMC2026'],
        ]);
        ($this->berita)('Liputan Lain', ['terbit_pada' => now()->subMinutes(5)]);
        ($this->berita)('Pengumuman Lain', ['kategori' => KategoriBerita::Pengumuman, 'terbit_pada' => now()->subDays(3)]);

        $res = $this->get('/berita/juknis-robotik')->assertOk()
            ->assertSee('Juknis Robotik')
            ->assertSee('1 MENIT BACA')
            ->assertSee('Panitia JMC 2026')
            ->assertSee('<h2>Cara mendaftar</h2>', false)
            ->assertSee('#Robotik')
            ->assertSee('Bagikan ke WhatsApp')
            ->assertSee('Siap mendaftar?');

        expect($res->viewData('lainnya')->pluck('judul')->all())->toBe(['Pengumuman Lain', 'Liputan Lain']);
    });

    it('membuang skrip dan atribut berbahaya dari isi', function () {
        ($this->berita)('Isi Nakal', ['isi' => '<p onclick="alert(1)">Halo</p><script>alert(2)</script><a href="javascript:alert(3)">x</a>']);

        $this->get('/berita/isi-nakal')->assertOk()
            ->assertSee('Halo')
            ->assertDontSee('<script>', false)
            ->assertDontSee('onclick', false)
            ->assertDontSee('javascript:', false);
    });

    it('404 untuk draf dan berita terjadwal', function () {
        ($this->berita)('Draf Rahasia', ['terbit_pada' => null]);
        ($this->berita)('Belum Waktunya', ['terbit_pada' => now()->addHour()]);

        $this->get('/berita/draf-rahasia')->assertNotFound();
        $this->get('/berita/belum-waktunya')->assertNotFound();
        $this->get('/berita/tidak-ada')->assertNotFound();
    });

    it('mengarahkan berita edisi lain ke edisi berita itu', function () {
        Berita::factory()->for($this->lama)->create(['judul' => 'Arsip', 'slug' => 'arsip-lama']);

        $this->get('/berita/arsip-lama')->assertRedirect('/berita/arsip-lama?edisi=2025');
        $this->get('/berita/arsip-lama?edisi=2025')->assertOk()->assertSee('Arsip');
    });
});

describe('beranda', function () {
    it('menampilkan seksi berita: unggulan + tiga terbaru', function () {
        ($this->berita)('Kabar Utama', ['unggulan' => true, 'terbit_pada' => now()->subDays(9)]);
        foreach (range(1, 4) as $i) {
            ($this->berita)("Kabar {$i}", ['terbit_pada' => now()->subHours($i)]);
        }

        $this->get('/')->assertOk()
            ->assertSeeInOrder(['Berita', 'Semua berita', 'Kabar Utama', 'Kabar 1', 'Kabar 2', 'Kabar 3', 'Klasemen'])
            ->assertDontSee('Kabar 4');
    });

    it('menyembunyikan seksi berita jika belum ada berita', function () {
        $this->get('/')->assertOk()->assertDontSee('Semua berita');
    });

    it('menautkan menu Berita di navbar', function () {
        $this->get('/')->assertSee(route('berita.index'), false);
    });
});

it('mengisi berita contoh lewat seeder dan aman dijalankan ulang', function () {
    $this->seed(CaborSeeder::class);
    $this->seed(BeritaContohSeeder::class);
    $this->seed(BeritaContohSeeder::class);

    expect(Berita::count())->toBe(7)
        ->and(Berita::where('unggulan', true)->count())->toBe(1)
        ->and(Berita::where('sematkan_di_pita', true)->count())->toBe(1);

    $this->get('/berita')->assertOk();
});
