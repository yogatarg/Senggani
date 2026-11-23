{{--
    Pilihan metode bayar berbentuk ubin radio (rancangan invoice 5.2). Tanpa pilihan bawaan.
    Variabel: $nama (metode_dp | metode_lunas), $legend, $metodeBayar (nilai => label),
    opsional: $terpilih (nilai lama), $wajib (bool; dicek JS sebelum kirim), $bantuKosong (teks bila belum dipilih,
    mis. "Belum dicatat" untuk data lama), $kelas (kelas tambahan wadah), $atribut (atribut HTML tambahan wadah),
    $pesanWajib (pesan galat klien bila belum dipilih). Tanda * selalu dirender (hidden bila tidak wajib) supaya
    pesanan.js bisa menjadikannya wajib bersyarat (Edit SPP: DP atau tanggal lunas diisi/diubah).
--}}
@php
    $wajib = $wajib ?? false;
    $terpilih = old($nama, $terpilih ?? null);
    $adaGalat = $errors->has($nama);
    $idBantu = 'bantu-' . $nama;
@endphp
<fieldset class="sg-field sg-metode {{ $adaGalat ? 'sg-metode-galat' : '' }} {{ $kelas ?? '' }}" id="f-{{ $nama }}"
          data-metode @if ($wajib) data-metode-wajib @endif data-pesan-wajib="{{ $pesanWajib ?? 'Pilih metode pembayaran.' }}" {!! $atribut ?? '' !!}
          @if ($adaGalat || ! empty($bantuKosong)) aria-describedby="{{ $adaGalat ? 'g-' . $nama : '' }} {{ ! empty($bantuKosong) ? $idBantu : '' }}" @endif>
    <legend class="sg-label">{{ $legend }} <span class="sg-wajib" aria-hidden="true" data-tanda-wajib @unless ($wajib) hidden @endunless>*</span></legend>
    <div class="sg-metode-grid">
        @foreach ($metodeBayar as $nilai => $label)
            <label class="sg-metode-ubin">
                <input type="radio" class="visually-hidden" name="{{ $nama }}" value="{{ $nilai }}" @checked($terpilih === $nilai)
                       @if ($wajib) aria-required="true" @endif @if ($adaGalat) aria-invalid="true" @endif>
                <i class="fa-solid {{ \App\Enums\MetodeBayar::from($nilai)->ikon() }} sg-metode-ikon" aria-hidden="true"></i>
                {{-- "Transfer BCA/BRI" selalu dua baris (Transfer / nama bank) supaya semua ubin seragam di layar sempit maupun lebar --}}
                @if (str_starts_with($label, 'Transfer '))
                    <span>Transfer <span class="sg-metode-bank">{{ substr($label, 9) }}</span></span>
                @else
                    <span>{{ $label }}</span>
                @endif
                <i class="fa-solid fa-circle-check sg-metode-cek" aria-hidden="true"></i>
            </label>
        @endforeach
    </div>
    <p class="sg-galat" id="g-{{ $nama }}" data-metode-galat @unless ($adaGalat) hidden @endunless>
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span>{{ $errors->first($nama) ?: ($pesanWajib ?? 'Pilih metode pembayaran.') }}</span>
    </p>
    @if (! empty($bantuKosong))
        <p class="sg-bantu" id="{{ $idBantu }}" data-metode-kosong @if ($terpilih) hidden @endif>{{ $bantuKosong }}</p>
    @endif
</fieldset>
