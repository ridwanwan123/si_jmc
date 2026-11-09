<?php

namespace App\Support;

use App\Models\Edisi;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Edisi yang sedang ditampilkan di halaman publik.
 *
 * ?edisi=2025 memilih edisi arsip; tanpa parameter = edisi aktif.
 * Tahun yang tidak dikenal → 404 (ditangani middleware TetapkanEdisi).
 * Didaftarkan sebagai scoped singleton (satu per request).
 */
class EdisiTerpilih
{
    private ?Edisi $edisi = null;

    private bool $dariParameter = false;

    /** @var Collection<int, Edisi>|null */
    private ?Collection $semua = null;

    public function resolve(Request $request): ?Edisi
    {
        // Mulai bersih setiap request (instance bisa dipakai ulang, mis. di test atau Octane).
        $this->semua = null;
        $this->edisi = null;
        $this->dariParameter = false;

        $tahun = $request->query('edisi');

        if ($tahun !== null && $tahun !== '') {
            if (! ctype_digit((string) $tahun)) {
                return null;
            }

            $this->edisi = $this->semua()->firstWhere('tahun', (int) $tahun);
            $this->dariParameter = $this->edisi !== null && ! $this->edisi->is_aktif;

            return $this->edisi;
        }

        // Belum ada edisi sama sekali → edisi sementara (tidak disimpan) agar web tetap tampil kosong.
        $this->edisi = $this->semua()->firstWhere('is_aktif', true)
            ?? $this->semua()->first()
            ?? Edisi::sementara();

        return $this->edisi;
    }

    /** Edisi terpilih hanya pengganti karena database belum berisi edisi. */
    public function sementara(): bool
    {
        return ! $this->get()->exists;
    }

    /** Edisi untuk dropdown dan footer: semua edisi, atau edisi sementara jika belum ada. */
    public function pilihan(): Collection
    {
        return $this->semua()->isNotEmpty() ? $this->semua() : collect([$this->get()]);
    }

    public function get(): Edisi
    {
        return $this->edisi ?? throw new \LogicException('Edisi belum ditetapkan. Pasang middleware TetapkanEdisi.');
    }

    /** Apakah halaman sedang menampilkan edisi arsip (bukan edisi aktif). */
    public function arsip(): bool
    {
        return $this->dariParameter;
    }

    /** Parameter query untuk dibawa ke link lain: ['edisi' => 2025] atau []. */
    public function param(): array
    {
        return $this->dariParameter ? ['edisi' => $this->edisi->tahun] : [];
    }

    /** route() yang otomatis membawa ?edisi= jika sedang melihat arsip. */
    public function route(string $name, array $params = []): string
    {
        return route($name, $params + $this->param());
    }

    /**
     * Halaman yang sama untuk edisi lain (dropdown, footer, banner arsip).
     * Parameter lain dipertahankan, halaman pagination di-reset, tanpa "?" menggantung.
     */
    public function urlUntuk(Edisi $edisi): string
    {
        $query = Arr::except(request()->query(), ['edisi', 'page']);

        if (! $edisi->is_aktif) {
            $query['edisi'] = $edisi->tahun;
        }

        return url()->current().($query ? '?'.Arr::query($query) : '');
    }

    /** Edisi terpilih belum punya lomba sama sekali. */
    public function kosong(): bool
    {
        return (int) $this->get()->lomba_count === 0;
    }

    /** Semua edisi, terbaru dulu (untuk dropdown dan footer arsip), dengan lomba_count. */
    public function semua(): Collection
    {
        return $this->semua ??= Edisi::query()->withCount('lomba')->orderByDesc('tahun')->get();
    }
}
