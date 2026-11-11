<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Galeri: album per edisi (opsional per cabor/lomba) dan fotonya. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galeri_album', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edisi_id')->constrained('edisi')->cascadeOnDelete();
            $table->foreignId('cabor_id')->nullable()->constrained('cabor')->nullOnDelete();
            $table->foreignId('lomba_id')->nullable()->constrained('lomba')->nullOnDelete();
            $table->string('judul');
            $table->string('slug')->unique();
            $table->date('tanggal')->nullable();
            $table->unsignedBigInteger('sampul_foto_id')->nullable(); // FK dibuat setelah galeri_foto ada
            $table->timestamps();
        });

        Schema::create('galeri_foto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained('galeri_album')->cascadeOnDelete();
            $table->string('path');
            $table->string('thumb_path');
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['album_id', 'urutan']);
        });

        Schema::table('galeri_album', function (Blueprint $table) {
            $table->foreign('sampul_foto_id')->references('id')->on('galeri_foto')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('galeri_album', function (Blueprint $table) {
            $table->dropForeign(['sampul_foto_id']);
        });
        Schema::dropIfExists('galeri_foto');
        Schema::dropIfExists('galeri_album');
    }
};
