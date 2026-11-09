<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galeri_album', function (Blueprint $table) {
            $table->foreignId('sampul_foto_id')->nullable()->after('tanggal')
                ->constrained('galeri_foto')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('galeri_album', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sampul_foto_id');
        });
    }
};
