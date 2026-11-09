<?php

namespace Database\Factories;

use App\Models\GaleriAlbum;
use App\Models\GaleriFoto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<GaleriFoto> */
class GaleriFotoFactory extends Factory
{
    protected $model = GaleriFoto::class;

    public function definition(): array
    {
        $nama = Str::uuid();

        return [
            'album_id' => GaleriAlbum::factory(),
            'path' => "galeri/{$nama}.webp",
            'thumb_path' => "galeri/thumb/{$nama}.webp",
            'keterangan' => $this->faker->optional()->sentence(),
            'urutan' => 0,
        ];
    }
}
