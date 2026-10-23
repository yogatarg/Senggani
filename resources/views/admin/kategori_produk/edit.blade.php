@extends('admin.layouts.app')

@section('title', 'Edit Kategori')
@section('page_title', 'Edit Kategori Produk')

@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.kategori-produk.update', $kategoriProduk) }}"
              method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')

            <div class="form-group">
                <label>Nama Kategori <span class="text-danger">*</span></label>
                <input type="text" name="nama_kategori"
                       class="form-control @error('nama_kategori') is-invalid @enderror"
                       value="{{ old('nama_kategori', $kategoriProduk->nama_kategori) }}"
                       required>
                @error('nama_kategori')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label>Slug saat ini</label>
                <input type="text" class="form-control"
                       value="{{ $kategoriProduk->slug }}" disabled>
                <small class="text-muted">Diperbarui otomatis jika nama diubah.</small>
            </div>

            <div class="form-group">
                <label>Deskripsi</label>
                <textarea name="deskripsi" rows="4"
                          class="form-control @error('deskripsi') is-invalid @enderror">{{ old('deskripsi', $kategoriProduk->deskripsi) }}</textarea>
                @error('deskripsi')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            {{-- INPUT GAMBAR --}}
            <div class="form-group">
                <label>Gambar Kategori</label>

                {{-- Tampilkan gambar saat ini --}}
                @if($kategoriProduk->gambar)
                    <div class="mb-2">
                        <img src="{{ asset('storage/' . $kategoriProduk->gambar) }}"
                             height="100" class="rounded border"
                             alt="{{ $kategoriProduk->nama_kategori }}"
                             id="previewImg">
                        <p class="text-muted small mt-1">
                            Gambar saat ini. Upload baru untuk mengganti.
                        </p>
                    </div>
                @else
                    <img id="previewImg" src="" height="100"
                         class="rounded border d-none" alt="Preview">
                @endif

                <input type="file" name="gambar"
                       class="form-control @error('gambar') is-invalid @enderror"
                       accept="image/*"
                       onchange="previewGambar(this)">
                @error('gambar')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Format: JPG, PNG, WEBP. Maks: 2MB.</small>
            </div>

            <a href="{{ route('admin.kategori-produk.index') }}"
               class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Update Kategori</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function previewGambar(input) {
        const img = document.getElementById('previewImg');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                img.src = e.target.result;
                img.classList.remove('d-none');
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
@endpush