{{-- Field informasi produk (tambah & edit), tanpa tag form. Variabel: $kategori, $produk (null saat tambah). --}}
@php $pr = $produk ?? null; @endphp
<div class="sg-dua-768">
    <div class="sg-field">
        <label class="sg-label" for="f-nama_produk">Nama produk <span class="sg-wajib" aria-hidden="true">*</span></label>
        <input type="text" id="f-nama_produk" name="nama_produk" value="{{ old('nama_produk', $pr?->nama_produk) }}" required
               placeholder="Contoh: Paperbag Kraft Natural"
               class="form-control @error('nama_produk') is-invalid @enderror"
               aria-describedby="bantu-nama{{ $errors->has('nama_produk') ? ' g-nama_produk' : '' }}" @error('nama_produk') aria-invalid="true" @enderror>
        @include('admin.layouts._galat', ['k' => 'nama_produk'])
        <p class="sg-bantu" id="bantu-nama">Slug URL dibuat otomatis dari nama produk.</p>
    </div>
    <div class="sg-field">
        <label class="sg-label" for="f-kategori_id">Kategori <span class="sg-wajib" aria-hidden="true">*</span></label>
        <select id="f-kategori_id" name="kategori_id" required
                class="form-select @error('kategori_id') is-invalid @enderror" @error('kategori_id') aria-invalid="true" aria-describedby="g-kategori_id" @enderror>
            @unless ($pr)
                <option value="">Pilih kategori</option>
            @endunless
            @foreach ($kategori as $kat)
                <option value="{{ $kat->id }}" @selected(old('kategori_id', $pr?->kategori_id) == $kat->id)>{{ $kat->nama_kategori }}</option>
            @endforeach
        </select>
        @include('admin.layouts._galat', ['k' => 'kategori_id'])
    </div>
</div>

<div class="sg-field">
    <label class="sg-label" for="f-deskripsi">Deskripsi <span class="fw-normal sg-muted">(opsional)</span></label>
    <textarea id="f-deskripsi" name="deskripsi" rows="4" placeholder="Deskripsi lengkap produk…"
              class="form-control @error('deskripsi') is-invalid @enderror" @error('deskripsi') aria-invalid="true" aria-describedby="g-deskripsi" @enderror>{{ old('deskripsi', $pr?->deskripsi) }}</textarea>
    @include('admin.layouts._galat', ['k' => 'deskripsi'])
</div>

<div class="sg-field">
    <label class="sg-label" for="f-minimal_pesan">Minimal pesan <span class="sg-wajib" aria-hidden="true">*</span></label>
    <div class="sg-imbuh sg-imbuh-belakang sg-isian-angka">
        <input type="number" id="f-minimal_pesan" name="minimal_pesan" value="{{ old('minimal_pesan', $pr ? $pr->minimal_pesan : 100) }}" min="1" required inputmode="numeric"
               class="form-control @error('minimal_pesan') is-invalid @enderror"
               aria-describedby="bantu-minimal{{ $errors->has('minimal_pesan') ? ' g-minimal_pesan' : '' }}" @error('minimal_pesan') aria-invalid="true" @enderror>
        <span class="sg-imbuh-teks" aria-hidden="true">pcs</span>
    </div>
    @include('admin.layouts._galat', ['k' => 'minimal_pesan'])
    <p class="sg-bantu" id="bantu-minimal">Isi 1 bila tanpa minimum order.</p>
</div>

@include('admin.katalog._gambar', [
    'nama' => 'gambar',
    'label' => 'Gambar utama',
    'rasio' => '4 / 3',
    'maks' => '320px',
    'src' => $pr?->gambar ? asset('storage/' . $pr->gambar) : null,
    'ingatkanUlang' => ! $pr,
    'bantu' => 'Rasio 4:3 (mendatar), disarankan 1200×900 px, produk di tengah dengan sedikit ruang kosong di sekeliling (tepi atas-bawah atau kiri-kanan bisa terpotong di kartu & halaman detail). Format: JPG, PNG, WEBP. Gambar otomatis dikompres; maks 2 MB.' . ($pr ? ' Kosongkan jika tidak ingin mengganti.' : ''),
])

<div class="sg-field">
    <label class="sg-centang" for="f-produk_kustom">
        <input type="checkbox" class="form-check-input" id="f-produk_kustom" name="produk_kustom" @checked(old('produk_kustom', $pr?->produk_kustom))>
        Produk ini bisa dikustomisasi
    </label>
</div>
