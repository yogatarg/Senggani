{{-- Jumlah bahan · finishing · cetak. Bahan & finishing wajib untuk status Lengkap. Variabel: $item (dengan *_count). --}}
<p class="sg-hitung-atribut sg-angka">
    @foreach ([['bahan', $item->bahan_count, true], ['finishing', $item->finishing_count, true], ['cetak', $item->cetak_count, false]] as [$nama, $jml, $wajib])
        @if ($jml > 0)
            <span>{{ $jml }} {{ $nama }}</span>
        @elseif ($wajib)
            <span class="sg-nol-wajib"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>0 {{ $nama }}</span>
        @else
            <span class="sg-nol">0 {{ $nama }}</span>
        @endif
        @unless ($loop->last)<span aria-hidden="true"> · </span>@endunless
    @endforeach
</p>
