<?php

namespace App\Models;

use App\Enums\Jenjang;
use App\Enums\StatusMadrasah;
use App\Enums\Kota;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Madrasah extends Model
{
    use HasFactory;

    protected $table = 'madrasah';

    protected $fillable = [
        'npsn', 'nama', 'jenjang', 'status', 'logo',
        'provinsi', 'kota', 'kecamatan', 'kelurahan', 'alamat_sekolah',
    ];

    protected function casts(): array
    {
        return [
            'jenjang' => Jenjang::class,
            'status' => StatusMadrasah::class,
            'kota' => Kota::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'npsn';
    }

    public function hasil(): HasMany
    {
        return $this->hasMany(Hasil::class);
    }
}
