<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Pesanan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pesanan>
 */
class PesananFactory extends Factory
{
    protected $model = Pesanan::class;

    public function definition(): array
    {
        static $urut = 0;
        $urut++;

        return [
            'nomor_spp'       => 900000 + $urut, // jauh dari nomor yang diketik di tes
            'customer_id'     => Customer::factory(),
            'nama_customer'   => fake()->name(),
            'nomor_whatsapp'  => '6281234567' . str_pad((string) ($urut % 100), 2, '0', STR_PAD_LEFT),
            'alamat'          => fake()->streetAddress(),
            'kota'            => fake()->city(),
            'tanggal_order'   => today(),
            'deadline'        => today()->addDays(10),
            'jenis_deadline'  => 'kirim',
            'total'           => 0,
            'dp'              => 0,
            'status_produksi' => 'antrian',
            'status_desain'   => 'belum_dikirim',
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn () => [
            'status_produksi' => $status,
            'selesai_pada'    => in_array($status, ['selesai', 'dibatalkan'], true) ? now() : null,
        ]);
    }
}
