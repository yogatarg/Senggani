<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengganti tabel pesanan/detail_pesanan lama (tidak pernah dipakai) dengan
 * struktur modul Pesanan internal. Berhenti SEBELUM DDL apa pun bila tabel lama
 * berisi data, karena DDL MySQL tidak bisa di-rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['detail_pesanan', 'pesanan'] as $tabel) {
            if (Schema::hasTable($tabel) && DB::table($tabel)->exists()) {
                throw new RuntimeException("Tabel {$tabel} berisi data. Migrasi dihentikan; periksa dan cadangkan dulu.");
            }
        }

        Schema::dropIfExists('detail_pesanan');
        Schema::dropIfExists('pesanan');

        Schema::create('pesanan', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_order', 20)->unique();
            $table->string('nama_customer', 150)->index();
            $table->string('nomor_whatsapp', 20);
            $table->text('alamat');
            $table->string('kota', 100);
            $table->date('tanggal_order');
            $table->date('deadline')->index();
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('dp', 15, 2)->default(0);
            $table->date('tanggal_lunas')->nullable();
            $table->string('link_desain', 500)->nullable();
            $table->text('catatan_desain')->nullable();
            $table->string('status_desain', 20)->default('belum_dikirim');
            $table->text('catatan_admin')->nullable();
            $table->string('status_produksi', 20)->default('antrian');
            $table->timestamp('selesai_pada')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('admin')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes()->index();

            $table->index(['status_produksi', 'deadline']);
            $table->index(['status_produksi', 'selesai_pada']);
        });

        Schema::create('detail_pesanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesanan_id')->constrained('pesanan')->cascadeOnDelete();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->string('jenis_packaging', 150);
            $table->decimal('panjang', 8, 2)->nullable();
            $table->decimal('lebar', 8, 2)->nullable();
            $table->decimal('tinggi', 8, 2)->nullable();
            $table->string('jenis_bahan', 150);
            $table->string('gramasi', 50)->nullable();
            $table->string('jenis_cetak', 150)->nullable();
            $table->string('warna_cetak', 100)->nullable();
            $table->text('finishing')->nullable();
            $table->string('aksesori', 255)->nullable();
            $table->unsignedInteger('jumlah');
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_pesanan');
        Schema::dropIfExists('pesanan');

        // Struktur lama, sama dengan migrasi 2026_03_10_014211 dan 2026_03_10_014217
        Schema::create('pesanan', function (Blueprint $table) {
            $table->id();
            $table->string('nama_pelanggan');
            $table->string('nomor_whatsapp');
            $table->string('email_pelanggan')->nullable();
            $table->text('catatan')->nullable();
            $table->decimal('total_estimasi', 15, 2)->default(0);
            $table->enum('status_pesanan', ['menunggu', 'diproses', 'selesai', 'dibatalkan'])->default('menunggu');
            $table->timestamps();
        });

        Schema::create('detail_pesanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesanan_id')->constrained('pesanan')->onDelete('cascade');
            $table->foreignId('produk_id')->constrained('produk')->onDelete('cascade');
            $table->text('spesifikasi')->nullable();
            $table->integer('jumlah');
            $table->decimal('harga_satuan', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });
    }
};
