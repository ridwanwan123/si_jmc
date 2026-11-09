<?php

namespace App\Filament\Resources\Lombas\Pages;

use App\Filament\Actions\ImportExcelAction;
use App\Filament\Resources\Lombas\LombaResource;
use App\Imports\HasilImport;
use App\Imports\LombaImport;
use App\Models\Edisi;
use App\Services\LombaService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class ListLombas extends ListRecords
{
    protected static string $resource = LombaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                $this->tambahDariMasterAction(),
                $this->salinDariEdisiAction(),
                ImportExcelAction::make(
                    'importLomba',
                    'Import lomba dari Excel',
                    LombaImport::class,
                    fn (string $path, Edisi $edisi) => app(LombaImport::class)->import($edisi, $path),
                    pilihEdisi: true,
                )
                    ->label('Import lomba (Excel)')
                    ->modalDescription('Setiap baris harus sudah ada di master Mata Lomba. Kuota kosong = kuota master. Jika ada satu baris yang salah, tidak ada data yang disimpan.'),
            ])
                ->label('Tambah lomba')
                ->icon(Heroicon::OutlinedPlus)
                ->button(),
            ActionGroup::make([
                ImportExcelAction::template(LombaImport::class, 'template-lomba.xlsx')
                    ->label('Template lomba'),
                ImportExcelAction::template(HasilImport::class, 'template-hasil.xlsx')
                    ->label('Template hasil'),
            ])
                ->label('Unduh template')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->button(),
            ImportExcelAction::make(
                'importHasil',
                'Import hasil (juara) dari Excel',
                HasilImport::class,
                fn (string $path, Edisi $edisi) => app(HasilImport::class)->import($edisi, $path),
                pilihEdisi: true,
            )
                ->label('Import hasil')
                ->modalDescription('Satu baris per juara. Untuk setiap lomba di file, juara lamanya diganti dengan isi file. Jika ada satu baris yang salah, tidak ada data yang disimpan.'),
            CreateAction::make()->label('Lomba baru'),
        ];
    }

    /** Centang banyak mata lomba dari master sekaligus untuk satu edisi. */
    protected function tambahDariMasterAction(): Action
    {
        return Action::make('tambahDariMaster')
            ->label('Tambah dari master')
            ->icon(Heroicon::OutlinedListBullet)
            ->modalHeading('Tambah lomba dari master Mata Lomba')
            ->modalDescription('Hanya mata lomba aktif yang belum ada di edisi terpilih. Kuota mengikuti master dan bisa diubah per lomba setelahnya.')
            ->modalSubmitActionLabel('Tambahkan')
            ->schema([
                Select::make('edisi_id')
                    ->label('Edisi')
                    ->options(fn () => Edisi::query()->orderByDesc('tahun')->pluck('nama', 'id')->all())
                    ->default(fn () => Edisi::pilih()?->id)
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('mata_lomba_ids', [])),
                CheckboxList::make('mata_lomba_ids')
                    ->label('Mata lomba')
                    ->options(function (Get $get) {
                        // Jangan pakai flatMap(): ia mengindeks ulang kunci numerik (id mata lomba).
                        $opsi = [];
                        foreach (LombaResource::opsiMataLomba($get('edisi_id')) as $cabor => $grup) {
                            foreach ($grup as $id => $nama) {
                                $opsi[$id] = "{$cabor} — {$nama}";
                            }
                        }

                        return $opsi;
                    })
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(2)
                    ->required()
                    ->noSearchResultsMessage('Tidak ada mata lomba yang cocok.'),
            ])
            ->action(function (array $data) {
                $edisi = Edisi::findOrFail($data['edisi_id']);
                $hasil = app(LombaService::class)->tambahBanyak($edisi, $data['mata_lomba_ids']);

                Notification::make()
                    ->title("{$hasil['dibuat']} lomba ditambahkan ke {$edisi->nama}")
                    ->body($hasil['dilewati'] ? "{$hasil['dilewati']} dilewati karena sudah ada." : null)
                    ->success()
                    ->send();
            });
    }

    /** Salin semua lomba (tanpa hasil) dari edisi lain, mis. 2025 → 2026. */
    protected function salinDariEdisiAction(): Action
    {
        return Action::make('salinDariEdisi')
            ->label('Salin dari edisi lain')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->modalHeading('Salin daftar lomba dari edisi lain')
            ->modalDescription('Hanya daftar lomba dan kuotanya yang disalin, bukan hasilnya. Mata lomba nonaktif dan yang sudah ada di edisi tujuan dilewati.')
            ->modalSubmitActionLabel('Salin')
            ->schema([
                Select::make('sumber_id')
                    ->label('Dari edisi')
                    ->options(fn () => Edisi::query()->withCount('lomba')->orderByDesc('tahun')->get()
                        ->mapWithKeys(fn (Edisi $e) => [$e->id => "{$e->nama} ({$e->lomba_count} lomba)"])->all())
                    ->required(),
                Select::make('tujuan_id')
                    ->label('Ke edisi')
                    ->options(fn () => Edisi::query()->orderByDesc('tahun')->pluck('nama', 'id')->all())
                    ->default(fn () => Edisi::pilih()?->id)
                    ->different('sumber_id')
                    ->required(),
                Toggle::make('salin_kuota')
                    ->label('Salin kuota juara dari edisi sumber')
                    ->helperText('Jika dimatikan, kuota mengikuti master.')
                    ->default(true),
            ])
            ->action(function (array $data, Action $action) {
                $sumber = Edisi::findOrFail($data['sumber_id']);
                $tujuan = Edisi::findOrFail($data['tujuan_id']);

                try {
                    $hasil = app(LombaService::class)->salinDariEdisi($sumber, $tujuan, (bool) $data['salin_kuota']);
                } catch (ValidationException $e) {
                    Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->title("{$hasil['dibuat']} lomba disalin dari {$sumber->nama} ke {$tujuan->nama}")
                    ->body($hasil['dilewati'] ? "{$hasil['dilewati']} dilewati (sudah ada atau mata lomba nonaktif)." : null)
                    ->success()
                    ->send();
            });
    }
}
