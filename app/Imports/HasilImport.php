<?php

namespace App\Imports;

use App\Enums\Jenjang;
use App\Enums\Kategori;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\Lomba;
use App\Models\Madrasah;
use App\Services\HasilService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Import hasil (juara) ke satu edisi, format satu baris per juara.
 * Kolom: Cabor, Lomba, Jenjang, Kategori, Peringkat, NPSN Madrasah, Nama Peserta.
 *
 * Mode GANTI: untuk setiap lomba yang muncul di file, seluruh juara lamanya diganti
 * dengan isi file (lewat HasilService::simpan), sehingga import ulang tidak menggandakan.
 * Lomba yang tidak ada di file tidak disentuh. Semua-atau-tidak-sama-sekali.
 */
class HasilImport extends ImportExcel
{
    public function __construct(private readonly HasilService $hasilService) {}

    public static function header(): array
    {
        return ['Cabor', 'Lomba', 'Jenjang', 'Kategori', 'Peringkat', 'NPSN Madrasah', 'Nama Peserta'];
    }

    public static function contoh(): array
    {
        return ['Olahraga', 'Tenis Meja', 'MTs', 'Putri', '1', '20178214', 'Siti Aminah'];
    }

    public function import(Edisi $edisi, string $path): LaporanImport
    {
        $laporan = new LaporanImport;
        $rows = $this->baca($path);

        $cabor = $this->petaCabor();
        $lomba = $edisi->lomba()->get()->keyBy(fn (Lomba $l) => $this->kunciLomba(
            $l->cabor_id, $l->nama, $l->jenjang, $l->kategori,
        ));
        $npsnDiFile = array_filter(array_map(fn ($r) => $this->kolom($r, 'npsn_madrasah', 'npsn'), $rows));
        $madrasah = Madrasah::query()->whereIn('npsn', $npsnDiFile)->pluck('id', 'npsn');

        /** @var array<int, array{lomba: Lomba, baris: list<array<string, mixed>>}> $perLomba */
        $perLomba = [];

        foreach ($rows as $i => $row) {
            $no = $i + 2;
            $namaCabor = $this->kolom($row, 'cabor');
            $caborId = $namaCabor ? ($cabor[mb_strtolower($namaCabor)] ?? null) : null;
            $namaLomba = $this->kolom($row, 'lomba', 'nama_lomba');
            $jenjang = $this->cocokEnum(Jenjang::class, $this->kolom($row, 'jenjang'));
            $kategori = $this->cocokEnum(Kategori::class, $this->kolom($row, 'kategori'));
            $peringkat = $this->kolom($row, 'peringkat', 'juara');
            $npsn = $this->kolom($row, 'npsn_madrasah', 'npsn');
            $peserta = $this->kolom($row, 'nama_peserta', 'peserta');

            $salah = [];
            if (! $caborId) {
                $salah[] = $namaCabor ? "cabor \"{$namaCabor}\" tidak dikenal" : 'cabor wajib diisi';
            }
            if ($namaLomba === null) {
                $salah[] = 'nama lomba wajib diisi';
            }
            if (! $jenjang) {
                $salah[] = 'jenjang harus salah satu dari '.$this->daftar(Jenjang::cases());
            }
            if (! $kategori) {
                $salah[] = 'kategori harus salah satu dari '.$this->daftar(Kategori::cases());
            }
            if (! in_array($peringkat, ['1', '2', '3'], true)) {
                $salah[] = 'peringkat harus 1, 2, atau 3';
            }
            if ($npsn === null) {
                $salah[] = 'NPSN madrasah wajib diisi';
            } elseif (! isset($madrasah[$npsn])) {
                $salah[] = "madrasah dengan NPSN {$npsn} tidak ditemukan";
            }

            $l = null;
            if ($caborId && $namaLomba !== null && $jenjang && $kategori) {
                $l = $lomba->get($this->kunciLomba($caborId, $namaLomba, $jenjang, $kategori));
                if (! $l) {
                    $salah[] = "lomba \"{$namaLomba} · {$jenjang->value} · {$kategori->value}\" ({$namaCabor}) tidak ada di {$edisi->nama}";
                }
            }

            if ($salah) {
                $laporan->errors[] = "Baris {$no}: ".implode('; ', $salah).'.';

                continue;
            }

            $perLomba[$l->id]['lomba'] = $l;
            $perLomba[$l->id]['baris'][] = [
                'peringkat' => (int) $peringkat,
                'madrasah_id' => $madrasah[$npsn],
                'nama_peserta' => $peserta,
            ];
        }

        if ($rows === []) {
            $laporan->errors[] = 'File tidak berisi data.';
        }

        // Cek kuota per lomba lewat satu-satunya tempat validasi.
        foreach ($perLomba as $item) {
            try {
                $this->hasilService->validasi($item['lomba'], $item['baris']);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $pesan) {
                    $laporan->errors[] = "{$item['lomba']->namaLengkap()}: {$pesan[0]}";
                }
            }
        }

        if (! $laporan->berhasil()) {
            return $laporan;
        }

        DB::transaction(function () use ($perLomba, $laporan) {
            foreach ($perLomba as $item) {
                $laporan->dibuat += $this->hasilService->simpan($item['lomba'], $item['baris'])->count();
            }
        });

        return $laporan;
    }

    private function kunciLomba(int $caborId, string $nama, Jenjang $jenjang, Kategori $kategori): string
    {
        return implode('|', [$caborId, mb_strtolower(trim($nama)), $jenjang->value, $kategori->value]);
    }
}
