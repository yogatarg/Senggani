@extends('admin.layouts.pesanan')

@section('title', 'Edit ' . $pesanan->nomor_order)

@section('content')
<p><a href="{{ route('admin.pesanan.show', $pesanan) }}">← {{ $pesanan->nomor_order }}</a></p>
<h1>Edit Order {{ $pesanan->nomor_order }}</h1>

<form method="POST" action="{{ route('admin.pesanan.update', $pesanan) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('admin.pesanan._form')
    <button type="submit">Simpan Perubahan</button>
    <a href="{{ route('admin.pesanan.show', $pesanan) }}">Batal</a>
</form>
@endsection
