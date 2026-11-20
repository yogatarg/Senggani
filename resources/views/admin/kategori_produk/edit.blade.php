@extends('admin.layouts.pesanan')

@section('title', 'Edit Kategori')
@section('kembali', route('admin.kategori-produk.index'))
@section('tanpa_bottomnav', '1')

@section('content')
<div class="sg-kepala">
    <div>
        <a href="{{ route('admin.kategori-produk.index') }}" class="sg-remah d-none d-lg-inline-flex" data-batal-form><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Kategori</a>
        <h1>Edit kategori</h1>
        <p class="sg-kepala-sub" style="overflow-wrap:anywhere">{{ $kategoriProduk->nama_kategori }}</p>
    </div>
</div>

@include('admin.kategori_produk._form')
@endsection
