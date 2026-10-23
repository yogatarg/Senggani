@extends('admin.layouts.app')

@section('title', 'Master Finishing')
@section('page_title', 'Master Finishing')

@section('content')
    <div class="row">

        {{-- FORM TAMBAH --}}
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Tambah Finishing</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.finishing.store') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label>Nama Finishing <span class="text-danger">*</span></label>
                            <input type="text" name="nama_finishing"
                                class="form-control @error('nama_finishing') is-invalid @enderror"
                                value="{{ old('nama_finishing') }}" placeholder="Contoh: Laminasi Doff" required autofocus>
                            @error('nama_finishing')
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
                    <h3 class="card-title">Daftar Finishing ({{ $finishing->count() }})</h3>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th width="40">#</th>
                                <th>Nama Finishing</th>
                                <th>Deskripsi</th>
                                <th>Dipakai</th>
                                <th width="130">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($finishing as $i => $item)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $item->nama_finishing }}</td>
                                    <td>{{ Str::limit($item->deskripsi, 60, '...') ?: '-' }}</td>
                                    <td>
                                        <span class="badge badge-info">{{ $item->produk_count }} produk</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.finishing.edit', $item) }}" class="btn btn-warning btn-xs">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form action="{{ route('admin.finishing.destroy', $item) }}" method="POST"
                                            class="d-inline"
                                            onsubmit="return confirm('Hapus finishing ini? Finishing juga akan dilepas dari {{ $item->produk_count }} produk.')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger btn-xs">
                                                <i class="fas fa-trash"></i> Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Belum ada finishing.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
@endsection
