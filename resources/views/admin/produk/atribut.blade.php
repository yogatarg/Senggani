@extends('admin.layouts.pesanan')

@section('title', 'Atribut Produk')
@section('kembali', route('admin.produk.index'))
@section('tanpa_bottomnav', '1')

@php
    // Tiga daftar centang dalam satu form, disimpan sekaligus dengan satu tombol
    $kelompok = [
        ['jenis' => 'bahan', 'judul' => 'Bahan Produk', 'label' => 'bahan', 'semua' => $semuaBahan,
         'dipilih' => $produk->bahan->pluck('id')->all(), 'nama' => 'nama_bahan',
         'master' => route('admin.bahan.index'), 'labelMaster' => 'Master Bahan', 'ringkas' => 'bahan'],
        ['jenis' => 'cetak', 'judul' => 'Jenis Cetak Produk', 'label' => 'jenis cetak', 'semua' => $semuaCetak,
         'dipilih' => $produk->cetak->pluck('id')->all(), 'nama' => 'nama_cetak',
         'master' => route('admin.cetak.index'), 'labelMaster' => 'Master Cetak', 'ringkas' => 'cetak'],
        ['jenis' => 'finishing', 'judul' => 'Finishing Produk', 'label' => 'finishing', 'semua' => $semuaFinishing,
         'dipilih' => $produk->finishing->pluck('id')->all(), 'nama' => 'nama_finishing',
         'master' => route('admin.finishing.index'), 'labelMaster' => 'Master Finishing', 'ringkas' => 'finishing'],
    ];
    $ringkasAwal = collect($kelompok)->map(fn ($k) => count($k['dipilih']) . ' ' . $k['ringkas'])->implode(' · ');
@endphp

@section('content')
<div class="sg-kepala">
    <div>
        <a href="{{ route('admin.produk.index') }}" class="sg-remah d-none d-lg-inline-flex" data-batal-form><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Produk</a>
        <h1>Atribut produk</h1>
        <p class="sg-kepala-sub" style="overflow-wrap:anywhere">{{ $produk->nama_produk }}</p>
    </div>
</div>

<form action="{{ route('admin.produk.atribut.simpan', $produk) }}" method="POST" data-kirim data-jaga-perubahan>
    @csrf @method('PUT')

    <div class="sg-atribut-grid">
        @foreach ($kelompok as $k)
            <section class="sg-kartu" id="f-{{ $k['jenis'] }}" data-pilihan="{{ $k['jenis'] }}" data-label="{{ $k['label'] }}" data-label-ringkas="{{ $k['ringkas'] }}" aria-labelledby="judul-{{ $k['jenis'] }}">
                <div class="sg-atribut-kepala">
                    <h2 class="sg-kartu-judul" id="judul-{{ $k['jenis'] }}">{{ $k['judul'] }}</h2>
                    <span class="sg-chip-kecil" data-counter aria-live="polite">{{ count($k['dipilih']) }} dipilih</span>
                </div>

                @if ($k['semua']->isEmpty())
                    <div class="sg-kosong sg-kosong-ringkas">
                        <p>Belum ada data {{ $k['label'] }}.</p>
                        <a href="{{ $k['master'] }}" class="sg-btn sg-btn-sekunder" data-batal-form>Tambah di {{ $k['labelMaster'] }}</a>
                    </div>
                @else
                    <div class="sg-atribut-alat">
                        <div class="sg-cari">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <label class="visually-hidden" for="cari-{{ $k['jenis'] }}">Cari {{ $k['label'] }}</label>
                            <input type="search" id="cari-{{ $k['jenis'] }}" class="form-control" placeholder="Cari {{ $k['label'] }}…" autocomplete="off" data-cari>
                            <button type="button" class="sg-cari-hapus" data-cari-hapus hidden aria-label="Hapus pencarian {{ $k['label'] }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                        </div>
                        <button type="button" class="sg-btn sg-btn-sekunder" data-pilih-semua>Pilih semua</button>
                    </div>

                    <ul class="sg-pilih-daftar">
                        @foreach ($k['semua'] as $item)
                            <li data-item="{{ mb_strtolower($item->{$k['nama']}) }}">
                                <label class="sg-pilih-baris" for="{{ $k['jenis'] . $item->id }}">
                                    <input type="checkbox" class="form-check-input" id="{{ $k['jenis'] . $item->id }}" name="{{ $k['jenis'] }}[]" value="{{ $item->id }}"
                                           @checked(in_array($item->id, $k['dipilih']))>
                                    <span>{{ $item->{$k['nama']} }}</span>
                                </label>
                            </li>
                        @endforeach
                        <li class="sg-pilih-kosong" data-cari-kosong hidden></li>
                    </ul>
                @endif

                <div class="sg-atribut-kaki">
                    <a href="{{ $k['master'] }}" class="sg-btn sg-btn-teks" data-batal-form><i class="fa-solid fa-gear" aria-hidden="true"></i> Kelola {{ $k['labelMaster'] }}</a>
                </div>
            </section>
        @endforeach
    </div>

    <div class="sg-aksi-lekat d-none d-lg-flex">
        <p><strong class="d-block fw-semibold text-body sg-angka" data-ringkas-atribut>{{ $ringkasAwal }}</strong> Bahan, jenis cetak, dan finishing disimpan sekaligus.</p>
        <button type="submit" class="sg-btn sg-btn-utama" data-teks-kirim="Menyimpan…"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan Semua</button>
    </div>
    <div class="sg-bilah d-lg-none">
        <p class="sg-bilah-ringkas m-0"><span class="sg-angka" data-ringkas-atribut aria-live="polite">{{ $ringkasAwal }}</span></p>
        <button type="submit" class="sg-btn sg-btn-utama" data-teks-kirim="Menyimpan…"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan Semua</button>
    </div>
</form>
@endsection
