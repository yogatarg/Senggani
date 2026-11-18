<?php

namespace App\Http\Requests;

use App\Enums\StatusDesain;
use App\Http\Requests\Concerns\PesanIndonesia;
use App\Models\Pesanan;
use App\Models\PesananFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi tambah & edit order (khusus admin).
 *
 * Kontrak field (lihat juga laporan backend):
 * - item[i][jenis_packaging|jenis_bahan|jenis_cetak]: teks dari master, atau Pesanan::LAINNYA
 *   lalu isi item[i][<field>_lainnya].
 * - item[i][finishing][]: daftar nama finishing (multi) + item[i][finishing_lainnya] teks bebas.
 * - Harga & DP boleh dikirim dengan pemisah ribuan ("2.500" / "Rp 2.500"); disimpan rupiah bulat.
 * - mockup[] dan referensi[]: gambar baru (maks 5 MB per file, total maks 10 file per order).
 */
class SimpanPesananRequest extends FormRequest
{
    use PesanIndonesia;

    // Batas total order agar muat di kolom decimal(15,2)
    private const MAKS_TOTAL = 9_999_999_999_999;

    private const FIELD_PILIHAN = ['jenis_packaging', 'jenis_bahan', 'jenis_cetak'];

    public function authorize(): bool
    {
        $pesanan = $this->route('pesanan');

        return $pesanan instanceof Pesanan
            ? Gate::allows('update', $pesanan)
            : Gate::allows('create', Pesanan::class);
    }

    protected function prepareForValidation(): void
    {
        $item = $this->input('item');

        if (is_array($item)) {
            foreach ($item as $i => $baris) {
                if (is_array($baris) && array_key_exists('harga_satuan', $baris)) {
                    $item[$i]['harga_satuan'] = $this->keAngka($baris['harga_satuan']);
                }
                if (is_array($baris) && array_key_exists('jumlah', $baris)) {
                    $item[$i]['jumlah'] = $this->keAngka($baris['jumlah']);
                }
            }
            $this->merge(['item' => $item]);
        }

        if ($this->has('dp')) {
            $this->merge(['dp' => $this->keAngka($this->input('dp'))]);
        }
    }

