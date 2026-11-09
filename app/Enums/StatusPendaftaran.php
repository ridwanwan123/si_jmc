<?php

namespace App\Enums;

enum StatusPendaftaran: string
{
    case Menunggu = 'menunggu';
    case Diverifikasi = 'diverifikasi';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu verifikasi',
            self::Diverifikasi => 'Diverifikasi',
            self::Ditolak => 'Ditolak',
        };
    }

    /** Warna badge Filament. */
    public function warna(): string
    {
        return match ($this) {
            self::Menunggu => 'warning',
            self::Diverifikasi => 'success',
            self::Ditolak => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
