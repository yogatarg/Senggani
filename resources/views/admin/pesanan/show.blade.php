@extends('admin.layouts.pesanan')

@section('title', $pesanan->nomor_order)

@php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $status = $pesanan->status_produksi;
@endphp

@section('content')
<p><a href="{{ route('admin.pesanan.index') }}">← Pesanan</a></p>

<h1>{{ $pesanan->nomor_order }}</h1>
<p>{{ $pesanan->nama_customer }} · +{{ $pesanan->nomor_whatsapp }}</p>
<p>{{ $pesanan->alamat }}, {{ $pesanan->kota }}</p>
<p>
    Produksi: <strong>{{ $status->label() }}</strong> ·
    Bayar: <strong>{{ $pesanan->label_status_pembayaran }}</strong> ·
    Deadline {{ $pesanan->deadline->translatedFormat('d M Y') }}
    @if ($pesanan->penanda_deadline === 'lewat')
        [LEWAT {{ abs($pesanan->hari_menuju_deadline) }} hari]
    @elseif ($pesanan->penanda_deadline === 'mendekat')
        [{{ $pesanan->hari_menuju_deadline === 0 ? 'Hari ini' : $pesanan->hari_menuju_deadline . ' hari lagi' }}]
    @endif
</p>

@can('update', $pesanan)
    <p>
        <a href="{{ route('admin.pesanan.edit', $pesanan) }}">Edit</a> ·
        <a href="{{ $pesanan->link_whatsapp }}" target="_blank" rel="noopener">WhatsApp Customer</a>
    </p>
@endcan

<h2>Status produksi</h2>
<ol>
    @foreach ($alurStatus as $s)
        <li>
            {{ $s->label() }}
            @if ($s === $status) <strong>(sekarang)</strong> @endif
            @isset($waktuStatus[$s->value]) — {{ $waktuStatus[$s->value]->translatedFormat('d M Y H.i') }} @endisset
        </li>
    @endforeach
</ol>

@if ($status === \App\Enums\StatusProduksi::Dibatalkan)
    <p><strong>Order ini dibatalkan.</strong></p>
    @can('pulihkan', $pesanan)
        <form method="POST" action="{{ route('admin.pesanan.pulihkan', $pesanan) }}">
            @csrf
            <input type="text" name="catatan" placeholder="Catatan (opsional)" maxlength="500">
            <button type="submit" onclick="return confirm('Pulihkan order ini?')">Pulihkan order</button>
        </form>
    @endcan
@else
    @can('ubahStatus', $pesanan)
        @if ($statusBerikutnya)
            <form method="POST" action="{{ route('admin.pesanan.status', $pesanan) }}">
                @csrf
                <input type="hidden" name="status_lama" value="{{ $status->value }}">
                <input type="hidden" name="status_baru" value="{{ $statusBerikutnya->value }}">
                <input type="text" name="catatan" placeholder="Catatan (opsional)" maxlength="500">
                <button type="submit" onclick="return confirm('Pindahkan ke {{ $statusBerikutnya->label() }}?')">Pindahkan ke {{ $statusBerikutnya->label() }} →</button>
            </form>
        @else
            <p>Pesanan sudah selesai.</p>
        @endif
        @if ($statusSebelumnya)
            <form method="POST" action="{{ route('admin.pesanan.status', $pesanan) }}">
                @csrf
                <input type="hidden" name="status_lama" value="{{ $status->value }}">
                <input type="hidden" name="status_baru" value="{{ $statusSebelumnya->value }}">
                <input type="text" name="catatan" placeholder="Kenapa dikembalikan?" maxlength="500">
                <button type="submit" onclick="return confirm('Kembalikan ke {{ $statusSebelumnya->label() }}?')">← Kembalikan ke {{ $statusSebelumnya->label() }}</button>
            </form>
        @endif
    @endcan
    @if ($status->bisaDibatalkan())
        @can('batalkan', $pesanan)
            <form method="POST" action="{{ route('admin.pesanan.batal', $pesanan) }}">
                @csrf
                <input type="hidden" name="status_lama" value="{{ $status->value }}">
                <input type="text" name="catatan" placeholder="Alasan pembatalan (opsional)" maxlength="500">
                <button type="submit" onclick="return confirm('Batalkan order ini?')">Batalkan order</button>
            </form>
        @endcan
    @endif
@endif

