{{--
    Halaman Master Bahan / Finishing / Cetak: form tambah + daftar.
    Variabel: $daftar (koleksi dengan produk_count), $rute ('admin.bahan'), $kolom ('nama_bahan'),
    $label ('bahan'), $judul ('Master Bahan'), $placeholder, $ikon ('fa-scroll').
--}}
@php
    $Label = \Illuminate\Support\Str::ucfirst($label);
    $idNama = 'f-' . $kolom;
    $teksHapus = fn ($item) => [
        'aksi' => route($rute . '.destroy', $item),
        'judul' => 'Hapus ' . $label . ' ' . $item->{$kolom} . '?',
        'isi' => $item->produk_count ? $Label . ' ini juga dilepas dari ' . $item->produk_count . ' produk.' : 'Belum dipakai produk mana pun.',
        'labelAria' => 'Hapus ' . $label . ' ' . $item->{$kolom},
    ];
@endphp

<div class="sg-kepala">
    <div>
        <h1>{{ $judul }}</h1>
        <p class="sg-kepala-sub">{{ $daftar->count() }} {{ $label }} · dipakai di halaman Atribut produk</p>
    </div>
</div>

<div class="sg-master-tata">
    <section class="sg-kartu" aria-labelledby="judul-tambah">
        <h2 class="sg-kartu-judul" id="judul-tambah">Tambah {{ $label }}</h2>
        <form action="{{ route($rute . '.store') }}" method="POST" data-kirim>
            @csrf
            <div class="sg-field">
                <label class="sg-label" for="{{ $idNama }}">Nama {{ $label }} <span class="sg-wajib" aria-hidden="true">*</span></label>
                <input type="text" id="{{ $idNama }}" name="{{ $kolom }}" value="{{ old($kolom) }}" placeholder="{{ $placeholder }}" required
                       class="form-control @error($kolom) is-invalid @enderror" @error($kolom) aria-invalid="true" aria-describedby="g-{{ $kolom }}" @enderror>
                @include('admin.layouts._galat', ['k' => $kolom])
            </div>
            <div class="sg-field">
                <label class="sg-label" for="f-deskripsi">Deskripsi <span class="fw-normal sg-muted">(opsional)</span></label>
                <textarea id="f-deskripsi" name="deskripsi" rows="3" class="form-control @error('deskripsi') is-invalid @enderror"
                          @error('deskripsi') aria-invalid="true" aria-describedby="g-deskripsi" @enderror>{{ old('deskripsi') }}</textarea>
                @include('admin.layouts._galat', ['k' => 'deskripsi'])
            </div>
            <button type="submit" class="sg-btn sg-btn-utama sg-btn-penuh mt-2" data-teks-kirim="Menyimpan…"><i class="fa-solid fa-plus" aria-hidden="true"></i> Tambah</button>
        </form>
    </section>

    <section aria-label="Daftar {{ $label }}">
        @if ($daftar->isEmpty())
            <div class="sg-kosong">
                <span class="sg-kosong-ikon"><i class="fa-solid {{ $ikon }}" aria-hidden="true"></i></span>
                <h2>Belum ada {{ $label }}</h2>
                <p>{{ $Label }} yang ditambahkan bisa dicentang di halaman Atribut produk.</p>
            </div>
        @else
            {{-- Desktop: tabel --}}
            <div class="d-none d-lg-block sg-tabel-gulir">
                <table class="sg-tabel sg-tabel-katalog">
                    <thead>
                        <tr>
                            <th scope="col">Nama</th>
                            <th scope="col">Deskripsi</th>
                            <th scope="col">Dipakai</th>
                            <th scope="col"><span class="visually-hidden">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($daftar as $item)
                            <tr>
                                <td><strong class="fw-semibold" style="overflow-wrap:anywhere">{{ $item->{$kolom} }}</strong></td>
                                <td class="sg-muted"><span class="sg-klip-2">{{ $item->deskripsi ?: '—' }}</span></td>
                                <td>
                                    @if ($item->produk_count)
                                        <span class="sg-chip-kecil text-nowrap">{{ $item->produk_count }} produk</span>
                                    @else
                                        <span class="sg-muted sg-kecil text-nowrap">Belum dipakai</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="sg-tabel-aksi">
                                        <a href="{{ route($rute . '.edit', $item) }}" class="sg-btn sg-btn-sekunder" aria-label="Edit {{ $label }} {{ $item->{$kolom} }}"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>
                                        @include('admin.katalog._hapus', $teksHapus($item) + ['ikon' => true])
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- HP & tablet: kartu --}}
            <ul class="sg-kartu-katalog d-lg-none">
                @foreach ($daftar as $item)
                    <li>
                        <div class="sg-kk-badan">
                            <h3 class="sg-kk-judul">{{ $item->{$kolom} }}</h3>
                            @if ($item->deskripsi)
                                <p class="sg-kk-deskripsi">{{ $item->deskripsi }}</p>
                            @endif
                            <p class="sg-kk-info">
                                @if ($item->produk_count)
                                    <span class="sg-chip-kecil">{{ $item->produk_count }} produk</span>
                                @else
                                    <span class="sg-muted">Belum dipakai</span>
                                @endif
                            </p>
                        </div>
                        <div class="sg-kk-aksi">
                            <a href="{{ route($rute . '.edit', $item) }}" class="sg-btn sg-btn-sekunder" aria-label="Edit {{ $label }} {{ $item->{$kolom} }}"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>
                            @include('admin.katalog._hapus', $teksHapus($item))
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