    public function rules(): array
    {
        $gambar = ['image', 'mimes:jpg,jpeg,png,webp', 'max:' . PesananFile::MAKS_KB, 'dimensions:max_width=8000,max_height=8000'];
        $lainnya = Pesanan::LAINNYA;

        return [
            'nama_customer'   => ['required', 'string', 'max:150'],
            'nomor_whatsapp'  => ['required', 'string', 'max:30', 'regex:/^[0-9+\s\-().]+$/'],
            'alamat'          => ['required', 'string', 'max:2000'],
            'kota'            => ['required', 'string', 'max:100'],
            'tanggal_order'   => ['required', 'date'],
            'deadline'        => ['required', 'date', 'after_or_equal:tanggal_order'],
            'dp'              => ['nullable', 'integer', 'min:0'],
            'tanggal_lunas'   => ['nullable', 'date', 'before_or_equal:today'],
            'link_desain'     => ['nullable', 'url', 'max:500', 'starts_with:https://drive.google.com/,https://docs.google.com/'],
            'catatan_desain'  => ['nullable', 'string', 'max:5000'],
            'status_desain'   => ['nullable', Rule::enum(StatusDesain::class)],
            'catatan_admin'   => ['nullable', 'string', 'max:5000'],

            'item'                           => ['required', 'array', 'min:1', 'max:50'],
            'item.*'                         => ['array'],
            'item.*.jenis_packaging'         => ['required', 'string', 'max:150'],
            'item.*.jenis_packaging_lainnya' => ["required_if:item.*.jenis_packaging,{$lainnya}", 'nullable', 'string', 'max:150'],
            'item.*.panjang'                 => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'item.*.lebar'                   => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'item.*.tinggi'                  => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'item.*.jenis_bahan'             => ['required', 'string', 'max:150'],
            'item.*.jenis_bahan_lainnya'     => ["required_if:item.*.jenis_bahan,{$lainnya}", 'nullable', 'string', 'max:150'],
            'item.*.gramasi'                 => ['nullable', 'string', 'max:50'],
            'item.*.jenis_cetak'             => ['nullable', 'string', 'max:150'],
            'item.*.jenis_cetak_lainnya'     => ["required_if:item.*.jenis_cetak,{$lainnya}", 'nullable', 'string', 'max:150'],
            'item.*.warna_cetak'             => ['nullable', 'string', 'max:100'],
            'item.*.finishing'               => ['nullable', 'array', 'max:30'],
            'item.*.finishing.*'             => ['string', 'max:150'],
            'item.*.finishing_lainnya'       => ['nullable', 'string', 'max:255'],
            'item.*.aksesori'                => ['nullable', 'string', 'max:255'],
            'item.*.jumlah'                  => ['required', 'integer', 'min:1', 'max:10000000'],
            'item.*.harga_satuan'            => ['required', 'integer', 'min:0', 'max:1000000000'],

            'mockup'      => ['nullable', 'array', 'max:' . PesananFile::MAKS_PER_ORDER],
            'mockup.*'    => $gambar,
            'referensi'   => ['nullable', 'array', 'max:' . PesananFile::MAKS_PER_ORDER],
            'referensi.*' => $gambar,
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return; // cek lanjutan butuh data dasar yang sudah valid
            }

            $wa = self::normalisasiWhatsapp((string) $this->input('nomor_whatsapp'));
            if (! preg_match('/^62\d{8,13}$/', $wa)) {
                $validator->errors()->add('nomor_whatsapp', 'Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.');
            }

            $total = $this->hitungTotal();
            if ($total > self::MAKS_TOTAL) {
                $validator->errors()->add('item', 'Total order terlalu besar.');
            }

            if ((int) $this->input('dp', 0) > $total) {
                $validator->errors()->add('dp', 'DP tidak boleh lebih dari total order (Rp ' . number_format($total, 0, ',', '.') . ').');
            }

            if ($this->filled('tanggal_lunas') && $total <= 0) {
                $validator->errors()->add('tanggal_lunas', 'Tanggal lunas hanya bisa diisi bila total order lebih dari Rp 0.');
            }

            $pesanan = $this->route('pesanan');
            $sudahAda = $pesanan instanceof Pesanan ? $pesanan->file()->count() : 0;
            $baru = count($this->file('mockup', [])) + count($this->file('referensi', []));
            if ($sudahAda + $baru > PesananFile::MAKS_PER_ORDER) {
                $sisa = max(PesananFile::MAKS_PER_ORDER - $sudahAda, 0);
                $validator->errors()->add('mockup', 'Gambar maksimal ' . PesananFile::MAKS_PER_ORDER . " per order. Masih bisa menambah {$sisa} gambar.");
            }
        }];
    }

    public function messages(): array
    {
        return [
            ...$this->pesanUmum(),
            'deadline.after_or_equal' => 'Deadline tidak boleh sebelum tanggal order.',
            'tanggal_lunas.before_or_equal' => 'Tanggal lunas tidak boleh setelah hari ini.',
            'item.required'  => 'Tambahkan minimal satu item pesanan.',
            'item.min'       => 'Tambahkan minimal satu item pesanan.',
            'item.*.jenis_packaging_lainnya.required_if' => 'Tulis jenis packaging lainnya.',
            'item.*.jenis_bahan_lainnya.required_if'     => 'Tulis jenis bahan lainnya.',
            'item.*.jenis_cetak_lainnya.required_if'     => 'Tulis jenis cetak lainnya.',
            'item.*.jumlah.min' => 'Jumlah item ke-:position minimal 1.',
            'mockup.*.dimensions'    => 'Gambar mockup terlalu besar. Lebar dan tinggi maksimal 8000 piksel.',
            'referensi.*.dimensions' => 'Gambar referensi terlalu besar. Lebar dan tinggi maksimal 8000 piksel.',
            'nomor_whatsapp.regex'   => 'Nomor WhatsApp hanya boleh berisi angka, spasi, +, dan -.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_customer'  => 'Nama customer',
            'nomor_whatsapp' => 'Nomor WhatsApp',
            'alamat'         => 'Alamat',
            'kota'           => 'Kota',
            'tanggal_order'  => 'Tanggal order',
            'deadline'       => 'Deadline',
            'dp'             => 'DP',
            'tanggal_lunas'  => 'Tanggal lunas',
            'link_desain'    => 'Link Google Drive',
            'catatan_desain' => 'Catatan desain',
            'status_desain'  => 'Status approval desain',
            'catatan_admin'  => 'Catatan order',
            'item'           => 'Item pesanan',
            'item.*.jenis_packaging'         => 'Jenis packaging item ke-:position',
            'item.*.jenis_packaging_lainnya' => 'Jenis packaging lainnya item ke-:position',
            'item.*.panjang'                 => 'Panjang item ke-:position',
            'item.*.lebar'                   => 'Lebar item ke-:position',
            'item.*.tinggi'                  => 'Tinggi item ke-:position',
            'item.*.jenis_bahan'             => 'Jenis bahan item ke-:position',
            'item.*.jenis_bahan_lainnya'     => 'Jenis bahan lainnya item ke-:position',
            'item.*.gramasi'                 => 'Gramasi item ke-:position',
            'item.*.jenis_cetak'             => 'Jenis cetak item ke-:position',
            'item.*.jenis_cetak_lainnya'     => 'Jenis cetak lainnya item ke-:position',
            'item.*.warna_cetak'             => 'Warna cetak item ke-:position',
            'item.*.finishing'               => 'Finishing item ke-:position',
            'item.*.finishing.*'             => 'Finishing item ke-:position',
            'item.*.finishing_lainnya'       => 'Finishing lainnya item ke-:position',
            'item.*.aksesori'                => 'Tali/aksesori item ke-:position',
            'item.*.jumlah'                  => 'Jumlah item ke-:position',
            'item.*.harga_satuan'            => 'Harga satuan item ke-:position',
            'mockup'      => 'Gambar mockup',
            'mockup.*'    => 'Gambar mockup',
            'referensi'   => 'Gambar referensi',
            'referensi.*' => 'Gambar referensi',
        ];
    }

    // ── Data siap simpan (dipakai controller setelah validasi) ──

    /** Kolom tabel pesanan (tanpa nomor_order, total, status_produksi). */
    public function dataPesanan(): array
    {
        $v = $this->validated();

        return [
            'nama_customer'  => trim($v['nama_customer']),
            'nomor_whatsapp' => self::normalisasiWhatsapp($v['nomor_whatsapp']),
            'alamat'         => trim($v['alamat']),
            'kota'           => trim($v['kota']),
            'tanggal_order'  => $v['tanggal_order'],
            'deadline'       => $v['deadline'],
            'dp'             => (int) ($v['dp'] ?? 0),
            'tanggal_lunas'  => $v['tanggal_lunas'] ?? null,
            'link_desain'    => $v['link_desain'] ?? null,
            'catatan_desain' => $v['catatan_desain'] ?? null,
            'status_desain'  => $v['status_desain'] ?? StatusDesain::BelumDikirim->value,
            'catatan_admin'  => $v['catatan_admin'] ?? null,
        ];
    }

    /** Item dengan pilihan "Lainnya" sudah diganti teks bebasnya, finishing digabung koma. */
    public function dataItem(): array
    {
        $hasil = [];

        foreach (array_values($this->validated('item')) as $baris) {
            foreach (self::FIELD_PILIHAN as $field) {
                $nilai = $baris[$field] ?? null;
                if ($nilai === Pesanan::LAINNYA) {
                    $nilai = $baris[$field . '_lainnya'] ?? null;
                }
                $baris[$field] = $nilai !== null && trim($nilai) !== '' ? trim($nilai) : null;
                unset($baris[$field . '_lainnya']);
            }

            $finishing = array_map('trim', $baris['finishing'] ?? []);
            $finishing = array_filter($finishing, fn ($f) => $f !== '' && $f !== Pesanan::LAINNYA);
            if (filled($baris['finishing_lainnya'] ?? null)) {
                $finishing[] = trim($baris['finishing_lainnya']);
            }
            $baris['finishing'] = $finishing ? implode(', ', array_unique($finishing)) : null;
            unset($baris['finishing_lainnya']);

            $hasil[] = $baris;
        }

        return $hasil;
    }

    /** Daftar gambar baru: [['jenis' => 'mockup', 'file' => UploadedFile, 'field' => 'mockup.0'], ...] */
    public function gambarBaru(): array
    {
        $hasil = [];
        foreach (['mockup', 'referensi'] as $jenis) {
            foreach ($this->file($jenis, []) as $i => $file) {
                $hasil[] = ['jenis' => $jenis, 'file' => $file, 'field' => "{$jenis}.{$i}"];
            }
        }

        return $hasil;
    }

    /** 0812-3456-7890 / +62 812... / 812... → 6281234567890 */
    public static function normalisasiWhatsapp(string $nomor): string
    {
        $angka = preg_replace('/\D+/', '', $nomor) ?? '';

        if (str_starts_with($angka, '0')) {
            $angka = '62' . substr($angka, 1);
        } elseif (str_starts_with($angka, '8')) {
            $angka = '62' . $angka;
        }

        return $angka;
    }

    private function hitungTotal(): int
    {
        $total = 0;
        foreach ((array) $this->input('item', []) as $baris) {
            $total += (int) ($baris['jumlah'] ?? 0) * (int) ($baris['harga_satuan'] ?? 0);
        }

        return $total;
    }

    // "Rp 2.500" → "2500"; nilai kosong dibiarkan kosong agar aturan required bekerja
    private function keAngka(mixed $nilai): mixed
    {
        if (! is_string($nilai)) {
            return $nilai;
        }

        $nilai = trim($nilai);
        if ($nilai === '') {
            return null;
        }

        // Buang sen ",00" bila ada, lalu semua pemisah ribuan
        $nilai = preg_replace('/,\d{1,2}$/', '', $nilai);
        $angka = preg_replace('/[^\d\-]/', '', $nilai);

        return $angka === '' ? $nilai : $angka;
    }
}
