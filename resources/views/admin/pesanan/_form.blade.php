{{--
    Form order SEMENTARA (HTML dasar). Variabel: $pesanan, $masterPackaging, $masterBahan,
    $masterFinishing, $masterCetak (array nama), $statusDesain (nilai => label), $lainnya (nilai opsi "Lainnya").
--}}
@php
    // Ubah item tersimpan ke bentuk input form: nilai di luar master menjadi "Lainnya" + teks bebas
    $keInput = function ($d) use ($masterPackaging, $masterBahan, $masterCetak, $masterFinishing, $lainnya) {
        $pilih = function ($nilai, $master) use ($lainnya) {
            if ($nilai === null || $nilai === '') return [null, null];
            return in_array($nilai, $master, true) ? [$nilai, null] : [$lainnya, $nilai];
        };
        [$pk, $pkL] = $pilih($d->jenis_packaging, $masterPackaging);
        [$bh, $bhL] = $pilih($d->jenis_bahan, $masterBahan);
        [$ct, $ctL] = $pilih($d->jenis_cetak, $masterCetak);
        $fin = $d->daftar_finishing;
        return [
            'jenis_packaging' => $pk, 'jenis_packaging_lainnya' => $pkL,
            'panjang' => $d->panjang, 'lebar' => $d->lebar, 'tinggi' => $d->tinggi,
            'jenis_bahan' => $bh, 'jenis_bahan_lainnya' => $bhL, 'gramasi' => $d->gramasi,
            'jenis_cetak' => $ct, 'jenis_cetak_lainnya' => $ctL, 'warna_cetak' => $d->warna_cetak,
            'finishing' => array_values(array_intersect($fin, $masterFinishing)),
            'finishing_lainnya' => implode(', ', array_diff($fin, $masterFinishing)),
            'aksesori' => $d->aksesori, 'jumlah' => $d->jumlah, 'harga_satuan' => (int) $d->harga_satuan,
        ];
    };
    $daftarItem = old('item', $pesanan->exists ? $pesanan->detailPesanan->map($keInput)->all() : [[]]);
    if (! $daftarItem) $daftarItem = [[]];
    $tgl = fn ($v) => $v instanceof \Carbon\CarbonInterface ? $v->toDateString() : $v;
@endphp

<fieldset>
    <legend>1. Customer</legend>
    <p><label>Nama customer* <input type="text" name="nama_customer" value="{{ old('nama_customer', $pesanan->nama_customer) }}" maxlength="150" required></label></p>
    <p><label>No. WhatsApp* <input type="tel" inputmode="tel" name="nomor_whatsapp" value="{{ old('nomor_whatsapp', $pesanan->nomor_whatsapp) }}" placeholder="0812 3456 7890" required></label></p>
    <p><label>Alamat lengkap* <textarea name="alamat" rows="2" required>{{ old('alamat', $pesanan->alamat) }}</textarea></label></p>
    <p><label>Kota* <input type="text" name="kota" value="{{ old('kota', $pesanan->kota) }}" maxlength="100" required></label></p>
</fieldset>

<fieldset>
    <legend>2. Item pesanan</legend>
    <div id="daftar-item">
        @foreach (array_values($daftarItem) as $i => $it)
            @include('admin.pesanan._item', ['i' => $i, 'it' => $it])
        @endforeach
    </div>
    <template id="template-item">
        @include('admin.pesanan._item', ['i' => '__I__', 'it' => []])
    </template>
    <button type="button" id="tambah-item">+ Tambah item</button>
</fieldset>

<fieldset>
    <legend>3. Desain</legend>
    <p><label>Link Google Drive <input type="url" name="link_desain" value="{{ old('link_desain', $pesanan->link_desain) }}" placeholder="https://drive.google.com/..." maxlength="500"></label></p>
    <p><label>Mockup (JPG/PNG/WebP, maks 5 MB) <input type="file" name="mockup[]" multiple accept="image/jpeg,image/png,image/webp"></label></p>
    <p><label>Referensi <input type="file" name="referensi[]" multiple accept="image/jpeg,image/png,image/webp"></label></p>
    <p>Maksimal {{ \App\Models\PesananFile::MAKS_PER_ORDER }} gambar per order.</p>
    @if ($pesanan->exists && $pesanan->file->isNotEmpty())
        <p>Gambar tersimpan ({{ $pesanan->file->count() }}):</p>
        <ul>
            @foreach ($pesanan->file as $f)
                <li><a href="{{ route('admin.pesanan.file.lihat', [$pesanan, $f]) }}" target="_blank">{{ $f->nama_asli }}</a> ({{ $f->jenis->label() }})</li>
            @endforeach
        </ul>
        <p>Hapus gambar dari halaman detail order.</p>
    @endif
    <p><label>Catatan desain <textarea name="catatan_desain" rows="3">{{ old('catatan_desain', $pesanan->catatan_desain) }}</textarea></label></p>
    <p>Status approval:
        @foreach ($statusDesain as $nilai => $label)
            <label><input type="radio" name="status_desain" value="{{ $nilai }}" @checked(old('status_desain', $pesanan->status_desain?->value) === $nilai)> {{ $label }}</label>
        @endforeach
    </p>
</fieldset>

<fieldset>
    <legend>4. Order &amp; harga</legend>
    <p><label>Tanggal order* <input type="date" name="tanggal_order" value="{{ old('tanggal_order', $tgl($pesanan->tanggal_order)) }}" required></label></p>
    <p><label>Deadline* <input type="date" name="deadline" value="{{ old('deadline', $tgl($pesanan->deadline)) }}" required></label></p>
    @if ($pesanan->exists)
        <p>Total tersimpan: Rp {{ number_format((float) $pesanan->total, 0, ',', '.') }} (dihitung ulang otomatis dari item saat disimpan)</p>
    @endif
    <p><label>DP (Rp) <input type="text" inputmode="numeric" name="dp" value="{{ old('dp', $pesanan->exists ? (int) $pesanan->dp : '') }}"></label></p>
    <p><label>Tanggal lunas <input type="date" name="tanggal_lunas" value="{{ old('tanggal_lunas', $tgl($pesanan->tanggal_lunas)) }}"></label> (isi bila sudah lunas)</p>
    <p><label>Catatan order <textarea name="catatan_admin" rows="3">{{ old('catatan_admin', $pesanan->catatan_admin) }}</textarea></label></p>
</fieldset>

<script>
    (function () {
        var wadah = document.getElementById('daftar-item');
        var urut = wadah.children.length;
        document.getElementById('tambah-item').addEventListener('click', function () {
            var html = document.getElementById('template-item').innerHTML.replace(/__I__/g, urut++);
            wadah.insertAdjacentHTML('beforeend', html);
        });
        wadah.addEventListener('click', function (e) {
            if (e.target.matches('[data-hapus-item]') && wadah.children.length > 1) {
                e.target.closest('[data-item]').remove();
            }
        });
    })();
</script>
