@extends('admin.layouts.pesanan')

@section('title', 'Edit Finishing')
@section('kembali', route('admin.finishing.index'))
@section('tanpa_bottomnav', '1')

@section('content')
    @include('admin.katalog._master-edit', [
        'item' => $finishing,
        'rute' => 'admin.finishing',
        'kolom' => 'nama_finishing',
        'label' => 'finishing',
        'judulIndeks' => 'Master Finishing',
    ])
@endsection
