<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cabang lomba (master, 7 cabor). Foreign key users.cabor_id (admin cabor) dibuat di sini
 * karena tabel users dibuat lebih dulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cabor', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('slug')->unique();
            $table->string('ikon')->nullable();
            $table->unsignedTinyInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('cabor_id')->references('id')->on('cabor')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['cabor_id']);
        });
        Schema::dropIfExists('cabor');
    }
};
