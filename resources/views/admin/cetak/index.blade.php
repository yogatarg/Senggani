@extends('admin.layouts.pesanan')

@section('title', 'Master Cetak')

@section('content')
    @include('admin.katalog._master-index', [
        'daftar' => $cetak,
        'rute' => 'admin.cetak',
        'kolom' => 'nama_cetak',
        'label' => 'jenis cetak',
        'judul' => 'Master Cetak',
        'placeholder' => 'Contoh: Cetak Full Color (lebih dari 2 warna)',
        'ikon' => 'fa-fill-drip',
    ])
@endsection
