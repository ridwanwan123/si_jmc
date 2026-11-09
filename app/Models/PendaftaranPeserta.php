<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendaftaranPeserta extends Model
{
    protected $table = 'pendaftaran_peserta';

    protected $fillable = ['pendaftaran_id', 'nama', 'nisn', 'urutan'];

    protected function casts(): array
    {
        return ['urutan' => 'integer'];
    }

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }
}
