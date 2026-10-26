{{-- Halaman 500 mandiri: tanpa layout, tanpa query DB; nomor WA dari config dengan fallback aman --}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#4c0404">
    <title>Terjadi Gangguan | Senggani Paperbag &amp; Packaging</title>
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: #fff;
            color: #333;
            text-align: center;
            padding: 24px;
            box-sizing: border-box;
        }

        main {
            max-width: 520px;
        }

        .kode {
            font-size: 4.5rem;
            font-weight: 700;
            color: #4c0404;
            margin: 0 0 8px;
            line-height: 1;
        }

        h1 {
            font-size: 1.5rem;
            color: #4c0404;
            margin: 0 0 12px;
        }

        p {
            color: #6c757d;
            line-height: 1.6;
            margin: 0 0 24px;
        }

        .aksi {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
        }

        a {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 999px;
            font-weight: 600;
            text-decoration: none;
        }

        a:focus-visible {
            outline: 3px solid #d4a853;
            outline-offset: 2px;
        }

        .utama {
            background: #4c0404;
            color: #fff;
        }

        .wa {
            background: #198754;
            color: #fff;
        }
    </style>
</head>

<body>
    <main>
        <p class="kode" aria-hidden="true">500</p>
        <h1>Maaf, sedang ada gangguan</h1>
        <p>Halaman ini belum bisa ditampilkan. Coba muat ulang beberapa saat lagi, atau hubungi kami lewat WhatsApp.</p>
        <div class="aksi">
            <a class="utama" href="/">Ke Beranda</a>
            {{-- config() hanya membaca nilai yang sudah dimuat di memori (tanpa DB/IO), jadi aman di halaman error;
                 fallback angka bila config kosong/tidak valid --}}
            @php($nomorWa500 = preg_replace('/\D/', '', (string) (rescue(fn () => config('usaha.whatsapp'), '', false) ?: '')) ?: '6283829208316')
            <a class="wa" href="https://wa.me/{{ $nomorWa500 }}" target="_blank" rel="noopener">Chat WhatsApp</a>
        </div>
    </main>
</body>

</html>
