{{--
    Unggah gambar invoice (tanda tangan, stempel, QRIS) dengan pratinjau di atas papan catur (rancangan invoice 6).
    Memakai komponen sg-gambar-isian ([data-gambar-isian] di pesanan.js) ditambah:
    data-min-sisi (QRIS) dan data-peringatan-jpg (tanda tangan & stempel).
    Variabel: $jenis (ttd|stempel|qris), $label, $src (URL pratinjau atau null), $rasio, $maks, $bantu,
    opsional: $minSisi (int), $peringatanJpg (bool), $konfirmasiHapus (teks).
--}}
@php
    $id = 'f-' . $jenis;
    $adaGalat = $errors->has($jenis);
    $labelKecil = mb_strtolower($label);
    $terangkan = 'bantu-' . $jenis . ' peringatan-' . $jenis . ($adaGalat ? ' g-' . $jenis : '');
@endphp
<div class="sg-field sg-gambar-isian sg-gi-invoice" data-gambar-isian data-maks-mb="2"
     @isset($minSisi) data-min-sisi="{{ $minSisi }}" data-label-gambar="{{ $labelGalat ?? $label }}" @endisset
     @if (! empty($peringatanJpg)) data-peringatan-jpg @endif
     style="--rasio: {{ $rasio }}; --gambar-maks: {{ $maks }}">
    <label class="sg-label" for="{{ $id }}">{{ $label }}</label>
    <div class="sg-gi-baris">
        <label class="sg-gambar-bingkai sg-gi-catur {{ $src ? '' : 'sg-gi-kosong' }} {{ $adaGalat ? 'is-invalid' : '' }}" data-gi-bingkai>
            <img data-gi-gambar alt="Pratinjau {{ $labelKecil }}" @if ($src) src="{{ $src }}" data-src-lama="{{ $src }}" @else hidden @endif>
            <span class="sg-gambar-kosong" data-gi-kosong @if ($src) hidden @endif>
                <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                <strong>Pilih gambar</strong>
            </span>
            <span class="sg-pratinjau-label sg-gi-belum" data-gi-baru hidden>Belum disimpan</span>
            <input type="file" id="{{ $id }}" name="{{ $jenis }}" accept="image/png,image/jpeg,image/webp"
                   @if ($adaGalat) aria-invalid="true" @endif aria-describedby="{{ $terangkan }}">
        </label>
        <div class="sg-gi-samping">
            <div class="sg-gambar-kendali" data-gi-kendali @unless ($src) hidden @endunless>
                <button type="button" class="sg-btn sg-btn-sekunder" data-gi-ganti aria-label="Ganti {{ $labelKecil }}"><i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i> Ganti</button>
                @if ($src)
                    <button type="submit" form="hapus-{{ $jenis }}" class="sg-btn sg-btn-teks sg-bahaya" data-gi-hapus aria-label="Hapus {{ $labelKecil }}"><i class="fa-solid fa-trash-can" aria-hidden="true"></i> Hapus</button>
                @endif
                <button type="button" class="sg-btn sg-btn-teks" data-gi-batal hidden>Batalkan</button>
                <span class="sg-gi-ket" data-gi-ket>Gambar saat ini</span>
            </div>
            <p class="sg-bantu" id="bantu-{{ $jenis }}">{{ $bantu }}</p>
        </div>
    </div>
    <div aria-live="polite">
        <p class="sg-galat" data-gi-galat hidden></p>
        <p class="sg-bantu sg-ulang" id="peringatan-{{ $jenis }}" data-gi-peringatan hidden></p>
    </div>
    @include('admin.layouts._galat', ['k' => $jenis])
</div>
