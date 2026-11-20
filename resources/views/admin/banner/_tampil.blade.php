{{-- Tombol status tampil banner (PATCH, tanpa konfirmasi karena mudah dibalik). Variabel: $item. --}}
<form action="{{ route('admin.banner.aktif', $item) }}" method="POST" data-kirim class="m-0">
    @csrf @method('PATCH')
    @if ($item->aktif)
        <button type="submit" class="sg-tampil sg-aktif" data-teks-kirim="Menyimpan…" aria-label="Banner {{ $item->judul }} aktif. Sembunyikan dari website"><i class="fa-solid fa-eye" aria-hidden="true"></i> Aktif</button>
    @else
        <button type="submit" class="sg-tampil" data-teks-kirim="Menyimpan…" aria-label="Banner {{ $item->judul }} nonaktif. Tampilkan di website"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> Nonaktif</button>
    @endif
</form>
