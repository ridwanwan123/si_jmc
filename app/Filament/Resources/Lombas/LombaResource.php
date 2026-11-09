<?php

namespace App\Filament\Resources\Lombas;

use App\Enums\Jenjang;
use App\Enums\Kategori;
use App\Enums\Medali;
use App\Filament\Pages\InputJuara;
use App\Filament\Resources\Lombas\Pages\CreateLomba;
use App\Filament\Resources\Lombas\Pages\EditLomba;
use App\Filament\Resources\Lombas\Pages\ListLombas;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\Lomba;
use App\Models\Madrasah;
use App\Models\MataLomba;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use UnitEnum;

class LombaResource extends Resource
{
    protected static ?string $model = Lomba::class;

    protected static ?string $slug = 'lomba';

    protected static ?string $modelLabel = 'lomba';

    protected static ?string $pluralModelLabel = 'semua lomba';

    protected static ?string $navigationLabel = 'Semua Lomba';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data lomba')
                    ->description('Pengaturan lomba. Nama, cabor, jenjang, dan kategori mengikuti master Mata Lomba. Juara diisi lewat menu cabor → Input juara.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('edisi_id')
                            ->label('Edisi')
                            ->options(fn () => Edisi::query()->orderByDesc('tahun')->pluck('nama', 'id')->all())
                            ->default(fn () => Edisi::pilih()?->id)
                            ->required()
                            ->live()
                            ->disabledOn('edit')
                            ->afterStateUpdated(fn (Set $set) => $set('mata_lomba_id', null)),
                        Select::make('mata_lomba_id')
                            ->label('Mata lomba')
                            ->placeholder('Pilih dari master…')
                            ->options(fn (Get $get, ?Lomba $record) => static::opsiMataLomba($get('edisi_id'), $record))
                            ->getOptionLabelUsing(fn ($value) => MataLomba::with('cabor')->find($value)?->label())
                            ->searchable()
                            ->required()
                            ->live()
                            ->disabledOn('edit')
                            ->helperText(fn (string $operation) => $operation === 'create'
                                ? 'Hanya mata lomba aktif yang belum ada di edisi ini. Tambah mata lomba baru di menu Master → Mata Lomba.'
                                : null)
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($m = MataLomba::find($state)) {
                                    $set('jumlah_anggota', $m->jumlah_anggota);
                                    $set('kuota_juara_1', $m->kuota_juara_1);
                                    $set('kuota_juara_2', $m->kuota_juara_2);
                                    $set('kuota_juara_3', $m->kuota_juara_3);
                                }
                            })
                            ->rules(fn (Get $get, ?Lomba $record) => [
                                Rule::unique('lomba', 'mata_lomba_id')
                                    ->where('edisi_id', $get('edisi_id'))
                                    ->ignore($record?->id),
                            ])
                            ->validationMessages(['unique' => 'Mata lomba ini sudah ada di edisi tersebut.']),
                        Grid::make(5)
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('jumlah_anggota')
                                    ->label('Jumlah anggota')
                                    ->helperText('1 = perorangan')
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(30)
                                    ->default(1)
                                    ->required(),
                                ...collect(Medali::cases())->map(fn (Medali $m) => TextInput::make("kuota_juara_{$m->value}")
                                    ->label("Kuota juara {$m->value} ({$m->label()})")
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(20)
                                    ->default($m->kuotaDefault())
                                    ->required()
                                    ->live(onBlur: true))->all(),
                                TextInput::make('urutan')
                                    ->label('Urutan tampil')
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0),
                            ]),
                    ]),

            ]);
    }

    /**
     * Pilihan mata lomba: aktif dan belum dipakai di edisi itu (kecuali milik record ini),
     * dikelompokkan per cabor.
     *
     * @return array<string, array<int, string>>
     */
    public static function opsiMataLomba(int|string|null $edisiId, ?Lomba $record = null): array
    {
        if (! $edisiId) {
            return [];
        }

        $terpakai = Lomba::query()
            ->where('edisi_id', $edisiId)
            ->when($record, fn (Builder $q) => $q->whereKeyNot($record->id))
            ->pluck('mata_lomba_id');

        return MataLomba::query()
            ->with('cabor')
            ->where(fn (Builder $q) => $q->where('mata_lomba.is_aktif', true)
                ->when($record?->mata_lomba_id, fn (Builder $q) => $q->orWhere('mata_lomba.id', $record->mata_lomba_id)))
            ->whereNotIn('mata_lomba.id', $terpakai)
            ->terurut()
            ->get()
            ->groupBy(fn (MataLomba $m) => $m->cabor->nama)
            ->map(fn ($grup) => $grup->mapWithKeys(fn (MataLomba $m) => [$m->id => $m->namaLengkap()])->all())
            ->all();
    }

    public static function labelMadrasah(Madrasah $m): string
    {
        return "{$m->nama} ({$m->jenjang->value} · {$m->nsm})";
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['cabor', 'edisi'])->withCount('hasil'))
            ->defaultSort('urutan')
            ->columns([
                TextColumn::make('cabor.nama')
                    ->label('Cabor')
                    ->sortable(),
                TextColumn::make('nama')
                    ->label('Lomba')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jenjang')
                    ->badge(),
                TextColumn::make('kategori'),
                TextColumn::make('kuota')
                    ->label('Kuota 1/2/3')
                    ->state(fn (Lomba $r) => "{$r->kuota_juara_1}/{$r->kuota_juara_2}/{$r->kuota_juara_3}")
                    ->fontFamily('mono'),
                TextColumn::make('hasil_count')
                    ->label('Hasil')
                    ->state(function (Lomba $r) {
                        $kuota = $r->kuota_juara_1 + $r->kuota_juara_2 + $r->kuota_juara_3;

                        return $r->hasil_count === 0 ? 'Menunggu hasil' : "{$r->hasil_count}/{$kuota} juara";
                    })
                    ->badge()
                    ->color(fn (Lomba $r) => $r->hasil_count === 0 ? 'gray' : 'success'),
                TextColumn::make('edisi.tahun')
                    ->label('Edisi')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('edisi_id')
                    ->label('Edisi')
                    ->options(fn () => Edisi::query()->orderByDesc('tahun')->pluck('nama', 'id')->all())
                    ->default(fn () => Edisi::pilih()?->id),
                SelectFilter::make('cabor_id')
                    ->label('Cabor')
                    ->options(fn () => Cabor::query()->orderBy('urutan')->pluck('nama', 'id')->all()),
                SelectFilter::make('jenjang')->options(Jenjang::options()),
                SelectFilter::make('kategori')->options(Kategori::options()),
                TernaryFilter::make('ada_hasil')
                    ->label('Status hasil')
                    ->trueLabel('Sudah ada hasil')
                    ->falseLabel('Menunggu hasil')
                    ->queries(
                        true: fn (Builder $query) => $query->has('hasil'),
                        false: fn (Builder $query) => $query->doesntHave('hasil'),
                    ),
            ])
            ->recordActions([
                Action::make('inputJuara')
                    ->label('Input juara')
                    ->icon(Heroicon::OutlinedTrophy)
                    ->url(fn (Lomba $record) => InputJuara::getUrl(['lomba' => $record])),
                EditAction::make()->label('Pengaturan'),
                DeleteAction::make()
                    ->modalDescription('Semua hasil (juara) lomba ini ikut terhapus.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLombas::route('/'),
            'create' => CreateLomba::route('/create'),
            'edit' => EditLomba::route('/{record}/edit'),
        ];
    }
}
