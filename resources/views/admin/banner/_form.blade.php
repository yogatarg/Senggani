{{-- Form banner (tambah & edit). Variabel: $banner (null saat tambah). --}}
@php
    $bn = $banner ?? null;
    $urlIndeks = route('admin.banner.index');
    $wajibHp = ! $bn || ! $bn->gambar_mobile;
    // Default aktif saat tambah; setelah gagal validasi ikuti pilihan sebelumnya
    $aktif = old('_token') ? old('aktif') : ($bn ? $bn->aktif : true);
@endphp
<form action="{{ $bn ? route('admin.banner.update', $bn) : route('admin.banner.store') }}" method="POST" enctype="multipart/form-data"
      class="sg-sedang" data-kirim data-jaga-perubahan>
    @csrf
    @if ($bn) @method('PUT') @endif

    <section class="sg-kartu" aria-labelledby="judul-isi">
        <h2 class="sg-kartu-judul" id="judul-isi"><span class="sg-no" aria-hidden="true">1</span> Isi banner</h2>
        <div class="sg-field">
            <label class="sg-label" for="f-judul">Judul <span class="sg-wajib" aria-hidden="true">*</span></label>
            <input type="text" id="f-judul" name="judul" value="{{ old('judul', $bn?->judul) }}" required
                   class="form-control @error('judul') is-invalid @enderror" @error('judul') aria-invalid="true" aria-describedby="g-judul" @enderror>
            @include('admin.layouts._galat', ['k' => 'judul'])
        </div>
        <div class="sg-field">
            <label class="sg-label" for="f-deskripsi">Deskripsi <span class="fw-normal sg-muted">(opsional)</span></label>
            <textarea id="f-deskripsi" name="deskripsi" rows="4" class="form-control @error('deskripsi') is-invalid @enderror"
                      @error('deskripsi') aria-invalid="true" aria-describedby="g-deskripsi" @enderror>{{ old('deskripsi', $bn?->deskripsi) }}</textarea>
            @include('admin.layouts._galat', ['k' => 'deskripsi'])
        </div>
        <div class="sg-field">
            <label class="sg-label" for="f-tautan">Tautan <span class="fw-normal sg-muted">(opsional)</span></label>
            <input type="url" id="f-tautan" name="tautan" inputmode="url" value="{{ old('tautan', $bn?->tautan) }}" placeholder="https://…"
                   class="form-control @error('tautan') is-invalid @enderror" @error('tautan') aria-invalid="true" aria-describedby="g-tautan" @enderror>
            @include('admin.layouts._galat', ['k' => 'tautan'])
        </div>
    </section>

    <section class="sg-kartu" aria-labelledby="judul-gambar">
        <h2 class="sg-kartu-judul" id="judul-gambar"><span class="sg-no" aria-hidden="true">2</span> Gambar</h2>
        <div class="sg-gambar-dua">
            @include('admin.katalog._gambar', [
                'nama' => 'gambar',
                'label' => 'Gambar desktop',
                'rasio' => '12 / 5',
                'maks' => '100%',
                'wajib' => ! $bn,
                'src' => $bn?->gambar ? asset('storage/' . $bn->gambar) : null,
                'ingatkanUlang' => ! $bn,
                'bantu' => ($bn ? '' : 'Wajib. ') . 'Tampil di layar ≥768 px (tinggi 550 px, terpotong mengikuti lebar layar). Rasio 12:5 (melebar), disarankan 1920×800 px. Kosongkan ±50% bagian kiri untuk teks; letakkan produk di kanan ±52–85% lebar dan jauhi tepi atas-bawah ±15%. Gambar otomatis dikompres; maks 2 MB.' . ($bn ? ' Kosongkan jika tidak ingin mengganti.' : ''),
            ])
            @include('admin.katalog._gambar', [
                'nama' => 'gambar_mobile',
                'label' => 'Gambar HP',
                'rasio' => '3 / 4',
                'maks' => '200px',
                'maksMb' => 3,
                'wajib' => $wajibHp,
                'src' => $bn?->gambar_mobile ? asset('storage/' . $bn->gambar_mobile) : null,
                'ingatkanUlang' => ! $bn,
                'bantu' => $bn
                    ? 'Tampil di HP. Rasio 3:4 (tegak), disarankan 1080×1440 px. Kosongkan jika tidak ingin mengganti; wajib diisi bila banner belum punya gambar HP.'
                    : 'Wajib. Tampil di HP. Rasio 3:4 (tegak), disarankan 1080×1440 px.',
            ])
        </div>
    </section>

    <section class="sg-kartu" aria-labelledby="judul-tayang">
        <h2 class="sg-kartu-judul" id="judul-tayang"><span class="sg-no" aria-hidden="true">3</span> Penayangan</h2>
        <div class="form-check form-switch sg-saklar">
            <label for="f-aktif">
                <input type="checkbox" class="form-check-input" role="switch" id="f-aktif" name="aktif" aria-describedby="bantu-aktif" @checked($aktif)>
                Tampilkan banner di website
            </label>
            <p class="sg-bantu" id="bantu-aktif">Bila dimatikan, banner tersimpan tetapi tidak tampil di beranda.</p>
        </div>
    </section>

    <div class="sg-form-aksi sg-rata-kanan d-none d-lg-flex">
        <a href="{{ $urlIndeks }}" class="sg-btn sg-btn-sekunder" data-batal-form>Batal</a>
        <button type="submit" class="sg-btn sg-btn-utama" data-teks-kirim="Menyimpan…"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ $bn ? 'Simpan perubahan' : 'Simpan' }}</button>
    </div>
    <div class="sg-bilah d-lg-none">
        <a href="{{ $urlIndeks }}" class="sg-btn sg-btn-sekunder" data-batal-form>Batal</a>
        <button type="submit" class="sg-btn sg-btn-utama flex-grow-1" data-teks-kirim="Menyimpan…"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ $bn ? 'Simpan perubahan' : 'Simpan' }}</button>
    </div>
</form>
