@extends('admin.layouts.pesanan')

@section('title', 'Kelola Pengguna')

@section('content')
<h1>Kelola Pengguna</h1>
<p><a href="{{ route('admin.pengguna.create') }}">+ Tambah Pengguna</a></p>

<table border="1" cellpadding="4">
    <thead>
        <tr><th>Nama</th><th>Username</th><th>Email</th><th>Peran</th><th>Status</th><th>Aksi</th></tr>
    </thead>
    <tbody>
        @foreach ($pengguna as $p)
            <tr>
                <td>{{ $p->nama }} @if ($p->id === $idSaya) (Anda) @endif</td>
                <td>{{ $p->username ?? '—' }}</td>
                <td>{{ $p->email ?? '—' }}</td>
                <td>{{ $daftarPeran[$p->peran->value] }}</td>
                <td>{{ $p->aktif ? 'Aktif' : 'Nonaktif' }}</td>
                <td>
                    <a href="{{ route('admin.pengguna.edit', $p) }}">Edit / reset password</a>
                    @if ($p->id !== $idSaya)
                        <form method="POST" action="{{ route('admin.pengguna.aktif', $p) }}" style="display:inline">
                            @csrf @method('PATCH')
                            @if ($p->aktif)
                                <button type="submit" onclick="return confirm('Nonaktifkan akun {{ $p->nama }}? Ia tidak bisa masuk lagi sampai diaktifkan.')">Nonaktifkan</button>
                            @else
                                <button type="submit">Aktifkan</button>
                            @endif
                        </form>
                    @else
                        <button type="button" disabled title="Akun sendiri tidak bisa dinonaktifkan">Nonaktifkan</button>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
@endsection
