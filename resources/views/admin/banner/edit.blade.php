@extends('admin.layouts.pesanan')

@section('title', 'Edit Banner')
@section('kembali', route('admin.banner.index'))
@section('tanpa_bottomnav', '1')

@section('content')
<div class="sg-kepala">
    <div>
        <a href="{{ route('admin.banner.index') }}" class="sg-remah d-none d-lg-inline-flex" data-batal-form><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Banner</a>
        <h1>Edit banner</h1>
        <p class="sg-kepala-sub" style="overflow-wrap:anywhere">{{ $banner->judul }}</p>
    </div>
</div>

@include('admin.banner._form')
@endsection
