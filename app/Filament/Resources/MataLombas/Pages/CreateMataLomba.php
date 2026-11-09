<?php

namespace App\Filament\Resources\MataLombas\Pages;

use App\Filament\Resources\MataLombas\MataLombaResource;
use App\Services\LombaService;
use Filament\Resources\Pages\CreateRecord;

class CreateMataLomba extends CreateRecord
{
    protected static string $resource = MataLombaResource::class;

    /** Lomba di edisi aktif mengikuti master (tambah / nonaktif). */
    protected function afterCreate(): void
    {
        app(LombaService::class)->sinkronEdisiAktif($this->getRecord()->cabor_id);
    }
}
