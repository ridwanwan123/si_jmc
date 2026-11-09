<?php

namespace App\Filament\Resources\Pendaftarans;

use App\Enums\Jenjang;
use App\Enums\StatusPendaftaran;
use App\Filament\Resources\Pendaftarans\Pages\ListPendaftarans;
use App\Filament\Resources\Pendaftarans\Pages\ViewPendaftaran;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\Pendaftaran;
use App\Services\PendaftaranService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/**
 * Pendaftaran peserta dari form /daftar: lihat, verifikasi, tolak, unduh surat tugas.
 * Pendaftaran dibuat oleh peserta (tidak ada tombol "buat" di admin).
 */
class PendaftaranResource extends Resource
{
    protected static ?string $model = Pendaftaran::class;

    protected static ?string $slug = 'pendaftaran';

    protected static ?string $modelLabel = 'pendaftaran';

    protected static ?string $pluralModelLabel = 'pendaftaran';

    protected static ?string $navigationLabel = 'Pendaftaran';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Data Lomba';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'nomor';

    /** Pendaftaran dikelola per cabor (menu Cabor), bukan dari menu sendiri. */
    protected static bool $shouldRegisterNavigation = false;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Pendaftaran::query()->where('status', StatusPendaftaran::Menunggu->value)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Menunggu verifikasi';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pendaftaran')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('nomor')->label('Nomor registrasi')->fontFamily('mono')->weight('bold')->copyable(),
                    TextEntry::make('status')->badge()
                        ->formatStateUsing(fn (StatusPendaftaran $state) => $state->label())
                        ->color(fn (StatusPendaftaran $state) => $state->warna()),
                    TextEntry::make('created_at')->label('Tanggal daftar')->dateTime('d M Y · H:i', config('jmc.timezone')),
                    TextEntry::make('edisi.nama')->label('Edisi'),
                    TextEntry::make('lomba.cabor.nama')->label('Cabor'),
                    TextEntry::make('lomba')->label('Mata lomba')
                        ->formatStateUsing(fn (Pendaftaran $record) => $record->lomba->namaLengkap()
                            .' · '.($record->lomba->tim() ? "Tim {$record->lomba->jumlah_anggota} orang" : 'Perorangan')),
                    TextEntry::make('madrasah.nama')->label('Madrasah')
                        ->formatStateUsing(fn (Pendaftaran $record) => "{$record->madrasah->nama} ({$record->madrasah->jenjang->value} · NSM {$record->madrasah->nsm})")
                        ->columnSpan(2),
                    TextEntry::make('catatan')->label('Catatan panitia')->placeholder('—'),
                ]),
            Section::make(fn (Pendaftaran $record) => $record->lomba->tim() ? 'Anggota tim' : 'Peserta')
                ->description(fn (Pendaftaran $record) => $record->peserta->count().' orang')
                ->columnSpanFull()
                ->schema([
                    // Satu tabel ringkas: tetap rapi walau timnya 10 orang.
                    RepeatableEntry::make('peserta')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('No')->width('64px'),
                            TableColumn::make('Nama lengkap'),
                            TableColumn::make('NISN / NUPTK')->width('220px'),
                        ])
                        ->schema([
                            TextEntry::make('urutan')->formatStateUsing(fn ($state) => nomor((int) $state))->fontFamily('mono')->color('gray'),
                            TextEntry::make('nama')->weight('medium'),
                            TextEntry::make('nisn')->fontFamily('mono')->copyable()->copyMessage('Disalin'),
                        ]),
                ]),
            Section::make('Guru pendamping')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('guru_nama')->label('Nama'),
                    TextEntry::make('guru_wa')->label('WhatsApp')
                        ->url(fn (Pendaftaran $record) => 'https://wa.me/62'.ltrim($record->guru_wa, '0'), shouldOpenInNewTab: true)
                        ->color('primary'),
                    TextEntry::make('surat_tugas_path')->label('Surat tugas')
                        ->formatStateUsing(fn ($state) => $state ? 'Terlampir (PDF)' : null)
                        ->placeholder('Tidak ada'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['lomba.cabor', 'madrasah', 'peserta']))
            ->defaultSort('created_at', 'desc')
            ->columns(static::kolom())
            ->filters([
                static::filterEdisi(),
                SelectFilter::make('status')->options(StatusPendaftaran::options()),
                SelectFilter::make('cabor')
                    ->label('Cabor')
                    ->options(fn () => Cabor::query()->orderBy('urutan')->pluck('nama', 'id')->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null,
                        fn (Builder $q, $id) => $q->whereHas('lomba', fn (Builder $l) => $l->where('cabor_id', $id)))),
                static::filterJenjang(),
            ])
            ->recordActions(static::aksiBaris())
            ->toolbarActions([static::verifikasiTerpilihAction()]);
    }

    /**
     * Kolom tabel pendaftaran (dipakai juga di tab Peserta halaman cabor).
     *
     * @return array<TextColumn>
     */
    public static function kolom(bool $tampilCabor = true): array
    {
        return [
            TextColumn::make('nomor')
                ->label('Nomor')
                ->fontFamily('mono')
                ->weight('bold')
                ->searchable()
                ->sortable(),
            TextColumn::make('madrasah.nama')
                ->label('Madrasah')
                ->description(fn (Pendaftaran $record) => $record->madrasah->jenjang->value.' · '.$record->madrasah->wilayah?->value)
                ->searchable()
                ->sortable(),
            TextColumn::make('lomba.nama')
                ->label('Lomba')
                ->formatStateUsing(fn (Pendaftaran $record) => $record->lomba->namaLengkap())
                ->description(fn (Pendaftaran $record) => $tampilCabor ? $record->lomba->cabor->nama : null),
            TextColumn::make('peserta.nama')
                ->label('Peserta')
                ->listWithLineBreaks()
                ->limitList(3)
                ->expandableLimitedList()
                ->searchable(),
            TextColumn::make('guru_nama')
                ->label('Pendamping')
                ->description(fn (Pendaftaran $record) => $record->guru_wa)
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('status')
                ->badge()
                ->formatStateUsing(fn (StatusPendaftaran $state) => $state->label())
                ->color(fn (StatusPendaftaran $state) => $state->warna())
                ->sortable(),
            TextColumn::make('created_at')
                ->label('Tanggal')
                ->dateTime('d M Y · H:i', config('jmc.timezone'))
                ->sortable()
                ->toggleable(),
        ];
    }

    public static function filterEdisi(): SelectFilter
    {
        return SelectFilter::make('edisi_id')
            ->label('Edisi')
            ->options(fn () => Edisi::query()->orderByDesc('tahun')->pluck('nama', 'id')->all())
            ->default(fn () => Edisi::pilih()?->id)
            ->selectablePlaceholder(false);
    }

    public static function filterJenjang(): SelectFilter
    {
        return SelectFilter::make('jenjang')
            ->options(Jenjang::options())
            ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null,
                fn (Builder $q, $j) => $q->whereHas('lomba', fn (Builder $l) => $l->where('jenjang', $j))));
    }

    /** @return array<Action> */
    public static function aksiBaris(): array
    {
        return [
            ViewAction::make()->url(fn (Pendaftaran $record) => static::getUrl('view', ['record' => $record])),
            static::verifikasiAction(),
            static::tolakAction(),
        ];
    }

    public static function verifikasiTerpilihAction(): BulkAction
    {
        return BulkAction::make('verifikasiTerpilih')
            ->label('Verifikasi terpilih')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->action(function (Collection $records) {
                $n = 0;
                foreach ($records as $r) {
                    if ($r->status !== StatusPendaftaran::Diverifikasi) {
                        app(PendaftaranService::class)->verifikasi($r);
                        $n++;
                    }
                }
                Notification::make()->title("{$n} pendaftaran diverifikasi")->success()->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function verifikasiAction(): Action
    {
        return Action::make('verifikasi')
            ->label('Verifikasi')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->hidden(fn (Pendaftaran $record) => $record->status === StatusPendaftaran::Diverifikasi)
            ->requiresConfirmation()
            ->modalDescription(fn (Pendaftaran $record) => "Data peserta dan surat tugas {$record->madrasah->nama} sudah dicek dan sesuai?")
            ->action(function (Pendaftaran $record) {
                app(PendaftaranService::class)->verifikasi($record);
                Notification::make()->title("{$record->nomor} diverifikasi")->success()->send();
            });
    }

    public static function tolakAction(): Action
    {
        return Action::make('tolak')
            ->label('Tolak')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->hidden(fn (Pendaftaran $record) => $record->status === StatusPendaftaran::Ditolak)
            ->schema([
                Textarea::make('catatan')
                    ->label('Alasan penolakan')
                    ->helperText('Disampaikan ke guru pendamping, mis. "Surat tugas tidak ditandatangani kepala madrasah".')
                    ->required()
                    ->maxLength(500),
            ])
            ->action(function (Pendaftaran $record, array $data) {
                app(PendaftaranService::class)->tolak($record, $data['catatan']);
                Notification::make()->title("{$record->nomor} ditolak")->danger()->send();
            });
    }

    public static function unduhSuratAction(): Action
    {
        return Action::make('unduhSurat')
            ->label('Unduh surat tugas')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(fn (Pendaftaran $record) => filled($record->surat_tugas_path)
                && Storage::disk(Pendaftaran::DISK)->exists($record->surat_tugas_path))
            ->action(fn (Pendaftaran $record) => Storage::disk(Pendaftaran::DISK)
                ->download($record->surat_tugas_path, "surat-tugas-{$record->nomor}.pdf"));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPendaftarans::route('/'),
            'view' => ViewPendaftaran::route('/{record}'),
        ];
    }
}
