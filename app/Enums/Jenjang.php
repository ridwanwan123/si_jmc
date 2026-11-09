<?php

namespace App\Enums;

enum Jenjang: string
{
    case RA = 'RA';
    case MI = 'MI';
    case MTs = 'MTs';
    case MA = 'MA';

    public function label(): string
    {
        return match ($this) {
            self::RA => 'Raudhatul Athfal',
            self::MI => 'Madrasah Ibtidaiyah',
            self::MTs => 'Madrasah Tsanawiyah',
            self::MA => 'Madrasah Aliyah',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $j) => [$j->value => $j->value])->all();
    }

    /**
     * Jenjang yang ikut klasemen umum (lihat config/jmc.php).
     *
     * @return list<self>
     */
    public static function klasemenUmum(): array
    {
        return array_map(fn (string $v) => self::from($v), config('jmc.jenjang_klasemen_umum'));
    }
}
