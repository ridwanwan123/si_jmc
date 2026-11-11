<?php

namespace App\Http\Controllers;

use App\Enums\KategoriBerita;
use App\Models\Berita;
use App\Services\BeritaService;
use App\Support\EdisiTerpilih;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BeritaController extends Controller
{
    /** Jumlah kartu "Terbaru" per muat. */
    public const PER_MUAT = 6;

    public function __construct(
        private readonly EdisiTerpilih $edisiTerpilih,
        private readonly BeritaService $berita,
    ) {}

    public function index(Request $request): View
    {
        $edisi = $this->edisiTerpilih->get();
        $kategori = KategoriBerita::tryFrom((string) $request->query('kategori'));
        $tampil = min(60, max(self::PER_MUAT, (int) $request->query('tampil', self::PER_MUAT)));

        $unggulan = $this->berita->unggulan($edisi, $kategori);
        $sisa = $this->berita->query($edisi, $kategori)->when($unggulan, fn ($q) => $q->whereKeyNot($unggulan->id));
        $total = (clone $sisa)->count();

        return view('publik.berita-index', [
            'kategori' => $kategori,
            'unggulan' => $unggulan,
            'terbaru' => $sisa->limit($tampil)->get(),
            'adaLagi' => $total > $tampil,
            'urlLagi' => rute('berita.index', array_filter(['kategori' => $kategori?->value, 'tampil' => $tampil + self::PER_MUAT])).'#terbaru',
            'pengumuman' => $this->berita->query($edisi, KategoriBerita::Pengumuman)->limit(3)->get(),
            'pita' => $this->berita->pita($edisi),
            'dibuka' => $edisi->pendaftaranDibuka() && ! $this->edisiTerpilih->arsip(),
            'opsiKategori' => collect([null, ...KategoriBerita::cases()])->map(fn (?KategoriBerita $k) => [
                $k?->label() ?? 'Semua',
                rute('berita.index', array_filter(['kategori' => $k?->value])),
                $k === $kategori,
            ])->all(),
        ]);
    }

    public function show(Berita $berita): View|RedirectResponse
    {
        abort_unless($berita->terbit_pada && $berita->terbit_pada->isPast(), 404);

        $edisi = $this->edisiTerpilih->get();

        // Berita milik edisi lain: arahkan ke edisi berita itu agar navbar/arsip konsisten.
        if ($berita->edisi_id !== $edisi->id) {
            $e = $berita->edisi()->first(['tahun', 'is_aktif']);

            return redirect()->route('berita.show', ['berita' => $berita->slug] + ($e->is_aktif ? [] : ['edisi' => $e->tahun]));
        }

        $berita->load(['cabor', 'edisi']);

        return view('publik.berita-show', [
            'berita' => $berita,
            'lainnya' => $this->berita->lainnya($berita),
            'pita' => $this->berita->pita($edisi),
            'dibuka' => $edisi->pendaftaranDibuka() && ! $this->edisiTerpilih->arsip(),
        ]);
    }
}
