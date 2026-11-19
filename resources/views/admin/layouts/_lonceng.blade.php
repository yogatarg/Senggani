{{--
    Tombol lonceng notifikasi deadline (top bar HP & profil sidebar desktop). Membuka #panelNotif.
    $notifDeadline dari view composer (lihat App\Services\NotifikasiDeadline). Angka diperbarui JS tiap 60 detik.
--}}
@php
    $nTotal = (int) ($notifDeadline['total'] ?? 0);
    $nLewat = (int) ($notifDeadline['lewat'] ?? 0);
    $labelLonceng = $nTotal === 0
        ? 'Notifikasi deadline, tidak ada yang mendesak'
        : "Notifikasi deadline: {$nTotal} pesanan mendesak" . ($nLewat ? ", {$nLewat} lewat deadline" : '');
@endphp
<button type="button" class="sg-lonceng" data-bs-toggle="modal" data-bs-target="#panelNotif" aria-haspopup="dialog" aria-expanded="false"
        aria-controls="panelNotif" aria-label="{{ $labelLonceng }}" data-lonceng>
    <i class="fa-solid fa-bell" aria-hidden="true"></i>
    <span class="sg-lonceng-angka {{ $nLewat ? 'sg-lewat' : '' }}" aria-hidden="true" data-lonceng-angka @if ($nTotal === 0) hidden @endif>{{ $nTotal > 99 ? '99+' : $nTotal }}</span>
</button>
