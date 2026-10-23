<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bahan extends Model
{
    protected $table = 'bahan';

    protected $fillable = [
        'nama_bahan', 'deskripsi'
    ];

    public function produk()
    {
        return $this->belongsToMany(Produk::class, 'produk_bahan', 'bahan_id', 'produk_id');
    }
}
