<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\KategoriProduk;
use App\Models\Produk;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * File untuk mesin pencari & asisten AI: sitemap.xml, robots.txt, llms.txt.
 * Semuanya dinamis agar URL absolut selalu mengikuti APP_URL (domain belum pasti).
 */
class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $kategori = KategoriProduk::orderBy('id')->get(['slug', 'updated_at']);
        $produk   = Produk::orderBy('id')->get(['slug', 'updated_at']);

        // Halaman daftar dianggap berubah saat ada kategori/produk yang berubah
        $terbaru = collect([$kategori->max('updated_at'), $produk->max('updated_at')])->filter()->max();

        $url = [
            [$this->url('home'), $terbaru],
            [$this->url('produk.index'), $produk->max('updated_at')],
            [$this->url('kategori.index'), $kategori->max('updated_at')],
        ];

        foreach ($kategori as $k) {
            $url[] = [$this->url('kategori.show', $k->slug), $k->updated_at];
        }

        foreach ($produk as $p) {
            $url[] = [$this->url('produk.show', $p->slug), $p->updated_at];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($url as [$loc, $lastmod]) {
            $xml .= "  <url>\n    <loc>" . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
            if ($lastmod) {
                $xml .= '    <lastmod>' . Carbon::parse($lastmod)->toAtomString() . "</lastmod>\n";
            }
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>' . "\n";

        return response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function robots(): Response
    {
        // Bot AI disebut eksplisit agar jelas diizinkan; grup khusus menggantikan grup "*",
        // jadi aturan Disallow /admin diulang di sana.
        $teks = <<<TXT
            User-agent: *
            Allow: /
            Disallow: /admin

            User-agent: GPTBot
            User-agent: OAI-SearchBot
            User-agent: ClaudeBot
            User-agent: PerplexityBot
            User-agent: Google-Extended
            Allow: /
            Disallow: /admin

            Sitemap: {$this->url('sitemap')}

            TXT;

        return $this->teks($teks);
    }

    public function llms(): Response
    {
        $u        = config('usaha');
        $kategori = KategoriProduk::orderBy('id')->get();
        $produk   = Produk::with('kategori')->orderBy('kategori_id')->orderBy('nama_produk')->get();
        $faq      = collect(config('faq.items', []))->filter(fn ($i) => ! empty($i['tanya']) && ! empty($i['jawab']));

        $a      = $u['alamat'];
        $alamat = implode(', ', array_filter([
            $a['jalan'] ?? null,
            $a['kelurahan'] ?? null,
            $a['kecamatan'] ?? null,
            $a['kota'] ?? null,
            $a['provinsi'] ?? null,
            $a['kode_pos'] ?? null,
            ($a['negara'] ?? null) === 'ID' ? 'Indonesia' : ($a['negara'] ?? null),
        ]));

        $wa = 'https://wa.me/' . $u['whatsapp'];

        $md   = [];
        $md[] = '# ' . $u['nama'];
        $md[] = '';
        $md[] = '> ' . $u['deskripsi'];
        $md[] = '';
        $md[] = $u['nama'] . ' menerima pesanan kemasan custom (desain, ukuran, bahan, dan finishing sesuai kebutuhan) '
            . 'untuk area layanan ' . implode(' dan ', $u['area_layanan']) . '. '
            . 'Harga tidak dicantumkan karena menyesuaikan spesifikasi dan jumlah pesanan.';
        $md[] = '';

        $md[] = '## Kontak & Alamat';
        $md[] = '';
        $md[] = '- Website: ' . $this->url('home');
        $md[] = '- WhatsApp: ' . $wa;
        $md[] = '- Telepon: ' . $u['telepon'];
        $md[] = '- Email: ' . $u['email'];
        $md[] = '- Alamat: ' . $alamat;
        if (! empty($u['peta'])) {
            $md[] = '- Google Maps: ' . $u['peta'];
        }
        if (! empty($u['jam_buka'])) {
            $md[] = '- Jam buka: ' . implode('; ', $u['jam_buka']);
        }
        foreach ($u['sosial'] as $sosial) {
            $md[] = '- Media sosial: ' . $sosial;
        }
        $md[] = '';

        $md[] = '## Cara Memesan';
        $md[] = '';
        $md[] = '1. Pilih produk atau kategori di website (' . $this->url('produk.index') . ').';
        $md[] = '2. Hubungi kami lewat WhatsApp (' . $wa . ') dengan menyebutkan produk, ukuran, bahan, jenis cetak, finishing, dan jumlah.';
        $md[] = '3. Kami kirimkan penawaran harga; harga menyesuaikan karena setiap pesanan custom.';
        $md[] = '4. Perhatikan jumlah minimal pemesanan yang tertera di setiap produk.';
        $md[] = '';

        if ($kategori->isNotEmpty()) {
            $md[] = '## Kategori';
            $md[] = '';
            foreach ($kategori as $k) {
                $md[] = $this->item($k->nama_kategori, $this->url('kategori.show', $k->slug), $this->ringkas($k->deskripsi));
            }
            $md[] = '';
        }

        if ($produk->isNotEmpty()) {
            $md[] = '## Produk';
            $md[] = '';
            foreach ($produk as $p) {
                $info = array_filter([
                    $p->kategori ? 'Kategori: ' . $p->kategori->nama_kategori . '.' : null,
                    (int) $p->minimal_pesan > 1
                        ? 'Min. pesan ' . number_format((int) $p->minimal_pesan, 0, ',', '.') . ' pcs.'
                        : 'Tanpa minimum order.',
                    $this->ringkas($p->deskripsi),
                ]);
                $md[] = $this->item($p->nama_produk, $this->url('produk.show', $p->slug), implode(' ', $info));
            }
            $md[] = '';
        }

        if ($faq->isNotEmpty()) {
            $md[] = '## FAQ';
            $md[] = '';
            foreach ($faq as $i) {
                $md[] = '### ' . $this->satuBaris($i['tanya']);
                $md[] = '';
                $md[] = $this->satuBaris($i['jawab']);
                $md[] = '';
            }
        }

        $md[] = '## Optional';
        $md[] = '';
        $md[] = $this->item('Sitemap', $this->url('sitemap'), 'Daftar semua halaman publik.');

        return $this->teks(implode("\n", $md) . "\n");
    }

    // URL absolut berbasis APP_URL (bukan host permintaan)
    private function url(string $nama, mixed $parameter = []): string
    {
        $path = route($nama, $parameter, false);

        return rtrim(config('app.url'), '/') . ($path === '/' ? '/' : $path);
    }

    private function item(string $judul, string $url, ?string $catatan): string
    {
        $judul = str_replace(['[', ']'], ['(', ')'], $this->satuBaris($judul));

        return "- [{$judul}]({$url})" . ($catatan ? ': ' . $catatan : '');
    }

    private function ringkas(?string $teks, int $batas = 200): ?string
    {
        $teks = $this->satuBaris((string) $teks);

        return $teks === '' ? null : Str::limit($teks, $batas);
    }

    private function satuBaris(string $teks): string
    {
        $teks = html_entity_decode(strip_tags($teks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $teks = str_replace(['**', '__'], '', $teks); // tebal ala markdown dari deskripsi admin

        return trim(preg_replace('/\s+/u', ' ', $teks));
    }

    private function teks(string $isi): Response
    {
        return response($isi, 200, [
            'Content-Type'  => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
