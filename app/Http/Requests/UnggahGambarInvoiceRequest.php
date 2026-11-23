<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AturanGambarInvoice;
use App\Http\Requests\Concerns\PesanIndonesia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Unggah satu gambar invoice lewat route admin.pengaturan.invoice.unggah ({jenis} = ttd|stempel|qris,
 * dibatasi di route). Field: gambar (lihat AturanGambarInvoice).
 */
class UnggahGambarInvoiceRequest extends FormRequest
{
    use AturanGambarInvoice, PesanIndonesia;

    public function authorize(): bool
    {
        return Gate::allows('kelola-invoice');
    }

    public function rules(): array
    {
        return ['gambar' => ['required', ...$this->aturanGambar($this->jenis())]];
    }

    public function messages(): array
    {
        return [
            ...$this->pesanUmum(),
            'gambar.required' => 'Pilih gambar dulu.',
            ...$this->pesanGambar('gambar', $this->jenis()),
        ];
    }

    public function jenis(): string
    {
        return (string) $this->route('jenis');
    }
}
