<?php

namespace App\Http\Controllers;

use App\Data\BarisKlasemen;
use App\Models\Edisi;
use App\Services\KlasemenService;
use App\Support\EdisiTerpilih;
use Illuminate\Contracts\View\View;

/**
 * /arsip: semua edisi beserta ringkasan dan juara umumnya.
 * Angka diambil dari KlasemenService (tidak dihitung ulang di sini).
 */
class ArsipController extends Controller
{
    public function __invoke(EdisiTerpilih $edisiTerpilih, KlasemenService $klasemen): View
    {
        $daftar = $edisiTerpilih->semua()->map(fn (Edisi $e) => [
            'edisi' => $e,
            'ringkasan' => $klasemen->ringkasan($e),
            'juaraUmum' => $klasemen->klasemen($e)->filter(fn (BarisKlasemen $b) => $b->peringkat === 1)->values(),
            'param' => $e->is_aktif ? [] : ['edisi' => $e->tahun],
        ]);

        return view('publik.arsip', ['daftar' => $daftar]);
    }
}
