<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Madrasah DKI Jakarta (data awal: MadrasahSeeder). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('madrasah', function (Blueprint $table) {
            $table->id();
            $table->string('npsn', 20)->unique();
            $table->string('nama');
            $table->enum('jenjang', ['RA', 'MI', 'MTs', 'MA'])->index();
            $table->enum('status', ['Negeri', 'Swasta']);
            $table->string('logo')->nullable();
            $table->string('provinsi')->nullable();
            $table->enum('kota', [
                'Jakarta Pusat', 'Jakarta Utara', 'Jakarta Barat',
                'Jakarta Selatan', 'Jakarta Timur', 'Kepulauan Seribu',
            ]);
            $table->string('kecamatan')->nullable();
            $table->string('kelurahan')->nullable();
            $table->text('alamat_sekolah')->nullable();
            $table->timestamps();

            $table->index('nama');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('madrasah');
    }
};
