<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pesanan;
use App\Services\InvoicePdf;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Unduh invoice PDF SPP (khusus admin; status Menunggu DP s.d. Selesai, lihat PesananPolicy::unduhInvoice).
 */
class InvoiceController extends Controller
{
    public function unduh(Pesanan $pesanan, InvoicePdf $invoice)
    {
        Gate::authorize('unduhInvoice', $pesanan);

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
