<?php

namespace App\Filament\Resources\Users;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Cabor;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

/**
 * Kelola akun panel admin (superadmin saja).
 * Admin cabor wajib terikat satu cabor; ia juga mengurus lomba GTK yang pengelolanya cabor itu.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->bolehKelolaMaster();
    }

    protected static ?string $slug = 'pengguna';

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'pengguna';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?int $navigationSort = 90;

    protected static ?string $recordTitleAttribute = 'name';

    /** Nilai role dari form (string) atau model (enum). */
    private static function adminCabor(mixed $role): bool
    {
        return ($role instanceof Role ? $role : Role::tryFrom((string) $role)) === Role::AdminCabor;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Akun')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->label('Nama')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                        ->helperText(fn (string $operation) => $operation === 'edit' ? 'Kosongkan jika tidak diganti.' : 'Minimal 8 karakter.'),
                ]),
            Section::make('Hak akses')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('role')
                        ->label('Role')
                        ->options(Role::options())
                        ->default(Role::AdminCabor->value)
                        ->required()
                        ->live()
                        ->helperText('Superadmin: semua menu. Admin cabor: hanya cabornya. Admin berita: berita dan galeri.')
                        // Tidak bisa mengubah role diri sendiri (agar tidak terkunci dari menu ini).
                        ->disabled(fn (?User $record) => (bool) $record?->is(auth()->user())),
                    Select::make('cabor_id')
                        ->label('Cabor')
                        ->options(fn () => Cabor::query()->orderBy('urutan')->get()
                            ->mapWithKeys(fn (Cabor $c) => [$c->id => $c->nomor().' · '.$c->nama])->all())
                        ->visible(fn (Get $get) => static::adminCabor($get('role')))
                        ->required(fn (Get $get) => static::adminCabor($get('role')))
                        ->helperText('Admin cabor juga mengurus lomba GTK yang pengelolanya cabor ini.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('cabor'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable()->weight('semibold'),
                TextColumn::make('email')->searchable()->color('gray'),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (Role $state) => $state->label())
                    ->color(fn (Role $state) => $state->warna()),
                TextColumn::make('cabor.nama')->label('Cabor')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options(Role::options()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (User $record) => $record->is(auth()->user())),
            ]);
    }

    /** cabor_id hanya untuk admin cabor. */
    public static function rapikanData(array $data, ?User $record = null): array
    {
        if (! static::adminCabor($data['role'] ?? $record?->role)) {
            $data['cabor_id'] = null;
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
