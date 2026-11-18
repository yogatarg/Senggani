<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PesanIndonesia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

/** Ubah password sendiri (semua peran), wajib password lama. */
class UbahPasswordRequest extends FormRequest
{
    use PesanIndonesia;

    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'password_lama' => ['required', 'string'],
            'password'      => ['required', 'string', 'min:8', 'max:255', 'confirmed', 'different:password_lama'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('password_lama')) {
                return;
            }

            if (! Hash::check((string) $this->input('password_lama'), Auth::user()->password)) {
                $validator->errors()->add('password_lama', 'Password saat ini salah.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            ...$this->pesanUmum(),
            'password.confirmed' => 'Ulangan password baru tidak sama.',
            'password.different' => 'Password baru harus berbeda dengan password saat ini.',
        ];
    }

    public function attributes(): array
    {
        return ['password_lama' => 'Password saat ini', 'password' => 'Password baru'];
    }
}
