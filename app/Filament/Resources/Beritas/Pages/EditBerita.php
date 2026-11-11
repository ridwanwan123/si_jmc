<?php

namespace App\Filament\Resources\Beritas\Pages;

use App\Filament\Resources\Beritas\BeritaResource;
use App\Models\Berita;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditBerita extends EditRecord
{
    protected static string $resource = BeritaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('lihat')
                ->label('Lihat di web')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Berita $record) => route('berita.show', ['berita' => $record->slug] + ($record->edisi?->is_aktif ? [] : ['edisi' => $record->edisi?->tahun])), shouldOpenInNewTab: true)
                ->visible(fn (Berita $record) => $record->status() === 'terbit'),
            BeritaResource::terbitkanAction(),
            BeritaResource::jadikanDrafAction(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return BeritaResource::rapikanData($data, $this->record);
    }
}
