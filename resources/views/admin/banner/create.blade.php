@extends('admin.layouts.app')

@section('title', 'Tambah Banner')
@section('page_title', 'Tambah Banner')

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.banner.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="form-group">
                    <label>Judul</label>
                    <input type="text" name="judul" class="form-control @error('judul') is-invalid @enderror"
                        value="{{ old('judul') }}">
                    @error('judul')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label>Deskripsi</label>

                    <textarea name="deskripsi" rows="4" class="form-control @error('deskripsi') is-invalid @enderror">{{ old('deskripsi') }}</textarea>

                    @error('deskripsi')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="form-group">
                    <label>Gambar Desktop</label>
                    <input type="file" name="gambar" class="form-control @error('gambar') is-invalid @enderror">

                    @error('gambar')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label>Gambar Mobile</label>

                    <input type="file" name="gambar_mobile"
                        class="form-control @error('gambar_mobile') is-invalid @enderror">

                    <small class="text-muted">
                        Opsional. Jika kosong maka sistem akan memakai gambar desktop.
                    </small>

                    @error('gambar_mobile')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="form-group">
                    <label>Tautan (opsional)</label>
                    <input type="url" name="tautan" class="form-control @error('tautan') is-invalid @enderror" value="{{ old('tautan') }}"
                        placeholder="https://...">
                    @error('tautan')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <div class="custom-control custom-switch">
                        {{-- Default aktif; setelah gagal validasi ikuti pilihan sebelumnya --}}
                        <input type="checkbox" name="aktif" class="custom-control-input" id="aktif"
                            {{ (old('_token') ? old('aktif') : true) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="aktif">Tampilkan banner di website</label>
                    </div>
                </div>

                <a href="{{ route('admin.banner.index') }}" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </form>
        </div>
    </div>
@endsection
