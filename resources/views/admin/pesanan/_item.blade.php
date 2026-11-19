{{--
    Satu item SPP. $i = indeks (angka, atau '__I__' untuk template JS), $it = nilai input (array).
    Nama field: item[i][...] (kontrak backend). id field: f-item-i-<nama> (dipakai link ringkasan error).
--}}
@php
    $n = "item[{$i}]";
    $v = fn ($k) => $it[$k] ?? null;
    $k = fn ($f) => "item.{$i}.{$f}";
    $fidI = fn ($f) => "f-item-{$i}-{$f}";
    $galatItem = is_numeric($i)
        ? collect($errors->keys())->filter(fn ($kunci) => str_starts_with($kunci, "item.{$i}."))->count()
        : 0;
    $hargaTampil = $v('harga_satuan');
    // Hanya angka murni yang diformat; isian lama (old) seperti "2.500" dibiarkan apa adanya (is_numeric akan membacanya 2,5)
    if (is_int($hargaTampil) || (is_string($hargaTampil) && ctype_digit($hargaTampil))) { $hargaTampil = number_format((int) $hargaTampil, 0, ',', '.'); }
    // Ukuran: buang nol desimal & pakai koma desimal (30.00→30, 12.50→"12,5"); isian lama "12,5" tampil apa adanya
    $ukuranTampil = function ($x) {
        if ($x === null || $x === '') return '';
        $s = trim((string) $x);
        return preg_match('/^\d+\.\d*$/', $s) ? str_replace('.', ',', rtrim(rtrim($s, '0'), '.')) : $s;
    };
    // $hargaWajib dari _form (false saat tambah / Hitung Harga)
    $hargaWajib = $hargaWajib ?? false;
    $finDipilih = (array) $v('finishing');
    $adaFinLain = filled($v('finishing_lainnya'));
