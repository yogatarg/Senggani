<?php

namespace Tests\Feature;

use App\Models\KategoriProduk;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HalamanProdukTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_produk_tampil(): void
    {
        Produk::factory()->create(['nama_produk' => 'Paperbag Daftar Uji']);

        $this->get('/produk')
            ->assertOk()
            ->assertViewIs('web.produk.index')
            ->assertViewHas('kategoriAktif', null)
            ->assertSee('Paperbag Daftar Uji');
    }

    public function test_filter_kategori_hanya_menampilkan_produk_kategori_itu(): void
    {
        $paperbag = KategoriProduk::factory()->create(['slug' => 'paperbag-uji']);
        $box = KategoriProduk::factory()->create(['slug' => 'box-uji']);
        Produk::factory()->create(['kategori_id' => $paperbag->id, 'nama_produk' => 'Paperbag Kraft Uji']);
        Produk::factory()->create(['kategori_id' => $box->id, 'nama_produk' => 'Box Hampers Uji']);

        $this->get('/produk?kategori=paperbag-uji')
            ->assertOk()
            ->assertViewHas('kategoriAktif', fn ($k) => $k?->is($paperbag))
            ->assertSee('Paperbag Kraft Uji')
            ->assertDontSee('Box Hampers Uji');
    }

    public function test_slug_kategori_asing_dialihkan_permanen_ke_daftar_produk(): void
    {
        Produk::factory()->create(['nama_produk' => 'Produk Umum Uji']);

        $this->get('/produk?kategori=tidak-ada')
            ->assertStatus(301)
            ->assertRedirect(route('produk.index'));

        $this->get('/produk?kategori=tidak-ada&page=2')
            ->assertStatus(301)
            ->assertRedirect(route('produk.index'));
    }

    public function test_kategori_kosong_atau_array_dialihkan_permanen_ke_daftar_produk(): void
    {
        Produk::factory()->create();

        $this->get('/produk?kategori=')
            ->assertStatus(301)
            ->assertRedirect(route('produk.index'));

        $this->get('/produk?kategori[]=paperbag')
            ->assertStatus(301)
            ->assertRedirect(route('produk.index'));
    }

    public function test_halaman_di_luar_rentang_dialihkan_ke_halaman_terakhir(): void
    {
        Produk::factory()->count(13)->create();

        $response = $this->get('/produk?page=999');
        $response->assertStatus(302);
        $this->assertSame(['page' => '2'], $this->queryDari($response->headers->get('Location')));
    }

    public function test_halaman_di_luar_rentang_dengan_kategori_mempertahankan_kategori(): void
    {
        $kategori = KategoriProduk::factory()->create(['slug' => 'paperbag-uji']);
        Produk::factory()->count(13)->create(['kategori_id' => $kategori->id]);

        $response = $this->get('/produk?kategori=paperbag-uji&page=999');
        $response->assertStatus(302);
        $this->assertSame(
            ['kategori' => 'paperbag-uji', 'page' => '2'],
            $this->queryDari($response->headers->get('Location'))
        );
    }

    public function test_halaman_di_luar_rentang_tanpa_produk_tetap_200(): void
    {
        $this->get('/produk?page=999')->assertOk();
    }

    public function test_page_tidak_valid_menampilkan_halaman_pertama(): void
    {
        Produk::factory()->create(['nama_produk' => 'Produk Halaman Satu']);

        foreach (['abc', '-1'] as $page) {
            $this->get('/produk?page='.$page)
                ->assertOk()
                ->assertViewHas('produk', fn ($p) => $p->currentPage() === 1)
                ->assertSee('Produk Halaman Satu');
        }
    }

    private function queryDari(?string $url): array
    {
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);
        ksort($query);

        return $query;
    }

    public function test_daftar_kategori_berisi_jumlah_produk(): void
    {
        $kategori = KategoriProduk::factory()->create();
        Produk::factory()->count(2)->create(['kategori_id' => $kategori->id]);

        $this->get('/produk')
            ->assertOk()
            ->assertViewHas('kategori', fn ($daftar) => $daftar->firstWhere('id', $kategori->id)->produk_count === 2);
    }

    public function test_paginasi_12_per_halaman(): void
    {
        // Produk tertua dibuat paling awal, jadi muncul di halaman 2 (urutan terbaru dulu)
        Produk::factory()->create(['nama_produk' => 'Produk Paling Lama', 'created_at' => now()->subDay()]);
        Produk::factory()->count(12)->create();

        $this->get('/produk')
            ->assertOk()
            ->assertViewHas('produk', fn ($p) => $p->count() === 12 && $p->total() === 13 && $p->lastPage() === 2)
            ->assertDontSee('Produk Paling Lama');

        $this->get('/produk?page=2')
            ->assertOk()
            ->assertViewHas('produk', fn ($p) => $p->count() === 1)
            ->assertSee('Produk Paling Lama');
    }

    public function test_paginasi_mempertahankan_query_string(): void
    {
        $kategori = KategoriProduk::factory()->create(['slug' => 'paperbag-uji']);
        Produk::factory()->count(13)->create(['kategori_id' => $kategori->id]);

        $this->get('/produk?kategori=paperbag-uji')
            ->assertOk()
            ->assertViewHas('produk', fn ($p) => str_contains($p->nextPageUrl(), 'kategori=paperbag-uji'));
    }
}
