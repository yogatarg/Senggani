{{--
    Isi form SPP (tambah & edit), dibungkus <form> di create/edit.
    Variabel: $pesanan, $masterPackaging, $masterBahan, $masterFinishing, $masterCetak (array nama),
    $statusDesain (nilai => label), $lainnya (nilai opsi "Lainnya"), $labelSimpan, $urlBatal,
    $saranNomor (int), $jenisDeadline (nilai => label), $urlCariCustomer (endpoint JSON autocomplete).

    Alur baru: tambah SPP TANPA nomor, DP, dan (boleh) tanpa harga. Nomor SPP diberikan saat "Catat DP".
    - Nomor SPP tampil & wajib hanya saat edit SPP yang sudah bernomor (ganti nomor → konfirmasi_ganti_nomor=1).
    - DP & tanggal lunas hanya saat edit SPP yang sudah masuk produksi.
    - Harga satuan wajib hanya bila status sudah melewati Hitung Harga.
--}}
@php
    // Ubah item tersimpan ke bentuk input form: nilai di luar master menjadi "Lainnya" + teks bebas
    $keInput = function ($d) use ($masterPackaging, $masterBahan, $masterCetak, $masterFinishing, $lainnya) {
        $pilih = function ($nilai, $master) use ($lainnya) {
            if ($nilai === null || $nilai === '') return [null, null];
            return in_array($nilai, $master, true) ? [$nilai, null] : [$lainnya, $nilai];
        };
        [$pk, $pkL] = $pilih($d->jenis_packaging, $masterPackaging);
        // Data lama: gramasi/warna cetak (kolom tidak diisi lagi) digabung ke bahan/jenis cetak agar tidak hilang saat disimpan ulang.
        $gabung = fn ($a, $b) => trim(trim((string) $a) . ' ' . trim((string) $b));
        [$bh, $bhL] = $pilih($gabung($d->jenis_bahan, $d->gramasi), $masterBahan);
        [$ct, $ctL] = $pilih($gabung($d->jenis_cetak, $d->warna_cetak), $masterCetak);
        $fin = $d->daftar_finishing;
        return [
            'jenis_packaging' => $pk, 'jenis_packaging_lainnya' => $pkL,
            'panjang' => $d->panjang, 'lebar' => $d->lebar, 'tinggi' => $d->tinggi,
            'jenis_bahan' => $bh, 'jenis_bahan_lainnya' => $bhL,
            'jenis_cetak' => $ct, 'jenis_cetak_lainnya' => $ctL,
            'finishing' => array_values(array_intersect($fin, $masterFinishing)),
            'finishing_lainnya' => implode(', ', array_diff($fin, $masterFinishing)),
            'aksesori' => $d->aksesori, 'catatan' => $d->catatan, 'jumlah' => $d->jumlah,
            'harga_satuan' => $d->harga_satuan === null ? null : (int) $d->harga_satuan,
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
    // Sama seperti harga: "250.000" dari old() bukan 250
    if (is_int($dpTampil) || (is_string($dpTampil) && ctype_digit($dpTampil))) { $dpTampil = (int) $dpTampil === 0 && ! $pesanan->exists ? '' : number_format((int) $dpTampil, 0, ',', '.'); }

    $ikonDesain = ['belum_dikirim' => 'fa-circle-minus', 'menunggu' => 'fa-hourglass-half', 'revisi' => 'fa-pen', 'disetujui' => 'fa-circle-check'];
    $fileLama = $pesanan->exists ? $pesanan->file : collect();
    // Edit: lepas_customer=1 → customer ditentukan ulang dari isian (customer baru atau pemilik nomor WA)
    $lepasCustomer = $pesanan->exists && old('lepas_customer') === '1';
    $customerTerpilih = $lepasCustomer ? null : old('customer_id', $pesanan->customer_id);
    // Data chip customer terpilih (edit, atau setelah gagal validasi)
    $customerAwal = $customerTerpilih ? \App\Models\Customer::withCount('pesanan')->find($customerTerpilih) : null;
    if (! $customerAwal) { $customerTerpilih = null; }
    $statusSpp = $pesanan->exists ? $pesanan->status_produksi : null;
    // Lihat SimpanPesananRequest: pakaiNomor, pakaiPembayaran, hargaWajib
    $pakaiNomor = $pesanan->exists && (bool) $pesanan->nomor_spp;
    $pakaiBayar = (bool) $statusSpp?->sudahProduksi();
    // Kunci pelunasan: SPP dikirim/selesai wajib tetap tercatat lunas
    $lunasWajib = in_array($statusSpp, [\App\Enums\StatusProduksi::Pengiriman, \App\Enums\StatusProduksi::Selesai], true);
    $hargaWajib = $statusSpp !== null && ! in_array($statusSpp, [\App\Enums\StatusProduksi::HitungHarga, \App\Enums\StatusProduksi::TidakJadi], true);
    // Edit SPP bernomor: konfirmasi_ganti_nomor (nomor diganti)
    $kunciKonfirmasi = 'konfirmasi_ganti_nomor';
    $tampilKonfirmasi = $errors->has($kunciKonfirmasi) || old($kunciKonfirmasi);
    $maksFile = \App\Models\PesananFile::MAKS_PER_ORDER;
@endphp

<div class="sg-form-tata" data-form-order data-maks-file="{{ $maksFile }}" data-maks-mb="{{ \App\Models\PesananFile::MAKS_KB / 1024 }}">
    <div class="sg-form-utama">

        {{-- ① SPP & customer --}}
        <section class="sg-kartu" id="bagian-customer" aria-labelledby="judul-customer">
            <h2 class="sg-kartu-judul" id="judul-customer"><span class="sg-no" aria-hidden="true">1</span> SPP &amp; customer</h2>

            {{-- Nomor SPP: hanya untuk SPP yang sudah bernomor (masuk produksi) --}}
            @if ($pakaiNomor)
            <div class="sg-field sg-nomor-spp">
                <label class="sg-label" for="f-nomor_spp">Nomor SPP <span class="sg-wajib" aria-hidden="true">*</span></label>
                <div class="sg-imbuh sg-imbuh-depan sg-nomor-spp-isian">
                    <span class="sg-imbuh-teks" aria-hidden="true">#</span>
                    <input type="text" inputmode="numeric" id="f-nomor_spp" name="nomor_spp" value="{{ old('nomor_spp', $pesanan->nomor_spp) }}" maxlength="9" required autocomplete="off"
                           class="form-control sg-angka @error('nomor_spp') is-invalid @enderror" data-nomor-spp data-saran-nomor="{{ $saranNomor }}" data-nomor-lama="{{ $pesanan->exists ? $pesanan->nomor_spp : '' }}"
                           aria-describedby="bantu-nomor-spp {{ $errors->has('nomor_spp') ? $gid('nomor_spp') : '' }}" @error('nomor_spp') aria-invalid="true" @enderror>
                </div>
                {!! $galat('nomor_spp') !!}
                <p class="sg-bantu" id="bantu-nomor-spp">
                    Nomor saat ini <strong class="sg-angka">#{{ $pesanan->nomor_spp }}</strong>. Ganti hanya bila salah input.
                </p>

                {{-- Kotak konfirmasi nomor: tampil dari error server, atau dari cek browser sebelum kirim (server tetap penentu) --}}
                <div class="sg-alert sg-alert-peringatan sg-konfirmasi-nomor" data-konfirmasi-nomor="{{ $kunciKonfirmasi }}"
                     @unless ($tampilKonfirmasi) hidden @endunless>
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <div>
                        <p class="mb-2" id="pesan-{{ $kunciKonfirmasi }}" data-pesan-konfirmasi>{{ $errors->first($kunciKonfirmasi) }}</p>
                        <label class="sg-centang" for="f-{{ $kunciKonfirmasi }}">
                            <input type="checkbox" id="f-{{ $kunciKonfirmasi }}" name="{{ $kunciKonfirmasi }}" value="1" class="form-check-input" @checked(old($kunciKonfirmasi))
                                   aria-describedby="pesan-{{ $kunciKonfirmasi }}" @error($kunciKonfirmasi) aria-invalid="true" @enderror>
                            <span>Ya, saya yakin pakai nomor ini</span>
                        </label>
                    </div>
                </div>
            </div>
            @else
            <p class="sg-status-info mb-0">
                <i class="fa-solid fa-hashtag" aria-hidden="true"></i>
                <span>
                    @if ($pesanan->exists)
                        Kode sementara <strong class="sg-angka">{{ $pesanan->kode_tampil }}</strong>.
                    @endif
                    Nomor SPP diberikan saat admin mencatat DP dan memasukkan pesanan ke produksi.
                </span>
            </p>
            @endif

            <hr class="sg-pemisah my-3">

            {{-- Pilih customer lama (autocomplete). Kosong = customer baru dari isian di bawah. --}}
            <input type="hidden" name="customer_id" id="f-customer_id" value="{{ $customerTerpilih }}" data-customer-id>
            @if ($pesanan->exists)
                <input type="hidden" name="lepas_customer" id="f-lepas_customer" value="{{ $lepasCustomer ? '1' : '' }}" data-lepas-customer-input>
            @endif
            <div class="sg-field" data-pilih-customer data-url="{{ $urlCariCustomer }}">
                <div class="sg-chip-customer" data-chip-customer @unless ($customerAwal) hidden @endunless>
                    <span class="sg-chip-customer-ikon" aria-hidden="true"><i class="fa-solid fa-user-check"></i></span>
                    <div class="sg-chip-customer-teks">
                        <strong data-chip-judul>@if ($customerAwal)Customer lama {{ $customerAwal->kode }} · {{ $customerAwal->pesanan_count }} SPP @endif</strong>
                        <span data-chip-sub>@if ($customerAwal){{ $customerAwal->nama }} · {{ $customerAwal->whatsapp_tampil }}@endif</span>
                    </div>
                    <div class="sg-chip-customer-aksi">
                        <button type="button" class="sg-btn sg-btn-teks" data-ganti-customer>Ganti</button>
                        <button type="button" class="sg-btn sg-btn-teks" data-lepas-customer aria-label="Lepas customer. SPP akan memakai customer dari isian: customer baru atau pemilik nomor WA ini">Lepas</button>
                    </div>
                </div>

                <div data-cari-customer-wadah @if ($customerAwal) hidden @endif>
                    <label class="sg-label" for="f-cari_customer">Cari customer lama</label>
                    <div class="sg-cari sg-ac">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="text" id="f-cari_customer" class="form-control" autocomplete="off" maxlength="100" enterkeyhint="search"
                               placeholder="Nama, nomor WA, atau kode C-0001"
                               role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="daftar-customer" aria-describedby="bantu-cari-customer"
                               data-input-cari-customer>
                        <ul class="sg-ac-daftar" id="daftar-customer" role="listbox" aria-label="Hasil pencarian customer" data-daftar-customer hidden></ul>
                    </div>
                    <p class="sg-bantu" id="bantu-cari-customer">Belum memilih? Customer baru dibuat dari isian di bawah. Bila nomor WA sudah terdaftar, SPP otomatis dikaitkan ke customer itu.</p>
                </div>
                <p class="visually-hidden" aria-live="polite" data-status-customer></p>
                {!! $galat('customer_id') !!}
            </div>

            <div class="sg-dua" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr))">
                <div class="sg-field">
                    <label class="sg-label" for="f-nama_customer">Nama customer <span class="sg-wajib" aria-hidden="true">*</span></label>
                    <input type="text" id="f-nama_customer" name="nama_customer" value="{{ old('nama_customer', $pesanan->nama_customer) }}" maxlength="150" required autocomplete="off"
                           class="form-control @error('nama_customer') is-invalid @enderror" data-isi-customer="nama" {!! $aria('nama_customer') !!}>
                    {!! $galat('nama_customer') !!}
                </div>
                <div class="sg-field">
                    <label class="sg-label" for="f-nomor_whatsapp">No. WhatsApp <span class="sg-wajib" aria-hidden="true">*</span></label>
                    <input type="tel" inputmode="tel" id="f-nomor_whatsapp" name="nomor_whatsapp" value="{{ $waLama }}" maxlength="30" required autocomplete="off"
                           placeholder="0812 3456 7890" class="form-control sg-angka @error('nomor_whatsapp') is-invalid @enderror" data-isi-customer="wa" {!! $aria('nomor_whatsapp') !!}>
                    {!! $galat('nomor_whatsapp') !!}
                </div>
            </div>
            <div class="sg-field">
                <label class="sg-label" for="f-nama_brand">Nama brand / acara <span class="fw-normal sg-muted">(opsional)</span></label>
                <input type="text" id="f-nama_brand" name="nama_brand" value="{{ old('nama_brand', $pesanan->nama_brand) }}" maxlength="150" autocomplete="off"
                       placeholder="Misalnya: Kopi Senja, Nikahan Rina &amp; Dimas"
                       class="form-control @error('nama_brand') is-invalid @enderror" {!! $aria('nama_brand') !!}>
                {!! $galat('nama_brand') !!}
            </div>
            <div class="sg-field">
                <label class="sg-label" for="f-alamat">Alamat lengkap <span class="sg-wajib" aria-hidden="true">*</span></label>
                <textarea id="f-alamat" name="alamat" rows="2" required maxlength="2000" style="min-height:72px"
                          class="form-control @error('alamat') is-invalid @enderror" data-isi-customer="alamat" {!! $aria('alamat') !!}>{{ old('alamat', $pesanan->alamat) }}</textarea>
                {!! $galat('alamat') !!}
            </div>
            <div class="sg-field">
                <label class="sg-label" for="f-kota">Kota <span class="sg-wajib" aria-hidden="true">*</span></label>
                <input type="text" id="f-kota" name="kota" value="{{ old('kota', $pesanan->kota) }}" maxlength="100" required
                       class="form-control @error('kota') is-invalid @enderror" data-isi-customer="kota" {!! $aria('kota') !!}>
                {!! $galat('kota') !!}
            </div>
            <div class="sg-field" data-wadah-perbarui-customer @unless ($customerAwal) hidden @endunless>
                <label class="sg-centang" for="f-perbarui_customer">
                    <input type="checkbox" id="f-perbarui_customer" name="perbarui_customer" value="1" class="form-check-input" @checked(old('perbarui_customer')) aria-describedby="bantu-perbarui-customer">
                    <span>Perbarui data customer dengan isian ini</span>
                </label>
                <p class="sg-bantu" id="bantu-perbarui-customer">Alamat &amp; kota boleh beda per SPP. Centang hanya bila data customer memang berubah; SPP lain tidak ikut berubah.</p>
            </div>
        </section>

        {{-- ② Item --}}
        <section class="sg-kartu" id="bagian-item" aria-labelledby="judul-bagian-item">
            <h2 class="sg-kartu-judul" id="judul-bagian-item"><span class="sg-no" aria-hidden="true">2</span> Item</h2>
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

            <p class="sg-bantu mt-0 mb-3" data-hitung-file aria-live="polite">Maksimal {{ $maksFile }} gambar per SPP (JPG, PNG, atau WebP, maks 5 MB per gambar).</p>

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

        {{-- ④ Jadwal & harga --}}
        <section class="sg-kartu" id="bagian-harga" aria-labelledby="judul-harga">
            <h2 class="sg-kartu-judul" id="judul-harga"><span class="sg-no" aria-hidden="true">4</span> Jadwal &amp; {{ $pakaiBayar ? 'pembayaran' : 'total' }}</h2>
            <div class="sg-jadwal">
                <div class="sg-field">
                    <label class="sg-label" for="f-tanggal_order">Tanggal SPP <span class="sg-wajib" aria-hidden="true">*</span></label>
                    <input type="date" id="f-tanggal_order" name="tanggal_order" value="{{ old('tanggal_order', $tgl($pesanan->tanggal_order)) }}" required
                           class="form-control @error('tanggal_order') is-invalid @enderror" data-tanggal-order {!! $aria('tanggal_order') !!}>
                    {!! $galat('tanggal_order') !!}
                </div>
                <div class="sg-field">
                    <label class="sg-label" for="f-deadline" id="label-deadline">Deadline <span class="sg-wajib" aria-hidden="true">*</span></label>
                    <div class="sg-deadline-isian">
                        <input type="date" id="f-deadline" name="deadline" value="{{ old('deadline', $tgl($pesanan->deadline)) }}" required
                               min="{{ old('tanggal_order', $tgl($pesanan->tanggal_order)) }}"
                               class="form-control @error('deadline') is-invalid @enderror" data-deadline
                               aria-describedby="bantu-deadline {{ $errors->has('deadline') ? $gid('deadline') : '' }}" @error('deadline') aria-invalid="true" @enderror>
                        <div class="sg-segmen-rapat" id="f-jenis_deadline" role="radiogroup" aria-labelledby="label-jenis-deadline" aria-describedby="bantu-deadline">
                            <span class="visually-hidden" id="label-jenis-deadline">Jenis deadline</span>
                            @foreach ($jenisDeadline as $nilai => $label)
                                <label>
                                    <input type="radio" name="jenis_deadline" value="{{ $nilai }}" @checked(old('jenis_deadline', $pesanan->jenis_deadline ?? 'kirim') === $nilai)>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    {!! $galat('deadline') !!}
                    {!! $galat('jenis_deadline') !!}
                    <p class="sg-bantu" id="bantu-deadline"><strong>Siap</strong>: barang siap diambil pada tanggal itu. <strong>Kirim</strong>: barang sudah dikirim.</p>
                </div>
            </div>

            <div class="sg-otomatis"><span>Subtotal produk <span class="sg-kecil sg-muted">(otomatis)</span></span><strong data-total>Rp 0</strong></div>

            {{-- Ongkir & diskon (rancangan invoice 5.3): total tagihan & sisa dihitung langsung di pesanan.js --}}
            @php
                $biayaTampil = function (string $f) use ($pesanan) {
                    $v = old($f, (float) $pesanan->{$f} > 0 ? (int) $pesanan->{$f} : '');
                    return is_int($v) || (is_string($v) && ctype_digit($v) && $v !== '') ? number_format((int) $v, 0, ',', '.') : $v;
                };
            @endphp
            <div class="sg-dua mt-2">
                <div class="sg-field">
                    <label class="sg-label" for="f-ongkir">Ongkir <span class="fw-normal sg-muted">(opsional)</span></label>
                    <div class="sg-imbuh sg-imbuh-depan">
                        <span class="sg-imbuh-teks" aria-hidden="true">Rp</span>
                        <input type="text" inputmode="numeric" id="f-ongkir" name="ongkir" value="{{ $biayaTampil('ongkir') }}" autocomplete="off" placeholder="0"
                               class="form-control sg-angka @error('ongkir') is-invalid @enderror" data-rupiah data-ongkir {!! $aria('ongkir') !!}>
                    </div>
                    {!! $galat('ongkir') !!}
                </div>
                <div class="sg-field">
                    <label class="sg-label" for="f-diskon">Diskon <span class="fw-normal sg-muted">(opsional)</span></label>
                    <div class="sg-imbuh sg-imbuh-depan">
                        <span class="sg-imbuh-teks" aria-hidden="true">Rp</span>
                        <input type="text" inputmode="numeric" id="f-diskon" name="diskon" value="{{ $biayaTampil('diskon') }}" autocomplete="off" placeholder="0"
                               class="form-control sg-angka @error('diskon') is-invalid @enderror" data-diskon
                               aria-describedby="bantu-diskon galat-diskon-langsung {{ $errors->has('diskon') ? $gid('diskon') : '' }}" @error('diskon') aria-invalid="true" data-galat-server @enderror data-rupiah>
                    </div>
                    {!! $galat('diskon') !!}
                    <p class="sg-galat" id="galat-diskon-langsung" data-galat-diskon hidden><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span></span></p>
                    <p class="sg-bantu" id="bantu-diskon">Potongan dalam rupiah.</p>
                </div>
            </div>

            <div class="sg-otomatis sg-otomatis-total"><span>Total tagihan <span class="sg-kecil sg-muted">(otomatis)</span></span><strong data-total-tagihan aria-live="polite">Rp 0</strong></div>

            @if ($pakaiBayar)
            <div class="sg-dua-md mt-2">
                <div>
                    <div class="sg-field">
                        <label class="sg-label" for="f-dp">DP / sudah dibayar <span class="sg-wajib" aria-hidden="true">*</span></label>
                        <div class="sg-imbuh sg-imbuh-depan">
                            <span class="sg-imbuh-teks" aria-hidden="true">Rp</span>
                            <input type="text" inputmode="numeric" id="f-dp" name="dp" value="{{ $dpTampil }}" autocomplete="off" placeholder="0" required
                                   pattern=".*[1-9].*" data-min-rupiah="DP minimal Rp 1 karena SPP sudah masuk produksi."
                                   class="form-control sg-angka @error('dp') is-invalid @enderror" data-rupiah data-dp
                                   aria-describedby="bantu-dp peringatan-dp {{ $errors->has('dp') ? $gid('dp') : '' }}" @error('dp') aria-invalid="true" @enderror>
                        </div>
                        {!! $galat('dp') !!}
                        <p class="sg-bantu" id="bantu-dp">SPP sudah masuk produksi, DP wajib diisi minimal Rp 1.</p>
                        <p class="sg-galat" id="peringatan-dp" data-peringatan-dp hidden><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span></span></p>
                    </div>
                    @include('admin.pesanan._metode-bayar', [
                        'nama' => 'metode_dp', 'legend' => 'Metode DP', 'terpilih' => $pesanan->metode_dp?->value,
                        'bantuKosong' => 'Belum dicatat',
                    ])
                </div>
                <div>
                    <div class="sg-field">
                        <label class="sg-label" for="f-tanggal_lunas">Tanggal lunas @if ($lunasWajib)<span class="sg-wajib" aria-hidden="true">*</span>@endif</label>
                        <input type="date" id="f-tanggal_lunas" name="tanggal_lunas" value="{{ old('tanggal_lunas', $tgl($pesanan->tanggal_lunas)) }}" max="{{ today()->toDateString() }}" @if ($lunasWajib) required @endif
                               class="form-control @error('tanggal_lunas') is-invalid @enderror" data-tanggal-lunas
                               aria-describedby="bantu-lunas {{ $errors->has('tanggal_lunas') ? $gid('tanggal_lunas') : '' }}" @error('tanggal_lunas') aria-invalid="true" @enderror>
                        {!! $galat('tanggal_lunas') !!}
                        <p class="sg-bantu" id="bantu-lunas">{{ $lunasWajib ? 'Kembalikan status ke Pesanan Selesai bila pelunasan perlu dikoreksi.' : 'Isi bila customer sudah melunasi.' }}</p>
                    </div>
                    {{-- Hanya tampil (dan ikut terkirim) bila tanggal lunas terisi; diatur pesanan.js --}}
                    @include('admin.pesanan._metode-bayar', [
                        'nama' => 'metode_lunas', 'legend' => 'Metode pelunasan', 'terpilih' => $pesanan->metode_lunas?->value,
                        'bantuKosong' => 'Belum dicatat', 'atribut' => 'data-metode-lunas',
                    ])
                </div>
            </div>

            <div class="sg-otomatis"><span>Sisa <span class="sg-kecil sg-muted">(otomatis)</span></span><strong data-sisa>Rp 0</strong></div>
            <p class="mb-3">@include('admin.pesanan._badge-bayar-live')</p>
            @else
            <p class="sg-bantu mt-0 mb-3" data-bantu-total>
                @if ($hargaWajib)
                    DP dicatat lewat tombol <strong>Catat DP &amp; masukkan ke produksi</strong> di halaman detail.
                @else
                    Harga boleh dikosongkan; produksi menghitungnya. DP dicatat setelah harga lengkap, lewat halaman detail.
                @endif
            </p>
            @endif

            <div class="sg-field">
                <label class="sg-label" for="f-catatan_admin">Catatan SPP</label>
                <textarea id="f-catatan_admin" name="catatan_admin" rows="3" maxlength="5000"
                          class="form-control @error('catatan_admin') is-invalid @enderror" {!! $aria('catatan_admin') !!}>{{ old('catatan_admin', $pesanan->catatan_admin) }}</textarea>
                {!! $galat('catatan_admin') !!}
            </div>
        </section>
    </div>

    {{-- Kolom samping (≥1200) --}}
    <aside class="sg-form-samping" aria-label="Ringkasan SPP">
        <div class="sg-kartu sg-ringkas">
            <h2 class="sg-kartu-judul">Ringkasan</h2>
            <dl>
                <div><dt>Item</dt><dd data-jumlah-item>1</dd></div>
                <div><dt>Subtotal</dt><dd data-total>Rp 0</dd></div>
                <div data-baris-ongkir hidden><dt>Ongkir</dt><dd data-ongkir-tampil>Rp 0</dd></div>
                <div data-baris-diskon hidden><dt>Diskon</dt><dd data-diskon-tampil>− Rp 0</dd></div>
                <div class="sg-baris-total {{ $pakaiBayar ? '' : 'sg-sisa' }}"><dt>Total</dt><dd data-total-tagihan>Rp 0</dd></div>
                @if ($pakaiBayar)
                    <div><dt>DP</dt><dd data-dp-tampil>Rp 0</dd></div>
                    <div class="sg-sisa"><dt>Sisa</dt><dd data-sisa>Rp 0</dd></div>
                @endif
            </dl>
            @if ($pakaiBayar)
                <p class="mt-2">@include('admin.pesanan._badge-bayar-live')</p>
            @endif
            <hr class="sg-pemisah my-3">
            <nav aria-label="Lompat ke bagian">
                <ol class="sg-lompat">
                    <li><a href="#bagian-customer">1. SPP &amp; customer</a></li>
                    <li><a href="#bagian-item">2. Item (<span data-jumlah-item>1</span>)</a></li>
                    <li><a href="#bagian-desain">3. Desain</a></li>
                    <li><a href="#bagian-harga">4. Jadwal &amp; {{ $pakaiBayar ? 'pembayaran' : 'total' }}</a></li>
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
        <strong>Total <span data-total-tagihan>Rp 0</span></strong>
        @if ($pakaiBayar)
            <span>Sisa <span data-sisa>Rp 0</span></span>
        @else
            <span data-total-ket>{{ $hargaWajib ? 'Dihitung dari item' : 'Harga boleh menyusul' }}</span>
        @endif
    </div>
    <a href="{{ $urlBatal }}" class="sg-btn sg-btn-sekunder d-none d-sm-inline-flex" data-batal-form>Batal</a>
    <button type="submit" class="sg-btn sg-btn-utama" data-teks-kirim="Menyimpan…">Simpan</button>
</div>
