{{--
    Edit satu item master (bahan / finishing / cetak).
    Variabel: $item, $rute ('admin.bahan'), $kolom ('nama_bahan'), $label ('bahan'), $judulIndeks ('Master Bahan').
--}}
@php $idNama = 'f-' . $kolom; @endphp

<div class="sg-kepala">
    <div>
        <a href="{{ route($rute . '.index') }}" class="sg-remah d-none d-lg-inline-flex"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> {{ $judulIndeks }}</a>
        <h1>Edit {{ $label }}</h1>
        <p class="sg-kepala-sub" style="overflow-wrap:anywhere">{{ $item->{$kolom} }}</p>
    </div>
</div>

<div class="sg-kartu sg-sempit">
    <form action="{{ route($rute . '.update', $item) }}" method="POST" data-kirim>
        @csrf @method('PUT')
        <div class="sg-field">
            <label class="sg-label" for="{{ $idNama }}">Nama {{ $label }} <span class="sg-wajib" aria-hidden="true">*</span></label>
            <input type="text" id="{{ $idNama }}" name="{{ $kolom }}" value="{{ old($kolom, $item->{$kolom}) }}" required
                   class="form-control @error($kolom) is-invalid @enderror" aria-describedby="bantu-nama{{ $errors->has($kolom) ? ' g-' . $kolom : '' }}" @error($kolom) aria-invalid="true" @enderror>
            @include('admin.layouts._galat', ['k' => $kolom])
            <p class="sg-bantu" id="bantu-nama">Perubahan nama berlaku di semua produk yang memakai {{ $label }} ini.</p>
        </div>
        <div class="sg-field">
            <label class="sg-label" for="f-deskripsi">Deskripsi <span class="fw-normal sg-muted">(opsional)</span></label>
            <textarea id="f-deskripsi" name="deskripsi" rows="3" class="form-control @error('deskripsi') is-invalid @enderror"
                      @error('deskripsi') aria-invalid="true" aria-describedby="g-deskripsi" @enderror>{{ old('deskripsi', $item->deskripsi) }}</textarea>
            @include('admin.layouts._galat', ['k' => 'deskripsi'])
        </div>
        <div class="sg-form-aksi">
            <a href="{{ route($rute . '.index') }}" class="sg-btn sg-btn-sekunder"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Kembali</a>
            <button type="submit" class="sg-btn sg-btn-utama" data-teks-kirim="Menyimpan…"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan</button>
        </div>
    </form>
</div>
