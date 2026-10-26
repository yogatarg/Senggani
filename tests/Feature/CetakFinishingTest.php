<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Cetak;
use App\Models\Finishing;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CetakFinishingTest extends TestCase
{
    use RefreshDatabase;

    private function loginAdmin(): void
    {
        $admin = Admin::factory()->create();
        $this->withSession(['admin_logged_in' => true, 'admin_id' => $admin->id]);
    }

    public function test_admin_bisa_kelola_master_cetak(): void
    {
        $this->loginAdmin();

        $this->post(route('admin.cetak.store'), ['nama_cetak' => 'Cetak Full Color'])
            ->assertRedirect(route('admin.cetak.index'));
        $cetak = Cetak::where('nama_cetak', 'Cetak Full Color')->firstOrFail();

        // Nama ganda ditolak
        $this->post(route('admin.cetak.store'), ['nama_cetak' => 'Cetak Full Color'])
            ->assertSessionHasErrors('nama_cetak');

        $this->get(route('admin.cetak.index'))->assertOk()->assertSee('Cetak Full Color');

        $this->put(route('admin.cetak.update', $cetak), ['nama_cetak' => 'Cetak 1 Warna'])
            ->assertRedirect(route('admin.cetak.index'));
        $this->assertSame('Cetak 1 Warna', $cetak->fresh()->nama_cetak);

        $this->delete(route('admin.cetak.destroy', $cetak))->assertRedirect(route('admin.cetak.index'));
        $this->assertDatabaseMissing('cetak', ['id' => $cetak->id]);
    }

    public function test_admin_memilih_cetak_per_produk(): void
    {
        $this->loginAdmin();
        $produk = Produk::factory()->create();
        $full   = Cetak::create(['nama_cetak' => 'Cetak Full Color']);
        $satu   = Cetak::create(['nama_cetak' => 'Cetak 1 Warna']);

        $this->get(route('admin.produk.atribut', $produk))->assertOk()->assertSee('Jenis Cetak Produk');

        $this->put(route('admin.produk.cetak.simpan', $produk), ['cetak' => [$full->id, $satu->id]])
            ->assertSessionHas('success');
        $this->assertCount(2, $produk->fresh()->cetak);

        // Yang tidak dicentang dilepas
        $this->put(route('admin.produk.cetak.simpan', $produk), ['cetak' => [$satu->id]]);
        $this->assertSame(['Cetak 1 Warna'], $produk->fresh()->cetak->pluck('nama_cetak')->all());

        // Id yang tidak ada ditolak
        $this->put(route('admin.produk.cetak.simpan', $produk), ['cetak' => [9999]])
            ->assertSessionHasErrors('cetak.0');
    }

    public function test_detail_produk_cetak_satu_pilihan_finishing_boleh_banyak(): void
    {
        $produk = Produk::factory()->create();
        $produk->cetak()->attach(Cetak::create(['nama_cetak' => 'Cetak Full Color']));
        $produk->finishing()->attach([
            Finishing::create(['nama_finishing' => 'Laminasi Doff'])->id,
            Finishing::create(['nama_finishing' => 'Tali Jinjing'])->id,
        ]);

        $this->get(route('produk.show', $produk->slug))
            ->assertOk()
            ->assertSee('id="pilihCetak"', false)
            ->assertSee('Cetak Full Color')
            ->assertSee('(boleh lebih dari satu)')
            ->assertSee('type="checkbox"', false)
            ->assertSee('Laminasi Doff')
            ->assertSee('Tali Jinjing')
            ->assertSee('Cetak       : ${cetak}', false);
    }

    public function test_detail_produk_tanpa_cetak_tidak_menampilkan_pilihan_cetak(): void
    {
        $produk = Produk::factory()->create();

        $this->get(route('produk.show', $produk->slug))
            ->assertOk()
            ->assertDontSee('id="pilihCetak"', false);
    }
}
