@extends('admin.layouts.pesanan')

@section('title', 'Edit ' . $customer->kode)
@section('kembali', route('admin.customer.show', $customer))

{{-- Variabel: $customer. Field: nama, nomor_whatsapp, alamat, kota. --}}
@php
    $waLama = old('nomor_whatsapp', $customer->nomor_whatsapp);
    if (! old('nomor_whatsapp') && is_string($waLama) && str_starts_with($waLama, '62')) { $waLama = '0' . substr($waLama, 2); }
@endphp

@section('content')
<div class="sg-kepala">
    <div>
        <a href="{{ route('admin.customer.show', $customer) }}" class="sg-remah d-none d-lg-inline-flex"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> {{ $customer->kode }}</a>
        <h1>Edit Customer {{ $customer->kode }}</h1>
        <p class="sg-kepala-sub">SPP yang sudah ada tetap memakai data saat SPP dibuat.</p>
    </div>
</div>

<section class="sg-kartu sg-sempit">
    <form method="POST" action="{{ route('admin.customer.update', $customer) }}" data-kirim>
        @csrf @method('PUT')
        <div class="sg-field">
            <label class="sg-label" for="f-nama">Nama <span class="sg-wajib" aria-hidden="true">*</span></label>
            <input type="text" id="f-nama" name="nama" value="{{ old('nama', $customer->nama) }}" maxlength="150" required autocomplete="off"
                   class="form-control @error('nama') is-invalid @enderror" @error('nama') aria-invalid="true" aria-describedby="g-nama" @enderror>
            @include('admin.layouts._galat', ['k' => 'nama'])
        </div>
        <div class="sg-field">
            <label class="sg-label" for="f-nomor_whatsapp">No. WhatsApp <span class="sg-wajib" aria-hidden="true">*</span></label>
            <input type="tel" inputmode="tel" id="f-nomor_whatsapp" name="nomor_whatsapp" value="{{ $waLama }}" maxlength="30" required autocomplete="off"
                   class="form-control sg-angka @error('nomor_whatsapp') is-invalid @enderror" @error('nomor_whatsapp') aria-invalid="true" aria-describedby="g-nomor_whatsapp" @enderror>
            @include('admin.layouts._galat', ['k' => 'nomor_whatsapp'])
        </div>
        <div class="sg-field">
            <label class="sg-label" for="f-alamat">Alamat</label>
            <textarea id="f-alamat" name="alamat" rows="2" maxlength="2000" class="form-control @error('alamat') is-invalid @enderror">{{ old('alamat', $customer->alamat) }}</textarea>
            @include('admin.layouts._galat', ['k' => 'alamat'])
        </div>
        <div class="sg-field">
            <label class="sg-label" for="f-kota">Kota</label>
            <input type="text" id="f-kota" name="kota" value="{{ old('kota', $customer->kota) }}" maxlength="100" class="form-control @error('kota') is-invalid @enderror">
            @include('admin.layouts._galat', ['k' => 'kota'])
        </div>
        <div class="sg-form-aksi">
            <button type="submit" class="sg-btn sg-btn-utama" data-teks-kirim="Menyimpan…">Simpan Perubahan</button>
            <a href="{{ route('admin.customer.show', $customer) }}" class="sg-btn sg-btn-sekunder">Batal</a>
        </div>
    </form>
</section>
@endsection
