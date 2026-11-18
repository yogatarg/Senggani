{{-- Badge status pembayaran (kotak bergaris + label "Bayar"). $p = Pesanan --}}
@php
    $ikonBayar = ['belum_dp' => 'fa-wallet', 'dp' => 'fa-coins', 'lunas' => 'fa-circle-check'];
@endphp
<span class="sg-bayar sg-by-{{ $p->status_pembayaran }}"><i class="fa-solid {{ $ikonBayar[$p->status_pembayaran] }}" aria-hidden="true"></i>@unless ($tanpaLabel ?? false)<span class="sg-bayar-label">Bayar</span> @endunless{{ $p->label_status_pembayaran }}</span>
