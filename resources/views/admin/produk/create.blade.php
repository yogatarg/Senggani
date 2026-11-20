@extends('admin.layouts.pesanan')

@section('title', 'Tambah Produk')
@section('kembali', route('admin.produk.index'))
@section('tanpa_bottomnav', '1')

@section('content')
<div class="sg-kepala">
    <div>
        <a href="{{ route('admin.produk.index') }}" class="sg-remah d-none d-lg-inline-flex" data-batal-form><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Produk</a>
        <h1>Tambah produk</h1>
    </div>
</div>

<form id="formCreateProduk" action="{{ route('admin.produk.store') }}" method="POST" enctype="multipart/form-data"
      class="sg-sedang" data-kirim data-jaga-perubahan>
    @csrf

    <section class="sg-kartu" aria-labelledby="judul-info">
        <h2 class="sg-kartu-judul" id="judul-info"><span class="sg-no" aria-hidden="true">1</span> Informasi produk</h2>
        @include('admin.produk._info')
    </section>

    <section class="sg-kartu" aria-labelledby="judul-galeri">
        <h2 class="sg-kartu-judul" id="judul-galeri"><span class="sg-no" aria-hidden="true">2</span> Galeri produk</h2>
        @include('admin.produk._galeri-unggah', ['nama' => 'galeri', 'id' => 'f-galeri', 'kunciGalat' => 'galeri.*', 'teks' => 'Pilih foto galeri (bisa lebih dari satu)'])
    </section>

    <div class="sg-form-aksi sg-rata-kanan d-none d-lg-flex">
        <a href="{{ route('admin.produk.index') }}" class="sg-btn sg-btn-sekunder" data-batal-form>Batal</a>
        <button type="submit" class="sg-btn sg-btn-utama" data-teks-kirim="Menyimpan…"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan Produk</button>
    </div>
    <div class="sg-bilah d-lg-none">
        <a href="{{ route('admin.produk.index') }}" class="sg-btn sg-btn-sekunder" data-batal-form>Batal</a>
        <button type="submit" class="sg-btn sg-btn-utama flex-grow-1" data-teks-kirim="Menyimpan…"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan Produk</button>
    </div>
</form>
@endsection
