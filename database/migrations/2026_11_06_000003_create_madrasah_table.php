<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('madrasah', function (Blueprint $table) {
            $table->id();
            $table->string('nsm', 20)->unique();
            $table->string('nama');
            $table->enum('jenjang', ['RA', 'MI', 'MTs', 'MA'])->index();
            $table->enum('status', ['Negeri', 'Swasta']);
            $table->enum('wilayah', [
                'Jakarta Pusat', 'Jakarta Utara', 'Jakarta Barat',
                'Jakarta Selatan', 'Jakarta Timur', 'Kepulauan Seribu',
            ]);
            $table->string('logo')->nullable();
            $table->timestamps();

            $table->index('nama');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('madrasah');
    }
};
