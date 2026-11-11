<?php

namespace App\Filament\Resources\MataLombas;

use App\Enums\Jenjang;
use App\Enums\Kategori;
use App\Enums\Medali;
use App\Filament\Resources\MataLombas\Pages\CreateMataLomba;
use App\Filament\Resources\MataLombas\Pages\EditMataLomba;
use App\Filament\Resources\MataLombas\Pages\ListMataLombas;
use App\Models\Cabor;
use App\Models\MataLomba;
use App\Services\LombaService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class MataLombaResource extends Resource
{
    protected static ?string $model = MataLomba::class;

    /** Master data hanya untuk superadmin. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->bolehKelolaMaster();
    }

    protected static ?string $slug = 'mata-lomba';

    protected static ?string $modelLabel = 'mata lomba';

    protected static ?string $pluralModelLabel = 'mata lomba';

    protected static ?string $navigationLabel = 'Mata Lomba';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Mata lomba')
                    ->description('Katalog lomba tetap. Setiap edisi memilih lomba dari daftar ini.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('cabor_id')
                            ->label('Cabor')
                            ->options(fn () => Cabor::query()->orderBy('urutan')->pluck('nama', 'id')->all())
                            ->required()
                            ->live(),
                        // Lomba GTK diurus admin cabor lain, mis. Badminton GTK → admin Olahraga.
                        // Medali tetap dihitung ke GTK. Kosong = ditebak dari nama lomba.
                        Select::make('pengelola_cabor_id')
                            ->label('Dikelola oleh admin cabor')
                            ->options(fn () => Cabor::query()->orderBy('urutan')->pluck('nama', 'id')->all())
                            ->placeholder('Otomatis dari nama lomba')
                            ->helperText('Admin cabor ini yang memverifikasi peserta dan menginput juara. Medali tetap masuk cabor GTK.')
                            ->visible(fn (Get $get) => Cabor::query()->whereKey($get('cabor_id'))->value('slug') === 'gtk'),
                        TextInput::make('nama')
                            ->label('Nama lomba')
                            ->placeholder('Tenis Meja')
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                                    ->where('cabor_id', $get('cabor_id'))
                                    ->where('jenjang', $get('jenjang'))
                                    ->where('kategori', $get('kategori')),
                            )
                            ->validationMessages(['unique' => 'Mata lomba dengan cabor, nama, jenjang, dan kategori ini sudah ada.']),
                        Select::make('jenjang')
                            ->options(Jenjang::options())
                            ->required()
                            ->live(),
                        Select::make('kategori')
                            ->options(Kategori::options())
                            ->required()
                            ->live(),
                        TextInput::make('jumlah_anggota')
                            ->label('Maksimal anggota')
                            ->helperText('1 = perorangan. Lebih dari 1 = tim, mis. Cerdas Cermat 3, Basket 12. Madrasah boleh mendaftarkan kurang dari angka ini (minimal kapten).')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(30)
                            ->default(1)
                            ->required(),
                        Grid::make(4)
                            ->columnSpanFull()
                            ->schema([
                                ...collect(Medali::cases())->map(fn (Medali $m) => TextInput::make("kuota_juara_{$m->value}")
                                    ->label("Kuota juara {$m->value} ({$m->label()})")
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(20)
                                    ->default($m->kuotaDefault())
                                    ->required())->all(),
                                TextInput::make('urutan')
                                    ->label('Urutan tampil')
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0),
                            ]),
                        Toggle::make('is_aktif')
                            ->label('Aktif')
                            ->default(true)
                            ->helperText('Nonaktifkan jika lomba ini tidak diadakan lagi. Lomba di edisi lama tetap tersimpan.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['cabor', 'pengelola'])->withCount('lomba')->terurut())
            ->columns([
                TextColumn::make('cabor.nama')
                    ->label('Cabor')
                    ->description(fn (MataLomba $r) => $r->pengelola_cabor_id !== $r->cabor_id ? 'Dikelola '.$r->pengelola?->nama : null),
                TextColumn::make('nama')
                    ->label('Mata lomba')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('mata_lomba.nama', 'like', "%{$search}%")),
                TextColumn::make('jenjang')
                    ->badge(),
                TextColumn::make('kategori'),
                TextColumn::make('kuota')
                    ->label('Kuota 1/2/3')
                    ->state(fn (MataLomba $r) => "{$r->kuota_juara_1}/{$r->kuota_juara_2}/{$r->kuota_juara_3}")
                    ->fontFamily('mono'),
                TextColumn::make('lomba_count')
                    ->label('Dipakai')
                    ->suffix(' edisi')
                    ->numeric(),
                ToggleColumn::make('is_aktif')
                    ->label('Aktif')
                    ->afterStateUpdated(fn (MataLomba $record) => app(LombaService::class)->sinkronEdisiAktif($record->cabor_id)),
            ])
            ->filters([
                SelectFilter::make('cabor_id')
                    ->label('Cabor')
                    ->options(fn () => Cabor::query()->orderBy('urutan')->pluck('nama', 'id')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->where('mata_lomba.cabor_id', $data['value']) : $query),
                SelectFilter::make('jenjang')
                    ->options(Jenjang::options())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->where('mata_lomba.jenjang', $data['value']) : $query),
                SelectFilter::make('kategori')
                    ->options(Kategori::options())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->where('mata_lomba.kategori', $data['value']) : $query),
                TernaryFilter::make('is_aktif')
                    ->label('Status')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif')
                    ->queries(
                        true: fn (Builder $query) => $query->where('mata_lomba.is_aktif', true),
                        false: fn (Builder $query) => $query->where('mata_lomba.is_aktif', false),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                static::hapusAction(DeleteAction::make()),
            ]);
    }

    /** Mata lomba yang sudah dipakai di edisi mana pun tidak boleh dihapus (cukup dinonaktifkan). */
    public static function hapusAction(DeleteAction $action): DeleteAction
    {
        return $action->before(function (MataLomba $record, DeleteAction $action) {
            if ($record->lomba()->exists()) {
                Notification::make()
                    ->title('Mata lomba tidak bisa dihapus')
                    ->body('Mata lomba ini sudah dipakai di edisi. Nonaktifkan saja agar tidak muncul di edisi berikutnya.')
                    ->danger()
                    ->send();

                $action->cancel();
            }
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMataLombas::route('/'),
            'create' => CreateMataLomba::route('/create'),
            'edit' => EditMataLomba::route('/{record}/edit'),
        ];
    }
}
