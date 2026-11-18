@extends('admin.layouts.pesanan')

@section('title', 'Edit ' . $pengguna->nama)

@section('content')
<p><a href="{{ route('admin.pengguna.index') }}">← Kelola Pengguna</a></p>
<h1>Edit Pengguna: {{ $pengguna->nama }}</h1>

<form method="POST" action="{{ route('admin.pengguna.update', $pengguna) }}">
    @csrf @method('PUT')
    @include('admin.pengguna._form')
    <button type="submit">Simpan Perubahan</button>
</form>

<h2>Reset password</h2>
<p>Isi password baru lalu sampaikan sendiri ke penggunanya.</p>
<form method="POST" action="{{ route('admin.pengguna.password', $pengguna) }}">
    @csrf @method('PUT')
    <p><label>Password baru* <input type="password" name="password" minlength="8" autocomplete="new-password" required></label></p>
    <p><label>Ulangi password baru* <input type="password" name="password_confirmation" autocomplete="new-password" required></label></p>
    <button type="submit">Reset password</button>
</form>
@endsection
