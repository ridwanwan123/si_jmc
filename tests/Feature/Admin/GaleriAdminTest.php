<?php

use App\Filament\Resources\GaleriAlbums\Pages\CreateGaleriAlbum;
use App\Filament\Resources\GaleriAlbums\Pages\EditGaleriAlbum;
use App\Filament\Resources\GaleriAlbums\Pages\ListGaleriAlbums;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\GaleriAlbum;
use App\Models\GaleriFoto;
use App\Models\Lomba;
use App\Models\User;
use App\Services\GaleriService;
use Filament\Forms\Components\Repeater;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->actingAs(User::factory()->create());
    $this->edisi = Edisi::factory()->aktif()->create(['tahun' => 2026]);
    $this->cabor = Cabor::factory()->create(['nama' => 'Olahraga']);
});

it('menampilkan halaman daftar, buat, dan edit album', function () {
    $album = GaleriAlbum::factory()->for($this->edisi)->create();

    $this->get('/admin/galeri')->assertOk();
    $this->get('/admin/galeri/create')->assertOk();
    $this->get("/admin/galeri/{$album->slug}/edit")->assertOk();
});

it('membuat album umum dengan slug otomatis lalu diarahkan ke halaman edit', function () {
    Livewire::test(CreateGaleriAlbum::class)
        ->fillForm(['edisi_id' => $this->edisi->id, 'judul' => 'Upacara Pembukaan', 'tanggal' => '2026-11-03'])
        ->assertSchemaStateSet(['slug' => 'upacara-pembukaan'])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $album = GaleriAlbum::firstWhere('slug', 'upacara-pembukaan');
    expect($album->cabor_id)->toBeNull()->and($album->edisi_id)->toBe($this->edisi->id);
});

it('membuat album yang terkait lomba', function () {
    $lomba = Lomba::factory()->for($this->edisi)->for($this->cabor)->create();

    Livewire::test(CreateGaleriAlbum::class)
        ->fillForm([
            'edisi_id' => $this->edisi->id,
            'cabor_id' => $this->cabor->id,
            'lomba_id' => $lomba->id,
            'judul' => 'Tenis Meja',
            'slug' => 'tenis-meja',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(GaleriAlbum::firstWhere('slug', 'tenis-meja')->lomba_id)->toBe($lomba->id);
});

it('mengunggah banyak foto sekaligus lewat form edit', function () {
    $album = GaleriAlbum::factory()->for($this->edisi)->create();

    Livewire::test(EditGaleriAlbum::class, ['record' => $album->getRouteKey()])
        ->fillForm(['foto_baru' => [
            UploadedFile::fake()->image('a.jpg', 2500, 1500),
            UploadedFile::fake()->image('b.jpg', 1200, 800),
            UploadedFile::fake()->image('c.png', 640, 480),
        ]])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('3 foto ditambahkan');

    $album->refresh();

    expect($album->foto)->toHaveCount(3)
        ->and($album->sampul_foto_id)->toBe($album->foto->first()->id)
        ->and(Storage::disk('public')->allFiles())->toHaveCount(6)
        ->and(Storage::disk('local')->allFiles('galeri-upload'))->toBe([]); // file sementara dibersihkan
});

it('mengubah keterangan, urutan, sampul, dan menghapus foto', function () {
    $album = GaleriAlbum::factory()->for($this->edisi)->create();
    $service = app(GaleriService::class);
    [$a, $b, $c] = $service->tambahBanyak($album, [
        UploadedFile::fake()->image('a.jpg', 800, 600),
        UploadedFile::fake()->image('b.jpg', 800, 600),
        UploadedFile::fake()->image('c.jpg', 800, 600),
    ]);

    // Item repeater relationship memakai kunci "record-{id}". Urutan baru: c, a; b dihapus.
    Livewire::test(EditGaleriAlbum::class, ['record' => $album->getRouteKey()])
        ->fillForm(['sampul_foto_id' => $c->id])
        // set() mengganti seluruh state repeater (fillForm() hanya menggabungkan).
        ->set('data.foto', [
            "record-{$c->id}" => ['keterangan' => 'Foto C dulu'],
            "record-{$a->id}" => ['keterangan' => null],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($album->fresh()->foto->pluck('id')->all())->toBe([$c->id, $a->id])
        ->and($c->fresh()->keterangan)->toBe('Foto C dulu')
        ->and($album->fresh()->sampul_foto_id)->toBe($c->id)
        ->and(GaleriFoto::find($b->id))->toBeNull();

    Storage::disk('public')->assertMissing([$b->path, $b->thumb_path]);
});

it('menghapus album beserta file fotonya dari tabel', function () {
    $album = GaleriAlbum::factory()->for($this->edisi)->create();
    app(GaleriService::class)->tambahFoto($album, UploadedFile::fake()->image('a.jpg', 800, 600));

    Livewire::test(ListGaleriAlbums::class)->callTableAction('delete', $album);

    expect(GaleriAlbum::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('menampilkan album edisi aktif secara default di tabel', function () {
    $ini = GaleriAlbum::factory()->for($this->edisi)->create();
    $lama = GaleriAlbum::factory()->for(Edisi::factory()->create(['tahun' => 2025]))->create();

    Livewire::test(ListGaleriAlbums::class)
        ->assertCanSeeTableRecords([$ini])
        ->assertCanNotSeeTableRecords([$lama]);
});
