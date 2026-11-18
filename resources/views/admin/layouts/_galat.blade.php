{{-- Pesan error satu field. $k = kunci field. id: g-<kunci>. --}}
@error($k)<p class="sg-galat" id="g-{{ str_replace('.', '-', $k) }}"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>@enderror
