{{-- Status kelengkapan produk: lengkap bila bahan > 0 dan finishing > 0. Variabel: $item. --}}
@if ($item->bahan_count > 0 && $item->finishing_count > 0)
    <span class="sg-lengkap"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Lengkap</span>
@else
    <span class="sg-lengkap sg-lengkap-belum"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> Belum lengkap</span>
@endif
