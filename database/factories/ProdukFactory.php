<?php

namespace Database\Factories;

use App\Models\KategoriProduk;
use App\Models\Produk;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Produk>
 */
class ProdukFactory extends Factory
{
    protected $model = Produk::class;

    public function definition(): array
    {
        $nama = fake()->unique()->words(3, true);

        return [
            'kategori_id'   => KategoriProduk::factory(),
            'nama_produk'   => Str::title($nama),
            'slug'          => Str::slug($nama),
            'deskripsi'     => fake()->paragraph(),
            'minimal_pesan' => 100,
            'gambar'        => null,
            'produk_kustom' => false,
        ];
    }
}
