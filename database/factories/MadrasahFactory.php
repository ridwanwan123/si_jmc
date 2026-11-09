<?php

namespace Database\Factories;

use App\Enums\Jenjang;
use App\Enums\StatusMadrasah;
use App\Enums\Wilayah;
use App\Models\Madrasah;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Madrasah> */
class MadrasahFactory extends Factory
{
    protected $model = Madrasah::class;

    public function definition(): array
    {
        $jenjang = $this->faker->randomElement(Jenjang::cases());
        $status = $this->faker->randomElement(StatusMadrasah::cases());
        $prefix = $status === StatusMadrasah::Negeri ? "{$jenjang->value}N" : "{$jenjang->value}S";

        return [
            'nsm' => (string) $this->faker->unique()->numerify('1############'),
            'nama' => "{$prefix} ".$this->faker->unique()->numberBetween(1, 99999).' Jakarta',
            'jenjang' => $jenjang,
            'status' => $status,
            'wilayah' => $this->faker->randomElement(Wilayah::cases()),
            'logo' => null,
        ];
    }

    public function jenjang(Jenjang $jenjang): static
    {
        return $this->state(['jenjang' => $jenjang]);
    }
}
