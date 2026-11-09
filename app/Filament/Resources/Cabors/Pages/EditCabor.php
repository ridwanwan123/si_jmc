<?php

namespace App\Filament\Resources\Cabors\Pages;

use App\Filament\Resources\Cabors\CaborResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCabor extends EditRecord
{
    protected static string $resource = CaborResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CaborResource::hapusAction(DeleteAction::make()),
        ];
    }
}
