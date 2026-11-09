<?php

namespace App\Filament\Pages;

use App\Enums\Medali;
use App\Filament\Resources\Lombas\LombaResource;
use App\Models\Lomba;
use App\Models\Madrasah;
use App\Models\Pendaftaran;
use App\Services\HasilService;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Input juara satu lomba, khusus untuk admin cabor: pilih madrasah juara dari peserta
 * terverifikasi (nama siswa terisi otomatis), atau isi manual. Disimpan lewat HasilService.
 *
 * @property-read Schema $form
 */
class InputJuara extends Page
{
    protected static ?string $slug = 'input-juara/{lomba}';

    protected static bool $shouldRegisterNavigation = false;

    public Lomba $lomba;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(Lomba $lomba): void
    {
        $this->lomba = $lomba->load(['cabor', 'edisi']);

        $hasil = $lomba->hasil()->get();
        $isi = [];
        foreach (Medali::cases() as $m) {
            $isi["juara_{$m->value}"] = $hasil->where('peringkat', $m->value)
                ->map(fn ($h) => [
                    'manual' => $h->pendaftaran_id === null,
                    'pendaftaran_id' => $h->pendaftaran_id,
                    'madrasah_id' => $h->madrasah_id,
                    'nama_peserta' => $h->nama_peserta,
                ])->values()->all();
        }

        $this->form->fill($isi);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Juara '.$this->lomba->nama;
    }

    public function getSubheading(): ?string
    {
        $l = $this->lomba;
        $peserta = $l->pendaftaran()->diverifikasi()->count();

        return "{$l->cabor->nama} · {$l->jenjang->value} · {$l->kategori->value} · "
            .($l->tim() ? "Tim {$l->jumlah_anggota} orang" : 'Perorangan')
            ." · {$l->edisi->nama} · {$peserta} peserta terverifikasi";
    }

