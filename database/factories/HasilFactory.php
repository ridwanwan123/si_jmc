<?php

namespace Database\Factories;

use App\Models\Hasil;
use App\Models\Lomba;
use App\Models\Madrasah;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Hasil> */
class HasilFactory extends Factory
{
    protected $model = Hasil::class;

    public function definition(): array
    {
        return [
            'lomba_id' => Lomba::factory(),
            'peringkat' => 1,
            'madrasah_id' => Madrasah::factory(),
            'nama_peserta' => $this->faker->name(),
            'urutan' => 0,
        ];
    }

    public function emas(): static
    {
        return $this->state(['peringkat' => 1]);
    }

    public function perak(): static
    {
        return $this->state(['peringkat' => 2]);
    }

    public function perunggu(): static
    {
        return $this->state(['peringkat' => 3]);
    }
}
