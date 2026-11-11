<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Edisi JMC per tahun. Hanya satu yang is_aktif. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edisi', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun')->unique();
            $table->string('nama');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->string('lokasi')->nullable();
            $table->date('pendaftaran_buka')->nullable();
            $table->date('pendaftaran_tutup')->nullable();
            $table->boolean('is_aktif')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edisi');
    }
};
