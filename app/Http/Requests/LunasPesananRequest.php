<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PesanIndonesia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/** Catat pelunasan (khusus admin). Tanggal bawaan: hari ini. */
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
    }

    public function rules(): array
    {
        $tanggalSpp = $this->route('pesanan')->tanggal_order?->toDateString();

        return ['tanggal_lunas' => array_values(array_filter([
            'required', 'date',
            $tanggalSpp ? 'after_or_equal:' . $tanggalSpp : null,
            'before_or_equal:today',
        ]))];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ((float) $this->route('pesanan')->total <= 0) {
                $validator->errors()->add('tanggal_lunas', 'Tanggal lunas hanya bisa diisi bila total SPP lebih dari Rp 0.');
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
        ];
    }

    public function attributes(): array
    {
        return ['tanggal_lunas' => 'Tanggal lunas'];
    }
}
