<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin — Senggani Paperbag &amp; Packaging</title>
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/dist/css/adminlte.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #343a40;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-box {
            width: 400px;
        }
        .login-logo a {
            color: #fff;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .login-logo span {
            color: #ffc107;
        }
    </style>
</head>
<body data-login-register-auto-height="false">

<div class="login-box">

    {{-- Logo --}}
    <div class="login-logo text-center mb-3">
        <a href="#">Senggani <span>Paperbag &amp; Packaging</span></a>
        <p class="text-muted" style="font-size: 0.85rem;">Admin Dashboard</p>
    </div>

    <div class="card">
        <div class="card-body">

            <p class="login-box-msg text-center font-weight-bold">
                Masuk ke Panel Admin
            </p>

            {{-- Alert Error --}}
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="fas fa-exclamation-triangle"></i>
                    {{ session('error') }}
                </div>
            @endif

            {{-- Alert Sukses (setelah logout) --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('admin.login.post') }}" method="POST">
                @csrf

                {{-- Email --}}
                <div class="input-group mb-3">
                    <input type="email" name="email"
                           class="form-control @error('email') is-invalid @enderror"
                           placeholder="Email Admin"
                           value="{{ old('email') }}"
                           autofocus required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-envelope"></span>
                        </div>
                    </div>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Password --}}
                <div class="input-group mb-4">
                    <input type="password" name="password"
                           class="form-control @error('password') is-invalid @enderror"
                           placeholder="Kata Sandi"
                           required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i> Masuk
                </button>
            </form>

        </div>
    </div>

</div>

<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/adminlte/dist/js/adminlte.min.js') }}"></script>
</body>
</html>