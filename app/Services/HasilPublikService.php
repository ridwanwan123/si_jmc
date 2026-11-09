<?php

namespace App\Services;

use App\Enums\Jenjang;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\Hasil;
use App\Models\Lomba;
use App\Models\Madrasah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Query daftar untuk halaman publik (bukan perhitungan klasemen — itu di KlasemenService).
 * Semua relasi yang ditampilkan di-eager load untuk menghindari N+1.
 */
class HasilPublikService
{
    /**
     * Lomba yang sudah punya hasil, terbaru diperbarui dulu (tab Peraih Medali & ticker).
     *
     * @param  Jenjang|null  $jenjang  jenjang lomba
     * @return EloquentCollection<int, Lomba>
     */
    public function peraihMedali(Edisi $edisi, ?int $caborId = null, ?Jenjang $jenjang = null, ?int $limit = null): EloquentCollection
    {
        return Lomba::query()
            ->where('edisi_id', $edisi->id)
            ->when($caborId, fn (Builder $q) => $q->where('cabor_id', $caborId))
            ->when($jenjang, fn (Builder $q) => $q->where('jenjang', $jenjang->value))
            ->whereHas('hasil')
            ->withMax('hasil', 'updated_at')
            ->with(['cabor', 'hasil.madrasah'])
            ->orderByDesc('hasil_max_updated_at')
            ->orderBy('urutan')
            ->when($limit, fn (Builder $q) => $q->limit($limit))
            ->get();
    }

    /**
     * Pencarian di nama madrasah dan nama peserta.
     *
     * @return array{madrasah: EloquentCollection<int, Madrasah>, peserta: EloquentCollection<int, Hasil>}
     */
    public function cari(Edisi $edisi, string $kata): array
    {
        $kata = trim($kata);
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $kata).'%';

        return [
            'madrasah' => Madrasah::query()
                ->where('nama', 'like', $like)
                ->orderBy('nama')
                ->limit(20)
                ->get(),
            'peserta' => Hasil::query()
                ->edisi($edisi)
                ->where('nama_peserta', 'like', $like)
                ->with(['lomba.cabor', 'madrasah'])
                ->orderBy('peringkat')
                ->orderBy('nama_peserta')
                ->limit(50)
                ->get(),
        ];
    }

    /**
     * Lomba satu cabor di satu edisi, dikelompokkan per jenjang (urutan RA, MI, MTs, MA).
     *
     * @return Collection<string, EloquentCollection<int, Lomba>> [kode jenjang => lomba]
     */
    public function lombaPerJenjang(Edisi $edisi, Cabor $cabor): Collection
    {
        $lomba = Lomba::query()
            ->where('edisi_id', $edisi->id)
            ->where('cabor_id', $cabor->id)
            ->with(['hasil.madrasah', 'galeriAlbum' => fn ($q) => $q->adaFoto()->select('id', 'lomba_id', 'slug')])
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();

        return collect(Jenjang::cases())
            ->mapWithKeys(fn (Jenjang $j) => [$j->value => $lomba->filter(fn (Lomba $l) => $l->jenjang === $j)->values()]);
    }

    /**
     * Semua cabor dengan jumlah lomba dan lomba selesai di edisi ini.
     *
     * @return EloquentCollection<int, Cabor> (atribut lomba_count, lomba_selesai_count)
     */
    public function caborDenganJumlah(Edisi $edisi): EloquentCollection
    {
        return Cabor::query()
            ->withCount([
                'lomba' => fn (Builder $q) => $q->where('edisi_id', $edisi->id),
                'lomba as lomba_selesai_count' => fn (Builder $q) => $q->where('edisi_id', $edisi->id)->whereHas('hasil'),
            ])
            ->orderBy('urutan')
            ->get();
    }

    /**
     * Daftar lomba per cabor dan per jenjang di satu edisi (untuk Beranda), satu query.
     *
     * @return Collection<int, Collection<string, Collection<int, Lomba>>> [cabor_id => [jenjang => lomba]]
     */
    public function mataLombaPerCabor(Edisi $edisi): Collection
    {
        $urutanJenjang = array_flip(array_map(fn (Jenjang $j) => $j->value, Jenjang::cases()));

        return Lomba::query()
            ->where('edisi_id', $edisi->id)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get(['id', 'cabor_id', 'nama', 'jenjang', 'kategori'])
            ->groupBy('cabor_id')
            ->map(fn ($lomba) => $lomba
                ->groupBy(fn (Lomba $l) => $l->jenjang->value)
                ->sortBy(fn ($grup, $jenjang) => $urutanJenjang[$jenjang]));
    }

    /**
     * Daftar juara satu madrasah di satu edisi: emas dulu, lalu perak, perunggu.
     *
     * @return EloquentCollection<int, Hasil>
     */
    public function juaraMadrasah(Edisi $edisi, Madrasah $madrasah): EloquentCollection
    {
        return Hasil::query()
            ->edisi($edisi)
            ->where('madrasah_id', $madrasah->id)
            ->with('lomba.cabor')
            ->join('lomba', 'lomba.id', '=', 'hasil.lomba_id')
            ->join('cabor', 'cabor.id', '=', 'lomba.cabor_id')
            ->orderBy('hasil.peringkat')
            ->orderBy('cabor.urutan')
            ->orderBy('lomba.nama')
            ->select('hasil.*')
            ->get();
    }
}
