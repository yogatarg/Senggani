<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Masuk — Senggani Paperbag &amp; Packaging</title>
    <link rel="icon" href="{{ asset('favicon-32.png') }}" sizes="32x32">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/pesanan.css') }}?v={{ filemtime(public_path('css/pesanan.css')) }}" rel="stylesheet">
</head>
<body class="sg">
<main class="sg-login-tubuh">
    <div class="sg-login">
        <div class="sg-sidebar-logo">
            <img src="{{ asset('images/logo.png') }}" alt="Senggani Paperbag &amp; Packaging" width="123" height="24">
        </div>
        <h1>Masuk</h1>
        <p class="sg-muted sg-kecil mt-1 mb-4">Sistem pesanan & katalog Senggani</p>

        @if (session('error'))
            <div class="sg-alert sg-alert-galat" role="alert">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <p>{{ session('error') }}</p>
            </div>
        @endif
        @if (session('success'))
            <div class="sg-alert sg-alert-sukses" role="status">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        <form action="{{ route('admin.login.post') }}" method="POST" data-kirim novalidate>
            @csrf
            <div class="sg-field">
                <label class="sg-label" for="f-login">Email atau username</label>
                <input type="text" id="f-login" name="login" value="{{ old('login') }}"
                       class="form-control @error('login') is-invalid @enderror"
                       autocomplete="username" autocapitalize="none" spellcheck="false" required data-fokus-desktop
                       @error('login') aria-invalid="true" aria-describedby="g-login" @enderror>
                @include('admin.layouts._galat', ['k' => 'login'])
            </div>
            <div class="sg-field">
                <label class="sg-label" for="f-password">Password</label>
                <div class="sg-sandi">
                    <input type="password" id="f-password" name="password" autocomplete="current-password" required
                           class="form-control @error('password') is-invalid @enderror"
                           @error('password') aria-invalid="true" aria-describedby="g-password" @enderror>
                    <button type="button" data-tampil-sandi aria-pressed="false" aria-label="Tampilkan password"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                </div>
                @include('admin.layouts._galat', ['k' => 'password'])
            </div>
            <button type="submit" class="sg-btn sg-btn-utama sg-btn-penuh mt-2" data-teks-kirim="Masuk…">Masuk</button>
        </form>
        <p class="sg-muted sg-kecil text-center mt-3 mb-0">Lupa password? Hubungi admin.</p>
    </div>
</main>
<script src="{{ asset('js/pesanan.js') }}?v={{ filemtime(public_path('js/pesanan.js')) }}"></script>
</body>
</html>
