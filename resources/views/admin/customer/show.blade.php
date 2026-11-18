@extends('admin.layouts.pesanan')

@section('title', $customer->kode . ' · ' . $customer->nama)
@section('judul_bar', $customer->nama)
@section('kembali', route('admin.customer.index'))

{{-- Variabel: $customer, $pesanan (paginator SPP milik customer, nomor terbesar dulu), $bisaHapus --}}
@php
    $linkWa = 'https://wa.me/' . $customer->nomor_whatsapp;
@endphp

@section('content')
<div class="sg-kepala">
    <div style="min-width:0">
        <a href="{{ route('admin.customer.index') }}" class="sg-remah d-none d-lg-inline-flex"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Customer</a>
        <p class="sg-order-no m-0">{{ $customer->kode }}</p>
        <h1 class="text-break">{{ $customer->nama }}</h1>
        <p class="sg-kepala-sub sg-angka">{{ $customer->whatsapp_tampil }}@if ($customer->kota) · {{ $customer->kota }}@endif</p>
    </div>
    <div class="sg-kepala-aksi sg-customer-aksi">
        <a href="{{ $linkWa }}" target="_blank" rel="noopener" class="sg-btn sg-btn-wa"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp<span class="visually-hidden"> (tab baru)</span></a>
        <a href="{{ route('admin.customer.edit', $customer) }}" class="sg-btn sg-btn-sekunder" style="min-height:44px"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>
        @if ($bisaHapus)
            <button type="button" class="sg-btn sg-btn-bahaya" style="min-height:44px" data-bs-toggle="modal" data-bs-target="#modalHapusCustomer"><i class="fa-solid fa-trash-can" aria-hidden="true"></i> Hapus</button>
        @endif
    </div>
</div>

<div class="sg-customer-tata">
    <section class="sg-kartu" aria-labelledby="judul-data-customer">
        <h2 class="sg-kartu-judul" id="judul-data-customer">Data customer</h2>
        <dl class="sg-dl">
            <dt>Kode</dt><dd class="sg-angka">{{ $customer->kode }}</dd>
            <dt>WhatsApp</dt><dd class="sg-angka">{{ $customer->whatsapp_tampil }}</dd>
            <dt>Alamat</dt><dd class="sg-teks-catatan">{{ $customer->alamat ?: '—' }}</dd>
            <dt>Kota</dt><dd>{{ $customer->kota ?: '—' }}</dd>
            <dt>Jumlah SPP</dt><dd class="sg-angka">{{ $pesanan->total() }}</dd>
        </dl>
        <p class="sg-bantu mb-0">Alamat &amp; kota di tiap SPP bisa berbeda; SPP memakai data saat dibuat.</p>
        @unless ($bisaHapus)
            <p class="sg-bantu mb-0">Customer yang sudah punya SPP tidak bisa dihapus.</p>
        @endunless
    </section>

    <section aria-labelledby="judul-riwayat-spp" style="min-width:0">
        <h2 class="sg-kartu-judul" id="judul-riwayat-spp">Riwayat SPP <span class="sg-chip-kecil sg-angka">{{ $pesanan->total() }}</span></h2>
        @if ($pesanan->isEmpty())
            <div class="sg-kosong sg-kartu">
                <span class="sg-kosong-ikon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></span>
                <h3 class="fs-6 fw-semibold">Belum ada SPP</h3>
                <p class="mb-0">Customer ini belum punya SPP.</p>
            </div>
        @else
            <div class="sg-daftar sg-daftar-customer">
                @foreach ($pesanan as $p)
                    @include('admin.pesanan._kartu', ['p' => $p, 'tanpaKode' => true, 'tingkat' => 'h3'])
                @endforeach
            </div>
            @if ($pesanan->hasPages())
                <div class="sg-muat">
                    @unless ($pesanan->onFirstPage())
                        <a href="{{ $pesanan->previousPageUrl() }}" class="sg-btn sg-btn-sekunder"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Sebelumnya</a>
                    @endunless
                    @if ($pesanan->hasMorePages())
                        <a href="{{ $pesanan->nextPageUrl() }}" class="sg-btn sg-btn-sekunder">Berikutnya <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    @endif
                </div>
                <p class="sg-muted sg-kecil text-center mt-2 mb-0">Menampilkan {{ $pesanan->firstItem() }}–{{ $pesanan->lastItem() }} dari {{ $pesanan->total() }} SPP</p>
            @endif
        @endif
    </section>
</div>

@if ($bisaHapus)
    <div class="modal fade modal-sheet" id="modalHapusCustomer" tabindex="-1" aria-labelledby="modalHapusCustomerJudul" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.customer.destroy', $customer) }}" data-kirim>
                @csrf @method('DELETE')
                <div class="modal-header">
                    <h2 class="modal-title" id="modalHapusCustomerJudul">Hapus customer {{ $customer->kode }}?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="m-0"><strong>{{ $customer->nama }}</strong> belum punya SPP, jadi aman dihapus. Tindakan ini tidak bisa dibatalkan.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="sg-btn sg-btn-sekunder" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="sg-btn sg-btn-bahaya-isi" data-teks-kirim="Menghapus…">Ya, hapus</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
