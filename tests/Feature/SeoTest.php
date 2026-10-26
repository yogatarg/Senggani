<?php

namespace Tests\Feature;

use App\Models\KategoriProduk;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // URL absolut harus mengikuti APP_URL, bukan host permintaan
        config(['app.url' => 'https://contoh-senggani.id']);
    }

    public function test_sitemap_xml_berisi_halaman_utama_kategori_dan_produk(): void
    {
        $kategori = KategoriProduk::factory()->create(['slug' => 'paperbag']);
        $produk   = Produk::factory()->create([
            'kategori_id' => $kategori->id,
            'slug'        => 'paperbag-kraft',
            'updated_at'  => '2026-10-20 10:00:00',
        ]);

        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringStartsWith('application/xml', $response->headers->get('Content-Type'));

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'Sitemap harus XML yang valid');
        $this->assertSame('urlset', $xml->getName());

        $loc = [];
        foreach ($xml->url as $u) {
            $loc[] = (string) $u->loc;
        }

        $this->assertContains('https://contoh-senggani.id/', $loc);
        $this->assertContains('https://contoh-senggani.id/produk', $loc);
        $this->assertContains('https://contoh-senggani.id/kategori', $loc);
        $this->assertContains('https://contoh-senggani.id/kategori/paperbag', $loc);
        $this->assertContains('https://contoh-senggani.id/produk/paperbag-kraft', $loc);

        $response->assertSee('<lastmod>' . $produk->fresh()->updated_at->toAtomString() . '</lastmod>', false);
    }

    public function test_robots_txt_mengizinkan_bot_ai_menutup_admin_dan_menunjuk_sitemap(): void
    {
        $response = $this->get('/robots.txt')->assertOk();

        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));

        $isi = $response->getContent();
        foreach (['User-agent: *', 'GPTBot', 'ClaudeBot', 'PerplexityBot', 'Google-Extended', 'OAI-SearchBot'] as $bot) {
            $this->assertStringContainsString($bot, $isi);
        }
        $this->assertStringContainsString('Disallow: /admin', $isi);
        $this->assertStringNotContainsString("Disallow: /\n", $isi);
        $this->assertStringContainsString('Sitemap: https://contoh-senggani.id/sitemap.xml', $isi);
    }

    public function test_llms_txt_berisi_usaha_kontak_kategori_dan_produk(): void
    {
        config(['faq.items' => []]); // deterministik: tidak bergantung isi config/faq.php

        $kategori = KategoriProduk::factory()->create(['nama_kategori' => 'Dus Kemasan', 'slug' => 'dus-kemasan']);
        Produk::factory()->create([
            'kategori_id'   => $kategori->id,
            'nama_produk'   => 'Dus Makanan',
            'slug'          => 'dus-makanan',
            'minimal_pesan' => 500,
            'deskripsi'     => '<p>Dus kraft <b>250 gsm</b> aman untuk makanan.</p>',
        ]);

        $response = $this->get('/llms.txt')->assertOk();

        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));

        $isi = $response->getContent();
        $this->assertStringStartsWith('# ' . config('usaha.nama') . "\n", $isi);
        $this->assertStringContainsString('> ' . config('usaha.deskripsi'), $isi);
        $this->assertStringContainsString('https://wa.me/' . config('usaha.whatsapp'), $isi);
        $this->assertStringContainsString(config('usaha.email'), $isi);
        $this->assertStringContainsString('Kotagede', $isi);
        $this->assertStringContainsString('- [Dus Kemasan](https://contoh-senggani.id/kategori/dus-kemasan)', $isi);
        $this->assertStringContainsString(
            '- [Dus Makanan](https://contoh-senggani.id/produk/dus-makanan): Kategori: Dus Kemasan. Min. pesan 500 pcs. Dus kraft 250 gsm aman untuk makanan.',
            $isi
        );
        $this->assertStringContainsString('## Cara Memesan', $isi);
        $this->assertStringNotContainsString('## FAQ', $isi); // tanpa FAQ, seksi tidak ditampilkan
    }

    public function test_llms_txt_menampilkan_faq_bila_ada(): void
    {
        config(['faq.items' => [
            ['tanya' => 'Berapa minimal pesan?', 'jawab' => 'Mulai 100 pcs, tergantung produk.'],
        ]]);

        $this->get('/llms.txt')
            ->assertOk()
            ->assertSee("## FAQ\n\n### Berapa minimal pesan?\n\nMulai 100 pcs, tergantung produk.", false);
    }
}
