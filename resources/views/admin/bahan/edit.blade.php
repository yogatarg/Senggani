@extends('admin.layouts.app')

@section('title', 'Edit Bahan')
@section('page_title', 'Edit Bahan: ' . $bahan->nama_bahan)

@section('content')
    <div class="card" style="max-width: 600px;">
        <div class="card-body">
            <form action="{{ route('admin.bahan.update', $bahan) }}" method="POST">
                @csrf @method('PUT')
                <div class="form-group">
                    <label>Nama Bahan <span class="text-danger">*</span></label>
                    <input type="text" name="nama_bahan"
                        class="form-control @error('nama_bahan') is-invalid @enderror"
                        value="{{ old('nama_bahan', $bahan->nama_bahan) }}" required>
                    @error('nama_bahan')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Perubahan nama berlaku di semua produk yang memakai bahan ini.</small>
                </div>
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="deskripsi" rows="3" class="form-control">{{ old('deskripsi', $bahan->deskripsi) }}</textarea>
                </div>
                <a href="{{ route('admin.bahan.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </form>
        </div>
    </div>
@endsection
