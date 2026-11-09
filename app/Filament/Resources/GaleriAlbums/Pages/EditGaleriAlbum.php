<?php

namespace App\Filament\Resources\GaleriAlbums\Pages;

use App\Filament\Resources\GaleriAlbums\GaleriAlbumResource;
use App\Services\GaleriService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Edit album + upload banyak foto.
 *
 * Foto baru diproses di afterSave(), SETELAH repeater "foto" (relationship) disimpan,
 * supaya sinkronisasi repeater tidak menghapus foto yang baru ditambahkan.
 */
class EditGaleriAlbum extends EditRecord
{
    protected static string $resource = GaleriAlbumResource::class;

    /** @var list<string> path sementara di disk "local" */
    protected array $fotoBaru = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('Semua foto di album ini ikut terhapus (termasuk file-nya).'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->fotoBaru = array_values(array_filter((array) ($data['foto_baru'] ?? [])));
        unset($data['foto_baru']);

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->fotoBaru === []) {
            return;
        }

        $service = app(GaleriService::class);
        $local = Storage::disk('local');
        $berhasil = 0;
        $gagal = [];

        foreach ($this->fotoBaru as $path) {
            try {
                $service->tambahFoto($this->getRecord(), $local->path($path));
                $berhasil++;
            } catch (ValidationException $e) {
                $gagal[] = collect($e->errors())->flatten()->first();
            } finally {
                $local->delete($path);
            }
        }

        $this->fotoBaru = [];
        $this->getRecord()->refresh();
        $this->fillForm();

        Notification::make()
            ->title("{$berhasil} foto ditambahkan")
            ->body($gagal ? 'Gagal: '.implode(' ', $gagal) : null)
            ->{$gagal ? 'warning' : 'success'}()
            ->send();
    }
}
