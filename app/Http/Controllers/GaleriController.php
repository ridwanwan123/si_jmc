<?php

namespace App\Http\Controllers;

use App\Models\Cabor;
use App\Models\GaleriAlbum;
use App\Support\EdisiTerpilih;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;

class GaleriController extends Controller
{
    public function __construct(private readonly EdisiTerpilih $edisiTerpilih) {}

    public function index(): View
    {
        $edisi = $this->edisiTerpilih->get();

        return view('publik.galeri-index', [
            'album' => GaleriAlbum::query()
                ->where('edisi_id', $edisi->id)
                ->adaFoto()
                ->with(['cabor', 'sampul'])
                ->withCount('foto')
                ->orderByDesc('tanggal')
                ->orderBy('judul')
                ->get(),
            'semuaCabor' => Cabor::query()->orderBy('urutan')->get(),
        ]);
    }

    public function show(GaleriAlbum $album): View|RedirectResponse
    {
        $edisi = $this->edisiTerpilih->get();

        // Album milik edisi lain: arahkan ke edisi album itu agar navbar/arsip konsisten.
        if ($album->edisi_id !== $edisi->id) {
            $tahun = $album->edisi()->value('tahun');
            $aktif = $album->edisi()->value('is_aktif');

            return redirect()->route('galeri.show', ['album' => $album->slug] + ($aktif ? [] : ['edisi' => $tahun]));
        }

        $album->load(['cabor', 'lomba', 'foto']);
        abort_if($album->foto->isEmpty(), 404);

        return view('publik.galeri-show', [
            'album' => $album,
            'lainnya' => GaleriAlbum::query()
                ->where('edisi_id', $edisi->id)
                ->whereKeyNot($album->id)
                ->adaFoto()
                ->when($album->cabor_id, fn (Builder $q) => $q->where('cabor_id', $album->cabor_id))
                ->with(['cabor', 'sampul'])
                ->withCount('foto')
                ->orderByDesc('tanggal')
                ->limit(4)
                ->get(),
        ]);
    }
}
