<?php

namespace App\Http\Requests;

use App\Enums\MetodeBayar;
use App\Enums\StatusDesain;
use App\Enums\StatusProduksi;
use App\Http\Requests\Concerns\AngkaRupiah;
use App\Http\Requests\Concerns\PeriksaNomorSpp;
use App\Http\Requests\Concerns\PesanIndonesia;
use App\Models\Pesanan;
use App\Models\PesananFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
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
 * - item[i][harga_satuan]: OPSIONAL (kosong = belum dihitung), bila diisi minimal Rp 1. Wajib saat
 *   edit SPP yang sudah melewati Hitung Harga (menunggu_dp dst.).
 * - mockup[] dan referensi[]: gambar baru (maks 5 MB per file, total maks 10 file per order).
 * - nomor_spp: TIDAK dipakai saat tambah (nomor diberikan saat Catat DP). Saat edit SPP yang sudah
 *   bernomor: angka bulat ≥1 unik (boleh "#176" / "SPP #176"; "12.5" / "1e3" ditolak); mengganti
 *   nomor ditolak dengan error di kunci "konfirmasi_ganti_nomor" kecuali dikirim konfirmasi_ganti_nomor=1.
 *   Saat edit SPP yang belum bernomor, nomor_spp diabaikan.
 * - dp & tanggal_lunas: diabaikan saat tambah dan saat edit SPP yang belum masuk produksi
 *   (DP dicatat lewat "Catat DP", pelunasan lewat "Catat lunas"). Untuk SPP yang sudah masuk produksi
 *   DP wajib minimal Rp 1; untuk status pengiriman/selesai tanggal_lunas wajib (kunci pelunasan).
 * - customer_id (opsional) + perbarui_customer=1 (opsional): lihat PesananService::tentukanCustomer.
 * - lepas_customer=1 (opsional, saat edit): customer_id kosong berarti SPP dilepas dari customer lama
 *   dan customer ditentukan ulang dari isian (pemilik WA bila ada, jika tidak customer baru).
 *   Tanpa lepas_customer, customer_id kosong saat edit = customer lama tetap.
 * - item[i][panjang|lebar|tinggi]: boleh koma desimal ("12,5" → 12.5).
 * - ongkir, diskon (opsional, rupiah >= 0, kosong = 0): ikut total tagihan (subtotal + ongkir − diskon).
 *   Diskon tidak boleh melebihi subtotal produk (dicek bila semua harga item terisi). Saat edit, field
 *   yang tidak dikirim = nilai lama. Setelah SPP lunas (tanggal_lunas tersimpan dan tetap diisi),
 *   ongkir & diskon dikunci: perubahan ditolak sampai tanggal lunas dikosongkan. Begitu juga jumlah dan
 *   harga satuan item (dibandingkan per urutan; menambah/menghapus item juga ditolak). Isian teks item
 *   (bahan, finishing, catatan, dsb.) tetap boleh diubah.
 * - metode_dp, metode_lunas (opsional, App\Enums\MetodeBayar): hanya untuk SPP yang sudah masuk produksi;
 *   metode_dp wajib bila DP diisi/diubah lewat Edit, metode_lunas wajib bila tanggal lunas diisi/diubah;
 *   kosong hanya diizinkan untuk data lama yang nilainya tidak berubah. metode_lunas otomatis null bila
 *   tanggal_lunas kosong. Field yang tidak dikirim = nilai lama.
 * - DP ikut dikunci setelah lunas (sama seperti ongkir/diskon/harga).
 */
class SimpanPesananRequest extends FormRequest
{
    use AngkaRupiah, PeriksaNomorSpp, PesanIndonesia;

    // Batas total order agar muat di kolom decimal(15,2)
    public const MAKS_TOTAL = 9_999_999_999_999;

    public const PESAN_DP_TERKUNCI = 'DP dikunci karena SPP sudah lunas. Kosongkan tanggal lunas dulu bila perlu dikoreksi.';

