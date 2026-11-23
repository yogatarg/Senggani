<?php

namespace App\Http\Requests;

use App\Enums\MetodeBayar;
use App\Http\Requests\Concerns\PesanIndonesia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Catat pelunasan (khusus admin, SPP yang sudah masuk produksi). Tanggal bawaan: hari ini.
 * metode_lunas wajib (App\Enums\MetodeBayar); field `metode` diterima sebagai alias.
 */
class LunasPesananRequest extends FormRequest
{
    use PesanIndonesia;

    public function authorize(): bool
    {
        return Gate::allows('catatLunas', $this->route('pesanan'));
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('tanggal_lunas')) {
            $this->merge(['tanggal_lunas' => today()->toDateString()]);
        }
        if (! $this->has('metode_lunas') && $this->has('metode')) {
            $this->merge(['metode_lunas' => $this->input('metode')]);
        }
    }

    public function rules(): array
    {
        $tanggalSpp = $this->route('pesanan')->tanggal_order?->toDateString();

        return [
            'tanggal_lunas' => array_values(array_filter([
                'required', 'date',
                $tanggalSpp ? 'after_or_equal:' . $tanggalSpp : null,
                'before_or_equal:today',
            ])),
            'metode_lunas' => ['required', Rule::enum(MetodeBayar::class)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $this->route('pesanan')->status_produksi->sudahProduksi()) {
                $validator->errors()->add('tanggal_lunas', 'Pelunasan dicatat setelah DP dicatat dan SPP masuk produksi.');

                return;
            }
            if ($this->route('pesanan')->total_tagihan <= 0) {
                $validator->errors()->add('tanggal_lunas', 'Tanggal lunas hanya bisa diisi bila total tagihan lebih dari Rp 0.');
            }
        }];
    }

    public function messages(): array
    {
        $tanggalSpp = $this->route('pesanan')->tanggal_order;

        return [
            ...$this->pesanUmum(),
            'tanggal_lunas.after_or_equal'  => 'Tanggal lunas tidak boleh sebelum tanggal SPP'
                . ($tanggalSpp ? ' (' . $tanggalSpp->locale('id')->translatedFormat('j F Y') . ').' : '.'),
            'tanggal_lunas.before_or_equal' => 'Tanggal lunas tidak boleh setelah hari ini.',
            'metode_lunas.required'         => 'Pilih metode pembayaran.',
            'metode_lunas.enum'             => 'Pilih metode pembayaran.',
        ];
    }

    public function attributes(): array
    {
        return ['tanggal_lunas' => 'Tanggal lunas', 'metode_lunas' => 'Metode pembayaran'];
    }

    public function metode(): MetodeBayar
    {
        return MetodeBayar::from($this->validated('metode_lunas'));
    }
}
