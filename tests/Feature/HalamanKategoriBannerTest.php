<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Banner;
use App\Models\KategoriProduk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HalamanKategoriBannerTest extends TestCase
{
    use RefreshDatabase;

    private function loginAdmin(): void
    {
        $admin = Admin::factory()->create();
        $this->withSession(['admin_logged_in' => true, 'admin_id' => $admin->id]);
    }

    public function test_halaman_semua_kategori_menampilkan_semua(): void
    {
        $kategori = collect(range(1, 8))->map(fn ($i) => KategoriProduk::factory()->create([
            'nama_kategori' => "Kategori Uji {$i}",
        ]));

        $response = $this->get(route('kategori.index'))
            ->assertOk()
            ->assertViewIs('web.kategori.semua')
            ->assertViewHas('kategori', fn ($data) => $data->count() === 8);

        foreach ($kategori as $item) {
            $response->assertSee($item->nama_kategori);
        }
    }

    public function test_beranda_hanya_mengirim_6_kategori_dan_total(): void
    {
        $semua = KategoriProduk::factory()->count(8)->create();
        // Beranda mengurutkan berdasarkan id, jadi yang tampil 6 id terkecil
        $enamPertama = $semua->pluck('id')->sort()->take(6)->values()->all();

        $this->get('/')
            ->assertOk()
            ->assertViewHas('kategori', fn ($data) => $data->count() === 6
                && $data->pluck('id')->all() === $enamPertama)
            ->assertViewHas('totalKategori', 8);
    }

    public function test_tambah_banner_tanpa_gambar_mobile_gagal(): void
    {
        Storage::fake('public');
        $this->loginAdmin();

        $this->post(route('admin.banner.store'), [
            'judul'  => 'Banner Uji',
            'gambar' => UploadedFile::fake()->image('desktop.jpg'),
        ])->assertSessionHasErrors(['gambar_mobile' => 'Gambar mobile wajib diunggah.']);

        $this->assertSame(0, Banner::count());
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    public function test_tambah_banner_dengan_dua_gambar_berhasil(): void
    {
        Storage::fake('public');
        $this->loginAdmin();

        $this->post(route('admin.banner.store'), [
            'judul'         => 'Banner Uji',
            'gambar'        => UploadedFile::fake()->image('desktop.jpg'),
            'gambar_mobile' => UploadedFile::fake()->image('mobile.jpg'),
            'aktif'         => '1',
        ])->assertRedirect(route('admin.banner.index'))
            ->assertSessionHasNoErrors();

        $banner = Banner::firstOrFail();
        Storage::disk('public')->assertExists([$banner->gambar, $banner->gambar_mobile]);
    }

    public function test_ubah_banner_yang_sudah_punya_gambar_mobile_tanpa_upload_berhasil(): void
    {
        Storage::fake('public');
        $this->loginAdmin();

        $gambar       = UploadedFile::fake()->image('d.jpg')->store('banner', 'public');
        $gambarMobile = UploadedFile::fake()->image('m.jpg')->store('banner', 'public');
        $banner       = Banner::create([
            'judul' => 'Lama', 'gambar' => $gambar, 'gambar_mobile' => $gambarMobile, 'aktif' => true,
        ]);

        $this->put(route('admin.banner.update', $banner), ['judul' => 'Baru', 'aktif' => '1'])
            ->assertRedirect(route('admin.banner.index'))
            ->assertSessionHasNoErrors();

        $banner->refresh();
        $this->assertSame('Baru', $banner->judul);
        $this->assertSame($gambarMobile, $banner->gambar_mobile);
        Storage::disk('public')->assertExists([$gambar, $gambarMobile]);
    }

    public function test_ubah_banner_tanpa_gambar_mobile_dan_tanpa_upload_gagal(): void
    {
        Storage::fake('public');
        $this->loginAdmin();

        $gambar = UploadedFile::fake()->image('d.jpg')->store('banner', 'public');
        $banner = Banner::create(['judul' => 'Lama', 'gambar' => $gambar, 'aktif' => true]);

        $this->put(route('admin.banner.update', $banner), ['judul' => 'Baru'])
            ->assertSessionHasErrors(['gambar_mobile' => 'Gambar mobile wajib diunggah.']);

        $this->assertSame('Lama', $banner->refresh()->judul);
    }

    public function test_ubah_banner_lama_dengan_upload_gambar_mobile_berhasil(): void
    {
        Storage::fake('public');
        $this->loginAdmin();

        $gambar = UploadedFile::fake()->image('d.jpg')->store('banner', 'public');
        $banner = Banner::create(['judul' => 'Lama', 'gambar' => $gambar, 'aktif' => true]);

        $this->put(route('admin.banner.update', $banner), [
            'judul'         => 'Baru',
            'gambar_mobile' => UploadedFile::fake()->image('m.jpg'),
        ])->assertSessionHasNoErrors();

        $banner->refresh();
        $this->assertNotNull($banner->gambar_mobile);
        Storage::disk('public')->assertExists([$gambar, $banner->gambar_mobile]);
    }
}
