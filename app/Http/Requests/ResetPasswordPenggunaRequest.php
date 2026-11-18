<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PesanIndonesia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Admin mengatur password baru untuk pengguna lain (tanpa password lama). */
class ResetPasswordPenggunaRequest extends FormRequest
{
    use PesanIndonesia;

    public function authorize(): bool
    {
        return Gate::allows('kelola-pengguna');
    }

    public function rules(): array
    {
        return ['password' => ['required', 'string', 'min:8', 'max:255', 'confirmed']];
    }

    public function messages(): array
    {
        return $this->pesanUmum();
    }

    public function attributes(): array
    {
        return ['password' => 'Password baru'];
    }
}
