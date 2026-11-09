<?php

namespace App\Services;

use App\Models\Edisi;
use App\Models\Lomba;
use App\Models\MataLomba;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya tempat membuat lomba per edisi dari master mata lomba.
 * Dipakai form admin, aksi "Tambah dari master", "Salin dari edisi lain", dan import Excel.
 */
class LombaService
{
    /**
     * Buat satu lomba di edisi dari master. Kuota default dari master, bisa ditimpa.
     *
     * @param  array{1?:int, 2?:int, 3?:int}  $kuota
     *
     * @throws ValidationException jika mata lomba sudah ada di edisi itu
     */
    public function dariMaster(Edisi $edisi, MataLomba $master, array $kuota = [], ?int $urutan = null): Lomba
    {
        if ($this->sudahAda($edisi, $master)) {
            throw ValidationException::withMessages([
                'mata_lomba_id' => "{$master->namaLengkap()} sudah ada di {$edisi->nama}.",
            ]);
        }

        return Lomba::create([
            'edisi_id' => $edisi->id,
            'mata_lomba_id' => $master->id,
            'jumlah_anggota' => $master->jumlah_anggota,
            'kuota_juara_1' => $kuota[1] ?? $master->kuota_juara_1,
            'kuota_juara_2' => $kuota[2] ?? $master->kuota_juara_2,
            'kuota_juara_3' => $kuota[3] ?? $master->kuota_juara_3,
            'urutan' => $urutan ?? ((int) $edisi->lomba()->max('urutan') + 1),
        ]);
    }

    /**
     * Tambah banyak mata lomba sekaligus ke edisi. Yang sudah ada dilewati.
     *
     * @param  iterable<int>  $mataLombaIds
     * @return array{dibuat:int, dilewati:int}
     */
    public function tambahBanyak(Edisi $edisi, iterable $mataLombaIds): array
    {
        $hasil = ['dibuat' => 0, 'dilewati' => 0];

        DB::transaction(function () use ($edisi, $mataLombaIds, &$hasil) {
            $master = MataLomba::query()->whereIn('mata_lomba.id', collect($mataLombaIds)->all())->terurut()->get();

            foreach ($master as $m) {
                if ($this->sudahAda($edisi, $m)) {
                    $hasil['dilewati']++;

                    continue;
                }

                $this->dariMaster($edisi, $m);
                $hasil['dibuat']++;
            }
        });

        return $hasil;
    }

    /**
     * Salin daftar lomba (tanpa hasil) dari edisi lain. Mata lomba nonaktif dan yang
     * sudah ada di edisi tujuan dilewati.
     *
     * @return array{dibuat:int, dilewati:int}
     */
    public function salinDariEdisi(Edisi $sumber, Edisi $tujuan, bool $salinKuota = true): array
    {
        $hasil = ['dibuat' => 0, 'dilewati' => 0];

        if ($sumber->is($tujuan)) {
            throw ValidationException::withMessages(['sumber' => 'Edisi sumber dan tujuan tidak boleh sama.']);
        }

        DB::transaction(function () use ($sumber, $tujuan, $salinKuota, &$hasil) {
            $lomba = $sumber->lomba()->with('mataLomba')->orderBy('urutan')->get();

            foreach ($lomba as $l) {
                $m = $l->mataLomba;

                if (! $m || ! $m->is_aktif || $this->sudahAda($tujuan, $m)) {
                    $hasil['dilewati']++;

                    continue;
                }

                $this->dariMaster(
                    $tujuan,
                    $m,
                    $salinKuota ? [1 => $l->kuota_juara_1, 2 => $l->kuota_juara_2, 3 => $l->kuota_juara_3] : [],
                    $l->urutan,
                );
                $hasil['dibuat']++;
            }
        });

        return $hasil;
    }

    /**
     * Samakan daftar lomba edisi dengan master mata lomba aktif:
     * - mata lomba aktif yang belum ada → dibuatkan lombanya (kuota dari master);
     * - lomba yang mata lombanya dinonaktifkan → dihapus, KECUALI sudah punya juara atau pendaftar.
     *
     * Dipakai untuk edisi aktif agar halaman cabor di admin langsung berisi semua lomba.
     *
     * @return array{dibuat:int, dihapus:int}
     */
    public function sinkronMaster(Edisi $edisi, ?int $caborId = null): array
    {
        return DB::transaction(function () use ($edisi, $caborId) {
            $ada = Lomba::query()->where('edisi_id', $edisi->id)
                ->when($caborId, fn ($q) => $q->where('cabor_id', $caborId))
                ->pluck('mata_lomba_id');

            $baru = MataLomba::query()->aktif()
                ->when($caborId, fn ($q) => $q->where('mata_lomba.cabor_id', $caborId))
                ->whereNotIn('mata_lomba.id', $ada)
                ->pluck('mata_lomba.id');

            $dibuat = $baru->isEmpty() ? 0 : $this->tambahBanyak($edisi, $baru)['dibuat'];

            $dihapus = Lomba::query()->where('edisi_id', $edisi->id)
                ->when($caborId, fn ($q) => $q->where('cabor_id', $caborId))
                ->whereHas('mataLomba', fn ($q) => $q->where('is_aktif', false))
                ->doesntHave('hasil')
                ->doesntHave('pendaftaran')
                ->get()
                ->each->delete()
                ->count();

            return ['dibuat' => $dibuat, 'dihapus' => $dihapus];
        });
    }

    /** Sinkron master untuk edisi aktif (jika ada). */
    public function sinkronEdisiAktif(?int $caborId = null): void
    {
        if ($edisi = Edisi::query()->aktif()->first()) {
            $this->sinkronMaster($edisi, $caborId);
        }
    }

    public function sudahAda(Edisi $edisi, MataLomba $master): bool
    {
        return Lomba::query()->where('edisi_id', $edisi->id)->where('mata_lomba_id', $master->id)->exists();
    }
}
