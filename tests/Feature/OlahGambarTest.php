<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Banner;
use App\Models\GaleriProduk;
use App\Models\KategoriProduk;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OlahGambarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('cadangan');
    }

    private function loginAdmin(): void
    {
        $admin = Admin::factory()->create();
        $this->withSession(['admin_logged_in' => true, 'admin_id' => $admin->id]);
    }

    // [lebar, tinggi, mime] dari file di disk public
    private function infoGambar(string $path): array
    {
        $info = getimagesizefromstring(Storage::disk('public')->get($path));

        return [$info[0], $info[1], $info['mime']];
    }

    // ── Upload admin ──────────────────────────────────────

    public function test_upload_produk_dan_galeri_disimpan_webp_maks_1200(): void
    {
        $this->loginAdmin();
        $kategori = KategoriProduk::factory()->create();

        $this->post(route('admin.produk.store'), [
            'nama_produk'   => 'Produk WebP',
            'kategori_id'   => $kategori->id,
            'minimal_pesan' => 50,
            'gambar'        => UploadedFile::fake()->image('besar.jpg', 2400, 1600),
            'galeri'        => [UploadedFile::fake()->image('tegak.png', 900, 1800)],
        ])->assertSessionHasNoErrors();

        $produk = Produk::firstOrFail();

        $this->assertStringStartsWith('produk/', $produk->gambar);
        $this->assertStringEndsWith('.webp', $produk->gambar);
        $this->assertSame([1200, 800, 'image/webp'], $this->infoGambar($produk->gambar));

        $galeri = $produk->galeri()->firstOrFail();
        $this->assertStringStartsWith('galeri/', $galeri->nama_file);
        $this->assertSame([600, 1200, 'image/webp'], $this->infoGambar($galeri->nama_file));
    }

    public function test_gambar_kecil_tidak_diperbesar(): void
    {
        $this->loginAdmin();
        $kategori = KategoriProduk::factory()->create();

        $this->post(route('admin.produk.store'), [
            'nama_produk'   => 'Produk Kecil',
            'kategori_id'   => $kategori->id,
            'minimal_pesan' => 1,
            'gambar'        => UploadedFile::fake()->image('kecil.png', 400, 300),
        ])->assertSessionHasNoErrors();

        $this->assertSame([400, 300, 'image/webp'], $this->infoGambar(Produk::firstOrFail()->gambar));
    }

    public function test_upload_banner_desktop_dan_mobile_mengikuti_batas_masing_masing(): void
    {
        $this->loginAdmin();

        $this->post(route('admin.banner.store'), [
            'judul'         => 'Banner WebP',
            'gambar'        => UploadedFile::fake()->image('desktop.jpg', 2400, 1000),
            'gambar_mobile' => UploadedFile::fake()->image('mobile.jpg', 1500, 2000),
            'aktif'         => '1',
        ])->assertSessionHasNoErrors();

        $banner = Banner::firstOrFail();

        $this->assertSame([1920, 800, 'image/webp'], $this->infoGambar($banner->gambar));
        $this->assertSame([1080, 1440, 'image/webp'], $this->infoGambar($banner->gambar_mobile));
    }

    public function test_upload_kategori_disimpan_webp(): void
    {
        $this->loginAdmin();

        $this->post(route('admin.kategori-produk.store'), [
            'nama_kategori' => 'Kategori WebP',
            'gambar'        => UploadedFile::fake()->image('k.png', 1408, 768),
        ])->assertSessionHasNoErrors();

        $kategori = KategoriProduk::firstOrFail();

        $this->assertStringStartsWith('uploads/kategori/', $kategori->gambar);
        $this->assertSame([1200, 655, 'image/webp'], $this->infoGambar($kategori->gambar));
    }

    public function test_ganti_gambar_produk_menghapus_file_lama_dan_menyimpan_webp(): void
    {
        $this->loginAdmin();
        $lama   = UploadedFile::fake()->image('lama.jpg')->store('produk', 'public');
        $produk = Produk::factory()->create(['gambar' => $lama]);

        $this->put(route('admin.produk.update', $produk), [
            'nama_produk'   => $produk->nama_produk,
            'kategori_id'   => $produk->kategori_id,
            'minimal_pesan' => 10,
            'gambar'        => UploadedFile::fake()->image('baru.jpg', 1600, 1600),
        ])->assertSessionHasNoErrors();

        $produk->refresh();
        Storage::disk('public')->assertMissing($lama);
        $this->assertSame([1200, 1200, 'image/webp'], $this->infoGambar($produk->gambar));
    }

    // ── Perintah gambar:kompres ───────────────────────────

    /** Data lama: file JPG/PNG langsung di disk public, seperti sebelum ada kompresi. */
    private function dataLama(): array
    {
        $kategori = KategoriProduk::factory()->create([
            'gambar' => UploadedFile::fake()->image('k.png', 1408, 768)->store('uploads/kategori', 'public'),
        ]);
        $produk = Produk::factory()->create([
            'kategori_id' => $kategori->id,
            'gambar'      => UploadedFile::fake()->image('p.jpg', 2000, 1000)->store('produk', 'public'),
        ]);
        $galeri = GaleriProduk::create([
            'produk_id' => $produk->id,
            'nama_file' => UploadedFile::fake()->image('g.png', 800, 600)->store('galeri', 'public'),
        ]);
        $banner = Banner::create([
            'judul'         => 'Banner lama',
            'gambar'        => UploadedFile::fake()->image('d.png', 2500, 1000)->store('banner', 'public'),
            'gambar_mobile' => UploadedFile::fake()->image('m.jpg', 1200, 1600)->store('banner', 'public'),
            'aktif'         => true,
        ]);

        return compact('kategori', 'produk', 'galeri', 'banner');
    }

    public function test_kompres_dry_run_tidak_mengubah_apa_pun(): void
    {
        $data = $this->dataLama();
        $sebelum = Storage::disk('public')->allFiles();

        $this->artisan('gambar:kompres', ['--dry-run' => true])
            ->expectsOutputToContain('[DRY-RUN] 5 dikompres')
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing($sebelum, Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('cadangan')->allFiles());
        $this->assertSame($data['produk']->gambar, $data['produk']->fresh()->gambar);
        $this->assertSame($data['banner']->gambar_mobile, $data['banner']->fresh()->gambar_mobile);
    }

    public function test_kompres_mengubah_path_db_membuat_cadangan_dan_menghapus_asli(): void
    {
        $data = $this->dataLama();

        $asli = [
            'kategori' => $data['kategori']->gambar,
            'produk'   => $data['produk']->gambar,
            'galeri'   => $data['galeri']->nama_file,
            'desktop'  => $data['banner']->gambar,
            'mobile'   => $data['banner']->gambar_mobile,
        ];
        $isiProdukAsli = Storage::disk('public')->get($asli['produk']);

        $this->artisan('gambar:kompres')->assertSuccessful();

        $baru = [
            'kategori' => $data['kategori']->fresh()->gambar,
            'produk'   => $data['produk']->fresh()->gambar,
            'galeri'   => $data['galeri']->fresh()->nama_file,
            'desktop'  => $data['banner']->fresh()->gambar,
            'mobile'   => $data['banner']->fresh()->gambar_mobile,
        ];

        foreach ($asli as $kunci => $path) {
            // Nama sama, folder sama, ekstensi .webp
            $this->assertSame(preg_replace('/\.(png|jpe?g)$/', '.webp', $path), $baru[$kunci]);
            Storage::disk('public')->assertExists($baru[$kunci]);
            Storage::disk('public')->assertMissing($path);
            Storage::disk('cadangan')->assertExists($path);
        }

        $this->assertSame($isiProdukAsli, Storage::disk('cadangan')->get($asli['produk']));

        $this->assertSame([1200, 600, 'image/webp'], $this->infoGambar($baru['produk']));
        $this->assertSame([800, 600, 'image/webp'], $this->infoGambar($baru['galeri']));
        $this->assertSame([1920, 768, 'image/webp'], $this->infoGambar($baru['desktop']));
        $this->assertSame([1080, 1440, 'image/webp'], $this->infoGambar($baru['mobile']));
    }

    public function test_kompres_idempoten_dan_melewati_file_hilang(): void
    {
        $this->dataLama();
        Produk::factory()->create(['gambar' => 'produk/tidak-ada.jpg']);

        $this->artisan('gambar:kompres')
            ->expectsOutputToContain('5 dikompres, 0 dilewati (sudah WebP), 1 file hilang')
            ->assertSuccessful();

        $setelahPertama = Storage::disk('public')->allFiles();

        $this->artisan('gambar:kompres')
            ->expectsOutputToContain('0 dikompres, 5 dilewati (sudah WebP), 1 file hilang')
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing($setelahPertama, Storage::disk('public')->allFiles());
        $this->assertSame('produk/tidak-ada.jpg', Produk::where('gambar', 'like', '%tidak-ada%')->value('gambar'));
    }
}
