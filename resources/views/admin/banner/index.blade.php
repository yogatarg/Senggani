@extends('admin.layouts.app')

@section('title', 'Banner')
@section('page_title', 'Kelola Banner')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar Banner</h3>
            <a href="{{ route('admin.banner.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Tambah Banner
            </a>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Dekstop</th>
                        <th>Mobile</th>
                        <th>Judul</th>
                        <th>Deskripsi</th>
                        <th>Tautan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($banner as $i => $item)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>
                                <img src="{{ asset('storage/' . $item->gambar) }}" height="50" alt="{{ $item->judul }}">
                            </td>
                            <td>
                                @if ($item->gambar_mobile)
                                    <img src="{{ asset('storage/' . $item->gambar_mobile) }}" height="50">
                                @else
                                    <span class="text-muted">
                                        Belum ada
                                    </span>
                                @endif
                            </td>
                            <td>{{ $item->judul }}</td>
                            <td>{{ Str::limit($item->deskripsi, 60) }}</td>
                            <td>{{ $item->tautan ?? '-' }}</td>
                            <td>
                                <a href="{{ route('admin.banner.edit', $item) }}" class="btn btn-warning btn-xs">Edit</a>
                                <form action="{{ route('admin.banner.destroy', $item) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('Hapus banner ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-xs">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">Belum ada banner.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
