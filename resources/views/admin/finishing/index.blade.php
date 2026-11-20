@extends('admin.layouts.pesanan')

@section('title', 'Master Finishing')

@section('content')
    @include('admin.katalog._master-index', [
        'daftar' => $finishing,
        'rute' => 'admin.finishing',
        'kolom' => 'nama_finishing',
        'label' => 'finishing',
        'judul' => 'Master Finishing',
        'placeholder' => 'Contoh: Laminasi Doff',
        'ikon' => 'fa-wand-magic-sparkles',
    ])
@endsection
