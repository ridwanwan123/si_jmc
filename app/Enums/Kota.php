<?php

namespace App\Enums;

/** Kota/kabupaten administrasi di DKI Jakarta (kolom `madrasah.kota`). */
enum Kota: string
{
    case JakartaPusat = 'Jakarta Pusat';
    case JakartaUtara = 'Jakarta Utara';
    case JakartaBarat = 'Jakarta Barat';
    case JakartaSelatan = 'Jakarta Selatan';
    case JakartaTimur = 'Jakarta Timur';
    case KepulauanSeribu = 'Kepulauan Seribu';

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $k) => [$k->value => $k->value])->all();
    }

    /**
     * Cocokkan teks bebas ke kota, termasuk format data Kemenag/si_presma,
     * mis. "KOTA ADM. JAKARTA TIMUR" atau "ADM. KEP. SERIBU".
     */
    public static function dariTeks(?string $teks): ?self
    {
        if ($teks === null) {
            return null;
        }

        $t = mb_strtolower(trim($teks));

        foreach (self::cases() as $k) {
            if ($t === mb_strtolower($k->value)) {
                return $k;
            }
        }

        if (str_contains($t, 'seribu')) {
            return self::KepulauanSeribu;
        }

        foreach (['pusat', 'utara', 'barat', 'selatan', 'timur'] as $arah) {
            if (str_contains($t, 'jakarta') && preg_match('/\b'.$arah.'\b/', $t)) {
                return self::from('Jakarta '.ucfirst($arah));
            }
        }

        return null;
    }
}
