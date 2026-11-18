{{-- Field pengguna. Variabel: $pengguna, $daftarPeran, $akunSendiri (opsional). --}}
@php
    $keteranganPeran = [
        'admin'    => 'Input SPP, pembayaran, pengguna, katalog',
        'produksi' => 'Lihat SPP, ubah status, tulis catatan',
    ];
    $akunSendiri = $akunSendiri ?? false;
@endphp
<div class="sg-field">
    <label class="sg-label" for="f-nama">Nama <span class="sg-wajib" aria-hidden="true">*</span></label>
    <input type="text" id="f-nama" name="nama" value="{{ old('nama', $pengguna->nama) }}" maxlength="100" required autocomplete="off"
           class="form-control @error('nama') is-invalid @enderror" @error('nama') aria-invalid="true" aria-describedby="g-nama" @enderror>
    @include('admin.layouts._galat', ['k' => 'nama'])
</div>
<div class="sg-dua">
    <div class="sg-field">
        <label class="sg-label" for="f-username">Username</label>
        <input type="text" id="f-username" name="username" value="{{ old('username', $pengguna->username) }}" maxlength="50" autocapitalize="none" autocomplete="off" spellcheck="false"
               class="form-control @error('username') is-invalid @enderror" aria-describedby="bantu-username{{ $errors->has('username') ? ' g-username' : '' }}" @error('username') aria-invalid="true" @enderror>
        @include('admin.layouts._galat', ['k' => 'username'])
        <p class="sg-bantu" id="bantu-username">Untuk login. Huruf kecil, angka, titik, atau strip.</p>
    </div>
    <div class="sg-field">
        <label class="sg-label" for="f-email">Email <span class="fw-normal sg-muted">(opsional)</span></label>
        <input type="email" id="f-email" name="email" value="{{ old('email', $pengguna->email) }}" maxlength="255" autocomplete="off"
               class="form-control @error('email') is-invalid @enderror" @error('email') aria-invalid="true" aria-describedby="g-email" @enderror>
        @include('admin.layouts._galat', ['k' => 'email'])
    </div>
</div>
<fieldset class="sg-field" id="f-peran">
    <legend class="sg-label">Peran <span class="sg-wajib" aria-hidden="true">*</span></legend>
    <div class="sg-pilih-kartu">
        @foreach ($daftarPeran as $nilai => $label)
            <label>
                <input type="radio" name="peran" value="{{ $nilai }}"
                       @checked(old('peran', $pengguna->peran?->value) === $nilai)
                       @disabled($akunSendiri && $pengguna->peran?->value !== $nilai)>
                <span><strong>{{ $label }}</strong><small>{{ $keteranganPeran[$nilai] ?? '' }}</small></span>
            </label>
        @endforeach
    </div>
    @if ($akunSendiri)
        <input type="hidden" name="peran" value="{{ $pengguna->peran->value }}">
        <p class="sg-bantu">Peran akun sendiri tidak bisa diubah.</p>
    @endif
    @include('admin.layouts._galat', ['k' => 'peran'])
</fieldset>
