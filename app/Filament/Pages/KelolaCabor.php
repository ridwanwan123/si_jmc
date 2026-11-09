<?php

namespace App\Filament\Pages;

use App\Enums\StatusPendaftaran;
use App\Filament\Resources\Lombas\LombaResource;
use App\Filament\Resources\Pendaftarans\PendaftaranResource;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\Lomba;
use App\Models\Pendaftaran;
use App\Services\LombaService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

use function Filament\Support\original_request;

/**
 * Halaman kerja satu cabor: tab "Lomba & Juara" dan tab "Peserta" (pendaftaran cabor itu).
 * Sidebar menampilkan satu menu per cabor (01 Keagamaan … 07 GTK).
 */
class KelolaCabor extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'kelola-cabor/{cabor}';

    protected static string|\UnitEnum|null $navigationGroup = 'Cabor';

    public Cabor $cabor;

    #[Url]
    public string $tab = 'lomba';

    public function mount(Cabor $cabor): void
    {
        $this->cabor = $cabor;
        $this->tab = in_array($this->tab, ['lomba', 'peserta'], true) ? $this->tab : 'lomba';

        // Wadah lomba disiapkan otomatis: semua mata lomba aktif cabor ini ada di edisi aktif.
        app(LombaService::class)->sinkronEdisiAktif($cabor->id);
    }

    /**
     * Satu menu per cabor. Badge: jumlah pendaftaran menunggu verifikasi (kuning) jika ada,
     * selain itu progres lomba yang sudah punya juara, mis. "9/15".
     *
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $edisi = Edisi::pilih();

        $cabor = Cabor::query()->orderBy('urutan')
            ->withCount([
                'lomba as lomba_total' => fn (Builder $q) => $q->where('edisi_id', $edisi?->id),
                'lomba as lomba_selesai' => fn (Builder $q) => $q->where('edisi_id', $edisi?->id)->whereHas('hasil'),
            ])
            ->get();

        $menunggu = Pendaftaran::query()
            ->where('pendaftaran.edisi_id', $edisi?->id)
            ->where('pendaftaran.status', StatusPendaftaran::Menunggu->value)
            ->join('lomba', 'lomba.id', '=', 'pendaftaran.lomba_id')
            ->selectRaw('lomba.cabor_id, COUNT(*) AS n')
            ->groupBy('lomba.cabor_id')
            ->pluck('n', 'cabor_id');

        return $cabor->map(function (Cabor $c, int $i) use ($menunggu) {
            $antre = (int) ($menunggu[$c->id] ?? 0);

            return NavigationItem::make($c->nomor().'  '.$c->nama)
                ->key('cabor-'.$c->slug)
                ->group(static::getNavigationGroup())
                ->sort($i)
                ->url(static::getUrl(['cabor' => $c->slug]))
                ->activeWhen(fn (): bool => original_request()->routeIs(static::getRouteName())
                    && original_request()->route('cabor') === $c->slug)
                ->badge($antre > 0 ? (string) $antre : ($c->lomba_total ? "{$c->lomba_selesai}/{$c->lomba_total}" : null),
                    color: $antre > 0 ? 'warning' : 'gray')
                ->badgeTooltip($antre > 0 ? "{$antre} pendaftaran menunggu verifikasi" : 'Lomba yang sudah punya juara');
        })->all();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->cabor->nomor().' · '.$this->cabor->nama;
    }

    public function getSubheading(): ?string
    {
        $edisi = $this->edisiTerpilih();

        return $edisi ? "{$edisi->nama} · tahap {$edisi->tahap()->label()}" : 'Belum ada edisi. Buat edisi di menu Master → Edisi.';
    }

    public function getBreadcrumbs(): array
    {
        return ['Cabor', $this->cabor->nama];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.kelola-cabor-tab')->viewData(fn () => [
                'tab' => $this->tab,
                'url' => fn (string $t) => static::getUrl(['cabor' => $this->cabor->slug, 'tab' => $t]),
                'jumlah' => $this->jumlahTab(),
            ]),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $this->tab === 'peserta' ? $this->tabelPeserta($table) : $this->tabelLomba($table);
    }

    // ------------------------------------------------------------------ Tab Lomba & Juara

    protected function tabelLomba(Table $table): Table
    {
        return $table
            ->query(fn () => Lomba::query()
                ->where('cabor_id', $this->cabor->id)
                ->with(['hasil.madrasah'])
                ->withCount('hasil'))
            ->defaultSort(fn (Builder $query) => $query->orderByRaw("FIELD(jenjang, 'RA', 'MI', 'MTs', 'MA')")->orderBy('urutan')->orderBy('nama'))
            ->recordUrl(fn (Lomba $record) => InputJuara::getUrl(['lomba' => $record]))
            ->columns([
                TextColumn::make('jenjang')
                    ->badge()
                    ->color('gray')
                    ->grow(false),
                TextColumn::make('nama')
                    ->label('Lomba')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('kategori')
                    ->grow(false),
                TextColumn::make('jumlah_anggota')
                    ->label('Peserta')
                    ->state(fn (Lomba $r) => $r->tim() ? "Tim {$r->jumlah_anggota} orang" : 'Perorangan')
                    ->icon(fn (Lomba $r) => $r->tim() ? Heroicon::OutlinedUserGroup : Heroicon::OutlinedUser)
                    ->color('gray')
                    ->grow(false),
                TextColumn::make('hasil_count')
                    ->label('Juara')
                    ->badge()
                    ->state(function (Lomba $r) {
                        $kuota = $r->kuota_juara_1 + $r->kuota_juara_2 + $r->kuota_juara_3;

                        return $r->hasil_count === 0 ? 'Belum diisi' : "{$r->hasil_count}/{$kuota} juara";
                    })
                    ->color(function (Lomba $r) {
                        $kuota = $r->kuota_juara_1 + $r->kuota_juara_2 + $r->kuota_juara_3;

                        return match (true) {
                            $r->hasil_count === 0 => 'gray',
                            $r->hasil_count < $kuota => 'warning',
                            default => 'success',
                        };
                    }),
                TextColumn::make('juara_1')
                    ->label('Juara 1')
                    ->state(fn (Lomba $r) => $r->hasil->where('peringkat', 1)->map(fn ($h) => $h->madrasah->nama)->implode(', ') ?: '—')
                    ->color(fn (Lomba $r) => $r->hasil->isEmpty() ? 'gray' : null),
            ])
            ->filters([
                SelectFilter::make('edisi_id')
                    ->label('Edisi')
                    ->options(fn () => Edisi::query()->orderByDesc('tahun')->pluck('nama', 'id')->all())
                    ->default(fn () => Edisi::pilih()?->id)
                    ->selectablePlaceholder(false),
                SelectFilter::make('jenjang')
                    ->options(fn () => collect(['RA', 'MI', 'MTs', 'MA'])->mapWithKeys(fn ($j) => [$j => $j])->all())
                    ->placeholder('Semua jenjang'),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(2)
            ->recordActions([
                Action::make('inputJuara')
                    ->label(fn (Lomba $r) => $r->hasil_count ? 'Ubah juara' : 'Input juara')
                    ->icon(Heroicon::OutlinedTrophy)
                    ->button()
                    ->url(fn (Lomba $r) => InputJuara::getUrl(['lomba' => $r])),
                ActionGroup::make([
                    Action::make('pengaturan')
                        ->label('Pengaturan lomba')
                        ->icon(Heroicon::OutlinedCog6Tooth)
                        ->url(fn (Lomba $r) => LombaResource::getUrl('edit', ['record' => $r])),
                    DeleteAction::make()
                        ->label('Hapus lomba')
                        ->modalDescription('Semua juara dan pendaftaran lomba ini ikut terhapus.'),
                ]),
            ])
            ->emptyStateHeading('Belum ada lomba '.$this->cabor->nama)
            ->emptyStateDescription('Lomba muncul otomatis dari master. Tambahkan mata lomba '.$this->cabor->nama.' di menu Master → Mata Lomba.')
            ->paginated([25, 50, 'all'])
            ->defaultPaginationPageOption(50);
    }

    // ------------------------------------------------------------------ Tab Peserta

    protected function tabelPeserta(Table $table): Table
    {
        return $table
            ->query(fn () => Pendaftaran::query()
                ->whereHas('lomba', fn (Builder $q) => $q->where('cabor_id', $this->cabor->id))
                ->with(['lomba.cabor', 'madrasah', 'peserta']))
            ->defaultSort('created_at', 'desc')
            ->columns(PendaftaranResource::kolom(tampilCabor: false))
            ->filters([
                PendaftaranResource::filterEdisi(),
                SelectFilter::make('status')
                    ->options(StatusPendaftaran::options())
                    ->placeholder('Semua status'),
                PendaftaranResource::filterJenjang()->placeholder('Semua jenjang'),
                SelectFilter::make('lomba_id')
                    ->label('Lomba')
                    ->options(fn () => Lomba::query()
                        ->where('cabor_id', $this->cabor->id)
                        ->where('edisi_id', $this->edisiTerpilih()?->id)
                        ->orderByRaw("FIELD(jenjang, 'RA', 'MI', 'MTs', 'MA')")->orderBy('nama')
                        ->get()
                        ->mapWithKeys(fn (Lomba $l) => [$l->id => $l->namaLengkap()])
                        ->all())
                    ->searchable()
                    ->placeholder('Semua lomba'),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->recordActions(PendaftaranResource::aksiBaris())
            ->toolbarActions([PendaftaranResource::verifikasiTerpilihAction()])
            ->emptyStateHeading('Belum ada pendaftaran')
            ->emptyStateDescription('Pendaftaran dari halaman /daftar untuk cabor ini akan muncul di sini.');
    }

    // ------------------------------------------------------------------

    /** Edisi dari filter tabel (default edisi aktif). */
    public function edisiTerpilih(): ?Edisi
    {
        $id = $this->tableFilters['edisi_id']['value'] ?? null;

        return ($id ? Edisi::find($id) : null) ?? Edisi::pilih();
    }

    /** @return array{lomba:int, peserta:int, menunggu:int} */
    protected function jumlahTab(): array
    {
        $edisi = $this->edisiTerpilih();

        $pendaftaran = Pendaftaran::query()
            ->where('edisi_id', $edisi?->id)
            ->whereHas('lomba', fn (Builder $q) => $q->where('cabor_id', $this->cabor->id));

        return [
            'lomba' => Lomba::query()->where('edisi_id', $edisi?->id)->where('cabor_id', $this->cabor->id)->count(),
            'peserta' => (clone $pendaftaran)->count(),
            'menunggu' => (clone $pendaftaran)->where('status', StatusPendaftaran::Menunggu->value)->count(),
        ];
    }
}
