@extends('admin.layouts.app')

@section('title', 'Master Bahan')
@section('page_title', 'Master Bahan')

@section('content')
    <div class="row">

        {{-- FORM TAMBAH --}}
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Tambah Bahan</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.bahan.store') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label>Nama Bahan <span class="text-danger">*</span></label>
                            <input type="text" name="nama_bahan"
                                class="form-control @error('nama_bahan') is-invalid @enderror"
                                value="{{ old('nama_bahan') }}" placeholder="Contoh: Ivory 230 gsm" required autofocus>
                            @error('nama_bahan')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Deskripsi</label>
                            <textarea name="deskripsi" rows="3" class="form-control"
                                placeholder="Opsional">{{ old('deskripsi') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-plus"></i> Tambah
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- DAFTAR --}}
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Daftar Bahan ({{ $bahan->count() }})</h3>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th width="40">#</th>
                                <th>Nama Bahan</th>
                                <th>Deskripsi</th>
                                <th>Dipakai</th>
                                <th width="130">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bahan as $i => $item)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $item->nama_bahan }}</td>
                                    <td>{{ Str::limit($item->deskripsi, 60, '...') ?: '-' }}</td>
                                    <td>
                                        <span class="badge badge-info">{{ $item->produk_count }} produk</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.bahan.edit', $item) }}" class="btn btn-warning btn-xs">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form action="{{ route('admin.bahan.destroy', $item) }}" method="POST"
                                            class="d-inline"
                                            onsubmit="return confirm('Hapus bahan ini? Bahan juga akan dilepas dari {{ $item->produk_count }} produk.')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger btn-xs">
                                                <i class="fas fa-trash"></i> Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Belum ada bahan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
@endsection
