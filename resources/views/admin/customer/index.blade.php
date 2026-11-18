@extends('admin.layouts.pesanan')

@section('title', 'Customer')

{{-- Variabel: $customer (paginator, dengan pesanan_count & spp_terakhir), $q --}}
@section('content')
<div class="sg-kepala">
    <div>
        <h1>Customer</h1>
        <p class="sg-kepala-sub">Customer dibuat otomatis saat menyimpan SPP. Mengubah data di sini tidak mengubah SPP lama.</p>
    </div>
</div>

<form method="GET" action="{{ route('admin.customer.index') }}" class="mb-3" role="search">
    <label for="cari-customer" class="visually-hidden">Cari customer</label>
    <div class="d-flex gap-2">
        <input type="search" id="cari-customer" name="q" value="{{ $q }}" maxlength="100" class="form-control" placeholder="Cari nama, nomor WA, atau kode (C-0001)">
        <button type="submit" class="sg-btn sg-btn-sekunder">Cari</button>
    </div>
</form>

@if ($customer->isEmpty())
    <p class="sg-muted">{{ $q !== '' ? 'Tidak ada customer yang cocok.' : 'Belum ada customer.' }}</p>
@else
    <div class="table-responsive">
        <table class="sg-tabel">
            <thead>
                <tr><th scope="col">Kode</th><th scope="col">Nama</th><th scope="col">WhatsApp</th><th scope="col">Kota</th><th scope="col">SPP</th><th scope="col">SPP terakhir</th></tr>
            </thead>
            <tbody>
                @foreach ($customer as $c)
                    <tr>
                        <td class="sg-angka">{{ $c->kode }}</td>
                        <td><a href="{{ route('admin.customer.show', $c) }}">{{ $c->nama }}</a></td>
                        <td class="sg-angka">{{ $c->whatsapp_tampil }}</td>
                        <td>{{ $c->kota ?? '—' }}</td>
                        <td class="sg-angka">{{ $c->pesanan_count }}</td>
                        <td class="sg-angka">{{ $c->spp_terakhir ? '#' . $c->spp_terakhir : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($customer->hasPages())
        <div class="sg-muat">
            @unless ($customer->onFirstPage())
                <a href="{{ $customer->previousPageUrl() }}" class="sg-btn sg-btn-sekunder">Sebelumnya</a>
            @endunless
            @if ($customer->hasMorePages())
                <a href="{{ $customer->nextPageUrl() }}" class="sg-btn sg-btn-sekunder">Berikutnya</a>
            @endif
        </div>
        <p class="sg-muted sg-kecil text-center mt-2 mb-0">Menampilkan {{ $customer->firstItem() }}–{{ $customer->lastItem() }} dari {{ $customer->total() }} customer</p>
    @endif
@endif
@endsection
