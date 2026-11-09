<?php

namespace App\Filament\Resources\MataLombas\Pages;

use App\Filament\Actions\ImportExcelAction;
use App\Filament\Resources\MataLombas\MataLombaResource;
use App\Imports\MataLombaImport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMataLombas extends ListRecords
{
    protected static string $resource = MataLombaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportExcelAction::template(MataLombaImport::class, 'template-mata-lomba.xlsx'),
            ImportExcelAction::make(
                'importMataLomba',
                'Import mata lomba dari Excel',
                MataLombaImport::class,
                fn (string $path) => app(MataLombaImport::class)->import($path),
            ),
            CreateAction::make(),
        ];
    }
}
