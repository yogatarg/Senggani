{{--
    Modal konfirmasi ubah status (maju/mundur/batal/pulihkan), form POST biasa.
    Variabel: $idModal, $judul, $aksi, $ke (StatusProduksi|null), $placeholder, $tombol, $gaya, $keterangan (opsional).
    Bila status sudah diubah orang lain, backend menolak (bentrok) dan halaman memuat ulang dengan pesan.
--}}
<div class="modal fade modal-sheet" id="{{ $idModal }}" tabindex="-1" aria-labelledby="{{ $idModal }}Judul" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="{{ $aksi }}" data-kirim>
            @csrf
            <input type="hidden" name="status_lama" value="{{ $pesanan->status_produksi->value }}">
            @if ($ke && $idModal !== 'modalBatal')
                <input type="hidden" name="status_baru" value="{{ $ke->value }}">
            @endif
            <div class="modal-header">
                <h2 class="modal-title" id="{{ $idModal }}Judul">{{ $judul }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="m-0 sg-muted">{{ $pesanan->nomor_order }} · {{ $pesanan->nama_customer }}</p>
                @if ($ke)
                    <p class="sg-modal-pindah">
                        Dari @include('admin.pesanan._badge-status', ['status' => $pesanan->status_produksi])
                        ke @include('admin.pesanan._badge-status', ['status' => $ke])
                    </p>
                @endif
                @isset($keterangan)<p class="mb-3">{{ $keterangan }}</p>@endisset
                <label for="{{ $idModal }}Catatan" class="sg-label">Catatan</label>
                <textarea id="{{ $idModal }}Catatan" name="catatan" rows="2" maxlength="500" class="form-control" style="min-height:72px" placeholder="{{ $placeholder }}"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="sg-btn sg-btn-sekunder" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="sg-btn {{ $gaya }}" data-teks-kirim="Menyimpan…">{{ $tombol }}</button>
            </div>
        </form>
    </div>
</div>
