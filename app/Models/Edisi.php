<?php

namespace App\Models;

use App\Enums\Tahap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Edisi extends Model
{
    use HasFactory;

    protected $table = 'edisi';

    /** Cache per instance untuk tahap(); bukan kolom database. */
    private ?bool $adaHasil = null;

    protected $fillable = [
        'tahun',
        'nama',
        'tanggal_mulai',
        'tanggal_selesai',
        'lokasi',
        'pendaftaran_buka',
        'pendaftaran_tutup',
        'is_aktif',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'pendaftaran_buka' => 'date',
            'pendaftaran_tutup' => 'date',
            'is_aktif' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Album galeri ikut terhapus (cascade); hapus juga file fotonya.
        static::deleting(function (Edisi $edisi) {
            $edisi->galeriAlbum()->get()->each(fn (GaleriAlbum $album) => $album->delete());
        });

        // Hanya satu edisi yang boleh aktif.
        static::saved(function (Edisi $edisi) {
            if ($edisi->is_aktif) {
                static::query()
                    ->whereKeyNot($edisi->getKey())
                    ->where('is_aktif', true)
                    ->update(['is_aktif' => false]);
            }
        });
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_aktif', true);
    }

    /**
     * Status berdasarkan tanggal (WIB): 'AKAN DATANG', 'BERLANGSUNG', 'SELESAI',
     * atau null jika tanggal belum diisi.
     */
    public function status(): ?string
    {
        if (! $this->tanggal_mulai) {
            return null;
        }

        $hariIni = now(config('jmc.timezone'))->startOfDay();
        $selesai = $this->tanggal_selesai ?? $this->tanggal_mulai;

        return match (true) {
            $hariIni->lt($this->tanggal_mulai->copy()->startOfDay()) => 'AKAN DATANG',
            $hariIni->gt($selesai->copy()->startOfDay()) => 'SELESAI',
            default => 'BERLANGSUNG',
        };
    }

    /** Hasil sudah final: edisi selesai, atau edisi arsip tanpa tanggal. */
    public function selesai(): bool
    {
        return $this->status() === 'SELESAI' || ($this->status() === null && ! $this->is_aktif);
    }

    /**
     * Tahap edisi:
     * - Klasemen: sudah ada hasil.
     * - Registrasi: hari ini belum lewat tanggal tutup pendaftaran
     *   (jika tanggal tutup kosong: belum masuk tanggal lomba).
     * - Tanding: selain itu.
     */
    public function tahap(): Tahap
    {
        if ($this->exists && ($this->adaHasil ??= $this->hasil()->exists())) {
            return Tahap::Klasemen;
        }

        $hariIni = now(config('jmc.timezone'))->startOfDay();

        if ($this->pendaftaran_tutup) {
            return $hariIni->lte($this->pendaftaran_tutup->copy()->startOfDay()) ? Tahap::Registrasi : Tahap::Tanding;
        }

        return in_array($this->status(), ['BERLANGSUNG', 'SELESAI'], true) ? Tahap::Tanding : Tahap::Registrasi;
    }

    /** Formulir /daftar menerima pendaftaran untuk edisi ini. */
    public function pendaftaranDibuka(): bool
    {
        if (! $this->exists || $this->tahap() !== Tahap::Registrasi) {
            return false;
        }

        return ! $this->pendaftaran_buka
            || now(config('jmc.timezone'))->startOfDay()->gte($this->pendaftaran_buka->copy()->startOfDay());
    }

    /** Label tanggal pendaftaran, mis. "S.D. 25.10.2026". */
    public function labelTutupPendaftaran(): ?string
    {
        return $this->pendaftaran_tutup ? 'S.D. '.$this->pendaftaran_tutup->format('d.m.Y') : null;
    }

    /** Pengganti saat belum ada edisi di database: halaman publik tetap tampil dalam keadaan kosong. */
    public static function sementara(): self
    {
        $tahun = (int) now(config('jmc.timezone'))->format('Y');

        $edisi = new self(['tahun' => $tahun, 'nama' => 'JMC '.$tahun, 'is_aktif' => true]);
        $edisi->setAttribute('lomba_count', 0);

        return $edisi;
    }

    /** Edisi aktif, atau edisi dengan tahun tertentu jika diberikan (untuk ?edisi=2025). */
    public static function pilih(?int $tahun = null): ?self
    {
        if ($tahun) {
            return static::query()->where('tahun', $tahun)->first();
        }

        return static::query()->aktif()->first()
            ?? static::query()->orderByDesc('tahun')->first();
    }

    public function lomba(): HasMany
    {
        return $this->hasMany(Lomba::class);
    }

    public function hasil(): HasManyThrough
    {
        return $this->hasManyThrough(Hasil::class, Lomba::class);
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(Pendaftaran::class);
    }

    public function berita(): HasMany
    {
        return $this->hasMany(Berita::class);
    }

    public function galeriAlbum(): HasMany
    {
        return $this->hasMany(GaleriAlbum::class);
    }
}
