{{-- Miniatur daftar. Variabel: $src (path storage atau null), $kelas ('sg-thumb-43'), $w (lebar px). Tinggi selalu 48. --}}
@if ($src)
    <span class="sg-thumb {{ $kelas }}"><img src="{{ asset('storage/' . $src) }}" alt="" width="{{ $w }}" height="48" loading="lazy"></span>
@else
    <span class="sg-thumb sg-thumb-kosong {{ $kelas }}"><i class="fa-regular fa-image" aria-hidden="true"></i><span class="visually-hidden">Belum ada gambar</span></span>
@endif
