<?php

namespace App\Imports;

/**
 * Ringkasan hasil satu kali import Excel.
 */
final class LaporanImport
{
    /** @param list<string> $errors */
    public function __construct(
        public int $dibuat = 0,
        public int $diperbarui = 0,
        public array $errors = [],
    ) {}

    public function berhasil(): bool
    {
        return $this->errors === [];
    }

    public function ringkasan(): string
    {
        return "{$this->dibuat} data baru, {$this->diperbarui} data diperbarui.";
    }
}
