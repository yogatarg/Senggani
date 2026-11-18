@extends('admin.layouts.pesanan')

@section('title', 'Ubah Password')

@section('content')
<h1>Ubah Password</h1>
<p>Akun: {{ $pengguna->nama }} ({{ $pengguna->username ?? $pengguna->email }})</p>

<form method="POST" action="{{ route('admin.password.update') }}">
    @csrf @method('PUT')
    <p><label>Password saat ini* <input type="password" name="password_lama" autocomplete="current-password" required></label></p>
    <p><label>Password baru* <input type="password" name="password" minlength="8" autocomplete="new-password" required></label> (minimal 8 karakter)</p>
    <p><label>Ulangi password baru* <input type="password" name="password_confirmation" autocomplete="new-password" required></label></p>
    <button type="submit">Simpan password</button>
</form>
@endsection
