<?php

namespace Database\Factories;

use App\Enums\KategoriBerita;
use App\Models\Berita;
use App\Models\Edisi;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Berita> */
class BeritaFactory extends Factory
{
    protected $model = Berita::class;

    public function definition(): array
    {
        $judul = rtrim($this->faker->unique()->sentence(6), '.');

        return [
            'edisi_id' => Edisi::factory(),
            'cabor_id' => null,
            'kategori' => KategoriBerita::Liputan,
            'judul' => $judul,
            'slug' => Str::slug($judul).'-'.Str::lower(Str::random(4)),
            'ringkasan' => $this->faker->sentence(18),
            'isi' => '<p>'.implode('</p><p>', $this->faker->paragraphs(3)).'</p>',
            'tag' => null,
            'unggulan' => false,
            'sematkan_di_pita' => false,
            'terbit_pada' => now()->subDay(),
        ];
    }

    public function kategori(KategoriBerita $kategori): static
    {
        return $this->state(['kategori' => $kategori]);
    }

    public function draf(): static
    {
        return $this->state(['terbit_pada' => null]);
    }
}
