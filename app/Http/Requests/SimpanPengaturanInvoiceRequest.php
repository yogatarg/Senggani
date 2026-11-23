<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AturanGambarInvoice;
use App\Http\Requests\Concerns\PesanIndonesia;
use App\Services\PengaturanInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Simpan Pengaturan Invoice (khusus admin, Gate 'kelola-invoice').
 *
 * Field:
 * - penandatangan_nama (opsional, maks 100), penandatangan_jabatan (opsional, maks 100; kosong = tidak tampil).
 * - rekening[i][bank|nomor|atas_nama]: maks 4 baris; baris yang semua isiannya kosong dibuang.
 *   Nomor disimpan persis seperti diketik (hanya ujungnya dirapikan) dan tampil apa adanya di invoice;
 *   hanya angka dan spasi, 5–30 digit, maks 40 karakter. Boleh kosong semua.
 * - qris_keterangan (opsional, maks 200), keterangan_bawah (opsional, maks 500).
 * - persen_dp: 0–100 (0 = baris "DP minimal" tidak tampil di invoice).
 * - ttd, stempel, qris (opsional): file gambar baru (lihat AturanGambarInvoice); diproses setelah teks disimpan.
 */
class SimpanPengaturanInvoiceRequest extends FormRequest
{
    use AturanGambarInvoice, PesanIndonesia;

    public function authorize(): bool
    {
        return Gate::allows('kelola-invoice');
    }

    protected function prepareForValidation(): void
    {
        $rekening = $this->input('rekening');

        if (is_array($rekening)) {
            $bersih = [];
            foreach ($rekening as $r) {
                if (! is_array($r)) {
                    continue;
                }
                $r = [
                    'bank'      => trim((string) ($r['bank'] ?? '')),
                    'nomor'     => trim((string) ($r['nomor'] ?? '')),
                    'atas_nama' => trim((string) ($r['atas_nama'] ?? '')),
                ];
                if ($r['bank'] !== '' || $r['nomor'] !== '' || $r['atas_nama'] !== '') {
                    $bersih[] = $r;
                }
            }
            $this->merge(['rekening' => $bersih]);
        }

        if (is_string($this->input('persen_dp'))) {
            $this->merge(['persen_dp' => trim(str_replace('%', '', $this->input('persen_dp')))]);
        }
    }

    public function rules(): array
    {
        return [
            'penandatangan_nama'    => ['nullable', 'string', 'max:100'],
            'penandatangan_jabatan' => ['nullable', 'string', 'max:100'],
            'rekening'              => ['nullable', 'array', 'max:' . PengaturanInvoice::MAKS_REKENING],
            'rekening.*.bank'       => ['required', 'string', 'max:30'],
            // Hanya angka & spasi, dengan 5–30 digit (spasi bebas, mis. "3008 0101 7388 537")
            'rekening.*.nomor'      => ['required', 'string', 'max:40', 'regex:/^(?=(?:\D*\d){5,30}\D*$)[\d ]+$/'],
            'rekening.*.atas_nama'  => ['required', 'string', 'max:100'],
            'qris_keterangan'       => ['nullable', 'string', 'max:200'],
            'keterangan_bawah'      => ['nullable', 'string', 'max:500'],
            'persen_dp'             => ['required', 'integer', 'min:0', 'max:100'],
            'ttd'                   => ['nullable', ...$this->aturanGambar('ttd')],
            'stempel'               => ['nullable', ...$this->aturanGambar('stempel')],
            'qris'                  => ['nullable', ...$this->aturanGambar('qris')],
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->pesanUmum(),
            'rekening.max'            => 'Rekening maksimal ' . PengaturanInvoice::MAKS_REKENING . '.',
            'rekening.*.bank.required' => 'Nama bank rekening ke-:position wajib diisi.',
            'rekening.*.nomor.required' => 'Nomor rekening ke-:position wajib diisi.',
            'rekening.*.nomor.regex'  => 'Nomor rekening ke-:position hanya boleh angka dan spasi (5–30 digit).',
            'rekening.*.nomor.max'    => 'Nomor rekening ke-:position maksimal 40 karakter.',
            'rekening.*.atas_nama.required' => 'Atas nama rekening ke-:position wajib diisi.',
            'persen_dp.integer'       => 'Persen DP harus angka bulat 0–100.',
            'persen_dp.min'           => 'Persen DP minimal 0.',
            'persen_dp.max'           => 'Persen DP maksimal 100.',
            ...$this->pesanGambar('ttd', 'ttd'),
            ...$this->pesanGambar('stempel', 'stempel'),
            ...$this->pesanGambar('qris', 'qris'),
        ];
    }

    public function attributes(): array
    {
        return [
            'penandatangan_nama'    => 'Nama penanda tangan',
            'penandatangan_jabatan' => 'Jabatan',
            'rekening'              => 'Rekening',
            'rekening.*.bank'       => 'Bank rekening ke-:position',
            'rekening.*.nomor'      => 'Nomor rekening ke-:position',
            'rekening.*.atas_nama'  => 'Atas nama rekening ke-:position',
            'qris_keterangan'       => 'Keterangan QRIS',
            'keterangan_bawah'      => 'Keterangan bawah invoice',
            'persen_dp'             => 'Persen DP',
            'ttd'                   => 'Tanda tangan',
            'stempel'               => 'Stempel',
            'qris'                  => 'QRIS',
        ];
    }

    /** Isian teks siap simpan (lihat PengaturanInvoice::simpan). */
    public function dataPengaturan(): array
    {
        $v = $this->validated();

        return [
            'penandatangan_nama'    => trim((string) ($v['penandatangan_nama'] ?? '')),
            'penandatangan_jabatan' => trim((string) ($v['penandatangan_jabatan'] ?? '')),
            'rekening'              => array_values($v['rekening'] ?? []),
            'qris_keterangan'       => trim((string) ($v['qris_keterangan'] ?? '')),
            'keterangan_bawah'      => trim(str_replace("\r\n", "\n", (string) ($v['keterangan_bawah'] ?? ''))),
            'persen_dp'             => (int) $v['persen_dp'],
        ];
    }

    /** @return array<string, \Illuminate\Http\UploadedFile> jenis => file baru */
    public function gambarBaru(): array
    {
        $hasil = [];
        foreach (array_keys(PengaturanInvoice::JENIS_GAMBAR) as $jenis) {
            if ($this->hasFile($jenis)) {
                $hasil[$jenis] = $this->file($jenis);
            }
        }

        return $hasil;
    }
}
