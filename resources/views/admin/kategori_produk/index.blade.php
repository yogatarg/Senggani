@extends('admin.layouts.app')

@section('title', 'Kategori Produk')
@section('page_title', 'Kelola Kategori Produk')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar Kategori</h3>
            <a href="{{ route('admin.kategori-produk.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Tambah Kategori
            </a>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Kategori</th>
                        <th>Gambar</th>
                        <th>Slug</th>
                        <th>Deskripsi</th>
                        <th>Jumlah Produk</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($kategori as $i => $item)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $item->nama_kategori }}</td>
                            <td>
                                @if ($item->gambar)
                                    <img src="{{ asset('storage/' . $item->gambar) }}" height="45" width="60"
                                        style="object-fit:cover; border-radius:6px;" alt="{{ $item->nama_kategori }}">
                                @else
                                    <span class="badge badge-light border">
                                        <i class="fas fa-image text-muted"></i> Kosong
                                    </span>
                                @endif
                            </td>
                            <td><code>{{ $item->slug }}</code></td>
                            <td>{{ Str::limit($item->deskripsi, 60, '...') ?? '-' }}</td>
                            <td>
                                <span class="badge badge-info">{{ $item->produk_count }} produk</span>
                            </td>
                            <td>
                                <a href="{{ route('admin.kategori-produk.edit', $item) }}" class="btn btn-warning btn-xs">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <form action="{{ route('admin.kategori-produk.destroy', $item) }}" method="POST"
                                    class="d-inline"
                                    onsubmit="return confirm('Hapus kategori ini? Produk di dalamnya juga akan terhapus!')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-xs">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                Belum ada kategori. <a href="{{ route('admin.kategori-produk.create') }}">Tambah
                                    sekarang</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
