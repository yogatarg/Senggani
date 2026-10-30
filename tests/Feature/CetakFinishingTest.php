<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Bahan;
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

        $this->get(route('admin.produk.atribut', $produk))
            ->assertOk()
            ->assertSee('Jenis Cetak Produk')
            ->assertSee('Simpan Semua');

        $this->put(route('admin.produk.atribut.simpan', $produk), ['cetak' => [$full->id, $satu->id]])
            ->assertSessionHas('success');
        $this->assertCount(2, $produk->fresh()->cetak);

        // Yang tidak dicentang dilepas
        $this->put(route('admin.produk.atribut.simpan', $produk), ['cetak' => [$satu->id]]);
        $this->assertSame(['Cetak 1 Warna'], $produk->fresh()->cetak->pluck('nama_cetak')->all());

        // Id yang tidak ada ditolak, data lama tetap
        $this->put(route('admin.produk.atribut.simpan', $produk), ['cetak' => [9999]])
            ->assertSessionHasErrors('cetak.0');
        $this->assertCount(1, $produk->fresh()->cetak);
    }

    public function test_bahan_cetak_finishing_disimpan_sekaligus_satu_tombol(): void
    {
        $this->loginAdmin();
        $produk    = Produk::factory()->create();
        $bahan     = Bahan::create(['nama_bahan' => 'Kraft']);
        $cetak     = Cetak::create(['nama_cetak' => 'Cetak Full Color']);
        $finishing = [
            Finishing::create(['nama_finishing' => 'Laminasi Doff'])->id,
            Finishing::create(['nama_finishing' => 'Tali Jinjing'])->id,
        ];

        $this->put(route('admin.produk.atribut.simpan', $produk), [
            'bahan'     => [$bahan->id],
            'cetak'     => [$cetak->id],
            'finishing' => $finishing,
        ])->assertSessionHas('success');

        $produk->refresh();
        $this->assertCount(1, $produk->bahan);
        $this->assertCount(1, $produk->cetak);
        $this->assertCount(2, $produk->finishing);

        // Semua tidak dicentang → semua dilepas
        $this->put(route('admin.produk.atribut.simpan', $produk), []);
        $produk->refresh();
        $this->assertCount(0, $produk->bahan);
        $this->assertCount(0, $produk->cetak);
        $this->assertCount(0, $produk->finishing);

        // Satu id tidak valid → tidak ada yang tersimpan sebagian
        $this->put(route('admin.produk.atribut.simpan', $produk), [
            'bahan'     => [$bahan->id],
            'finishing' => [9999],
        ])->assertSessionHasErrors('finishing.0');
        $this->assertCount(0, $produk->fresh()->bahan);
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