<h2>Item pesanan ({{ $pesanan->detailPesanan->count() }})</h2>
@foreach ($pesanan->detailPesanan as $d)
    <div>
        <h3>{{ $loop->iteration }}. {{ $d->jenis_packaging }} {{ $d->ukuran_teks }} — {{ number_format($d->jumlah, 0, ',', '.') }} pcs</h3>
        <dl>
            <dt>Bahan</dt><dd>{{ $d->jenis_bahan }} {{ $d->gramasi }}</dd>
            @if ($d->jenis_cetak || $d->warna_cetak)<dt>Cetak</dt><dd>{{ $d->jenis_cetak }} {{ $d->warna_cetak }}</dd>@endif
            @if ($d->finishing)<dt>Finishing</dt><dd>{{ $d->finishing }}</dd>@endif
            @if ($d->aksesori)<dt>Tali/aksesori</dt><dd>{{ $d->aksesori }}</dd>@endif
            <dt>Harga</dt><dd>{{ $rp($d->harga_satuan) }} × {{ number_format($d->jumlah, 0, ',', '.') }} = {{ $rp($d->subtotal) }}</dd>
        </dl>
    </div>
@endforeach

<h2>Desain</h2>
<p>Approval: {{ $pesanan->status_desain->label() }}</p>
@if ($pesanan->link_desain)
    <p><a href="{{ $pesanan->link_desain }}" target="_blank" rel="noopener">Buka folder Google Drive ↗</a></p>
@else
    <p>Link Drive belum diisi.</p>
@endif
@forelse ($pesanan->file as $f)
    <figure>
        <a href="{{ route('admin.pesanan.file.lihat', [$pesanan, $f]) }}" target="_blank">
            <img src="{{ route('admin.pesanan.file.lihat', [$pesanan, $f]) }}" alt="{{ $f->jenis->label() }} {{ $loop->iteration }}" width="120" loading="lazy">
        </a>
        <figcaption>
            {{ $f->jenis->label() }} · {{ $f->nama_asli }}
            · <a href="{{ route('admin.pesanan.file.lihat', [$pesanan, $f, 'unduh' => 1]) }}">Unduh</a>
            @can('hapusFile', $pesanan)
                <form method="POST" action="{{ route('admin.pesanan.file.hapus', [$pesanan, $f]) }}" style="display:inline">
                    @csrf @method('DELETE')
                    <button type="submit" onclick="return confirm('Hapus gambar ini?')">Hapus</button>
                </form>
            @endcan
        </figcaption>
    </figure>
@empty
    <p>Belum ada mockup.</p>
@endforelse
@if ($pesanan->catatan_desain)<p>Catatan desain: {{ $pesanan->catatan_desain }}</p>@endif
@if ($pesanan->catatan_admin)<p>Catatan order: {{ $pesanan->catatan_admin }}</p>@endif

<h2>Pembayaran</h2>
<p>Total {{ $rp($pesanan->total) }} · DP {{ $rp($pesanan->dp) }} · Sisa {{ $rp($pesanan->sisa) }} · {{ $pesanan->label_status_pembayaran }}
    @if ($pesanan->tanggal_lunas) (lunas {{ $pesanan->tanggal_lunas->translatedFormat('d M Y') }}) @endif
</p>
@if (! $pesanan->tanggal_lunas && (float) $pesanan->total > 0)
    @can('catatLunas', $pesanan)
        <form method="POST" action="{{ route('admin.pesanan.lunas', $pesanan) }}">
            @csrf
            <input type="date" name="tanggal_lunas" value="{{ today()->toDateString() }}">
            <button type="submit">Catat lunas</button>
        </form>
    @endcan
@endif

<h2>Catatan progres</h2>
<form method="POST" action="{{ route('admin.pesanan.catatan', $pesanan) }}">
    @csrf
    <p>
        @foreach ($saranCatatan as $saran)
            <button type="button" onclick="this.form.catatan.value = this.textContent.trim()">{{ $saran }}</button>
        @endforeach
    </p>
    <textarea name="catatan" rows="3" maxlength="500" placeholder="Tulis catatan progres…">{{ old('catatan') }}</textarea>
    <button type="submit">Simpan catatan</button>
</form>

<h2>Riwayat</h2>
<ul>
    @foreach ($pesanan->riwayat as $r)
        <li>
            {{ $r->created_at->translatedFormat('d M Y, H.i') }} · {{ $r->nama_pengguna }} —
            @switch($r->jenis)
                @case('dibuat') Order dibuat @break
                @case('maju') Status: {{ $r->status_dari->label() }} → {{ $r->status_ke->label() }} @break
                @case('mundur') Dikembalikan ke {{ $r->status_ke->label() }} @break
                @case('batal') Order dibatalkan @break
                @case('pulihkan') Order dipulihkan ke {{ $r->status_ke->label() }} @break
                @default Catatan
            @endswitch
            @if ($r->catatan && $r->jenis !== 'dibuat'): {{ $r->catatan }} @endif
        </li>
    @endforeach
</ul>

@can('delete', $pesanan)
    <hr>
    <form method="POST" action="{{ route('admin.pesanan.destroy', $pesanan) }}">
        @csrf @method('DELETE')
        <button type="submit" onclick="return confirm('Hapus order {{ $pesanan->nomor_order }}? Gunakan hanya untuk order salah input.')">Hapus order (salah input)</button>
    </form>
@endcan
@endsection
