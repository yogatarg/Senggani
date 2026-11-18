{{-- Satu item order. $i = indeks, $it = nilai input (array). --}}
@php
    $n = "item[{$i}]";
    $v = fn ($k) => $it[$k] ?? null;
@endphp
<div data-item>
    <h4>Item {{ is_numeric($i) ? $i + 1 : '' }} <button type="button" data-hapus-item>Hapus</button></h4>
    <p>
        <label>Jenis packaging*
            <select name="{{ $n }}[jenis_packaging]">
                <option value="">— pilih —</option>
                @foreach ($masterPackaging as $m)
                    <option value="{{ $m }}" @selected($v('jenis_packaging') === $m)>{{ $m }}</option>
                @endforeach
                <option value="{{ $lainnya }}" @selected($v('jenis_packaging') === $lainnya)>Lainnya…</option>
            </select>
        </label>
        <input type="text" name="{{ $n }}[jenis_packaging_lainnya]" value="{{ $v('jenis_packaging_lainnya') }}" placeholder="Tulis jenis packaging (bila Lainnya)">
    </p>
    <p>Ukuran (cm):
        <input type="text" inputmode="decimal" name="{{ $n }}[panjang]" value="{{ $v('panjang') }}" placeholder="P" size="5"> ×
        <input type="text" inputmode="decimal" name="{{ $n }}[lebar]" value="{{ $v('lebar') }}" placeholder="L" size="5"> ×
        <input type="text" inputmode="decimal" name="{{ $n }}[tinggi]" value="{{ $v('tinggi') }}" placeholder="T" size="5">
    </p>
    <p>
        <label>Bahan*
            <select name="{{ $n }}[jenis_bahan]">
                <option value="">— pilih —</option>
                @foreach ($masterBahan as $m)
                    <option value="{{ $m }}" @selected($v('jenis_bahan') === $m)>{{ $m }}</option>
                @endforeach
                <option value="{{ $lainnya }}" @selected($v('jenis_bahan') === $lainnya)>Lainnya…</option>
            </select>
        </label>
        <input type="text" name="{{ $n }}[jenis_bahan_lainnya]" value="{{ $v('jenis_bahan_lainnya') }}" placeholder="Tulis bahan (bila Lainnya)">
        <label>Gramasi <input type="text" name="{{ $n }}[gramasi]" value="{{ $v('gramasi') }}" placeholder="250 gsm" maxlength="50"></label>
    </p>
    <p>
        <label>Jenis cetak
            <select name="{{ $n }}[jenis_cetak]">
                <option value="">— tidak ada —</option>
                @foreach ($masterCetak as $m)
                    <option value="{{ $m }}" @selected($v('jenis_cetak') === $m)>{{ $m }}</option>
                @endforeach
                <option value="{{ $lainnya }}" @selected($v('jenis_cetak') === $lainnya)>Lainnya…</option>
            </select>
        </label>
        <input type="text" name="{{ $n }}[jenis_cetak_lainnya]" value="{{ $v('jenis_cetak_lainnya') }}" placeholder="Tulis jenis cetak (bila Lainnya)">
        <label>Warna cetak <input type="text" name="{{ $n }}[warna_cetak]" value="{{ $v('warna_cetak') }}" placeholder="4/0, 1 warna" maxlength="100"></label>
    </p>
    <p>Finishing:
        @foreach ($masterFinishing as $m)
            <label><input type="checkbox" name="{{ $n }}[finishing][]" value="{{ $m }}" @checked(in_array($m, (array) $v('finishing'), true))> {{ $m }}</label>
        @endforeach
        <input type="text" name="{{ $n }}[finishing_lainnya]" value="{{ $v('finishing_lainnya') }}" placeholder="Finishing lainnya">
    </p>
    <p><label>Tali/aksesori <input type="text" name="{{ $n }}[aksesori]" value="{{ $v('aksesori') }}" maxlength="255"></label></p>
    <p>
        <label>Jumlah* <input type="text" inputmode="numeric" name="{{ $n }}[jumlah]" value="{{ $v('jumlah') }}"> pcs</label>
        <label>Harga satuan* Rp <input type="text" inputmode="numeric" name="{{ $n }}[harga_satuan]" value="{{ $v('harga_satuan') }}"></label>
    </p>
</div>
