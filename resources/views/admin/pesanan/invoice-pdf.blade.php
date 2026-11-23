{{--
    Invoice PDF (DomPDF, A4 portrait). Data dari App\Services\InvoicePdf::dataView().
    Aturan DomPDF: tata letak hanya <table>, gambar data URI, font Poppins (normal/bold saja).
    Rancangan: catatan/2026-11-23-desain-invoice-senggani.md bagian 2–3 & 8.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Invoice {{ $nomor }} - {{ $usaha['nama'] }}</title>
<style>
    @font-face { font-family: 'Poppins'; font-style: normal; font-weight: normal; src: url('{{ $fontRegular }}') format('truetype'); }
    @font-face { font-family: 'Poppins'; font-style: normal; font-weight: bold; src: url('{{ $fontBold }}') format('truetype'); }

    @page { margin: 14mm 14mm 18mm 14mm; }

    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Poppins', 'DejaVu Sans', sans-serif; font-size: 9pt; line-height: 1.3; color: #1C1917; }
    table { border-collapse: collapse; width: 100%; }
    td, th { padding: 0; vertical-align: top; }
    img { display: block; }
    .kanan { text-align: right; }
    .tengah { text-align: center; }
    .tebal { font-weight: bold; }
    .bantu { font-size: 8pt; line-height: 1.25; color: #57534E; }
    .label-kecil { font-size: 7.5pt; letter-spacing: .5pt; text-transform: uppercase; font-weight: bold; color: #44403C; }
    .jarak { height: 6mm; }

    /* Kop */
    .kop td { vertical-align: middle; }
    .kop-logo { width: 46mm; }
    .kop-logo-blok { width: 46mm; height: 11mm; padding-top: 5mm; background: #4C0404; text-align: center; }
    .kop-logo-blok img { display: inline; height: 6mm; }
    .kop-identitas { padding-left: 4mm; }
    .nama-usaha { font-size: 13pt; line-height: 1.2; font-weight: bold; }
    .tagline { font-size: 7.5pt; letter-spacing: 1pt; text-transform: uppercase; font-weight: bold; color: #7A0505; }
    .kop-judul { width: 50mm; text-align: right; }
    .judul { font-size: 24pt; line-height: 1.1; letter-spacing: 1pt; font-weight: bold; color: #7A0505; }
    .nomor { font-size: 11pt; font-weight: bold; }
    .garis-kop { border-bottom: 1.5pt solid #7A0505; height: 3mm; }

    /* Kepada & tanggal */
    .kepada-nama { font-size: 10pt; font-weight: bold; line-height: 1.3; }
    .info td { padding: 0 0 1.5pt 0; }
    .info .lbl { color: #57534E; text-align: right; padding-right: 4mm; white-space: nowrap; }
    .info .nilai { text-align: right; font-weight: bold; white-space: nowrap; }
    .badge { font-size: 8pt; font-weight: bold; padding: 2pt 7pt; border: 0.75pt solid; border-radius: 3pt; }
    .badge-belum_bayar { background: #FEF3F2; border-color: #FECDCA; color: #B42318; }
    .badge-dp { background: #FFFAEB; border-color: #FEDF89; color: #B54708; }
    .badge-lunas { background: #ECFDF3; border-color: #ABEFC6; color: #067647; }

    /* Tabel item */
    .item thead { display: table-header-group; }
    .item tr { page-break-inside: avoid; }
    .item th { background: #F5F5F4; padding: 5pt 6pt; font-size: 7.5pt; letter-spacing: .5pt; text-transform: uppercase; font-weight: bold; color: #44403C; text-align: left; }
    .item td { padding: 5pt 6pt; border-bottom: 0.75pt solid #D6D3D1; }
    .item .angka { text-align: right; white-space: nowrap; }
    .item-judul { font-weight: bold; }
    .item-detail { font-size: 7.5pt; line-height: 1.25; color: #57534E; }

    /* Penutup (ringkasan + info bayar + ttd) tidak terbelah */
    .penutup { page-break-inside: avoid; }
    .ringkasan td { padding: 2pt 0; }
    .ringkasan .nilai { text-align: right; white-space: nowrap; padding-left: 3mm; }
    .ringkasan .garis td { border-top: 0.75pt solid #1C1917; padding: 0; height: 3pt; }
    .ringkasan .besar td { font-size: 11.5pt; font-weight: bold; }
    .ringkasan .redup { color: #57534E; }
    .sisa-ada { color: #B42318; }
    .sisa-lunas { color: #067647; }
    .kelebihan { font-size: 8pt; color: #B54708; text-align: right; }
    .dp-minimal { font-size: 8pt; color: #44403C; text-align: right; }

    .kotak-bayar { background: #F5F5F4; padding: 4mm; border-radius: 3pt; }
    .rekening td { padding: 0 0 1pt 0; vertical-align: middle; }
    .rekening .bank { width: 12mm; font-weight: bold; }
    .rekening .norek { font-size: 9.5pt; font-weight: bold; }
    .qris-gambar { width: 34mm; }
    .qris-bingkai { background: #FFFFFF; padding: 2mm; width: 34mm; }
    .qris-teks { padding-left: 3mm; font-size: 8pt; line-height: 1.25; vertical-align: middle; }

    .keterangan { font-size: 8.5pt; line-height: 1.3; }
    .ttd-area { height: 24mm; position: relative; }
    .ttd-kosong { height: 24mm; border-bottom: 0.75pt solid #D6D3D1; margin: 0 6mm; }
    .ttd-nama { font-size: 9.5pt; font-weight: bold; }
</style>
</head>
<body>

{{-- ── Kop ─────────────────────────────────────── --}}
<table class="kop">
    <tr>
        <td class="kop-logo">
            <div class="kop-logo-blok">
                @if ($logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $usaha['nama'] }}">
                @endif
            </div>
        </td>
        <td class="kop-identitas">
            <div class="nama-usaha">{{ $usaha['nama'] }}</div>
            <div class="tagline">Paperbag &amp; Packaging</div>
            <div class="bantu">{{ $usaha['alamat'] }}@if ($usaha['wa']) · WA {{ $usaha['wa'] }}@endif</div>
        </td>
        <td class="kop-judul">
            <div class="judul">INVOICE</div>
            <div class="nomor">{{ $nomor }}</div>
        </td>
    </tr>
</table>
<div class="garis-kop"></div>
<div class="jarak"></div>

{{-- ── Kepada + tanggal ─────────────────────────── --}}
<table>
    <tr>
        <td style="width: 55%; padding-right: 4mm;">
            <div class="label-kecil" style="color: #57534E;">Kepada</div>
            <div class="kepada-nama">{{ $kepada['nama'] }}</div>
            @if ($kepada['wa'])
                <div>No. HP {{ $kepada['wa'] }}</div>
            @endif
        </td>
        <td style="width: 45%;">
            <table class="info">
                <tr><td class="lbl">Tanggal order</td><td class="nilai">{{ $tanggal['order'] ?? '–' }}</td></tr>
                <tr>
                    <td class="lbl">Deadline</td>
                    <td class="nilai">
                        {{ $tanggal['deadline'] ?? '–' }}
                        @if ($tanggal['deadline'] && $tanggal['jenisDeadline'])
                            <div style="font-size: 8pt; font-weight: normal; color: #57534E;">{{ $tanggal['jenisDeadline'] }}</div>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="lbl" style="padding-top: 2pt;">Status bayar</td>
                    <td class="nilai" style="padding-top: 2pt;"><span class="badge badge-{{ $statusBayar }}">{{ $labelStatus }}</span></td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<div class="jarak"></div>

{{-- ── Tabel item ───────────────────────────────── --}}
<table class="item">
    <thead>
        <tr>
            <th style="width: 8mm;">No</th>
            <th>Detail produk</th>
            <th class="angka" style="width: 22mm; text-align: right;">Jumlah</th>
            <th class="angka" style="width: 28mm; text-align: right;">Harga sat.</th>
            <th class="angka" style="width: 30mm; text-align: right;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($item as $baris)
            <tr>
                <td>{{ $baris['no'] }}</td>
                <td>
                    <div class="item-judul">{{ $baris['judul'] }}</div>
                    @if ($baris['spek'] !== '')
                        <div class="item-detail">{{ $baris['spek'] }}</div>
                    @endif
                    @if ($baris['catatan'])
                        <div class="item-detail">Catatan: {{ $baris['catatan'] }}</div>
                    @endif
                </td>
                <td class="angka">{{ $baris['jumlah'] }}</td>
                <td class="angka">{{ $baris['harga'] }}</td>
                <td class="angka">{{ $baris['subtotal'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
<div class="jarak"></div>

{{-- ── Penutup: info bayar + ringkasan + keterangan + ttd (satu blok, tidak terbelah) ── --}}
@php
    $adaRekening = count($rekening) > 0;
    $adaInfoBayar = $adaRekening || $qris;
@endphp
<table class="penutup">
    <tr>
        <td>
            <table>
                <tr>
                    <td style="width: 100mm; padding-right: 6mm;">
                        @if ($adaInfoBayar)
                            <div class="kotak-bayar">
                                @if ($adaRekening)
                                    <div class="label-kecil" style="margin-bottom: 1.5mm;">Transfer bank</div>
                                    <table class="rekening">
                                        @foreach ($rekening as $r)
                                            <tr>
                                                <td class="bank">{{ $r['bank'] }}</td>
                                                <td class="norek">{{ $r['nomor'] }}@if (! $atasNamaSama && filled($r['atas_nama'] ?? null))<span style="font-size: 8pt; font-weight: normal;"> a.n. {{ $r['atas_nama'] }}</span>@endif</td>
                                            </tr>
                                        @endforeach
                                    </table>
                                    @if ($atasNamaSama)
                                        <div style="font-size: 8.5pt;">a.n. {{ $atasNamaSama }}</div>
                                    @endif
                                @endif
                                @if ($qris)
                                    <table style="margin-top: {{ $adaRekening ? '3mm' : '0' }};">
                                        <tr>
                                            <td class="qris-gambar">
                                                <div class="qris-bingkai"><img src="{{ $qris['uri'] }}" alt="QRIS" style="width: 30mm; height: 30mm;"></div>
                                            </td>
                                            <td class="qris-teks">
                                                <div class="label-kecil">QRIS</div>
                                                @if ($qrisKeterangan !== '')
                                                    <div>{{ $qrisKeterangan }}</div>
                                                @endif
                                                <div style="color: #44403C;">{{ $qrisPengingat }}</div>
                                            </td>
                                        </tr>
                                    </table>
                                @endif
                            </div>
                        @endif
                    </td>
                    <td style="width: 76mm;">
                        <table class="ringkasan">
                            @foreach ($ringkasan['baris'] as $r)
                                <tr><td>{{ $r['label'] }}</td><td class="nilai">{{ $r['nilai'] }}</td></tr>
                            @endforeach
                            <tr class="garis"><td colspan="2"></td></tr>
                            <tr class="besar"><td>TOTAL TAGIHAN</td><td class="nilai">{{ $ringkasan['total'] }}</td></tr>
                            @foreach ($ringkasan['bayar'] as $r)
                                <tr>
                                    <td class="{{ $r['gaya'] }}">{{ $r['label'] }}</td>
                                    <td class="nilai">{{ $r['nilai'] }}</td>
                                </tr>
                            @endforeach
                            <tr class="garis"><td colspan="2" style="border-top-color: #D6D3D1;"></td></tr>
                            <tr class="besar">
                                <td>SISA TAGIHAN</td>
                                <td class="nilai {{ $ringkasan['sisaLunas'] ? 'sisa-lunas' : 'sisa-ada' }}">{{ $ringkasan['sisa'] }}</td>
                            </tr>
                        </table>
                        @if ($ringkasan['kelebihan'])
                            <div class="kelebihan">{{ $ringkasan['kelebihan'] }}</div>
                        @endif
                        @if ($ringkasan['dpMinimal'])
                            <div class="dp-minimal">{{ $ringkasan['dpMinimal'] }}</div>
                        @endif
                    </td>
                </tr>
            </table>

            <div class="jarak"></div>

            <table>
                <tr>
                    <td style="width: 110mm; padding-right: 10mm;">
                        @if ($keterangan !== '')
                            <div class="label-kecil">Keterangan</div>
                            <div class="keterangan">{!! nl2br(e($keterangan)) !!}</div>
                        @endif
                    </td>
                    <td style="width: 62mm;" class="tengah">
                        <div>Hormat kami,</div>
                        @php
                            $stempel = $penandatangan['stempel'];
                            $ttd = $penandatangan['ttd'];
                        @endphp
                        @if ($stempel || $ttd)
                            <div class="ttd-area">
                                @if ($stempel)
                                    <img src="{{ $stempel['uri'] }}" alt="Stempel"
                                         style="position: absolute; left: 50%; margin-left: -{{ $stempel['lebar'] / 2 }}mm; top: {{ (24 - $stempel['tinggi']) / 2 }}mm; width: {{ $stempel['lebar'] }}mm; height: {{ $stempel['tinggi'] }}mm;">
                                @endif
                                @if ($ttd)
                                    <img src="{{ $ttd['uri'] }}" alt="Tanda tangan"
                                         style="position: absolute; left: 50%; margin-left: -{{ $ttd['lebar'] / 2 }}mm; top: 2mm; width: {{ $ttd['lebar'] }}mm; height: {{ $ttd['tinggi'] }}mm;">
                                @endif
                            </div>
                        @else
                            <div class="ttd-kosong"></div>
                        @endif
                        @if ($penandatangan['nama'] !== '')
                            <div class="ttd-nama">{{ $penandatangan['nama'] }}</div>
                        @endif
                        @if ($penandatangan['jabatan'] !== '')
                            <div class="bantu">{{ $penandatangan['jabatan'] }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>
