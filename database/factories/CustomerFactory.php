<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'nama'           => fake()->name(),
            'nomor_whatsapp' => fake()->unique()->numerify('62857########'),
            'alamat'         => fake()->streetAddress(),
            'kota'           => fake()->city(),
        ];
    }
}
