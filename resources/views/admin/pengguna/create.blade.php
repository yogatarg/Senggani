@extends('admin.layouts.pesanan')

@section('title', 'Tambah Pengguna')
@section('kembali', route('admin.pengguna.index'))

@section('content')
<div class="sg-kepala">
    <div>
        <a href="{{ route('admin.pengguna.index') }}" class="sg-remah d-none d-lg-inline-flex"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Kelola Pengguna</a>
        <h1>Tambah Pengguna</h1>
    </div>
</div>

<div class="sg-kartu sg-sempit">
    <form method="POST" action="{{ route('admin.pengguna.store') }}" data-kirim>
        @csrf
        @include('admin.pengguna._form')
        @include('admin.layouts._sandi', ['nama' => 'password', 'label' => 'Password awal', 'autocomplete' => 'new-password', 'bantu' => 'Minimal 8 karakter. Sampaikan sendiri ke penggunanya.', 'minimal' => 8])
        @include('admin.layouts._sandi', ['nama' => 'password_confirmation', 'label' => 'Ulangi password', 'autocomplete' => 'new-password'])
        <div class="sg-form-aksi">
            <button type="submit" class="sg-btn sg-btn-utama" data-teks-kirim="Menyimpan…">Simpan</button>
            <a href="{{ route('admin.pengguna.index') }}" class="sg-btn sg-btn-sekunder">Batal</a>
        </div>
    </form>
</div>
@endsection
