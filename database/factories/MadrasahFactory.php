<?php

namespace Database\Factories;

use App\Enums\Jenjang;
use App\Enums\StatusMadrasah;
use App\Enums\Kota;
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
            'npsn' => (string) $this->faker->unique()->numerify('6#######'),
            'nama' => "{$prefix} ".$this->faker->unique()->numberBetween(1, 99999).' Jakarta',
            'jenjang' => $jenjang,
            'status' => $status,
            'provinsi' => 'DKI Jakarta',
            'kota' => $this->faker->randomElement(Kota::cases()),
            'kecamatan' => null,
            'kelurahan' => null,
            'alamat_sekolah' => null,
            'logo' => null,
        ];
    }

    public function jenjang(Jenjang $jenjang): static
    {
        return $this->state(['jenjang' => $jenjang]);
    }
}
