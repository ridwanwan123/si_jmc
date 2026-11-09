<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('galeri_foto');
    }
};
