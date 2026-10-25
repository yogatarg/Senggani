@extends('admin.layouts.app')

@section('title', 'Edit Banner')
@section('page_title', 'Edit Banner')

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.banner.update', $banner) }}" method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')

                <div class="form-group">
                    <label>Judul</label>
                    <input type="text" name="judul" class="form-control @error('judul') is-invalid @enderror"
                        value="{{ old('judul', $banner->judul) }}" required>
                    @error('judul')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label>Deskripsi</label>

                    <textarea name="deskripsi" rows="4" class="form-control @error('deskripsi') is-invalid @enderror">{{ old('deskripsi', $banner->deskripsi) }}</textarea>
                    @error('deskripsi')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label>Gambar Banner</label>
                    @if ($banner->gambar)
                        <div class="mb-2">
                            <img src="{{ asset('storage/' . $banner->gambar) }}" height="80" alt="{{ $banner->judul }}">
                            <p class="text-muted text-sm">Biarkan kosong jika tidak ingin mengganti gambar.</p>
                        </div>
                    @endif
                    <input type="file" name="gambar" accept=".jpg,.jpeg,.png,.webp"
                        class="form-control @error('gambar') is-invalid @enderror">
                    @error('gambar')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label for="gambar_mobile">Gambar Mobile
                        @unless ($banner->gambar_mobile)
                            <span class="text-danger">*</span>
                        @endunless
                    </label>

                    @if ($banner->gambar_mobile)
                        <div class="mb-2">
                            <img src="{{ asset('storage/' . $banner->gambar_mobile) }}" height="80"
                                alt="Gambar mobile {{ $banner->judul }}">
                        </div>
                    @endif

                    <input type="file" name="gambar_mobile" id="gambar_mobile" accept=".jpg,.jpeg,.png,.webp"
                        class="form-control @error('gambar_mobile') is-invalid @enderror"
                        @unless ($banner->gambar_mobile) required @endunless>
                    @error('gambar_mobile')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror

                    <small class="text-muted">
                        Tampil di HP. Rasio 3:4 (tegak), disarankan 1080×1440 px. Kosongkan jika tidak ingin mengganti;
                        wajib diisi bila banner belum punya gambar mobile.
                    </small>
                </div>

                <div class="form-group">
                    <label>Tautan (opsional)</label>
                    <input type="url" name="tautan" class="form-control @error('tautan') is-invalid @enderror" value="{{ old('tautan', $banner->tautan) }}"
                        placeholder="https://...">
                    @error('tautan')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" name="aktif" class="custom-control-input" id="aktif"
                            {{ (old('_token') ? old('aktif') : $banner->aktif) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="aktif">Tampilkan banner di website</label>
                    </div>
                </div>

                <a href="{{ route('admin.banner.index') }}" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Update Banner</button>
            </form>
        </div>
    </div>
@endsection
