<?php

namespace App\Http\Requests;

use App\Enums\MetodeBayar;
use App\Enums\StatusProduksi;
use App\Http\Requests\Concerns\AngkaRupiah;
use App\Http\Requests\Concerns\PeriksaNomorSpp;
use App\Http\Requests\Concerns\PesanIndonesia;
use App\Models\Pesanan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * "Catat DP & masukkan ke produksi" (khusus admin, status menunggu_dp).
 *
 * Field:
 * - dp: rupiah > 0 dan ≤ total tagihan (subtotal + ongkir; "Rp 500.000" diterima).
 * - metode_dp: wajib, nilai App\Enums\MetodeBayar (cash, transfer_bca, transfer_bri, qris).
 *   Field `metode` diterima sebagai alias.
 * - tanggal_dp (opsional, bawaan hari ini): antara tanggal SPP dan hari ini.
 * - nomor_spp: angka bulat ≥1 ("#176" / "SPP #176" diterima), unik termasuk SPP terhapus.
 *   Saran: Pesanan::saranNomorSpp() (atau nomor lama bila SPP sudah pernah bernomor).
 * - konfirmasi_nomor=1: wajib bila nomor loncat > AMBANG_LONCAT_NOMOR dari saran (galat di kunci "konfirmasi_nomor").
 */
class CatatDpPesananRequest extends FormRequest
{
    use AngkaRupiah, PeriksaNomorSpp, PesanIndonesia;

    public function authorize(): bool
    {
        return Gate::allows('catatDp', $this->route('pesanan'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('dp')) {
            $this->merge(['dp' => $this->keAngka($this->input('dp'))]);
        }
        if (! $this->filled('tanggal_dp')) {
            $this->merge(['tanggal_dp' => today()->toDateString()]);
        }
        $this->rapikanNomorSpp();

        if (! $this->has('metode_dp') && $this->has('metode')) {
            $this->merge(['metode_dp' => $this->input('metode')]);
        }
    }

    public function rules(): array
    {
        $tanggalSpp = $this->route('pesanan')->tanggal_order?->toDateString();

        return [
            'dp'               => ['required', 'integer', 'min:1', 'max:' . SimpanPesananRequest::MAKS_TOTAL],
            'tanggal_dp'       => array_values(array_filter([
                'required', 'date', $tanggalSpp ? 'after_or_equal:' . $tanggalSpp : null, 'before_or_equal:today',
            ])),
            'nomor_spp'        => ['required', 'integer', 'min:1', 'max:' . self::MAKS_NOMOR],
            'konfirmasi_nomor' => ['nullable', 'boolean'],
            'metode_dp'        => ['required', Rule::enum(MetodeBayar::class)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var Pesanan $pesanan */
            $pesanan = $this->route('pesanan');

            if ($pesanan->status_produksi !== StatusProduksi::MenungguDp) {
                $validator->errors()->add('status', 'DP hanya bisa dicatat saat SPP berstatus Menunggu DP (sekarang: '
                    . $pesanan->status_produksi->label() . ').');

                return;
            }

            $total = $pesanan->total_tagihan;
            if (! $validator->errors()->has('dp') && (int) $this->input('dp') > $total) {
                $validator->errors()->add('dp', 'DP tidak boleh lebih dari total tagihan (Rp '
                    . number_format($total, 0, ',', '.') . ').');
            }

            if ($validator->errors()->has('nomor_spp')) {
                return;
            }

            $nomor = (int) $this->input('nomor_spp');
            if ($this->periksaNomorUnik($validator, $nomor, $pesanan)) {
                return;
            }

            // Nomor lama SPP ini sendiri (pernah masuk produksi lalu dikembalikan) tidak dianggap loncat
            if ($nomor !== (int) $pesanan->nomor_spp) {
                $this->periksaNomorLoncat($validator, $nomor);
            }
        }];
    }

    public function messages(): array
    {
        $tanggalSpp = $this->route('pesanan')->tanggal_order;

        return [
            ...$this->pesanUmum(),
            'dp.min'                     => 'DP harus lebih dari Rp 0.',
            'dp.integer'                 => 'DP harus berupa angka rupiah, mis. 500.000.',
            'nomor_spp.integer'          => 'Nomor SPP harus angka bulat, mis. 176.',
            'tanggal_dp.after_or_equal'  => 'Tanggal DP tidak boleh sebelum tanggal SPP'
                . ($tanggalSpp ? ' (' . $tanggalSpp->locale('id')->translatedFormat('j F Y') . ').' : '.'),
            'tanggal_dp.before_or_equal' => 'Tanggal DP tidak boleh setelah hari ini.',
            'metode_dp.required'         => 'Pilih metode pembayaran.',
            'metode_dp.enum'             => 'Pilih metode pembayaran.',
        ];
    }

    public function attributes(): array
    {
        return ['dp' => 'DP', 'tanggal_dp' => 'Tanggal DP', 'nomor_spp' => 'Nomor SPP', 'metode_dp' => 'Metode pembayaran'];
    }

    public function metode(): MetodeBayar
    {
        return MetodeBayar::from($this->validated('metode_dp'));
    }
}
