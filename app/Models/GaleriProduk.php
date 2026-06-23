<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GaleriProduk extends Model
{
    protected $table = 'galeri_produk';

    protected $fillable = [
        'produk_id', 'nama_file', 'keterangan', 'urutan'
    ];
}