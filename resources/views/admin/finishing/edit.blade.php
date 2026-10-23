@extends('admin.layouts.app')

@section('title', 'Edit Finishing')
@section('page_title', 'Edit Finishing: ' . $finishing->nama_finishing)

@section('content')
    <div class="card" style="max-width: 600px;">
        <div class="card-body">
            <form action="{{ route('admin.finishing.update', $finishing) }}" method="POST">
                @csrf @method('PUT')
                <div class="form-group">
                    <label>Nama Finishing <span class="text-danger">*</span></label>
                    <input type="text" name="nama_finishing"
                        class="form-control @error('nama_finishing') is-invalid @enderror"
                        value="{{ old('nama_finishing', $finishing->nama_finishing) }}" required>
                    @error('nama_finishing')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Perubahan nama berlaku di semua produk yang memakai finishing ini.</small>
                </div>
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="deskripsi" rows="3" class="form-control">{{ old('deskripsi', $finishing->deskripsi) }}</textarea>
                </div>
                <a href="{{ route('admin.finishing.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </form>
        </div>
    </div>
@endsection
