<?php

namespace Tests\Feature;

use App\Models\KategoriProduk;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HalamanPublikTest extends TestCase
{
    use RefreshDatabase;

    public function test_beranda_tampil_dengan_data(): void
    {
        Produk::factory()->create(['nama_produk' => 'Paperbag Kraft Uji']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Paperbag Kraft Uji');
    }

    public function test_halaman_kategori_tampil(): void
    {
        $kategori = KategoriProduk::factory()->create(['slug' => 'paperbag-uji']);
        Produk::factory()->create(['kategori_id' => $kategori->id, 'nama_produk' => 'Produk Kategori Uji']);

        $this->get('/kategori/paperbag-uji')
            ->assertOk()
            ->assertSee('Produk Kategori Uji');
    }

    public function test_halaman_produk_tampil(): void
    {
        Produk::factory()->create(['slug' => 'box-uji', 'nama_produk' => 'Box Uji']);

        $this->get('/produk/box-uji')
            ->assertOk()
            ->assertSee('Box Uji');
    }

    public function test_slug_kategori_tidak_ada_404(): void
    {
        $this->get('/kategori/tidak-ada')->assertNotFound();
    }

    public function test_slug_produk_tidak_ada_404(): void
    {
        $this->get('/produk/tidak-ada')->assertNotFound();
    }

    public function test_header_keamanan_terpasang(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Di luar produksi tidak boleh memblokir iframe (pratinjau HP di lokal)
        $response->assertHeaderMissing('X-Frame-Options');
        $response->assertHeaderMissing('Content-Security-Policy');
        // HSTS hanya untuk HTTPS
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_header_anti_iframe_dan_hsts_di_produksi_https(): void
    {
        $this->app['env'] = 'production';

        $response = $this->get('https://localhost/');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
