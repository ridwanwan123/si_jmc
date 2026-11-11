<?php

namespace App\Data;

use App\Enums\Jenjang;

/**
 * Satu baris klasemen medali (hanya-baca). Dibuat oleh KlasemenService.
 */
final readonly class BarisKlasemen
{
    public function __construct(
        public int $madrasahId,
        public string $npsn,
        public string $nama,
        public Jenjang $jenjang,
        public string $kota,
        public string $status,
        public int $emas,
        public int $perak,
        public int $perunggu,
        public int $peringkat = 0,
        public bool $seri = false,
    ) {}

    public function total(): int
    {
        return $this->emas + $this->perak + $this->perunggu;
    }

    /** Kunci pembanding untuk menentukan peringkat sama. */
    public function kunciMedali(): string
    {
        return "{$this->emas}-{$this->perak}-{$this->perunggu}";
    }

    /** Label tampil, mis. "01" atau "=04". */
    public function labelPeringkat(): string
    {
        // Peringkat sama tetap bernomor sama (mis. 03, 03, lalu 05), tanpa tanda "=" (permintaan tampilan).
        return str_pad((string) $this->peringkat, 2, '0', STR_PAD_LEFT);
    }

    public function denganPeringkat(int $peringkat, bool $seri): self
    {
        return new self(
            $this->madrasahId, $this->npsn, $this->nama, $this->jenjang, $this->kota, $this->status,
            $this->emas, $this->perak, $this->perunggu, $peringkat, $seri,
        );
    }
}
