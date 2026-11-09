<?php

namespace App\Enums;

enum Kategori: string
{
    case Putra = 'Putra';
    case Putri = 'Putri';
    case Umum = 'Umum';
    case Beregu = 'Beregu';

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $k) => [$k->value => $k->value])->all();
    }
}
