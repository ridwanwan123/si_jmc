<?php

namespace App\Models;

use App\Enums\Jenjang;
use App\Enums\Kategori;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master mata lomba: katalog lomba tetap, mis. "MTQ · MA · Putra" di cabor Keagamaan.
 * Lomba per edisi dibuat dari master ini (LombaService), bukan diketik bebas.
 */
class MataLomba extends Model
{
    use HasFactory;

    protected $table = 'mata_lomba';

    protected $fillable = [
        'cabor_id', 'pengelola_cabor_id', 'nama', 'jenjang', 'kategori', 'jumlah_anggota',
        'kuota_juara_1', 'kuota_juara_2', 'kuota_juara_3',
        'urutan', 'is_aktif',
    ];

    protected $attributes = [
        'jumlah_anggota' => 1,
        'kuota_juara_1' => 1,
        'kuota_juara_2' => 2,
        'kuota_juara_3' => 3,
        'urutan' => 0,
        'is_aktif' => true,
    ];

    protected function casts(): array
    {
        return [
            'jenjang' => Jenjang::class,
            'kategori' => Kategori::class,
            'cabor_id' => 'integer',
            'pengelola_cabor_id' => 'integer',
            'jumlah_anggota' => 'integer',
            'kuota_juara_1' => 'integer',
            'kuota_juara_2' => 'integer',
            'kuota_juara_3' => 'integer',
            'urutan' => 'integer',
            'is_aktif' => 'boolean',
        ];
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_aktif', true);
    }

    /** Urutan tampil: cabor 01–07, lalu jenjang RA → MA, lalu urutan/nama. */
    public function scopeTerurut(Builder $query): Builder
    {
        return $query
            ->join('cabor', 'cabor.id', '=', 'mata_lomba.cabor_id')
            ->orderBy('cabor.urutan')
            ->orderByRaw("FIELD(mata_lomba.jenjang, 'RA', 'MI', 'MTs', 'MA')")
            ->orderBy('mata_lomba.urutan')
            ->orderBy('mata_lomba.nama')
            ->select('mata_lomba.*');
    }

    /** "MTQ · MA · Putra" */
    public function namaLengkap(): string
    {
        return "{$this->nama} · {$this->jenjang->value} · {$this->kategori->value}";
    }

    /** "Keagamaan — MTQ · MA · Putra" (untuk pilihan di admin). */
    public function label(): string
    {
        return ($this->cabor?->nama ?? '?').' — '.$this->namaLengkap();
    }

    protected static function booted(): void
    {
        // Pengelola = cabor yang admin-nya mengurus mata lomba ini. Selain GTK selalu cabor sendiri;
        // GTK boleh dikelola cabor lain (mis. Badminton GTK → Olahraga), kosong = tebak dari nama.
        static::saving(function (MataLomba $m) {
            if (! static::caborGtk($m->cabor_id)) {
                $m->pengelola_cabor_id = $m->cabor_id;
            } elseif (! $m->pengelola_cabor_id) {
                $m->pengelola_cabor_id = static::tebakPengelolaGtk($m->nama) ?? $m->cabor_id;
            }
        });

        // Pengelola menentukan siapa yang mengurus (bukan data historis): ikut diperbarui di semua lomba.
        static::saved(function (MataLomba $m) {
            if ($m->wasChanged('pengelola_cabor_id')) {
                $m->lomba()->update(['pengelola_cabor_id' => $m->pengelola_cabor_id]);
            }
        });
    }

    private static function caborGtk(?int $caborId): bool
    {
        return $caborId !== null && Cabor::query()->whereKey($caborId)->value('slug') === 'gtk';
    }

    /** Tebakan cabor pengelola untuk mata lomba GTK dari namanya; null jika tidak dikenali. */
    public static function tebakPengelolaGtk(string $nama): ?int
    {
        $n = mb_strtolower($nama);
        $slug = match (true) {
            str_contains($n, 'badminton'), str_contains($n, 'bulu tangkis'), str_contains($n, 'tenis'),
            str_contains($n, 'catur'), str_contains($n, 'futsal'), str_contains($n, 'voli') => 'olahraga',
            str_contains($n, 'mtq'), str_contains($n, 'mhq'), str_contains($n, 'kultum'), str_contains($n, 'azan'),
            str_contains($n, 'adzan'), str_contains($n, 'ceramah'), str_contains($n, 'khutbah') => 'keagamaan',
            str_contains($n, 'riset'), str_contains($n, 'inovasi') => 'riset',
            str_contains($n, 'dirigen'), str_contains($n, 'kaligrafi'), str_contains($n, 'nasyid'), str_contains($n, 'lukis') => 'seni',
            str_contains($n, 'robot') => 'robotik',
            str_contains($n, 'olimpiade'), str_contains($n, 'sains') => 'sains',
            default => null,
        };

        return $slug ? Cabor::query()->where('slug', $slug)->value('id') : null;
    }

    public function cabor(): BelongsTo
    {
        return $this->belongsTo(Cabor::class);
    }

    /** Cabor yang admin-nya mengurus mata lomba ini. */
    public function pengelola(): BelongsTo
    {
        return $this->belongsTo(Cabor::class, 'pengelola_cabor_id');
    }

    public function lomba(): HasMany
    {
        return $this->hasMany(Lomba::class);
    }
}
