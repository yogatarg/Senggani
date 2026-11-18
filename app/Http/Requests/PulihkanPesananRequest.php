<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PesanIndonesia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Pulihkan order yang dibatalkan ke status sebelum dibatalkan (khusus admin). */
class PulihkanPesananRequest extends FormRequest
{
    use PesanIndonesia;

    public function authorize(): bool
    {
        return Gate::allows('pulihkan', $this->route('pesanan'));
    }

    public function rules(): array
    {
        return ['catatan' => ['nullable', 'string', 'max:500']];
    }

    public function messages(): array
    {
        return $this->pesanUmum();
    }

    public function attributes(): array
    {
        return ['catatan' => 'Catatan'];
    }
}
