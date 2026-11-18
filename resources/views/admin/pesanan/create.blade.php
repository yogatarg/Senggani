@extends('admin.layouts.pesanan')

@section('title', 'Tambah Order')
@section('kembali', route('admin.pesanan.index'))
@section('tanpa_bottomnav', '1')

@section('content')
<div class="sg-kepala">
    <div>
        <a href="{{ route('admin.pesanan.index') }}" class="sg-remah d-none d-lg-inline-flex" data-batal-form><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Pesanan</a>
        <h1>Tambah Order</h1>
        <p class="sg-kepala-sub">Isian bertanda <span class="sg-wajib" aria-hidden="true">*</span> wajib diisi.</p>
    </div>
</div>

<form method="POST" action="{{ route('admin.pesanan.store') }}" enctype="multipart/form-data" data-kirim data-jaga-perubahan>
    @csrf
    @include('admin.pesanan._form', ['labelSimpan' => 'Simpan Order', 'urlBatal' => route('admin.pesanan.index')])
</form>
@endsection
