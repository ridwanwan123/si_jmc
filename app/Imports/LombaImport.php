<?php

namespace App\Imports;

use App\Enums\Jenjang;
use App\Enums\Kategori;
use App\Models\Edisi;
use App\Models\Lomba;
use App\Models\MataLomba;
use App\Services\HasilService;
use App\Services\LombaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Import lomba ke satu edisi. Setiap baris HARUS cocok dengan master mata lomba
 * (cabor + nama + jenjang + kategori) — tidak ada nama lomba bebas.
 * Kolom: Cabor, Nama, Jenjang, Kategori, Kuota Juara 1/2/3 (kosong = kuota master).
 * Lomba yang sudah ada di edisi itu diperbarui kuotanya.
 */
class LombaImport extends ImportExcel
{
    public function __construct(
        private readonly HasilService $hasilService,
        private readonly LombaService $lombaService,
    ) {}

    public static function header(): array
    {
        return ['Cabor', 'Nama', 'Jenjang', 'Kategori', 'Kuota Juara 1', 'Kuota Juara 2', 'Kuota Juara 3'];
    }

    public static function contoh(): array
    {
        return ['Olahraga', 'Tenis Meja', 'MTs', 'Putri', '', '', ''];
    }

    public function import(Edisi $edisi, string $path): LaporanImport
    {
        $laporan = new LaporanImport;
        $cabor = $this->petaCabor();
        $master = MataLomba::all()->keyBy(fn (MataLomba $m) => $this->kunci($m->cabor_id, $m->nama, $m->jenjang, $m->kategori));
        $data = [];
        $dipakai = [];

        foreach ($this->baca($path) as $i => $row) {
            $no = $i + 2;
            $namaCabor = $this->kolom($row, 'cabor');
            $caborId = $namaCabor ? ($cabor[mb_strtolower($namaCabor)] ?? null) : null;
            $nama = $this->kolom($row, 'nama', 'nama_lomba', 'lomba');
            $jenjang = $this->cocokEnum(Jenjang::class, $this->kolom($row, 'jenjang'));
            $kategori = $this->cocokEnum(Kategori::class, $this->kolom($row, 'kategori'));

            $salah = [];
            if (! $caborId) {
                $salah[] = $namaCabor ? "cabor \"{$namaCabor}\" tidak dikenal" : 'cabor wajib diisi';
            }
            if ($nama === null) {
                $salah[] = 'nama lomba wajib diisi';
            }
            if (! $jenjang) {
                $salah[] = 'jenjang harus salah satu dari '.$this->daftar(Jenjang::cases());
            }
            if (! $kategori) {
                $salah[] = 'kategori harus salah satu dari '.$this->daftar(Kategori::cases());
            }

            $kuota = [];
            foreach ([1, 2, 3] as $p) {
                $v = $this->kolom($row, "kuota_juara_{$p}", "kuota_{$p}");
                if ($v === null) {
                    continue; // pakai kuota master
                }
                if (ctype_digit($v) && (int) $v <= 20) {
                    $kuota[$p] = (int) $v;
                } else {
                    $salah[] = "kuota juara {$p} harus angka 0–20";
                }
            }

            $m = null;
            if (! $salah) {
                $kunci = $this->kunci($caborId, $nama, $jenjang, $kategori);
                $m = $master->get($kunci);

                if (! $m) {
                    $salah[] = "\"{$nama} · {$jenjang->value} · {$kategori->value}\" ({$namaCabor}) belum ada di master Mata Lomba";
                } elseif (! $m->is_aktif) {
                    $salah[] = "mata lomba \"{$m->namaLengkap()}\" nonaktif";
                } elseif (isset($dipakai[$kunci])) {
                    $salah[] = "lomba ini sudah ada di baris {$dipakai[$kunci]}";
                }
            }

            if ($salah) {
                $laporan->errors[] = "Baris {$no}: ".implode('; ', $salah).'.';

                continue;
            }

            $dipakai[$kunci] = $no;
            $data[] = ['no' => $no, 'master' => $m, 'kuota' => $kuota];
        }

        if ($data === [] && $laporan->berhasil()) {
            $laporan->errors[] = 'File tidak berisi data.';
        }

        // Lomba yang sudah ada: kuota baru tidak boleh di bawah jumlah juara tersimpan.
        foreach ($data as $k => $d) {
            $lomba = Lomba::query()->where('edisi_id', $edisi->id)->where('mata_lomba_id', $d['master']->id)->first();

            if ($lomba && $d['kuota'] !== []) {
                try {
                    $this->hasilService->validasiPerubahanKuota($lomba, $d['kuota']);
                } catch (ValidationException $e) {
                    $laporan->errors[] = "Baris {$d['no']}: ".implode(' ', array_merge(...array_values($e->errors())));
                }
            }

            $data[$k]['lomba'] = $lomba;
        }

        if (! $laporan->berhasil()) {
            return $laporan;
        }

        DB::transaction(function () use ($edisi, $data, $laporan) {
            foreach ($data as $d) {
                if ($d['lomba']) {
                    if ($d['kuota'] !== []) {
                        $d['lomba']->update(collect($d['kuota'])->mapWithKeys(fn ($v, $p) => ["kuota_juara_{$p}" => $v])->all());
                    }
                    $laporan->diperbarui++;
                } else {
                    $this->lombaService->dariMaster($edisi, $d['master'], $d['kuota']);
                    $laporan->dibuat++;
                }
            }
        });

        return $laporan;
    }

    private function kunci(int $caborId, string $nama, Jenjang $jenjang, Kategori $kategori): string
    {
        return implode('|', [$caborId, mb_strtolower(trim($nama)), $jenjang->value, $kategori->value]);
    }
}
