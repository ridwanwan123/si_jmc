<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pendaftaran peserta: satu baris = satu peserta / satu tim dari satu madrasah di satu lomba.
 * Nama dan NISN siswa di pendaftaran_peserta. Hasil (juara) bisa menunjuk pendaftaran.
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
            $table->string('nomor', 20)->unique(); // JMC26-0001
            $table->string('guru_nama');
            $table->string('guru_wa', 20);
            $table->string('surat_tugas_path')->nullable();
            $table->string('status', 20)->default('menunggu')->index(); // menunggu / diverifikasi / ditolak
            $table->text('catatan')->nullable();
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->timestamps();

            // Satu madrasah mengirim satu peserta atau satu tim per lomba.
            $table->unique(['lomba_id', 'madrasah_id'], 'pendaftaran_lomba_madrasah_unik');
            $table->index(['edisi_id', 'status']);
        });

        Schema::create('pendaftaran_peserta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran')->cascadeOnDelete();
            $table->string('nama');
            $table->string('nisn', 16); // NISN siswa (10 digit) atau NUPTK guru/tendik GTK (16 digit)
            $table->unsignedTinyInteger('urutan')->default(0);
            $table->timestamps();

            $table->index('nisn');
        });

        Schema::table('hasil', function (Blueprint $table) {
            $table->foreignId('pendaftaran_id')->nullable()->after('madrasah_id')
                ->constrained('pendaftaran')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hasil', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pendaftaran_id');
        });
        Schema::dropIfExists('pendaftaran_peserta');
        Schema::dropIfExists('pendaftaran');
    }
};
