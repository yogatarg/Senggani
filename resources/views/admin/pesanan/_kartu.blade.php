{{-- Kartu order di daftar. $p = Pesanan (dengan detailPesanan, detail_pesanan_count, total_jumlah). --}}
@php
    $itemPertama = $p->detailPesanan->first();
    $jumlahItem = (int) $p->detail_pesanan_count;
    $totalPcs = number_format((int) $p->total_jumlah, 0, ',', '.');
    $ringkasLabel = collect([
        $p->nomor_spp_teks,
        $p->nama_customer,
        $p->status_produksi->label(),
        'bayar ' . $p->label_status_pembayaran,
        $p->penanda_deadline === 'lewat' ? 'lewat deadline ' . abs($p->hari_menuju_deadline) . ' hari'
            : ($p->penanda_deadline === 'mendekat' ? 'deadline dekat, ' . $p->deadline->translatedFormat('j F') : null),
    ])->filter()->implode(', ');
@endphp
<article class="sg-order">
    <div class="sg-order-atas">
        <span class="sg-order-no">{{ $p->nomor_spp_teks }}</span>
        @include('admin.pesanan._deadline', ['p' => $p])
    </div>
    <h2 class="sg-order-nama">
        <a href="{{ route('admin.pesanan.show', $p) }}" class="stretched-link" aria-label="{{ $ringkasLabel }}">{{ $p->nama_customer }}</a>
    </h2>
    @if ($itemPertama)
        <p class="sg-order-item">{{ $itemPertama->jenis_packaging }}@if ($itemPertama->ukuran_teks) {{ $itemPertama->ukuran_teks }}@endif</p>
    @endif
    <p class="sg-order-jml">
        @if ($jumlahItem > 1)
            <span class="sg-chip-kecil">+{{ $jumlahItem - 1 }} item</span> Total {{ $totalPcs }} pcs
        @else
            {{ $totalPcs }} pcs
        @endif
    </p>
    <dl class="sg-order-status">
        <div><dt>Produksi</dt><dd>@include('admin.pesanan._badge-status', ['status' => $p->status_produksi])</dd></div>
        <div><dt>Bayar</dt><dd>@include('admin.pesanan._badge-bayar', ['p' => $p, 'tanpaLabel' => true])</dd></div>
    </dl>
</article>
