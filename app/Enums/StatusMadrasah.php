<?php

namespace App\Enums;

enum StatusMadrasah: string
{
    case Negeri = 'Negeri';
    case Swasta = 'Swasta';

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->value])->all();
    }
}
