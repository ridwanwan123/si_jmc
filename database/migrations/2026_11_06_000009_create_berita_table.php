<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berita per edisi: pengumuman, liputan, dan kabar hasil lomba.
 * terbit_pada NULL = draf (tidak tampil di halaman publik).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('berita', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edisi_id')->constrained('edisi')->cascadeOnDelete();
            $table->foreignId('cabor_id')->nullable()->constrained('cabor')->nullOnDelete();
            $table->string('kategori', 20)->index(); // pengumuman / liputan / hasil
            $table->string('judul');
            $table->string('slug')->unique();
            $table->text('ringkasan');
            $table->longText('isi');
            $table->string('sampul')->nullable();
            $table->string('keterangan_sampul')->nullable();
            $table->string('penulis')->nullable(); // kosong = "Panitia JMC {tahun}"
            $table->json('tag')->nullable();
            $table->boolean('unggulan')->default(false);
            $table->boolean('sematkan_di_pita')->default(false);
            $table->timestamp('terbit_pada')->nullable();
            $table->timestamps();

            $table->index(['edisi_id', 'terbit_pada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('berita');
    }
};
