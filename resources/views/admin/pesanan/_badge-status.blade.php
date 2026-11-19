{{-- Badge status produksi (pil berisi + ikon + teks). $status = App\Enums\StatusProduksi --}}
@php
    $ikonStatus = [
        'hitung_harga' => 'fa-calculator', 'menunggu_dp' => 'fa-hourglass-half', 'tidak_jadi' => 'fa-circle-xmark',
        'antrian' => 'fa-hourglass-half', 'proses' => 'fa-print', 'selesai_produksi' => 'fa-circle-check',
        'pengiriman' => 'fa-truck', 'selesai' => 'fa-house-circle-check', 'dibatalkan' => 'fa-ban',
    ];
@endphp
<span class="sg-badge sg-st-{{ $status->value }}"><i class="fa-solid {{ $ikonStatus[$status->value] ?? 'fa-circle' }}" aria-hidden="true"></i>{{ $status->label() }}</span>
