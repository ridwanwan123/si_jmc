<?php

namespace App\Filament\Resources\GaleriAlbums\Pages;

use App\Filament\Resources\GaleriAlbums\GaleriAlbumResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGaleriAlbums extends ListRecords
{
    protected static string $resource = GaleriAlbumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
