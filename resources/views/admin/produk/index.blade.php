@extends('admin.layouts.app')

@section('title', 'Produk')
@section('page_title', 'Kelola Produk')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar Produk</h3>
            <a href="{{ route('admin.produk.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Tambah Produk
            </a>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Gambar</th>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <th>Min. Pesan</th>
                        <th>Bahan</th>
                        <th>Finishing</th>
                        <th>Cetak</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($produk as $i => $item)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>
                                @if ($item->gambar)
                                    <img src="{{ asset('storage/' . $item->gambar) }}" height="50"
                                        alt="{{ $item->nama_produk }}">
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                {{ $item->nama_produk }}
                                <br>
                                <small class="text-muted"><code>{{ $item->slug }}</code></small>
                            </td>
                            <td>
                                <span class="badge badge-secondary">
                                    {{ $item->kategori->nama_kategori ?? '-' }}
                                </span>
                            </td>
                            <td>{{ $item->minimal_pesan }} pcs</td>
                            <td>
                                @if ($item->bahan_count > 0)
                                    <span class="text-success">✔ {{ $item->bahan_count }}</span>
                                @else
                                    <span class="text-danger">❌ 0</span>
                                @endif
                            </td>

                            <td>
                                @if ($item->finishing_count > 0)
                                    <span class="text-success">✔ {{ $item->finishing_count }}</span>
                                @else
                                    <span class="text-danger">❌ 0</span>
                                @endif
                            </td>

                            <td>
                                @if ($item->cetak_count > 0)
                                    <span class="text-success">✔ {{ $item->cetak_count }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>

                            <td>
                                @if ($item->bahan_count > 0 && $item->finishing_count > 0)
                                    <span class="badge badge-success">Lengkap</span>
                                @else
                                    <span class="badge badge-danger">Belum</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.produk.edit', $item) }}" class="btn btn-warning btn-xs">
                                    <i class="fas fa-edit"></i> Edit
                                </a>

                                <a href="{{ route('admin.produk.atribut', $item) }}" class="btn btn-info btn-xs">
                                    Kelola
                                </a>

                                <form action="{{ route('admin.produk.destroy', $item) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('Hapus produk ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-xs">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted">
                                Belum ada produk. <a href="{{ route('admin.produk.create') }}">Tambah sekarang</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
