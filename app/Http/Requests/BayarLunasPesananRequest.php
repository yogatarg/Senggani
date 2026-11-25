<?php

namespace App\Http\Requests;

use App\Enums\MetodeBayar;
use App\Enums\StatusProduksi;
use App\Http\Requests\Concerns\PeriksaNomorSpp;
use App\Http\Requests\Concerns\PesanIndonesia;
use App\Models\Pesanan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * "Bayar lunas" dari Menunggu DP (khusus admin, Policy catatLunas): customer langsung membayar penuh.
 * Nominal tidak dikirim form; server memakai total tagihan (lihat PesananService::bayarLunas).
 *
 * Field:
 * - metode_bayar: wajib, nilai App\Enums\MetodeBayar. Field `metode` diterima sebagai alias.
 * - tanggal_bayar (opsional, bawaan hari ini): antara tanggal SPP dan hari ini.
 * - nomor_spp & konfirmasi_nomor: sama seperti Catat DP (unik termasuk SPP terhapus, konfirmasi bila loncat).
 * - aksi=bayar_lunas: penanda form (dipakai tampilan untuk membuka modal yang benar saat galat).
 */
class BayarLunasPesananRequest extends FormRequest
{
    use PeriksaNomorSpp, PesanIndonesia;

    // Galat terpisah dari modal Catat DP (field nomor_spp/konfirmasi_nomor/status sama namanya)
    public const ERROR_BAG = 'bayarLunas';

    protected $errorBag = self::ERROR_BAG;

    public function authorize(): bool
    {
        return Gate::allows('catatLunas', $this->route('pesanan'));
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('tanggal_bayar')) {
            $this->merge(['tanggal_bayar' => today()->toDateString()]);
        }
        $this->rapikanNomorSpp();

        if (! $this->has('metode_bayar') && $this->has('metode')) {
            $this->merge(['metode_bayar' => $this->input('metode')]);
        }
    }

    public function rules(): array
    {
        $tanggalSpp = $this->route('pesanan')->tanggal_order?->toDateString();

        return [
            'tanggal_bayar'    => array_values(array_filter([
                'required', 'date', $tanggalSpp ? 'after_or_equal:' . $tanggalSpp : null, 'before_or_equal:today',
            ])),
            'nomor_spp'        => ['required', 'integer', 'min:1', 'max:' . self::MAKS_NOMOR],
            'konfirmasi_nomor' => ['nullable', 'boolean'],
            'metode_bayar'     => ['required', Rule::enum(MetodeBayar::class)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var Pesanan $pesanan */
            $pesanan = $this->route('pesanan');

            if ($pesanan->status_produksi !== StatusProduksi::MenungguDp) {
                $validator->errors()->add('status', 'Bayar lunas hanya untuk SPP berstatus Menunggu DP (sekarang: '
                    . $pesanan->status_produksi->label() . '). Untuk SPP yang sudah produksi, pakai tombol Lunas.');

                return;
            }

            if ($pesanan->total_tagihan <= 0) {
                $validator->errors()->add('status', 'Total tagihan masih Rp 0, jadi tidak ada yang dibayar lunas.');

                return;
            }

            if ($validator->errors()->has('nomor_spp')) {
                return;
            }

            $nomor = (int) $this->input('nomor_spp');
            if ($this->periksaNomorUnik($validator, $nomor, $pesanan)) {
                return;
            }

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
            'nomor_spp.integer'             => 'Nomor SPP harus angka bulat, mis. 176.',
            'tanggal_bayar.after_or_equal'  => 'Tanggal bayar tidak boleh sebelum tanggal SPP'
                . ($tanggalSpp ? ' (' . $tanggalSpp->locale('id')->translatedFormat('j F Y') . ').' : '.'),
            'tanggal_bayar.before_or_equal' => 'Tanggal bayar tidak boleh setelah hari ini.',
            'metode_bayar.required'         => 'Pilih metode pembayaran.',
            'metode_bayar.enum'             => 'Pilih metode pembayaran.',
        ];
    }

    public function attributes(): array
    {
        return ['tanggal_bayar' => 'Tanggal bayar', 'nomor_spp' => 'Nomor SPP', 'metode_bayar' => 'Metode pembayaran'];
    }

    public function metode(): MetodeBayar
    {
        return MetodeBayar::from($this->validated('metode_bayar'));
    }
}
