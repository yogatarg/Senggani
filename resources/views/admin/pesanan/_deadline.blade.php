{{--
    Chip deadline. $p = Pesanan. Warna hanya untuk SPP yang belum selesai produksi (accessor penanda_deadline).
    Jenis deadline (Siap/Kirim) ikut tampil: "Deadline kirim 30 Nov", "Besok · siap 30 Nov".
--}}
@php
    $tglDl = $p->deadline?->translatedFormat('j M');
    $jenisTeks = $p->label_jenis_deadline ? mb_strtolower($p->label_jenis_deadline) . ' ' : '';
    $hari = $p->hari_menuju_deadline;
    if ($p->penanda_deadline === 'lewat') {
        $jenisDl = 'lewat'; $ikonDl = 'fa-circle-exclamation';
        $teksDl = 'Lewat ' . abs($hari) . ' hari · ' . $jenisTeks . $tglDl;
    } elseif ($p->penanda_deadline === 'mendekat') {
        $jenisDl = 'mendekat'; $ikonDl = 'fa-clock';
        $teksDl = ($hari === 0 ? 'Hari ini' : ($hari === 1 ? 'Besok' : $hari . ' hari lagi')) . ' · ' . $jenisTeks . $tglDl;
    } else {
        $jenisDl = 'netral'; $ikonDl = 'fa-calendar';
        $teksDl = 'Deadline ' . $jenisTeks . $tglDl;
    }
    $teksDlPanjang = ($jenisDl !== 'netral' && ($panjang ?? false)) ? $teksDl . ' (deadline)' : $teksDl;
@endphp
@if ($p->deadline)
    <span class="sg-deadline sg-deadline-{{ $jenisDl }}"><i class="fa-solid {{ $ikonDl }}" aria-hidden="true"></i>{{ $teksDlPanjang }}</span>
@endif
