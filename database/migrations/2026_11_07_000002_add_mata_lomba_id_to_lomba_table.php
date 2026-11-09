<?php

use App\Models\MataLomba;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hubungkan lomba per edisi ke master mata lomba.
 * nama/jenjang/kategori/cabor di tabel lomba tetap disimpan sebagai snapshot
 * (agar riwayat tidak berubah jika master diganti kemudian).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lomba', function (Blueprint $table) {
            $table->foreignId('mata_lomba_id')->nullable()->after('cabor_id')
                ->constrained('mata_lomba')->restrictOnDelete();
        });

        // Isi master dari lomba yang sudah ada, lalu hubungkan.
        MataLomba::isiDariLombaLama();

        Schema::table('lomba', function (Blueprint $table) {
            $table->unique(['edisi_id', 'mata_lomba_id'], 'lomba_edisi_mata_lomba_unik');
        });
    }

    public function down(): void
    {
        Schema::table('lomba', function (Blueprint $table) {
            $table->dropUnique('lomba_edisi_mata_lomba_unik');
            $table->dropConstrainedForeignId('mata_lomba_id');
        });
    }
};
