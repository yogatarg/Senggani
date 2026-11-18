@extends('admin.layouts.pesanan')

@section('title', 'Edit ' . $pengguna->nama)
@section('kembali', route('admin.pengguna.index'))

@section('content')
<div class="sg-kepala">
    <div>
        <a href="{{ route('admin.pengguna.index') }}" class="sg-remah d-none d-lg-inline-flex"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Kelola Pengguna</a>
        <h1>Edit Pengguna: {{ $pengguna->nama }}</h1>
    </div>
</div>

<div class="d-grid gap-3 sg-sempit">
    <section class="sg-kartu" aria-labelledby="judul-data">
        <h2 class="sg-kartu-judul" id="judul-data">Data pengguna</h2>
        <form method="POST" action="{{ route('admin.pengguna.update', $pengguna) }}" data-kirim>
            @csrf @method('PUT')
            @include('admin.pengguna._form')
            <div class="sg-form-aksi">
                <button type="submit" class="sg-btn sg-btn-utama" data-teks-kirim="Menyimpan…">Simpan Perubahan</button>
                <a href="{{ route('admin.pengguna.index') }}" class="sg-btn sg-btn-sekunder">Batal</a>
            </div>
        </form>
    </section>

    <section class="sg-kartu" aria-labelledby="judul-reset">
        <h2 class="sg-kartu-judul" id="judul-reset">Reset password</h2>
        <p class="sg-muted">Isi password baru lalu sampaikan sendiri ke penggunanya.</p>
        <form method="POST" action="{{ route('admin.pengguna.password', $pengguna) }}" data-kirim>
            @csrf @method('PUT')
            @include('admin.layouts._sandi', ['nama' => 'password', 'label' => 'Password baru', 'autocomplete' => 'new-password', 'bantu' => 'Minimal 8 karakter.', 'minimal' => 8])
            @include('admin.layouts._sandi', ['nama' => 'password_confirmation', 'label' => 'Ulangi password baru', 'autocomplete' => 'new-password'])
            <div class="sg-form-aksi">
                <button type="submit" class="sg-btn sg-btn-sekunder" data-teks-kirim="Menyimpan…"><i class="fa-solid fa-key" aria-hidden="true"></i> Reset password</button>
            </div>
        </form>
    </section>
</div>
@endsection
