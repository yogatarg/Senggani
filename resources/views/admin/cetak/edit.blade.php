@extends('admin.layouts.app')

@section('title', 'Edit Jenis Cetak')
@section('page_title', 'Edit Jenis Cetak: ' . $cetak->nama_cetak)

@section('content')
    <div class="card" style="max-width: 600px;">
        <div class="card-body">
            <form action="{{ route('admin.cetak.update', $cetak) }}" method="POST">
                @csrf @method('PUT')
                <div class="form-group">
                    <label>Nama Cetak <span class="text-danger">*</span></label>
                    <input type="text" name="nama_cetak"
                        class="form-control @error('nama_cetak') is-invalid @enderror"
                        value="{{ old('nama_cetak', $cetak->nama_cetak) }}" required>
                    @error('nama_cetak')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Perubahan nama berlaku di semua produk yang memakai jenis cetak ini.</small>
                </div>
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="deskripsi" rows="3" class="form-control">{{ old('deskripsi', $cetak->deskripsi) }}</textarea>
                </div>
                <a href="{{ route('admin.cetak.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </form>
        </div>
    </div>
@endsection
