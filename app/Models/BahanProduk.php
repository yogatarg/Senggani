<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BahanProduk extends Model
{
    protected $table = 'bahan_produk';

    protected $fillable = [
        'produk_id', 'nama_bahan'
    ];
}