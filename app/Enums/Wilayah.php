<?php

namespace App\Enums;

enum Wilayah: string
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
        return collect(self::cases())->mapWithKeys(fn (self $w) => [$w->value => $w->value])->all();
    }
}
