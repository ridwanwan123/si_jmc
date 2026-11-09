<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cabor extends Model
{
    use HasFactory;

    protected $table = 'cabor';

    protected $fillable = ['nama', 'slug', 'ikon', 'urutan'];

    /** Ikon garis yang tersedia di komponen x-ikon (nilai kolom `ikon`). */
    public const IKON = [
        'bulan' => 'Bulan sabit',
        'tabung' => 'Tabung reaksi',
        'bola' => 'Bola',
        'palet' => 'Palet',
        'kaca-pembesar' => 'Kaca pembesar',
        'robot' => 'Robot',
        'toga' => 'Toga',
    ];

    protected function casts(): array
    {
        return ['urutan' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Nomor tampil 01–07. */
    public function nomor(): string
    {
        return str_pad((string) $this->urutan, 2, '0', STR_PAD_LEFT);
    }

    public function lomba(): HasMany
    {
        return $this->hasMany(Lomba::class);
    }

    public function mataLomba(): HasMany
    {
        return $this->hasMany(MataLomba::class);
    }

    public function galeriAlbum(): HasMany
    {
        return $this->hasMany(GaleriAlbum::class);
    }
}
