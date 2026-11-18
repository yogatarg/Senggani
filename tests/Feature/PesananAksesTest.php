<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Pesanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Peran admin/produksi, login email/username, akun nonaktif, kelola pengguna, ubah password.
 */
class PesananAksesTest extends TestCase
{
    use RefreshDatabase;

    private function sebagai(Admin $pengguna): static
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_id' => $pengguna->id]);
    }

    // ── Login ──────────────────────────────────────────

    public function test_login_dengan_username_produksi_diarahkan_ke_pesanan(): void
    {
        $produksi = Admin::factory()->produksi()->create(['username' => 'mamah', 'email' => null]);

        $this->post('/admin/login', ['login' => 'Mamah', 'password' => 'password'])
            ->assertRedirect(route('admin.pesanan.index'))
            ->assertSessionHas('admin_id', $produksi->id)
            ->assertSessionHas('admin_peran', 'produksi');
    }

    public function test_login_dengan_email_di_field_login_admin_ke_dashboard(): void
    {
        $admin = Admin::factory()->create(['email' => 'cs@contoh.test', 'username' => 'cs']);

        $this->post('/admin/login', ['login' => 'cs@contoh.test', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('admin_peran', 'admin');

        $this->post('/admin/logout');

        $this->post('/admin/login', ['login' => 'cs', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('admin_id', $admin->id);
    }

    public function test_login_username_salah_pesan_sama_dan_terkena_limit(): void
    {
        Admin::factory()->create(['username' => 'ayah']);

        for ($i = 1; $i <= 5; $i++) {
            $this->post('/admin/login', ['login' => 'ayah', 'password' => 'salah'])
                ->assertSessionHas('error', 'Email atau kata sandi salah.');
        }

        $this->post('/admin/login', ['login' => 'ayah', 'password' => 'password'])
            ->assertSessionHas('error', fn ($p) => str_starts_with($p, 'Terlalu banyak percobaan login.'));
    }

    public function test_login_kosong_menampilkan_error_validasi(): void
    {
        $this->from('/admin/login')->post('/admin/login', ['login' => '', 'password' => ''])
            ->assertSessionHasErrors(['login' => 'Email atau username wajib diisi.']);
    }

    public function test_akun_nonaktif_tidak_bisa_login(): void
    {
        Admin::factory()->produksi()->nonaktif()->create(['username' => 'ayah']);

        $this->post('/admin/login', ['login' => 'ayah', 'password' => 'password'])
            ->assertSessionHas('error', 'Akun Anda sudah dinonaktifkan. Hubungi admin.')
            ->assertSessionMissing('admin_logged_in');
    }

    public function test_session_ditolak_setelah_akun_dinonaktifkan(): void
    {
        $produksi = Admin::factory()->produksi()->create();

        $this->sebagai($produksi)->get('/admin/pesanan')->assertOk();

        $produksi->update(['aktif' => false]);

        $this->sebagai($produksi)->get('/admin/pesanan')
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', 'Akun Anda sudah dinonaktifkan. Hubungi admin.');
    }

    public function test_perubahan_peran_langsung_berlaku_di_session(): void
    {
        $pengguna = Admin::factory()->create();
        $this->sebagai($pengguna)->get('/admin/dashboard')->assertOk();

        $pengguna->update(['peran' => 'produksi']);

        $this->sebagai($pengguna)->get('/admin/dashboard')->assertRedirect(route('admin.pesanan.index'));
    }

    // ── Hak akses produksi ─────────────────────────────

    public function test_produksi_ditolak_di_semua_rute_katalog_dan_pengguna(): void
    {
        $produksi = Admin::factory()->produksi()->create();

        $get = ['/admin/dashboard', '/admin/banner', '/admin/banner/create', '/admin/kategori-produk', '/admin/produk',
            '/admin/produk/create', '/admin/bahan', '/admin/finishing', '/admin/cetak', '/admin/pengguna', '/admin/pengguna/tambah'];

        foreach ($get as $url) {
            $this->sebagai($produksi)->get($url)->assertRedirect(route('admin.pesanan.index'));
        }

        $this->sebagai($produksi)->post('/admin/bahan', ['nama_bahan' => 'Kraft'])->assertForbidden();
        $this->sebagai($produksi)->post('/admin/kategori-produk', ['nama_kategori' => 'X'])->assertForbidden();
        $this->sebagai($produksi)->post('/admin/pengguna', [])->assertForbidden();
        $this->assertDatabaseCount('bahan', 0);
    }

    public function test_produksi_ditolak_aksi_khusus_admin_di_pesanan(): void
    {
        $produksi = Admin::factory()->produksi()->create();
        $pesanan  = Pesanan::factory()->create();

        $this->sebagai($produksi)->get(route('admin.pesanan.create'))
            ->assertRedirect(route('admin.pesanan.index'))->assertSessionHas('error', 'Halaman ini khusus admin.');
        $this->sebagai($produksi)->post(route('admin.pesanan.store'), [])->assertForbidden();
        $this->sebagai($produksi)->get(route('admin.pesanan.edit', $pesanan))
            ->assertRedirect(route('admin.pesanan.index'))->assertSessionHas('error', 'Halaman ini khusus admin.');
        $this->sebagai($produksi)->put(route('admin.pesanan.update', $pesanan), [])->assertForbidden();
        $this->sebagai($produksi)->delete(route('admin.pesanan.destroy', $pesanan))->assertForbidden();
        $this->sebagai($produksi)->post(route('admin.pesanan.batal', $pesanan), ['status_lama' => 'antrian'])->assertForbidden();
        $this->sebagai($produksi)->post(route('admin.pesanan.pulihkan', $pesanan))->assertForbidden();
        $this->sebagai($produksi)->post(route('admin.pesanan.lunas', $pesanan))->assertForbidden();

        $this->assertNotSoftDeleted($pesanan);
        $this->assertSame('antrian', $pesanan->fresh()->status_produksi->value);
    }

    public function test_produksi_boleh_lihat_daftar_detail_harga_dan_ubah_password(): void
    {
        $produksi = Admin::factory()->produksi()->create();
        $pesanan  = Pesanan::factory()->create(['total' => 1250000]);

        $this->sebagai($produksi)->get(route('admin.pesanan.index'))->assertOk()->assertSee($pesanan->nomor_spp_teks);
        $this->sebagai($produksi)->get(route('admin.pesanan.show', $pesanan))->assertOk()
            ->assertSee('Rp 1.250.000')
            ->assertDontSee('Hapus SPP')
            ->assertDontSee('Batalkan SPP');
        $this->sebagai($produksi)->get(route('admin.pesanan.jumlah'))->assertOk();
        $this->sebagai($produksi)->get(route('admin.password.edit'))->assertOk();
    }

    public function test_tamu_ditolak_di_modul_pesanan(): void
    {
        $pesanan = Pesanan::factory()->create();

        $this->get(route('admin.pesanan.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.pesanan.show', $pesanan))->assertRedirect(route('admin.login'));
        $this->getJson(route('admin.pesanan.jumlah'))->assertUnauthorized();
    }

    // ── Kelola pengguna ────────────────────────────────

    public function test_admin_menambah_pengguna_produksi_tanpa_email(): void
    {
        $admin = Admin::factory()->create();

        $this->sebagai($admin)->post(route('admin.pengguna.store'), [
            'nama' => 'Ayah', 'username' => 'Ayah', 'email' => '', 'peran' => 'produksi',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('admin.pengguna.index'));

        $baru = Admin::where('username', 'ayah')->firstOrFail();
        $this->assertNull($baru->email);
        $this->assertTrue($baru->isProduksi());
        $this->assertTrue($baru->aktif);
        $this->assertTrue(Hash::check('rahasia123', $baru->password));
    }

    public function test_validasi_pengguna_username_atau_email_wajib_dan_unik(): void
    {
        $admin = Admin::factory()->create(['username' => 'kasir']);

        $this->sebagai($admin)->post(route('admin.pengguna.store'), [
            'nama' => 'X', 'peran' => 'produksi', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors(['username', 'email']);

        $this->sebagai($admin)->post(route('admin.pengguna.store'), [
            'nama' => 'X', 'username' => 'Kasir', 'peran' => 'produksi', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors(['username' => 'Username sudah dipakai.']);

        $this->sebagai($admin)->post(route('admin.pengguna.store'), [
            'nama' => 'X', 'username' => 'pakai spasi', 'peran' => 'bos', 'password' => 'pendek', 'password_confirmation' => 'pendek',
        ])->assertSessionHasErrors(['username', 'peran', 'password']);
    }

    public function test_admin_edit_reset_password_dan_nonaktifkan_pengguna(): void
    {
        $admin    = Admin::factory()->create();
        $produksi = Admin::factory()->produksi()->create(['username' => 'mamah']);

        $this->sebagai($admin)->put(route('admin.pengguna.update', $produksi), [
            'nama' => 'Mamah Rina', 'username' => 'mamah', 'peran' => 'produksi',
        ])->assertRedirect(route('admin.pengguna.index'));
        $this->assertSame('Mamah Rina', $produksi->fresh()->nama);

        $this->sebagai($admin)->put(route('admin.pengguna.password', $produksi), [
            'password' => 'baru12345', 'password_confirmation' => 'baru12345',
        ])->assertRedirect();
        $this->assertTrue(Hash::check('baru12345', $produksi->fresh()->password));

        $this->sebagai($admin)->patch(route('admin.pengguna.aktif', $produksi))->assertRedirect();
        $this->assertFalse($produksi->fresh()->aktif);

        $this->sebagai($admin)->patch(route('admin.pengguna.aktif', $produksi))->assertRedirect();
        $this->assertTrue($produksi->fresh()->aktif);
        $this->assertDatabaseCount('admin', 2); // tidak pernah dihapus
    }

    public function test_admin_tidak_bisa_menonaktifkan_atau_menurunkan_peran_sendiri(): void
    {
        $admin = Admin::factory()->create(['username' => 'cs']);

        $this->sebagai($admin)->patch(route('admin.pengguna.aktif', $admin))
            ->assertSessionHas('error', 'Anda tidak bisa menonaktifkan akun Anda sendiri.');
        $this->assertTrue($admin->fresh()->aktif);

        $this->sebagai($admin)->put(route('admin.pengguna.update', $admin), [
            'nama' => 'CS', 'username' => 'cs', 'peran' => 'produksi',
        ])->assertSessionHasErrors('peran');
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_halaman_kelola_pengguna_tampil_untuk_admin(): void
    {
        $admin    = Admin::factory()->create(['nama' => 'Rina']);
        $produksi = Admin::factory()->produksi()->create(['nama' => 'Mamah', 'username' => 'mamah']);

        $this->sebagai($admin)->get(route('admin.pengguna.index'))->assertOk()->assertSee('Mamah')->assertSee('mamah');
        $this->sebagai($admin)->get(route('admin.pengguna.create'))->assertOk();
        $this->sebagai($admin)->get(route('admin.pengguna.edit', $produksi))->assertOk()->assertSee('Reset password');
        $this->sebagai($admin)->get(route('admin.pengguna.edit', $admin))->assertOk()->assertViewHas('akunSendiri', true);
    }

    // ── Ubah password sendiri ──────────────────────────

    public function test_ubah_password_wajib_password_lama_yang_benar(): void
    {
        $produksi = Admin::factory()->produksi()->create();

        $this->sebagai($produksi)->put(route('admin.password.update'), [
            'password_lama' => 'salah', 'password' => 'baru12345', 'password_confirmation' => 'baru12345',
        ])->assertSessionHasErrors(['password_lama' => 'Password saat ini salah.']);

        $this->sebagai($produksi)->put(route('admin.password.update'), [
            'password_lama' => 'password', 'password' => 'baru12345', 'password_confirmation' => 'beda12345',
        ])->assertSessionHasErrors(['password' => 'Ulangan password baru tidak sama.']);

        $this->sebagai($produksi)->put(route('admin.password.update'), [
            'password_lama' => 'password', 'password' => 'baru12345', 'password_confirmation' => 'baru12345',
        ])->assertRedirect(route('admin.pesanan.index'));

        $this->assertTrue(Hash::check('baru12345', $produksi->fresh()->password));
    }
}