    public function getBreadcrumbs(): array
    {
        return [
            KelolaCabor::getUrl(['cabor' => $this->lomba->cabor->slug]) => $this->lomba->cabor->nama,
            'Input juara',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pengaturan')
                ->label('Pengaturan lomba')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->url(LombaResource::getUrl('edit', ['record' => $this->lomba])),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components(collect(Medali::cases())->map(fn (Medali $m) => $this->bagian($m))->all());
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('simpan')
                ->footer([
                    Actions::make([
                        Action::make('simpan')->label('Simpan juara')->submit('simpan')->keyBindings(['mod+s']),
                        Action::make('batal')->label('Kembali')->color('gray')
                            ->url(KelolaCabor::getUrl(['cabor' => $this->lomba->cabor->slug])),
                    ]),
                ]),
        ]);
    }

    /** Satu bagian per peringkat: Juara 1 (Emas), Juara 2 (Perak), Juara 3 (Perunggu). */
    protected function bagian(Medali $m): Section
    {
        $p = $m->value;
        $kuota = $this->lomba->kuota($m);
        $gtk = $this->lomba->cabor->slug === 'gtk';

        return Section::make("Juara {$p} · {$m->label()}")
            ->icon(Heroicon::OutlinedTrophy)
            ->iconColor(match ($m) {
                Medali::Emas => 'warning',
                Medali::Perak => 'gray',
                Medali::Perunggu => 'danger',
            })
            ->description(fn (Get $get) => count($get("juara_{$p}") ?? []).' dari '.$kuota.' juara')
            ->schema([
                Repeater::make("juara_{$p}")
                    ->hiddenLabel()
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('pendaftaran_id')
                                ->label('Madrasah')
                                ->placeholder('Pilih madrasah juara…')
                                ->options(fn () => $this->opsiPendaftaran())
                                ->searchable()
                                ->live()
                                ->visible(fn (Get $get) => ! $get('manual'))
                                ->required(fn (Get $get) => ! $get('manual'))
                                ->disableOptionWhen(fn ($value, $state) => (string) $value !== (string) $state
                                    && in_array((string) $value, $this->pendaftaranTerpilih(), true))
                                ->helperText('Hanya peserta yang sudah diverifikasi di lomba ini.')
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $daftar = $state ? Pendaftaran::with('peserta')->find($state) : null;
                                    $set('nama_peserta', $daftar?->namaPeserta());
                                    $set('madrasah_id', $daftar?->madrasah_id);
                                }),
                            Select::make('madrasah_id')
                                ->label('Madrasah')
                                ->placeholder('Cari nama madrasah atau NSM…')
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search) => Madrasah::query()
                                    ->where('jenjang', $this->lomba->jenjang->value)
                                    ->where(fn (Builder $q) => $q->where('nama', 'like', "%{$search}%")->orWhere('nsm', 'like', "%{$search}%"))
                                    ->orderBy('nama')->limit(50)
                                    ->pluck('nama', 'id')->all())
                                ->getOptionLabelUsing(fn ($value) => Madrasah::find($value)?->nama)
                                ->visible(fn (Get $get) => (bool) $get('manual'))
                                ->required(fn (Get $get) => (bool) $get('manual')),
                            Textarea::make('nama_peserta')
                                ->label($gtk ? 'Nama guru / tendik' : ($this->lomba->tim() ? 'Nama siswa (anggota tim)' : 'Nama siswa'))
                                ->placeholder('Terisi otomatis setelah madrasah dipilih')
                                ->helperText('Satu nama per baris. Ubah jika ada pergantian peserta.')
                                ->rows(max(2, min(10, $this->lomba->jumlah_anggota)))
                                ->autosize()
                                ->maxLength(2000),
                        ]),
                        Toggle::make('manual')
                            ->label('Juara tidak ada di daftar pendaftaran (isi manual)')
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('pendaftaran_id', null);
                                $set('madrasah_id', null);
                                $set('nama_peserta', null);
                            }),
                    ])
                    ->defaultItems(0)
                    ->maxItems($kuota)
                    ->addActionLabel("Tambah juara {$p}")
                    ->reorderable()
                    ->collapsible(false)
                    ->itemLabel(fn (array $state) => $this->labelItem($state)),
            ]);
    }

    public function simpan(): void
    {
        $data = $this->form->getState();

        $baris = [];
        foreach (Medali::cases() as $m) {
            foreach (array_values($data["juara_{$m->value}"] ?? []) as $item) {
                $manual = (bool) ($item['manual'] ?? false);
                $baris[] = [
                    'peringkat' => $m->value,
                    'pendaftaran_id' => $manual ? null : ($item['pendaftaran_id'] ?? null),
                    'madrasah_id' => $item['madrasah_id'] ?? null,
                    'nama_peserta' => $item['nama_peserta'] ?? null,
                ];
            }
        }

        try {
            DB::transaction(fn () => app(HasilService::class)->simpan($this->lomba, $baris));
        } catch (ValidationException $e) {
            $pesan = [];
            foreach ($e->errors() as $key => $msgs) {
                $field = preg_match('/^juara_\d$/', $key) ? "data.{$key}" : 'data.juara_1';
                $pesan[$field] = array_merge($pesan[$field] ?? [], $msgs);
            }

            throw ValidationException::withMessages($pesan);
        }

        $jumlah = count($baris);
        Notification::make()
            ->title($jumlah ? "{$jumlah} juara {$this->lomba->nama} tersimpan" : "Juara {$this->lomba->nama} dikosongkan")
            ->success()
            ->send();

        $this->redirect(KelolaCabor::getUrl(['cabor' => $this->lomba->cabor->slug]));
    }

    /**
     * Peserta terverifikasi di lomba ini: [id => "MTsN 3 Jakarta — Rizky, Annisa, Farhan"].
     *
     * @return array<int, string>
     */
    protected function opsiPendaftaran(): array
    {
        return once(fn () => Pendaftaran::query()
            ->where('lomba_id', $this->lomba->id)
            ->diverifikasi()
            ->with(['madrasah', 'peserta'])
            ->get()
            ->sortBy(fn (Pendaftaran $d) => $d->madrasah->nama)
            ->mapWithKeys(fn (Pendaftaran $d) => [$d->id => $d->label()])
            ->all());
    }

    /** @return list<string> pendaftaran_id yang sudah dipilih di semua peringkat */
    protected function pendaftaranTerpilih(): array
    {
        return collect(Medali::cases())
            ->flatMap(fn (Medali $m) => $this->data["juara_{$m->value}"] ?? [])
            ->pluck('pendaftaran_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    protected function labelItem(array $state): ?string
    {
        if (filled($state['pendaftaran_id'] ?? null)) {
            return explode(' — ', $this->opsiPendaftaran()[$state['pendaftaran_id']] ?? '')[0] ?: null;
        }
        if (filled($state['madrasah_id'] ?? null)) {
            return Madrasah::find($state['madrasah_id'])?->nama.' (manual)';
        }

        return null;
    }
}
