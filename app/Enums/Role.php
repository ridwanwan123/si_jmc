<?php

namespace App\Enums;

/** Role pengguna panel admin. */
enum Role: string
{
    /** Semua menu: master data, semua cabor, berita, galeri, kelola user. */
    case Superadmin = 'superadmin';

    /** Hanya cabor miliknya (users.cabor_id): lomba, input juara, verifikasi pendaftaran. */
    case AdminCabor = 'admin_cabor';

    /** Berita dan galeri. */
    case AdminBerita = 'admin_berita';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::AdminCabor => 'Admin cabor',
            self::AdminBerita => 'Admin berita',
        };
    }

    public function warna(): string
    {
        return match ($this) {
            self::Superadmin => 'danger',
            self::AdminCabor => 'info',
            self::AdminBerita => 'success',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $r) => [$r->value => $r->label()])->all();
    }
}
