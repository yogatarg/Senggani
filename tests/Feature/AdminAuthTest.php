<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
    }

    public function test_halaman_login_tampil(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_login_gagal_menampilkan_error(): void
    {
        Admin::factory()->create(['email' => 'admin@contoh.test']);

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => 'admin@contoh.test', 'password' => 'salah'])
            ->assertRedirect('/admin/login')
            ->assertSessionHas('error', 'Email atau kata sandi salah.');

        $this->followingRedirects()
            ->from('/admin/login')
            ->post('/admin/login', ['email' => 'admin@contoh.test', 'password' => 'salah'])
            ->assertSee('Email atau kata sandi salah.');
    }

    public function test_login_email_tidak_terdaftar_pesan_sama(): void
    {
        $this->from('/admin/login')
            ->post('/admin/login', ['email' => 'tidakada@contoh.test', 'password' => 'apa saja'])
            ->assertRedirect('/admin/login')
            ->assertSessionHas('error', 'Email atau kata sandi salah.');
    }

    public function test_login_berhasil(): void
    {
        $admin = Admin::factory()->create(['email' => 'admin@contoh.test']);

        $this->post('/admin/login', ['email' => 'admin@contoh.test', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('admin_id', $admin->id);

        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_limit_per_email_dari_ip_berbeda(): void
    {
        Admin::factory()->create(['email' => 'admin@contoh.test']);

        // 20 percobaan gagal dari IP yang berganti-ganti (limit email|IP tidak tercapai)
        for ($i = 1; $i <= 20; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->post('/admin/login', ['email' => 'admin@contoh.test', 'password' => 'salah'])
                ->assertSessionHas('error', 'Email atau kata sandi salah.');
        }

        // Percobaan ke-21 diblokir walau dari IP baru, bahkan dengan password benar
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->post('/admin/login', ['email' => 'admin@contoh.test', 'password' => 'password'])
            ->assertSessionHas('error', fn ($pesan) => str_starts_with($pesan, 'Terlalu banyak percobaan login.'));
    }

    public function test_session_gugur_bila_admin_dihapus(): void
    {
        $admin = Admin::factory()->create();

        $this->withSession(['admin_logged_in' => true, 'admin_id' => $admin->id])
            ->get('/admin/dashboard')
            ->assertOk();

        $admin->delete();

        $this->withSession(['admin_logged_in' => true, 'admin_id' => $admin->id])
            ->get('/admin/dashboard')
            ->assertRedirect(route('admin.login'));

        $this->assertNull(session('admin_logged_in'));
    }

    public function test_cast_hashed_tidak_hash_ulang_dan_hash_plain(): void
    {
        // Seperti admin:buat: nilai sudah Hash::make tidak di-hash ulang
        $admin = Admin::create(['nama' => 'A', 'email' => 'a@contoh.test', 'password' => Hash::make('rahasia123')]);
        $this->assertTrue(Hash::check('rahasia123', $admin->fresh()->password));

        // Nilai polos otomatis di-hash
        $admin->update(['password' => 'baru12345']);
        $this->assertNotSame('baru12345', $admin->fresh()->password);
        $this->assertTrue(Hash::check('baru12345', $admin->fresh()->password));
    }

    public function test_route_show_admin_tidak_terdaftar(): void
    {
        foreach (['admin.banner.show', 'admin.kategori-produk.show', 'admin.produk.show', 'admin.bahan.show', 'admin.finishing.show'] as $nama) {
            $this->assertFalse(Route::has($nama), "Route {$nama} seharusnya tidak ada");
        }
    }
}
