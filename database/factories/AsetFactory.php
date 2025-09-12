<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Aset>
 */
class AsetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lokasiList = [
            'Gudang ATK Lt. 1',
            'Gudang Lt. 3',
            'Gudang Lt. 4',
            'Gudang Lt. 5',
            'Gudang Lt. 6',
            'Gudang RBI Kayu Tangi',
            'Gudang RBI Bumi Asih',
        ];

        return [
            'nama_barang' => $this->faker->word(),
            'jumlah_barang' => $this->faker->numberBetween(1, 100),
            'lokasi' => $this->faker->randomElement($lokasiList),
            'keterangan' => $this->faker->sentence(),
        ];
    }
}
