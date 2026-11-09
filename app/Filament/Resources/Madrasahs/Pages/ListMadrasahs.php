<?php

namespace App\Filament\Resources\Madrasahs\Pages;

use App\Filament\Actions\ImportExcelAction;
use App\Filament\Resources\Madrasahs\MadrasahResource;
use App\Imports\MadrasahImport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMadrasahs extends ListRecords
{
    protected static string $resource = MadrasahResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportExcelAction::template(MadrasahImport::class, 'template-madrasah.xlsx'),
            ImportExcelAction::make(
                'importMadrasah',
                'Import madrasah dari Excel',
                MadrasahImport::class,
                fn (string $path) => app(MadrasahImport::class)->import($path),
            ),
            CreateAction::make(),
        ];
    }
}
