<?php

namespace App\Http\Controllers;

use App\Enums\StatusPendaftaran;
use App\Http\Requests\DaftarRequest;
use App\Models\Cabor;
use App\Models\Lomba;
use App\Models\Madrasah;
use App\Models\Pendaftaran;
use App\Services\PendaftaranService;
use App\Support\EdisiTerpilih;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;

/**
 * /daftar: formulir pendaftaran peserta 5 langkah (madrasah → cabor → mata lomba → data peserta → preview).
 * Penyimpanan dan aturan pendaftaran ada di PendaftaranService.
 */
class DaftarController extends Controller
{
    public function __construct(
        private readonly EdisiTerpilih $edisiTerpilih,
        private readonly PendaftaranService $service,
    ) {}

    public function index(): View
    {
        $edisi = $this->edisiTerpilih->get();
        $dibuka = $edisi->pendaftaranDibuka() && ! $this->edisiTerpilih->arsip();

        $lomba = $edisi->exists
            ? Lomba::query()->with('cabor:id,slug')->where('edisi_id', $edisi->id)->orderBy('urutan')->orderBy('nama')->get()
            : collect();

        return view('publik.daftar', [
            'dibuka' => $dibuka,
            'madrasah' => Madrasah::query()->orderBy('nama')
                ->get(['id', 'npsn', 'nama', 'jenjang', 'status', 'kota', 'kelurahan'])
                ->map(fn (Madrasah $m) => [
                    'id' => $m->id,
                    'npsn' => $m->npsn,
                    'nama' => $m->nama,
                    'jenjang' => $m->jenjang->value,
                    'status' => $m->status?->value,
                    'kota' => $m->kota?->value,
                    'kelurahan' => $m->kelurahan,
                ])->values(),
            'cabor' => Cabor::query()->orderBy('urutan')->get()
                ->map(fn (Cabor $c) => ['id' => $c->id, 'nama' => $c->nama, 'ikon' => $c->ikon, 'nomor' => $c->nomor()])
                ->values(),
            'lomba' => $lomba->map(fn (Lomba $l) => [
                'id' => $l->id,
                'cabor_id' => $l->cabor_id,
                'nama' => $l->nama,
                'jenjang' => $l->jenjang->value,
                'kategori' => $l->kategori->value,
                'anggota' => $l->jumlah_anggota,
                'gtk' => $l->cabor->slug === 'gtk', // peserta guru: NUPTK 16 digit, bukan NISN
            ])->values(),
            // Pasangan madrasah–lomba yang sudah terdaftar, mis. "12-40" (ditandai "Sudah terdaftar").
            // Pendaftaran yang ditolak tidak dihitung: madrasah boleh daftar ulang.
            'terdaftar' => $edisi->exists
                ? Pendaftaran::query()->where('edisi_id', $edisi->id)
                    ->where('status', '!=', StatusPendaftaran::Ditolak->value)
                    ->get(['madrasah_id', 'lomba_id'])
                    ->map(fn ($p) => $p->madrasah_id.'-'.$p->lomba_id)->values()
                : [],
            'kontak' => config('jmc.pendaftaran.kontak_panitia'),
        ]);
    }

    public function store(DaftarRequest $request): JsonResponse
    {
        abort_if($this->edisiTerpilih->arsip(), 403, 'Pendaftaran hanya untuk edisi aktif.');

        $pendaftaran = $this->service->daftar(
            $this->edisiTerpilih->get(),
            $request->safe()->only(['madrasah_id', 'lomba_id', 'guru_nama', 'guru_wa', 'peserta']),
            $request->file('surat_tugas'),
        );

        return response()->json([
            'nomor' => $pendaftaran->nomor,
            'lomba' => $pendaftaran->lomba->nama.' '.$pendaftaran->lomba->kategori->value,
            'cabor' => $pendaftaran->lomba->cabor->nama,
            'madrasah' => $pendaftaran->madrasah->nama,
            // Link bertanda tangan: bukti berisi NISN dan nomor WA, jadi tidak bisa ditebak dari nomor saja.
            'bukti' => URL::signedRoute('daftar.bukti', ['pendaftaran' => $pendaftaran->nomor]),
        ], 201);
    }

    /** Bukti pendaftaran siap cetak (simpan sebagai PDF lewat browser). */
    public function bukti(Pendaftaran $pendaftaran): View
    {
        return view('publik.daftar-bukti', [
            'p' => $pendaftaran->load(['edisi', 'lomba.cabor', 'madrasah', 'peserta']),
        ]);
    }
}
