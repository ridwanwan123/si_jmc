<?php

namespace App\Filament\Resources\Madrasahs;

use App\Enums\Jenjang;
use App\Enums\StatusMadrasah;
use App\Enums\Wilayah;
use App\Filament\Resources\Madrasahs\Pages\CreateMadrasah;
use App\Filament\Resources\Madrasahs\Pages\EditMadrasah;
use App\Filament\Resources\Madrasahs\Pages\ListMadrasahs;
use App\Models\Madrasah;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MadrasahResource extends Resource
{
    protected static ?string $model = Madrasah::class;

    protected static ?string $slug = 'madrasah';

    protected static ?string $modelLabel = 'madrasah';

    protected static ?string $pluralModelLabel = 'madrasah';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data madrasah')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('nsm')
                            ->label('NSM')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true),
                        TextInput::make('nama')
                            ->required()
                            ->maxLength(255),
                        Select::make('jenjang')
                            ->options(Jenjang::options())
                            ->required(),
                        Select::make('status')
                            ->options(StatusMadrasah::options())
                            ->required(),
                        Select::make('wilayah')
                            ->options(Wilayah::options())
                            ->required(),
                        FileUpload::make('logo')
                            ->image()
                            ->disk('public')
                            ->directory('madrasah-logo')
                            ->maxSize(1024),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('hasil'))
            ->defaultSort('nama')
            ->columns([
                TextColumn::make('nsm')
                    ->label('NSM')
                    ->searchable()
                    ->fontFamily('mono'),
                TextColumn::make('nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jenjang')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status'),
                TextColumn::make('wilayah')
                    ->toggleable(),
                TextColumn::make('hasil_count')
                    ->label('Medali')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('jenjang')->options(Jenjang::options()),
                SelectFilter::make('status')->options(StatusMadrasah::options()),
                SelectFilter::make('wilayah')->options(Wilayah::options()),
            ])
            ->recordActions([
                EditAction::make(),
                static::hapusAction(DeleteAction::make()),
            ]);
    }

    /** Madrasah yang sudah punya hasil tidak boleh dihapus. */
    public static function hapusAction(DeleteAction $action): DeleteAction
    {
        return $action->before(function (Madrasah $record, DeleteAction $action) {
            if ($record->hasil()->exists()) {
                Notification::make()
                    ->title('Madrasah tidak bisa dihapus')
                    ->body('Madrasah ini sudah tercatat sebagai juara. Hapus hasilnya di halaman lomba terlebih dahulu.')
                    ->danger()
                    ->send();

                $action->cancel();
            }
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMadrasahs::route('/'),
            'create' => CreateMadrasah::route('/create'),
            'edit' => EditMadrasah::route('/{record}/edit'),
        ];
    }
}
