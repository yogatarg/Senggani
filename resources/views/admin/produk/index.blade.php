@extends('admin.layouts.pesanan')

@section('title', 'Produk')

@php
    $hapusProduk = fn ($item) => [
        'aksi' => route('admin.produk.destroy', $item),
        'judul' => 'Hapus produk ' . $item->nama_produk . '?',
        'isi' => 'Gambar dan galeri produk ini ikut terhapus. Tindakan ini tidak bisa dibatalkan.',
        'labelAria' => 'Hapus produk ' . $item->nama_produk,
    ];
@endphp

@section('content')
<div class="sg-kepala">
    <div>
        <h1>Produk</h1>
        <p class="sg-kepala-sub">{{ $produk->count() }} produk · "Lengkap" berarti sudah punya bahan dan finishing</p>
    </div>
    <div class="sg-kepala-aksi">
        <a href="{{ route('admin.produk.create') }}" class="sg-btn sg-btn-utama"><i class="fa-solid fa-plus" aria-hidden="true"></i> Tambah Produk</a>
    </div>
</div>

@if ($produk->isEmpty())
    <div class="sg-kosong">
        <span class="sg-kosong-ikon"><i class="fa-solid fa-cube" aria-hidden="true"></i></span>
        <h2>Belum ada produk</h2>
        <p>Produk yang ditambahkan akan tampil di katalog website.</p>
        <a href="{{ route('admin.produk.create') }}" class="sg-btn sg-btn-utama"><i class="fa-solid fa-plus" aria-hidden="true"></i> Tambah Produk</a>
    </div>
@else
    {{-- Desktop: tabel --}}
    <div class="d-none d-xl-block sg-tabel-gulir">
        <table class="sg-tabel sg-tabel-katalog">
            <thead>
                <tr>
                    <th scope="col">Produk</th>
                    <th scope="col">Kategori</th>
                    <th scope="col">Min. pesan</th>
                    <th scope="col">Atribut &amp; status</th>
                    <th scope="col"><span class="visually-hidden">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($produk as $item)
                    <tr>
                        <td>
                            <div class="sg-nama-sel">
                                @include('admin.katalog._thumb', ['src' => $item->gambar, 'kelas' => 'sg-thumb-43', 'w' => 64])
                                <span class="sg-nama-teks">
                                    <strong>{{ $item->nama_produk }}</strong>
                                    <span class="sg-slug">{{ $item->slug }}</span>
                                </span>
                            </div>
                        </td>
                        <td>
                            @if ($item->kategori)
                                <span class="sg-chip-kecil">{{ $item->kategori->nama_kategori }}</span>
                            @else
                                <span class="sg-muted sg-kecil">Tanpa kategori</span>
                            @endif
                        </td>
                        <td class="sg-angka text-nowrap">{{ number_format($item->minimal_pesan, 0, ',', '.') }} pcs</td>
                        <td>
                            <div class="sg-sel-status">
                                @include('admin.produk._hitung', ['item' => $item])
                                @include('admin.produk._lengkap', ['item' => $item])
                            </div>
                        </td>
                        <td class="text-end">
                            <div class="sg-tabel-aksi">
                                <a href="{{ route('admin.produk.edit', $item) }}" class="sg-btn sg-btn-sekunder" aria-label="Edit produk {{ $item->nama_produk }}"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>
                                <a href="{{ route('admin.produk.atribut', $item) }}" class="sg-btn sg-btn-sekunder" aria-label="Kelola bahan, cetak, dan finishing {{ $item->nama_produk }}"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Kelola</a>
                                @include('admin.katalog._hapus', $hapusProduk($item) + ['ikon' => true])
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- HP, tablet & laptop kecil (<1200): kartu, karena 6 kolom tidak muat di samping sidebar --}}
    <ul class="sg-kartu-katalog d-xl-none">
        @foreach ($produk as $item)
            <li>
                <div class="sg-kk-kepala">
                    @include('admin.katalog._thumb', ['src' => $item->gambar, 'kelas' => 'sg-thumb-43', 'w' => 64])
                    <div style="min-width:0">
                        <h2 class="sg-kk-judul">{{ $item->nama_produk }}</h2>
                        <p class="sg-kk-meta sg-satu-baris sg-slug">{{ $item->slug }}</p>
                        <p class="sg-kk-info">
                            @if ($item->kategori)
                                <span class="sg-chip-kecil">{{ $item->kategori->nama_kategori }}</span>
                            @else
                                <span class="sg-muted">Tanpa kategori</span>
                            @endif
                            <span class="sg-angka">Min. {{ number_format($item->minimal_pesan, 0, ',', '.') }} pcs</span>
                        </p>
                    </div>
                </div>
                <div class="sg-kk-status">
                    @include('admin.produk._hitung', ['item' => $item])
                    @include('admin.produk._lengkap', ['item' => $item])
                </div>
                <div class="sg-kk-aksi">
                    <a href="{{ route('admin.produk.edit', $item) }}" class="sg-btn sg-btn-sekunder" aria-label="Edit produk {{ $item->nama_produk }}"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>
                    <a href="{{ route('admin.produk.atribut', $item) }}" class="sg-btn sg-btn-sekunder" aria-label="Kelola bahan, cetak, dan finishing {{ $item->nama_produk }}"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Kelola</a>
                    @include('admin.katalog._hapus', $hapusProduk($item))
                </div>
            </li>
        @endforeach
    </ul>
@endif
@endsection
