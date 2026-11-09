<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Template .xlsx untuk import: baris header + satu baris contoh.
 */
class TemplateExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @param  list<string>  $header
     * @param  list<list<string>>  $baris
     */
    public function __construct(private array $header, private array $baris = []) {}

    public function headings(): array
    {
        return $this->header;
    }

    public function array(): array
    {
        return $this->baris;
    }
}
