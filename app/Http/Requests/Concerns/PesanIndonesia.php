<?php

namespace App\Http\Requests\Concerns;

/**
 * Pesan validasi umum berbahasa Indonesia (locale aplikasi masih "en").
 * Form Request pemakai cukup mengisi attributes() untuk nama field yang ramah.
 */
trait PesanIndonesia
{
    protected function pesanUmum(): array
    {
        return [
            'required'          => ':attribute wajib diisi.',
            'required_if'       => ':attribute wajib diisi.',
            'required_with'     => ':attribute wajib diisi.',
            'string'            => ':attribute harus berupa teks.',
            'max'               => [
                'string'  => ':attribute maksimal :max karakter.',
                'numeric' => ':attribute maksimal :max.',
                'file'    => ':attribute maksimal :max KB (5 MB).',
                'array'   => ':attribute maksimal :max file.',
            ],
            'min'               => [
                'string'  => ':attribute minimal :min karakter.',
                'numeric' => ':attribute minimal :min.',
                'file'    => ':attribute minimal :min KB.',
                'array'   => ':attribute minimal :min.',
            ],
            'integer'           => ':attribute harus berupa angka bulat.',
            'numeric'           => ':attribute harus berupa angka.',
            'date'              => ':attribute bukan tanggal yang valid.',
            'after_or_equal'    => ':attribute tidak boleh sebelum :date.',
            'before_or_equal'   => ':attribute tidak boleh setelah :date.',
            'url'               => ':attribute harus berupa URL yang valid.',
            'starts_with'       => ':attribute harus link Google Drive (drive.google.com atau docs.google.com).',
            'in'                => ':attribute tidak valid.',
            'enum'              => ':attribute tidak valid.',
            'array'             => ':attribute tidak valid.',
            'email'             => ':attribute harus berupa alamat email yang valid.',
            'unique'            => ':attribute sudah dipakai.',
            'confirmed'         => 'Ulangan :attribute tidak sama.',
            'image'             => ':attribute harus berupa gambar.',
            'mimes'             => ':attribute harus berformat JPG, PNG, atau WebP.',
            'uploaded'          => ':attribute gagal diunggah. Pastikan ukurannya tidak lebih dari 5 MB.',
            'alpha_dash'        => ':attribute hanya boleh berisi huruf, angka, titik, strip, dan garis bawah.',
            'regex'             => 'Format :attribute tidak valid.',
            'different'         => ':attribute harus berbeda dengan :other.',
            'current_password'  => 'Password saat ini salah.',
        ];
    }
}
