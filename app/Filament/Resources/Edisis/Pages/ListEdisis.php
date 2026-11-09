<?php

namespace App\Filament\Resources\Edisis\Pages;

use App\Filament\Resources\Edisis\EdisiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEdisis extends ListRecords
{
    protected static string $resource = EdisiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
