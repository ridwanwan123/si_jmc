<?php

namespace App\Imports;

use App\Enums\Jenjang;
use App\Enums\Kota;
use App\Enums\StatusMadrasah;
use App\Models\Madrasah;
use Illuminate\Support\Facades\DB;

/**
 * Import madrasah. Kolom: NPSN, Nama, Jenjang, Status, Provinsi, Kota,
 * Kecamatan, Kelurahan, Alamat. NPSN yang sudah ada diperbarui, NPSN baru ditambahkan.
 * Kota menerima "Jakarta Timur" maupun format Kemenag "KOTA ADM. JAKARTA TIMUR".
 */
class MadrasahImport extends ImportExcel
{
    public static function header(): array
    {
        return ['NPSN', 'Nama', 'Jenjang', 'Status', 'Provinsi', 'Kota', 'Kecamatan', 'Kelurahan', 'Alamat'];
    }

    public static function contoh(): array
    {
        return ['20178214', 'MTsN 1 Jakarta', 'MTs', 'Negeri', 'DKI Jakarta', 'Jakarta Pusat', 'Gambir', 'Cideng', 'Jl. Contoh No. 1'];
    }

    public function import(string $path): LaporanImport
    {
        $laporan = new LaporanImport;
        $data = [];
        $npsnDipakai = [];

        foreach ($this->baca($path) as $i => $row) {
            $no = $i + 2;
            $npsn = $this->kolom($row, 'npsn');
            $nama = $this->kolom($row, 'nama', 'nama_madrasah');
            $jenjang = $this->cocokEnum(Jenjang::class, $this->kolom($row, 'jenjang', 'jenjang_madrasah'));
            $status = $this->cocokEnum(StatusMadrasah::class, $this->kolom($row, 'status', 'status_madrasah'));
            $teksKota = $this->kolom($row, 'kota');
            $kota = Kota::dariTeks($teksKota);

            $salah = [];
            if ($npsn === null) {
                $salah[] = 'NPSN wajib diisi';
            } elseif (mb_strlen($npsn) > 20) {
                $salah[] = 'NPSN maksimal 20 karakter';
            } elseif (isset($npsnDipakai[$npsn])) {
                $salah[] = "NPSN {$npsn} sudah ada di baris {$npsnDipakai[$npsn]}";
            }
            if ($nama === null) {
                $salah[] = 'nama wajib diisi';
            }
            if (! $jenjang) {
                $salah[] = 'jenjang harus salah satu dari '.$this->daftar(Jenjang::cases());
            }
            if (! $status) {
                $salah[] = 'status harus Negeri atau Swasta';
            }
            if (! $kota) {
                $salah[] = ($teksKota ? "kota \"{$teksKota}\" tidak dikenal" : 'kota wajib diisi')
                    .'; pilihan: '.$this->daftar(Kota::cases());
            }

            if ($salah) {
                $laporan->errors[] = "Baris {$no}: ".implode('; ', $salah).'.';

                continue;
            }

            $npsnDipakai[$npsn] = $no;
            $data[] = [
                'npsn' => $npsn,
                'nama' => $nama,
                'jenjang' => $jenjang,
                'status' => $status,
                'provinsi' => $this->kolom($row, 'provinsi'),
                'kota' => $kota,
                'kecamatan' => $this->kolom($row, 'kecamatan'),
                'kelurahan' => $this->kolom($row, 'kelurahan'),
                'alamat_sekolah' => $this->kolom($row, 'alamat', 'alamat_sekolah'),
            ];
        }

        if ($data === [] && $laporan->berhasil()) {
            $laporan->errors[] = 'File tidak berisi data.';
        }

        if (! $laporan->berhasil()) {
            return $laporan;
        }

        DB::transaction(function () use ($data, $laporan) {
            foreach ($data as $d) {
                $m = Madrasah::updateOrCreate(['npsn' => $d['npsn']], $d);
                $m->wasRecentlyCreated ? $laporan->dibuat++ : $laporan->diperbarui++;
            }
        });

        return $laporan;
    }
}
