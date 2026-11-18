<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PesanIndonesia;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Edit data customer (khusus admin). Nomor WA dinormalisasi ke 62xxx lalu dicek unik.
 * Salinan data di SPP lama tidak ikut berubah.
 */
class SimpanCustomerRequest extends FormRequest
{
    use PesanIndonesia;

    public function authorize(): bool
    {
        return Gate::allows('kelola-customer');
    }

    public function rules(): array
    {
        return [
            'nama'           => ['required', 'string', 'max:150'],
            'nomor_whatsapp' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s\-().]+$/'],
            'alamat'         => ['nullable', 'string', 'max:2000'],
            'kota'           => ['nullable', 'string', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('nomor_whatsapp')) {
                return;
            }

            $wa = SimpanPesananRequest::normalisasiWhatsapp((string) $this->input('nomor_whatsapp'));
            if (! preg_match('/^62\d{8,13}$/', $wa)) {
                $validator->errors()->add('nomor_whatsapp', 'Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.');

                return;
            }

            $customer = $this->route('customer');
            $pemilik  = Customer::where('nomor_whatsapp', $wa)
                ->when($customer instanceof Customer, fn ($q) => $q->whereKeyNot($customer->id))
                ->first();
            if ($pemilik) {
                $validator->errors()->add('nomor_whatsapp', "Nomor WhatsApp ini sudah dipakai customer {$pemilik->kode} ({$pemilik->nama}).");
            }
        }];
    }

    public function messages(): array
    {
        return [
            ...$this->pesanUmum(),
            'nomor_whatsapp.regex' => 'Nomor WhatsApp hanya boleh berisi angka, spasi, +, dan -.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama'           => 'Nama customer',
            'nomor_whatsapp' => 'Nomor WhatsApp',
            'alamat'         => 'Alamat',
            'kota'           => 'Kota',
        ];
    }

    public function dataCustomer(): array
    {
        $v = $this->validated();

        return [
            'nama'           => trim($v['nama']),
            'nomor_whatsapp' => SimpanPesananRequest::normalisasiWhatsapp($v['nomor_whatsapp']),
            'alamat'         => filled($v['alamat'] ?? null) ? trim($v['alamat']) : null,
            'kota'           => filled($v['kota'] ?? null) ? trim($v['kota']) : null,
        ];
    }
}
