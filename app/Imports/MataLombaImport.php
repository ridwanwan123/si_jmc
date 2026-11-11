<?php

namespace App\Imports;

use App\Enums\Jenjang;
use App\Enums\Kategori;
use App\Models\MataLomba;
use App\Services\LombaService;
use Illuminate\Support\Facades\DB;

/**
 * Import master mata lomba.
 * Kolom: Cabor, Nama, Jenjang, Kategori, Kuota Juara 1/2/3 (kosong = default 1/2/3).
 * Mata lomba yang sama (cabor + nama + jenjang + kategori) diperbarui kuotanya.
 */
class MataLombaImport extends ImportExcel
{
    public static function header(): array
    {
        return ['Cabor', 'Nama', 'Jenjang', 'Kategori', 'Maksimal Anggota', 'Kuota Juara 1', 'Kuota Juara 2', 'Kuota Juara 3'];
    }

    public static function contoh(): array
    {
        return ['Olahraga', 'Tenis Meja', 'MTs', 'Putri', '1', '', '', ''];
    }

    public function import(string $path): LaporanImport
    {
        $laporan = new LaporanImport;
        $cabor = $this->petaCabor();
        $data = [];
        $dipakai = [];

        foreach ($this->baca($path) as $i => $row) {
            $no = $i + 2;
            $namaCabor = $this->kolom($row, 'cabor');
            $caborId = $namaCabor ? ($cabor[mb_strtolower($namaCabor)] ?? null) : null;
            $nama = $this->kolom($row, 'nama', 'nama_lomba', 'lomba', 'mata_lomba');
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
            [$kuota, $salahKuota] = $this->kuota($row);
            // Maksimal anggota (opsional): kosong = 1 (perorangan). Header lama "Jumlah Anggota" tetap diterima.
            $anggotaMentah = $this->kolom($row, 'maksimal_anggota', 'jumlah_anggota', 'anggota');
            $anggota = $anggotaMentah === null ? 1 : (ctype_digit((string) $anggotaMentah) ? (int) $anggotaMentah : 0);
            if ($anggota < 1 || $anggota > 30) {
                $salah[] = 'maksimal anggota harus angka 1–30';
            }
            array_push($salah, ...$salahKuota);

            if (! $salah) {
                $kunci = implode('|', [$caborId, mb_strtolower($nama), $jenjang->value, $kategori->value]);
                if (isset($dipakai[$kunci])) {
                    $salah[] = "mata lomba ini sudah ada di baris {$dipakai[$kunci]}";
                }
            }

            if ($salah) {
                $laporan->errors[] = "Baris {$no}: ".implode('; ', $salah).'.';

                continue;
            }

            $dipakai[$kunci] = $no;
            $data[] = compact('caborId', 'nama', 'jenjang', 'kategori', 'kuota', 'anggota');
        }

        if ($data === [] && $laporan->berhasil()) {
            $laporan->errors[] = 'File tidak berisi data.';
        }

        if (! $laporan->berhasil()) {
            return $laporan;
        }

        DB::transaction(function () use ($data, $laporan) {
            $urutan = (int) MataLomba::max('urutan');

            foreach ($data as $d) {
                $m = MataLomba::query()->firstOrNew([
                    'cabor_id' => $d['caborId'],
                    'nama' => $d['nama'],
                    'jenjang' => $d['jenjang']->value,
                    'kategori' => $d['kategori']->value,
                ]);
                $baru = ! $m->exists;

                $m->fill([
                    'jumlah_anggota' => $d['anggota'],
                    'kuota_juara_1' => $d['kuota'][1],
                    'kuota_juara_2' => $d['kuota'][2],
                    'kuota_juara_3' => $d['kuota'][3],
                ]);
                if ($baru) {
                    $m->urutan = ++$urutan;
                    $m->is_aktif = true;
                }
                $m->save();

                $baru ? $laporan->dibuat++ : $laporan->diperbarui++;
            }
        });

        // Edisi aktif langsung berisi mata lomba baru.
        app(LombaService::class)->sinkronEdisiAktif();

        return $laporan;
    }

    /** @return array{0: array{1:int,2:int,3:int}, 1: list<string>} */
    private function kuota(array $row): array
    {
        $kuota = [];
        $salah = [];

        foreach ([1 => 1, 2 => 2, 3 => 3] as $p => $default) {
            $v = $this->kolom($row, "kuota_juara_{$p}", "kuota_{$p}");
            if ($v === null) {
                $kuota[$p] = $default;
            } elseif (ctype_digit($v) && (int) $v <= 20) {
                $kuota[$p] = (int) $v;
            } else {
                $salah[] = "kuota juara {$p} harus angka 0–20";
            }
        }

        return [$kuota, $salah];
    }
}
