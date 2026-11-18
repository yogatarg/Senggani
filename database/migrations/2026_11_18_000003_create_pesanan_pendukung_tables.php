<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gambar mockup/referensi (disk "local" = storage/app/private, tidak publik)
        Schema::create('pesanan_file', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesanan_id')->constrained('pesanan')->cascadeOnDelete();
            $table->string('jenis', 20);
            $table->string('path', 255);
            $table->string('nama_asli', 255);
            $table->string('mime', 100);
            $table->unsignedInteger('ukuran');
            $table->foreignId('diunggah_oleh')->nullable()->constrained('admin')->nullOnDelete();
            $table->timestamps();

            $table->index(['pesanan_id', 'jenis']);
        });

        // Perubahan status + catatan progres (tidak pernah diedit)
        Schema::create('pesanan_riwayat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesanan_id')->constrained('pesanan')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admin')->nullOnDelete();
            $table->string('nama_pengguna', 100);
            $table->string('status_dari', 20)->nullable();
            $table->string('status_ke', 20)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pesanan_id', 'created_at']);
        });

        // Penghitung nomor order per bulan (YYMM)
        Schema::create('pesanan_nomor_urut', function (Blueprint $table) {
            $table->char('periode', 4)->primary();
            $table->unsignedInteger('terakhir')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesanan_nomor_urut');
        Schema::dropIfExists('pesanan_riwayat');
        Schema::dropIfExists('pesanan_file');
    }
};
