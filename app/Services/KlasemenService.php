<?php

namespace App\Services;

use App\Data\BarisKlasemen;
use App\Enums\Jenjang;
use App\Enums\StatusPendaftaran;
use App\Models\Edisi;
use App\Models\Hasil;
use App\Models\Madrasah;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat perhitungan klasemen medali (lihat CLAUDE.md, "Aturan klasemen").
 *
 * Setiap baris `hasil` = 1 medali (1 emas, 2 perak, 3 perunggu), sehingga juara ganda
 * dan lomba beregu otomatis terhitung benar. Klasemen tidak disimpan, selalu dihitung.
 */
class KlasemenService
{
    /**
     * Klasemen medali satu edisi.
     *
     * @param  int|null  $caborId  filter berdasarkan cabor lomba
     * @param  Jenjang|null  $jenjang  filter berdasarkan jenjang madrasah;
     *                                 tanpa filter = jenjang klasemen umum (config/jmc.php)
     * @return Collection<int, BarisKlasemen>
     */
    public function klasemen(Edisi $edisi, ?int $caborId = null, ?Jenjang $jenjang = null): Collection
    {
        $rows = $this->dasar($edisi, $caborId, $jenjang)
            ->select($this->kolomMadrasah())
            ->selectRaw($this->kolomMedali())
            ->groupBy($this->kolomMadrasah())
            ->orderByDesc('emas')
            ->orderByDesc('perak')
            ->orderByDesc('perunggu')
            ->orderBy('m.nama')
            ->get();

        return $this->beriPeringkat($rows->map(fn (object $r) => $this->keBaris($r)));
    }

    /**
     * Rincian medali per cabor untuk beberapa madrasah sekaligus (satu query).
     *
     * @param  list<int>  $madrasahIds
     * @return array<int, array<int, array{emas:int, perak:int, perunggu:int, total:int}>>
     *                                                                                     [madrasah_id => [cabor_id => medali]]
     */
    public function rincianPerCabor(Edisi $edisi, array $madrasahIds): array
    {
        if ($madrasahIds === []) {
            return [];
        }

        $rows = $this->dasar($edisi, terapkanJenjangUmum: false)
            ->whereIn('h.madrasah_id', $madrasahIds)
            ->select('h.madrasah_id', 'l.cabor_id')
            ->selectRaw($this->kolomMedali())
            ->groupBy('h.madrasah_id', 'l.cabor_id')
            ->get();

        $hasil = [];
        foreach ($rows as $r) {
            $hasil[(int) $r->madrasah_id][(int) $r->cabor_id] = [
                'emas' => (int) $r->emas,
                'perak' => (int) $r->perak,
                'perunggu' => (int) $r->perunggu,
                'total' => (int) $r->emas + (int) $r->perak + (int) $r->perunggu,
            ];
        }

        return $hasil;
    }

    /**
     * Pemuncak tiap cabor = peringkat 1 klasemen dengan filter cabor tersebut.
     * Jika seri di puncak, semua madrasah yang seri dikembalikan.
     *
     * @return Collection<int, Collection<int, BarisKlasemen>> [cabor_id => baris peringkat 1]
     */
    public function pemuncakCabor(Edisi $edisi, ?Jenjang $jenjang = null): Collection
    {
        $rows = $this->dasar($edisi, jenjang: $jenjang)
            ->select('l.cabor_id', ...$this->kolomMadrasah())
            ->selectRaw($this->kolomMedali())
            ->groupBy('l.cabor_id', ...$this->kolomMadrasah())
            ->orderByDesc('emas')
            ->orderByDesc('perak')
            ->orderByDesc('perunggu')
            ->orderBy('m.nama')
            ->get();

        return $rows->groupBy('cabor_id')
            ->map(fn (Collection $perCabor) => $this
                ->beriPeringkat($perCabor->map(fn (object $r) => $this->keBaris($r))->values())
                ->filter(fn (BarisKlasemen $b) => $b->peringkat === 1)
                ->values());
    }

    /** Baris klasemen satu madrasah, atau null jika belum punya medali. */
    public function peringkatMadrasah(Edisi $edisi, Madrasah|int $madrasah, ?int $caborId = null, ?Jenjang $jenjang = null): ?BarisKlasemen
    {
        $id = $madrasah instanceof Madrasah ? $madrasah->getKey() : $madrasah;

        return $this->klasemen($edisi, $caborId, $jenjang)
            ->first(fn (BarisKlasemen $b) => $b->madrasahId === $id);
    }

    /**
     * Riwayat madrasah di semua edisi (terbaru dulu).
     *
     * @return Collection<int, array{edisi: Edisi, baris: BarisKlasemen|null}>
     */
    public function riwayat(Madrasah $madrasah): Collection
    {
        return Edisi::query()->orderByDesc('tahun')->get()
            ->map(fn (Edisi $edisi) => [
                'edisi' => $edisi,
                'baris' => $this->peringkatMadrasah($edisi, $madrasah),
            ]);
    }

