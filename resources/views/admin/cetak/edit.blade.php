@extends('admin.layouts.pesanan')

@section('title', 'Edit Jenis cetak')
@section('kembali', route('admin.cetak.index'))
@section('tanpa_bottomnav', '1')

@section('content')
    @include('admin.katalog._master-edit', [
        'item' => $cetak,
        'rute' => 'admin.cetak',
        'kolom' => 'nama_cetak',
        'label' => 'jenis cetak',
        'judulIndeks' => 'Master Cetak',
    ])
@endsection
