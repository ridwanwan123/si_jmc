<?php

namespace App\Filament\Resources\MataLombas\Pages;

use App\Filament\Resources\MataLombas\MataLombaResource;
use App\Services\LombaService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMataLomba extends EditRecord
{
    protected static string $resource = MataLombaResource::class;

    /** Lomba di edisi aktif mengikuti master (tambah / nonaktif). */
    protected function afterSave(): void
    {
        app(LombaService::class)->sinkronEdisiAktif($this->getRecord()->cabor_id);
    }

    protected function getHeaderActions(): array
    {
        return [
            MataLombaResource::hapusAction(DeleteAction::make()),
        ];
    }
}
