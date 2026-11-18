@extends('admin.layouts.pesanan')

@section('title', 'Kelola Pengguna')

@php
    $inisialP = fn ($nama) => collect(preg_split('/\s+/', trim((string) $nama)) ?: [])
        ->filter()->take(2)->map(fn ($k) => mb_strtoupper(mb_substr($k, 0, 1)))->implode('') ?: '?';
@endphp

@section('content')
<div class="sg-kepala">
    <div>
        <h1>Kelola Pengguna</h1>
        <p class="sg-kepala-sub">Akun yang tidak dipakai dinonaktifkan, bukan dihapus, supaya riwayat order tetap mencatat namanya.</p>
    </div>
    <div class="sg-kepala-aksi">
        <a href="{{ route('admin.pengguna.create') }}" class="sg-btn sg-btn-utama"><i class="fa-solid fa-plus" aria-hidden="true"></i> Tambah Pengguna</a>
    </div>
</div>

{{-- Desktop & tablet: tabel --}}
<div class="d-none d-md-block table-responsive">
    <table class="sg-tabel">
        <thead>
            <tr><th scope="col">Nama</th><th scope="col">Username</th><th scope="col">Email</th><th scope="col">Peran</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aksi</span></th></tr>
        </thead>
        <tbody>
            @foreach ($pengguna as $p)
                <tr class="{{ $p->aktif ? '' : 'sg-baris-nonaktif' }}">
                    <td>
                        <span class="sg-nama-sel">
                            <span class="sg-avatar" aria-hidden="true">{{ $inisialP($p->nama) }}</span>
                            <span>{{ $p->nama }} @if ($p->id === $idSaya)<span class="sg-chip-kecil fw-normal">Anda</span>@endif</span>
                        </span>
                    </td>
                    <td>{{ $p->username ?? '—' }}</td>
                    <td class="text-break">{{ $p->email ?? '—' }}</td>
                    <td><span class="sg-chip-peran">{{ $daftarPeran[$p->peran->value] }}</span></td>
                    <td><span class="sg-status-akun {{ $p->aktif ? '' : 'sg-nonaktif' }}">{{ $p->aktif ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="text-end">
                        @include('admin.pengguna._aksi', ['p' => $p])
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- HP: kartu --}}
<ul class="sg-kartu-pengguna d-md-none">
    @foreach ($pengguna as $p)
        <li>
            <div class="sg-nama-sel mb-2 {{ $p->aktif ? '' : 'sg-muted' }}">
                <span class="sg-avatar" aria-hidden="true">{{ $inisialP($p->nama) }}</span>
                <span style="min-width:0">
                    {{ $p->nama }} @if ($p->id === $idSaya)<span class="sg-chip-kecil fw-normal">Anda</span>@endif
                    <span class="d-block sg-kecil sg-muted fw-normal text-break">{{ collect([$p->username, $p->email])->filter()->implode(' · ') }}</span>
                </span>
            </div>
            <p class="d-flex align-items-center gap-3 flex-wrap mb-3">
                <span class="sg-chip-peran">{{ $daftarPeran[$p->peran->value] }}</span>
                <span class="sg-status-akun {{ $p->aktif ? '' : 'sg-nonaktif' }}">{{ $p->aktif ? 'Aktif' : 'Nonaktif' }}</span>
            </p>
            @include('admin.pengguna._aksi', ['p' => $p])
        </li>
    @endforeach
</ul>
@endsection
