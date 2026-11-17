<?php

namespace App\Http\Controllers;

use App\Models\Pendaftaran;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menampilkan surat tugas (PDF) langsung di browser untuk admin yang memverifikasi.
 * File ada di disk privat, jadi hanya admin yang boleh mengelola lomba tersebut yang bisa membukanya.
 */
class SuratTugasController extends Controller
{
    public function __invoke(Pendaftaran $pendaftaran): StreamedResponse
    {
        $user = auth()->user();
        abort_unless($user && $user->bolehKelolaLomba($pendaftaran->lomba), 403);

        $path = $pendaftaran->surat_tugas_path;
        abort_unless(filled($path) && Storage::disk(Pendaftaran::DISK)->exists($path), 404, 'Surat tugas tidak ditemukan.');

        return Storage::disk(Pendaftaran::DISK)->response(
            $path,
            "surat-tugas-{$pendaftaran->nomor}.pdf",
            ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff'],
            'inline',
        );
    }
}
