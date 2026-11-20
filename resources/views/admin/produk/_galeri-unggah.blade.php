{{--
    Dropzone unggah banyak foto galeri + pratinjau. Perilaku di pesanan.js ([data-galeri-unggah]).
    Variabel: $nama ('galeri' / 'gambar'), $id ('f-galeri'), $kunciGalat ('galeri.*'), $teks (judul dropzone).
    Opsional: $wajib (minimal satu foto, dicek di browser), $ingatkanUlang (true bila form gagal validasi;
    pengingat disembunyikan JS bila diketahui sebelumnya tidak ada foto galeri yang dipilih).
--}}
@php
    $adaGalat = $errors->has($kunciGalat);
    $idGalat = 'g-' . $id;
    $wajib = $wajib ?? false;
    $ulang = $ingatkanUlang ?? false;
    $terangkan = 'bantu-' . $id . ($adaGalat ? ' ' . $idGalat : '') . ($ulang ? ' ulang-' . $id : '');
@endphp
<div class="sg-field" data-galeri-unggah data-maks-mb="2">
    <label class="sg-dropzone" data-dropzone>
        <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
        <strong>{{ $teks }}</strong>
        <span class="sg-kecil">JPG, PNG, WebP · maks 2 MB per foto</span>
        <input type="file" id="{{ $id }}" name="{{ $nama }}[]" multiple accept="image/jpeg,image/png,image/webp"
               aria-describedby="{{ $terangkan }}" @if ($wajib) required data-pesan-wajib="Pilih minimal satu foto dulu, lalu tekan Unggah foto." @endif
               @if ($adaGalat) aria-invalid="true" @endif>
    </label>
    @if ($adaGalat)
        <p class="sg-galat" id="{{ $idGalat }}"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $errors->first($kunciGalat) }}</p>
    @endif
    <div aria-live="polite"><p class="sg-galat" data-galeri-galat hidden></p></div>
    @if ($ulang)
        <p class="sg-bantu sg-ulang" id="ulang-{{ $id }}" data-galeri-ulang><i class="fa-solid fa-rotate" aria-hidden="true"></i> Pilih ulang foto galeri sebelum menyimpan.</p>
    @endif
    <ul class="sg-tolak" data-galeri-tolak aria-live="polite"></ul>
    <p class="sg-pratinjau-hitung" data-galeri-hitung aria-live="polite" hidden></p>
    <ul class="sg-pratinjau" data-galeri-pratinjau></ul>
    <p class="sg-bantu" id="bantu-{{ $id }}">Bisa pilih lebih dari satu foto. Rasio 4:3 (mendatar), disarankan 1200×900 px, produk di tengah dengan sedikit ruang kosong di sekeliling (tepi atas-bawah atau kiri-kanan bisa terpotong di kartu & halaman detail). Format: JPG, PNG, WEBP. Gambar otomatis dikompres; maks 2 MB per foto.</p>
</div>
