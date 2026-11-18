<?php

namespace Database\Factories;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    public function definition(): array
    {
        return [
            'nama'     => fake()->name(),
            'email'    => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'peran'    => 'admin',
            'aktif'    => true,
        ];
    }

    public function produksi(): static
    {
        return $this->state(fn () => ['peran' => 'produksi']);
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['aktif' => false]);
    }
}
