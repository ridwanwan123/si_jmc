<?php

namespace App\Filament\Widgets;

use App\Models\Edisi;
use App\Services\KlasemenService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Angka ringkas edisi aktif (dari KlasemenService). */
class RingkasanWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getHeading(): ?string
    {
        $edisi = Edisi::pilih();

        return $edisi ? "Ringkasan {$edisi->nama}" : 'Belum ada edisi';
    }

    protected function getDescription(): ?string
    {
        $edisi = Edisi::pilih();
        $label = $edisi ? app(KlasemenService::class)->labelTerakhirDiperbarui($edisi) : null;

        return $label ? "Terakhir diperbarui {$label}" : null;
    }

    protected function getStats(): array
    {
        $edisi = Edisi::pilih();

        if (! $edisi) {
            return [];
        }

        $r = app(KlasemenService::class)->ringkasan($edisi);

        return [
            Stat::make('Lomba selesai', "{$r['lomba_selesai']} / {$r['jumlah_lomba']}")
                ->description($r['jumlah_lomba'] - $r['lomba_selesai'].' lomba menunggu hasil'),
            Stat::make('Medali dibagikan', $r['total_medali'])
                ->description("{$r['emas']} emas · {$r['perak']} perak · {$r['perunggu']} perunggu"),
            Stat::make('Madrasah peraih medali', $r['madrasah_peraih'])
                ->description("dari {$r['madrasah_peserta']} madrasah peserta"),
        ];
    }
}
