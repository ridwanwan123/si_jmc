<?php

namespace App\Imports;

use App\Models\Cabor;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Dasar import Excel: membaca sheet pertama (baris 1 = header) menjadi array.
 *
 * Header diubah ke snake_case, mis. "NSM Madrasah" → nsm_madrasah,
 * "Kuota Juara 1" → kuota_juara_1. Nomor baris Excel = indeks + 2.
 *
 * Semua import bersifat semua-atau-tidak-sama-sekali: jika ada satu baris
 * bermasalah, tidak ada data yang disimpan.
 */
abstract class ImportExcel
{
    /** Header kolom template (urutan tampil di file template). */
    abstract public static function header(): array;

    /** Satu baris contoh untuk template. */
    abstract public static function contoh(): array;

    /** @return list<array<string, mixed>> */
    protected function baca(string $path): array
    {
        $sheets = Excel::toArray(new class implements ToArray, WithHeadingRow
        {
            public function array(array $array): void {}
        }, $path);

        $rows = $sheets[0] ?? [];

        // Lewati baris yang benar-benar kosong.
        return array_values(array_filter(
            $rows,
            fn (array $r) => collect($r)->filter(fn ($v) => filled($v))->isNotEmpty(),
        ));
    }

    /** Nilai sel sebagai teks bersih (angka 1234.0 → "1234"). */
    protected function teks(mixed $nilai): ?string
    {
        if ($nilai === null) {
            return null;
        }

        if (is_float($nilai) && floor($nilai) === $nilai) {
            $nilai = (string) (int) $nilai;
        }

        $nilai = trim((string) $nilai);

        return $nilai === '' ? null : $nilai;
    }

    /** Ambil nilai kolom pertama yang tersedia dari beberapa kemungkinan nama header. */
    protected function kolom(array $row, string ...$nama): ?string
    {
        foreach ($nama as $n) {
            if (array_key_exists($n, $row)) {
                return $this->teks($row[$n]);
            }
        }

        return null;
    }

    /**
     * Cocokkan teks ke enum string tanpa membedakan huruf besar/kecil.
     *
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    protected function cocokEnum(string $enum, ?string $nilai): ?\BackedEnum
    {
        if ($nilai === null) {
            return null;
        }

        foreach ($enum::cases() as $case) {
            if (mb_strtolower($case->value) === mb_strtolower($nilai)) {
                return $case;
            }
        }

        return null;
    }

    /** @return array<string, int> nama/slug cabor (huruf kecil) → id */
    protected function petaCabor(): array
    {
        $peta = [];
        foreach (Cabor::all(['id', 'nama', 'slug']) as $c) {
            $peta[mb_strtolower($c->nama)] = $c->id;
            $peta[mb_strtolower($c->slug)] = $c->id;
        }

        return $peta;
    }

    /** @param list<\BackedEnum> $cases */
    protected function daftar(array $cases): string
    {
        return implode(', ', array_map(fn (\BackedEnum $c) => $c->value, $cases));
    }
}
