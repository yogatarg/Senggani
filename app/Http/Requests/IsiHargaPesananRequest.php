<?php

namespace App\Http\Requests;

use App\Enums\StatusProduksi;
use App\Exceptions\AksiDitolak;
use App\Http\Requests\Concerns\AngkaRupiah;
use App\Http\Requests\Concerns\PesanIndonesia;
use App\Models\Pesanan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Isi/ubah harga satuan per item (tab Hitung Harga). Produksi hanya selama status
 * hitung_harga (pesan AksiDitolak::HARGA_DIKUNCI), admin kapan saja.
 *
 * Field:
 * - harga[<detail_pesanan.id>]: harga satuan rupiah ("4.300" / "Rp 4.300" diterima), minimal Rp 1. Kosong = belum
 *   dihitung (hanya boleh saat hitung_harga / tidak_jadi). Item yang tidak dikirim tidak diubah.
 * - catatan_harga (opsional): catatan perhitungan; dikirim kosong = dikosongkan.
 */
class IsiHargaPesananRequest extends FormRequest
{
    use AngkaRupiah, PesanIndonesia;

    public function authorize(): bool
    {
        // Gate::authorize melempar 403 dengan pesan dari Policy (bukan pesan bawaan)
        Gate::authorize('isiHarga', $this->route('pesanan'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        $harga = $this->input('harga');

        if (is_array($harga)) {
            $this->merge(['harga' => array_map(fn ($h) => $this->keAngka($h), $harga)]);
        }
    }

    public function rules(): array
    {
        return [
            'harga'         => ['required', 'array', 'min:1', 'max:50'],
            'harga.*'       => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'catatan_harga' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Pesanan $pesanan */
            $pesanan = $this->route('pesanan');
            $item    = $pesanan->detailPesanan()->get()->keyBy('id');
            $harga   = (array) $this->input('harga');

            foreach (array_keys($harga) as $id) {
                if (! $item->has((int) $id) || ! ctype_digit((string) $id)) {
                    $validator->errors()->add('harga', 'Ada item yang tidak dikenal. Muat ulang halaman lalu coba lagi.');

                    return;
                }
            }

            $bolehKosong = in_array($pesanan->status_produksi, [StatusProduksi::HitungHarga, StatusProduksi::TidakJadi], true);
            $total = 0;
            $no    = 0;
            foreach ($item as $id => $d) {
                $no++;
                $nilai = array_key_exists($id, $harga) ? $harga[$id] : $d->harga_satuan;

                if ($nilai === null && array_key_exists($id, $harga) && ! $bolehKosong) {
                    $validator->errors()->add("harga.{$id}", "Harga item ke-{$no} tidak boleh dikosongkan setelah SPP melewati Hitung Harga.");
                }
                $total += (int) $d->jumlah * (int) $nilai;
            }

            if ($total > SimpanPesananRequest::MAKS_TOTAL) {
                $validator->errors()->add('harga', 'Total SPP terlalu besar.');
            }
        }];
    }

    /** @return array<int, int|null> detail_pesanan.id => harga */
    public function dataHarga(): array
    {
        $hasil = [];
        foreach ((array) $this->validated('harga') as $id => $nilai) {
            $hasil[(int) $id] = $nilai === null ? null : (int) $nilai;
        }

        return $hasil;
    }

    /** null bila field tidak dikirim (catatan lama tetap). */
    public function catatanHarga(): ?string
    {
        return $this->has('catatan_harga') ? (string) $this->input('catatan_harga', '') : null;
    }

    public function messages(): array
    {
        return [
            ...$this->pesanUmum(),
            'harga.required' => 'Isi harga minimal satu item.',
            'harga.*.integer' => 'Harga harus berupa angka rupiah, mis. 4.300.',
            'harga.*.min'     => 'Harga satuan minimal Rp 1.',
        ];
    }

    public function attributes(): array
    {
        return ['harga' => 'Harga', 'harga.*' => 'Harga item', 'catatan_harga' => 'Catatan perhitungan'];
    }
}
