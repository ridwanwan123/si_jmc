<?php

namespace App\Http\Controllers;

use App\Data\BarisKlasemen;
use App\Enums\Jenjang;
use App\Http\Requests\KlasemenRequest;
use App\Models\Cabor;
use App\Services\HasilPublikService;
use App\Services\KlasemenService;
use App\Support\EdisiTerpilih;
use Illuminate\Contracts\View\View;

class KlasemenController extends Controller
{
    public function __invoke(
        KlasemenRequest $request,
        EdisiTerpilih $edisiTerpilih,
        KlasemenService $klasemen,
        HasilPublikService $publik,
    ): View {
        $edisi = $edisiTerpilih->get();
        $tab = $request->tab();
        $jenjang = $request->jenjang();
        $cabor = $request->cabor();

        $data = [
            'tab' => $tab,
            'jenjang' => $jenjang,
            'cabor' => $cabor,
            'kata' => $request->kata(),
            'semuaCabor' => Cabor::query()->orderBy('urutan')->get(),
            'terakhir' => $klasemen->labelTerakhirDiperbarui($edisi),
            // Parameter filter yang dibawa antar-tab dan antar-filter.
            'filter' => array_filter([
                'jenjang' => $jenjang?->value,
                'cabor' => $cabor?->slug,
            ]),
        ];

        if ($tab === 'klasemen') {
            $baris = $klasemen->klasemen($edisi, $cabor?->id, $jenjang);
            $data['baris'] = $baris;
            $data['maks'] = max(1, (int) $baris->max(fn (BarisKlasemen $b) => $b->total()));
            $data['rincian'] = $klasemen->rincianPerCabor($edisi, $baris->pluck('madrasahId')->all());
        } elseif ($tab === 'peraih') {
            $data['lomba'] = $publik->peraihMedali($edisi, $cabor?->id, $jenjang);
        } else {
            $data['hasilCari'] = mb_strlen($request->kata()) >= 2 ? $publik->cari($edisi, $request->kata()) : null;
            $data['klasemenUmum'] = $data['hasilCari']
                ? $klasemen->klasemen($edisi)->keyBy('madrasahId')
                : collect();
        }

        $data['opsiJenjang'] = collect([null, ...Jenjang::cases()])->map(fn (?Jenjang $j) => [
            $j?->value ?? 'Semua',
            rute('klasemen', array_filter(['tab' => $tab === 'klasemen' ? null : $tab, 'jenjang' => $j?->value, 'cabor' => $cabor?->slug])),
            $j === $jenjang,
        ])->all();

        return view('publik.klasemen', $data);
    }
}
