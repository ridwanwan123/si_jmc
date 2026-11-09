<?php

namespace Database\Factories;

use App\Models\Edisi;
use App\Models\GaleriAlbum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<GaleriAlbum> */
class GaleriAlbumFactory extends Factory
{
    protected $model = GaleriAlbum::class;

    public function definition(): array
    {
        $judul = ucwords($this->faker->words(3, true));

        return [
            'edisi_id' => Edisi::factory(),
            'cabor_id' => null,
            'lomba_id' => null,
            'judul' => $judul,
            'slug' => Str::slug($judul).'-'.Str::lower(Str::random(5)),
            'tanggal' => $this->faker->date(),
            'sampul_foto_id' => null,
        ];
    }
}
