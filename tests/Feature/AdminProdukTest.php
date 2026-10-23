<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Banner;
use App\Models\GaleriProduk;
use App\Models\KategoriProduk;
use App\Models\Produk;
use App\Models\UkuranProduk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProdukTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $admin = Admin::factory()->create();
        $this->withSession(['admin_logged_in' => true, 'admin_id' => $admin->id]);
    }

    public function test_tambah_produk_dengan_gambar_dan_galeri(): void
    {
        $kategori = KategoriProduk::factory()->create();

        $response = $this->post(route('admin.produk.store'), [
            'nama_produk'   => 'Paperbag Uji',
            'kategori_id'   => $kategori->id,
            'minimal_pesan' => 100,
            'gambar'        => UploadedFile::fake()->image('utama.jpg'),
            'galeri'        => [
                UploadedFile::fake()->image('g1.png'),
                UploadedFile::fake()->image('g2.webp'),
            ],
        ]);

        $produk = Produk::firstOrFail();

        $response->assertRedirect(route('admin.produk.atribut', $produk))
            ->assertSessionHas('success', 'Produk berhasil dibuat. Silakan pilih bahan dan finishing produk.');

        $this->assertSame('paperbag-uji', $produk->slug);
        Storage::disk('public')->assertExists($produk->gambar);
        $this->assertSame(2, $produk->galeri()->count());
        foreach ($produk->galeri as $g) {
            Storage::disk('public')->assertExists($g->nama_file);
        }
    }

    public function test_nama_produk_ganda_tetap_boleh(): void
    {
        $kategori = KategoriProduk::factory()->create();
        $data = ['nama_produk' => 'Box Sama', 'kategori_id' => $kategori->id, 'minimal_pesan' => 1];

        $this->post(route('admin.produk.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('admin.produk.store'), $data)->assertSessionHasNoErrors();

        $this->assertEquals(['box-sama', 'box-sama-2'], Produk::orderBy('id')->pluck('slug')->all());
    }

    public function test_gambar_produk_selain_jpg_png_webp_ditolak(): void
    {
        $kategori = KategoriProduk::factory()->create();

        $this->post(route('admin.produk.store'), [
            'nama_produk'   => 'Produk GIF',
            'kategori_id'   => $kategori->id,
            'minimal_pesan' => 1,
            'gambar'        => UploadedFile::fake()->image('anim.gif'),
        ])->assertSessionHasErrors('gambar');

        $this->assertSame(0, Produk::count());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_gambar_lebih_dari_2mb_ditolak(): void
    {
        $kategori = KategoriProduk::factory()->create();

        $this->post(route('admin.produk.store'), [
            'nama_produk'   => 'Produk Besar',
            'kategori_id'   => $kategori->id,
            'minimal_pesan' => 1,
            'gambar'        => UploadedFile::fake()->image('besar.jpg')->size(2049),
        ])->assertSessionHasErrors('gambar');
    }

    public function test_tambah_galeri_harus_array_dan_mimes(): void
    {
        $produk = Produk::factory()->create();

        $this->post(route('admin.produk.galeri.tambah', $produk), [
            'gambar' => [UploadedFile::fake()->image('a.gif')],
        ])->assertSessionHasErrors('gambar.0');

        $this->post(route('admin.produk.galeri.tambah', $produk), [
            'gambar' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertSessionHas('success', 'Galeri berhasil ditambahkan.');

        $this->assertSame(1, $produk->galeri()->count());
    }

    public function test_ganti_gambar_produk_menghapus_file_lama_setelah_simpan(): void
    {
        $lama   = UploadedFile::fake()->image('lama.jpg')->store('produk', 'public');
        $produk = Produk::factory()->create(['gambar' => $lama]);

        $this->put(route('admin.produk.update', $produk), [
            'nama_produk'   => $produk->nama_produk,
            'kategori_id'   => $produk->kategori_id,
            'minimal_pesan' => 1,
            'gambar'        => UploadedFile::fake()->image('baru.jpg'),
        ])->assertRedirect(route('admin.produk.edit', $produk))
          ->assertSessionHas('success', 'Produk berhasil diperbarui.');

        $produk->refresh();
        Storage::disk('public')->assertMissing($lama);
        Storage::disk('public')->assertExists($produk->gambar);
    }

    public function test_simpan_gagal_file_lama_tetap_dan_file_baru_dibuang(): void
    {
        $lama   = UploadedFile::fake()->image('lama.jpg')->store('produk', 'public');
        $produk = Produk::factory()->create(['gambar' => $lama]);

        // Paksa penyimpanan gagal
        Produk::updating(fn () => throw new \RuntimeException('DB gagal'));

        $this->withoutExceptionHandling();

        try {
            $this->put(route('admin.produk.update', $produk), [
                'nama_produk'   => $produk->nama_produk,
                'kategori_id'   => $produk->kategori_id,
                'minimal_pesan' => 1,
                'gambar'        => UploadedFile::fake()->image('baru.jpg'),
            ]);
            $this->fail('Seharusnya melempar exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('DB gagal', $e->getMessage());
        }

        Storage::disk('public')->assertExists($lama);
        $this->assertSame([$lama], Storage::disk('public')->allFiles());
        $this->assertSame($lama, $produk->fresh()->gambar);
    }

    public function test_tambah_produk_gagal_tidak_meninggalkan_data_dan_file(): void
    {
        $kategori = KategoriProduk::factory()->create();

        // Galeri gagal disimpan: produk ikut dibatalkan (transaksi)
        GaleriProduk::creating(fn () => throw new \RuntimeException('DB gagal'));

        $this->withoutExceptionHandling();

        try {
            $this->post(route('admin.produk.store'), [
                'nama_produk'   => 'Gagal',
                'kategori_id'   => $kategori->id,
                'minimal_pesan' => 1,
                'gambar'        => UploadedFile::fake()->image('utama.jpg'),
                'galeri'        => [UploadedFile::fake()->image('g1.jpg')],
            ]);
            $this->fail('Seharusnya melempar exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('DB gagal', $e->getMessage());
        }

        $this->assertSame(0, Produk::count());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_hapus_produk_menghapus_data_dan_file(): void
    {
        $gambar = UploadedFile::fake()->image('p.jpg')->store('produk', 'public');
        $foto   = UploadedFile::fake()->image('g.jpg')->store('galeri', 'public');
        $produk = Produk::factory()->create(['gambar' => $gambar]);
        GaleriProduk::create(['produk_id' => $produk->id, 'nama_file' => $foto]);

        $this->delete(route('admin.produk.destroy', $produk))
            ->assertRedirect(route('admin.produk.index'))
            ->assertSessionHas('success', 'Produk berhasil dihapus.');

        $this->assertSame(0, Produk::count());
        $this->assertSame(0, GaleriProduk::count());
        Storage::disk('public')->assertMissing([$gambar, $foto]);
    }

    public function test_hapus_kategori_tetap_cascade_ke_produk(): void
    {
        $gambar   = UploadedFile::fake()->image('k.jpg')->store('uploads/kategori', 'public');
        $fotoProd = UploadedFile::fake()->image('p.jpg')->store('produk', 'public');
        $kategori = KategoriProduk::factory()->create(['gambar' => $gambar]);
        Produk::factory()->create(['kategori_id' => $kategori->id, 'gambar' => $fotoProd]);

        $this->delete(route('admin.kategori-produk.destroy', $kategori))
            ->assertRedirect(route('admin.kategori-produk.index'))
            ->assertSessionHas('success', 'Kategori berhasil dihapus.');

        $this->assertSame(0, KategoriProduk::count());
        $this->assertSame(0, Produk::count());
        Storage::disk('public')->assertMissing([$gambar, $fotoProd]);
    }

    public function test_hapus_banner_menghapus_file(): void
    {
        $gambar = UploadedFile::fake()->image('b.jpg')->store('banner', 'public');
        $banner = Banner::create(['judul' => 'B', 'gambar' => $gambar, 'aktif' => true]);

        $this->delete(route('admin.banner.destroy', $banner))
            ->assertRedirect(route('admin.banner.index'))
            ->assertSessionHas('success', 'Banner berhasil dihapus.');

        $this->assertSame(0, Banner::count());
        Storage::disk('public')->assertMissing($gambar);
    }

    public function test_validasi_ukuran(): void
    {
        $produk = Produk::factory()->create();

        $this->post(route('admin.produk.ukuran.tambah', $produk), [
            'nama_ukuran' => 'S',
            'panjang'     => 'abc',
            'lebar'       => -1,
            'tinggi'      => 1000000,
        ])->assertSessionHasErrors(['panjang', 'lebar', 'tinggi']);

        $this->post(route('admin.produk.ukuran.tambah', $produk), [
            'nama_ukuran' => 'M',
            'panjang'     => '20.5',
            'lebar'       => 10,
            'tinggi'      => '',
            'keterangan'  => 'Ukuran standar',
        ])->assertSessionHas('success', 'Ukuran berhasil ditambahkan.');

        $this->assertSame(1, UkuranProduk::count());
        $this->assertNull(UkuranProduk::first()->tinggi);
    }
}
