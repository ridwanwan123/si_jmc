<?php

use App\Http\Controllers\ArsipController;
use App\Http\Controllers\BerandaController;
use App\Http\Controllers\CaborController;
use App\Http\Controllers\DaftarController;
use App\Http\Controllers\GaleriController;
use App\Http\Controllers\KlasemenController;
use App\Http\Controllers\MadrasahController;
use App\Http\Middleware\TetapkanEdisi;
use Illuminate\Support\Facades\Route;

// Halaman publik. Semua menerima ?edisi=YYYY (tanpa parameter = edisi aktif).
Route::middleware(TetapkanEdisi::class)->group(function () {
    Route::get('/', BerandaController::class)->name('beranda');
    Route::get('/klasemen', KlasemenController::class)->name('klasemen');

    Route::get('/cabor', [CaborController::class, 'index'])->name('cabor.index');
    Route::get('/cabor/{cabor:slug}', [CaborController::class, 'show'])->name('cabor.show');

    Route::get('/madrasah', [MadrasahController::class, 'index'])->name('madrasah.index');
    Route::get('/madrasah/{madrasah:nsm}', [MadrasahController::class, 'show'])->name('madrasah.show');

    Route::get('/arsip', ArsipController::class)->name('arsip');

    // Pendaftaran peserta.
    Route::get('/daftar', [DaftarController::class, 'index'])->name('daftar');
    Route::post('/daftar', [DaftarController::class, 'store'])->middleware('throttle:10,1')->name('daftar.store');
    Route::get('/daftar/bukti/{pendaftaran:nomor}', [DaftarController::class, 'bukti'])->middleware('signed')->name('daftar.bukti');

    Route::get('/galeri', [GaleriController::class, 'index'])->name('galeri.index');
    Route::get('/galeri/{album:slug}', [GaleriController::class, 'show'])->name('galeri.show');
});
