{{--
    Tombol hapus berkonfirmasi (#modalKonfirmasi lewat data-konfirmasi).
    Variabel: $aksi (URL), $judul, $isi, $labelAria, opsional: $tombol ('Ya, hapus'), $ikon (true = tombol ikon saja).
--}}
<form action="{{ $aksi }}" method="POST" data-kirim
      data-konfirmasi="{{ $judul }}" data-konfirmasi-isi="{{ $isi }}" data-konfirmasi-tombol="{{ $tombol ?? 'Ya, hapus' }}" data-konfirmasi-bahaya>
    @csrf @method('DELETE')
    @if ($ikon ?? false)
        <button type="submit" class="sg-btn sg-btn-bahaya sg-btn-ikon" aria-label="{{ $labelAria }}" title="Hapus"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>
    @else
        <button type="submit" class="sg-btn sg-btn-bahaya" aria-label="{{ $labelAria }}"><i class="fa-solid fa-trash-can" aria-hidden="true"></i> Hapus</button>
    @endif
</form>
