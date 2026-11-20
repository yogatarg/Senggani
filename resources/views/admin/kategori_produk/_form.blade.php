{{-- Form kategori (tambah & edit). Variabel: $kategoriProduk (null saat tambah). --}}
@php
    $kat = $kategoriProduk ?? null;
    $urlIndeks = route('admin.kategori-produk.index');
@endphp
<div class="sg-kartu sg-sempit">
    <form action="{{ $kat ? route('admin.kategori-produk.update', $kat) : route('admin.kategori-produk.store') }}"
          method="POST" enctype="multipart/form-data" data-kirim data-jaga-perubahan>
        @csrf
        @if ($kat) @method('PUT') @endif

        <div class="sg-field">
            <label class="sg-label" for="f-nama_kategori">Nama kategori <span class="sg-wajib" aria-hidden="true">*</span></label>
            <input type="text" id="f-nama_kategori" name="nama_kategori" value="{{ old('nama_kategori', $kat?->nama_kategori) }}" required
                   class="form-control @error('nama_kategori') is-invalid @enderror"
                   @if (! $kat) aria-describedby="bantu-nama{{ $errors->has('nama_kategori') ? ' g-nama_kategori' : '' }}" @elseif ($errors->has('nama_kategori')) aria-describedby="g-nama_kategori" @endif
                   @error('nama_kategori') aria-invalid="true" @enderror>
            @include('admin.layouts._galat', ['k' => 'nama_kategori'])
            @unless ($kat)
                <p class="sg-bantu" id="bantu-nama">Slug dibuat otomatis.</p>
            @endunless
        </div>

        @if ($kat)
            <div class="sg-field">
                <label class="sg-label" for="slug-kini">Slug saat ini</label>
                <input type="text" id="slug-kini" class="form-control" value="{{ $kat->slug }}" disabled aria-describedby="bantu-slug">
                <p class="sg-bantu" id="bantu-slug">Diperbarui otomatis jika nama diubah.</p>
            </div>
        @endif

        <div class="sg-field">
            <label class="sg-label" for="f-deskripsi">Deskripsi <span class="fw-normal sg-muted">(opsional)</span></label>
            <textarea id="f-deskripsi" name="deskripsi" rows="4" class="form-control @error('deskripsi') is-invalid @enderror"
                      @error('deskripsi') aria-invalid="true" aria-describedby="g-deskripsi" @enderror>{{ old('deskripsi', $kat?->deskripsi) }}</textarea>
            @include('admin.layouts._galat', ['k' => 'deskripsi'])
        </div>

        @include('admin.katalog._gambar', [
            'nama' => 'gambar',
            'label' => 'Gambar kategori',
            'rasio' => '3 / 2',
            'maks' => '360px',
            'src' => $kat?->gambar ? asset('storage/' . $kat->gambar) : null,
            'ingatkanUlang' => ! $kat,
            'bantu' => 'Rasio 3:2 (mendatar), disarankan 1200×800 px, objek di tengah (tepi bisa terpotong di kartu kategori). Format: JPG, PNG, WEBP. Gambar otomatis dikompres; maks 2 MB.' . ($kat ? ' Kosongkan jika tidak ingin mengganti.' : ''),
        ])

        <div class="sg-form-aksi">
            <a href="{{ $urlIndeks }}" class="sg-btn sg-btn-sekunder" data-batal-form>Batal</a>
            <button type="submit" class="sg-btn sg-btn-utama" data-teks-kirim="Menyimpan…"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ $kat ? 'Simpan perubahan' : 'Simpan' }}</button>
        </div>
    </form>
</div>
