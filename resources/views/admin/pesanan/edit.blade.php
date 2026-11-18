@extends('admin.layouts.pesanan')

@section('title', 'Edit ' . $pesanan->nomor_spp_teks)
@section('kembali', route('admin.pesanan.show', $pesanan))
@section('tanpa_bottomnav', '1')

@section('content')
<div class="sg-kepala">
    <div>
        <a href="{{ route('admin.pesanan.show', $pesanan) }}" class="sg-remah d-none d-lg-inline-flex" data-batal-form><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> {{ $pesanan->nomor_spp_teks }}</a>
        <h1>Edit {{ $pesanan->nomor_spp_teks }}</h1>
        <p class="sg-kepala-sub">Total dihitung ulang otomatis dari item saat disimpan.</p>
    </div>
</div>

<form method="POST" action="{{ route('admin.pesanan.update', $pesanan) }}" enctype="multipart/form-data" data-kirim data-jaga-perubahan>
    @csrf
    @method('PUT')
    @include('admin.pesanan._form', ['labelSimpan' => 'Simpan Perubahan', 'urlBatal' => route('admin.pesanan.show', $pesanan)])
</form>
@endsection
