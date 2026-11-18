<?php

namespace App\Http\Requests;

use App\Enums\StatusProduksi;
use App\Http\Requests\Concerns\PesanIndonesia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Maju/mundur satu langkah. status_lama = status yang tampil di layar pengguna
 * (dipakai untuk anti-bentrok), status_baru = tujuan.
 */
class UbahStatusPesananRequest extends FormRequest
{
    use PesanIndonesia;

    public function authorize(): bool
    {
        return Gate::allows('ubahStatus', $this->route('pesanan'));
    }

    public function rules(): array
    {
        $alur = Rule::in(StatusProduksi::ALUR);

        return [
            'status_lama' => ['required', 'string', $alur],
            'status_baru' => ['required', 'string', $alur, 'different:status_lama'],
            'catatan'     => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->pesanUmum(),
            'status_lama.in' => 'Status SPP ini tidak bisa diubah lewat tombol maju/mundur.',
            'status_baru.in' => 'Status tujuan tidak valid.',
        ];
    }

    public function attributes(): array
    {
        return ['status_lama' => 'Status saat ini', 'status_baru' => 'Status tujuan', 'catatan' => 'Catatan'];
    }
}
