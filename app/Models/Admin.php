<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Admin extends Authenticatable
{
    use HasFactory;

    protected $table = 'admin';

    protected $fillable = [
        'nama',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    // Password otomatis di-hash saat diisi. Nilai yang sudah berupa hash
    // (mis. dari Hash::make di perintah admin:buat) tidak di-hash ulang.
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

}