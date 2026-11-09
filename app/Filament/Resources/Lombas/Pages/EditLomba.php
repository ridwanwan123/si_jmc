<?php

namespace App\Filament\Resources\Lombas\Pages;

use App\Filament\Pages\InputJuara;
use App\Filament\Pages\KelolaCabor;
use App\Filament\Resources\Lombas\LombaResource;
use App\Models\Lomba;
use App\Services\HasilService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Pengaturan lomba (kuota, jumlah anggota, urutan). Juara diisi di halaman Input Juara.
 */
class EditLomba extends EditRecord
{
    protected static string $resource = LombaResource::class;

    protected static ?string $title = 'Pengaturan lomba';

    public function getBreadcrumbs(): array
    {
        /** @var Lomba $lomba */
        $lomba = $this->getRecord();

        return [
            KelolaCabor::getUrl(['cabor' => $lomba->cabor->slug]) => $lomba->cabor->nama,
            $lomba->namaLengkap(),
            'Pengaturan',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('inputJuara')
                ->label('Input juara')
                ->icon(Heroicon::OutlinedTrophy)
                ->url(fn () => InputJuara::getUrl(['lomba' => $this->getRecord()])),
            DeleteAction::make()
                ->modalDescription('Semua juara dan pendaftaran lomba ini ikut terhapus.')
                ->successRedirectUrl(fn () => KelolaCabor::getUrl(['cabor' => $this->getRecord()->cabor->slug])),
        ];
    }

    /** @param Lomba $record */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Kuota tidak boleh lebih kecil dari jumlah juara yang sudah tersimpan.
        try {
            app(HasilService::class)->validasiPerubahanKuota($record, [
                1 => (int) ($data['kuota_juara_1'] ?? $record->kuota_juara_1),
                2 => (int) ($data['kuota_juara_2'] ?? $record->kuota_juara_2),
                3 => (int) ($data['kuota_juara_3'] ?? $record->kuota_juara_3),
            ]);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn ($msgs, $key) => ["data.{$key}" => $msgs])->all()
            );
        }

        $record->update($data);

        return $record;
    }

    protected function getRedirectUrl(): ?string
    {
        return KelolaCabor::getUrl(['cabor' => $this->getRecord()->cabor->slug]);
    }
}
