<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan aplikasi berbentuk kunci–nilai (nilai JSON). Dipakai pertama kali untuk
 * Pengaturan Invoice (App\Services\PengaturanInvoice). Nilai awal invoice diisi di sini.
 * Nomor rekening memang dicetak di invoice (bukan rahasia). File ttd/stempel/QRIS tidak
 * di-seed: diunggah admin lewat halaman Pengaturan Invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 100)->unique();
            $table->json('nilai')->nullable();
            $table->timestamps();
        });

        $sekarang = now();
        $awal = [
            'invoice.penandatangan_nama'    => 'Putri Maulidina',
            'invoice.penandatangan_jabatan' => '',
            'invoice.rekening'              => [
                ['bank' => 'BCA', 'nomor' => '8023011164', 'atas_nama' => 'Herni Pri Nugrahani'],
                ['bank' => 'BRI', 'nomor' => '300801017388537', 'atas_nama' => 'Herni Pri Nugrahani'],
            ],
            'invoice.keterangan_bawah' => 'DP minimal 50%. Pengiriman barang setelah pelunasan.',
            'invoice.persen_dp'        => 50,
            'invoice.qris_keterangan'  => 'QRIS a.n. SENGGANI - FOTOCOPY (satu usaha).',
            'invoice.file_ttd'         => null,
            'invoice.file_stempel'     => null,
            'invoice.file_qris'        => null,
        ];

        DB::table('pengaturan')->insert(array_map(fn ($kunci, $nilai) => [
            'kunci'      => $kunci,
            'nilai'      => json_encode($nilai, JSON_UNESCAPED_UNICODE),
            'created_at' => $sekarang,
            'updated_at' => $sekarang,
        ], array_keys($awal), $awal));
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
    }
};
