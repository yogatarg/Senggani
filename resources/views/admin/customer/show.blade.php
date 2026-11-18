@extends('admin.layouts.pesanan')

@section('title', $customer->kode . ' · ' . $customer->nama)
@section('kembali', route('admin.customer.index'))

{{-- Variabel: $customer, $pesanan (paginator SPP milik customer, nomor terbesar dulu), $bisaHapus --}}
@section('content')
<div class="sg-kepala">
    <div>
        <a href="{{ route('admin.customer.index') }}" class="sg-remah d-none d-lg-inline-flex"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Customer</a>
        <h1>{{ $customer->nama }}</h1>
        <p class="sg-kepala-sub sg-angka">{{ $customer->kode }} · {{ $customer->whatsapp_tampil }}@if ($customer->kota) · {{ $customer->kota }}@endif</p>
    </div>
    <div class="sg-kepala-aksi">
        <a href="{{ route('admin.customer.edit', $customer) }}" class="sg-btn sg-btn-sekunder"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>
        @if ($bisaHapus)
            <form method="POST" action="{{ route('admin.customer.destroy', $customer) }}" data-kirim>
                @csrf @method('DELETE')
                <button type="submit" class="sg-btn sg-btn-sekunder"><i class="fa-solid fa-trash" aria-hidden="true"></i> Hapus customer</button>
            </form>
        @endif
    </div>
</div>

@if ($customer->alamat)
    <section class="sg-kartu mb-3" aria-labelledby="judul-alamat">
        <h2 class="sg-kartu-judul" id="judul-alamat">Alamat</h2>
        <p class="sg-teks-catatan mb-0">{{ $customer->alamat }}@if ($customer->kota), {{ $customer->kota }}@endif</p>
    </section>
@endif

<section aria-labelledby="judul-riwayat-spp">
    <h2 class="sg-kartu-judul" id="judul-riwayat-spp">Riwayat SPP ({{ $pesanan->total() }})</h2>
    @if ($pesanan->isEmpty())
        <p class="sg-muted">Belum ada SPP.</p>
    @else
        <div class="sg-daftar">
            @foreach ($pesanan as $p)
                @include('admin.pesanan._kartu', ['p' => $p])
            @endforeach
        </div>
        @if ($pesanan->hasPages())
            <div class="sg-muat">
                @unless ($pesanan->onFirstPage())
                    <a href="{{ $pesanan->previousPageUrl() }}" class="sg-btn sg-btn-sekunder">Sebelumnya</a>
                @endunless
                @if ($pesanan->hasMorePages())
                    <a href="{{ $pesanan->nextPageUrl() }}" class="sg-btn sg-btn-sekunder">Berikutnya</a>
                @endif
            </div>
        @endif
    @endif
</section>
@endsection
