<?php

namespace App\Filament\Resources\Cabors;

use App\Filament\Resources\Cabors\Pages\CreateCabor;
use App\Filament\Resources\Cabors\Pages\EditCabor;
use App\Filament\Resources\Cabors\Pages\ListCabors;
use App\Models\Cabor;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

class CaborResource extends Resource
{
    protected static ?string $model = Cabor::class;

    protected static ?string $slug = 'cabor';

    protected static ?string $modelLabel = 'cabor';

    protected static ?string $pluralModelLabel = 'cabor';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data cabor')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('nama')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation) {
                                if ($operation === 'create' || blank($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->helperText('Dipakai di alamat halaman publik: /cabor/{slug}')
                            ->required()
                            ->maxLength(100)
                            ->alphaDash()
                            ->unique(ignoreRecord: true),
                        Select::make('ikon')
                            ->options(Cabor::IKON)
                            ->required(),
                        TextInput::make('urutan')
                            ->helperText('Nomor tampil 01, 02, … di halaman publik.')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(99)
                            ->default(fn () => (int) Cabor::max('urutan') + 1)
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['mataLomba', 'lomba']))
            ->defaultSort('urutan')
            ->reorderable('urutan')
            ->paginated(false)
            ->columns([
                TextColumn::make('urutan')
                    ->label('No')
                    ->formatStateUsing(fn (int $state) => str_pad((string) $state, 2, '0', STR_PAD_LEFT))
                    ->fontFamily('mono')
                    ->color('primary'),
                TextColumn::make('nama')
                    ->searchable(),
                TextColumn::make('ikon')
                    ->formatStateUsing(fn (?string $state) => Cabor::IKON[$state] ?? $state),
                TextColumn::make('mata_lomba_count')
                    ->label('Mata lomba')
                    ->numeric(),
                TextColumn::make('lomba_count')
                    ->label('Dipakai di lomba')
                    ->numeric(),
            ])
            ->recordActions([
                EditAction::make(),
                static::hapusAction(DeleteAction::make()),
            ]);
    }

    /** Cabor yang sudah punya mata lomba atau lomba tidak boleh dihapus. */
    public static function hapusAction(DeleteAction $action): DeleteAction
    {
        return $action->before(function (Cabor $record, DeleteAction $action) {
            if ($record->mataLomba()->exists() || $record->lomba()->exists()) {
                Notification::make()
                    ->title('Cabor tidak bisa dihapus')
                    ->body('Cabor ini masih punya mata lomba atau lomba. Hapus/pindahkan mata lombanya terlebih dahulu.')
                    ->danger()
                    ->send();

                $action->cancel();
            }
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCabors::route('/'),
            'create' => CreateCabor::route('/create'),
            'edit' => EditCabor::route('/{record}/edit'),
        ];
    }
}
