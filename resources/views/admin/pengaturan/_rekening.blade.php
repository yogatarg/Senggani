{{--
    Satu baris rekening (Pengaturan Invoice). $i = indeks (atau "__I__" di template), $r = ['bank','nomor','atas_nama'].
    Indeks dirapikan ulang oleh pesanan.js ([data-rekening]) setiap baris ditambah/dihapus.
--}}
@php
    $k = fn (string $f) => "rekening.$i.$f";
    $idF = fn (string $f) => "f-rekening-$i-$f";
    $bank = trim((string) ($r['bank'] ?? ''));
    $no = is_numeric($i) ? $i + 1 : '';
@endphp
<div class="sg-rek-baris" data-rek-baris>
    @foreach (['bank' => 'Bank', 'nomor' => 'Nomor rekening', 'atas_nama' => 'Atas nama'] as $f => $label)
        @php $galat = is_numeric($i) && $errors->has($k($f)); @endphp
        <div class="sg-rek-isian">
            <label class="sg-label" for="{{ $idF($f) }}" data-rek-label="{{ $label }}">{{ $label }}<span class="visually-hidden" data-rek-ke> rekening ke-{{ $no }}</span></label>
            <input type="text" id="{{ $idF($f) }}" name="rekening[{{ $i }}][{{ $f }}]" value="{{ $r[$f] ?? '' }}" autocomplete="off"
                   @if ($f === 'bank') maxlength="30" list="daftar-bank" placeholder="BCA" data-rek-bank
                   @elseif ($f === 'nomor') maxlength="40" inputmode="numeric" placeholder="8023011164"
                   @else maxlength="100" @endif
                   class="form-control {{ $f === 'nomor' ? 'sg-angka' : '' }} {{ $galat ? 'is-invalid' : '' }}"
                   @if ($galat) aria-invalid="true" aria-describedby="g-rekening-{{ $i }}-{{ $f }}" @endif>
            @if ($galat)
                <p class="sg-galat" id="g-rekening-{{ $i }}-{{ $f }}"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $errors->first($k($f)) }}</p>
            @endif
        </div>
    @endforeach
    <button type="button" class="sg-rek-hapus" data-rek-hapus aria-label="Hapus rekening {{ $bank !== '' ? $bank : 'ke-' . $no }}">
        <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
    </button>
</div>
