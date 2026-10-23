<?php

namespace App\Models;

use App\Models\Concerns\SlugUnik;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Produk extends Model
{
    use SlugUnik;

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

    // Hapus gambar utama & semua foto galeri dari storage
    // (dipanggil sebelum produk dihapus)
    public function hapusFile(): void
    {
        $file = $this->galeri()->pluck('nama_file')->push($this->gambar)->filter()->all();

        Storage::disk('public')->delete($file);
    }

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

    // Relasi ke bahan (dipilih dari master bahan)
    public function bahan()
    {
        return $this->belongsToMany(Bahan::class, 'produk_bahan', 'produk_id', 'bahan_id')
                    ->orderBy('nama_bahan');
    }

    // Relasi ke finishing (dipilih dari master finishing)
    public function finishing()
    {
        return $this->belongsToMany(Finishing::class, 'produk_finishing', 'produk_id', 'finishing_id')
                    ->orderBy('nama_finishing');
    }
}