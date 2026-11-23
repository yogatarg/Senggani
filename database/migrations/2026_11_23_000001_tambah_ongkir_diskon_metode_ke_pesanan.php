<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice: ongkir & diskon (rupiah) ikut total tagihan, serta metode pembayaran DP dan pelunasan
 * (App\Enums\MetodeBayar). Metode boleh null untuk data lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesanan', function (Blueprint $table) {
            $table->decimal('ongkir', 15, 2)->default(0)->after('total');
            $table->decimal('diskon', 15, 2)->default(0)->after('ongkir');
            $table->string('metode_dp', 20)->nullable()->after('tanggal_dp');
            $table->string('metode_lunas', 20)->nullable()->after('tanggal_lunas');
        });
    }

    public function down(): void
    {
        Schema::table('pesanan', function (Blueprint $table) {
            $table->dropColumn(['ongkir', 'diskon', 'metode_dp', 'metode_lunas']);
        });
    }
};
