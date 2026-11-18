@extends('admin.layouts.pesanan')

@section('title', 'Pesanan')

@section('content')
<h1>Pesanan</h1>
<p>Diperbarui <span id="waktu-diperbarui">{{ now()->format('H.i') }}</span></p>
<p id="ada-perubahan" hidden>Ada perubahan pesanan. <a href="{{ request()->fullUrl() }}">Muat ulang</a></p>

<nav aria-label="Tab status">
    @foreach ($daftarTab as $kunci => $label)
        <a href="{{ route('admin.pesanan.index', ['tab' => $kunci, 'urut' => $urut]) }}"
           @if ($tab === $kunci && $q === '') aria-current="page" @endif>
            {{ $label }} (<span data-jumlah-tab="{{ $kunci }}">{{ $ringkasan['jumlah'][$kunci] }}</span>)
        </a>
        @if (! $loop->last) | @endif
    @endforeach
</nav>

<form method="GET" action="{{ route('admin.pesanan.index') }}">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <input type="search" name="q" value="{{ $q }}" placeholder="Cari nama customer atau nomor order" enterkeyhint="search">
    <select name="urut" onchange="this.form.submit()">
        @foreach ($daftarUrut as $kunci => $label)
            <option value="{{ $kunci }}" @selected($urut === $kunci)>{{ $label }}</option>
        @endforeach
    </select>
    <button type="submit">Cari</button>
</form>

@if ($q !== '')
    <p>{{ $pesanan->total() }} order cocok dengan "{{ $q }}" (semua status, termasuk History) ·
        <a href="{{ route('admin.pesanan.index', ['tab' => $tab]) }}">Hapus pencarian</a></p>
@endif

@forelse ($pesanan as $p)
    @php $itemPertama = $p->detailPesanan->first(); @endphp
    <article>
        <h3><a href="{{ route('admin.pesanan.show', $p) }}">{{ $p->nomor_order }}</a> — {{ $p->nama_customer }}</h3>
        <p>
            @if ($itemPertama)
                {{ $itemPertama->jenis_packaging }} {{ $itemPertama->ukuran_teks }}
            @endif
            @if ($p->detail_pesanan_count > 1)
                (+{{ $p->detail_pesanan_count - 1 }} item) · Total {{ number_format((int) $p->total_jumlah, 0, ',', '.') }} pcs
            @else
                · {{ number_format((int) $p->total_jumlah, 0, ',', '.') }} pcs
            @endif
        </p>
        <p>
            Deadline {{ $p->deadline->translatedFormat('d M Y') }}
            @if ($p->penanda_deadline === 'lewat')
                [LEWAT {{ abs($p->hari_menuju_deadline) }} hari]
            @elseif ($p->penanda_deadline === 'mendekat')
                [{{ $p->hari_menuju_deadline === 0 ? 'Hari ini' : $p->hari_menuju_deadline . ' hari lagi' }}]
            @endif
        </p>
        <p>Produksi: {{ $p->status_produksi->label() }} · Bayar: {{ $p->label_status_pembayaran }}</p>
    </article>
    <hr>
@empty
    <p>{{ $q !== '' ? "Tidak ada pesanan untuk \"{$q}\"." : 'Belum ada pesanan di tab ini.' }}</p>
@endforelse

{{ $pesanan->links() }}

<script>
    // Auto-refresh ringan: cek jumlah per tab tiap 60 detik saat tab browser terlihat
    (function () {
        var penanda = @json($ringkasan['penanda']);
        setInterval(function () {
            if (document.visibilityState !== 'visible') return;
            fetch(@json(route('admin.pesanan.jumlah')), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data) return;
                    Object.keys(data.jumlah).forEach(function (k) {
                        var el = document.querySelector('[data-jumlah-tab="' + k + '"]');
                        if (el) el.textContent = data.jumlah[k];
                    });
                    if (data.penanda !== penanda) document.getElementById('ada-perubahan').hidden = false;
                })
                .catch(function () {});
        }, 60000);
    })();
</script>
@endsection
