@extends('admin.layouts.pesanan')

@section('title', 'Tambah Order')

@section('content')
<p><a href="{{ route('admin.pesanan.index') }}">← Pesanan</a></p>
<h1>Tambah Order</h1>

<form method="POST" action="{{ route('admin.pesanan.store') }}" enctype="multipart/form-data">
    @csrf
    @include('admin.pesanan._form')
    <button type="submit">Simpan Order</button>
    <a href="{{ route('admin.pesanan.index') }}">Batal</a>
</form>
@endsection
