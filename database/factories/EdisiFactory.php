<?php

namespace Database\Factories;

use App\Models\Edisi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Edisi> */
class EdisiFactory extends Factory
{
    protected $model = Edisi::class;

    public function definition(): array
    {
        // Di luar rentang tahun "nyata" agar tidak bentrok dengan tahun yang dibuat eksplisit
        // di test (mis. 2025/2026) — kolom tahun unik.
        $tahun = $this->faker->unique()->numberBetween(3000, 9999);

        return [
            'tahun' => $tahun,
            'nama' => "JMC {$tahun}",
            'tanggal_mulai' => "{$tahun}-11-03",
            'tanggal_selesai' => "{$tahun}-11-07",
            'lokasi' => 'Jakarta',
            'is_aktif' => false,
        ];
    }

    public function aktif(): static
    {
        return $this->state(['is_aktif' => true]);
    }
}
