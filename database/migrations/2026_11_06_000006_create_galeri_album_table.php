<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galeri_album');
    }
};
