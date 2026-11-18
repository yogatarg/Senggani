{{-- Field password dengan tombol tampilkan. $nama, $label, $autocomplete, $bantu & $minimal (opsional). --}}
@php
    $deskripsi = trim((isset($bantu) ? 'bantu-' . $nama : '') . ' ' . ($errors->has($nama) ? 'g-' . $nama : ''));
@endphp
<div class="sg-field">
    <label class="sg-label" for="f-{{ $nama }}">{{ $label }} <span class="sg-wajib" aria-hidden="true">*</span></label>
    <div class="sg-sandi">
        <input type="password" id="f-{{ $nama }}" name="{{ $nama }}" autocomplete="{{ $autocomplete }}" required
               @isset($minimal) minlength="{{ $minimal }}" @endisset
               class="form-control @error($nama) is-invalid @enderror"
               @if ($deskripsi !== '') aria-describedby="{{ $deskripsi }}" @endif
               @error($nama) aria-invalid="true" @enderror>
        <button type="button" data-tampil-sandi aria-pressed="false" aria-label="Tampilkan password"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
    </div>
    @include('admin.layouts._galat', ['k' => $nama])
    @isset($bantu)<p class="sg-bantu" id="bantu-{{ $nama }}">{{ $bantu }}</p>@endisset
</div>
