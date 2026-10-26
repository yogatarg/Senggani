@extends('web.layouts.app')

@section('title', 'Halaman Tidak Ditemukan | ' . (config('usaha.nama') ?: 'Senggani Paperbag & Packaging'))
@section('meta_description', 'Halaman yang Anda cari tidak ditemukan. Lihat produk kemasan kami atau hubungi kami via WhatsApp.')
@section('robots', 'noindex, follow')

@php
    $waHalaman404 = preg_replace('/\D+/', '', (string) (config('usaha.whatsapp') ?: '6283829208316'));
    $pesanWa404 = 'Halo ' . (config('usaha.nama') ?: 'Senggani Paperbag & Packaging') . ', saya ingin bertanya tentang kemasan.';
@endphp

@push('styles')
    <style>
        .halaman-404 {
            padding: 72px 0 88px;
        }

        .halaman-404 .kode-404 {
            font-size: 5.5rem;
            font-weight: 700;
            line-height: 1;
            color: var(--primary);
            letter-spacing: -0.02em;
        }

        .halaman-404 .ikon-404 {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: #f9f3f3;
            color: var(--primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.25rem;
        }

        .halaman-404 .aksi-404 .btn {
            font-weight: 600;
            padding: 0.7rem 1.5rem;
        }

        .halaman-404 .aksi-404 .btn:focus-visible {
            outline: 3px solid var(--accent);
            outline-offset: 2px;
        }

        @media (max-width: 575.98px) {
            .halaman-404 {
                padding: 48px 0 64px;
            }

            .halaman-404 .kode-404 {
                font-size: 4rem;
            }

            .halaman-404 .aksi-404 .btn {
                width: 100%;
            }
        }
    </style>
@endpush

@section('content')
    <section class="halaman-404 text-center" aria-labelledby="judul-404">
        <div class="container" style="max-width: 640px;">
            <div class="ikon-404 mb-4" aria-hidden="true"><i class="fas fa-box-open"></i></div>
            <p class="kode-404 mb-2" aria-hidden="true">404</p>
            <h1 class="h3 fw-bold mb-3" id="judul-404" style="color:var(--primary);">Halaman tidak ditemukan</h1>
            <p class="text-muted mb-4">
                Maaf, halaman yang Anda cari mungkin sudah dipindahkan atau alamatnya salah ketik.
                Silakan lihat produk kami atau tanyakan langsung lewat WhatsApp.
            </p>
            <div class="aksi-404 d-flex flex-column flex-sm-row flex-wrap justify-content-center gap-2">
                <a href="{{ route('home') }}" class="btn btn-primary rounded-pill">
                    <i class="fas fa-home me-2" aria-hidden="true"></i>Ke Beranda
                </a>
                <a href="{{ route('produk.index') }}" class="btn btn-outline-primary rounded-pill">
                    <i class="fas fa-box me-2" aria-hidden="true"></i>Lihat Produk
                </a>
                <a href="https://wa.me/{{ $waHalaman404 }}?text={{ rawurlencode($pesanWa404) }}" target="_blank"
                    rel="noopener" class="btn btn-success rounded-pill">
                    <i class="fab fa-whatsapp me-2" aria-hidden="true"></i>Chat WhatsApp
                </a>
            </div>
        </div>
    </section>
@endsection
