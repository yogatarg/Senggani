@extends('admin.layouts.app')

@section('title', 'Edit Produk')
@section('page_title', 'Edit Produk: ' . $produk->nama_produk)

@section('content')

    {{-- ① INFO PRODUK --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Informasi Produk</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.produk.update', $produk) }}" method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="row">
                    <div class="form-group col-md-6">
                        <label>Nama Produk</label>
                        <input type="text" name="nama_produk" class="form-control @error('nama_produk') is-invalid @enderror"
                            value="{{ old('nama_produk', $produk->nama_produk) }}" required>
                        @error('nama_produk')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group col-md-6">
                        <label>Kategori</label>
                        <select name="kategori_id" class="form-control @error('kategori_id') is-invalid @enderror" required>
                            @foreach ($kategori as $kat)
                                <option value="{{ $kat->id }}"
                                    {{ old('kategori_id', $produk->kategori_id) == $kat->id ? 'selected' : '' }}>
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
                    <textarea name="deskripsi" class="form-control @error('deskripsi') is-invalid @enderror" rows="4">{{ old('deskripsi', $produk->deskripsi) }}</textarea>
                    @error('deskripsi')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Minimal Pesan (pcs)</label>
                            <input type="number" name="minimal_pesan" class="form-control @error('minimal_pesan') is-invalid @enderror"
                                value="{{ old('minimal_pesan', $produk->minimal_pesan) }}" min="1" required>
                            <small class="form-text text-muted">Isi 1 bila tanpa minimum order.</small>
                            @error('minimal_pesan')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Gambar Utama</label>
                    @if ($produk->gambar)
                        <div class="mb-2">
                            <img src="{{ asset('storage/' . $produk->gambar) }}" height="80">
                        </div>
                    @endif
                    <input type="file" name="gambar" class="form-control @error('gambar') is-invalid @enderror">
                    @error('gambar')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted d-block">
                        Rasio 4:3 (mendatar), disarankan 1200×900 px, produk di tengah dengan sedikit ruang kosong di sekeliling (tepi atas-bawah atau kiri-kanan bisa terpotong di kartu & halaman detail).
                        Format: JPG, PNG, WEBP. Gambar otomatis dikompres; maks 2 MB.
                    </small>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" name="produk_kustom" class="custom-control-input" id="produk_kustom"
                            {{ old('produk_kustom', $produk->produk_kustom) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="produk_kustom">
                            Produk ini bisa dikustomisasi
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Update Produk</button>
            </form>
        </div>
    </div>

    {{-- ② GALERI --}}
    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title">Galeri Produk</h3>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                @foreach ($produk->galeri as $foto)
                    <div class="col-md-2 text-center mb-2">
                        <img src="{{ asset('storage/' . $foto->nama_file) }}" class="img-thumbnail" height="80">
                        <form action="{{ route('admin.galeri.hapus', $foto->id) }}" method="POST"
                            onsubmit="return confirm('Hapus foto?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger btn-xs mt-1">Hapus</button>
                        </form>
                    </div>
                @endforeach
            </div>

            <form action="{{ route('admin.produk.galeri.tambah', $produk) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label>Upload Foto (bisa lebih dari satu)</label>
                    <input type="file" name="gambar[]" class="form-control @error('gambar.*') is-invalid @enderror" multiple>
                    @error('gambar.*')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted d-block">
                        Rasio 4:3 (mendatar), disarankan 1200×900 px, produk di tengah dengan sedikit ruang kosong di sekeliling (tepi atas-bawah atau kiri-kanan bisa terpotong di kartu & halaman detail).
                        Format: JPG, PNG, WEBP. Gambar otomatis dikompres; maks 2 MB per foto.
                    </small>
                </div>
                <button type="submit" class="btn btn-success">Upload Galeri</button>
            </form>
        </div>
    </div>

@endsection
