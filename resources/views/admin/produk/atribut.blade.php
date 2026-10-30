@extends('admin.layouts.app')

@section('title', 'Atribut Produk')
@section('page_title', 'Atribut: ' . $produk->nama_produk)

@section('content')

@php
    // Tiga daftar centang dalam satu form, disimpan sekaligus dengan satu tombol
    $kelompok = [
        ['jenis' => 'bahan', 'judul' => 'Bahan Produk', 'label' => 'bahan', 'semua' => $semuaBahan,
         'dipilih' => $produk->bahan->pluck('id')->all(), 'nama' => 'nama_bahan',
         'master' => route('admin.bahan.index'), 'labelMaster' => 'Master Bahan'],
        ['jenis' => 'cetak', 'judul' => 'Jenis Cetak Produk', 'label' => 'jenis cetak', 'semua' => $semuaCetak,
         'dipilih' => $produk->cetak->pluck('id')->all(), 'nama' => 'nama_cetak',
         'master' => route('admin.cetak.index'), 'labelMaster' => 'Master Cetak'],
        ['jenis' => 'finishing', 'judul' => 'Finishing Produk', 'label' => 'finishing', 'semua' => $semuaFinishing,
         'dipilih' => $produk->finishing->pluck('id')->all(), 'nama' => 'nama_finishing',
         'master' => route('admin.finishing.index'), 'labelMaster' => 'Master Finishing'],
    ];
@endphp

<div class="mb-3">
    <a href="{{ route('admin.produk.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Kembali ke Daftar Produk
    </a>
</div>

<form action="{{ route('admin.produk.atribut.simpan', $produk) }}" method="POST">
    @csrf @method('PUT')

    <div class="row">
        @foreach ($kelompok as $k)
            <div class="col-lg-4 col-md-6">
                <div class="card" data-pilihan="{{ $k['jenis'] }}">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title">{{ $k['judul'] }}</h3>
                        <span class="badge badge-primary ml-auto" data-counter>
                            {{ count($k['dipilih']) }} dipilih
                        </span>
                    </div>

                    <div class="card-body">
                        @if ($k['semua']->isEmpty())
                            <p class="text-muted text-center mb-0">
                                Belum ada data {{ $k['label'] }}.
                                <a href="{{ $k['master'] }}">Tambah di {{ $k['labelMaster'] }}</a>
                            </p>
                        @else
                            <div class="d-flex mb-3">
                                <input type="search" class="form-control form-control-sm mr-2"
                                    placeholder="Cari {{ $k['label'] }}..." data-cari>
                                <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" data-pilih-semua>
                                    Pilih semua
                                </button>
                            </div>

                            <div class="row">
                                @foreach ($k['semua'] as $item)
                                    <div class="col-sm-6" data-item="{{ strtolower($item->{$k['nama']}) }}">
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input type="checkbox" class="custom-control-input"
                                                id="{{ $k['jenis'] . $item->id }}" name="{{ $k['jenis'] }}[]" value="{{ $item->id }}"
                                                {{ in_array($item->id, $k['dipilih']) ? 'checked' : '' }}>
                                            <label class="custom-control-label font-weight-normal" for="{{ $k['jenis'] . $item->id }}">
                                                {{ $item->{$k['nama']} }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="card-footer">
                        <a href="{{ $k['master'] }}" class="small">
                            <i class="fas fa-cog"></i> Kelola {{ $k['labelMaster'] }}
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap">
            <span class="text-muted small mb-2 mb-md-0">
                Bahan, jenis cetak, dan finishing disimpan sekaligus.
            </span>
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Simpan Semua
            </button>
        </div>
    </div>
</form>

@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-pilihan]').forEach(kartu => {
        const counter    = kartu.querySelector('[data-counter]');
        const items      = kartu.querySelectorAll('[data-item]');
        const checkbox   = kartu.querySelectorAll('input[type="checkbox"]');
        const cari       = kartu.querySelector('[data-cari]');
        const pilihSemua = kartu.querySelector('[data-pilih-semua]');

        // ── Hitung jumlah yang dicentang ──
        function updateCounter() {
            const jumlah = kartu.querySelectorAll('input[type="checkbox"]:checked').length;
            counter.textContent = jumlah + ' dipilih';
        }
        checkbox.forEach(cb => cb.addEventListener('change', updateCounter));

        // ── Pencarian ──
        cari?.addEventListener('input', function () {
            const kata = this.value.toLowerCase().trim();
            items.forEach(el => {
                el.style.display = el.dataset.item.includes(kata) ? '' : 'none';
            });
        });

        // Enter di kotak cari tidak mengirim form
        cari?.addEventListener('keydown', e => {
            if (e.key === 'Enter') e.preventDefault();
        });

        // ── Pilih / batal pilih semua (yang sedang tampil) ──
        pilihSemua?.addEventListener('click', function () {
            const tampil = Array.from(items)
                .filter(el => el.style.display !== 'none')
                .map(el => el.querySelector('input'));
            const semuaTercentang = tampil.every(cb => cb.checked);
            tampil.forEach(cb => cb.checked = !semuaTercentang);
            this.textContent = semuaTercentang ? 'Pilih semua' : 'Batal pilih';
            updateCounter();
        });
    });
</script>
@endpush
