@extends('admin.layouts.pesanan')

@section('title', 'Pengaturan Invoice')

{{--
    PLACEHOLDER BACKEND (fungsional, belum sesuai rancangan bagian 6): frontend merapikan menjadi
    4 kartu bernomor, komponen unggah gambar dengan pratinjau papan catur, baris rekening dinamis,
    bilah simpan sticky di HP, dan item sidebar "Pengaturan Invoice".

    Kontrak form (PUT admin.pengaturan.invoice.update, multipart):
    penandatangan_nama, penandatangan_jabatan, rekening[i][bank|nomor|atas_nama] (maks 4),
    qris_keterangan, keterangan_bawah, persen_dp, ttd, stempel, qris (file opsional).
    Hapus gambar: DELETE admin.pengaturan.invoice.hapus {jenis}. Unggah satuan (JSON/AJAX):
    POST admin.pengaturan.invoice.unggah {jenis} dengan field "gambar".
--}}

@section('content')
@php
    $rekening = old('rekening', $pengaturan['rekening']);
    $rekening = array_pad(array_values((array) $rekening), min(count((array) $rekening) + 1, $maksRekening), ['bank' => '', 'nomor' => '', 'atas_nama' => '']);
@endphp
<div class="sg-kepala">
    <div>
        <h1>Pengaturan Invoice</h1>
        <p class="sg-kepala-sub">Dipakai di semua invoice PDF. File disimpan privat, tidak ikut repo.</p>
    </div>
    <div>
        <a href="{{ route('admin.pengaturan.invoice.contoh') }}" target="_blank" rel="noopener" class="sg-btn sg-btn-sekunder">
            <i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Lihat contoh PDF
        </a>
        <p class="sg-bantu">Simpan dulu untuk melihat perubahan.</p>
    </div>
</div>

