@extends('admin.layouts.app')

@section('title', 'Tambah Produk')
@section('page_title', 'Tambah Produk Baru')

@section('content')

<form id="formCreateProduk"
      action="{{ route('admin.produk.store') }}"
      method="POST"
      enctype="multipart/form-data">
@csrf

{{-- ① INFO PRODUK --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Informasi Produk</h3>
    </div>
    <div class="card-body">

        <div class="row">
            <div class="form-group col-md-6">
                <label>Nama Produk <span class="text-danger">*</span></label>
                <input type="text" name="nama_produk"
                       class="form-control @error('nama_produk') is-invalid @enderror"
                       value="{{ old('nama_produk') }}"
                       placeholder="Contoh: Paperbag Kraft Natural" required>
                @error('nama_produk')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Slug URL dibuat otomatis dari nama produk.</small>
            </div>

            <div class="form-group col-md-6">
                <label>Kategori <span class="text-danger">*</span></label>
                <select name="kategori_id"
                        class="form-control @error('kategori_id') is-invalid @enderror"
                        required>
                    <option value="">-- Pilih Kategori --</option>
                    @foreach($kategori as $kat)
                        <option value="{{ $kat->id }}"
                            {{ old('kategori_id') == $kat->id ? 'selected' : '' }}>
                            {{ $kat->nama_kategori }}
                        </option>
                    @endforeach
                </select>
                @error('kategori_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="form-group">
            <label>Deskripsi</label>
            <textarea name="deskripsi" rows="4" class="form-control @error('deskripsi') is-invalid @enderror"
                      placeholder="Deskripsi lengkap produk...">{{ old('deskripsi') }}</textarea>
            @error('deskripsi')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label>Minimal Pesan (pcs) <span class="text-danger">*</span></label>
            <input type="number" name="minimal_pesan"
                   class="form-control @error('minimal_pesan') is-invalid @enderror"
                   value="{{ old('minimal_pesan', 100) }}" min="1" required>
            @error('minimal_pesan')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label>Gambar Utama Produk</label>
            <input type="file" name="gambar"
                   class="form-control @error('gambar') is-invalid @enderror"
                   accept="image/*" onchange="previewGambarUtama(this)">
            @error('gambar')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <small class="text-muted">Format: JPG, PNG, WEBP. Maks: 2MB.</small>
            <div id="previewUtama" class="mt-2" style="display:none;">
                <img id="previewUtamaImg" src="" height="100"
                     class="rounded border" alt="Preview">
            </div>
        </div>

        <div class="form-group">
            <div class="custom-control custom-checkbox">
                <input type="checkbox" name="produk_kustom"
                       class="custom-control-input" id="produk_kustom"
                       {{ old('produk_kustom') ? 'checked' : '' }}>
                <label class="custom-control-label" for="produk_kustom">
                    Produk ini bisa dikustomisasi
                </label>
            </div>
        </div>

    </div>
</div>

{{-- ② GALERI PRODUK --}}
<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title">Galeri Produk</h3>
    </div>
    <div class="card-body">
        <div class="form-group">
            <label>Upload Foto Galeri</label>
            <input type="file" name="galeri[]"
                   class="form-control @error('galeri.*') is-invalid @enderror" accept="image/*" multiple>
            @error('galeri.*')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <small class="text-muted">
                Bisa pilih lebih dari satu foto. Format: JPG, PNG, WEBP. Maks: 2MB per foto.
            </small>
        </div>
        <div id="previewGaleri" class="d-flex flex-wrap gap-2 mt-2"></div>
    </div>
</div>

{{-- Tombol Submit --}}
<div class="mt-3 mb-4">
    <a href="{{ route('admin.produk.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Batal
    </a>
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i> Simpan Produk
    </button>
</div>

</form>

@endsection

@push('scripts')
<script>
    // ── Preview gambar utama ──
    function previewGambarUtama(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('previewUtamaImg').src = e.target.result;
                document.getElementById('previewUtama').style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // ── Preview galeri ──
    document.querySelector('input[name="galeri[]"]')
        .addEventListener('change', function () {
            const container = document.getElementById('previewGaleri');
            container.innerHTML = '';
            Array.from(this.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = e => {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.cssText =
                        'height:80px; width:100px; object-fit:cover; border-radius:6px; border:1px solid #ddd;';
                    container.appendChild(img);
                };
                reader.readAsDataURL(file);
            });
        });
</script>
@endpush