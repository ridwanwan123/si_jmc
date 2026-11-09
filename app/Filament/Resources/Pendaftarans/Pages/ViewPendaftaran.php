<?php

namespace App\Filament\Resources\Pendaftarans\Pages;

use App\Filament\Pages\KelolaCabor;
use App\Filament\Resources\Pendaftarans\PendaftaranResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPendaftaran extends ViewRecord
{
    protected static string $resource = PendaftaranResource::class;

    public function getBreadcrumbs(): array
    {
        $lomba = $this->getRecord()->lomba;

        return [
            KelolaCabor::getUrl(['cabor' => $lomba->cabor->slug, 'tab' => 'peserta']) => $lomba->cabor->nama.' · Peserta',
            $this->getRecord()->nomor,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            PendaftaranResource::unduhSuratAction(),
            PendaftaranResource::verifikasiAction(),
            PendaftaranResource::tolakAction(),
        ];
    }
}
