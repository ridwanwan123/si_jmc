<?php

namespace App\Filament\Resources\Edisis\Pages;

use App\Filament\Resources\Edisis\EdisiResource;
use App\Services\LombaService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEdisi extends EditRecord
{
    protected static string $resource = EdisiResource::class;

    /** Edisi aktif langsung berisi semua mata lomba aktif dari master. */
    protected function afterSave(): void
    {
        if ($this->getRecord()->is_aktif) {
            app(LombaService::class)->sinkronMaster($this->getRecord());
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            EdisiResource::hapusAction(DeleteAction::make()),
        ];
    }
}
