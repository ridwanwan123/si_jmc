<?php

namespace App\Filament\Widgets;

use App\Models\Edisi;
use App\Services\KlasemenService;
use Filament\Widgets\Widget;

/** Klasemen top 5 edisi aktif (dari KlasemenService). */
class KlasemenTopWidget extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.klasemen-top';

    protected function getViewData(): array
    {
        $edisi = Edisi::pilih();

        return [
            'edisi' => $edisi,
            'baris' => $edisi ? app(KlasemenService::class)->klasemen($edisi)->take(5) : collect(),
        ];
    }
}
