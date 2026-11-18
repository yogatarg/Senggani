<?php

namespace App\Http\Requests;

use App\Enums\StatusProduksi;
use App\Http\Requests\Concerns\PesanIndonesia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Batalkan order (khusus admin). status_lama untuk anti-bentrok. */
class BatalPesananRequest extends FormRequest
{
    use PesanIndonesia;

    public function authorize(): bool
    {
        return Gate::allows('batalkan', $this->route('pesanan'));
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
        return ['status_lama' => 'Status saat ini', 'catatan' => 'Alasan pembatalan'];
    }
}
