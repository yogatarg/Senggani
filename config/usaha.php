<?php

/*
|--------------------------------------------------------------------------
| Data Usaha (sumber tunggal)
|--------------------------------------------------------------------------
|
| Dipakai tampilan (header, footer, tombol WhatsApp, data terstruktur/SEO),
| sitemap, dan llms.txt. Ubah di sini atau lewat .env, jangan ditulis ulang
| di view. Akses: config('usaha.nama'), config('usaha.alamat.kota'), dst.
|
*/

// Nilai .env teks yang kosong dianggap tidak diisi (kembali ke default).
// Koordinat: kunci tidak ada di .env -> default; diisi angka -> angka itu;
// diisi kosong/bukan angka (mis. "USAHA_LAT=") -> null (geo disembunyikan, bukan 0.0).
$teks  = fn (string $kunci, ?string $bawaan = null) => filled($nilai = env($kunci)) ? (string) $nilai : $bawaan;
$angka = fn (string $kunci, ?float $bawaan = null) => ($nilai = env($kunci)) === null
    ? $bawaan
    : (is_numeric($nilai) ? (float) $nilai : null);

return [

    'nama' => $teks('USAHA_NAMA', 'Senggani Paperbag & Packaging'),

    'deskripsi' => $teks(
        'USAHA_DESKRIPSI',
        'Spesialis kemasan custom untuk bisnis — paperbag, dus, dan box — dari Yogyakarta.'
    ),

    // Format tampilan/tautan tel: (dengan +kode negara)
    'telepon' => $teks('USAHA_TELEPON', '+6283829208316'),

    // Nomor untuk https://wa.me/<nomor> (angka saja, tanpa + atau 0 di depan)
    'whatsapp' => $teks('USAHA_WHATSAPP', '6283829208316'),

    'email' => $teks('USAHA_EMAIL', 'sengganipaperbag@gmail.com'),

    'alamat' => [
        'jalan'     => 'Pilahan, RT 40, RW 12',
        'kelurahan' => 'Rejowinangun',
        'kecamatan' => 'Kotagede',
        'kota'      => 'Yogyakarta',
        'provinsi'  => 'DI Yogyakarta',
        'kode_pos'  => '55171',
        'negara'    => 'ID', // kode ISO 3166-1 alpha-2
    ],

    // Koordinat lokasi (desimal), dari titik listing resmi Google Maps. null = tidak ditampilkan.
    'geo' => [
        'lat' => $angka('USAHA_LAT', -7.8158176),
        'lng' => $angka('USAHA_LNG', 110.4000244),
    ],

    // Tautan listing Google Maps (Google Business Profile). Dipakai untuk link alamat
    // di footer, hasMap dan sameAs di data terstruktur, serta llms.txt.
    'peta' => $teks('USAHA_PETA', 'https://maps.app.goo.gl/kPMTD1tzhLQC5zTD9'),

    // Jam buka format schema.org openingHours. Hari yang tidak disebut = libur
    // (Minggu libur; footer menampilkan "Minggu Libur" otomatis).
    'jam_buka' => [
        'Mo-Fr 08:00-20:00',
        'Sa 08:00-18:00',
    ],

    // URL profil media sosial (dipakai juga untuk sameAs di data terstruktur)
    'sosial' => [
        'https://www.instagram.com/sengganipaperbag/',
    ],

    'area_layanan' => ['Yogyakarta', 'Indonesia'],

];