<div class="sg-kartu sg-sempit">
    <form method="POST" action="{{ route('admin.pengaturan.invoice.update') }}" enctype="multipart/form-data" data-kirim>
        @csrf @method('PUT')

        <h2 class="sg-kartu-judul">1. Penanda tangan</h2>
        <div class="mb-3">
            <label class="sg-label" for="f-penandatangan_nama">Nama penanda tangan</label>
            <input type="text" id="f-penandatangan_nama" name="penandatangan_nama" maxlength="100" class="form-control @error('penandatangan_nama') is-invalid @enderror"
                   value="{{ old('penandatangan_nama', $pengaturan['penandatangan_nama']) }}">
            @error('penandatangan_nama')<p class="sg-galat">{{ $message }}</p>@enderror
        </div>
        <div class="mb-3">
            <label class="sg-label" for="f-penandatangan_jabatan">Jabatan <span class="fw-normal sg-muted">(opsional)</span></label>
            <input type="text" id="f-penandatangan_jabatan" name="penandatangan_jabatan" maxlength="100" class="form-control @error('penandatangan_jabatan') is-invalid @enderror"
                   value="{{ old('penandatangan_jabatan', $pengaturan['penandatangan_jabatan']) }}">
            <p class="sg-bantu">Kosongkan bila tidak ingin ditampilkan.</p>
            @error('penandatangan_jabatan')<p class="sg-galat">{{ $message }}</p>@enderror
        </div>
        @foreach (['ttd', 'stempel'] as $jenis)
            <div class="mb-3">
                <label class="sg-label" for="f-{{ $jenis }}">{{ $labelGambar[$jenis] }}</label>
                @if ($gambar[$jenis])
                    <p><img src="{{ $gambar[$jenis] }}" alt="{{ $labelGambar[$jenis] }} tersimpan" style="max-width: 240px; max-height: 96px;"></p>
                @endif
                <input type="file" id="f-{{ $jenis }}" name="{{ $jenis }}" accept="image/png,image/jpeg,image/webp" class="form-control @error($jenis) is-invalid @enderror">
                <p class="sg-bantu">PNG transparan disarankan, maks 2 MB.</p>
                @error($jenis)<p class="sg-galat">{{ $message }}</p>@enderror
            </div>
        @endforeach

        <h2 class="sg-kartu-judul">2. Rekening bank</h2>
        @error('rekening')<p class="sg-galat">{{ $message }}</p>@enderror
        @foreach ($rekening as $i => $r)
            <div class="row g-2 mb-2">
                <div class="col-sm-3">
                    <label class="sg-label" for="f-rek-{{ $i }}-bank">Bank</label>
                    <input type="text" id="f-rek-{{ $i }}-bank" name="rekening[{{ $i }}][bank]" maxlength="30" list="daftar-bank" class="form-control" value="{{ $r['bank'] ?? '' }}">
                </div>
                <div class="col-sm-4">
                    <label class="sg-label" for="f-rek-{{ $i }}-nomor">Nomor rekening</label>
                    <input type="text" id="f-rek-{{ $i }}-nomor" name="rekening[{{ $i }}][nomor]" inputmode="numeric" maxlength="40" class="form-control sg-angka" value="{{ $r['nomor'] ?? '' }}">
                </div>
                <div class="col-sm-5">
                    <label class="sg-label" for="f-rek-{{ $i }}-atas_nama">Atas nama</label>
                    <input type="text" id="f-rek-{{ $i }}-atas_nama" name="rekening[{{ $i }}][atas_nama]" maxlength="100" class="form-control" value="{{ $r['atas_nama'] ?? '' }}">
                </div>
                @foreach (['bank', 'nomor', 'atas_nama'] as $f)
                    @error("rekening.$i.$f")<p class="sg-galat">{{ $message }}</p>@enderror
                @endforeach
            </div>
        @endforeach
        <datalist id="daftar-bank"><option value="BCA"><option value="BRI"><option value="Mandiri"><option value="BNI"></datalist>
        <p class="sg-bantu">Kosongkan semua isian satu baris untuk menghapus rekening itu. Maksimal {{ $maksRekening }} rekening.</p>

        <h2 class="sg-kartu-judul">3. QRIS</h2>
        <div class="mb-3">
            <label class="sg-label" for="f-qris">Gambar QR</label>
            @if ($gambar['qris'])
                <p><img src="{{ $gambar['qris'] }}" alt="QRIS tersimpan" style="width: 160px; height: 160px; object-fit: contain;"></p>
            @endif
            <input type="file" id="f-qris" name="qris" accept="image/png,image/jpeg,image/webp" class="form-control @error('qris') is-invalid @enderror">
            <p class="sg-bantu">Unggah area QR saja (persegi), minimal 300 px.</p>
            @error('qris')<p class="sg-galat">{{ $message }}</p>@enderror
        </div>
        <div class="mb-3">
            <label class="sg-label" for="f-qris_keterangan">Keterangan QRIS</label>
            <input type="text" id="f-qris_keterangan" name="qris_keterangan" maxlength="200" class="form-control @error('qris_keterangan') is-invalid @enderror"
                   value="{{ old('qris_keterangan', $pengaturan['qris_keterangan']) }}">
            @error('qris_keterangan')<p class="sg-galat">{{ $message }}</p>@enderror
        </div>

        <h2 class="sg-kartu-judul">4. Keterangan bawah invoice</h2>
        <div class="mb-3">
            <label class="sg-label" for="f-keterangan_bawah">Keterangan</label>
            <textarea id="f-keterangan_bawah" name="keterangan_bawah" rows="3" maxlength="500" class="form-control @error('keterangan_bawah') is-invalid @enderror">{{ old('keterangan_bawah', $pengaturan['keterangan_bawah']) }}</textarea>
            @error('keterangan_bawah')<p class="sg-galat">{{ $message }}</p>@enderror
        </div>
        <div class="mb-3">
            <label class="sg-label" for="f-persen_dp">DP minimal (%)</label>
            <input type="number" id="f-persen_dp" name="persen_dp" min="0" max="100" class="form-control sg-angka @error('persen_dp') is-invalid @enderror"
                   value="{{ old('persen_dp', $pengaturan['persen_dp']) }}">
            <p class="sg-bantu">Dipakai untuk baris "DP minimal X%" di invoice yang belum dibayar. Isi 0 untuk menyembunyikan.</p>
            @error('persen_dp')<p class="sg-galat">{{ $message }}</p>@enderror
        </div>

        <div class="sg-form-aksi">
            <button type="submit" class="sg-btn sg-btn-utama" data-teks-kirim="Menyimpan…">Simpan pengaturan</button>
        </div>
    </form>

    @foreach ($labelGambar as $jenis => $label)
        @if ($gambar[$jenis])
            <form method="POST" action="{{ route('admin.pengaturan.invoice.hapus', $jenis) }}" class="d-inline"
                  onsubmit="return confirm('Hapus {{ mb_strtolower($label) }}? Invoice akan tampil tanpa {{ mb_strtolower($label) }}.');">
                @csrf @method('DELETE')
                <button type="submit" class="sg-btn sg-btn-sekunder">Hapus {{ mb_strtolower($label) }}</button>
            </form>
        @endif
    @endforeach
</div>
@endsection
