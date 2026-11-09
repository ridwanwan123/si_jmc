<?php

namespace App\Http\Controllers;

use App\Data\BarisKlasemen;
use App\Enums\StatusPendaftaran;
use App\Models\Edisi;
use App\Models\GaleriAlbum;
use App\Models\Pendaftaran;
use App\Models\PendaftaranPeserta;
use App\Services\HasilPublikService;
use App\Services\KlasemenService;
use App\Support\EdisiTerpilih;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class BerandaController extends Controller
{
    public function __invoke(EdisiTerpilih $edisiTerpilih, KlasemenService $klasemen, HasilPublikService $publik): View
    {
        $edisi = $edisiTerpilih->get();
        $baris = $klasemen->klasemen($edisi);
        $top = $baris->take(5);

        return view('publik.beranda', [
            'tahap' => $edisi->tahap(),
            'status' => $edisi->status() ?? ($edisi->selesai() ? 'SELESAI' : null),
            'terakhir' => $klasemen->labelTerakhirDiperbarui($edisi),
            'podium' => $baris->take(3),
            'top' => $top,
            'maksTop' => max(1, (int) $top->max(fn (BarisKlasemen $b) => $b->total())),
            'ticker' => $publik->peraihMedali($edisi, limit: 10),
            'ringkasan' => $klasemen->ringkasan($edisi),
            'cabor' => $publik->caborDenganJumlah($edisi),
            'mataLomba' => $publik->mataLombaPerCabor($edisi),
            'galeri' => $this->galeriPilihan($edisi),
            'pendaftaran' => $this->ringkasanPendaftaran($edisi),
        ]);
    }

    /**
     * Angka pendaftaran untuk kotak podium saat belum ada juara (tahap registrasi/tanding).
     * Null jika belum ada pendaftaran sama sekali.
     *
     * @return array{tim:int, madrasah:int, siswa:int, sisaHari:?int}|null
     */
    private function ringkasanPendaftaran(Edisi $edisi): ?array
    {
        if (! $edisi->exists) {
            return null;
        }

        $aktif = Pendaftaran::query()->where('edisi_id', $edisi->id)
            ->where('status', '!=', StatusPendaftaran::Ditolak->value);

        $tim = (clone $aktif)->count();
        if ($tim === 0) {
            return null;
        }

        return [
            'tim' => $tim,
            'madrasah' => (clone $aktif)->distinct('madrasah_id')->count('madrasah_id'),
            'siswa' => PendaftaranPeserta::query()->whereIn('pendaftaran_id', (clone $aktif)->select('id'))->count(),
            'sisaHari' => $edisi->pendaftaranDibuka() && $edisi->pendaftaran_tutup
                ? (int) now(config('jmc.timezone'))->startOfDay()->diffInDays($edisi->pendaftaran_tutup->copy()->startOfDay())
                : null,
        ];
    }

    /**
     * Satu album utama (album umum terbaru, mis. pembukaan) + 4 album lain.
     *
     * @return Collection<int, GaleriAlbum>
     */
    private function galeriPilihan(Edisi $edisi): Collection
    {
        $album = GaleriAlbum::query()
            ->where('edisi_id', $edisi->id)
            ->adaFoto()
            ->with(['cabor', 'sampul'])
            ->withCount('foto')
            ->orderByDesc('tanggal')
            ->limit(20)
            ->get();

        $utama = $album->firstWhere('cabor_id', null) ?? $album->first();

        return $utama
            ? collect([$utama])->merge($album->reject(fn (GaleriAlbum $a) => $a->is($utama))->take(4))
            : collect();
    }
}
