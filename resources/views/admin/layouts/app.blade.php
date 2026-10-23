<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — Senggani Kemasan</title>

    {{-- AdminLTE CSS --}}
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/dist/css/adminlte.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="hold-transition sidebar-mini">
    <div class="wrapper">

        {{-- NAVBAR --}}
        <nav class="main-header navbar navbar-expand navbar-white navbar-light">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
                </li>
            </ul>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="{{ url('/') }}" target="_blank">
                        <i class="fas fa-globe"></i> Lihat Website
                    </a>
                </li>
            </ul>
        </nav>

        {{-- SIDEBAR --}}
        <aside class="main-sidebar sidebar-dark-primary elevation-4">
            <a href="{{ route('admin.dashboard') }}" class="brand-link text-center">
                <span class="brand-text font-weight-bold">Senggani Kemasan</span>
            </a>

            <div class="sidebar">
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview">

                        <li class="nav-item">
                            <a href="{{ route('admin.dashboard') }}"
                                class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-tachometer-alt"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="{{ route('admin.banner.index') }}"
                                class="nav-link {{ request()->routeIs('admin.banner*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-image"></i>
                                <p>Banner</p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="{{ route('admin.kategori-produk.index') }}"
                                class="nav-link {{ request()->routeIs('admin.kategori-produk*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-tags"></i>
                                <p>Kategori Produk</p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="{{ route('admin.produk.index') }}"
                                class="nav-link {{ request()->routeIs('admin.produk*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-box"></i>
                                <p>Produk</p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="{{ route('admin.bahan.index') }}"
                                class="nav-link {{ request()->routeIs('admin.bahan*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-layer-group"></i>
                                <p>Master Bahan</p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="{{ route('admin.finishing.index') }}"
                                class="nav-link {{ request()->routeIs('admin.finishing*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-paint-brush"></i>
                                <p>Master Finishing</p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <form action="{{ route('admin.logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="nav-link btn btn-link text-danger"
                                    style="width:100%; text-align:left;">
                                    <i class="nav-icon fas fa-sign-out-alt"></i>
                                    <p>Logout</p>
                                </button>
                            </form>
                        </li>

                        <li class="nav-item">
                            <span class="nav-link">
                                <i class="fas fa-user-circle"></i>
                                {{ session('admin_nama') }}
                            </span>
                        </li>

                    </ul>
                </nav>
            </div>
        </aside>

        {{-- CONTENT --}}
        <div class="content-wrapper">
            <div class="content-header">
                <div class="container-fluid">
                    <h1 class="m-0">@yield('page_title', 'Dashboard')</h1>
                </div>
            </div>

            <div class="content">
                <div class="container-fluid">

                    {{-- Alert Sukses --}}
                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            {{ session('success') }}
                        </div>
                    @endif

                    {{-- Alert Error --}}
                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <i class="fas fa-exclamation-circle me-1"></i> {{ session('error') }}
                        </div>
                    @endif

                    @yield('content')
                </div>
            </div>
        </div>

        <footer class="main-footer text-center">
            <strong>Senggani Kemasan</strong> &copy; {{ date('Y') }}
        </footer>

    </div>

    {{-- Scripts --}}
    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/adminlte/dist/js/adminlte.min.js') }}"></script>
    @stack('scripts')
</body>

</html>
