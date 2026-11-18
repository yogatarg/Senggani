<?php

namespace App\Http\Requests;

use App\Enums\Peran;
use App\Http\Requests\Concerns\PesanIndonesia;
use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Tambah & edit pengguna (khusus admin). Minimal salah satu dari username/email
 * harus diisi agar akun bisa login. Password hanya saat tambah (edit memakai reset password).
 */
class SimpanPenggunaRequest extends FormRequest
{
    use PesanIndonesia;

    public function authorize(): bool
    {
        return Gate::allows('kelola-pengguna');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => filled($this->input('username')) ? Str::lower(trim((string) $this->input('username'))) : null,
            'email'    => filled($this->input('email')) ? Str::lower(trim((string) $this->input('email'))) : null,
        ]);
    }

    public function rules(): array
    {
        $pengguna = $this->route('pengguna');
        $abaikan  = $pengguna instanceof Admin ? $pengguna->id : null;

        $aturan = [
            'nama'     => ['required', 'string', 'max:100'],
            'username' => ['nullable', 'required_without:email', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('admin', 'username')->ignore($abaikan)],
            'email'    => ['nullable', 'required_without:username', 'email', 'max:255', Rule::unique('admin', 'email')->ignore($abaikan)],
            'peran'    => ['required', Rule::enum(Peran::class)],
        ];

        if (! $pengguna) {
            $aturan['password'] = ['required', 'string', 'min:8', 'max:255', 'confirmed'];
        }

        return $aturan;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $pengguna = $this->route('pengguna');

            // Admin tidak boleh menurunkan perannya sendiri (bisa terkunci dari kelola pengguna)
            if ($pengguna instanceof Admin && $pengguna->is(Auth::user()) && $this->input('peran') !== $pengguna->peran->value) {
                $validator->errors()->add('peran', 'Anda tidak bisa mengubah peran akun Anda sendiri.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            ...$this->pesanUmum(),
            'username.required_without' => 'Isi username atau email (minimal salah satu) agar pengguna bisa login.',
            'email.required_without'    => 'Isi username atau email (minimal salah satu) agar pengguna bisa login.',
            'username.regex'            => 'Username hanya boleh berisi huruf kecil, angka, titik, strip, dan garis bawah (tanpa spasi).',
        ];
    }

    public function attributes(): array
    {
        return ['nama' => 'Nama', 'username' => 'Username', 'email' => 'Email', 'peran' => 'Peran', 'password' => 'Password'];
    }
}
