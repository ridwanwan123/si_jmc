<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pendaftaran peserta: satu baris = satu peserta / satu tim dari satu madrasah di satu lomba.
 * Satu madrasah = satu pendaftaran AKTIF per lomba: kolom generated madrasah_aktif bernilai
 * NULL jika ditolak, sehingga unique (lomba_id, madrasah_aktif) tetap mengizinkan daftar ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pendaftaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edisi_id')->constrained('edisi')->cascadeOnDelete();
            $table->foreignId('lomba_id')->constrained('lomba')->cascadeOnDelete();
            $table->foreignId('madrasah_id')->constrained('madrasah')->restrictOnDelete();
            $table->unsignedBigInteger('madrasah_aktif')->nullable()
                ->storedAs("CASE WHEN status <> 'ditolak' THEN madrasah_id END");
            $table->string('nomor', 20)->unique(); // JMC26-0001
            $table->string('guru_nama');
            $table->string('guru_wa', 20);
            $table->string('surat_tugas_path')->nullable();
            $table->string('status', 20)->default('menunggu')->index(); // menunggu / diverifikasi / ditolak
            $table->text('catatan')->nullable();
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->timestamps();

            $table->unique(['lomba_id', 'madrasah_aktif'], 'pendaftaran_lomba_madrasah_aktif_unik');
            $table->index(['edisi_id', 'status']);
        });

        Schema::create('pendaftaran_peserta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran')->cascadeOnDelete();
            $table->string('nama');
            $table->string('nisn', 16); // NISN siswa (10 digit) atau NUPTK guru/tendik GTK (16 digit)
            $table->unsignedTinyInteger('urutan')->default(0); // 1 = kapten (lomba tim)
            $table->timestamps();

            $table->index('nisn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pendaftaran_peserta');
        Schema::dropIfExists('pendaftaran');
    }
};
