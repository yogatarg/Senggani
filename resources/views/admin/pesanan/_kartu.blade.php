{{--
    Kartu SPP di daftar. $p = Pesanan (dengan detailPesanan, detail_pesanan_count, total_jumlah).
    Opsional: $tanpaKode (true di halaman customer), $tingkat ('h2' default, 'h3' bila di bawah judul section).
--}}
@php
    $itemPertama = $p->detailPesanan->first();
    $jumlahItem = (int) $p->detail_pesanan_count;
    $totalPcs = number_format((int) $p->total_jumlah, 0, ',', '.');
    $tagJudul = ($tingkat ?? 'h2') === 'h3' ? 'h3' : 'h2';
    // Info harga hanya selama Hitung Harga; tanda pelunasan di status mana pun ia muncul
    $tanpaHarga = $p->status_produksi === \App\Enums\StatusProduksi::HitungHarga ? $p->jumlah_item_tanpa_harga : 0;
    $tungguLunas = $p->menunggu_pelunasan;
    $tampilKode = $p->customer_id && ! ($tanpaKode ?? false) && \Illuminate\Support\Facades\Gate::allows('kelola-customer');
    $ringkasLabel = collect([
        $p->kode_tampil,
        $p->nama_customer . ($p->nama_brand ? ' / ' . $p->nama_brand : ''),
        $p->status_produksi->label(),
        $tanpaHarga ? $tanpaHarga . ' item belum diberi harga' : null,
        $tungguLunas ? 'menunggu pelunasan' : null,
        $p->penanda_deadline === 'lewat' ? 'lewat deadline ' . abs($p->hari_menuju_deadline) . ' hari'
            : ($p->penanda_deadline === 'mendekat' ? 'deadline dekat, ' . $p->deadline->translatedFormat('j F') : null),
    ])->filter()->implode(', ');
@endphp
<article class="sg-order">
    <div class="sg-order-atas">
        <span class="sg-order-no">
            {{ $p->kode_tampil }}
            @if ($tampilKode)
                <span aria-hidden="true">·</span>
                <a href="{{ route('admin.customer.show', $p->customer_id) }}" class="sg-order-kode" aria-label="Customer {{ \App\Models\Customer::kodeDari($p->customer_id) }}">{{ \App\Models\Customer::kodeDari($p->customer_id) }}</a>
            @endif
        </span>
        @include('admin.pesanan._deadline', ['p' => $p])
    </div>
    <{{ $tagJudul }} class="sg-order-nama">
        <a href="{{ route('admin.pesanan.show', $p) }}" class="stretched-link" aria-label="{{ $ringkasLabel }}">{{ $p->nama_customer }}</a>
    </{{ $tagJudul }}>
    @if ($p->nama_brand)
        <p class="sg-order-brand">{{ $p->nama_brand }}</p>
    @endif
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
    <div class="sg-order-status sg-order-tanda">
        @include('admin.pesanan._badge-status', ['status' => $p->status_produksi])
        @if ($tungguLunas)
            <span class="sg-bayar sg-by-dp"><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>Menunggu pelunasan</span>
        @endif
        @if ($tanpaHarga)
            <span class="sg-info-harga"><i class="fa-solid fa-calculator" aria-hidden="true"></i>{{ $tanpaHarga }} item belum diberi harga</span>
        @endif
    </div>
</article>
