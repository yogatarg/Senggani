<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pesanan', function (Blueprint $table) {
            $table->id();
            $table->string('nama_pelanggan');
            $table->string('nomor_whatsapp');
            $table->string('email_pelanggan')->nullable();
            $table->text('catatan')->nullable();
            $table->decimal('total_estimasi', 15, 2)->default(0);
            $table->enum('status_pesanan', [
                'menunggu',
                'diproses',
                'selesai',
                'dibatalkan'
            ])->default('menunggu');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesanan');
    }
};