<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UkuranProduk extends Model
{
    protected $table = 'ukuran_produk';

    protected $fillable = [
        'produk_id', 'nama_ukuran', 'panjang', 'lebar', 'tinggi', 'keterangan'
    ];
}