    /**
     * Angka ringkas untuk beranda.
     *
     * @return array{jumlah_lomba:int, lomba_selesai:int, emas:int, perak:int, perunggu:int, total_medali:int, madrasah_peraih:int, madrasah_peserta:int}
     */
    public function ringkasan(Edisi $edisi): array
    {
        $medali = DB::table('hasil as h')
            ->join('lomba as l', 'l.id', '=', 'h.lomba_id')
            ->where('l.edisi_id', $edisi->getKey())
            ->selectRaw($this->kolomMedali())
            ->selectRaw('COUNT(DISTINCT h.lomba_id) AS lomba_selesai')
            ->selectRaw('COUNT(DISTINCT h.madrasah_id) AS madrasah_peraih')
            ->first();

        $emas = (int) $medali->emas;
        $perak = (int) $medali->perak;
        $perunggu = (int) $medali->perunggu;

        return [
            'jumlah_lomba' => $edisi->lomba()->count(),
            'lomba_selesai' => (int) $medali->lomba_selesai,
            'emas' => $emas,
            'perak' => $perak,
            'perunggu' => $perunggu,
            'total_medali' => $emas + $perak + $perunggu,
            'madrasah_peraih' => (int) $medali->madrasah_peraih,
            'madrasah_peserta' => $this->jumlahMadrasahPeserta($edisi),
        ];
    }

    /**
     * Jumlah madrasah berbeda yang mengikuti edisi: punya pendaftaran yang
     * diverifikasi, atau punya juara (termasuk juara yang diinput tanpa pendaftaran).
     */
    public function jumlahMadrasahPeserta(Edisi $edisi): int
    {
        $pendaftar = DB::table('pendaftaran')
            ->where('edisi_id', $edisi->getKey())
            ->where('status', StatusPendaftaran::Diverifikasi->value)
            ->select('madrasah_id');

        $peraih = DB::table('hasil as h')
            ->join('lomba as l', 'l.id', '=', 'h.lomba_id')
            ->where('l.edisi_id', $edisi->getKey())
            ->select('h.madrasah_id');

        // UNION (bukan UNION ALL) sudah menghilangkan madrasah ganda.
        return DB::query()->fromSub($pendaftar->union($peraih), 'peserta')->count();
    }

    /** MAX(hasil.updated_at) untuk lomba di edisi ini, dalam zona Asia/Jakarta. */
    public function terakhirDiperbarui(Edisi $edisi): ?Carbon
    {
        $max = Hasil::query()->edisi($edisi)->max('updated_at');

        return $max === null
            ? null
            : Carbon::parse($max, config('app.timezone'))->setTimezone(config('jmc.timezone'));
    }

    /** Format "05.11.2026 · 11:30 WIB", atau null jika belum ada hasil. */
    public function labelTerakhirDiperbarui(Edisi $edisi): ?string
    {
        $waktu = $this->terakhirDiperbarui($edisi);

        return $waktu ? $waktu->format(config('jmc.format_waktu')).' WIB' : null;
    }

    // ------------------------------------------------------------------

    /**
     * Query dasar hasil ⨝ lomba ⨝ madrasah untuk satu edisi, dengan filter opsional.
     */
    private function dasar(Edisi $edisi, ?int $caborId = null, ?Jenjang $jenjang = null, bool $terapkanJenjangUmum = true): Builder
    {
        return DB::table('hasil as h')
            ->join('lomba as l', 'l.id', '=', 'h.lomba_id')
            ->join('madrasah as m', 'm.id', '=', 'h.madrasah_id')
            ->where('l.edisi_id', $edisi->getKey())
            ->when($caborId, fn (Builder $q) => $q->where('l.cabor_id', $caborId))
            ->when(
                $jenjang,
                fn (Builder $q) => $q->where('m.jenjang', $jenjang->value),
                fn (Builder $q) => $q->when(
                    $terapkanJenjangUmum,
                    fn (Builder $q) => $q->whereIn('m.jenjang', config('jmc.jenjang_klasemen_umum')),
                ),
            );
    }

    /** @return list<string> */
    private function kolomMadrasah(): array
    {
        return ['m.id', 'm.npsn', 'm.nama', 'm.jenjang', 'm.kota', 'm.status'];
    }

    private function kolomMedali(): string
    {
        return 'SUM(CASE WHEN h.peringkat = 1 THEN 1 ELSE 0 END) AS emas, '
            .'SUM(CASE WHEN h.peringkat = 2 THEN 1 ELSE 0 END) AS perak, '
            .'SUM(CASE WHEN h.peringkat = 3 THEN 1 ELSE 0 END) AS perunggu';
    }

    private function keBaris(object $r): BarisKlasemen
    {
        return new BarisKlasemen(
            madrasahId: (int) $r->id,
            npsn: (string) $r->npsn,
            nama: (string) $r->nama,
            jenjang: Jenjang::from($r->jenjang),
            kota: (string) $r->kota,
            status: (string) $r->status,
            emas: (int) $r->emas,
            perak: (int) $r->perak,
            perunggu: (int) $r->perunggu,
        );
    }

    /**
     * Peringkat kompetisi standar: emas/perak/perunggu sama persis → peringkat sama
     * (ditandai seri), peringkat berikutnya melompat (1, 2, =3, =3, 5).
     * Masukan harus sudah terurut.
     *
     * @param  Collection<int, BarisKlasemen>  $baris
     * @return Collection<int, BarisKlasemen>
     */
    private function beriPeringkat(Collection $baris): Collection
    {
        $jumlahPerKunci = $baris->countBy(fn (BarisKlasemen $b) => $b->kunciMedali());

        $peringkat = 0;
        $kunciSebelumnya = null;

        return $baris->values()->map(function (BarisKlasemen $b, int $i) use (&$peringkat, &$kunciSebelumnya, $jumlahPerKunci) {
            if ($b->kunciMedali() !== $kunciSebelumnya) {
                $peringkat = $i + 1;
                $kunciSebelumnya = $b->kunciMedali();
            }

            return $b->denganPeringkat($peringkat, $jumlahPerKunci[$b->kunciMedali()] > 1);
        });
    }
}
