<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master cetak (mis. Cetak Full Color, Cetak 1 Warna) dipilih per produk
     * seperti bahan & finishing. Finishing lama yang namanya diawali "Cetak"
     * dipindahkan ke master cetak beserta relasi produknya.
     */
    public function up(): void
    {
        // ── Tabel master ──
        Schema::create('cetak', function (Blueprint $table) {
            $table->id();
            $table->string('nama_cetak')->unique();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        // ── Tabel pivot ──
        Schema::create('produk_cetak', function (Blueprint $table) {
            $table->foreignId('produk_id')->constrained('produk')->onDelete('cascade');
            $table->foreignId('cetak_id')->constrained('cetak')->onDelete('cascade');
            $table->primary(['produk_id', 'cetak_id']);
        });

        // ── Pindahkan finishing "Cetak ..." ke master cetak ──
        $lama = DB::table('finishing')->where('nama_finishing', 'like', 'Cetak%')->get();

        foreach ($lama as $f) {
            $cetakId = DB::table('cetak')->insertGetId([
                'nama_cetak' => $f->nama_finishing,
                'deskripsi'  => $f->deskripsi,
                'created_at' => $f->created_at ?? now(),
                'updated_at' => now(),
            ]);

            $produkIds = DB::table('produk_finishing')->where('finishing_id', $f->id)->pluck('produk_id');
            foreach ($produkIds as $produkId) {
                DB::table('produk_cetak')->insert(['produk_id' => $produkId, 'cetak_id' => $cetakId]);
            }

            // Relasi produk_finishing ikut terhapus (cascade)
            DB::table('finishing')->where('id', $f->id)->delete();
        }
    }

    public function down(): void
    {
        // Kembalikan master cetak menjadi finishing beserta relasinya
        foreach (DB::table('cetak')->get() as $c) {
            $finishingId = DB::table('finishing')->where('nama_finishing', $c->nama_cetak)->value('id')
                ?? DB::table('finishing')->insertGetId([
                    'nama_finishing' => $c->nama_cetak,
                    'deskripsi'      => $c->deskripsi,
                    'created_at'     => $c->created_at ?? now(),
                    'updated_at'     => now(),
                ]);

            $produkIds = DB::table('produk_cetak')->where('cetak_id', $c->id)->pluck('produk_id');
            foreach ($produkIds as $produkId) {
                DB::table('produk_finishing')->insertOrIgnore(['produk_id' => $produkId, 'finishing_id' => $finishingId]);
            }
        }

        Schema::dropIfExists('produk_cetak');
        Schema::dropIfExists('cetak');
    }
};
