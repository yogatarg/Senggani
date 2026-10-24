@extends('web.layouts.app')

@section('title', 'Kategori Produk')

@section('content')

    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5 fade-up">
                <h1 class="section-title">Kategori Produk</h1>
                <p class="text-muted mt-3 mb-0">Temukan berbagai jenis kemasan sesuai kebutuhan bisnis Anda</p>
            </div>

            <div class="row g-4 justify-content-center">
                @forelse ($kategori as $item)
                    @include('web.partials.kartu-kategori', ['item' => $item])
                @empty
                    <div class="col-12 text-center text-muted py-5">
                        <i class="fas fa-box-open fa-3x mb-3" style="color:#6a3131; opacity:0.4;" aria-hidden="true"></i>
                        <p class="mb-3">Belum ada kategori tersedia.</p>
                        <a href="{{ route('produk.index') }}" class="btn btn-outline-primary rounded-pill px-4">
                            Lihat Semua Produk
                        </a>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

@endsection
