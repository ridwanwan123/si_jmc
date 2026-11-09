<?php

use App\Models\Edisi;
use App\Models\GaleriAlbum;
use App\Models\GaleriFoto;
use App\Services\GaleriService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('public');
    $this->service = app(GaleriService::class);
    $this->album = GaleriAlbum::factory()->for(Edisi::factory()->create(['tahun' => 2026]))->create(['slug' => 'pembukaan']);
});

/** Ukuran dan tipe gambar yang tersimpan di disk public (palsu). */
function infoGambar(string $path): array
{
    $info = getimagesizefromstring(Storage::disk('public')->get($path));

    return [$info[0], $info[1], $info['mime']];
}

it('mengompres foto besar ke WebP lebar 1920 dan membuat thumbnail lebar 600', function () {
    $foto = $this->service->tambahFoto($this->album, UploadedFile::fake()->image('besar.jpg', 3000, 2000));

    expect($foto->path)->toStartWith('galeri/2026/')->toEndWith('.webp')
        ->and($foto->thumb_path)->toContain('/thumb/')
        ->and(infoGambar($foto->path))->toBe([1920, 1280, 'image/webp'])
        ->and(infoGambar($foto->thumb_path))->toBe([600, 400, 'image/webp']);
});

it('tidak memperbesar foto yang lebih kecil dari batas', function () {
    $foto = $this->service->tambahFoto($this->album, UploadedFile::fake()->image('kecil.png', 500, 300));

    expect(infoGambar($foto->path))->toBe([500, 300, 'image/webp'])
        ->and(infoGambar($foto->thumb_path))->toBe([500, 300, 'image/webp']);
});

it('menjadikan foto pertama sebagai sampul dan menambah urutan', function () {
    $a = $this->service->tambahFoto($this->album, UploadedFile::fake()->image('a.jpg', 800, 600), 'Pembukaan');
    $b = $this->service->tambahFoto($this->album, UploadedFile::fake()->image('b.jpg', 800, 600));

    expect($this->album->fresh()->sampul_foto_id)->toBe($a->id)
        ->and([$a->urutan, $b->urutan])->toBe([1, 2])
        ->and($a->keterangan)->toBe('Pembukaan');
});

it('menolak file yang bukan gambar', function () {
    expect(fn () => $this->service->tambahFoto($this->album, UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf')))
        ->toThrow(ValidationException::class);

    expect(GaleriFoto::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('menghapus file saat foto dihapus dan memindahkan sampul', function () {
    $a = $this->service->tambahFoto($this->album, UploadedFile::fake()->image('a.jpg', 800, 600));
    $b = $this->service->tambahFoto($this->album, UploadedFile::fake()->image('b.jpg', 800, 600));

    $a->delete();

    Storage::disk('public')->assertMissing([$a->path, $a->thumb_path]);
    Storage::disk('public')->assertExists([$b->path, $b->thumb_path]);
    expect($this->album->fresh()->sampul_foto_id)->toBe($b->id);
});

it('menghapus semua file saat album atau edisinya dihapus', function () {
    $this->service->tambahBanyak($this->album, [
        UploadedFile::fake()->image('a.jpg', 800, 600),
        UploadedFile::fake()->image('b.jpg', 800, 600),
    ]);
    $lain = GaleriAlbum::factory()->for($this->album->edisi)->create();
    $this->service->tambahFoto($lain, UploadedFile::fake()->image('c.jpg', 800, 600));

    expect(Storage::disk('public')->allFiles())->toHaveCount(6);

    $this->album->delete();
    expect(Storage::disk('public')->allFiles())->toHaveCount(2)
        ->and(GaleriFoto::count())->toBe(1);

    $lain->edisi->delete();
    expect(Storage::disk('public')->allFiles())->toBe([])
        ->and(GaleriFoto::count())->toBe(0);
});
