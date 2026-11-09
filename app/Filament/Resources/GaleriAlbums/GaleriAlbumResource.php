<?php

namespace App\Filament\Resources\GaleriAlbums;

use App\Filament\Resources\GaleriAlbums\Pages\CreateGaleriAlbum;
use App\Filament\Resources\GaleriAlbums\Pages\EditGaleriAlbum;
use App\Filament\Resources\GaleriAlbums\Pages\ListGaleriAlbums;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\GaleriAlbum;
use App\Models\GaleriFoto;
use App\Models\Lomba;
use App\Services\GaleriService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class GaleriAlbumResource extends Resource
{
    protected static ?string $model = GaleriAlbum::class;

    protected static ?string $slug = 'galeri';

    protected static ?string $modelLabel = 'album';

    protected static ?string $pluralModelLabel = 'album galeri';

    protected static ?string $navigationLabel = 'Galeri';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Lainnya';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'judul';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data album')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('edisi_id')
                            ->label('Edisi')
                            ->options(fn () => Edisi::query()->orderByDesc('tahun')->pluck('nama', 'id')->all())
                            ->default(fn () => Edisi::pilih()?->id)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('lomba_id', null)),
                        Select::make('cabor_id')
                            ->label('Cabor')
                            ->placeholder('Umum (tanpa cabor, mis. pembukaan)')
                            ->options(fn () => Cabor::query()->orderBy('urutan')->pluck('nama', 'id')->all())
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('lomba_id', null)),
                        Select::make('lomba_id')
                            ->label('Lomba (opsional)')
                            ->placeholder('Tidak terkait lomba tertentu')
                            ->options(fn (Get $get) => $get('edisi_id') && $get('cabor_id')
                                ? Lomba::query()
                                    ->where('edisi_id', $get('edisi_id'))
                                    ->where('cabor_id', $get('cabor_id'))
                                    ->orderBy('urutan')
                                    ->get()
                                    ->mapWithKeys(fn (Lomba $l) => [$l->id => $l->namaLengkap()])
                                    ->all()
                                : [])
                            ->disabled(fn (Get $get) => ! $get('cabor_id'))
                            ->helperText(fn (Get $get) => $get('cabor_id') ? null : 'Pilih cabor terlebih dahulu.')
                            ->searchable(),
                        DatePicker::make('tanggal'),
                        TextInput::make('judul')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation) {
                                if ($operation === 'create' || blank($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->helperText('Dipakai di alamat halaman: /galeri/{slug}')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique(ignoreRecord: true),
                    ]),

                Section::make('Tambah foto')
                    ->description('Pilih banyak foto sekaligus (JPG, PNG, atau WebP, maks. 15 MB per foto). Saat disimpan, foto dikompres ke lebar maks. 1920px dan dibuat thumbnail 600px, format WebP.')
                    ->visibleOn('edit')
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('foto_baru')
                            ->hiddenLabel()
                            ->multiple()
                            ->image()
                            ->acceptedFileTypes(GaleriService::MIME)
                            ->maxSize(GaleriService::MAKS_KB)
                            ->maxFiles(50)
                            ->disk('local')
                            ->directory('galeri-upload')
                            ->panelLayout('grid')
                            ->dehydrated(),
                    ]),

                Section::make('Foto di album ini')
                    ->description('Geser untuk mengubah urutan. Hapus foto dengan ikon tempat sampah.')
                    ->visibleOn('edit')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('sampul_foto_id')
                            ->label('Foto sampul')
                            ->placeholder('Otomatis: foto pertama')
                            ->options(fn (?GaleriAlbum $record) => $record
                                ? $record->foto()->get()->mapWithKeys(fn (GaleriFoto $f, int $i) => [
                                    $f->id => 'Foto '.($i + 1).($f->keterangan ? ' — '.Str::limit($f->keterangan, 40) : ''),
                                ])->all()
                                : []),
                        Repeater::make('foto')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('urutan')
                            ->addable(false)
                            ->reorderable()
                            ->deletable()
                            ->grid(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->schema([
                                Image::make(
                                    fn (?GaleriFoto $record) => $record?->thumbUrl() ?? '',
                                    fn (?GaleriFoto $record) => $record?->keterangan ?? 'Foto',
                                )->imageHeight('8rem'),
                                TextInput::make('keterangan')
                                    ->hiddenLabel()
                                    ->placeholder('Keterangan (opsional)')
                                    ->maxLength(255),
                            ])
                            ->deleteAction(fn ($action) => $action->requiresConfirmation()),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['sampul', 'cabor', 'edisi'])->withCount('foto'))
            ->defaultSort('tanggal', 'desc')
            ->columns([
                ImageColumn::make('sampul_thumb')
                    ->label('Sampul')
                    ->state(fn (GaleriAlbum $record) => $record->sampul?->thumbUrl())
                    ->imageHeight(48),
                TextColumn::make('judul')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cabor.nama')
                    ->label('Cabor')
                    ->default('Umum'),
                TextColumn::make('foto_count')
                    ->label('Foto')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tanggal')
                    ->date('d M Y')
                    ->sortable(),
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
                    ->options(fn () => ['umum' => 'Umum'] + Cabor::query()->orderBy('urutan')->pluck('nama', 'id')->all())
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        null, '' => $query,
                        'umum' => $query->whereNull('cabor_id'),
                        default => $query->where('cabor_id', $data['value']),
                    }),
            ])
            ->recordActions([
                EditAction::make()->label('Edit & foto'),
                DeleteAction::make()
                    ->modalDescription('Semua foto di album ini ikut terhapus (termasuk file-nya).'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGaleriAlbums::route('/'),
            'create' => CreateGaleriAlbum::route('/create'),
            'edit' => EditGaleriAlbum::route('/{record}/edit'),
        ];
    }
}
