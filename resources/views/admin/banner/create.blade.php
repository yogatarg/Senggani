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
                    <label for="gambar">Gambar Desktop <span class="text-danger">*</span></label>
                    <input type="file" name="gambar" id="gambar" accept=".jpg,.jpeg,.png,.webp"
                        class="form-control @error('gambar') is-invalid @enderror" required>

                    @error('gambar')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="form-group mt-3">
                    <label for="gambar_mobile">Gambar Mobile <span class="text-danger">*</span></label>

                    <input type="file" name="gambar_mobile" id="gambar_mobile" accept=".jpg,.jpeg,.png,.webp"
                        class="form-control @error('gambar_mobile') is-invalid @enderror" required>

                    <small class="text-muted">
                        Wajib. Tampil di HP. Rasio 4:3, disarankan 1080×810 px.
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
