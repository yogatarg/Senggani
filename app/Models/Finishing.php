<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Finishing extends Model
{
    protected $table = 'finishing';

    protected $fillable = [
        'nama_finishing', 'deskripsi'
    ];

    public function produk()
    {
        return $this->belongsToMany(Produk::class, 'produk_finishing', 'finishing_id', 'produk_id');
    }
}
