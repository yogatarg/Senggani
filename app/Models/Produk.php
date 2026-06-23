<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produk extends Model
{
    protected $table = 'produk';

    protected $fillable = [
        'kategori_id',
        'nama_produk',
        'slug',
        'deskripsi',
        'minimal_pesan',
        'gambar',
        'produk_kustom',
    ];

    // Relasi ke kategori
    public function kategori()
    {
        return $this->belongsTo(KategoriProduk::class, 'kategori_id');
    }

    // Relasi ke galeri
    public function galeri()
    {
        return $this->hasMany(GaleriProduk::class, 'produk_id')->orderBy('urutan');
    }

    // Relasi ke ukuran
    public function ukuran()
    {
        return $this->hasMany(UkuranProduk::class, 'produk_id');
    }

    // Relasi ke bahan
    public function bahan()
    {
        return $this->hasMany(BahanProduk::class, 'produk_id');
    }

    // Relasi ke finishing
    public function finishing()
    {
        return $this->hasMany(FinishingProduk::class, 'produk_id');
    }
}