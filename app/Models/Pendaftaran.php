<?php

namespace App\Models;

use App\Enums\StatusPendaftaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Satu pendaftaran = satu peserta atau satu tim dari satu madrasah di satu lomba.
 * Dibuat lewat PendaftaranService (form /daftar).
 */
class Pendaftaran extends Model
{
    use HasFactory;

    protected $table = 'pendaftaran';

    /** Disk privat untuk surat tugas (tidak bisa diakses publik). */
    public const DISK = 'local';

    protected $fillable = [
        'edisi_id', 'lomba_id', 'madrasah_id', 'nomor',
        'guru_nama', 'guru_wa', 'surat_tugas_path',
        'status', 'catatan', 'diverifikasi_pada',
    ];

    protected $attributes = [
        'status' => 'menunggu',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPendaftaran::class,
            'diverifikasi_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Pendaftaran $p) {
            if ($p->surat_tugas_path) {
                Storage::disk(self::DISK)->delete($p->surat_tugas_path);
            }
        });
    }

    public function scopeDiverifikasi(Builder $query): Builder
    {
        return $query->where('status', StatusPendaftaran::Diverifikasi->value);
    }

    /** Nama peserta satu per baris (untuk hasil.nama_peserta). */
    public function namaPeserta(): string
    {
        return $this->peserta->pluck('nama')->implode("\n");
    }

    /** "MTsN 3 Jakarta — Rizky Pratama, Annisa Rahma" (pilihan juara di admin). */
    public function label(): string
    {
        return $this->madrasah->nama.' — '.$this->peserta->pluck('nama')->implode(', ');
    }

    public function edisi(): BelongsTo
    {
        return $this->belongsTo(Edisi::class);
    }

    public function lomba(): BelongsTo
    {
        return $this->belongsTo(Lomba::class);
    }

    public function madrasah(): BelongsTo
    {
        return $this->belongsTo(Madrasah::class);
    }

    public function peserta(): HasMany
    {
        return $this->hasMany(PendaftaranPeserta::class)->orderBy('urutan');
    }

    public function hasil(): HasMany
    {
        return $this->hasMany(Hasil::class);
    }
}
