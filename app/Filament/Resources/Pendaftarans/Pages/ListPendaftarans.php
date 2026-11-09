<?php

namespace App\Filament\Resources\Pendaftarans\Pages;

use App\Enums\StatusPendaftaran;
use App\Filament\Resources\Pendaftarans\PendaftaranResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPendaftarans extends ListRecords
{
    protected static string $resource = PendaftaranResource::class;

    public function getTabs(): array
    {
        $tab = ['semua' => Tab::make('Semua')];

        foreach (StatusPendaftaran::cases() as $s) {
            $tab[$s->value] = Tab::make($s->label())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $s->value));
        }

        return $tab;
    }
}
