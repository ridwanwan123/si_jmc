<?php

namespace App\Filament\Actions;

use App\Exports\TemplateExport;
use App\Imports\ImportExcel;
use App\Imports\LaporanImport;
use App\Models\Edisi;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Tombol "Import Excel" dan "Unduh template" untuk halaman daftar Filament.
 * Logika import ada di App\Imports\*, bukan di sini.
 */
class ImportExcelAction
{
    /**
     * @param  class-string<ImportExcel>  $importClass
     * @param  Closure(string $path, ?Edisi $edisi): LaporanImport  $jalankan
     */
    public static function make(string $name, string $judul, string $importClass, Closure $jalankan, bool $pilihEdisi = false): Action
    {
        $schema = [];

        if ($pilihEdisi) {
            $schema[] = Select::make('edisi_id')
                ->label('Edisi')
                ->options(fn () => Edisi::query()->orderByDesc('tahun')->pluck('nama', 'id'))
                ->default(fn () => Edisi::pilih()?->id)
                ->required();
        }

        $schema[] = FileUpload::make('file')
            ->label('File Excel (.xlsx)')
            ->helperText('Kolom: '.implode(', ', $importClass::header()).'. Baris pertama = header.')
            // File tidak dipindah ke storage: dibaca langsung dari file sementara
            // Livewire. Dengan begitu file tetap ada jika import gagal dan admin
            // menekan "Import" lagi (sebelumnya file sudah terhapus → FileNotFound).
            ->storeFiles(false)
            ->acceptedFileTypes([
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
                'text/csv',
            ])
            ->maxSize(5120)
            ->required();

        return Action::make($name)
            ->label('Import Excel')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->modalHeading($judul)
            ->modalDescription('Jika ada satu baris yang salah, tidak ada data yang disimpan. Perbaiki file lalu import ulang.')
            ->modalSubmitActionLabel('Import')
            ->schema($schema)
            ->action(function (array $data, Action $action) use ($jalankan) {
                /** @var TemporaryUploadedFile $file */
                $file = $data['file'];

                if (! $file instanceof TemporaryUploadedFile || ! $file->exists()) {
                    Notification::make()
                        ->title('File tidak ditemukan')
                        ->body('Silakan pilih ulang file Excel lalu import lagi.')
                        ->danger()
                        ->send();
                    $action->halt();
                }

                $edisi = isset($data['edisi_id']) ? Edisi::find($data['edisi_id']) : null;
                $laporan = $jalankan($file->getRealPath(), $edisi);

                static::kirimNotifikasi($laporan);

                if (! $laporan->berhasil()) {
                    // File sementara dibiarkan agar bisa langsung di-import ulang.
                    $action->halt();
                }

                $file->delete();
            });
    }

    /** @param class-string<ImportExcel> $importClass */
    public static function template(string $importClass, string $namaFile): Action
    {
        return Action::make('template_'.class_basename($importClass))
            ->label('Unduh template')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->action(fn () => Excel::download(
                new TemplateExport($importClass::header(), [$importClass::contoh()]),
                $namaFile,
            ));
    }

    public static function kirimNotifikasi(LaporanImport $laporan): void
    {
        if ($laporan->berhasil()) {
            Notification::make()
                ->title('Import berhasil')
                ->body($laporan->ringkasan())
                ->success()
                ->send();

            return;
        }

        $tampil = array_slice($laporan->errors, 0, 15);
        $sisa = count($laporan->errors) - count($tampil);

        $body = '<ul style="list-style:disc;padding-left:1rem">'
            .collect($tampil)->map(fn ($e) => '<li>'.e($e).'</li>')->implode('')
            .'</ul>'
            .($sisa > 0 ? "<p>… dan {$sisa} kesalahan lain.</p>" : '');

        Notification::make()
            ->title('Import dibatalkan: '.count($laporan->errors).' kesalahan')
            ->body(new HtmlString($body))
            ->danger()
            ->persistent()
            ->send();
    }
}
