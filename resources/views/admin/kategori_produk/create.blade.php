@extends('admin.layouts.app')

@section('title', 'Tambah Kategori')
@section('page_title', 'Tambah Kategori Produk')

@section('content')
<div class="card">
    <div class="card-body">
        {{-- Tambahkan enctype untuk upload file --}}
        <form action="{{ route('admin.kategori-produk.store') }}"
              method="POST" enctype="multipart/form-data">
            @csrf

            <div class="form-group">
                <label>Nama Kategori <span class="text-danger">*</span></label>
                <input type="text" name="nama_kategori"
                       class="form-control @error('nama_kategori') is-invalid @enderror"
                       value="{{ old('nama_kategori') }}" required>
                @error('nama_kategori')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Slug dibuat otomatis.</small>
            </div>

            <div class="form-group">
                <label>Deskripsi</label>
                <textarea name="deskripsi" rows="4"
                          class="form-control">{{ old('deskripsi') }}</textarea>
            </div>

            {{-- INPUT GAMBAR --}}
            <div class="form-group">
                <label>Gambar Kategori</label>
                <input type="file" name="gambar"
                       class="form-control @error('gambar') is-invalid @enderror"
                       accept="image/*"
                       onchange="previewGambar(this)">
                @error('gambar')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Format: JPG, PNG, WEBP. Maks: 2MB.</small>

                {{-- Preview gambar sebelum upload --}}
                <div id="previewContainer" class="mt-2" style="display:none;">
                    <img id="previewImg" src="" height="100"
                         class="rounded border" alt="Preview">
                </div>
            </div>

            <a href="{{ route('admin.kategori-produk.index') }}"
               class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function previewGambar(input) {
        const container = document.getElementById('previewContainer');
        const img = document.getElementById('previewImg');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                img.src = e.target.result;
                container.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
@endpush