<?php

namespace App\Filament\Resources\Beritas;

use App\Enums\KategoriBerita;
use App\Filament\Resources\Beritas\Pages\CreateBerita;
use App\Filament\Resources\Beritas\Pages\EditBerita;
use App\Filament\Resources\Beritas\Pages\ListBeritas;
use App\Models\Berita;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Services\BeritaService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/**
 * Berita (superadmin & admin berita): tulis, jadwalkan, terbitkan, unggulkan, sematkan di pita.
 * Status: draf (terbit_pada kosong) · terjadwal (terbit_pada di masa depan) · terbit.
 */
class BeritaResource extends Resource
{
    protected static ?string $model = Berita::class;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->bolehKelolaBerita();
    }

    protected static ?string $slug = 'berita';

    protected static ?string $modelLabel = 'berita';

    protected static ?string $pluralModelLabel = 'berita';

    protected static ?string $navigationLabel = 'Berita';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Publikasi';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'judul';

    public const STATUS = ['draf' => 'Draf', 'terjadwal' => 'Terjadwal', 'terbit' => 'Terbit'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])
                ->columnSpanFull()
                ->schema([
                    Section::make('Isi berita')
                        ->columnSpan(['lg' => 2])
                        ->schema([
                            TextInput::make('judul')
                                ->required()
                                ->maxLength(200)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (?string $state, ?string $old, Get $get, Set $set, string $operation) {
                                    // Slug mengikuti judul saat membuat berita, selama slug belum diubah manual.
                                    if ($operation === 'create' && in_array($get('slug'), [null, '', Str::slug((string) $old)], true)) {
                                        $set('slug', Str::slug((string) $state));
                                    }
                                }),
                            TextInput::make('slug')
                                ->label('Alamat (slug)')
                                ->prefix('/berita/')
                                ->required()
                                ->maxLength(200)
                                ->alphaDash()
                                ->unique(ignoreRecord: true)
                                ->helperText('Otomatis dari judul. Hindari mengubahnya setelah berita dibagikan.'),
                            Textarea::make('ringkasan')
                                ->required()
                                ->rows(3)
                                ->maxLength(400)
                                ->helperText('1–2 kalimat. Tampil di kartu berita dan sebagai paragraf pembuka.'),
                            RichEditor::make('isi')
                                ->required()
                                ->toolbarButtons([
                                    ['bold', 'italic', 'underline', 'link'],
                                    ['h2', 'h3'],
                                    ['bulletList', 'orderedList', 'blockquote'],
                                    ['table', 'attachFiles'],
                                    ['undo', 'redo'],
                                ])
                                ->fileAttachmentsDisk('public')
                                ->fileAttachmentsDirectory('berita/isi')
                                ->fileAttachmentsVisibility('public')
                                ->extraInputAttributes(['style' => 'min-height: 22rem']),
                        ]),

                    Grid::make(1)
                        ->columnSpan(['lg' => 1])
                        ->schema([
                            Section::make('Publikasi')
                                ->schema([
                                    Select::make('edisi_id')
                                        ->label('Edisi')
                                        ->options(fn () => Edisi::query()->orderByDesc('tahun')->pluck('nama', 'id')->all())
                                        ->default(fn () => Edisi::pilih()?->id)
                                        ->required(),
                                    Select::make('kategori')
                                        ->options(KategoriBerita::options())
                                        ->default(KategoriBerita::Liputan->value)
                                        ->required()
                                        ->live(),
                                    Select::make('cabor_id')
                                        ->label('Cabor')
                                        ->options(fn () => Cabor::query()->orderBy('urutan')->pluck('nama', 'id')->all())
                                        ->placeholder('Umum')
                                        ->helperText('Ikon cabor dipakai jika berita tidak punya foto.'),
                                    DateTimePicker::make('terbit_pada')
                                        ->label('Waktu terbit')
                                        ->seconds(false)
                                        ->timezone(config('jmc.timezone'))
                                        ->helperText('Kosong = draf. Waktu di masa depan = terjadwal.'),
                                    Toggle::make('unggulan')
                                        ->label('Berita unggulan')
                                        ->helperText('Tampil besar di beranda dan halaman berita. Hanya satu per edisi.'),
                                    Toggle::make('sematkan_di_pita')
                                        ->label('Sematkan di pita pengumuman')
                                        ->helperText('Pita hitam di atas halaman berita. Hanya satu per edisi.')
                                        ->visible(fn (Get $get) => $get('kategori') === KategoriBerita::Pengumuman->value || $get('kategori') === KategoriBerita::Pengumuman),
                                ]),
                            Section::make('Foto sampul')
                                ->schema([
                                    FileUpload::make('sampul')
                                        ->hiddenLabel()
                                        ->image()
                                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                        ->maxSize(10240)
                                        ->disk('public')
                                        ->imageEditor()
                                        // Dikompres ke WebP maks 1600px.
                                        ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => app(BeritaService::class)->simpanSampul($file))
                                        ->helperText('JPG/PNG/WebP, otomatis dikompres.'),
                                    TextInput::make('keterangan_sampul')
                                        ->label('Keterangan foto')
                                        ->maxLength(255),
                                ]),
                            Section::make('Lainnya')
                                ->schema([
                                    TextInput::make('penulis')
                                        ->placeholder(fn (Get $get) => 'Panitia '.(Edisi::find($get('edisi_id'))?->nama ?? 'JMC'))
                                        ->maxLength(100),
                                    TagsInput::make('tag')
                                        ->placeholder('Ketik lalu Enter')
                                        ->splitKeys([',']),
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['cabor', 'edisi']))
            ->defaultSort(fn (Builder $query) => $query->orderByRaw('terbit_pada IS NULL DESC')->orderByDesc('terbit_pada')->orderByDesc('id'))
            ->columns([
                ImageColumn::make('sampul')
                    ->label('')
                    ->disk('public')
                    ->imageHeight(48)
                    ->imageWidth(72)
                    ->extraImgAttributes(['class' => 'rounded-md object-cover']),
                TextColumn::make('judul')
                    ->searchable()
                    ->weight('semibold')
                    ->wrap()
                    ->description(fn (Berita $r) => $r->kategori->label().($r->cabor ? ' · '.$r->cabor->nama : '')),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Berita $r) => static::STATUS[$r->status()])
                    ->color(fn (Berita $r) => match ($r->status()) {
                        'terbit' => 'success',
                        'terjadwal' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('terbit_pada')
                    ->label('Terbit')
                    ->dateTime('d M Y · H:i', config('jmc.timezone'))
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('unggulan')
                    ->boolean()
                    ->trueIcon(Heroicon::Star)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->alignCenter(),
                IconColumn::make('sematkan_di_pita')
                    ->label('Pita')
                    ->boolean()
                    ->trueIcon(Heroicon::Megaphone)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('primary')
                    ->falseColor('gray')
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('edisi_id')
                    ->label('Edisi')
                    ->options(fn () => Edisi::query()->orderByDesc('tahun')->pluck('nama', 'id')->all())
                    ->default(fn () => Edisi::pilih()?->id),
                SelectFilter::make('kategori')->options(KategoriBerita::options()),
                SelectFilter::make('status')
                    ->options(static::STATUS)
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'draf' => $query->whereNull('terbit_pada'),
                        'terjadwal' => $query->where('terbit_pada', '>', now()),
                        'terbit' => $query->where('terbit_pada', '<=', now()),
                        default => $query,
                    }),
                SelectFilter::make('cabor_id')
                    ->label('Cabor')
                    ->options(fn () => Cabor::query()->orderBy('urutan')->pluck('nama', 'id')->all()),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('lihat')
                        ->label('Lihat di web')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (Berita $r) => route('berita.show', ['berita' => $r->slug] + ($r->edisi?->is_aktif ? [] : ['edisi' => $r->edisi?->tahun])), shouldOpenInNewTab: true)
                        ->visible(fn (Berita $r) => $r->status() === 'terbit'),
                    static::terbitkanAction(),
                    static::jadikanDrafAction(),
                    Action::make('unggulkan')
                        ->label('Jadikan unggulan')
                        ->icon(Heroicon::OutlinedStar)
                        ->hidden(fn (Berita $r) => $r->unggulan)
                        ->action(function (Berita $r) {
                            $r->update(['unggulan' => true]);
                            Notification::make()->title('Berita unggulan diganti')->success()->send();
                        }),
                    Action::make('sematkan')
                        ->label(fn (Berita $r) => $r->sematkan_di_pita ? 'Lepas dari pita' : 'Sematkan di pita')
                        ->icon(Heroicon::OutlinedMegaphone)
                        ->visible(fn (Berita $r) => $r->kategori === KategoriBerita::Pengumuman)
                        ->action(function (Berita $r) {
                            $r->update(['sematkan_di_pita' => ! $r->sematkan_di_pita]);
                            Notification::make()->title($r->sematkan_di_pita ? 'Disematkan di pita' : 'Dilepas dari pita')->success()->send();
                        }),
                    DeleteAction::make(),
                ])->tooltip('Aksi lain'),
            ])
            ->recordActionsColumnLabel('Aksi')
            ->emptyStateHeading('Belum ada berita')
            ->emptyStateDescription('Tulis berita pertama dengan tombol "Buat berita".');
    }

    public static function terbitkanAction(): Action
    {
        return Action::make('terbitkan')
            ->label('Terbitkan sekarang')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('success')
            ->hidden(fn (Berita $record) => $record->status() === 'terbit')
            ->requiresConfirmation()
            ->modalDescription('Berita langsung tampil di website.')
            ->action(function (Berita $record) {
                $record->update(['terbit_pada' => now()]);
                Notification::make()->title('Berita diterbitkan')->success()->send();
            });
    }

    public static function jadikanDrafAction(): Action
    {
        return Action::make('jadikanDraf')
            ->label('Jadikan draf')
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('gray')
            ->hidden(fn (Berita $record) => $record->status() === 'draf')
            ->requiresConfirmation()
            ->modalDescription('Berita disembunyikan dari website sampai diterbitkan lagi.')
            ->action(function (Berita $record) {
                $record->update(['terbit_pada' => null, 'unggulan' => false, 'sematkan_di_pita' => false]);
                Notification::make()->title('Berita dijadikan draf')->success()->send();
            });
    }

    /** Slug unik dari judul jika kosong; pita hanya untuk pengumuman. */
    public static function rapikanData(array $data, ?Berita $record = null): array
    {
        if (blank($data['slug'] ?? null)) {
            $data['slug'] = Berita::slugUnik($data['judul']);
        }
        if (($data['kategori'] ?? null) !== KategoriBerita::Pengumuman->value) {
            $data['sematkan_di_pita'] = false;
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBeritas::route('/'),
            'create' => CreateBerita::route('/create'),
            'edit' => EditBerita::route('/{record}/edit'),
        ];
    }
}
