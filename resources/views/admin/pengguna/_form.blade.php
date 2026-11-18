{{-- Field pengguna. Variabel: $pengguna, $daftarPeran, $akunSendiri (opsional). --}}
<p><label>Nama* <input type="text" name="nama" value="{{ old('nama', $pengguna->nama) }}" maxlength="100" required></label></p>
<p><label>Username <input type="text" name="username" value="{{ old('username', $pengguna->username) }}" maxlength="50" autocapitalize="none"></label>
    (huruf kecil/angka/titik/strip, untuk login)</p>
<p><label>Email (opsional) <input type="email" name="email" value="{{ old('email', $pengguna->email) }}" maxlength="255"></label></p>
<p>Peran*:
    @foreach ($daftarPeran as $nilai => $label)
        <label>
            <input type="radio" name="peran" value="{{ $nilai }}"
                   @checked(old('peran', $pengguna->peran?->value) === $nilai)
                   @disabled(($akunSendiri ?? false) && $pengguna->peran?->value !== $nilai)>
            {{ $label }}
        </label>
    @endforeach
    @if ($akunSendiri ?? false)
        <input type="hidden" name="peran" value="{{ $pengguna->peran->value }}">
        (peran akun sendiri tidak bisa diubah)
    @endif
</p>
