<?php

namespace App\Filament\Resources\Madrasahs\Pages;

use App\Filament\Resources\Madrasahs\MadrasahResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMadrasah extends EditRecord
{
    protected static string $resource = MadrasahResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MadrasahResource::hapusAction(DeleteAction::make()),
        ];
    }
}
