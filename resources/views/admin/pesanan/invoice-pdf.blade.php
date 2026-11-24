{{--
    Invoice PDF (DomPDF, A4 portrait). Data dari App\Services\InvoicePdf::dataView().
    Layout final admin (23 Nov 2026): kop merah tua + kapsul kontak, INVOICE di kanan, tabel item,
    sela bergaris, blok bawah (pembayaran transfer + rincian + ttd) menempel di bawah halaman terakhir.
    Watermark stempel pudar di tengah setiap halaman (position: fixed, di belakang isi). Aturan DomPDF: tata letak dengan <table>, CSS 2.1, gambar data URI, font Poppins.
    $sela = null pada pass ukur (gambar diganti PNG 1×1, jadi semua <img> wajib berukuran tetap lewat CSS/style);
    render penuh berisi tinggi sela (lihat InvoicePdf::buat). Teks panjang tanpa spasi dipotong (overflow-wrap)
    dan tabel item memakai table-layout: fixed supaya kolom angka selalu terlihat.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Invoice {{ $nomor }} - {{ $usaha['nama'] }}</title>
<style>
    @font-face { font-family: 'Poppins'; font-style: normal; font-weight: normal; src: url('{{ $fontRegular }}') format('truetype'); }
    @font-face { font-family: 'Poppins'; font-style: normal; font-weight: bold; src: url('{{ $fontBold }}') format('truetype'); }

    /* Bawah 26 mm: ruang footer (garis 274 mm) */
    @page { margin: 13mm 14mm 26mm 14mm; }

    /* DomPDF: width/height = kotak isi (padding di luar), jadi ukuran di bawah sudah dikurangi padding */
    body { margin: 0; font-family: 'Poppins', 'DejaVu Sans', sans-serif; font-size: 10pt; line-height: 1.3; color: #1C1917; }
    table { border-collapse: collapse; width: 100%; }
    td, th { padding: 0; vertical-align: top; }
    img { display: block; }
    .kanan { text-align: right; }
    .tengah { text-align: center; }
    .tebal { font-weight: bold; }

    /* Kop */
    .nama-usaha { font-size: 17.8pt; line-height: 1.15; font-weight: bold; color: #7A0505; }
    .alamat { font-size: 10pt; line-height: 1.3; color: #7A0505; margin-top: 1mm; }
    .kapsul { margin-top: 4.6mm; height: 7.3mm; background: #7A0505; border-radius: 3.65mm; padding: 0 0 0 4mm; }
    .kapsul td { vertical-align: middle; height: 7.3mm; color: #FFFFFF; font-size: 7.4pt; line-height: 1; white-space: nowrap; }
    .kapsul .ikon { width: 4.6mm; padding-top: 0.2mm; }
    .kapsul .ikon img { width: 3.5mm; height: 3.5mm; }
    .kapsul .teks { padding-right: 4.8mm; padding-top: 0.6mm; }
    .kop-kanan { width: 60mm; text-align: right; vertical-align: top; }
    .logo-kop { margin-left: auto; margin-top: 1.2mm; }
    .judul { font-size: 28.5pt; line-height: 1; font-weight: bold; color: #111111; margin-top: 4.4mm; letter-spacing: 0.3pt; }
    .nomor { font-size: 9.5pt; color: #57534E; margin-top: 0.8mm; }
    .garis-kop { border-bottom: 1.4pt solid #7A0505; margin-top: 3.2mm; }

    /* Kepada, tanggal, status */
    .kepada td { vertical-align: top; }
    .label-kepada { font-size: 7pt; letter-spacing: 1pt; font-weight: bold; color: #44403C; }
    .kepada-nama { font-size: 10pt; font-weight: bold; margin-top: 2mm; overflow-wrap: anywhere; }
    .kepada-hp { font-size: 9.2pt; margin-top: 1.2mm; }
    .kepada-hp .lbl { width: 11.5mm; }
    .tgl-label { font-size: 9.4pt; color: #57534E; }
    .tgl-nilai { font-size: 11pt; font-weight: bold; color: #44403C; margin-top: 1.8mm; }
    .pemisah { width: 4mm; border-right: 0.75pt solid #D6D3D1; }
    .status-label { font-size: 9.4pt; color: #57534E; }
    .badge { margin-top: 1.6mm; padding: 1.1mm 0 0.9mm 0; font-size: 8pt; font-weight: bold; text-align: center; border: 0.75pt solid; border-radius: 1.2mm; }
    .badge-belum_lunas { background: #FDECEF; border-color: #F5BCC5; color: #D32F2F; }
    .badge-lunas { background: #E8F7EE; border-color: #A9DFBF; color: #1E8449; }

    /* Tabel item */
    .item { margin-top: 9.5mm; table-layout: fixed; width: 100%; }
    .item thead { display: table-header-group; }
    .item tr { page-break-inside: avoid; }
    .item th { background: #F5F5F3; height: 5.6mm; padding: 3.4mm 3mm 0 3mm; font-size: 6.8pt; letter-spacing: 1pt; font-weight: bold; color: #44403C; text-align: left; vertical-align: top; }
    .item td { padding: 4.4mm 3mm 4.4mm 3mm; border-bottom: 0.75pt solid #D6D6D6; font-size: 9.2pt; }
    .item .detail { overflow-wrap: anywhere; }
    .item .angka { text-align: right; white-space: nowrap; }
    .item .no { padding-left: 2.3mm; }
    .item .akhir { padding-right: 1.2mm; }
    .item-judul { font-weight: bold; font-size: 9.2pt; }
    .item-detail { font-size: 7.5pt; line-height: 1.3; color: #57534E; margin-top: 1.4mm; }
    .item-catatan { font-size: 7.5pt; line-height: 1.3; color: #78716C; margin-top: 0.6mm; }

    /* Watermark tiap halaman: fixed = diulang DomPDF di setiap halaman, relatif ke area isi (dalam margin
       @page). z-index negatif + posisi pertama di <body> = digambar paling dulu, jadi di belakang isi. */
    .watermark { position: fixed; z-index: -1; }

    /* Sela: garis tipis */
    .sela { position: relative; page-break-inside: avoid; }
    .sela-baru { page-break-before: always; }
    .sela-garis { position: absolute; left: 0; width: 182mm; height: 0; border-top: 0.75pt solid #D6D6D6; }

    /* Blok bawah (tidak terbelah) */
    .blok-bawah { page-break-inside: avoid; padding-top: 4mm; }
    .keterangan { font-size: 8pt; line-height: 1.3; color: #57534E; margin-bottom: 2.5mm; overflow-wrap: anywhere; }
    .kotak-bayar { width: 89.3mm; background: #E8E9E8; border-radius: 4.5mm; padding: 5mm 4.5mm 4.5mm 4.5mm; }
    .judul-bayar { font-size: 10.5pt; letter-spacing: 0.6pt; font-weight: bold; color: #44403C; text-align: center; }
    .rekening { margin-top: 3mm; }
    .rekening td { vertical-align: middle; height: 7.2mm; }
    .rekening .logo { width: 14.5mm; }
    .rekening .logo img { height: 4.1mm; }
    .rekening .bank-teks { font-weight: bold; font-size: 10pt; color: #0B5CAD; }
    .rekening .norek { width: 35mm; font-size: 9.6pt; font-weight: bold; white-space: nowrap; }
    .rekening .an { font-size: 7.8pt; white-space: nowrap; }
    .qris { margin-top: 2mm; }
    .qris-bingkai { width: 27.8mm; height: 27.8mm; background: #FFFFFF; padding: 1.6mm; }
    .qris-teks { padding-left: 5.4mm; vertical-align: top; }
    .qris-teks .logo-qris { height: 8.6mm; margin-top: 1.8mm; }
    .qris-an { font-size: 11pt; font-weight: bold; margin-top: 3mm; overflow-wrap: anywhere; }
    .qris-mohon { font-size: 8.4pt; line-height: 1.45; color: #44403C; margin-top: 1mm; }

    .rincian td { font-size: 11pt; height: 6.2mm; vertical-align: middle; }
    .rincian .nilai { text-align: right; white-space: nowrap; padding-left: 2mm; }
    /* Baris DP/Pelunasan: metode · tanggal di baris kecil bawah label, nominal sejajar baris label */
    .rincian .berinfo td { vertical-align: top; height: auto; padding-top: 0.9mm; padding-bottom: 0.7mm; }
    .rincian .info { font-size: 7.5pt; line-height: 1.2; font-weight: normal; color: #57534E; }
    .rincian .tebal td { font-weight: bold; }
    .rincian .garis td { border-bottom: 0.75pt solid #44403C; }
    /* Ajakan bayar DP: latar merah muda tipis seperti badge BELUM LUNAS, teks merah tua tebal */
    .rincian-sorot { border-collapse: separate; border-spacing: 0; margin-top: 1.6mm; background: #FDECEF; border: 0.75pt solid #F5BCC5; border-radius: 1.2mm; }
    .rincian-sorot td { font-size: 11pt; font-weight: bold; color: #7A0505; height: 6.2mm; vertical-align: middle; padding: 0 1.6mm; }
    .rincian-sorot .nilai { text-align: right; white-space: nowrap; }
    .kelebihan { font-size: 8pt; color: #B54708; text-align: right; margin-top: 1mm; }
    .ttd-area { position: relative; height: 22mm; margin-top: 2mm; }
    .ttd-kosong { height: 16mm; }
    .hormat { font-size: 9.2pt; text-align: center; margin-top: 1mm; }
    .ttd-nama { font-size: 9.6pt; font-weight: bold; text-align: center; overflow-wrap: anywhere; }
    .ttd-jabatan { font-size: 8.5pt; color: #57534E; text-align: center; overflow-wrap: anywhere; }
</style>
</head>
<body>

{{-- ── Watermark stempel (setiap halaman, di belakang isi) ── --}}
@if ($watermark)
    <img class="watermark" src="{{ $watermark['uri'] }}" alt=""
         style="left: {{ $watermark['kiri'] }}mm; top: {{ $watermark['atas'] }}mm; width: {{ $watermark['lebar'] }}mm; height: {{ $watermark['tinggi'] }}mm;">
@endif

{{-- ── Kop ─────────────────────────────────────── --}}
<table>
    <tr>
        <td>
            <div class="nama-usaha">{{ $usaha['nama'] }}</div>
            <div class="alamat">{{ $usaha['alamat'] }}</div>
            <table class="kapsul" style="width: auto;">
                <tr>
                    @foreach ([['wa', $usaha['wa']], ['web', $usaha['situs']], ['ig', $usaha['instagram']]] as [$jenis, $teks])
                        @if ($teks !== '')
                            @if ($ikon[$jenis])
                                <td class="ikon"><img src="{{ $ikon[$jenis]['uri'] }}" alt=""></td>
                            @endif
                            <td class="teks">{{ $teks }}</td>
                        @endif
                    @endforeach
                </tr>
            </table>
        </td>
        <td class="kop-kanan">
            @if ($logoKop)
                <img class="logo-kop" src="{{ $logoKop['uri'] }}" alt="{{ $usaha['nama'] }}" style="width: {{ $logoKop['lebar'] }}mm; height: {{ $logoKop['tinggi'] }}mm;">
            @endif
            <div class="judul">INVOICE</div>
            <div class="nomor">{{ $nomor }}</div>
        </td>
    </tr>
</table>
<div class="garis-kop"></div>

{{-- ── Kepada + tanggal + status ─────────────────── --}}
<table class="kepada" style="margin-top: 5.5mm;">
    <tr>
        <td>
            <div class="label-kepada">KEPADA</div>
            <div class="kepada-nama">{{ $kepada['nama'] }}</div>
            @if ($kepada['wa'])
                <table class="kepada-hp" style="width: auto;"><tr><td class="lbl">No.HP</td><td>:&nbsp;&nbsp;{{ $kepada['wa'] }}</td></tr></table>
            @endif
        </td>
        <td style="width: 50mm; padding-top: 2.2mm;" class="kanan">
            <div class="tgl-label">Tanggal :</div>
            <div class="tgl-nilai">{{ $tanggal ?? '–' }}</div>
        </td>
        <td class="pemisah">&nbsp;</td>
        <td style="width: 23.5mm; padding-left: 4mm; padding-top: 0.8mm;">
            <div class="status-label" style="text-align: right;">Status bayar :</div>
            <div class="badge badge-{{ $statusBayar }}">{{ $labelStatus }}</div>
        </td>
    </tr>
</table>

{{-- ── Tabel item ───────────────────────────────── --}}
<table class="item">
    <thead>
        <tr>
            {{-- table-layout: fixed di DomPDF hanya memakai lebar persen (lebar mm dianggap 0). Persen dari 182 mm,
                 termasuk padding: NO 12,2 mm, detail 77,7 mm, jumlah 28 mm, harga 35,2 mm, subtotal 28,9 mm. --}}
            <th class="no" style="width: 6.7%;">NO</th>
            <th style="width: 42.7%;">DETAIL PRODUK</th>
            <th style="width: 15.4%; text-align: right;">JUMLAH</th>
            <th style="width: 19.3%; text-align: right;">HARGA 1 PCS</th>
            <th class="akhir" style="width: 15.9%; text-align: right;">SUBTOTAL</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($item as $baris)
            <tr @if ($loop->last) id="baris-akhir" @endif>
                <td class="no">{{ $baris['no'] }}</td>
                <td class="detail">
                    <div class="item-judul">{{ $baris['judul'] }}</div>
                    @if ($baris['spek'] !== '')
                        <div class="item-detail">{{ $baris['spek'] }}</div>
                    @endif
                    @if ($baris['catatan'])
                        <div class="item-catatan">Catatan: {{ $baris['catatan'] }}</div>
                    @endif
                </td>
                <td class="angka">{{ $baris['jumlah'] }}</td>
                <td class="angka">{{ $baris['harga'] }}</td>
                <td class="angka akhir">{{ $baris['subtotal'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- ── Sela: garis tipis (hanya di render penuh; halaman_baru = mulai di halaman berikutnya) ── --}}
@if ($sela)
    <div class="sela {{ $sela['halaman_baru'] ? 'sela-baru' : '' }}" style="height: {{ $sela['tinggi'] }}mm;">
        @foreach ($sela['garis'] as $y)
            <div class="sela-garis" style="top: {{ $y }}mm;"></div>
        @endforeach
    </div>
@endif

{{-- ── Blok bawah: pembayaran + rincian + ttd ───── --}}
<div class="blok-bawah" id="blok-bawah">
    @if ($keterangan !== '')
        <div class="keterangan">{!! nl2br(e($keterangan)) !!}</div>
    @endif
    <table>
        <tr>
            <td style="width: 103mm;">
                @if (count($rekening) || $qris)
                    <div class="kotak-bayar">
                        <div class="judul-bayar">PEMBAYARAN TRANSFER</div>
                        @if (count($rekening))
                            <table class="rekening">
                                @foreach ($rekening as $r)
                                    <tr>
                                        <td class="logo">
                                            @if ($r['logo'])
                                                <img src="{{ $r['logo']['uri'] }}" alt="{{ $r['bank'] }}" style="width: {{ round(4.1 * $r['logo']['lebar'] / max($r['logo']['tinggi'], 1), 2) }}mm;">
                                            @else
                                                <span class="bank-teks">{{ $r['bank'] }}</span>
                                            @endif
                                        </td>
                                        <td class="norek">{{ $r['nomor'] }}</td>
                                        <td class="an">@if ($r['atas_nama'] !== '')a.n. {{ $r['atas_nama'] }}@endif</td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif
                        @if ($qris)
                            <table class="qris">
                                <tr>
                                    <td style="width: 31mm;">
                                        <div class="qris-bingkai"><img src="{{ $qris['uri'] }}" alt="QRIS" style="width: 27.8mm; height: 27.8mm;"></div>
                                    </td>
                                    <td class="qris-teks">
                                        @if ($logoQris)
                                            <img class="logo-qris" src="{{ $logoQris['uri'] }}" alt="QRIS" style="width: {{ round(8.6 * $logoQris['lebar'] / max($logoQris['tinggi'], 1), 2) }}mm;">
                                        @else
                                            <div class="qris-an" style="margin-top: 1.8mm; font-size: 16pt;">QRIS</div>
                                        @endif
                                        @if ($qrisKeterangan !== '')
                                            <div class="qris-an">{{ $qrisKeterangan }}</div>
                                        @endif
                                        @if ($mohonBukti)
                                            <div class="qris-mohon">Mohon kirimkan bukti transfer setelah pembayaran. Terima kasih</div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        @endif
                    </div>
                @endif
            </td>
            <td style="padding-left: 2mm;">
                <table class="rincian">
                    @foreach (array_filter($ringkasan['baris'], fn ($r) => empty($r['sorot'])) as $r)
                        <tr class="{{ $r['tebal'] ? 'tebal' : '' }} {{ $r['garis'] ? 'garis' : '' }} {{ ! empty($r['info']) ? 'berinfo' : '' }}">
                            <td>{{ $r['label'] }}@if (! empty($r['info']))<div class="info">{{ $r['info'] }}</div>@endif</td>
                            <td class="nilai">{{ $r['nilai'] }}</td>
                        </tr>
                    @endforeach
                </table>
                {{-- Ajakan bayar (mis. "Bayar DP min 50%"): kotak sorot terpisah, bukan bagian penjumlahan --}}
                @foreach (array_filter($ringkasan['baris'], fn ($r) => ! empty($r['sorot'])) as $r)
                    <table class="rincian-sorot">
                        <tr>
                            <td>{{ $r['label'] }}</td>
                            <td class="nilai">{{ $r['nilai'] }}</td>
                        </tr>
                    </table>
                @endforeach
                @if ($ringkasan['kelebihan'])
                    <div class="kelebihan">{{ $ringkasan['kelebihan'] }}</div>
                @endif

                @php
                    $stempel = $penandatangan['stempel'];
                    $ttd = $penandatangan['ttd'];
                @endphp
                <table style="margin-top: 1mm;">
                    <tr>
                        <td style="width: 42mm;"></td>
                        <td>
                            @if ($stempel || $ttd)
                                <div class="ttd-area">
                                    @if ($stempel)
                                        <img src="{{ $stempel['uri'] }}" alt="Stempel"
                                             style="position: absolute; left: 50%; margin-left: -{{ $stempel['lebar'] / 2 }}mm; top: {{ 22 - $stempel['tinggi'] - 1 }}mm; width: {{ $stempel['lebar'] }}mm; height: {{ $stempel['tinggi'] }}mm;">
                                    @endif
                                    @if ($ttd)
                                        <img src="{{ $ttd['uri'] }}" alt="Tanda tangan"
                                             style="position: absolute; left: 50%; margin-left: -{{ $ttd['lebar'] / 2 + 2 }}mm; top: 0; width: {{ $ttd['lebar'] }}mm; height: {{ $ttd['tinggi'] }}mm;">
                                    @endif
                                </div>
                            @else
                                <div class="ttd-kosong"></div>
                            @endif
                            <div class="hormat">Hormat kami,</div>
                            @if ($penandatangan['nama'] !== '')
                                <div class="ttd-nama">{{ $penandatangan['nama'] }}</div>
                            @endif
                            @if ($penandatangan['jabatan'] !== '')
                                <div class="ttd-jabatan">{{ $penandatangan['jabatan'] }}</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
