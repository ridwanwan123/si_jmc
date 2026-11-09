<?php

namespace App\Models;

use App\Services\GaleriService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GaleriAlbum extends Model
{
    use HasFactory;

    protected $table = 'galeri_album';

    protected $fillable = ['edisi_id', 'cabor_id', 'lomba_id', 'judul', 'slug', 'tanggal', 'sampul_foto_id'];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    protected static function booted(): void
    {
        // Hapus file foto sebelum baris foto ikut terhapus (cascade di database).
        static::deleting(fn (GaleriAlbum $album) => app(GaleriService::class)->hapusFolderAlbum($album));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Album yang punya minimal satu foto (yang tampil di halaman publik). */
    public function scopeAdaFoto(Builder $query): Builder
    {
        return $query->whereHas('foto');
    }

    /** Label kecil di atas judul, mis. "OLAHRAGA" atau "UMUM". */
    public function labelCabor(): string
    {
        return mb_strtoupper($this->cabor?->nama ?? 'Umum');
    }

    public function edisi(): BelongsTo
    {
        return $this->belongsTo(Edisi::class);
    }

    /** Null = album umum (mis. pembukaan). */
    public function cabor(): BelongsTo
    {
        return $this->belongsTo(Cabor::class);
    }

    public function lomba(): BelongsTo
    {
        return $this->belongsTo(Lomba::class);
    }

    public function foto(): HasMany
    {
        return $this->hasMany(GaleriFoto::class, 'album_id')->orderBy('urutan')->orderBy('id');
    }

    public function sampul(): BelongsTo
    {
        return $this->belongsTo(GaleriFoto::class, 'sampul_foto_id');
    }
}
