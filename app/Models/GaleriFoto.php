<?php

namespace App\Models;

use App\Services\GaleriService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GaleriFoto extends Model
{
    use HasFactory;

    protected $table = 'galeri_foto';

    protected $fillable = ['album_id', 'path', 'thumb_path', 'keterangan', 'urutan'];

    protected function casts(): array
    {
        return ['urutan' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleted(function (GaleriFoto $foto) {
            app(GaleriService::class)->hapusFile($foto);

            // Jika foto ini sampul, FK di-null-kan oleh database; pindahkan sampul ke foto berikutnya.
            $album = GaleriAlbum::find($foto->album_id);
            if ($album && ! $album->sampul_foto_id) {
                $album->update(['sampul_foto_id' => $album->foto()->value('id')]);
            }
        });
    }

    /**
     * URL foto. Memakai asset() (host request saat ini), bukan Storage::url() yang
     * bergantung pada APP_URL — supaya tetap benar di si_jmc.test maupun artisan serve.
     */
    public function url(): string
    {
        return asset('storage/'.$this->path);
    }

    public function thumbUrl(): string
    {
        return asset('storage/'.$this->thumb_path);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(GaleriAlbum::class, 'album_id');
    }
}
