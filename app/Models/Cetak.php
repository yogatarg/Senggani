<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cetak extends Model
{
    protected $table = 'cetak';

    protected $fillable = [
        'nama_cetak', 'deskripsi'
    ];

    public function produk()
    {
        return $this->belongsToMany(Produk::class, 'produk_cetak', 'cetak_id', 'produk_id');
    }
}
