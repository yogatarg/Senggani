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

                    <textarea name="deskripsi" rows="4" class="form-control">{{ old('deskripsi', $banner->deskripsi) }}</textarea>
                </div>

                <div class="form-group">
                    <label>Gambar Banner</label>
                    @if ($banner->gambar)
                        <div class="mb-2">
                            <img src="{{ asset('storage/' . $banner->gambar) }}" height="80" alt="{{ $banner->judul }}">
                            <p class="text-muted text-sm">Biarkan kosong jika tidak ingin mengganti gambar.</p>
                        </div>
                    @endif
                    <input type="file" name="gambar" class="form-control @error('gambar') is-invalid @enderror">
                    @error('gambar')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label>Gambar Mobile</label>

                    @if ($banner->gambar_mobile)
                        <div class="mb-2">
                            <img src="{{ asset('storage/' . $banner->gambar_mobile) }}" height="80">
                        </div>
                    @endif

                    <input type="file" name="gambar_mobile" class="form-control">

                    <small class="text-muted">
                        Kosongkan jika tidak ingin mengganti gambar mobile.
                    </small>
                </div>

                <div class="form-group">
                    <label>Tautan (opsional)</label>
                    <input type="url" name="tautan" class="form-control" value="{{ old('tautan', $banner->tautan) }}"
                        placeholder="https://...">
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
