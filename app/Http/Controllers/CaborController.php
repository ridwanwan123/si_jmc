<?php

namespace App\Http\Controllers;

use App\Models\Cabor;
use App\Services\HasilPublikService;
use App\Support\EdisiTerpilih;
use Illuminate\Contracts\View\View;

class CaborController extends Controller
{
    public function __construct(
        private readonly EdisiTerpilih $edisiTerpilih,
        private readonly HasilPublikService $publik,
    ) {}

    /** /cabor menampilkan cabor pertama (01). */
    public function index(): View
    {
        return $this->tampil(Cabor::query()->orderBy('urutan')->firstOrFail());
    }

    public function show(Cabor $cabor): View
    {
        return $this->tampil($cabor);
    }

    private function tampil(Cabor $terpilih): View
    {
        $edisi = $this->edisiTerpilih->get();
        $semua = $this->publik->caborDenganJumlah($edisi);

        return view('publik.cabor', [
            'semuaCabor' => $semua,
            'terpilih' => $semua->firstWhere('id', $terpilih->id),
            // Semua jenjang RA–MA; jenjang tanpa lomba menampilkan "Tidak ada lomba di jenjang ini."
            // Jika cabor belum punya lomba sama sekali, kelompok jenjang tidak ditampilkan.
            'perJenjang' => (fn ($g) => $g->flatten()->isEmpty() ? collect() : $g)($this->publik->lombaPerJenjang($edisi, $terpilih)),
        ]);
    }
}
