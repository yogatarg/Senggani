@extends('admin.layouts.pesanan')

@section('title', 'Edit Produk')
@section('kembali', route('admin.produk.index'))
@section('tanpa_bottomnav', '1')

@section('content')
<div class="sg-kepala">
    <div>
        <a href="{{ route('admin.produk.index') }}" class="sg-remah d-none d-lg-inline-flex" data-batal-form><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Produk</a>
        <h1>Edit produk</h1>
        <p class="sg-kepala-sub" style="overflow-wrap:anywhere">{{ $produk->nama_produk }}</p>
    </div>
    <div class="sg-kepala-aksi">
        <a href="{{ route('admin.produk.atribut', $produk) }}" class="sg-btn sg-btn-sekunder" data-batal-form><i class="fa-solid fa-sliders" aria-hidden="true"></i> Kelola atribut</a>
    </div>
</div>

<div class="sg-sedang">
    {{-- ① Informasi produk (form PUT sendiri) --}}
    <section class="sg-kartu" aria-labelledby="judul-info">
        <h2 class="sg-kartu-judul" id="judul-info"><span class="sg-no" aria-hidden="true">1</span> Informasi produk</h2>
        <form action="{{ route('admin.produk.update', $produk) }}" method="POST" enctype="multipart/form-data" data-kirim data-jaga-perubahan>
            @csrf @method('PUT')
            @include('admin.produk._info')
            <div class="sg-kartu-aksi">
                <button type="submit" class="sg-btn sg-btn-utama" data-teks-kirim="Menyimpan…"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan perubahan</button>
            </div>
        </form>
    </section>

    {{-- ② Galeri produk: hapus per foto + form unggah sendiri --}}
    <section class="sg-kartu" aria-labelledby="judul-galeri">
        <h2 class="sg-kartu-judul" id="judul-galeri"><span class="sg-no" aria-hidden="true">2</span> Galeri produk</h2>
        @if ($produk->galeri->isEmpty())
            <p class="sg-galeri-kosong"><i class="fa-regular fa-images" aria-hidden="true"></i> Belum ada foto galeri.</p>
        @else
            <ul class="sg-pratinjau sg-galeri-produk">
                @foreach ($produk->galeri as $foto)
                    <li>
                        <img src="{{ asset('storage/' . $foto->nama_file) }}" alt="Foto galeri {{ $loop->iteration }}" width="96" height="96" loading="lazy">
                        <form action="{{ route('admin.galeri.hapus', $foto->id) }}" method="POST" data-kirim
                              data-konfirmasi="Hapus foto ini dari galeri?" data-konfirmasi-isi="Foto langsung dihapus dari halaman produk."
                              data-konfirmasi-tombol="Ya, hapus" data-konfirmasi-bahaya>
                            @csrf @method('DELETE')
                            <button type="submit" aria-label="Hapus foto galeri {{ $loop->iteration }}" title="Hapus"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <form action="{{ route('admin.produk.galeri.tambah', $produk) }}" method="POST" enctype="multipart/form-data" data-kirim>
            @csrf
            @include('admin.produk._galeri-unggah', ['nama' => 'gambar', 'id' => 'f-galeri-tambah', 'kunciGalat' => 'gambar.*', 'teks' => 'Tambah foto galeri (bisa lebih dari satu)', 'wajib' => true, 'ingatkanUlang' => $errors->has('gambar.*')])
            <div class="sg-kartu-aksi">
                <button type="submit" class="sg-btn sg-btn-sekunder" data-teks-kirim="Mengunggah…"><i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i> Unggah foto</button>
            </div>
        </form>
    </section>
</div>
@endsection
