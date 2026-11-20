@extends('admin.layouts.pesanan')

@section('title', 'Edit Bahan')
@section('kembali', route('admin.bahan.index'))
@section('tanpa_bottomnav', '1')

@section('content')
    @include('admin.katalog._master-edit', [
        'item' => $bahan,
        'rute' => 'admin.bahan',
        'kolom' => 'nama_bahan',
        'label' => 'bahan',
        'judulIndeks' => 'Master Bahan',
    ])
@endsection
