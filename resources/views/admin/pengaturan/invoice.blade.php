@extends('admin.layouts.pesanan')

@section('title', 'Pengaturan Invoice')
@section('kembali', route('admin.pesanan.index'))
@section('tanpa_bottomnav', '1')

{{--
    Pengaturan Invoice (rancangan invoice bagian 6). Kontrak form (PUT admin.pengaturan.invoice.update, multipart):
    penandatangan_nama, penandatangan_jabatan, rekening[i][bank|nomor|atas_nama] (maks $maksRekening),
    qris_keterangan, keterangan_bawah, persen_dp, ttd, stempel, qris (file opsional).
    Hapus gambar: DELETE admin.pengaturan.invoice.hapus {jenis} (form terpisah, tombolnya memakai atribut form=).
--}}

@section('content')
@php
    $rekening = array_values(array_filter((array) old('rekening', $pengaturan['rekening']), 'is_array'));
    $teksHapus = [
        'ttd'     => 'Invoice akan tampil tanpa tanda tangan.',
        'stempel' => 'Invoice akan tampil tanpa stempel.',
        'qris'    => 'Invoice akan tampil tanpa QRIS.',
    ];
@endphp

<div class="sg-kepala sg-lebar-720">
    <div>
        <h1>Pengaturan Invoice</h1>
        <p class="sg-kepala-sub">Dipakai di semua invoice PDF. File disimpan privat, tidak ikut repo.</p>
    </div>
    <div class="sg-contoh-pdf">
        <a href="{{ route('admin.pengaturan.invoice.contoh') }}" target="_blank" rel="noopener" class="sg-btn sg-btn-sekunder" aria-describedby="ket-contoh-pdf">
            <i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Lihat contoh PDF<span class="visually-hidden"> (tab baru)</span>
        </a>
        <p class="sg-bantu" id="ket-contoh-pdf">Simpan dulu untuk melihat perubahan.</p>
    </div>
</div>

{{-- Form hapus gambar (di luar form utama; tombolnya ada di komponen unggah lewat atribut form=) --}}
@foreach ($labelGambar as $jenis => $label)
    @if ($gambar[$jenis])
        <form id="hapus-{{ $jenis }}" method="POST" action="{{ route('admin.pengaturan.invoice.hapus', $jenis) }}" hidden
              data-kirim data-konfirmasi="Hapus {{ mb_strtolower($label) }}?" data-konfirmasi-isi="{{ $teksHapus[$jenis] }}"
              data-konfirmasi-tombol="Ya, hapus" data-konfirmasi-bahaya>
            @csrf @method('DELETE')
        </form>
    @endif
@endforeach

