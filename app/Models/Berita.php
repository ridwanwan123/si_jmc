<?php

namespace App\Models;

use App\Enums\KategoriBerita;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Berita extends Model
{
    use HasFactory;

    protected $table = 'berita';

    /** Tag HTML yang boleh tampil di isi berita (sisanya dibuang). */
    public const TAG_ISI = '<p><br><h2><h3><h4><strong><b><em><i><u><s><a><ul><ol><li><blockquote><cite><figure><figcaption><img><table><thead><tbody><tr><th><td><hr>';

    protected $fillable = [
        'edisi_id', 'cabor_id', 'kategori', 'judul', 'slug', 'ringkasan', 'isi',
        'sampul', 'keterangan_sampul', 'penulis', 'tag', 'unggulan', 'sematkan_di_pita', 'terbit_pada',
    ];

    protected function casts(): array
    {
        return [
            'kategori' => KategoriBerita::class,
            'tag' => 'array',
            'unggulan' => 'boolean',
            'sematkan_di_pita' => 'boolean',
            'terbit_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Unggulan dan pita: masing-masing hanya satu per edisi. Menandai yang baru melepas yang lama.
        static::saved(function (Berita $b) {
            foreach (['unggulan', 'sematkan_di_pita'] as $kolom) {
                if ($b->{$kolom} && ($b->wasRecentlyCreated || $b->wasChanged($kolom))) {
                    static::query()->where('edisi_id', $b->edisi_id)->whereKeyNot($b->id)->where($kolom, true)
                        ->update([$kolom => false]);
                }
            }
        });

        // File sampul ikut dihapus bersama berita.
        static::deleted(function (Berita $b) {
            if ($b->sampul) {
                Storage::disk('public')->delete($b->sampul);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** "draf" (belum ada tanggal terbit), "terjadwal" (terbit nanti), atau "terbit". */
    public function status(): string
    {
        return match (true) {
            $this->terbit_pada === null => 'draf',
            $this->terbit_pada->isFuture() => 'terjadwal',
            default => 'terbit',
        };
    }

    /** Hanya berita yang sudah terbit (bukan draf, bukan terjadwal di masa depan). */
    public function scopeTerbit(Builder $query): Builder
    {
        return $query->whereNotNull('terbit_pada')->where('terbit_pada', '<=', now());
    }

    public function scopeTerbaru(Builder $query): Builder
    {
        return $query->orderByDesc('terbit_pada')->orderByDesc('id');
    }

    /** Tanggal terbit di zona Jakarta, mis. "02 NOV 2026". */
    public function tanggal(string $format = 'd M Y'): string
    {
        return mb_strtoupper($this->terbit_pada?->setTimezone(config('jmc.timezone'))->translatedFormat($format) ?? '');
    }

    public function penulisTampil(): string
    {
        return $this->penulis ?: 'Panitia '.($this->edisi?->nama ?? 'JMC');
    }

    /** Perkiraan waktu baca (200 kata per menit, minimal 1). */
    public function menitBaca(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($this->ringkasan.' '.$this->isi)) / 200));
    }

    /** Isi berita yang aman ditampilkan: hanya tag tertentu, tanpa atribut event/javascript:. */
    public function isiAman(): string
    {
        $html = strip_tags((string) $this->isi, self::TAG_ISI);
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/(href|src)\s*=\s*(["\']?)\s*(javascript|data|vbscript):[^"\'\s>]*\2/i', '$1="#"', $html);

        return (string) $html;
    }

    public function urlSampul(): ?string
    {
        return $this->sampul ? Storage::disk('public')->url($this->sampul) : null;
    }

    /** Ikon placeholder saat tidak ada foto: ikon cabor, atau ikon gambar. */
    public function ikonPlaceholder(): string
    {
        return $this->cabor?->ikon ?? 'gambar';
    }

    public static function slugUnik(string $judul): string
    {
        $dasar = Str::slug($judul) ?: 'berita';
        $slug = $dasar;
        for ($i = 2; static::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$dasar}-{$i}";
        }

        return $slug;
    }

    public function edisi(): BelongsTo
    {
        return $this->belongsTo(Edisi::class);
    }

    public function cabor(): BelongsTo
    {
        return $this->belongsTo(Cabor::class);
    }
}
