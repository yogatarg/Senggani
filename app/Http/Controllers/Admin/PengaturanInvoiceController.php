<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimpanPengaturanInvoiceRequest;
use App\Http\Requests\UnggahGambarInvoiceRequest;
use App\Services\InvoicePdf;
use App\Services\PengaturanInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Halaman Pengaturan Invoice (khusus admin, Gate 'kelola-invoice'; Produksi 403).
 * Gambar disimpan di disk privat dan disajikan lewat route admin (pratinjau), tidak lewat public.
 */
class PengaturanInvoiceController extends Controller
{
    public function __construct(private PengaturanInvoice $pengaturan)
    {
    }

    public function edit()
    {
        Gate::authorize('kelola-invoice');

        $set = $this->pengaturan->semua();

        return view('admin.pengaturan.invoice', [
            'pengaturan'   => $set,
            // jenis => URL pratinjau (null bila belum diunggah)
            'gambar'       => collect(PengaturanInvoice::JENIS_GAMBAR)->mapWithKeys(fn ($label, $jenis) => [
                $jenis => $this->pengaturan->pathGambar($jenis)
                    ? route('admin.pengaturan.invoice.gambar', ['jenis' => $jenis, 'v' => md5((string) $set['file_' . $jenis])])
                    : null,
            ])->all(),
            'labelGambar'  => PengaturanInvoice::JENIS_GAMBAR,
            'maksRekening' => PengaturanInvoice::MAKS_REKENING,
        ]);
    }

    public function update(SimpanPengaturanInvoiceRequest $request)
    {
        $this->pengaturan->simpan($request->dataPengaturan());

        foreach ($request->gambarBaru() as $jenis => $file) {
            $this->simpanGambar($jenis, $file, $jenis);
        }

        if ($request->expectsJson()) {
            return response()->json(['pesan' => 'Pengaturan invoice disimpan.']);
        }

        return redirect()->route('admin.pengaturan.invoice.edit')->with('success', 'Pengaturan invoice disimpan.');
    }

    /** Pratinjau gambar privat (inline). */
    public function gambar(string $jenis)
    {
        Gate::authorize('kelola-invoice');

        $path = $this->pengaturan->pathGambar($jenis);
        abort_unless($path, 404);

        $disk = Storage::disk(PengaturanInvoice::DISK);

        return $disk->response($path, $jenis . '.' . pathinfo($path, PATHINFO_EXTENSION), [
            'Content-Type'           => $disk->mimeType($path) ?: 'application/octet-stream',
            'Cache-Control'          => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function unggah(UnggahGambarInvoiceRequest $request, string $jenis)
    {
        $this->simpanGambar($jenis, $request->file('gambar'), 'gambar');

        $pesan = PengaturanInvoice::JENIS_GAMBAR[$jenis] . ' disimpan.';

        if ($request->expectsJson()) {
            return response()->json([
                'pesan' => $pesan,
                'url'   => route('admin.pengaturan.invoice.gambar', ['jenis' => $jenis, 'v' => md5((string) $this->pengaturan->pathGambar($jenis))]),
            ]);
        }

        return redirect()->route('admin.pengaturan.invoice.edit')->with('success', $pesan);
    }

    public function hapus(Request $request, string $jenis)
    {
        Gate::authorize('kelola-invoice');

        $this->pengaturan->hapusGambar($jenis);

        $pesan = PengaturanInvoice::JENIS_GAMBAR[$jenis] . ' dihapus.';

        if ($request->expectsJson()) {
            return response()->json(['pesan' => $pesan]);
        }

        return redirect()->route('admin.pengaturan.invoice.edit')->with('success', $pesan);
    }

    /** Contoh PDF (inline, tab baru) memakai pengaturan yang sudah disimpan. */
    public function contoh(InvoicePdf $invoice)
    {
        Gate::authorize('kelola-invoice');

        $pesanan = $invoice->pesananContoh();

        try {
            $pdf = $invoice->buat($pesanan);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.pengaturan.invoice.edit')
                ->with('error', 'Contoh invoice gagal dibuat. Coba lagi, atau hubungi pengelola bila berulang.');
        }

        $respons = $pdf->stream(InvoicePdf::namaFile($pesanan));
        $respons->headers->set('Cache-Control', 'private, no-store');

        return $respons;
    }

    /** Kegagalan GD diubah menjadi galat validasi pada $field (bukan halaman 500). */
    private function simpanGambar(string $jenis, $file, string $field): void
    {
        try {
            $this->pengaturan->simpanGambar($jenis, $file);
        } catch (RuntimeException $e) {
            report($e);

            throw ValidationException::withMessages([
                $field => $file->getClientOriginalName() . ' gagal diproses: ' . $e->getMessage(),
            ]);
        }
    }
}
