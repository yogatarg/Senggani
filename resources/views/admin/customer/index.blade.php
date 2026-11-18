@extends('admin.layouts.pesanan')

@section('title', 'Customer')

{{-- Variabel: $customer (paginator, dengan pesanan_count & spp_terakhir), $q --}}
@php
    $mencari = $q !== '';
    $angka = fn ($n) => number_format((int) $n, 0, ',', '.');
@endphp

@section('content')
<div class="sg-kepala">
    <div>
        <h1>Customer</h1>
        <p class="sg-kepala-sub">Customer dibuat otomatis saat menyimpan SPP. Mengubah data di sini tidak mengubah SPP lama.</p>
    </div>
</div>

<form method="GET" action="{{ route('admin.customer.index') }}" class="sg-alat sg-alat-customer" role="search" data-form-cari>
    <div class="sg-cari">
        <label for="cari-customer" class="visually-hidden">Cari customer</label>
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" id="cari-customer" name="q" value="{{ $q }}" maxlength="100" class="form-control"
               placeholder="Nama, WA, atau kode C-0001" enterkeyhint="search" autocomplete="off">
        <button type="button" class="sg-cari-hapus" data-hapus-cari aria-label="Hapus isi pencarian" @if (! $mencari) hidden @endif>
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>
    <button type="submit" class="sg-btn sg-btn-sekunder">Cari</button>
</form>

@if ($mencari)
    <p class="sg-info-baris" role="status">
        <span><strong>{{ $angka($customer->total()) }}</strong> customer cocok dengan “{{ $q }}”</span>
        <span aria-hidden="true">·</span>
        <a href="{{ route('admin.customer.index') }}">Hapus pencarian</a>
    </p>
@endif

@if ($customer->isEmpty())
    <div class="sg-kosong">
        <span class="sg-kosong-ikon"><i class="fa-solid {{ $mencari ? 'fa-magnifying-glass' : 'fa-address-book' }}" aria-hidden="true"></i></span>
        <h2>{{ $mencari ? 'Tidak ada customer untuk “' . $q . '”' : 'Belum ada customer' }}</h2>
        <p>{{ $mencari ? 'Periksa ejaan, atau cari dengan nomor WA atau kode customer.' : 'Customer otomatis tercatat saat SPP pertamanya disimpan.' }}</p>
        @if ($mencari)
            <a href="{{ route('admin.customer.index') }}" class="sg-btn sg-btn-sekunder">Hapus pencarian</a>
        @endif
    </div>
@else
    {{-- Desktop & tablet: tabel --}}
    <div class="d-none d-md-block table-responsive">
        <table class="sg-tabel">
            <caption class="visually-hidden">Daftar customer</caption>
            <thead>
                <tr>
                    <th scope="col">Customer</th>
                    <th scope="col">WhatsApp</th>
                    <th scope="col">Kota</th>
                    <th scope="col" class="text-end">SPP</th>
                    <th scope="col">SPP terakhir</th>
                    <th scope="col"><span class="visually-hidden">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($customer as $c)
                    <tr>
                        <td>
                            <a href="{{ route('admin.customer.show', $c) }}" class="sg-tabel-nama">{{ $c->nama }}</a>
                            <span class="d-block sg-kecil sg-muted sg-angka">{{ $c->kode }}</span>
                        </td>
                        <td class="sg-angka text-nowrap">{{ $c->whatsapp_tampil }}</td>
                        <td>{{ $c->kota ?: '—' }}</td>
                        <td class="sg-angka text-end">{{ $angka($c->pesanan_count) }}</td>
                        <td class="sg-angka">{{ $c->spp_terakhir ? 'SPP #' . $c->spp_terakhir : '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.customer.show', $c) }}" class="sg-btn sg-btn-sekunder sg-btn-kecil" aria-label="Lihat {{ $c->kode }} {{ $c->nama }}">Lihat</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- HP: kartu --}}
    <ul class="sg-kartu-pengguna d-md-none">
        @foreach ($customer as $c)
            <li class="sg-kartu-customer">
                <a href="{{ route('admin.customer.show', $c) }}" class="sg-tabel-nama stretched-link">{{ $c->nama }}</a>
                <p class="sg-kecil sg-muted sg-angka mb-2">{{ $c->kode }} · {{ $c->whatsapp_tampil }}@if ($c->kota) · {{ $c->kota }}@endif</p>
                <p class="d-flex align-items-center gap-2 flex-wrap m-0">
                    <span class="sg-chip-kecil sg-angka">{{ $angka($c->pesanan_count) }} SPP</span>
                    @if ($c->spp_terakhir)<span class="sg-kecil sg-muted sg-angka">terakhir SPP #{{ $c->spp_terakhir }}</span>@endif
                </p>
            </li>
        @endforeach
    </ul>

    @if ($customer->hasPages())
        <div class="sg-muat">
            @unless ($customer->onFirstPage())
                <a href="{{ $customer->previousPageUrl() }}" class="sg-btn sg-btn-sekunder"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Sebelumnya</a>
            @endunless
            @if ($customer->hasMorePages())
                <a href="{{ $customer->nextPageUrl() }}" class="sg-btn sg-btn-sekunder">Berikutnya <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            @endif
        </div>
    @endif
    <p class="sg-muted sg-kecil text-center mt-3 mb-0">Menampilkan {{ $angka($customer->firstItem()) }}–{{ $angka($customer->lastItem()) }} dari {{ $angka($customer->total()) }} customer</p>
@endif
@endsection