<form id="form-invoice" method="POST" action="{{ route('admin.pengaturan.invoice.update') }}" enctype="multipart/form-data"
      class="sg-lebar-720 sg-pengaturan-invoice" data-kirim data-jaga-perubahan>
    @csrf @method('PUT')

    {{-- ① Penanda tangan --}}
    <section class="sg-kartu" aria-labelledby="judul-ttd">
        <h2 class="sg-kartu-judul" id="judul-ttd"><span class="sg-no" aria-hidden="true">1</span> Penanda tangan</h2>
        <div class="sg-dua">
            <div class="sg-field">
                <label class="sg-label" for="f-penandatangan_nama">Nama penanda tangan</label>
                <input type="text" id="f-penandatangan_nama" name="penandatangan_nama" maxlength="100" autocomplete="off"
                       class="form-control @error('penandatangan_nama') is-invalid @enderror"
                       value="{{ old('penandatangan_nama', $pengaturan['penandatangan_nama']) }}"
                       @error('penandatangan_nama') aria-invalid="true" aria-describedby="g-penandatangan_nama" @enderror>
                @include('admin.layouts._galat', ['k' => 'penandatangan_nama'])
            </div>
            <div class="sg-field">
                <label class="sg-label" for="f-penandatangan_jabatan">Jabatan <span class="fw-normal sg-muted">(opsional)</span></label>
                <input type="text" id="f-penandatangan_jabatan" name="penandatangan_jabatan" maxlength="100" autocomplete="off" placeholder="Mis. Pemilik"
                       class="form-control @error('penandatangan_jabatan') is-invalid @enderror"
                       value="{{ old('penandatangan_jabatan', $pengaturan['penandatangan_jabatan']) }}"
                       aria-describedby="bantu-jabatan{{ $errors->has('penandatangan_jabatan') ? ' g-penandatangan_jabatan' : '' }}" @error('penandatangan_jabatan') aria-invalid="true" @enderror>
                @include('admin.layouts._galat', ['k' => 'penandatangan_jabatan'])
                <p class="sg-bantu" id="bantu-jabatan">Kosongkan bila tidak ingin ditampilkan.</p>
            </div>
        </div>
        @include('admin.pengaturan._gambar-invoice', [
            'jenis' => 'ttd', 'label' => $labelGambar['ttd'], 'src' => $gambar['ttd'], 'rasio' => '5 / 2', 'maks' => '240px',
            'bantu' => 'PNG transparan, maks 2 MB.', 'peringatanJpg' => true,
        ])
        @include('admin.pengaturan._gambar-invoice', [
            'jenis' => 'stempel', 'label' => $labelGambar['stempel'], 'src' => $gambar['stempel'], 'rasio' => '5 / 2', 'maks' => '240px',
            'bantu' => 'PNG transparan, maks 2 MB. Tanda tangan ditumpuk di atas stempel.', 'peringatanJpg' => true,
        ])
    </section>

    {{-- ② Rekening bank --}}
    <section class="sg-kartu" aria-labelledby="judul-rekening" id="f-rekening">
        <h2 class="sg-kartu-judul" id="judul-rekening"><span class="sg-no" aria-hidden="true">2</span> Rekening bank</h2>
        @include('admin.layouts._galat', ['k' => 'rekening'])
        <div class="sg-rekening" data-rekening data-maks="{{ $maksRekening }}">
            <div class="sg-rek-kepala" aria-hidden="true" @if (! $rekening) hidden @endif data-rek-kepala>
                <span>Bank</span><span>Nomor rekening</span><span>Atas nama</span><span></span>
            </div>
            <div data-rek-daftar>
                @foreach ($rekening as $i => $r)
                    @include('admin.pengaturan._rekening', ['i' => $i, 'r' => $r])
                @endforeach
            </div>
            <p class="sg-bantu sg-rek-kosong" data-rek-kosong @if ($rekening) hidden @endif>Belum ada rekening. Blok transfer bank tidak ditampilkan di invoice.</p>
            <template id="template-rekening">
                @include('admin.pengaturan._rekening', ['i' => '__I__', 'r' => []])
            </template>
            <button type="button" class="sg-tambah-item mt-2" data-rek-tambah @if (count($rekening) >= $maksRekening) hidden @endif>
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Tambah rekening
            </button>
            <p class="sg-bantu" data-rek-info aria-live="polite">Maksimal {{ $maksRekening }} rekening. Nomor rekening: tulis persis seperti ingin tampil di invoice, boleh dengan spasi (contoh: 3008 0101 7388 537).</p>
        </div>
        <datalist id="daftar-bank"><option value="BCA"><option value="BRI"><option value="Mandiri"><option value="BNI"></datalist>
    </section>

    {{-- ③ QRIS --}}
    <section class="sg-kartu" aria-labelledby="judul-qris">
        <h2 class="sg-kartu-judul" id="judul-qris"><span class="sg-no" aria-hidden="true">3</span> QRIS</h2>
        @include('admin.pengaturan._gambar-invoice', [
            'jenis' => 'qris', 'label' => 'Gambar QR', 'src' => $gambar['qris'], 'rasio' => '1 / 1', 'maks' => '160px',
            'bantu' => 'Unggah area QR saja (persegi), minimal 300 px. Maks 2 MB.', 'minSisi' => 300, 'labelGalat' => 'QRIS',
        ])
        <div class="sg-field">
            <label class="sg-label" for="f-qris_keterangan">Keterangan QRIS</label>
            <input type="text" id="f-qris_keterangan" name="qris_keterangan" maxlength="200" autocomplete="off"
                   class="form-control @error('qris_keterangan') is-invalid @enderror"
                   value="{{ old('qris_keterangan', $pengaturan['qris_keterangan']) }}"
                   @error('qris_keterangan') aria-invalid="true" aria-describedby="g-qris_keterangan" @enderror>
            @include('admin.layouts._galat', ['k' => 'qris_keterangan'])
        </div>
        <div class="sg-alert sg-alert-info">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <p>QRIS ini statis: customer mengetik nominal sendiri. Invoice otomatis menambahkan pengingat "Ketik nominal sesuai sisa tagihan".</p>
        </div>
    </section>

    {{-- ④ Keterangan bawah --}}
    <section class="sg-kartu" aria-labelledby="judul-keterangan">
        <h2 class="sg-kartu-judul" id="judul-keterangan"><span class="sg-no" aria-hidden="true">4</span> Keterangan bawah invoice</h2>
        <div class="sg-field">
            <label class="sg-label" for="f-keterangan_bawah">Keterangan</label>
            <textarea id="f-keterangan_bawah" name="keterangan_bawah" rows="3" maxlength="500"
                      class="form-control @error('keterangan_bawah') is-invalid @enderror"
                      aria-describedby="bantu-keterangan{{ $errors->has('keterangan_bawah') ? ' g-keterangan_bawah' : '' }}" @error('keterangan_bawah') aria-invalid="true" @enderror>{{ old('keterangan_bawah', $pengaturan['keterangan_bawah']) }}</textarea>
            @include('admin.layouts._galat', ['k' => 'keterangan_bawah'])
            <p class="sg-bantu" id="bantu-keterangan">Maksimal 500 karakter. Baris baru ikut tampil di invoice.</p>
        </div>
        <div class="sg-field">
            <label class="sg-label" for="f-persen_dp">DP minimal</label>
            <div class="sg-imbuh sg-imbuh-belakang sg-persen-dp">
                <input type="text" inputmode="numeric" id="f-persen_dp" name="persen_dp" maxlength="3" autocomplete="off"
                       class="form-control sg-angka @error('persen_dp') is-invalid @enderror"
                       value="{{ old('persen_dp', $pengaturan['persen_dp']) }}"
                       aria-describedby="bantu-persen_dp{{ $errors->has('persen_dp') ? ' g-persen_dp' : '' }}" @error('persen_dp') aria-invalid="true" @enderror>
                <span class="sg-imbuh-teks" aria-hidden="true">%</span>
            </div>
            @include('admin.layouts._galat', ['k' => 'persen_dp'])
            <p class="sg-bantu" id="bantu-persen_dp">Untuk baris "DP minimal X%" di invoice yang belum dibayar. Isi 0 untuk menyembunyikan.</p>
        </div>
    </section>

    {{-- Desktop: di bawah form. HP & tablet: bilah sticky. --}}
    <div class="sg-form-aksi sg-rata-kanan d-none d-lg-flex">
        <a href="{{ route('admin.pesanan.index') }}" class="sg-btn sg-btn-sekunder" data-batal-form>Batal</a>
        <button type="submit" class="sg-btn sg-btn-utama" data-teks-kirim="Menyimpan…"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan pengaturan</button>
    </div>
    <div class="sg-bilah d-lg-none">
        <button type="submit" class="sg-btn sg-btn-utama flex-grow-1" data-teks-kirim="Menyimpan…"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan pengaturan</button>
    </div>
</form>
@endsection
