<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor SPP (bilangan bulat unik, diketik admin) menggantikan SGN-YYMM-NNN,
 * tabel customer dengan ID sendiri, serta field nama_brand & jenis_deadline.
 *
 * Data yang sudah ada dikonversi:
 * - nomor_spp diberikan berurutan 1, 2, 3, … menurut id (SGN-2611-001 → 1, dst.);
 * - satu customer per nomor WhatsApp, data diambil dari pesanan terbaru nomor itu;
 * - salinan nama/WA/alamat/kota di pesanan tidak diubah (snapshot).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150)->index();
            $table->string('nomor_whatsapp', 20)->unique();
            $table->text('alamat')->nullable();
            $table->string('kota', 100)->nullable();
            $table->timestamps();
        });

        Schema::table('pesanan', function (Blueprint $table) {
            $table->unsignedInteger('nomor_spp')->nullable()->after('id');
            $table->foreignId('customer_id')->nullable()->after('nomor_spp')->constrained('customer')->restrictOnDelete();
            $table->string('nama_brand', 150)->nullable()->after('nama_customer');
            $table->string('jenis_deadline', 10)->default('kirim')->after('deadline');
        });

        $this->isiDataLama();

        Schema::table('pesanan', function (Blueprint $table) {
            $table->dropUnique(['nomor_order']);
        });

        Schema::table('pesanan', function (Blueprint $table) {
            $table->dropColumn('nomor_order');
            $table->unsignedInteger('nomor_spp')->nullable(false)->change();
            $table->unsignedBigInteger('customer_id')->nullable(false)->change();
            $table->unique('nomor_spp');
        });

        Schema::dropIfExists('pesanan_nomor_urut');
    }

    private function isiDataLama(): void
    {
        $sekarang = now();
        $nomor    = 0;

        $daftar = DB::table('pesanan')->orderBy('id')->get(['id', 'nama_customer', 'nomor_whatsapp', 'alamat', 'kota']);

        // Customer: satu per nomor WA. Urutan id mengikuti pesanan pertamanya,
        // isi data dari pesanan terbaru nomor itu (baris terakhir menimpa).
        $terbaru = $daftar->keyBy(fn ($p) => (string) $p->nomor_whatsapp);
        $customerPerWa = [];
        foreach ($daftar as $pertama) {
            $wa = (string) $pertama->nomor_whatsapp;
            if (isset($customerPerWa[$wa])) {
                continue;
            }
            $p = $terbaru[$wa];

            $customerPerWa[$wa] = DB::table('customer')->insertGetId([
                'nama'           => $p->nama_customer,
                'nomor_whatsapp' => $wa,
                'alamat'         => $p->alamat,
                'kota'           => $p->kota,
                'created_at'     => $sekarang,
                'updated_at'     => $sekarang,
            ]);
        }

        foreach ($daftar as $p) {
            DB::table('pesanan')->where('id', $p->id)->update([
                'nomor_spp'   => ++$nomor,
                'customer_id' => $customerPerWa[(string) $p->nomor_whatsapp],
            ]);
        }
    }

    public function down(): void
    {
        Schema::create('pesanan_nomor_urut', function (Blueprint $table) {
            $table->char('periode', 4)->primary();
            $table->unsignedInteger('terakhir')->default(0);
        });

        Schema::table('pesanan', function (Blueprint $table) {
            $table->string('nomor_order', 20)->nullable()->after('id');
        });

        // Nomor lama tidak bisa dipulihkan persis; pakai periode palsu 0000 agar tetap unik
        foreach (DB::table('pesanan')->get(['id', 'nomor_spp']) as $p) {
            DB::table('pesanan')->where('id', $p->id)->update([
                'nomor_order' => sprintf('SGN-0000-%03d', $p->nomor_spp),
            ]);
        }

        Schema::table('pesanan', function (Blueprint $table) {
            $table->dropUnique(['nomor_spp']);
            $table->dropForeign(['customer_id']);
        });

        Schema::table('pesanan', function (Blueprint $table) {
            $table->dropColumn(['nomor_spp', 'customer_id', 'nama_brand', 'jenis_deadline']);
            $table->string('nomor_order', 20)->nullable(false)->change();
            $table->unique('nomor_order');
        });

        Schema::dropIfExists('customer');
    }
};
