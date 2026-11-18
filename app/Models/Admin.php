<?php

namespace App\Models;

use App\Enums\Peran;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Pengguna panel admin. Tabel tetap bernama "admin" walau kini ada dua peran:
 * admin (CS, akses penuh) dan produksi (hanya modul Pesanan + ubah password).
 */
class Admin extends Authenticatable
{
    use HasFactory;

    protected $table = 'admin';

    protected $fillable = [
        'nama',
        'username',
        'email',
        'peran',
        'aktif',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected $attributes = [
        'peran' => 'admin',
        'aktif' => true,
    ];

    // Password otomatis di-hash saat diisi. Nilai yang sudah berupa hash
    // (mis. dari Hash::make di perintah admin:buat) tidak di-hash ulang.
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'peran'    => Peran::class,
            'aktif'    => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->peran === Peran::Admin;
    }

    public function isProduksi(): bool
    {
        return $this->peran === Peran::Produksi;
    }
}
