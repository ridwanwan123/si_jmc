<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lomba = satu mata lomba di satu edisi. Nama/jenjang/kategori/cabor/pengelola
 * disalin dari master sebagai snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lomba', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edisi_id')->constrained('edisi')->cascadeOnDelete();
            $table->foreignId('cabor_id')->constrained('cabor')->restrictOnDelete();
            $table->foreignId('pengelola_cabor_id')->constrained('cabor')->restrictOnDelete();
            $table->foreignId('mata_lomba_id')->nullable()->constrained('mata_lomba')->restrictOnDelete();
            $table->string('nama');
            $table->enum('jenjang', ['RA', 'MI', 'MTs', 'MA']);
            $table->enum('kategori', ['Putra', 'Putri', 'Umum', 'Beregu']);
            $table->unsignedTinyInteger('jumlah_anggota')->default(1); // maksimal anggota; 1 = perorangan
            $table->unsignedTinyInteger('kuota_juara_1')->default(1);
            $table->unsignedTinyInteger('kuota_juara_2')->default(2);
            $table->unsignedTinyInteger('kuota_juara_3')->default(3);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();

            $table->unique(['edisi_id', 'mata_lomba_id'], 'lomba_edisi_mata_lomba_unik');
            $table->index(['edisi_id', 'cabor_id']);
            $table->index(['edisi_id', 'pengelola_cabor_id']);
            $table->index(['edisi_id', 'jenjang']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lomba');
    }
};
