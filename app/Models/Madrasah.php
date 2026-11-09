<?php

namespace App\Models;

use App\Enums\Jenjang;
use App\Enums\StatusMadrasah;
use App\Enums\Wilayah;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Madrasah extends Model
{
    use HasFactory;

    protected $table = 'madrasah';

    protected $fillable = ['nsm', 'nama', 'jenjang', 'status', 'wilayah', 'logo'];

    protected function casts(): array
    {
        return [
            'jenjang' => Jenjang::class,
            'status' => StatusMadrasah::class,
            'wilayah' => Wilayah::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'nsm';
    }

    public function hasil(): HasMany
    {
        return $this->hasMany(Hasil::class);
    }
}
