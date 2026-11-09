<?php

namespace App\Filament\Resources\GaleriAlbums\Pages;

use App\Filament\Resources\GaleriAlbums\GaleriAlbumResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGaleriAlbum extends CreateRecord
{
    protected static string $resource = GaleriAlbumResource::class;

    /** Setelah album dibuat, langsung ke halaman edit untuk upload foto. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
