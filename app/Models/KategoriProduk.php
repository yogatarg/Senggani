<?php

namespace App\Models;

use App\Models\Concerns\SlugUnik;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KategoriProduk extends Model
{
    use HasFactory, SlugUnik;

    protected $table = 'kategori_produk';

    protected $fillable = [
        'nama_kategori',
        'slug',
        'deskripsi',
        'gambar',        // ← tambahkan ini
    ];

    public function produk()
    {
        return $this->hasMany(Produk::class, 'kategori_id');
    }
}