<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Banner;
use App\Models\KategoriProduk;
use App\Models\Produk;
use App\Services\OlahGambar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Upload gambar tidak boleh membuat server fatal/500: resolusi berlebih ditolak
 * lewat validasi, kegagalan GD menjadi error form, dan memori disiapkan dulu.
 */
class UnggahGambarAmanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function loginAdmin(): void
    {
        $admin = Admin::factory()->create();
        $this->withSession(['admin_logged_in' => true, 'admin_id' => $admin->id]);
    }

    /**
     * PNG berisi header saja (tanpa piksel) dengan dimensi bebas. Cukup untuk
     * getimagesize/validasi, tanpa perlu membuat gambar raksasa di memori.
     */
    private function pngHeader(int $lebar, int $tinggi): string
    {
        $ihdr = pack('NNCCCCC', $lebar, $tinggi, 8, 6, 0, 0, 0);
        $chunk = fn (string $jenis, string $isi) => pack('N', strlen($isi)) . $jenis . $isi
            . pack('N', crc32($jenis . $isi));

        return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', $ihdr) . $chunk('IEND', '');
    }

    private function filePng(string $nama, int $lebar, int $tinggi): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, $this->pngHeader($lebar, $tinggi));
    }

    /** JPEG asli dengan tag EXIF Orientation (disisipkan setelah penanda SOI). */
    private function jpegDenganOrientasi(int $lebar, int $tinggi, int $orientasi): string
    {
        $gambar = imagecreatetruecolor($lebar, $tinggi);
        ob_start();
        imagejpeg($gambar, null, 70);
        $jpeg = ob_get_clean();

        $tiff = 'II*' . "\x00" . pack('V', 8)            // header TIFF little-endian, IFD di offset 8
            . pack('v', 1)                                  // 1 entri
            . pack('vvVvv', 0x0112, 3, 1, $orientasi, 0)    // Orientation, SHORT, 1 nilai
            . pack('V', 0);                                 // tidak ada IFD berikutnya
        $app1 = "Exif\x00\x00" . $tiff;

        return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1 . substr($jpeg, 2);
    }

    // ── Validasi dimensi ──────────────────────────────────

    public function test_dimensi_lebih_dari_8000_piksel_ditolak_validasi_produk(): void
    {
        $this->loginAdmin();
        $kategori = KategoriProduk::factory()->create();

        $this->from(route('admin.produk.create'))
            ->post(route('admin.produk.store'), [
                'nama_produk'   => 'Produk Raksasa',
                'kategori_id'   => $kategori->id,
                'minimal_pesan' => 10,
                'gambar'        => $this->filePng('besar.png', 9000, 1000),
                'galeri'        => [$this->filePng('galeri.png', 1000, 8500)],
            ])
            ->assertRedirect(route('admin.produk.create'))
            ->assertSessionHasErrors([
                'gambar'   => 'Gambar produk terlalu besar. Lebar dan tinggi maksimal 8000 piksel.',
                'galeri.0' => 'Foto galeri terlalu besar. Lebar dan tinggi maksimal 8000 piksel.',
            ]);

        $this->assertSame(0, Produk::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_dimensi_berlebih_ditolak_di_banner_kategori_dan_galeri(): void
    {
        $this->loginAdmin();

        $this->post(route('admin.banner.store'), [
            'judul'         => 'Banner',
            'gambar'        => UploadedFile::fake()->image('d.jpg', 1920, 800),
            'gambar_mobile' => $this->filePng('m.png', 1000, 9000),
        ])->assertSessionHasErrors(['gambar_mobile' => 'Gambar mobile terlalu besar. Lebar dan tinggi maksimal 8000 piksel.']);

        $this->post(route('admin.kategori-produk.store'), [
            'nama_kategori' => 'Kategori',
            'gambar'        => $this->filePng('k.png', 8001, 100),
        ])->assertSessionHasErrors(['gambar' => 'Gambar kategori terlalu besar. Lebar dan tinggi maksimal 8000 piksel.']);

        $produk = Produk::factory()->create();
        $this->post(route('admin.produk.galeri.tambah', $produk), [
            'gambar' => [$this->filePng('g.png', 9000, 9000)],
        ])->assertSessionHasErrors(['gambar.0' => 'Foto galeri terlalu besar. Lebar dan tinggi maksimal 8000 piksel.']);

        $this->assertSame(0, Banner::count());
        $this->assertSame(0, KategoriProduk::where('nama_kategori', 'Kategori')->count());
        $this->assertSame(0, $produk->galeri()->count());
    }

    // ── Batas megapiksel & memori di OlahGambar ───────────

    public function test_olah_gambar_menolak_lebih_dari_40_megapiksel_tanpa_decode(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Maksimal 40 megapiksel');

        // 8000×6000 lolos aturan dimensi, tetapi 48 MP terlalu berat untuk diproses
        app(OlahGambar::class)->keWebp($this->pngHeader(8000, 6000), OlahGambar::UMUM);
    }

    public function test_resolusi_lolos_validasi_tapi_lebih_40mp_menjadi_error_form_bukan_500(): void
    {
        $this->loginAdmin();
        $kategori = KategoriProduk::factory()->create();

        $this->from(route('admin.produk.create'))
            ->post(route('admin.produk.store'), [
                'nama_produk'   => 'Produk 48MP',
                'kategori_id'   => $kategori->id,
                'minimal_pesan' => 10,
                'gambar'        => $this->filePng('48mp.png', 8000, 6000),
            ])
            ->assertStatus(302)
            ->assertRedirect(route('admin.produk.create'))
            ->assertSessionHasErrors('gambar')
            ->assertSessionHasInput('nama_produk', 'Produk 48MP');

        $this->assertStringContainsString('megapiksel', session('errors')->first('gambar'));
        $this->assertSame(0, Produk::count());
    }

    public function test_memory_limit_dinaikkan_secukupnya_sebelum_decode(): void
    {
        $limitAsli = ini_get('memory_limit');
        $isi       = $this->jpegDenganOrientasi(3000, 3000, 1);

        // Batas sempit: sisa ~40 MB, padahal decode 3000×3000 butuh ±65 MB
        $sempit = (int) ceil((memory_get_usage(true) + 40 * 1024 * 1024) / 1048576);
        ini_set('memory_limit', $sempit . 'M');

        try {
            $webp = app(OlahGambar::class)->keWebp($isi, OlahGambar::UMUM);

            $this->assertNotSame($sempit . 'M', ini_get('memory_limit'));
            $info = getimagesizefromstring($webp);
            $this->assertSame([1200, 1200, 'image/webp'], [$info[0], $info[1], $info['mime']]);
        } finally {
            ini_set('memory_limit', $limitAsli);
        }
    }

    public function test_rotasi_exif_tetap_benar_setelah_diperkecil_dulu(): void
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('Ekstensi exif tidak aktif.');
        }

        // Foto HP mendatar 2400×1000 dengan Orientation=6 (putar 90° searah jarum jam)
        $webp = app(OlahGambar::class)->keWebp($this->jpegDenganOrientasi(2400, 1000, 6), OlahGambar::UMUM);
        $info = getimagesizefromstring($webp);

        // Tampil tegak 1000×2400 → muat di 1200×1200 menjadi 500×1200
        $this->assertSame([500, 1200], [$info[0], $info[1]]);

        // Banner mobile 1080×1440: hasil tegak tetap mematuhi batas tinggi 1440
        $webp = app(OlahGambar::class)->keWebp($this->jpegDenganOrientasi(3000, 1500, 8), OlahGambar::BANNER_MOBILE);
        $info = getimagesizefromstring($webp);
        $this->assertSame([720, 1440], [$info[0], $info[1]]);
    }

    // ── Kegagalan GD → error validasi (service di-mock) ───

    private function olahGambarGagal(): void
    {
        $this->mock(OlahGambar::class, function ($mock) {
            $mock->shouldReceive('simpan')->andThrow(new RuntimeException('Format gambar tidak dikenali.'));
        });
    }

    public function test_gagal_gd_di_produk_store_update_dan_galeri_menjadi_error_form(): void
    {
        $this->loginAdmin();
        $this->olahGambarGagal();
        $kategori = KategoriProduk::factory()->create();

        $this->from(route('admin.produk.create'))
            ->post(route('admin.produk.store'), [
                'nama_produk'   => 'Produk Gagal',
                'kategori_id'   => $kategori->id,
                'minimal_pesan' => 5,
                'gambar'        => UploadedFile::fake()->image('a.jpg', 100, 100),
            ])
            ->assertRedirect(route('admin.produk.create'))
            ->assertSessionHasErrors('gambar')
            ->assertSessionHasInput('nama_produk', 'Produk Gagal');
        $this->assertSame(0, Produk::where('nama_produk', 'Produk Gagal')->count());
        $this->assertStringContainsString('gagal diproses: Format gambar tidak dikenali.', session('errors')->first('gambar'));

        $produk = Produk::factory()->create(['nama_produk' => 'Nama Lama', 'gambar' => 'produk/lama.webp']);

        $this->from(route('admin.produk.edit', $produk))
            ->put(route('admin.produk.update', $produk), [
                'nama_produk'   => 'Nama Baru',
                'kategori_id'   => $produk->kategori_id,
                'minimal_pesan' => 5,
                'gambar'        => UploadedFile::fake()->image('b.jpg', 100, 100),
            ])
            ->assertRedirect(route('admin.produk.edit', $produk))
            ->assertSessionHasErrors('gambar')
            ->assertSessionHasInput('nama_produk', 'Nama Baru');
        $this->assertSame('Nama Lama', $produk->fresh()->nama_produk);
        $this->assertSame('produk/lama.webp', $produk->fresh()->gambar);

        $this->from(route('admin.produk.edit', $produk))
            ->post(route('admin.produk.galeri.tambah', $produk), [
                'gambar' => [UploadedFile::fake()->image('g.jpg', 100, 100)],
            ])
            ->assertRedirect(route('admin.produk.edit', $produk))
            ->assertSessionHasErrors('gambar.0');
        $this->assertSame(0, $produk->galeri()->count());
    }

    public function test_gagal_gd_di_kategori_menjadi_error_form(): void
    {
        $this->loginAdmin();
        $this->olahGambarGagal();

        $this->from(route('admin.kategori-produk.create'))
            ->post(route('admin.kategori-produk.store'), [
                'nama_kategori' => 'Kategori Gagal',
                'gambar'        => UploadedFile::fake()->image('k.jpg', 100, 100),
            ])
            ->assertRedirect(route('admin.kategori-produk.create'))
            ->assertSessionHasErrors('gambar')
            ->assertSessionHasInput('nama_kategori', 'Kategori Gagal');

        $kategori = KategoriProduk::factory()->create(['nama_kategori' => 'Tetap']);

        $this->from(route('admin.kategori-produk.edit', $kategori))
            ->put(route('admin.kategori-produk.update', $kategori), [
                'nama_kategori' => 'Berubah',
                'gambar'        => UploadedFile::fake()->image('k.jpg', 100, 100),
            ])
            ->assertRedirect(route('admin.kategori-produk.edit', $kategori))
            ->assertSessionHasErrors('gambar');

        $this->assertSame(0, KategoriProduk::where('nama_kategori', 'Kategori Gagal')->count());
        $this->assertSame('Tetap', $kategori->fresh()->nama_kategori);
    }

    public function test_gagal_gd_di_banner_menjadi_error_form_dan_file_yang_terlanjur_tersimpan_dihapus(): void
    {
        $this->loginAdmin();

        // Gambar desktop berhasil disimpan, gambar mobile gagal diproses GD
        $this->mock(OlahGambar::class, function ($mock) {
            $mock->shouldReceive('simpan')->once()->andReturnUsing(function () {
                Storage::disk('public')->put('banner/desktop.webp', 'isi');

                return 'banner/desktop.webp';
            });
            $mock->shouldReceive('simpan')->once()->andThrow(new RuntimeException('Konversi ke WebP gagal.'));
        });

        $this->from(route('admin.banner.create'))
            ->post(route('admin.banner.store'), [
                'judul'         => 'Banner Gagal',
                'gambar'        => UploadedFile::fake()->image('d.jpg', 100, 100),
                'gambar_mobile' => UploadedFile::fake()->image('m.jpg', 100, 100),
            ])
            ->assertRedirect(route('admin.banner.create'))
            ->assertSessionHasErrors('gambar_mobile')
            ->assertSessionDoesntHaveErrors('gambar')
            ->assertSessionHasInput('judul', 'Banner Gagal');

        $this->assertSame(0, Banner::count());
        Storage::disk('public')->assertMissing('banner/desktop.webp');
    }

    public function test_gagal_gd_di_banner_update_menjadi_error_form(): void
    {
        $this->loginAdmin();
        $this->olahGambarGagal();

        $banner = Banner::create([
            'judul'         => 'Lama',
            'gambar'        => 'banner/lama.webp',
            'gambar_mobile' => 'banner/lama-m.webp',
            'aktif'         => true,
        ]);

        $this->from(route('admin.banner.edit', $banner))
            ->put(route('admin.banner.update', $banner), [
                'judul'  => 'Baru',
                'gambar' => UploadedFile::fake()->image('d.jpg', 100, 100),
            ])
            ->assertRedirect(route('admin.banner.edit', $banner))
            ->assertSessionHasErrors('gambar');

        $this->assertSame('Lama', $banner->fresh()->judul);
        $this->assertSame('banner/lama.webp', $banner->fresh()->gambar);
    }

    // ── config/usaha.php ──────────────────────────────────

    private function muatConfigUsaha(array $env): array
    {
        $simpan = [];
        foreach ($env as $kunci => $nilai) {
            $simpan[$kunci] = [$_ENV[$kunci] ?? null, $_SERVER[$kunci] ?? null, getenv($kunci)];
            $_ENV[$kunci] = $_SERVER[$kunci] = $nilai;
            putenv("$kunci=$nilai");
        }

        try {
            return require config_path('usaha.php');
        } finally {
            foreach ($simpan as $kunci => [$e, $s, $p]) {
                if ($e === null) { unset($_ENV[$kunci]); } else { $_ENV[$kunci] = $e; }
                if ($s === null) { unset($_SERVER[$kunci]); } else { $_SERVER[$kunci] = $s; }
                putenv($p === false ? $kunci : "$kunci=$p");
            }
        }
    }

    public function test_env_usaha_kosong_menjadi_null_atau_default(): void
    {
        $config = $this->muatConfigUsaha([
            'USAHA_LAT'   => '',
            'USAHA_LNG'   => '',
            'USAHA_NAMA'  => '',
            'USAHA_EMAIL' => '',
        ]);

        $this->assertNull($config['geo']['lat']);
        $this->assertNull($config['geo']['lng']);
        $this->assertSame('Senggani Paperbag & Packaging', $config['nama']);
        $this->assertSame('sengganipaperbag@gmail.com', $config['email']);
    }

    public function test_env_usaha_geo_terisi_dibaca_sebagai_angka(): void
    {
        $config = $this->muatConfigUsaha(['USAHA_LAT' => '-7.8291', 'USAHA_LNG' => '110.4007']);

        $this->assertSame(-7.8291, $config['geo']['lat']);
        $this->assertSame(110.4007, $config['geo']['lng']);

        $config = $this->muatConfigUsaha(['USAHA_LAT' => 'bukan-angka', 'USAHA_LNG' => '']);
        $this->assertNull($config['geo']['lat']);
        $this->assertNull($config['geo']['lng']);
    }
}
