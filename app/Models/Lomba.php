<?php

namespace App\Models;

use App\Enums\Jenjang;
use App\Enums\Kategori;
use App\Enums\Medali;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lomba extends Model
{
    use HasFactory;

    protected $table = 'lomba';

    protected $fillable = [
        'edisi_id',
        'cabor_id',
        'pengelola_cabor_id',
        'mata_lomba_id',
        'nama',
        'jenjang',
        'kategori',
        'jumlah_anggota',
        'kuota_juara_1',
        'kuota_juara_2',
        'kuota_juara_3',
        'urutan',
    ];

    protected $attributes = [
        'jumlah_anggota' => 1,
        'kuota_juara_1' => 1,
        'kuota_juara_2' => 2,
        'kuota_juara_3' => 3,
        'urutan' => 0,
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
        ];
    }

    protected static function booted(): void
    {
        // Setiap lomba terhubung ke master mata lomba.
        static::creating(function (Lomba $lomba) {
            if ($lomba->mata_lomba_id) {
                // Snapshot nama/jenjang/kategori/cabor diambil dari master.
                $master = MataLomba::findOrFail($lomba->mata_lomba_id);
                $lomba->cabor_id = $master->cabor_id;
                $lomba->pengelola_cabor_id = $master->pengelola_cabor_id;
                $lomba->nama = $master->nama;
                $lomba->jenjang = $master->jenjang;
                $lomba->kategori = $master->kategori;
                if ((int) $lomba->jumlah_anggota === 1) { // default → ikut master
                    $lomba->jumlah_anggota = $master->jumlah_anggota;
                }

                return;
            }

            // Jalur lama (seeder/test/kode internal) tanpa master: cari atau buat master yang cocok.
            $lomba->mata_lomba_id = MataLomba::firstOrCreate([
                'cabor_id' => $lomba->cabor_id,
                'nama' => $lomba->nama,
                'jenjang' => $lomba->jenjang instanceof Jenjang ? $lomba->jenjang->value : $lomba->jenjang,
                'kategori' => $lomba->kategori instanceof Kategori ? $lomba->kategori->value : $lomba->kategori,
            ], [
                'jumlah_anggota' => $lomba->jumlah_anggota ?? 1,
                'kuota_juara_1' => $lomba->kuota_juara_1 ?? 1,
                'kuota_juara_2' => $lomba->kuota_juara_2 ?? 2,
                'kuota_juara_3' => $lomba->kuota_juara_3 ?? 3,
                'urutan' => $lomba->urutan ?? 0,
            ])->id;
            $lomba->pengelola_cabor_id ??= MataLomba::query()->whereKey($lomba->mata_lomba_id)->value('pengelola_cabor_id');
        });
    }

    /** Kuota juara untuk peringkat 1/2/3. */
    public function kuota(int|Medali $peringkat): int
    {
        $p = $peringkat instanceof Medali ? $peringkat->value : $peringkat;

        return match ($p) {
            1 => $this->kuota_juara_1,
            2 => $this->kuota_juara_2,
            3 => $this->kuota_juara_3,
            default => throw new \InvalidArgumentException("Peringkat tidak valid: {$p}"),
        };
    }

    /** Nama lengkap, mis. "Tenis Meja · MTs · Putri". */
    public function namaLengkap(): string
    {
        return "{$this->nama} · {$this->jenjang->value} · {$this->kategori->value}";
    }

    public function edisi(): BelongsTo
    {
        return $this->belongsTo(Edisi::class);
    }

    public function cabor(): BelongsTo
    {
        return $this->belongsTo(Cabor::class);
    }

    /** Cabor yang admin-nya mengurus lomba ini (lomba GTK bisa dikelola cabor lain). */
    public function pengelola(): BelongsTo
    {
        return $this->belongsTo(Cabor::class, 'pengelola_cabor_id');
    }

    /**
     * Lomba di halaman kerja satu cabor: lomba cabor itu sendiri, ditambah lomba GTK
     * yang dikelola cabor itu (mis. halaman Olahraga memuat Badminton GTK).
     */
    public function scopeUntukCabor(Builder $query, int $caborId): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('lomba.cabor_id', $caborId)->orWhere('lomba.pengelola_cabor_id', $caborId));
    }

    /** Lomba yang boleh dikelola user (superadmin: semua; admin cabor: cabornya). */
    public function scopeBolehDikelola(Builder $query, ?User $user): Builder
    {
        return match (true) {
            (bool) $user?->isSuperadmin() => $query,
            $user?->isAdminCabor() && $user->cabor_id !== null => $query->untukCabor($user->cabor_id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function mataLomba(): BelongsTo
    {
        return $this->belongsTo(MataLomba::class);
    }

    public function hasil(): HasMany
    {
        // chaperone(): $hasil->lomba terisi otomatis tanpa query tambahan (dipakai Hasil::anggota()).
        return $this->hasMany(Hasil::class)->orderBy('peringkat')->orderBy('urutan')->chaperone();
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(Pendaftaran::class);
    }

    /** Lomba tim (maksimal anggota lebih dari satu). */
    public function tim(): bool
    {
        return $this->jumlah_anggota > 1;
    }

    /** "Perorangan" atau "Tim maks. 12 orang". */
    public function labelAnggota(): string
    {
        return $this->tim() ? "Tim maks. {$this->jumlah_anggota} orang" : 'Perorangan';
    }

    public function galeriAlbum(): HasMany
    {
        return $this->hasMany(GaleriAlbum::class);
    }
}
