<?php

namespace App\Filament\Resources\Edisis;

use App\Filament\Resources\Edisis\Pages\CreateEdisi;
use App\Filament\Resources\Edisis\Pages\EditEdisi;
use App\Filament\Resources\Edisis\Pages\ListEdisis;
use App\Models\Edisi;
use App\Services\KlasemenService;
use App\Services\LombaService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class EdisiResource extends Resource
{
    protected static ?string $model = Edisi::class;

    /** Master data hanya untuk superadmin. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->bolehKelolaMaster();
    }

    protected static ?string $slug = 'edisi';

    protected static ?string $modelLabel = 'edisi';

    protected static ?string $pluralModelLabel = 'edisi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data edisi')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('tahun')
                            ->required()
                            ->integer()
                            ->minValue(2000)
                            ->maxValue(2100)
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                if (blank($get('nama')) && filled($state)) {
                                    $set('nama', "JMC {$state}");
                                }
                            }),
                        TextInput::make('nama')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('JMC 2026'),
                        DatePicker::make('tanggal_mulai')
                            ->label('Tanggal mulai'),
                        DatePicker::make('tanggal_selesai')
                            ->label('Tanggal selesai')
                            ->afterOrEqual('tanggal_mulai'),
                        TextInput::make('lokasi')
                            ->maxLength(255),
                        DatePicker::make('pendaftaran_buka')
                            ->label('Pendaftaran dibuka')
                            ->helperText('Kosong = langsung dibuka.'),
                        DatePicker::make('pendaftaran_tutup')
                            ->label('Pendaftaran ditutup')
                            ->helperText('Hari terakhir pendaftaran. Setelah itu tahap berganti ke Tanding.')
                            ->afterOrEqual('pendaftaran_buka'),
                        Toggle::make('is_aktif')
                            ->label('Edisi aktif')
                            ->helperText('Hanya satu edisi yang aktif. Mengaktifkan edisi ini akan menonaktifkan edisi lain.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('lomba'))
            ->defaultSort('tahun', 'desc')
            ->columns([
                TextColumn::make('tahun')
                    ->sortable(),
                TextColumn::make('nama')
                    ->searchable(),
                TextColumn::make('tanggal_mulai')
                    ->label('Tanggal')
                    ->formatStateUsing(fn (Edisi $record) => collect([
                        $record->tanggal_mulai?->translatedFormat('d M'),
                        $record->tanggal_selesai?->translatedFormat('d M Y'),
                    ])->filter()->implode(' – ')),
                TextColumn::make('pendaftaran_tutup')
                    ->label('Pendaftaran')
                    ->formatStateUsing(fn (Edisi $record) => collect([
                        $record->pendaftaran_buka?->translatedFormat('d M'),
                        $record->pendaftaran_tutup?->translatedFormat('d M Y'),
                    ])->filter()->implode(' – ')),
                TextColumn::make('tahap')
                    ->label('Tahap')
                    ->badge()
                    ->state(fn (Edisi $record) => $record->tahap()->label()),
                TextColumn::make('lokasi'),
                TextColumn::make('lomba_count')
                    ->label('Lomba')
                    ->numeric(),
                TextColumn::make('madrasah_peserta')
                    ->label('Madrasah peserta')
                    ->tooltip('Dihitung dari pendaftaran yang diverifikasi dan madrasah peraih medali.')
                    ->state(fn (Edisi $record) => app(KlasemenService::class)->jumlahMadrasahPeserta($record))
                    ->numeric(),
                IconColumn::make('is_aktif')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->recordActions([
                Action::make('jadikanAktif')
                    ->label('Jadikan aktif')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->hidden(fn (Edisi $record) => $record->is_aktif)
                    ->requiresConfirmation()
                    ->modalDescription('Halaman publik akan menampilkan edisi ini secara default.')
                    ->action(function (Edisi $record) {
                        $record->update(['is_aktif' => true]);
                        app(LombaService::class)->sinkronMaster($record);
                        Notification::make()->title("{$record->nama} sekarang edisi aktif")->success()->send();
                    }),
                EditAction::make(),
                static::hapusAction(DeleteAction::make()),
            ]);
    }

    /** Edisi aktif tidak boleh dihapus; edisi lain dihapus bersama lomba dan hasilnya. */
    public static function hapusAction(DeleteAction $action): DeleteAction
    {
        return $action
            ->hidden(fn (Edisi $record) => $record->is_aktif)
            ->modalDescription('Semua lomba, hasil, dan album galeri di edisi ini ikut terhapus. Tindakan ini tidak bisa dibatalkan.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEdisis::route('/'),
            'create' => CreateEdisi::route('/create'),
            'edit' => EditEdisi::route('/{record}/edit'),
        ];
    }
}
