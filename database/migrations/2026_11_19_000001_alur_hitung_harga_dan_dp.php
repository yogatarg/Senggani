<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alur baru SPP: hitung harga → menunggu DP → proses → ... (status "antrian" dihapus).
 *
 * - nomor_spp boleh kosong: nomor diberikan saat DP dicatat & SPP masuk produksi (unik bila terisi).
 * - tanggal_dp & catatan_harga baru di pesanan.
 * - harga_satuan & subtotal item boleh kosong (harga belum dihitung produksi).
 * - Data: status antrian → proses. Riwayat lama TIDAK diubah (label "Antrian Produksi" tetap tampil).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesanan', function (Blueprint $table) {
            $table->unsignedInteger('nomor_spp')->nullable()->change();
            $table->string('status_produksi', 20)->default('hitung_harga')->change();
            $table->date('tanggal_dp')->nullable()->after('dp');
            $table->text('catatan_harga')->nullable()->after('catatan_admin');
        });

        Schema::table('detail_pesanan', function (Blueprint $table) {
            $table->decimal('harga_satuan', 15, 2)->nullable()->default(null)->change();
            $table->decimal('subtotal', 15, 2)->nullable()->default(null)->change();
        });

        $this->pindahkanAntrian();
    }

    /** Status antrian (sudah dihapus dari alur) → proses. Riwayat lama tidak diubah. */
    public function pindahkanAntrian(): int
    {
        return DB::table('pesanan')->where('status_produksi', 'antrian')->update(['status_produksi' => 'proses']);
    }

    public function down(): void
    {
        // Status baru dipetakan ke status lama terdekat
        DB::table('pesanan')->whereIn('status_produksi', ['hitung_harga', 'menunggu_dp'])->update(['status_produksi' => 'antrian']);
        DB::table('pesanan')->where('status_produksi', 'tidak_jadi')->update(['status_produksi' => 'dibatalkan']);

        // Nomor wajib lagi: beri nomor berurutan setelah nomor terbesar
        $nomor = (int) DB::table('pesanan')->max('nomor_spp');
        foreach (DB::table('pesanan')->whereNull('nomor_spp')->orderBy('id')->pluck('id') as $id) {
            DB::table('pesanan')->where('id', $id)->update(['nomor_spp' => ++$nomor]);
        }

        DB::table('detail_pesanan')->whereNull('harga_satuan')->update(['harga_satuan' => 0]);
        DB::table('detail_pesanan')->whereNull('subtotal')->update(['subtotal' => 0]);

        Schema::table('detail_pesanan', function (Blueprint $table) {
            $table->decimal('harga_satuan', 15, 2)->nullable(false)->default(0)->change();
            $table->decimal('subtotal', 15, 2)->nullable(false)->default(0)->change();
        });

        Schema::table('pesanan', function (Blueprint $table) {
            $table->dropColumn(['tanggal_dp', 'catatan_harga']);
            $table->string('status_produksi', 20)->default('antrian')->change();
            $table->unsignedInteger('nomor_spp')->nullable(false)->change();
        });
    }
};
