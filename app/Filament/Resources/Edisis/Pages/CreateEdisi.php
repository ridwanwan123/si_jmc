<?php

namespace App\Filament\Resources\Edisis\Pages;

use App\Filament\Resources\Edisis\EdisiResource;
use App\Services\LombaService;
use Filament\Resources\Pages\CreateRecord;

class CreateEdisi extends CreateRecord
{
    protected static string $resource = EdisiResource::class;

    /** Edisi aktif langsung berisi semua mata lomba aktif dari master. */
    protected function afterCreate(): void
    {
        if ($this->getRecord()->is_aktif) {
            app(LombaService::class)->sinkronMaster($this->getRecord());
        }
    }
}
