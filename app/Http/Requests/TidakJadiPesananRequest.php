<?php

namespace App\Http\Requests;

use App\Enums\StatusProduksi;
use App\Http\Requests\Concerns\PesanIndonesia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Tandai "Tidak Jadi" SPP yang belum masuk produksi (khusus admin). status_lama untuk anti-bentrok. */
class TidakJadiPesananRequest extends FormRequest
{
    use PesanIndonesia;

    public function authorize(): bool
    {
        return Gate::allows('tidakJadi', $this->route('pesanan'));
    }

    public function rules(): array
    {
        return [
            'status_lama' => ['required', 'string', Rule::enum(StatusProduksi::class)],
            'catatan'     => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return $this->pesanUmum();
    }

    public function attributes(): array
    {
        return ['status_lama' => 'Status saat ini', 'catatan' => 'Alasan tidak jadi'];
    }
}
