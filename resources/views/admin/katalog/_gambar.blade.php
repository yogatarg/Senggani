{{--
    Unggah gambar tunggal dengan pratinjau (sg-gambar-isian). Perilaku di pesanan.js ([data-gambar-isian]).
    Variabel: $nama (nama field), $label, $rasio ('4 / 3'), $maks (lebar maks bingkai, mis. '320px'),
    $src (URL gambar lama atau null), $bantu (teks panduan), opsional: $wajib, $maksMb (2), $ingatkanUlang (form tambah).
--}}
@php
    $id = 'f-' . $nama;
    $wajib = $wajib ?? false;
    $maksMb = $maksMb ?? 2;
    $adaGalat = $errors->has($nama);
    $ulang = ($ingatkanUlang ?? false) && $errors->any() && ! $adaGalat;
    $terangkan = 'bantu-' . $nama . ($adaGalat ? ' g-' . $nama : '') . ($ulang ? ' ulang-' . $nama : '');
@endphp
<div class="sg-field sg-gambar-isian" data-gambar-isian data-maks-mb="{{ $maksMb }}" style="--rasio: {{ $rasio }}; --gambar-maks: {{ $maks ?? '100%' }}">
    <label class="sg-label" for="{{ $id }}">{{ $label }} @if ($wajib)<span class="sg-wajib" aria-hidden="true">*</span>@endif</label>
    <label class="sg-gambar-bingkai {{ $src ? '' : 'sg-gi-kosong' }} {{ $adaGalat ? 'is-invalid' : '' }}" data-gi-bingkai>
        <img data-gi-gambar alt="Pratinjau {{ mb_strtolower($label) }}" @if ($src) src="{{ $src }}" data-src-lama="{{ $src }}" @else hidden @endif>
        <span class="sg-gambar-kosong" data-gi-kosong @if ($src) hidden @endif>
            <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
            <strong><span class="d-lg-none">Pilih dari galeri atau kamera</span><span class="d-none d-lg-inline">Pilih gambar</span></strong>
            <span>JPG, PNG, WebP · maks {{ $maksMb }} MB</span>
        </span>
        <span class="sg-pratinjau-label" data-gi-baru hidden>Baru</span>
        <input type="file" id="{{ $id }}" name="{{ $nama }}" accept="image/jpeg,image/png,image/webp"
               @if ($wajib) required @endif @if ($adaGalat) aria-invalid="true" @endif aria-describedby="{{ $terangkan }}">
    </label>
    <div class="sg-gambar-kendali" data-gi-kendali @unless ($src) hidden @endunless>
        <button type="button" class="sg-btn sg-btn-sekunder" data-gi-ganti aria-label="Ganti {{ mb_strtolower($label) }}"><i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i> Ganti gambar</button>
        <span class="sg-gi-ket" data-gi-ket>Gambar saat ini</span>
        <button type="button" class="sg-btn sg-btn-teks" data-gi-batal hidden>Batalkan</button>
    </div>
    <div aria-live="polite"><p class="sg-galat" data-gi-galat hidden></p></div>
    @include('admin.layouts._galat', ['k' => $nama])
    @if ($ulang)
        <p class="sg-bantu sg-ulang" id="ulang-{{ $nama }}"><i class="fa-solid fa-rotate" aria-hidden="true"></i> Pilih ulang gambar sebelum menyimpan.</p>
    @endif
    <p class="sg-bantu" id="bantu-{{ $nama }}">{{ $bantu }}</p>
</div>
