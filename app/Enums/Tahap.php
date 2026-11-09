<?php

namespace App\Enums;

/**
 * Alur satu edisi JMC: Registrasi → Tanding → Klasemen.
 *
 * Sementara diturunkan dari data yang ada (tanggal edisi + ada tidaknya hasil),
 * lihat Edisi::tahap().
 */
enum Tahap: string
{
    case Registrasi = 'registrasi';
    case Tanding = 'tanding';
    case Klasemen = 'klasemen';

    public function label(): string
    {
        return match ($this) {
            self::Registrasi => 'Registrasi',
            self::Tanding => 'Tanding',
            self::Klasemen => 'Klasemen',
        };
    }

    public function nomor(): string
    {
        return str_pad((string) ($this->urutan() + 1), 2, '0', STR_PAD_LEFT);
    }

    public function urutan(): int
    {
        return array_search($this, self::cases(), true);
    }
}
