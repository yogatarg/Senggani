<?php

namespace Database\Factories;

use App\Models\KategoriProduk;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KategoriProduk>
 */
class KategoriProdukFactory extends Factory
{
    protected $model = KategoriProduk::class;

    public function definition(): array
    {
        $nama = fake()->unique()->words(2, true);

        return [
            'nama_kategori' => Str::title($nama),
            'slug'          => Str::slug($nama),
            'deskripsi'     => fake()->sentence(),
            'gambar'        => null,
        ];
    }
}
