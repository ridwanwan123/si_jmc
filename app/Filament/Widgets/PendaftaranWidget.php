<?php

namespace App\Filament\Widgets;

use App\Enums\StatusPendaftaran;
use App\Models\Edisi;
use App\Models\Pendaftaran;
use App\Models\PendaftaranPeserta;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Ringkasan pendaftaran edisi aktif. */
class PendaftaranWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return Edisi::pilih() !== null;
    }

    protected function getHeading(): ?string
    {
        $edisi = Edisi::pilih();

        return "Pendaftaran {$edisi?->nama} · tahap {$edisi?->tahap()->label()}";
    }

    protected function getDescription(): ?string
    {
        $edisi = Edisi::pilih();

        if (! $edisi?->pendaftaran_tutup) {
            return null;
        }

        return ($edisi->pendaftaranDibuka() ? 'Dibuka sampai ' : 'Ditutup ').$edisi->pendaftaran_tutup->translatedFormat('j F Y');
    }

    protected function getStats(): array
    {
        $edisi = Edisi::pilih();

        $per = Pendaftaran::query()->where('edisi_id', $edisi->id)
            ->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        $siswa = PendaftaranPeserta::query()
            ->whereHas('pendaftaran', fn ($q) => $q->where('edisi_id', $edisi->id)->where('status', '!=', StatusPendaftaran::Ditolak->value))
            ->count();

        $madrasah = Pendaftaran::query()->where('edisi_id', $edisi->id)
            ->where('status', '!=', StatusPendaftaran::Ditolak->value)
            ->distinct('madrasah_id')->count('madrasah_id');

        return [
            Stat::make('Menunggu verifikasi', (int) ($per['menunggu'] ?? 0))
                ->color('warning')
                ->description('Periksa di menu Cabor → tab Peserta'),
            Stat::make('Diverifikasi', (int) ($per['diverifikasi'] ?? 0))
                ->description((int) ($per['ditolak'] ?? 0).' ditolak'),
            Stat::make('Siswa terdaftar', $siswa)
                ->description("dari {$madrasah} madrasah"),
        ];
    }
}
