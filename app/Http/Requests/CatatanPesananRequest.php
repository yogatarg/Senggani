<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PesanIndonesia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CatatanPesananRequest extends FormRequest
{
    use PesanIndonesia;

    public function authorize(): bool
    {
        return Gate::allows('tambahCatatan', $this->route('pesanan'));
    }

    public function rules(): array
    {
        return ['catatan' => ['required', 'string', 'max:500']];
    }

    public function messages(): array
    {
        return [...$this->pesanUmum(), 'catatan.required' => 'Tulis catatan progres dulu.'];
    }

    public function attributes(): array
    {
        return ['catatan' => 'Catatan'];
    }
}
