@extends('admin.layouts.app')

@section('title', 'Atribut Produk')
@section('page_title', 'Atribut: ' . $produk->nama_produk)

@section('content')

<div class="mb-3">
    <a href="{{ route('admin.produk.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Kembali ke Daftar Produk
    </a>
</div>

<div class="row">

    {{-- ================= BAHAN ================= --}}
    <div class="col-lg-4 col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">Bahan Produk</h3>
                <span class="badge badge-primary ml-auto" data-counter="bahan">
                    {{ $produk->bahan->count() }} dipilih
                </span>
            </div>

            <form action="{{ route('admin.produk.bahan.simpan', $produk) }}" method="POST" data-pilihan="bahan">
                @csrf @method('PUT')
                <div class="card-body">
                    @if ($semuaBahan->isEmpty())
                        <p class="text-muted text-center mb-0">
                            Belum ada data bahan.
                            <a href="{{ route('admin.bahan.index') }}">Tambah di Master Bahan</a>
                        </p>
                    @else
                        <div class="d-flex mb-3">
                            <input type="search" class="form-control form-control-sm mr-2"
                                placeholder="Cari bahan..." data-cari>
                            <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" data-pilih-semua>
                                Pilih semua
                            </button>
                        </div>

                        <div class="row">
                            @php $dipilih = $produk->bahan->pluck('id')->all(); @endphp
                            @foreach ($semuaBahan as $item)
                                <div class="col-sm-6" data-item="{{ strtolower($item->nama_bahan) }}">
                                    <div class="custom-control custom-checkbox mb-2">
                                        <input type="checkbox" class="custom-control-input"
                                            id="bahan{{ $item->id }}" name="bahan[]" value="{{ $item->id }}"
                                            {{ in_array($item->id, $dipilih) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-normal" for="bahan{{ $item->id }}">
                                            {{ $item->nama_bahan }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="{{ route('admin.bahan.index') }}" class="small">
                        <i class="fas fa-cog"></i> Kelola Master Bahan
                    </a>
                    @if ($semuaBahan->isNotEmpty())
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Simpan Bahan
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- ================= FINISHING ================= --}}
    <div class="col-lg-4 col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">Finishing Produk</h3>
                <span class="badge badge-primary ml-auto" data-counter="finishing">
                    {{ $produk->finishing->count() }} dipilih
                </span>
            </div>

            <form action="{{ route('admin.produk.finishing.simpan', $produk) }}" method="POST" data-pilihan="finishing">
                @csrf @method('PUT')
                <div class="card-body">
                    @if ($semuaFinishing->isEmpty())
                        <p class="text-muted text-center mb-0">
                            Belum ada data finishing.
                            <a href="{{ route('admin.finishing.index') }}">Tambah di Master Finishing</a>
                        </p>
                    @else
                        <div class="d-flex mb-3">
                            <input type="search" class="form-control form-control-sm mr-2"
                                placeholder="Cari finishing..." data-cari>
                            <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" data-pilih-semua>
                                Pilih semua
                            </button>
                        </div>

                        <div class="row">
                            @php $dipilih = $produk->finishing->pluck('id')->all(); @endphp
                            @foreach ($semuaFinishing as $item)
                                <div class="col-sm-6" data-item="{{ strtolower($item->nama_finishing) }}">
                                    <div class="custom-control custom-checkbox mb-2">
                                        <input type="checkbox" class="custom-control-input"
                                            id="finishing{{ $item->id }}" name="finishing[]" value="{{ $item->id }}"
                                            {{ in_array($item->id, $dipilih) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-normal" for="finishing{{ $item->id }}">
                                            {{ $item->nama_finishing }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="{{ route('admin.finishing.index') }}" class="small">
                        <i class="fas fa-cog"></i> Kelola Master Finishing
                    </a>
                    @if ($semuaFinishing->isNotEmpty())
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Simpan Finishing
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- ================= CETAK ================= --}}
    <div class="col-lg-4 col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">Jenis Cetak Produk</h3>
                <span class="badge badge-primary ml-auto" data-counter="cetak">
                    {{ $produk->cetak->count() }} dipilih
                </span>
            </div>

            <form action="{{ route('admin.produk.cetak.simpan', $produk) }}" method="POST" data-pilihan="cetak">
                @csrf @method('PUT')
                <div class="card-body">
                    @if ($semuaCetak->isEmpty())
                        <p class="text-muted text-center mb-0">
                            Belum ada data jenis cetak.
                            <a href="{{ route('admin.cetak.index') }}">Tambah di Master Cetak</a>
                        </p>
                    @else
                        <div class="d-flex mb-3">
                            <input type="search" class="form-control form-control-sm mr-2"
                                placeholder="Cari jenis cetak..." data-cari>
                            <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" data-pilih-semua>
                                Pilih semua
                            </button>
                        </div>

                        <div class="row">
                            @php $dipilih = $produk->cetak->pluck('id')->all(); @endphp
                            @foreach ($semuaCetak as $item)
                                <div class="col-sm-6" data-item="{{ strtolower($item->nama_cetak) }}">
                                    <div class="custom-control custom-checkbox mb-2">
                                        <input type="checkbox" class="custom-control-input"
                                            id="cetak{{ $item->id }}" name="cetak[]" value="{{ $item->id }}"
                                            {{ in_array($item->id, $dipilih) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-normal" for="cetak{{ $item->id }}">
                                            {{ $item->nama_cetak }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="{{ route('admin.cetak.index') }}" class="small">
                        <i class="fas fa-cog"></i> Kelola Master Cetak
                    </a>
                    @if ($semuaCetak->isNotEmpty())
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Simpan Cetak
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
    document.querySelectorAll('form[data-pilihan]').forEach(form => {
        const jenis     = form.dataset.pilihan;
        const counter   = document.querySelector(`[data-counter="${jenis}"]`);
        const items     = form.querySelectorAll('[data-item]');
        const checkbox  = form.querySelectorAll('input[type="checkbox"]');
        const cari      = form.querySelector('[data-cari]');
        const pilihSemua = form.querySelector('[data-pilih-semua]');

        // ── Hitung jumlah yang dicentang ──
        function updateCounter() {
            const jumlah = form.querySelectorAll('input[type="checkbox"]:checked').length;
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
