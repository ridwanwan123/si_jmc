<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog mata lomba (master). pengelola_cabor_id = cabor yang admin-nya mengurus
 * mata lomba ini; sama dengan cabor_id kecuali lomba GTK (mis. Badminton GTK → Olahraga).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mata_lomba', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabor_id')->constrained('cabor')->restrictOnDelete();
            $table->foreignId('pengelola_cabor_id')->constrained('cabor')->restrictOnDelete();
            $table->string('nama');
            $table->enum('jenjang', ['RA', 'MI', 'MTs', 'MA']);
            $table->enum('kategori', ['Putra', 'Putri', 'Umum', 'Beregu']);
            $table->unsignedTinyInteger('jumlah_anggota')->default(1); // maksimal anggota; 1 = perorangan
            $table->unsignedTinyInteger('kuota_juara_1')->default(1);
            $table->unsignedTinyInteger('kuota_juara_2')->default(2);
            $table->unsignedTinyInteger('kuota_juara_3')->default(3);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_aktif')->default(true)->index();
            $table->timestamps();

            $table->unique(['cabor_id', 'nama', 'jenjang', 'kategori'], 'mata_lomba_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mata_lomba');
    }
};
