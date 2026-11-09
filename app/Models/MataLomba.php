<?php

namespace App\Models;

use App\Enums\Jenjang;
use App\Enums\Kategori;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Master mata lomba: katalog lomba tetap, mis. "MTQ · MA · Putra" di cabor Keagamaan.
 * Lomba per edisi dibuat dari master ini (LombaService), bukan diketik bebas.
 */
class MataLomba extends Model
{
    use HasFactory;

    protected $table = 'mata_lomba';

    protected $fillable = [
        'cabor_id', 'nama', 'jenjang', 'kategori', 'jumlah_anggota',
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

    /**
     * Buat master dari lomba yang sudah ada (dipakai migration) dan hubungkan lomba lama.
     * Aman dijalankan ulang.
     */
    public static function isiDariLombaLama(): void
    {
        $kombinasi = DB::table('lomba')
            ->select('cabor_id', 'nama', 'jenjang', 'kategori')
            ->selectRaw('MIN(urutan) AS urutan, MIN(kuota_juara_1) AS k1, MIN(kuota_juara_2) AS k2, MIN(kuota_juara_3) AS k3')
            ->groupBy('cabor_id', 'nama', 'jenjang', 'kategori')
            ->get();

        foreach ($kombinasi as $k) {
            $kunci = ['cabor_id' => $k->cabor_id, 'nama' => $k->nama, 'jenjang' => $k->jenjang, 'kategori' => $k->kategori];

            $id = DB::table('mata_lomba')->where($kunci)->value('id') ?? DB::table('mata_lomba')->insertGetId($kunci + [
                'kuota_juara_1' => $k->k1,
                'kuota_juara_2' => $k->k2,
                'kuota_juara_3' => $k->k3,
                'urutan' => $k->urutan,
                'is_aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('lomba')->where($kunci)->whereNull('mata_lomba_id')->update(['mata_lomba_id' => $id]);
        }
    }

    public function cabor(): BelongsTo
    {
        return $this->belongsTo(Cabor::class);
    }

    public function lomba(): HasMany
    {
        return $this->hasMany(Lomba::class);
    }
}
