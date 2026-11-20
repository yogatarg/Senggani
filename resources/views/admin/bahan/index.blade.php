@extends('admin.layouts.pesanan')

@section('title', 'Master Bahan')

@section('content')
    @include('admin.katalog._master-index', [
        'daftar' => $bahan,
        'rute' => 'admin.bahan',
        'kolom' => 'nama_bahan',
        'label' => 'bahan',
        'judul' => 'Master Bahan',
        'placeholder' => 'Contoh: Ivory 230 gsm',
        'ikon' => 'fa-scroll',
    ])
@endsection
