<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galeri_produk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')
                  ->constrained('produk')
                  ->onDelete('cascade');
            $table->string('nama_file');
            $table->string('keterangan')->nullable();
            $table->integer('urutan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galeri_produk');
    }
};