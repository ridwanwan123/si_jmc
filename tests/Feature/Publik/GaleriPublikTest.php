<?php

use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\GaleriAlbum;
use App\Models\Lomba;
use App\Services\GaleriService;
use Database\Seeders\CaborSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('public');
    $this->seed(CaborSeeder::class);

    $this->edisi = Edisi::factory()->aktif()->create(['tahun' => 2026, 'nama' => 'JMC 2026']);
    $this->lama = Edisi::factory()->create(['tahun' => 2025, 'nama' => 'JMC 2025']);
    $this->olahraga = Cabor::where('slug', 'olahraga')->first();

    $this->buatAlbum = function (string $judul, ?Cabor $cabor = null, int $foto = 2, ?Edisi $edisi = null, ?Lomba $lomba = null): GaleriAlbum {
        $album = GaleriAlbum::factory()->for($edisi ?? $this->edisi)->create([
            'judul' => $judul,
            'slug' => Str::slug($judul),
            'cabor_id' => $cabor?->id,
            'lomba_id' => $lomba?->id,
            'tanggal' => '2026-11-04',
        ]);
        for ($i = 0; $i < $foto; $i++) {
            app(GaleriService::class)->tambahFoto($album, UploadedFile::fake()->image("f{$i}.jpg", 800, 600), $i === 0 ? 'Keterangan pertama' : null);
        }

        return $album;
    };
});

it('menampilkan album edisi terpilih yang punya foto', function () {
    ($this->buatAlbum)('Upacara Pembukaan', foto: 3);
    ($this->buatAlbum)('Tenis Meja MTs Putri', $this->olahraga);
    ($this->buatAlbum)('Album Kosong', foto: 0);
    ($this->buatAlbum)('Album 2025', edisi: $this->lama);

    $this->get('/galeri')->assertOk()
        ->assertSee('2 ALBUM')
        ->assertSee('Upacara Pembukaan')->assertSee('3 FOTO')->assertSee('UMUM · 04.11.2026')
        ->assertSee('Tenis Meja MTs Putri')->assertSee('OLAHRAGA')
        ->assertDontSee('Album Kosong')
        ->assertDontSee('Album 2025');

    $this->get('/galeri?edisi=2025')->assertOk()->assertSee('Album 2025')->assertDontSee('Upacara Pembukaan');
});

it('menampilkan pesan jika belum ada album', function () {
    $this->get('/galeri')->assertOk()->assertSee('Belum ada album foto untuk JMC 2026.');
});

it('menampilkan isi album dengan data lightbox', function () {
    $album = ($this->buatAlbum)('Tenis Meja MTs Putri', $this->olahraga, foto: 3);

    $this->get('/galeri/tenis-meja-mts-putri')->assertOk()
        ->assertSee('Tenis Meja MTs Putri')
        ->assertSee('3 FOTO')
        ->assertSee('SEMUA ALBUM')
        ->assertSee('Buka foto 1: Keterangan pertama')
        ->assertSee('role="dialog"', false)
        ->assertSee($album->foto->first()->thumbUrl(), false)
        ->assertSee('Keterangan pertama');
});

it('mengarahkan album dari edisi lain ke edisi album itu', function () {
    ($this->buatAlbum)('Album 2025', edisi: $this->lama);

    $this->get('/galeri/album-2025')->assertRedirect('/galeri/album-2025?edisi=2025');
    $this->get('/galeri/album-2025?edisi=2025')->assertOk();
});

it('menampilkan 404 untuk album yang tidak ada atau kosong', function () {
    ($this->buatAlbum)('Album Kosong', foto: 0);

    $this->get('/galeri/tidak-ada')->assertNotFound();
    $this->get('/galeri/album-kosong')->assertNotFound();
});

it('selalu menampilkan seksi galeri di beranda, dengan placeholder jika belum ada album', function () {
    $this->get('/')->assertOk()
        ->assertSee('Semua album →')
        ->assertSee('akan tampil di sini setelah diunggah panitia');

    ($this->buatAlbum)('Upacara Pembukaan');
    ($this->buatAlbum)('Tenis Meja MTs Putri', $this->olahraga);

    $this->get('/')->assertOk()
        ->assertSee('Semua album →')
        ->assertSeeInOrder(['Upacara Pembukaan', 'Tenis Meja MTs Putri'])
        ->assertDontSee('akan tampil di sini setelah diunggah panitia');
});

it('menampilkan link foto di lomba yang punya album', function () {
    $lomba = Lomba::factory()->for($this->edisi)->for($this->olahraga)->create(['nama' => 'Tenis Meja', 'jenjang' => 'MTs', 'kategori' => 'Putri']);
    ($this->buatAlbum)('Tenis Meja MTs Putri', $this->olahraga, lomba: $lomba);

    $this->get('/cabor/olahraga')->assertOk()->assertSee('/galeri/tenis-meja-mts-putri', false);
});

it('tidak menambah query saat jumlah album dan foto bertambah', function () {
    ($this->buatAlbum)('Album A', $this->olahraga);
    $album = ($this->buatAlbum)('Album B');

    $hitung = function (string $url) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();

        return tap(count(DB::getQueryLog()), fn () => DB::disableQueryLog());
    };

    $urls = ['/galeri', '/galeri/album-b', '/'];
    $sebelum = collect($urls)->mapWithKeys(fn ($u) => [$u => $hitung($u)]);

    foreach (range(1, 5) as $i) {
        ($this->buatAlbum)("Tambahan {$i}", $this->olahraga, foto: 2);
    }
    app(GaleriService::class)->tambahFoto($album, UploadedFile::fake()->image('x.jpg', 800, 600));

    foreach ($urls as $u) {
        expect($hitung($u))->toBe($sebelum[$u], "Jumlah query bertambah di {$u}");
    }
});
