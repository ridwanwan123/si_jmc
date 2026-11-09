<?php

namespace Database\Factories;

use App\Models\Cabor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Cabor> */
class CaborFactory extends Factory
{
    protected $model = Cabor::class;

    public function definition(): array
    {
        $nama = ucfirst($this->faker->unique()->word());

        return [
            'nama' => $nama,
            'slug' => Str::slug($nama).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'ikon' => 'trophy',
            'urutan' => $this->faker->numberBetween(1, 7),
        ];
    }
}
