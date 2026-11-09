<?php

namespace App\Filament\Resources\Lombas\Pages;

use App\Filament\Resources\Lombas\LombaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLomba extends CreateRecord
{
    protected static string $resource = LombaResource::class;

    /** Setelah lomba dibuat, langsung ke halaman edit untuk input hasil. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
