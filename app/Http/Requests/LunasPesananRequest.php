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
        return ['tanggal_lunas' => ['required', 'date', 'before_or_equal:today']];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ((float) $this->route('pesanan')->total <= 0) {
                $validator->errors()->add('tanggal_lunas', 'Tanggal lunas hanya bisa diisi bila total order lebih dari Rp 0.');
            }
        }];
    }

    public function messages(): array
    {
        return [...$this->pesanUmum(), 'tanggal_lunas.before_or_equal' => 'Tanggal lunas tidak boleh setelah hari ini.'];
    }

    public function attributes(): array
    {
        return ['tanggal_lunas' => 'Tanggal lunas'];
    }
}
