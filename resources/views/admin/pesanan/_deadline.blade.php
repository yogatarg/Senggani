{{-- Chip deadline. $p = Pesanan. Warna hanya untuk order yang belum selesai produksi (accessor penanda_deadline). --}}
@php
    $tglDl = $p->deadline?->translatedFormat('j M');
    $hari = $p->hari_menuju_deadline;
    if ($p->penanda_deadline === 'lewat') {
        $jenisDl = 'lewat'; $ikonDl = 'fa-circle-exclamation';
        $teksDl = 'Lewat ' . abs($hari) . ' hari · ' . $tglDl;
    } elseif ($p->penanda_deadline === 'mendekat') {
        $jenisDl = 'mendekat'; $ikonDl = 'fa-clock';
        $teksDl = $hari === 0 ? 'Hari ini · ' . $tglDl : ($hari === 1 ? 'Besok · ' . $tglDl : $hari . ' hari lagi · ' . $tglDl);
    } else {
        $jenisDl = 'netral'; $ikonDl = 'fa-calendar';
        $teksDl = 'Deadline ' . $tglDl;
    }
    $teksDlPanjang = ($jenisDl !== 'netral' && ($panjang ?? false)) ? $teksDl . ' (deadline)' : $teksDl;
@endphp
@if ($p->deadline)
    <span class="sg-deadline sg-deadline-{{ $jenisDl }}"><i class="fa-solid {{ $ikonDl }}" aria-hidden="true"></i>{{ $teksDlPanjang }}</span>
@endif
