<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Juara lomba: satu baris per juara. Sengaja TIDAK unique per (lomba_id, peringkat):
 * juara bisa lebih dari satu per peringkat, dibatasi kuota_juara_N (divalidasi di HasilService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hasil', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lomba_id')->constrained('lomba')->cascadeOnDelete();
            $table->unsignedTinyInteger('peringkat'); // 1 emas, 2 perak, 3 perunggu
            $table->foreignId('madrasah_id')->constrained('madrasah')->restrictOnDelete();
            $table->foreignId('pendaftaran_id')->nullable()->constrained('pendaftaran')->nullOnDelete();
            $table->text('nama_peserta')->nullable(); // satu nama per baris untuk tim
            $table->unsignedTinyInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['lomba_id', 'peringkat']);
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil');
    }
};
