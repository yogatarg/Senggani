{{--
    Isi form order (tambah & edit), dibungkus <form> di create/edit.
    Variabel: $pesanan, $masterPackaging, $masterBahan, $masterFinishing, $masterCetak (array nama),
    $statusDesain (nilai => label), $lainnya (nilai opsi "Lainnya"), $labelSimpan, $urlBatal.
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

    // Bantuan error per field (id pesan: g-<kunci dengan titik → strip>)
    $gid = fn (string $kunci) => 'g-' . str_replace('.', '-', $kunci);
    $aria = fn (string $kunci) => $errors->has($kunci) ? 'aria-invalid="true" aria-describedby="' . $gid($kunci) . '"' : '';
    $galat = fn (string $kunci) => $errors->has($kunci)
        ? '<p class="sg-galat" id="' . $gid($kunci) . '"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>' . e($errors->first($kunci)) . '</p>'
        : '';

    // Nomor WA tersimpan 62xxx → tampil 08xxx (server menormalisasi lagi saat simpan)
    $waLama = old('nomor_whatsapp', $pesanan->nomor_whatsapp);
    if (! old('nomor_whatsapp') && is_string($waLama) && str_starts_with($waLama, '62')) { $waLama = '0' . substr($waLama, 2); }

    $dpTampil = old('dp', $pesanan->exists ? (int) $pesanan->dp : '');
    if (is_numeric($dpTampil)) { $dpTampil = (int) $dpTampil === 0 && ! $pesanan->exists ? '' : number_format((int) $dpTampil, 0, ',', '.'); }

    $ikonDesain = ['belum_dikirim' => 'fa-circle-minus', 'menunggu' => 'fa-hourglass-half', 'revisi' => 'fa-pen', 'disetujui' => 'fa-circle-check'];
    $fileLama = $pesanan->exists ? $pesanan->file : collect();
    $maksFile = \App\Models\PesananFile::MAKS_PER_ORDER;
@endphp

<div class="sg-form-tata" data-form-order data-maks-file="{{ $maksFile }}" data-maks-mb="{{ \App\Models\PesananFile::MAKS_KB / 1024 }}">
    <div class="sg-form-utama">

        {{-- ① Customer --}}
        <section class="sg-kartu" id="bagian-customer" aria-labelledby="judul-customer">
            <h2 class="sg-kartu-judul" id="judul-customer"><span class="sg-no" aria-hidden="true">1</span> Customer</h2>
            <div class="sg-dua" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr))">
                <div class="sg-field">
                    <label class="sg-label" for="f-nama_customer">Nama customer <span class="sg-wajib" aria-hidden="true">*</span></label>
                    <input type="text" id="f-nama_customer" name="nama_customer" value="{{ old('nama_customer', $pesanan->nama_customer) }}" maxlength="150" required autocomplete="off"
                           class="form-control @error('nama_customer') is-invalid @enderror" {!! $aria('nama_customer') !!}>
                    {!! $galat('nama_customer') !!}
                </div>
                <div class="sg-field">
                    <label class="sg-label" for="f-nomor_whatsapp">No. WhatsApp <span class="sg-wajib" aria-hidden="true">*</span></label>
                    <input type="tel" inputmode="tel" id="f-nomor_whatsapp" name="nomor_whatsapp" value="{{ $waLama }}" maxlength="30" required autocomplete="off"
                           placeholder="0812 3456 7890" class="form-control sg-angka @error('nomor_whatsapp') is-invalid @enderror" {!! $aria('nomor_whatsapp') !!}>
                    {!! $galat('nomor_whatsapp') !!}
                </div>
            </div>
            <div class="sg-field">
                <label class="sg-label" for="f-alamat">Alamat lengkap <span class="sg-wajib" aria-hidden="true">*</span></label>
                <textarea id="f-alamat" name="alamat" rows="2" required maxlength="2000" style="min-height:72px"
                          class="form-control @error('alamat') is-invalid @enderror" {!! $aria('alamat') !!}>{{ old('alamat', $pesanan->alamat) }}</textarea>
                {!! $galat('alamat') !!}
            </div>
            <div class="sg-field">
                <label class="sg-label" for="f-kota">Kota <span class="sg-wajib" aria-hidden="true">*</span></label>
                <input type="text" id="f-kota" name="kota" value="{{ old('kota', $pesanan->kota) }}" maxlength="100" required
                       class="form-control @error('kota') is-invalid @enderror" {!! $aria('kota') !!}>
                {!! $galat('kota') !!}
            </div>
        </section>

        {{-- ② Item --}}
        <section class="sg-kartu" id="bagian-item" aria-labelledby="judul-bagian-item">
            <h2 class="sg-kartu-judul" id="judul-bagian-item"><span class="sg-no" aria-hidden="true">2</span> Item pesanan</h2>
            <div id="f-item">{!! $galat('item') !!}</div>
            <div id="daftar-item" data-daftar-item>
                @foreach (array_values($daftarItem) as $i => $it)
                    @include('admin.pesanan._item', ['i' => $i, 'it' => is_array($it) ? $it : []])
                @endforeach
            </div>
            <template id="template-item">
                @include('admin.pesanan._item', ['i' => '__I__', 'it' => []])
            </template>
            <button type="button" class="sg-tambah-item" data-tambah-item><i class="fa-solid fa-plus" aria-hidden="true"></i> Tambah item</button>
        </section>

        {{-- ③ Desain --}}
        <section class="sg-kartu" id="bagian-desain" aria-labelledby="judul-desain">
            <h2 class="sg-kartu-judul" id="judul-desain"><span class="sg-no" aria-hidden="true">3</span> Desain</h2>

            <div class="sg-field">
                <label class="sg-label" for="f-link_desain">Link Google Drive</label>
                <div class="sg-imbuh sg-imbuh-depan">
                    <span class="sg-imbuh-teks" aria-hidden="true"><i class="fa-brands fa-google-drive"></i></span>
                    <input type="url" id="f-link_desain" name="link_desain" value="{{ old('link_desain', $pesanan->link_desain) }}" maxlength="500" inputmode="url"
                           placeholder="https://drive.google.com/…" class="form-control @error('link_desain') is-invalid @enderror" data-link-drive
                           aria-describedby="bantu-link {{ $errors->has('link_desain') ? $gid('link_desain') : '' }}" @error('link_desain') aria-invalid="true" @enderror>
                </div>
                {!! $galat('link_desain') !!}
                <p class="sg-bantu" id="bantu-link" data-bantu-link>Harus link drive.google.com atau docs.google.com.</p>
            </div>

            @if ($errors->any())
                <div class="sg-alert sg-alert-peringatan" role="status">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <p><strong>Pilih ulang gambar mockup/referensi.</strong> Gambar yang tadi dipilih belum terunggah karena ada isian yang perlu diperbaiki.</p>
                </div>
            @endif

            <p class="sg-bantu mt-0 mb-3" data-hitung-file aria-live="polite">Maksimal {{ $maksFile }} gambar per order (JPG, PNG, atau WebP, maks 5 MB per gambar).</p>

            @if ($fileLama->isNotEmpty())
                <div class="sg-field">
                    <h3 class="sg-label">Gambar tersimpan</h3>
                    <ul class="sg-pratinjau mt-0" data-file-lama-daftar>
                        @foreach ($fileLama as $f)
                            <li data-file-lama>
                                <img src="{{ route('admin.pesanan.file.lihat', [$pesanan, $f]) }}" alt="{{ $f->jenis->label() }}: {{ $f->nama_asli }}" loading="lazy">
                                <span class="sg-pratinjau-label">{{ $f->jenis->label() }}</span>
                                @can('hapusFile', $pesanan)
                                    <button type="button" data-hapus-file-lama="{{ route('admin.pesanan.file.hapus', [$pesanan, $f]) }}" aria-label="Hapus gambar {{ $f->nama_asli }}">
                                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                    </button>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @foreach (['mockup' => 'Mockup', 'referensi' => 'Referensi'] as $jenis => $labelJenis)
                <div class="sg-field" id="f-{{ $jenis }}">
                    <span class="sg-label" id="label-{{ $jenis }}">{{ $labelJenis }}</span>
                    <label class="sg-dropzone" data-dropzone>
                        <input type="file" name="{{ $jenis }}[]" multiple accept="image/jpeg,image/png,image/webp" data-unggah="{{ $jenis }}"
                               aria-labelledby="label-{{ $jenis }} teks-unggah-{{ $jenis }}" @if ($errors->has($jenis) || $errors->has($jenis . '.*')) aria-invalid="true" @endif>
                        <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                        <strong id="teks-unggah-{{ $jenis }}"><span class="d-md-none">Pilih dari galeri atau kamera</span><span class="d-none d-md-inline">Pilih gambar atau seret ke sini</span></strong>
                        <span class="sg-kecil sg-muted">JPG, PNG, WebP · maks 5 MB per gambar</span>
                    </label>
                    @foreach ($errors->getMessages() as $kunci => $pesanGalat)
                        @if ($kunci === $jenis || str_starts_with($kunci, $jenis . '.'))
                            <p class="sg-galat"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $pesanGalat[0] }}</p>
                        @endif
                    @endforeach
                    <ul class="sg-tolak" data-tolak="{{ $jenis }}" aria-live="polite"></ul>
                    <ul class="sg-pratinjau" data-pratinjau="{{ $jenis }}"></ul>
                </div>
            @endforeach

            <div class="sg-field">
                <label class="sg-label" for="f-catatan_desain">Catatan desain</label>
                <textarea id="f-catatan_desain" name="catatan_desain" rows="3" maxlength="5000"
                          class="form-control @error('catatan_desain') is-invalid @enderror" {!! $aria('catatan_desain') !!}>{{ old('catatan_desain', $pesanan->catatan_desain) }}</textarea>
                {!! $galat('catatan_desain') !!}
            </div>

            <fieldset class="sg-field" id="f-status_desain">
                <legend class="sg-label">Status approval desain</legend>
                <div class="sg-segmen">
                    @foreach ($statusDesain as $nilai => $label)
                        <label class="sg-sd-{{ $nilai }}">
                            <input type="radio" name="status_desain" value="{{ $nilai }}" @checked(old('status_desain', $pesanan->status_desain?->value) === $nilai)>
                            <span><i class="fa-solid {{ $ikonDesain[$nilai] ?? 'fa-circle' }}" aria-hidden="true"></i>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                {!! $galat('status_desain') !!}
            </fieldset>
        </section>

        {{-- ④ Order & harga --}}
        <section class="sg-kartu" id="bagian-harga" aria-labelledby="judul-harga">
            <h2 class="sg-kartu-judul" id="judul-harga"><span class="sg-no" aria-hidden="true">4</span> Order &amp; harga</h2>
            <div class="sg-dua">
                <div class="sg-field">
                    <label class="sg-label" for="f-tanggal_order">Tanggal order <span class="sg-wajib" aria-hidden="true">*</span></label>
                    <input type="date" id="f-tanggal_order" name="tanggal_order" value="{{ old('tanggal_order', $tgl($pesanan->tanggal_order)) }}" required
                           class="form-control @error('tanggal_order') is-invalid @enderror" data-tanggal-order {!! $aria('tanggal_order') !!}>
                    {!! $galat('tanggal_order') !!}
                </div>
                <div class="sg-field">
                    <label class="sg-label" for="f-deadline">Deadline <span class="sg-wajib" aria-hidden="true">*</span></label>
                    <input type="date" id="f-deadline" name="deadline" value="{{ old('deadline', $tgl($pesanan->deadline)) }}" required
                           min="{{ old('tanggal_order', $tgl($pesanan->tanggal_order)) }}"
                           class="form-control @error('deadline') is-invalid @enderror" data-deadline {!! $aria('deadline') !!}>
                    {!! $galat('deadline') !!}
                </div>
            </div>

            <div class="sg-otomatis"><span>Total <span class="sg-kecil sg-muted">(otomatis)</span></span><strong data-total>Rp 0</strong></div>

            <div class="sg-dua mt-2">
                <div class="sg-field">
                    <label class="sg-label" for="f-dp">DP / sudah dibayar</label>
                    <div class="sg-imbuh sg-imbuh-depan">
                        <span class="sg-imbuh-teks" aria-hidden="true">Rp</span>
                        <input type="text" inputmode="numeric" id="f-dp" name="dp" value="{{ $dpTampil }}" autocomplete="off" placeholder="0"
                               class="form-control sg-angka @error('dp') is-invalid @enderror" data-rupiah data-dp
                               aria-describedby="peringatan-dp {{ $errors->has('dp') ? $gid('dp') : '' }}" @error('dp') aria-invalid="true" @enderror>
                    </div>
                    {!! $galat('dp') !!}
                    <p class="sg-galat" id="peringatan-dp" data-peringatan-dp hidden><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span></span></p>
                </div>
                <div class="sg-field">
                    <label class="sg-label" for="f-tanggal_lunas">Tanggal lunas</label>
                    <input type="date" id="f-tanggal_lunas" name="tanggal_lunas" value="{{ old('tanggal_lunas', $tgl($pesanan->tanggal_lunas)) }}" max="{{ today()->toDateString() }}"
                           class="form-control @error('tanggal_lunas') is-invalid @enderror" data-tanggal-lunas
                           aria-describedby="bantu-lunas {{ $errors->has('tanggal_lunas') ? $gid('tanggal_lunas') : '' }}" @error('tanggal_lunas') aria-invalid="true" @enderror>
                    {!! $galat('tanggal_lunas') !!}
                    <p class="sg-bantu" id="bantu-lunas">Isi bila customer sudah melunasi.</p>
                </div>
            </div>

            <div class="sg-otomatis"><span>Sisa <span class="sg-kecil sg-muted">(otomatis)</span></span><strong data-sisa>Rp 0</strong></div>
            <p class="mb-3">@include('admin.pesanan._badge-bayar-live')</p>

            <div class="sg-field">
                <label class="sg-label" for="f-catatan_admin">Catatan order</label>
                <textarea id="f-catatan_admin" name="catatan_admin" rows="3" maxlength="5000"
                          class="form-control @error('catatan_admin') is-invalid @enderror" {!! $aria('catatan_admin') !!}>{{ old('catatan_admin', $pesanan->catatan_admin) }}</textarea>
                {!! $galat('catatan_admin') !!}
            </div>
        </section>
    </div>

    {{-- Kolom samping (≥1200) --}}
    <aside class="sg-form-samping" aria-label="Ringkasan order">
        <div class="sg-kartu sg-ringkas">
            <h2 class="sg-kartu-judul">Ringkasan</h2>
            <dl>
                <div><dt>Item</dt><dd data-jumlah-item>1</dd></div>
                <div><dt>Total</dt><dd data-total>Rp 0</dd></div>
                <div><dt>DP</dt><dd data-dp-tampil>Rp 0</dd></div>
                <div class="sg-sisa"><dt>Sisa</dt><dd data-sisa>Rp 0</dd></div>
            </dl>
            <p class="mt-2">@include('admin.pesanan._badge-bayar-live')</p>
            <hr class="sg-pemisah my-3">
            <nav aria-label="Lompat ke bagian">
                <ol class="sg-lompat">
                    <li><a href="#bagian-customer">1. Customer</a></li>
                    <li><a href="#bagian-item">2. Item (<span data-jumlah-item>1</span>)</a></li>
                    <li><a href="#bagian-desain">3. Desain</a></li>
                    <li><a href="#bagian-harga">4. Order &amp; harga</a></li>
                </ol>
            </nav>
            <hr class="sg-pemisah my-3">
            <div class="d-grid gap-2">
                <button type="submit" class="sg-btn sg-btn-utama" data-teks-kirim="Menyimpan…">{{ $labelSimpan }}</button>
                <a href="{{ $urlBatal }}" class="sg-btn sg-btn-sekunder" data-batal-form>Batal</a>
            </div>
        </div>
    </aside>
</div>

{{-- Bilah sticky (< 1200) --}}
<div class="sg-bilah sg-bilah-form">
    <div class="sg-bilah-ringkas sg-angka">
        <strong>Total <span data-total>Rp 0</span></strong>
        <span>Sisa <span data-sisa>Rp 0</span></span>
    </div>
    <a href="{{ $urlBatal }}" class="sg-btn sg-btn-sekunder d-none d-sm-inline-flex" data-batal-form>Batal</a>
    <button type="submit" class="sg-btn sg-btn-utama" data-teks-kirim="Menyimpan…">Simpan</button>
</div>
