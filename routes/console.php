<?php

use App\Models\Admin;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Buat akun admin baru, atau ganti password jika email sudah terdaftar
Artisan::command('admin:buat', function () {
    $email = $this->ask('Email admin');
    $admin = Admin::where('email', $email)->first();
    $nama  = $admin ? $admin->nama : $this->ask('Nama admin');

    $password   = $this->secret('Password (min. 8 karakter)');
    $konfirmasi = $this->secret('Ulangi password');

    $validator = Validator::make(
        ['nama' => $nama, 'email' => $email, 'password' => $password, 'password_confirmation' => $konfirmasi],
        ['nama' => 'required|string|max:255', 'email' => 'required|email', 'password' => 'required|min:8|confirmed'],
    );

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $pesan) {
            $this->error($pesan);
        }
        return 1;
    }

    if ($admin && ! $this->confirm("Admin {$email} sudah ada. Ganti password-nya?", true)) {
        return 0;
    }

    Admin::updateOrCreate(
        ['email' => $email],
        ['nama' => $nama, 'password' => Hash::make($password)],
    );

    $this->info($admin ? "Password admin {$email} berhasil diganti." : "Admin {$email} berhasil dibuat.");
})->purpose('Buat akun admin atau ganti password admin');
