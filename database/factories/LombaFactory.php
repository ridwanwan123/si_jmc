<?php

namespace Database\Factories;

use App\Enums\Jenjang;
use App\Enums\Kategori;
use App\Models\Cabor;
use App\Models\Edisi;
use App\Models\Lomba;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lomba> */
class LombaFactory extends Factory
{
    protected $model = Lomba::class;

    public function definition(): array
    {
        return [
            'edisi_id' => Edisi::factory(),
            'cabor_id' => Cabor::factory(),
            'nama' => ucwords($this->faker->words(2, true)),
            'jenjang' => $this->faker->randomElement(Jenjang::cases()),
            'kategori' => $this->faker->randomElement(Kategori::cases()),
            'kuota_juara_1' => 1,
            'kuota_juara_2' => 2,
            'kuota_juara_3' => 3,
            'urutan' => 0,
        ];
    }

    public function beregu(): static
    {
        return $this->state(['kategori' => Kategori::Beregu]);
    }

    public function kuota(int $j1, int $j2, int $j3): static
    {
        return $this->state(['kuota_juara_1' => $j1, 'kuota_juara_2' => $j2, 'kuota_juara_3' => $j3]);
    }
}
