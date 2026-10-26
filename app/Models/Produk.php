<?php

namespace App\Models;

use App\Models\Concerns\SlugUnik;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Produk extends Model
{
    use HasFactory, SlugUnik;

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

    // Daftar path gambar utama & semua foto galeri produk ini.
    // Dicatat sebelum produk dihapus, lalu filenya dihapus setelah data terhapus.
    public function daftarFile(): array
    {
        return $this->galeri()->pluck('nama_file')->push($this->gambar)->filter()->values()->all();
    }

    // Hapus gambar utama & semua foto galeri dari storage
    public function hapusFile(): void
    {
        Storage::disk('public')->delete($this->daftarFile());
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

    // Relasi ke cetak (dipilih dari master cetak)
    public function cetak()
    {
        return $this->belongsToMany(Cetak::class, 'produk_cetak', 'produk_id', 'cetak_id')
                    ->orderBy('nama_cetak');
    }
}