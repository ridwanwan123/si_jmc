<?php

namespace App\Models;

use App\Enums\Kategori;
use App\Enums\Medali;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu juara = satu medali untuk madrasahnya.
 */
class Hasil extends Model
{
    use HasFactory;

    protected $table = 'hasil';

    protected $fillable = ['lomba_id', 'peringkat', 'madrasah_id', 'pendaftaran_id', 'nama_peserta', 'urutan'];

    protected function casts(): array
    {
        return [
            'peringkat' => 'integer',
            'urutan' => 'integer',
        ];
    }

    public function medali(): Medali
    {
        return Medali::from($this->peringkat);
    }

    /**
     * Nama peserta, satu elemen per orang (untuk tim: satu per anggota).
     *
     * Pemisah: baris baru atau titik koma; jika tidak ada, koma. Gelar setelah koma
     * (mis. "Budi Santoso, S.Pd.") tetap menempel pada nama sebelumnya.
     *
     * @return list<string>
     */
    public function anggota(): array
    {
        $nama = trim((string) $this->nama_peserta);

        if ($nama === '') {
            return [];
        }

        if (preg_match('/[\r\n;]/', $nama)) {
            return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|;/', $nama))));
        }

        $hasil = [];
        foreach (array_filter(array_map('trim', explode(',', $nama))) as $bagian) {
            // Gelar: tanpa spasi, mengandung titik, pendek (S.Pd., M.Ag., Lc., S.Pd.I).
            $gelar = ! str_contains($bagian, ' ') && str_contains($bagian, '.') && mb_strlen($bagian) <= 8;

            if ($gelar && $hasil) {
                $hasil[count($hasil) - 1] .= ', '.$bagian;
            } else {
                $hasil[] = $bagian;
            }
        }

        return $hasil;
    }

    /** Juara berupa tim (lomba beregu atau lebih dari satu nama), ditampilkan sebagai TIM · n ANGGOTA. */
    public function tim(): bool
    {
        return $this->lomba?->kategori === Kategori::Beregu
            || ($this->lomba?->jumlah_anggota ?? 1) > 1
            || count($this->anggota()) > 1;
    }

    public function scopeEdisi(Builder $query, Edisi|int $edisi): Builder
    {
        $id = $edisi instanceof Edisi ? $edisi->getKey() : $edisi;

        return $query->whereHas('lomba', fn (Builder $q) => $q->where('edisi_id', $id));
    }

    public function lomba(): BelongsTo
    {
        return $this->belongsTo(Lomba::class);
    }

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(Pendaftaran::class);
    }

    public function madrasah(): BelongsTo
    {
        return $this->belongsTo(Madrasah::class);
    }
}
