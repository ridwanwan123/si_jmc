<?php

namespace App\Enums;

/**
 * Medali diturunkan dari hasil.peringkat: 1 = Emas, 2 = Perak, 3 = Perunggu.
 */
enum Medali: int
{
    case Emas = 1;
    case Perak = 2;
    case Perunggu = 3;

    public function label(): string
    {
        return $this->name;
    }

    /** Token warna Tailwind (lihat CLAUDE.md). */
    public function warna(): string
    {
        return match ($this) {
            self::Emas => 'emas',
            self::Perak => 'perak',
            self::Perunggu => 'perunggu',
        };
    }

    public function hex(): string
    {
        return match ($this) {
            self::Emas => '#E3AE2A',
            self::Perak => '#A8AFB9',
            self::Perunggu => '#B8692E',
        };
    }

    /** Kuota juara default per peringkat. */
    public function kuotaDefault(): int
    {
        return match ($this) {
            self::Emas => 1,
            self::Perak => 2,
            self::Perunggu => 3,
        };
    }
}
