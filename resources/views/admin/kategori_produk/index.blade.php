@extends('admin.layouts.pesanan')

@section('title', 'Kategori')

@php
    $hapusKategori = fn ($item) => [
        'aksi' => route('admin.kategori-produk.destroy', $item),
        'judul' => 'Hapus kategori ' . $item->nama_kategori . '?',
        'isi' => $item->produk_count
            ? $item->produk_count . ' produk di dalamnya juga akan terhapus. Tindakan ini tidak bisa dibatalkan.'
            : 'Kategori ini belum berisi produk.',
        'tombol' => $item->produk_count ? 'Hapus kategori & ' . $item->produk_count . ' produk' : 'Ya, hapus',
        'labelAria' => 'Hapus kategori ' . $item->nama_kategori,
    ];
@endphp

@section('content')
<div class="sg-kepala">
    <div>
        <h1>Kategori</h1>
        <p class="sg-kepala-sub">{{ $kategori->count() }} kategori</p>
    </div>
    <div class="sg-kepala-aksi">
        <a href="{{ route('admin.kategori-produk.create') }}" class="sg-btn sg-btn-utama"><i class="fa-solid fa-plus" aria-hidden="true"></i> Tambah Kategori</a>
    </div>
</div>

@if ($kategori->isEmpty())
    <div class="sg-kosong">
        <span class="sg-kosong-ikon"><i class="fa-solid fa-tags" aria-hidden="true"></i></span>
        <h2>Belum ada kategori</h2>
        <p>Kategori mengelompokkan produk di menu dan halaman katalog.</p>
        <a href="{{ route('admin.kategori-produk.create') }}" class="sg-btn sg-btn-utama"><i class="fa-solid fa-plus" aria-hidden="true"></i> Tambah Kategori</a>
    </div>
@else
    {{-- Desktop: tabel --}}
    <div class="d-none d-lg-block sg-tabel-gulir">
        <table class="sg-tabel sg-tabel-katalog">
            <thead>
                <tr>
                    <th scope="col">Kategori</th>
                    <th scope="col">Deskripsi</th>
                    <th scope="col">Jumlah produk</th>
                    <th scope="col"><span class="visually-hidden">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kategori as $item)
                    <tr>
                        <td>
                            <div class="sg-nama-sel">
                                @include('admin.katalog._thumb', ['src' => $item->gambar, 'kelas' => 'sg-thumb-32', 'w' => 72])
                                <span class="sg-nama-teks">
                                    <strong>{{ $item->nama_kategori }}</strong>
                                    <span class="sg-slug">{{ $item->slug }}</span>
                                </span>
                            </div>
                        </td>
                        <td class="sg-muted" style="max-width:360px"><span class="sg-klip-2">{{ $item->deskripsi ?: '—' }}</span></td>
                        <td><span class="sg-chip-kecil text-nowrap">{{ $item->produk_count }} produk</span></td>
                        <td class="text-end">
                            <div class="sg-tabel-aksi">
                                <a href="{{ route('admin.kategori-produk.edit', $item) }}" class="sg-btn sg-btn-sekunder" aria-label="Edit kategori {{ $item->nama_kategori }}"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>
                                @include('admin.katalog._hapus', $hapusKategori($item) + ['ikon' => true])
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- HP & tablet: kartu --}}
    <ul class="sg-kartu-katalog d-lg-none">
        @foreach ($kategori as $item)
            <li>
                <div class="sg-kk-kepala">
                    @include('admin.katalog._thumb', ['src' => $item->gambar, 'kelas' => 'sg-thumb-32', 'w' => 72])
                    <div style="min-width:0">
                        <h2 class="sg-kk-judul">{{ $item->nama_kategori }}</h2>
                        <p class="sg-kk-meta sg-satu-baris sg-slug">{{ $item->slug }}</p>
                        @if ($item->deskripsi)
                            <p class="sg-kk-deskripsi">{{ $item->deskripsi }}</p>
                        @endif
                        <p class="sg-kk-info"><span class="sg-chip-kecil">{{ $item->produk_count }} produk</span></p>
                    </div>
                </div>
                <div class="sg-kk-aksi">
                    <a href="{{ route('admin.kategori-produk.edit', $item) }}" class="sg-btn sg-btn-sekunder" aria-label="Edit kategori {{ $item->nama_kategori }}"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>
                    @include('admin.katalog._hapus', $hapusKategori($item))
                </div>
            </li>
        @endforeach
    </ul>
@endif
@endsection
