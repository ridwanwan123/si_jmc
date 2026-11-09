<?php

namespace App\Imports;

use App\Enums\Jenjang;
use App\Enums\StatusMadrasah;
use App\Enums\Wilayah;
use App\Models\Madrasah;
use Illuminate\Support\Facades\DB;

/**
 * Import madrasah. Kolom: NSM, Nama, Jenjang, Status, Wilayah.
 * NSM yang sudah ada diperbarui, NSM baru ditambahkan.
 */
class MadrasahImport extends ImportExcel
{
    public static function header(): array
    {
        return ['NSM', 'Nama', 'Jenjang', 'Status', 'Wilayah'];
    }

    public static function contoh(): array
    {
        return ['111231710001', 'MTsN 1 Jakarta', 'MTs', 'Negeri', 'Jakarta Pusat'];
    }

    public function import(string $path): LaporanImport
    {
        $laporan = new LaporanImport;
        $data = [];
        $nsmDipakai = [];

        foreach ($this->baca($path) as $i => $row) {
            $no = $i + 2;
            $nsm = $this->kolom($row, 'nsm');
            $nama = $this->kolom($row, 'nama', 'nama_madrasah');
            $jenjang = $this->cocokEnum(Jenjang::class, $this->kolom($row, 'jenjang'));
            $status = $this->cocokEnum(StatusMadrasah::class, $this->kolom($row, 'status'));
            $wilayah = $this->cocokEnum(Wilayah::class, $this->kolom($row, 'wilayah'));

            $salah = [];
            if ($nsm === null) {
                $salah[] = 'NSM wajib diisi';
            } elseif (mb_strlen($nsm) > 20) {
                $salah[] = 'NSM maksimal 20 karakter';
            } elseif (isset($nsmDipakai[$nsm])) {
                $salah[] = "NSM {$nsm} sudah ada di baris {$nsmDipakai[$nsm]}";
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
            if (! $wilayah) {
                $salah[] = 'wilayah harus salah satu dari '.$this->daftar(Wilayah::cases());
            }

            if ($salah) {
                $laporan->errors[] = "Baris {$no}: ".implode('; ', $salah).'.';

                continue;
            }

            $nsmDipakai[$nsm] = $no;
            $data[] = compact('nsm', 'nama', 'jenjang', 'status', 'wilayah');
        }

        if ($data === [] && $laporan->berhasil()) {
            $laporan->errors[] = 'File tidak berisi data.';
        }

        if (! $laporan->berhasil()) {
            return $laporan;
        }

        DB::transaction(function () use ($data, $laporan) {
            foreach ($data as $d) {
                $m = Madrasah::updateOrCreate(['nsm' => $d['nsm']], $d);
                $m->wasRecentlyCreated ? $laporan->dibuat++ : $laporan->diperbarui++;
            }
        });

        return $laporan;
    }
}