    public const PESAN_ITEM_TERKUNCI = 'Jumlah dan harga item dikunci karena SPP sudah lunas. Kosongkan tanggal lunas dulu bila perlu dikoreksi.';

    private const PESAN_BUKAN_GAMBAR = 'File harus berupa gambar JPG, PNG, atau WebP.';

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
                foreach (['panjang', 'lebar', 'tinggi'] as $ukuran) {
                    if (is_array($baris) && array_key_exists($ukuran, $baris)) {
                        $item[$i][$ukuran] = $this->keDesimal($baris[$ukuran]);
                    }
                }
                if (is_array($baris) && array_key_exists('harga_satuan', $baris)) {
                    $item[$i]['harga_satuan'] = $this->keAngka($baris['harga_satuan']);
                }
                if (is_array($baris) && array_key_exists('jumlah', $baris)) {
                    $item[$i]['jumlah'] = $this->keAngka($baris['jumlah']);
                }
            }
            $this->merge(['item' => $item]);
        }

        // "#176" / "SPP #176" → "176". Format lain ("12.5", "1e3", "1.000") dibiarkan apa adanya
        // supaya ditolak aturan integer, bukan diubah diam-diam menjadi angka lain.
        $this->rapikanNomorSpp();

        foreach (['dp', 'ongkir', 'diskon'] as $rupiah) {
            if ($this->has($rupiah)) {
                $this->merge([$rupiah => $this->keAngka($this->input($rupiah))]);
            }
        }
    }

    /** Pesanan yang diedit (null saat tambah). */
    private function pesananEdit(): ?Pesanan
    {
        $pesanan = $this->route('pesanan');

        return $pesanan instanceof Pesanan ? $pesanan : null;
    }

    /** Nomor SPP ikut divalidasi & disimpan hanya saat edit SPP yang sudah bernomor. */
    private function pakaiNomor(): bool
    {
        return (bool) $this->pesananEdit()?->nomor_spp;
    }

    /** DP & tanggal lunas lewat form hanya untuk SPP yang sudah masuk produksi. */
    private function pakaiPembayaran(): bool
    {
        return (bool) $this->pesananEdit()?->status_produksi?->sudahProduksi();
    }

    /** SPP yang sudah dikirim/selesai harus tetap tercatat lunas (kunci pelunasan). */
    private function lunasWajib(): bool
    {
        $status = $this->pesananEdit()?->status_produksi;

        return $status === StatusProduksi::Pengiriman || $status === StatusProduksi::Selesai;
    }

    /** Harga wajib bila SPP sudah melewati Hitung Harga (menunggu_dp, proses, dst.). */
    private function hargaWajib(): bool
    {
        $status = $this->pesananEdit()?->status_produksi;

        return $status !== null && $status !== StatusProduksi::HitungHarga && $status !== StatusProduksi::TidakJadi;
    }

    public function rules(): array
    {
        // bail: file bukan gambar cukup satu galat (tanpa galat ukuran/dimensi menyusul)
        $gambar = ['bail', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . PesananFile::MAKS_KB, 'dimensions:max_width=8000,max_height=8000'];
        $lainnya = Pesanan::LAINNYA;
        $bayar   = $this->pakaiPembayaran();

        return [
            'nomor_spp'              => $this->pakaiNomor() ? ['required', 'integer', 'min:1', 'max:' . self::MAKS_NOMOR] : ['exclude'],
            'konfirmasi_ganti_nomor' => ['nullable', 'boolean'],
            'customer_id'            => ['nullable', 'integer', Rule::exists('customer', 'id')],
            'perbarui_customer'      => ['nullable', 'boolean'],
            'lepas_customer'         => ['nullable', 'boolean'],
            'nama_brand'             => ['nullable', 'string', 'max:150'],
            'jenis_deadline'         => ['required', Rule::in(array_keys(Pesanan::JENIS_DEADLINE))],

            'nama_customer'   => ['required', 'string', 'max:150'],
            'nomor_whatsapp'  => ['required', 'string', 'max:30', 'regex:/^[0-9+\s\-().]+$/'],
            'alamat'          => ['required', 'string', 'max:2000'],
            'kota'            => ['required', 'string', 'max:100'],
            'tanggal_order'   => ['required', 'date'],
            'deadline'        => ['required', 'date', 'after_or_equal:tanggal_order'],
            'dp'              => $bayar ? ['required', 'integer', 'min:1'] : ['exclude'],
            'tanggal_lunas'   => $bayar ? [$this->lunasWajib() ? 'required' : 'nullable', 'date', 'after_or_equal:tanggal_order', 'before_or_equal:today'] : ['exclude'],
            'metode_dp'       => $bayar ? ['nullable', Rule::enum(MetodeBayar::class)] : ['exclude'],
            'metode_lunas'    => $bayar ? ['nullable', Rule::enum(MetodeBayar::class)] : ['exclude'],
            'ongkir'          => ['sometimes', 'nullable', 'integer', 'min:0', 'max:' . self::MAKS_TOTAL],
            'diskon'          => ['sometimes', 'nullable', 'integer', 'min:0', 'max:' . self::MAKS_TOTAL],
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
            'item.*.jenis_cetak'             => ['nullable', 'string', 'max:150'],
            'item.*.jenis_cetak_lainnya'     => ["required_if:item.*.jenis_cetak,{$lainnya}", 'nullable', 'string', 'max:150'],
            'item.*.finishing'               => ['nullable', 'array', 'max:30'],
            'item.*.finishing.*'             => ['string', 'max:150'],
            'item.*.finishing_lainnya'       => ['nullable', 'string', 'max:255'],
            'item.*.aksesori'                => ['nullable', 'string', 'max:255'],
            'item.*.catatan'                 => ['nullable', 'string', 'max:500'],
            'item.*.jumlah'                  => ['required', 'integer', 'min:1', 'max:10000000'],
            'item.*.harga_satuan'            => [$this->hargaWajib() ? 'required' : 'nullable', 'integer', 'min:1', 'max:1000000000'],

            'mockup'      => ['nullable', 'array', 'max:' . PesananFile::MAKS_PER_ORDER],
            'mockup.*'    => $gambar,
            'referensi'   => ['nullable', 'array', 'max:' . PesananFile::MAKS_PER_ORDER],
            'referensi.*' => $gambar,
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $adaGalatDasar = $validator->errors()->isNotEmpty();

            if ($this->pakaiNomor() && ! $validator->errors()->has('nomor_spp')) {
                $this->periksaNomorSpp($validator);
            }

            if ($adaGalatDasar) {
                return; // cek lanjutan butuh data dasar yang sudah valid
            }

            $wa = self::normalisasiWhatsapp((string) $this->input('nomor_whatsapp'));
            if (! preg_match('/^62\d{8,13}$/', $wa)) {
                $validator->errors()->add('nomor_whatsapp', 'Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.');
            }

            $subtotal = $this->hitungTotal();
            if ($subtotal > self::MAKS_TOTAL) {
                $validator->errors()->add('item', 'Total SPP terlalu besar.');
            }

            $pesanan = $this->pesananEdit();
            $ongkir  = $this->nilaiBiaya('ongkir');
            $diskon  = $this->nilaiBiaya('diskon');

            if ($this->hargaItemLengkap() && $diskon > $subtotal) {
                $validator->errors()->add('diskon', 'Diskon tidak boleh melebihi subtotal produk ('
                    . self::rupiah($subtotal) . ').');
            }

            // Kunci setelah lunas: ongkir & diskon tidak boleh berubah selama SPP tetap tercatat lunas
            $tetapLunas = $pesanan?->tanggal_lunas && (! $this->pakaiPembayaran() || $this->filled('tanggal_lunas'));
            if ($tetapLunas && ($ongkir !== (int) $pesanan->ongkir || $diskon !== (int) $pesanan->diskon)) {
                $validator->errors()->add($ongkir !== (int) $pesanan->ongkir ? 'ongkir' : 'diskon',
                    'Ongkir dan diskon dikunci karena SPP sudah lunas. Kosongkan tanggal lunas dulu bila perlu dikoreksi.');
            }

            // Kunci setelah lunas: jumlah & harga satuan item (urut sesuai form) tidak boleh berubah
            if ($tetapLunas && $this->jumlahHargaItem() !== $this->jumlahHargaItemLama($pesanan)) {
                $validator->errors()->add('item', self::PESAN_ITEM_TERKUNCI);
            }

            if ($pesanan && $this->pakaiPembayaran()) {
                $this->periksaDpLunas($validator, $pesanan, $tetapLunas);
            }

            $total = Pesanan::hitungTotalTagihan($subtotal, $ongkir, $diskon);

            // DP yang dibandingkan: isian form (SPP produksi) atau DP yang sudah tercatat
            $dp = $this->pakaiPembayaran() ? (int) $this->input('dp', 0) : (int) ($pesanan?->dp ?? 0);
            if ($dp > $total) {
                $validator->errors()->add($this->pakaiPembayaran() ? 'dp' : 'item',
                    'DP (' . self::rupiah($dp) . ') tidak boleh lebih dari total tagihan (' . self::rupiah($total) . ').');
            }

            if ($this->pakaiPembayaran() && $this->filled('tanggal_lunas') && $total <= 0) {
                $validator->errors()->add('tanggal_lunas', 'Tanggal lunas hanya bisa diisi bila total tagihan lebih dari Rp 0.');
            }

            $sudahAda = $pesanan ? $pesanan->file()->count() : 0;
            $baru = count($this->file('mockup', [])) + count($this->file('referensi', []));
            if ($sudahAda + $baru > PesananFile::MAKS_PER_ORDER) {
                $sisa = max(PesananFile::MAKS_PER_ORDER - $sudahAda, 0);
                $validator->errors()->add('mockup', 'Gambar maksimal ' . PesananFile::MAKS_PER_ORDER . " per SPP. Masih bisa menambah {$sisa} gambar.");
            }
        }];
    }

    /**
     * Edit SPP produksi: DP dikunci selama SPP tetap lunas; metode wajib bila DP atau tanggal lunas
     * diisi/diubah. Metode kosong hanya lolos untuk data lama yang nilainya tidak berubah.
     */
    private function periksaDpLunas(Validator $validator, Pesanan $pesanan, bool $tetapLunas): void
    {
        $dp        = (int) $this->input('dp', 0);
        $dpBerubah = $dp !== (int) $pesanan->dp;

        if ($tetapLunas && $dpBerubah) {
            $validator->errors()->add('dp', self::PESAN_DP_TERKUNCI);
        }

        if ($dpBerubah && $dp > 0 && blank($this->metodeBerlaku('metode_dp', $pesanan))) {
            $validator->errors()->add('metode_dp', 'Pilih metode pembayaran DP karena nominal DP diisi atau diubah.');
        }

        if ($this->filled('tanggal_lunas')) {
            $baru      = Carbon::parse($this->input('tanggal_lunas'))->toDateString();
            $ubahLunas = $baru !== $pesanan->tanggal_lunas?->toDateString();

            if ($ubahLunas && blank($this->metodeBerlaku('metode_lunas', $pesanan))) {
                $validator->errors()->add('metode_lunas', 'Pilih metode pelunasan karena tanggal lunas diisi atau diubah.');
            }
        }
    }

    /** Metode yang akan tersimpan: isian form, atau nilai lama bila field tidak dikirim. */
    private function metodeBerlaku(string $field, Pesanan $pesanan): ?string
    {
        if ($this->has($field)) {
            return $this->input($field) ?: null;
        }

        return $pesanan->{$field}?->value;
    }

    /** Edit SPP bernomor: unik (termasuk SPP yang dihapus) dan konfirmasi ganti nomor. */
    private function periksaNomorSpp(Validator $validator): void
    {
        $nomor   = (int) $this->input('nomor_spp');
        $pesanan = $this->pesananEdit();

        if ($this->periksaNomorUnik($validator, $nomor, $pesanan)) {
            return;
        }

        $lama = (int) $pesanan->nomor_spp;
        if ($nomor !== $lama && ! $this->boolean('konfirmasi_ganti_nomor')) {
            $validator->errors()->add('konfirmasi_ganti_nomor',
                "Nomor SPP akan diganti dari #{$lama} menjadi #{$nomor}. Produksi mungkin sudah menerima nomor lama "
                . 'lewat WhatsApp, jadi kabari mereka setelah menyimpan. Centang konfirmasi lalu simpan lagi bila yakin.');
        }
    }

    public function messages(): array
    {
        return [
            ...$this->pesanUmum(),
            'nomor_spp.integer'       => 'Nomor SPP harus angka bulat, mis. 176.',
            'deadline.after_or_equal' => 'Deadline tidak boleh sebelum tanggal SPP.',
            'dp.required'   => 'DP wajib diisi karena SPP sudah masuk produksi (minimal Rp 1).',
            'dp.min'        => 'DP minimal Rp 1 karena SPP sudah masuk produksi.',
            'tanggal_lunas.required' => 'SPP yang sudah dikirim/selesai harus tetap tercatat lunas. Kembalikan status ke Pesanan Selesai dulu bila pelunasan perlu dikoreksi.',
            'tanggal_lunas.after_or_equal'  => 'Tanggal lunas tidak boleh sebelum tanggal SPP.',
            'tanggal_lunas.before_or_equal' => 'Tanggal lunas tidak boleh setelah hari ini.',
            'item.required'  => 'Tambahkan minimal satu item pesanan.',
            'item.min'       => 'Tambahkan minimal satu item pesanan.',
            'item.*.jenis_packaging_lainnya.required_if' => 'Tulis jenis packaging lainnya.',
            'item.*.jenis_bahan_lainnya.required_if'     => 'Tulis jenis bahan lainnya.',
            'item.*.jenis_cetak_lainnya.required_if'     => 'Tulis jenis cetak lainnya.',
            'item.*.jumlah.min' => 'Jumlah item ke-:position minimal 1.',
            'item.*.harga_satuan.min'      => 'Harga satuan minimal Rp 1.',
            'item.*.harga_satuan.required' => 'Harga satuan item ke-:position wajib diisi karena SPP sudah melewati tahap Hitung Harga.',
            'mockup.*.image'         => self::PESAN_BUKAN_GAMBAR,
            'mockup.*.mimes'         => self::PESAN_BUKAN_GAMBAR,
            'referensi.*.image'      => self::PESAN_BUKAN_GAMBAR,
            'referensi.*.mimes'      => self::PESAN_BUKAN_GAMBAR,
            'mockup.*.dimensions'    => 'Gambar mockup terlalu besar. Lebar dan tinggi maksimal 8000 piksel.',
            'referensi.*.dimensions' => 'Gambar referensi terlalu besar. Lebar dan tinggi maksimal 8000 piksel.',
            'nomor_whatsapp.regex'   => 'Nomor WhatsApp hanya boleh berisi angka, spasi, +, dan -.',
            'customer_id.exists'     => 'Customer yang dipilih tidak ditemukan. Pilih ulang atau kosongkan.',
            'jenis_deadline.in'      => 'Pilih jenis deadline: Siap atau Kirim.',
            'ongkir.integer'         => 'Ongkir harus berupa angka rupiah, mis. 25.000.',
            'ongkir.min'             => 'Ongkir tidak boleh kurang dari Rp 0.',
            'diskon.integer'         => 'Diskon harus berupa angka rupiah, mis. 50.000.',
            'diskon.min'             => 'Diskon tidak boleh kurang dari Rp 0.',
            'metode_dp.enum'         => 'Pilih metode pembayaran DP.',
            'metode_lunas.enum'      => 'Pilih metode pelunasan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nomor_spp'      => 'Nomor SPP',
            'customer_id'    => 'Customer',
            'nama_brand'     => 'Nama brand/acara',
            'jenis_deadline' => 'Jenis deadline',
            'nama_customer'  => 'Nama customer',
            'nomor_whatsapp' => 'Nomor WhatsApp',
            'alamat'         => 'Alamat',
            'kota'           => 'Kota',
            'tanggal_order'  => 'Tanggal SPP',
            'deadline'       => 'Deadline',
            'dp'             => 'DP',
            'tanggal_lunas'  => 'Tanggal lunas',
            'ongkir'         => 'Ongkir',
            'diskon'         => 'Diskon',
            'metode_dp'      => 'Metode DP',
            'metode_lunas'   => 'Metode pelunasan',
            'link_desain'    => 'Link Google Drive',
            'catatan_desain' => 'Catatan desain',
            'status_desain'  => 'Status approval desain',
            'catatan_admin'  => 'Catatan SPP',
            'item'           => 'Item pesanan',
            'item.*.jenis_packaging'         => 'Jenis packaging item ke-:position',
            'item.*.jenis_packaging_lainnya' => 'Jenis packaging lainnya item ke-:position',
            'item.*.panjang'                 => 'Panjang item ke-:position',
            'item.*.lebar'                   => 'Lebar item ke-:position',
            'item.*.tinggi'                  => 'Tinggi item ke-:position',
            'item.*.jenis_bahan'             => 'Jenis bahan item ke-:position',
            'item.*.jenis_bahan_lainnya'     => 'Jenis bahan lainnya item ke-:position',
            'item.*.jenis_cetak'             => 'Jenis cetak item ke-:position',
            'item.*.jenis_cetak_lainnya'     => 'Jenis cetak lainnya item ke-:position',
            'item.*.finishing'               => 'Finishing item ke-:position',
            'item.*.finishing.*'             => 'Finishing item ke-:position',
            'item.*.finishing_lainnya'       => 'Finishing lainnya item ke-:position',
            'item.*.aksesori'                => 'Tali/aksesori item ke-:position',
            'item.*.catatan'                 => 'Catatan item ke-:position',
            'item.*.jumlah'                  => 'Jumlah item ke-:position',
            'item.*.harga_satuan'            => 'Harga satuan item ke-:position',
            'mockup'      => 'Gambar mockup',
            'mockup.*'    => 'Gambar mockup',
            'referensi'   => 'Gambar referensi',
            'referensi.*' => 'Gambar referensi',
        ];
    }

    // ── Data siap simpan (dipakai controller setelah validasi) ──

    /**
     * Kolom tabel pesanan (tanpa customer_id, total, status_produksi). nomor_spp, dp, dan
     * tanggal_lunas hanya ada bila boleh diubah lewat form (lihat pakaiNomor/pakaiPembayaran).
     */
    public function dataPesanan(): array
    {
        $v = $this->validated();

        $data = [
            'nama_customer'  => trim($v['nama_customer']),
            'nama_brand'     => filled($v['nama_brand'] ?? null) ? trim($v['nama_brand']) : null,
            'jenis_deadline' => $v['jenis_deadline'],
            'nomor_whatsapp' => self::normalisasiWhatsapp($v['nomor_whatsapp']),
            'alamat'         => trim($v['alamat']),
            'kota'           => trim($v['kota']),
            'tanggal_order'  => $v['tanggal_order'],
            'deadline'       => $v['deadline'],
            'link_desain'    => $v['link_desain'] ?? null,
            'catatan_desain' => $v['catatan_desain'] ?? null,
            'status_desain'  => $v['status_desain'] ?? StatusDesain::BelumDikirim->value,
            'catatan_admin'  => $v['catatan_admin'] ?? null,
        ];

        if ($this->pakaiNomor()) {
            $data['nomor_spp'] = (int) $v['nomor_spp'];
        }
        if ($this->pakaiPembayaran()) {
            $data['dp']            = (int) ($v['dp'] ?? 0);
            $data['tanggal_lunas'] = $v['tanggal_lunas'] ?? null;

            if (array_key_exists('metode_dp', $v)) {
                $data['metode_dp'] = $v['metode_dp'] ?: null;
            }
            if (array_key_exists('metode_lunas', $v)) {
                $data['metode_lunas'] = $v['metode_lunas'] ?: null;
            }
            if ($data['tanggal_lunas'] === null) {
                $data['metode_lunas'] = null;
            }
        }

        // Ongkir & diskon: tidak dikirim saat edit = nilai lama; saat tambah = 0
        foreach (['ongkir', 'diskon'] as $biaya) {
            if (array_key_exists($biaya, $v) || ! $this->pesananEdit()) {
                $data[$biaya] = (int) ($v[$biaya] ?? 0);
            }
        }

        return $data;
    }

    /** Ongkir/diskon yang akan berlaku: isian form, nilai lama bila tidak dikirim (edit), atau 0. */
    private function nilaiBiaya(string $field): int
    {
        if ($this->has($field)) {
            return (int) $this->input($field, 0);
        }

        return (int) ($this->pesananEdit()?->{$field} ?? 0);
    }

    /** Semua item isian punya harga satuan (diskon baru dibandingkan dengan subtotal bila lengkap). */
    /** @return list<array{0: int, 1: ?int}> [jumlah, harga_satuan] per item kiriman form */
    private function jumlahHargaItem(): array
    {
        return array_map(fn ($b) => [
            (int) ($b['jumlah'] ?? 0),
            isset($b['harga_satuan']) && $b['harga_satuan'] !== '' ? (int) $b['harga_satuan'] : null,
        ], array_values((array) $this->input('item', [])));
    }

    /** @return list<array{0: int, 1: ?int}> [jumlah, harga_satuan] per item tersimpan */
    private function jumlahHargaItemLama(Pesanan $pesanan): array
    {
        return $pesanan->detailPesanan()->get()->map(fn ($d) => [
            (int) $d->jumlah,
            $d->harga_satuan === null ? null : (int) $d->harga_satuan,
        ])->values()->all();
    }

    private function hargaItemLengkap(): bool
    {
        $item = (array) $this->input('item', []);

        foreach ($item as $baris) {
            if (! isset($baris['harga_satuan']) || $baris['harga_satuan'] === '') {
                return false;
            }
        }

        return $item !== [];
    }

    private static function rupiah(int|float $n): string
    {
        return 'Rp ' . number_format($n, 0, ',', '.');
    }

    /** Customer yang dipilih admin (null = cari lewat WA / buat baru). */
    public function customerId(): ?int
    {
        $id = $this->validated('customer_id');

        return $id ? (int) $id : null;
    }

    public function perbaruiCustomer(): bool
    {
        return $this->boolean('perbarui_customer');
    }

    /** Edit: lepas SPP dari customer lama bila customer_id kosong. */
    public function lepasCustomer(): bool
    {
        return $this->boolean('lepas_customer');
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

    // Ukuran cm tidak memakai pemisah ribuan: "12,5" → "12.5". Selain bentuk itu dibiarkan
    // apa adanya (aturan numeric yang menilai); kosong → null.
    private function keDesimal(mixed $nilai): mixed
    {
        if (! is_string($nilai)) {
            return $nilai;
        }

        $nilai = trim($nilai);
        if ($nilai === '') {
            return null;
        }

        return preg_match('/^\d+,\d+$/', $nilai) ? str_replace(',', '.', $nilai) : $nilai;
    }

}
