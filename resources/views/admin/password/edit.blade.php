@extends('admin.layouts.pesanan')

@section('title', 'Ubah Password')

@section('content')
<div class="sg-kepala">
    <div>
        <h1>Ubah Password</h1>
        <p class="sg-kepala-sub">Akun: {{ $pengguna->nama }} ({{ $pengguna->username ?? $pengguna->email }})</p>
    </div>
</div>

<div class="sg-kartu sg-sempit">
    <form method="POST" action="{{ route('admin.password.update') }}" data-kirim>
        @csrf @method('PUT')
        @include('admin.layouts._sandi', ['nama' => 'password_lama', 'label' => 'Password saat ini', 'autocomplete' => 'current-password'])
        @include('admin.layouts._sandi', ['nama' => 'password', 'label' => 'Password baru', 'autocomplete' => 'new-password', 'bantu' => 'Minimal 8 karakter.', 'minimal' => 8])
        @include('admin.layouts._sandi', ['nama' => 'password_confirmation', 'label' => 'Ulangi password baru', 'autocomplete' => 'new-password'])
        <div class="sg-form-aksi">
            <button type="submit" class="sg-btn sg-btn-utama" data-teks-kirim="Menyimpan…">Simpan password</button>
            <a href="{{ route('admin.pesanan.index') }}" class="sg-btn sg-btn-sekunder">Batal</a>
        </div>
    </form>
</div>
@endsection
