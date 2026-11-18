{{-- Tombol aksi satu pengguna. $p = Admin, $idSaya = id pengguna yang login. --}}
<div class="d-inline-flex gap-2 flex-wrap justify-content-end">
    <a href="{{ route('admin.pengguna.edit', $p) }}" class="sg-btn sg-btn-sekunder sg-btn-kecil" aria-label="Edit atau reset password {{ $p->nama }}"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>
    @if ($p->id !== $idSaya)
        <form method="POST" action="{{ route('admin.pengguna.aktif', $p) }}" data-kirim
              @if ($p->aktif) data-konfirmasi="Nonaktifkan akun {{ $p->nama }}? Ia tidak bisa masuk lagi sampai diaktifkan." @endif>
            @csrf @method('PATCH')
            @if ($p->aktif)
                <button type="submit" class="sg-btn sg-btn-bahaya sg-btn-kecil" aria-label="Nonaktifkan {{ $p->nama }}">Nonaktifkan</button>
            @else
                <button type="submit" class="sg-btn sg-btn-sekunder sg-btn-kecil" aria-label="Aktifkan {{ $p->nama }}">Aktifkan</button>
            @endif
        </form>
    @else
        <button type="button" class="sg-btn sg-btn-kecil" disabled title="Akun sendiri tidak bisa dinonaktifkan" aria-describedby="ket-akun-sendiri-{{ $p->id }}">Nonaktifkan</button>
        <span class="visually-hidden" id="ket-akun-sendiri-{{ $p->id }}">Akun sendiri tidak bisa dinonaktifkan.</span>
    @endif
</div>
