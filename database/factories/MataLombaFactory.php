<?php

namespace Database\Factories;

use App\Enums\Jenjang;
use App\Enums\Kategori;
use App\Models\Cabor;
use App\Models\MataLomba;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MataLomba> */
class MataLombaFactory extends Factory
{
    protected $model = MataLomba::class;

    public function definition(): array
    {
        return [
            'cabor_id' => Cabor::factory(),
            'nama' => ucwords($this->faker->unique()->words(2, true)),
            'jenjang' => $this->faker->randomElement(Jenjang::cases()),
            'kategori' => $this->faker->randomElement(Kategori::cases()),
            'kuota_juara_1' => 1,
            'kuota_juara_2' => 2,
            'kuota_juara_3' => 3,
            'urutan' => 0,
            'is_aktif' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(['is_aktif' => false]);
    }
}
