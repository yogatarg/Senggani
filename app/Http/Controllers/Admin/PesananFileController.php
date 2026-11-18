<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pesanan;
use App\Models\PesananFile;
use App\Services\PesananService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Menyajikan gambar mockup/referensi dari disk privat. Rute ada di grup auth.admin,
 * jadi file tidak bisa diakses tanpa login (tidak pernah lewat public/storage).
 */
class PesananFileController extends Controller
{
    public function lihat(Pesanan $pesanan, PesananFile $file)
    {
        abort_unless((int) $file->pesanan_id === (int) $pesanan->id, 404);
        Gate::authorize('view', $pesanan);

        $disk = Storage::disk(PesananFile::DISK);
        abort_unless($disk->exists($file->path), 404);

        // ?unduh=1 untuk mengunduh dengan nama asli, selain itu tampil inline
        $disposisi = request()->boolean('unduh') ? 'attachment' : 'inline';
        $namaUnduh = pathinfo($file->nama_asli, PATHINFO_FILENAME) . '.' . pathinfo($file->path, PATHINFO_EXTENSION);

        return $disk->response($file->path, $namaUnduh, [
            'Content-Type'           => $file->mime,
            'Cache-Control'          => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ], $disposisi);
    }

    public function hapus(Pesanan $pesanan, PesananFile $file, PesananService $layanan)
    {
        abort_unless((int) $file->pesanan_id === (int) $pesanan->id, 404);
        Gate::authorize('hapusFile', $pesanan);

        $layanan->hapusFile($file);

        if (request()->expectsJson()) {
            return response()->json(['pesan' => 'Gambar dihapus.']);
        }

        return back()->with('success', 'Gambar dihapus.');
    }
}
