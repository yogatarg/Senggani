<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimpanCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Daftar customer (khusus admin; rute di grup peran:admin, dicek lagi lewat Gate kelola-customer).
 * Customer dibuat otomatis dari form SPP; di sini hanya lihat, cari, edit, dan hapus
 * customer yang belum punya SPP sama sekali.
 */
class CustomerController extends Controller
{
    public const PER_HALAMAN = 20;

    public const MAKS_SARAN = 10;

    public function index(Request $request)
    {
        Gate::authorize('kelola-customer');

        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        $customer = Customer::query()
            ->cari($q)
            ->withCount('pesanan')
            ->withMax('pesanan as spp_terakhir', 'nomor_spp')
            ->orderBy('nama')->orderBy('id')
            ->paginate(self::PER_HALAMAN)
            ->withQueryString();

        return view('admin.customer.index', [
            'customer' => $customer,
            'q'        => $q,
        ]);
    }

    /** JSON autocomplete form SPP: cari nama/WA/kode, maks 10 hasil. */
    public function cari(Request $request)
    {
        Gate::authorize('kelola-customer');

        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        if ($q === '') {
            return response()->json(['data' => []]);
        }

        $hasil = Customer::query()
            ->cari($q)
            ->withCount('pesanan')
            ->orderBy('nama')->orderBy('id')
            ->limit(self::MAKS_SARAN)
            ->get()
            ->map(fn (Customer $c) => [
                'id'              => $c->id,
                'kode'            => $c->kode,
                'nama'            => $c->nama,
                'nomor_whatsapp'  => $c->nomor_whatsapp,
                'whatsapp_tampil' => $c->whatsapp_tampil,
                'alamat'          => $c->alamat,
                'kota'            => $c->kota,
                'jumlah_spp'      => (int) $c->pesanan_count,
            ]);

        return response()->json(['data' => $hasil]);
    }

    public function show(Customer $customer)
    {
        Gate::authorize('kelola-customer');

        $pesanan = $customer->pesanan()
            ->with('detailPesanan')
            ->withCount('detailPesanan')
            ->withSum('detailPesanan as total_jumlah', 'jumlah')
            ->orderByDesc('nomor_spp')
            ->paginate(self::PER_HALAMAN)
            ->withQueryString();

        return view('admin.customer.show', [
            'customer'   => $customer,
            'pesanan'    => $pesanan,
            'bisaHapus'  => ! $customer->pesanan()->withTrashed()->exists(),
        ]);
    }

    public function edit(Customer $customer)
    {
        Gate::authorize('kelola-customer');

        return view('admin.customer.edit', ['customer' => $customer]);
    }

    public function update(SimpanCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->dataCustomer());

        return redirect()->route('admin.customer.show', $customer)
            ->with('success', "Data customer {$customer->kode} diperbarui. SPP lama tetap memakai data saat dibuat.");
    }

    /** Hapus hanya bila belum pernah punya SPP (termasuk SPP yang sudah dihapus). */
    public function destroy(Customer $customer)
    {
        Gate::authorize('kelola-customer');

        if ($customer->pesanan()->withTrashed()->exists()) {
            return redirect()->route('admin.customer.show', $customer)
                ->with('error', "Customer {$customer->kode} sudah punya SPP, jadi tidak bisa dihapus.");
        }

        $customer->delete();

        return redirect()->route('admin.customer.index')
            ->with('success', "Customer {$customer->kode} ({$customer->nama}) dihapus.");
    }
}
