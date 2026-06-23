@extends('admin.layouts.app')

@section('title', 'Atribut Produk')
@section('page_title', 'Atribut: ' . $produk->nama_produk)

@section('content')

<div class="row">

    {{-- ================= BAHAN ================= --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Bahan Produk</h3>
            </div>
            <div class="card-body">

                {{-- LIST --}}
                <table class="table table-bordered table-sm mb-3">
                    <thead>
                        <tr>
                            <th>Nama Bahan</th>
                            <th width="80">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($produk->bahan as $item)
                            <tr>
                                <td>{{ $item->nama_bahan }}</td>
                                <td>
                                    <form action="{{ route('admin.produk.bahan.hapus', $item->id) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger btn-xs">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center text-muted">Belum ada bahan</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- FORM --}}
                <form action="{{ route('admin.produk.bahan.tambah', $produk) }}" method="POST">
                    @csrf
                    <div class="input-group">
                        <input type="text" name="nama_bahan" class="form-control" placeholder="Nama bahan" required>
                        <div class="input-group-append">
                            <button class="btn btn-success">Tambah</button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>

    {{-- ================= FINISHING ================= --}}
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Finishing Produk</h3>
            </div>
            <div class="card-body">

                {{-- LIST --}}
                <table class="table table-bordered table-sm mb-3">
                    <thead>
                        <tr>
                            <th>Nama Finishing</th>
                            <th width="80">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($produk->finishing as $item)
                            <tr>
                                <td>{{ $item->nama_finishing }}</td>
                                <td>
                                    <form action="{{ route('admin.produk.finishing.hapus', $item->id) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger btn-xs">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center text-muted">Belum ada finishing</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- FORM --}}
                <form action="{{ route('admin.produk.finishing.tambah', $produk) }}" method="POST">
                    @csrf
                    <div class="input-group">
                        <input type="text" name="nama_finishing" class="form-control" placeholder="Nama finishing" required>
                        <div class="input-group-append">
                            <button class="btn btn-success">Tambah</button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>

</div>

@endsection