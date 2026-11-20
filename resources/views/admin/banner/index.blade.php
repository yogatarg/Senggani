@extends('admin.layouts.pesanan')

@section('title', 'Banner')

@php
    $hapusBanner = fn ($item) => [
        'aksi' => route('admin.banner.destroy', $item),
        'judul' => 'Hapus banner ' . $item->judul . '?',
        'isi' => 'Banner tidak akan tampil lagi di website. Tindakan ini tidak bisa dibatalkan.',
        'labelAria' => 'Hapus banner ' . $item->judul,
    ];
@endphp

@section('content')
<div class="sg-kepala">
    <div>
        <h1>Banner</h1>
        <p class="sg-kepala-sub">{{ $banner->count() }} banner · {{ $banner->where('aktif', true)->count() }} aktif</p>
    </div>
    <div class="sg-kepala-aksi">
        <a href="{{ route('admin.banner.create') }}" class="sg-btn sg-btn-utama"><i class="fa-solid fa-plus" aria-hidden="true"></i> Tambah Banner</a>
    </div>
</div>

@if ($banner->isEmpty())
    <div class="sg-kosong">
        <span class="sg-kosong-ikon"><i class="fa-solid fa-images" aria-hidden="true"></i></span>
        <h2>Belum ada banner</h2>
        <p>Banner aktif tampil bergantian di beranda website.</p>
        <a href="{{ route('admin.banner.create') }}" class="sg-btn sg-btn-utama"><i class="fa-solid fa-plus" aria-hidden="true"></i> Tambah Banner</a>
    </div>
@else
    {{-- Desktop: tabel --}}
    <div class="d-none d-lg-block sg-tabel-gulir">
        <table class="sg-tabel sg-tabel-katalog">
            <thead>
                <tr>
                    <th scope="col">Gambar</th>
                    <th scope="col">Judul</th>
                    <th scope="col">Tautan</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="visually-hidden">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($banner as $item)
                    <tr>
                        <td>
                            <div class="sg-thumb-pasang">
                                <figure>
                                    @include('admin.katalog._thumb', ['src' => $item->gambar, 'kelas' => 'sg-thumb-125', 'w' => 116])
                                    <figcaption>PC</figcaption>
                                </figure>
                                <figure>
                                    @include('admin.katalog._thumb', ['src' => $item->gambar_mobile, 'kelas' => 'sg-thumb-34', 'w' => 36])
                                    <figcaption>HP</figcaption>
                                </figure>
                            </div>
                            @unless ($item->gambar_mobile)
                                <span class="sg-lengkap sg-lengkap-belum mt-2"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> Gambar HP belum ada</span>
                            @endunless
                        </td>
                        <td style="max-width:320px">
                            <strong class="d-block fw-semibold" style="overflow-wrap:anywhere">{{ $item->judul }}</strong>
                            @if ($item->deskripsi)
                                <span class="sg-klip-2 sg-kecil sg-muted">{{ $item->deskripsi }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($item->tautan)
                                <a href="{{ $item->tautan }}" target="_blank" rel="noopener" class="sg-tautan-luar sg-kecil">{{ \Illuminate\Support\Str::limit(preg_replace('#^https?://#', '', $item->tautan), 32) }} <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span class="visually-hidden">(tab baru)</span></a>
                            @else
                                <span class="sg-muted">—</span>
                            @endif
                        </td>
                        <td>@include('admin.banner._tampil', ['item' => $item])</td>
                        <td class="text-end">
                            <div class="sg-tabel-aksi">
                                <a href="{{ route('admin.banner.edit', $item) }}" class="sg-btn sg-btn-sekunder" aria-label="Edit banner {{ $item->judul }}"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>
                                @include('admin.katalog._hapus', $hapusBanner($item) + ['ikon' => true])
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- HP & tablet: kartu --}}
    <ul class="sg-kartu-katalog d-lg-none">
        @foreach ($banner as $item)
            <li>
                <div class="sg-kk-banner-gambar">
                    <div class="sg-kk-banner-pc">
                        @if ($item->gambar)
                            <img src="{{ asset('storage/' . $item->gambar) }}" alt="" width="600" height="250" loading="lazy">
                        @else
                            <i class="fa-regular fa-image" aria-hidden="true"></i><span class="visually-hidden">Belum ada gambar desktop</span>
                        @endif
                    </div>
                    @if ($item->gambar_mobile)
                        <span class="sg-thumb sg-thumb-34 sg-kk-banner-hp"><img src="{{ asset('storage/' . $item->gambar_mobile) }}" alt="" width="36" height="48" loading="lazy"></span>
                    @endif
                </div>
                <div class="sg-kk-badan">
                    <h2 class="sg-kk-judul">{{ $item->judul }}</h2>
                    @if ($item->deskripsi)
                        <p class="sg-kk-deskripsi">{{ $item->deskripsi }}</p>
                    @endif
                    @if ($item->tautan)
                        <a href="{{ $item->tautan }}" target="_blank" rel="noopener" class="sg-tautan-luar sg-kecil"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> {{ \Illuminate\Support\Str::limit(preg_replace('#^https?://#', '', $item->tautan), 32) }}<span class="visually-hidden">(tab baru)</span></a>
                    @endif
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                        @include('admin.banner._tampil', ['item' => $item])
                        @unless ($item->gambar_mobile)
                            <span class="sg-lengkap sg-lengkap-belum"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> Gambar HP belum ada</span>
                        @endunless
                    </div>
                </div>
                <div class="sg-kk-aksi">
                    <a href="{{ route('admin.banner.edit', $item) }}" class="sg-btn sg-btn-sekunder" aria-label="Edit banner {{ $item->judul }}"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>
                    @include('admin.katalog._hapus', $hapusBanner($item))
                </div>
            </li>
        @endforeach
    </ul>
@endif
@endsection
