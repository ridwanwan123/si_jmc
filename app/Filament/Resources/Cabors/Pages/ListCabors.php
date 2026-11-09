<?php

namespace App\Filament\Resources\Cabors\Pages;

use App\Filament\Resources\Cabors\CaborResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCabors extends ListRecords
{
    protected static string $resource = CaborResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
