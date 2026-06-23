<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinishingProduk extends Model
{
    protected $table = 'finishing_produk';

    protected $fillable = [
        'produk_id', 'nama_finishing'
    ];
}