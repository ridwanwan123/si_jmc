<?php

namespace App\Http\Controllers;

use App\Enums\Jenjang;
use App\Http\Requests\MadrasahIndexRequest;
use App\Models\Cabor;
use App\Models\Madrasah;
use App\Services\HasilPublikService;
use App\Services\KlasemenService;
use App\Support\EdisiTerpilih;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class MadrasahController extends Controller
{
    public function __construct(
        private readonly EdisiTerpilih $edisiTerpilih,
        private readonly KlasemenService $klasemen,
        private readonly HasilPublikService $publik,
    ) {}

    public function index(MadrasahIndexRequest $request): View
    {
        $edisi = $this->edisiTerpilih->get();
        $kata = $request->kata();
        $jenjang = $request->jenjang();

        $madrasah = Madrasah::query()
            ->when($kata !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('nama', 'like', '%'.addcslashes($kata, '%_\\').'%')
                ->orWhere('nsm', 'like', addcslashes($kata, '%_\\').'%')))
            ->when($jenjang, fn (Builder $q) => $q->where('jenjang', $jenjang->value))
            ->orderBy('nama')
            ->paginate(50)
            ->withQueryString();

        return view('publik.madrasah-index', [
            'madrasah' => $madrasah,
            'kata' => $kata,
            'jenjang' => $jenjang,
            // Perolehan medali di edisi ini, untuk semua jenjang (bukan hanya klasemen umum).
            'medali' => collect(Jenjang::cases())
                ->flatMap(fn (Jenjang $j) => $this->klasemen->klasemen($edisi, jenjang: $j))
                ->keyBy('madrasahId'),
            'opsiJenjang' => collect([null, ...Jenjang::cases()])->map(fn (?Jenjang $j) => [
                $j?->value ?? 'Semua',
                rute('madrasah.index', array_filter(['q' => $kata ?: null, 'jenjang' => $j?->value])),
                $j === $jenjang,
            ])->all(),
        ]);
    }

    public function show(Madrasah $madrasah): View
    {
        $edisi = $this->edisiTerpilih->get();

        // Peringkat di klasemen umum; jika jenjangnya tidak ikut klasemen umum, pakai klasemen jenjangnya.
        $ikutUmum = in_array($madrasah->jenjang, Jenjang::klasemenUmum(), true);
        $klasemen = $this->klasemen->klasemen($edisi, jenjang: $ikutUmum ? null : $madrasah->jenjang);
        $baris = $klasemen->firstWhere('madrasahId', $madrasah->id);

        $rincian = $this->klasemen->rincianPerCabor($edisi, [$madrasah->id])[$madrasah->id] ?? [];

        return view('publik.madrasah-show', [
            'madrasah' => $madrasah,
            'baris' => $baris,
            'jumlahDiKlasemen' => $klasemen->count(),
            'juara' => $this->publik->juaraMadrasah($edisi, $madrasah),
            'rincian' => $rincian,
            'semuaCabor' => Cabor::query()->orderBy('urutan')->get(),
            'riwayat' => $this->klasemen->riwayat($madrasah),
            'opsi' => Madrasah::query()->orderBy('nama')->get(['id', 'nsm', 'nama', 'jenjang']),
            'inisial' => $this->inisial($madrasah->nama),
        ]);
    }

    /** "MTsN 3 Jakarta" → "M3", "MI Al-Falah" → "MA". */
    private function inisial(string $nama): string
    {
        $kata = collect(preg_split('/\s+/', trim($nama)))
            ->reject(fn ($k) => in_array(mb_strtolower($k), ['jakarta', 'dki'], true))
            ->values();

        return mb_strtoupper(
            $kata->take(2)->map(fn ($k) => ctype_digit($k) ? $k : mb_substr($k, 0, 1))->implode('')
        ) ?: 'M';
    }
}
