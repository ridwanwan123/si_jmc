<?php

namespace App\Services;

use App\Enums\KategoriBerita;
use App\Models\Berita;
use App\Models\Edisi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/** Query berita untuk halaman publik (beranda, /berita, detail) dan penyimpanan sampul. */
class BeritaService
{
    /** Lebar maksimal sampul (px) dan kualitas WebP. */
    public const LEBAR_SAMPUL = 1600;

    public const KUALITAS_SAMPUL = 80;

    /** Ukuran upload maksimal (KB): sampul dan gambar di isi berita. */
    public const MAKS_SAMPUL_KB = 5120;

    public const MAKS_GAMBAR_ISI_KB = 2048;

    /** Lebar maksimal gambar di isi berita (px); kolom isi berita jauh lebih sempit dari ini. */
    public const LEBAR_GAMBAR_ISI = 1200;

    /** Tipe gambar yang bisa diproses server (HEIC diubah ke JPG di browser sebelum diunggah). */
    public const MIME = ['image/jpeg', 'image/png', 'image/webp'];

    /** Yang boleh dipilih di form: + HEIC/HEIF (iPhone), yang diubah ke JPG di browser (resources/js/admin-heic.js). */
    public const MIME_PILIH = [...self::MIME, 'image/heic', 'image/heif'];

    /** Kompres foto sampul ke WebP (maks 1600px) di disk public, kembalikan path-nya. */
    public function simpanSampul(UploadedFile|string $file): string
    {
        return $this->kompres($file, 'berita/'.now()->format('Y/m'), self::LEBAR_SAMPUL);
    }

    /** Kompres gambar yang disisipkan di isi berita ke WebP (maks 1200px), kembalikan path-nya. */
    public function simpanGambarIsi(UploadedFile|string $file): string
    {
        return $this->kompres($file, 'berita/isi/'.now()->format('Y/m'), self::LEBAR_GAMBAR_ISI);
    }

    private function kompres(UploadedFile|string $file, string $folder, int $lebar): string
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        if (! in_array(mime_content_type($path), self::MIME, true)) {
            throw ValidationException::withMessages([
                'gambar' => 'Gambar harus JPG, PNG, WebP, atau HEIC. Jika HEIC gagal diproses, ubah dulu ke JPG.',
            ]);
        }

        $manager = new ImageManager(GdDriver::class, autoOrientation: true, decodeAnimation: false, strip: true);
        $hasil = $manager->decodePath($path)->scaleDown(width: $lebar)
            ->encode(new WebpEncoder(quality: self::KUALITAS_SAMPUL))->toString();

        $tujuan = $folder.'/'.Str::uuid().'.webp';
        Storage::disk('public')->put($tujuan, $hasil);

        return $tujuan;
    }

    /** Berita terbit milik edisi, terbaru dulu. */
    public function query(Edisi $edisi, ?KategoriBerita $kategori = null): Builder
    {
        return Berita::query()
            ->where('edisi_id', $edisi->getKey())
            ->terbit()
            ->when($kategori, fn (Builder $q) => $q->where('kategori', $kategori->value))
            ->with('cabor')
            ->terbaru();
    }

    /** Berita besar di atas: yang ditandai unggulan (terbaru), atau berita terbaru. */
    public function unggulan(Edisi $edisi, ?KategoriBerita $kategori = null): ?Berita
    {
        return $this->query($edisi, $kategori)->where('unggulan', true)->first()
            ?? $this->query($edisi, $kategori)->first();
    }

    /** Pengumuman yang disematkan di pita hitam (terbaru). */
    public function pita(Edisi $edisi): ?Berita
    {
        return $this->query($edisi, KategoriBerita::Pengumuman)->where('sematkan_di_pita', true)->first();
    }

    /**
     * Berita lain untuk bagian bawah detail: sekategori dulu, lalu sisanya.
     *
     * @return Collection<int, Berita>
     */
    public function lainnya(Berita $berita, int $jumlah = 3): Collection
    {
        $edisi = $berita->edisi;
        $sama = $this->query($edisi, $berita->kategori)->whereKeyNot($berita->id)->limit($jumlah)->get();

        return $sama->concat(
            $this->query($edisi)->whereKeyNot([$berita->id, ...$sama->modelKeys()])->limit($jumlah - $sama->count())->get()
        )->take($jumlah)->values();
    }
}
