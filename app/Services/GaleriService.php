<?php

namespace App\Services;

use App\Models\GaleriAlbum;
use App\Models\GaleriFoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Satu-satunya tempat pemrosesan foto galeri:
 * kompres (maks lebar 1920px) + thumbnail (lebar 600px), format WebP, disk "public".
 */
class GaleriService
{
    public const DISK = 'public';

    public const LEBAR_FOTO = 1920;

    public const LEBAR_THUMB = 600;

    public const KUALITAS_FOTO = 80;

    public const KUALITAS_THUMB = 75;

    /** Ukuran file maksimal (KB) dan tipe yang diterima. */
    public const MAKS_KB = 15360;

    public const MIME = ['image/jpeg', 'image/png', 'image/webp'];

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(GdDriver::class, autoOrientation: true, decodeAnimation: false, strip: true);
    }

    /**
     * Proses satu file gambar (path lokal atau UploadedFile) dan simpan sebagai foto album.
     *
     * @throws ValidationException jika bukan gambar yang didukung
     */
    public function tambahFoto(GaleriAlbum $album, string|UploadedFile $file, ?string $keterangan = null): GaleriFoto
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $this->validasiFile($path, $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($path));

        try {
            $gambar = $this->manager->decodePath($path);
        } catch (Throwable) {
            throw ValidationException::withMessages(['foto' => 'File tidak bisa dibaca sebagai gambar.']);
        }

        $folder = $this->folder($album);
        $nama = Str::uuid()->toString().'.webp';
        $pathFoto = "{$folder}/{$nama}";
        $pathThumb = "{$folder}/thumb/{$nama}";

        $disk = Storage::disk(self::DISK);
        $disk->put($pathFoto, $gambar->scaleDown(width: self::LEBAR_FOTO)->encode(new WebpEncoder(quality: self::KUALITAS_FOTO))->toString());
        $disk->put($pathThumb, $gambar->scaleDown(width: self::LEBAR_THUMB)->encode(new WebpEncoder(quality: self::KUALITAS_THUMB))->toString());

        try {
            return DB::transaction(function () use ($album, $pathFoto, $pathThumb, $keterangan) {
                $foto = $album->foto()->create([
                    'path' => $pathFoto,
                    'thumb_path' => $pathThumb,
                    'keterangan' => filled($keterangan) ? trim($keterangan) : null,
                    'urutan' => (int) $album->foto()->max('urutan') + 1,
                ]);

                if (! $album->sampul_foto_id) {
                    $album->update(['sampul_foto_id' => $foto->id]);
                }

                return $foto;
            });
        } catch (Throwable $e) {
            $disk->delete([$pathFoto, $pathThumb]);

            throw $e;
        }
    }

    /**
     * @param  iterable<string|UploadedFile>  $files
     * @return list<GaleriFoto>
     */
    public function tambahBanyak(GaleriAlbum $album, iterable $files): array
    {
        $hasil = [];
        foreach ($files as $file) {
            $hasil[] = $this->tambahFoto($album, $file);
        }

        return $hasil;
    }

    /** Dipanggil saat foto dihapus (lihat GaleriFoto::booted). */
    public function hapusFile(GaleriFoto $foto): void
    {
        Storage::disk(self::DISK)->delete(array_filter([$foto->path, $foto->thumb_path]));
    }

    /** Dipanggil sebelum album dihapus (lihat GaleriAlbum::booted). */
    public function hapusFolderAlbum(GaleriAlbum $album): void
    {
        $paths = $album->foto()->get(['path', 'thumb_path'])
            ->flatMap(fn (GaleriFoto $f) => [$f->path, $f->thumb_path])
            ->filter()
            ->all();

        Storage::disk(self::DISK)->delete($paths);
    }

    private function folder(GaleriAlbum $album): string
    {
        $tahun = $album->edisi()->value('tahun') ?? 'umum';

        return "galeri/{$tahun}/{$album->id}-".Str::slug($album->slug ?: $album->judul);
    }

    private function validasiFile(string $path, string $nama): void
    {
        $salah = match (true) {
            ! is_file($path) => 'File tidak ditemukan.',
            filesize($path) > self::MAKS_KB * 1024 => "{$nama}: ukuran maksimal 15 MB.",
            ! in_array(mime_content_type($path), self::MIME, true) => "{$nama}: hanya JPG, PNG, atau WebP.",
            default => null,
        };

        if ($salah) {
            throw ValidationException::withMessages(['foto' => $salah]);
        }
    }
}
