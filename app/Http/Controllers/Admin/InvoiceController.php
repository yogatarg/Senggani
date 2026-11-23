<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pesanan;
use App\Services\InvoicePdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Unduh invoice PDF SPP (khusus admin; status Menunggu DP s.d. Selesai, lihat PesananPolicy::unduhInvoice).
 * Admin yang membuka URL invoice SPP berstatus Hitung Harga/Tidak Jadi/Dibatalkan dialihkan ke detail
 * SPP-nya dengan pesan (JSON tetap 403). Produksi tetap ditolak lewat GalatPanelAdmin.
 */
class InvoiceController extends Controller
{
    public function unduh(Request $request, Pesanan $pesanan, InvoicePdf $invoice)
    {
        $izin = Gate::inspect('unduhInvoice', $pesanan);

        if ($izin->denied() && Auth::user()?->isAdmin() && ! $request->expectsJson()) {
            return redirect()->route('admin.pesanan.show', $pesanan)->with('error', $izin->message());
        }

        $izin->authorize();

        try {
            $pdf = $invoice->buat($pesanan);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.pesanan.show', $pesanan)
                ->with('error', 'Invoice gagal dibuat. Coba lagi, atau hubungi pengelola bila berulang.');
        }

        $respons = $pdf->download(InvoicePdf::namaFile($pesanan));
        $respons->headers->set('Cache-Control', 'private, no-store');
        $respons->headers->set('X-Content-Type-Options', 'nosniff');

        return $respons;
    }
}
