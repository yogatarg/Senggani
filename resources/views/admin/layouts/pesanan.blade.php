{{--
    Layout SEMENTARA modul Pesanan (HTML dasar, tanpa gaya).
    Akan diganti frontend sesuai catatan desain 2026-11-18-desain-pesanan-senggani.md.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Pesanan') — Senggani</title>
</head>
<body>
<header>
    <strong>Senggani</strong> ·
    {{ session('admin_nama') }} ({{ session('admin_peran') === 'produksi' ? 'Produksi' : 'Admin' }})
    <nav>
        <a href="{{ route('admin.pesanan.index') }}">Pesanan</a>
        @can('create', \App\Models\Pesanan::class)
            | <a href="{{ route('admin.pesanan.create') }}">Tambah Order</a>
        @endcan
        @can('kelola-pengguna')
            | <a href="{{ route('admin.pengguna.index') }}">Kelola Pengguna</a>
            | <a href="{{ route('admin.dashboard') }}">Katalog (admin lama)</a>
        @endcan
        | <a href="{{ route('admin.password.edit') }}">Ubah Password</a>
        | <form action="{{ route('admin.logout') }}" method="POST" style="display:inline">
            @csrf
            <button type="submit">Keluar</button>
        </form>
    </nav>
    <hr>
</header>

<main>
    @if (session('success'))
        <p role="status"><strong>{{ session('success') }}</strong></p>
    @endif
    @if (session('error'))
        <p role="alert"><strong>{{ session('error') }}</strong></p>
    @endif
    @if ($errors->any())
        <div role="alert">
            <p>Ada {{ $errors->count() }} isian yang perlu diperbaiki:</p>
            <ul>
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>
</body>
</html>
