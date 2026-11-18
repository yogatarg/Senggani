<?php

namespace App\Exceptions;

use App\Models\Pesanan;
use App\Models\PesananFile;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Galat 403/404 untuk pengguna yang sudah login di /admin: jangan tampilkan halaman
 * error bawaan/publik. Halaman error publik (pengunjung situs) tidak berubah.
 *
 * - 404 (termasuk SPP yang sudah di-soft-delete): HTML → redirect ke daftar SPP dengan
 *   flash 'error'; JSON → {"pesan": ...} dengan 410 (SPP dihapus) atau 404.
 * - 403: GET HTML → redirect ke daftar SPP dengan flash 'error'; selain itu JSON 403 {"pesan": ...}.
 */
class GalatPanelAdmin
{
    public const PESAN_KHUSUS_ADMIN = 'Halaman ini khusus admin.';

    public const PESAN_AKSI_KHUSUS_ADMIN = 'Aksi ini khusus admin.';

    // Pesan bawaan Laravel/Symfony yang perlu diganti bahasa Indonesia
    private const PESAN_BAWAAN = ['', 'This action is unauthorized.', 'Forbidden', 'Not Found'];

    public static function daftarkan(Exceptions $exceptions): void
    {
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! self::diPanel($request)) {
                return null;
            }

            return match ($e->getStatusCode()) {
                404     => self::tidakDitemukan($e, $request),
                403     => self::ditolak($e, $request),
                default => null,
            };
        });
    }

    /** Area /admin, sudah login, dan bukan halaman tujuan redirect (hindari loop). */
    private static function diPanel(Request $request): bool
    {
        return $request->is('admin', 'admin/*')
            && $request->hasSession()
            && $request->session()->get('admin_logged_in')
            && ! $request->routeIs('admin.pesanan.index', 'admin.login', 'admin.login.post', 'admin.logout');
    }

    private static function tidakDitemukan(HttpExceptionInterface $e, Request $request)
    {
        $model  = $e instanceof \Throwable && $e->getPrevious() instanceof ModelNotFoundException ? $e->getPrevious() : null;
        $status = 404;

        if ($model && $model->getModel() === Pesanan::class) {
            $id      = (string) ($model->getIds()[0] ?? '');
            $dihapus = ctype_digit($id) ? Pesanan::onlyTrashed()->find((int) $id) : null;

            if ($dihapus) {
                $pesan  = "{$dihapus->nomor_spp_teks} sudah dihapus oleh admin.";
                $status = 410;
            } else {
                $pesan = 'SPP tidak ditemukan.';
            }
        } elseif ($model && $model->getModel() === PesananFile::class) {
            $pesan = 'Gambar tidak ditemukan. Mungkin sudah dihapus.';
        } else {
            $pesan = 'Halaman atau data tidak ditemukan.';
        }

        if ($request->expectsJson()) {
            return response()->json(['pesan' => $pesan], $status);
        }

        return redirect()->route('admin.pesanan.index')->with('error', $pesan);
    }

    private static function ditolak(HttpExceptionInterface $e, Request $request)
    {
        $pesan = $e instanceof \Throwable ? trim($e->getMessage()) : '';
        $get   = $request->isMethod('GET') || $request->isMethod('HEAD');

        if (in_array($pesan, self::PESAN_BAWAAN, true)) {
            $pesan = $get ? self::PESAN_KHUSUS_ADMIN : self::PESAN_AKSI_KHUSUS_ADMIN;
        }

        if ($get && ! $request->expectsJson()) {
            return redirect()->route('admin.pesanan.index')->with('error', $pesan);
        }

        return response()->json(['pesan' => $pesan], 403);
    }
}