@endphp
<div class="sg-item {{ $galatItem ? 'sg-ada-galat' : '' }}" data-item role="group" aria-labelledby="judul-item-{{ $i }}">
    <div class="sg-item-kepala">
        <h3 id="judul-item-{{ $i }}"><span data-item-label>Item {{ is_numeric($i) ? $i + 1 : '' }}</span>
            @if ($galatItem)<span class="sg-item-galat"> · {{ $galatItem }} isian perlu diperbaiki</span>@endif
        </h3>
        <button type="button" class="sg-btn sg-btn-teks sg-bahaya sg-btn-kecil" data-hapus-item><i class="fa-solid fa-trash-can" aria-hidden="true"></i> Hapus</button>
    </div>

    {{-- Jenis packaging --}}
    <div class="sg-field">
        <label class="sg-label" for="{{ $fidI('jenis_packaging') }}">Jenis packaging <span class="sg-wajib" aria-hidden="true">*</span></label>
        <select id="{{ $fidI('jenis_packaging') }}" name="{{ $n }}[jenis_packaging]" class="form-select @error($k('jenis_packaging')) is-invalid @enderror" data-pilih-lainnya required {!! $aria($k('jenis_packaging')) !!}>
            <option value="">Pilih jenis packaging</option>
            @foreach ($masterPackaging as $m)
                <option value="{{ $m }}" @selected($v('jenis_packaging') === $m)>{{ $m }}</option>
            @endforeach
            <option value="{{ $lainnya }}" @selected($v('jenis_packaging') === $lainnya)>Lainnya…</option>
        </select>
        {!! $galat($k('jenis_packaging')) !!}
        <div class="mt-2" data-isian-lainnya @if ($v('jenis_packaging') !== $lainnya) hidden @endif>
            <label class="visually-hidden" for="{{ $fidI('jenis_packaging_lainnya') }}">Tulis jenis packaging</label>
            <input type="text" id="{{ $fidI('jenis_packaging_lainnya') }}" name="{{ $n }}[jenis_packaging_lainnya]" value="{{ $v('jenis_packaging_lainnya') }}" maxlength="150"
                   class="form-control @error($k('jenis_packaging_lainnya')) is-invalid @enderror" placeholder="Tulis jenis packaging" {!! $aria($k('jenis_packaging_lainnya')) !!}>
            {!! $galat($k('jenis_packaging_lainnya')) !!}
        </div>
    </div>

    {{-- Ukuran --}}
    <fieldset class="sg-field">
        <legend class="sg-label">Ukuran (cm)</legend>
        <div class="sg-ukuran">
            @foreach (['panjang' => 'P', 'lebar' => 'L', 'tinggi' => 'T'] as $dim => $huruf)
                <div>
                    <input type="text" inputmode="decimal" id="{{ $fidI($dim) }}" name="{{ $n }}[{{ $dim }}]" value="{{ $ukuranTampil($v($dim)) }}" autocomplete="off"
                           class="form-control @error($k($dim)) is-invalid @enderror" aria-label="{{ ucfirst($dim) }} (cm)" {!! $aria($k($dim)) !!}>
                    <small aria-hidden="true">{{ $huruf }}</small>
                </div>
                @if (! $loop->last)<span class="sg-x" aria-hidden="true">×</span>@endif
            @endforeach
        </div>
        {!! $galat($k('panjang')) !!}{!! $galat($k('lebar')) !!}{!! $galat($k('tinggi')) !!}
    </fieldset>

    {{-- Bahan & gramasi --}}
    <div class="sg-dua">
        <div class="sg-field">
            <label class="sg-label" for="{{ $fidI('jenis_bahan') }}">Bahan <span class="sg-wajib" aria-hidden="true">*</span></label>
            <select id="{{ $fidI('jenis_bahan') }}" name="{{ $n }}[jenis_bahan]" class="form-select @error($k('jenis_bahan')) is-invalid @enderror" data-pilih-lainnya required {!! $aria($k('jenis_bahan')) !!}>
                <option value="">Pilih bahan</option>
                @foreach ($masterBahan as $m)
                    <option value="{{ $m }}" @selected($v('jenis_bahan') === $m)>{{ $m }}</option>
                @endforeach
                <option value="{{ $lainnya }}" @selected($v('jenis_bahan') === $lainnya)>Lainnya…</option>
            </select>
            {!! $galat($k('jenis_bahan')) !!}
            <div class="mt-2" data-isian-lainnya @if ($v('jenis_bahan') !== $lainnya) hidden @endif>
                <label class="visually-hidden" for="{{ $fidI('jenis_bahan_lainnya') }}">Tulis bahan</label>
                <input type="text" id="{{ $fidI('jenis_bahan_lainnya') }}" name="{{ $n }}[jenis_bahan_lainnya]" value="{{ $v('jenis_bahan_lainnya') }}" maxlength="150"
                       class="form-control @error($k('jenis_bahan_lainnya')) is-invalid @enderror" placeholder="Tulis bahan" {!! $aria($k('jenis_bahan_lainnya')) !!}>
                {!! $galat($k('jenis_bahan_lainnya')) !!}
            </div>
        </div>
        <div class="sg-field">
            <label class="sg-label" for="{{ $fidI('gramasi') }}">Gramasi</label>
            <input type="text" id="{{ $fidI('gramasi') }}" name="{{ $n }}[gramasi]" value="{{ $v('gramasi') }}" maxlength="50" placeholder="mis. 250 gsm"
                   class="form-control @error($k('gramasi')) is-invalid @enderror" {!! $aria($k('gramasi')) !!}>
            {!! $galat($k('gramasi')) !!}
        </div>
    </div>

    {{-- Cetak --}}
    <div class="sg-dua">
        <div class="sg-field">
            <label class="sg-label" for="{{ $fidI('jenis_cetak') }}">Jenis cetak</label>
            <select id="{{ $fidI('jenis_cetak') }}" name="{{ $n }}[jenis_cetak]" class="form-select @error($k('jenis_cetak')) is-invalid @enderror" data-pilih-lainnya {!! $aria($k('jenis_cetak')) !!}>
                <option value="">Tanpa cetak</option>
                @foreach ($masterCetak as $m)
                    <option value="{{ $m }}" @selected($v('jenis_cetak') === $m)>{{ $m }}</option>
                @endforeach
                <option value="{{ $lainnya }}" @selected($v('jenis_cetak') === $lainnya)>Lainnya…</option>
            </select>
            {!! $galat($k('jenis_cetak')) !!}
            <div class="mt-2" data-isian-lainnya @if ($v('jenis_cetak') !== $lainnya) hidden @endif>
                <label class="visually-hidden" for="{{ $fidI('jenis_cetak_lainnya') }}">Tulis jenis cetak</label>
                <input type="text" id="{{ $fidI('jenis_cetak_lainnya') }}" name="{{ $n }}[jenis_cetak_lainnya]" value="{{ $v('jenis_cetak_lainnya') }}" maxlength="150"
                       class="form-control @error($k('jenis_cetak_lainnya')) is-invalid @enderror" placeholder="Tulis jenis cetak" {!! $aria($k('jenis_cetak_lainnya')) !!}>
                {!! $galat($k('jenis_cetak_lainnya')) !!}
            </div>
        </div>
        <div class="sg-field">
            <label class="sg-label" for="{{ $fidI('warna_cetak') }}">Warna cetak</label>
            <input type="text" id="{{ $fidI('warna_cetak') }}" name="{{ $n }}[warna_cetak]" value="{{ $v('warna_cetak') }}" maxlength="100" placeholder="mis. 1 warna, 4/0"
                   class="form-control @error($k('warna_cetak')) is-invalid @enderror" {!! $aria($k('warna_cetak')) !!}>
            {!! $galat($k('warna_cetak')) !!}
        </div>
    </div>

    {{-- Finishing (multi) --}}
    <fieldset class="sg-field" id="{{ $fidI('finishing') }}">
        <legend class="sg-label">Finishing <span class="fw-normal sg-muted">(boleh lebih dari satu)</span></legend>
        <div class="sg-chips">
            @foreach ($masterFinishing as $m)
                <label class="sg-chip">
                    <input type="checkbox" name="{{ $n }}[finishing][]" value="{{ $m }}" @checked(in_array($m, $finDipilih, true))>
                    <span><i class="fa-solid fa-check" aria-hidden="true"></i>{{ $m }}</span>
                </label>
            @endforeach
            <button type="button" class="sg-chip-btn" data-toggle-fin-lain aria-pressed="{{ $adaFinLain ? 'true' : 'false' }}" aria-controls="{{ $fidI('finishing_lainnya') }}-wadah">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Lainnya
            </button>
        </div>
        {!! $galat($k('finishing')) !!}
        <div class="mt-2" id="{{ $fidI('finishing_lainnya') }}-wadah" data-fin-lain @if (! $adaFinLain) hidden @endif>
            <label class="visually-hidden" for="{{ $fidI('finishing_lainnya') }}">Finishing lainnya</label>
            <input type="text" id="{{ $fidI('finishing_lainnya') }}" name="{{ $n }}[finishing_lainnya]" value="{{ $v('finishing_lainnya') }}" maxlength="255"
                   class="form-control @error($k('finishing_lainnya')) is-invalid @enderror" placeholder="Tulis finishing lainnya" {!! $aria($k('finishing_lainnya')) !!}>
            {!! $galat($k('finishing_lainnya')) !!}
        </div>
    </fieldset>

    <div class="sg-field">
        <label class="sg-label" for="{{ $fidI('aksesori') }}">Tali / aksesori</label>
        <input type="text" id="{{ $fidI('aksesori') }}" name="{{ $n }}[aksesori]" value="{{ $v('aksesori') }}" maxlength="255" placeholder="mis. Tali kur"
               class="form-control @error($k('aksesori')) is-invalid @enderror" {!! $aria($k('aksesori')) !!}>
        {!! $galat($k('aksesori')) !!}
    </div>

    <div class="sg-dua">
        <div class="sg-field">
            <label class="sg-label" for="{{ $fidI('jumlah') }}">Jumlah <span class="sg-wajib" aria-hidden="true">*</span></label>
            <div class="sg-imbuh sg-imbuh-belakang">
                <input type="text" inputmode="numeric" id="{{ $fidI('jumlah') }}" name="{{ $n }}[jumlah]" value="{{ $v('jumlah') }}" required autocomplete="off"
                       class="form-control sg-angka @error($k('jumlah')) is-invalid @enderror" data-jumlah {!! $aria($k('jumlah')) !!}>
                <span class="sg-imbuh-teks" aria-hidden="true">pcs</span>
            </div>
            {!! $galat($k('jumlah')) !!}
        </div>
        <div class="sg-field">
            <label class="sg-label" for="{{ $fidI('harga_satuan') }}">Harga satuan @if ($hargaWajib)<span class="sg-wajib" aria-hidden="true">*</span>@else<span class="fw-normal sg-muted">(opsional)</span>@endif</label>
            <div class="sg-imbuh sg-imbuh-depan">
                <span class="sg-imbuh-teks" aria-hidden="true">Rp</span>
                <input type="text" inputmode="numeric" id="{{ $fidI('harga_satuan') }}" name="{{ $n }}[harga_satuan]" value="{{ $hargaTampil }}" @if ($hargaWajib) required @endif autocomplete="off"
                       class="form-control sg-angka @error($k('harga_satuan')) is-invalid @enderror" data-rupiah data-harga
                       @if ($errors->has($k('harga_satuan'))) aria-invalid="true" aria-describedby="{{ $gid($k('harga_satuan')) }}" @elseif (! $hargaWajib) aria-describedby="{{ $fidI('bantu-harga') }}" @endif>
            </div>
            {!! $galat($k('harga_satuan')) !!}
            @unless ($hargaWajib)
                <p class="sg-bantu" id="{{ $fidI('bantu-harga') }}">Boleh dikosongkan — harga dihitung produksi.</p>
            @endunless
        </div>
    </div>

    <div class="sg-subtotal">
        <span class="sg-muted">Subtotal</span>
        <strong data-subtotal>Rp 0</strong>
    </div>
</div>
