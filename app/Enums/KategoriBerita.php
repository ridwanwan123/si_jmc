<?php

namespace App\Enums;

enum KategoriBerita: string
{
    case Pengumuman = 'pengumuman';
    case Liputan = 'liputan';
    case Hasil = 'hasil';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Kelas label kecil (chip) di kartu berita. */
    public function kelasChip(): string
    {
        return match ($this) {
            self::Pengumuman => 'bg-ink text-white',
            self::Liputan => 'bg-[#E4ECFF] text-accent-tua',
            self::Hasil => 'bg-[#FBEFCF] text-[#7A5A00]',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $k) => [$k->value => $k->label()])->all();
    }
}
