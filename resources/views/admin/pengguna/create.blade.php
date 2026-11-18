@extends('admin.layouts.pesanan')

@section('title', 'Tambah Pengguna')

@section('content')
<p><a href="{{ route('admin.pengguna.index') }}">← Kelola Pengguna</a></p>
<h1>Tambah Pengguna</h1>

<form method="POST" action="{{ route('admin.pengguna.store') }}">
    @csrf
    @include('admin.pengguna._form')
    <p><label>Password awal* <input type="password" name="password" minlength="8" autocomplete="new-password" required></label> (minimal 8 karakter)</p>
    <p><label>Ulangi password* <input type="password" name="password_confirmation" autocomplete="new-password" required></label></p>
    <button type="submit">Simpan</button>
</form>
@endsection
