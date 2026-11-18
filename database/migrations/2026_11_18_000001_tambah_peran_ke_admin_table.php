<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peran pengguna panel (admin|produksi), login dengan username, dan status aktif.
 * Akun lama otomatis menjadi admin aktif lewat nilai default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('nama');
            $table->string('peran', 20)->default('admin')->after('email');
            $table->boolean('aktif')->default(true)->after('peran');
        });

        // Pengguna produksi boleh tanpa email (login dengan username)
        Schema::table('admin', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('admin', function (Blueprint $table) {
            $table->dropUnique(['username']);
        });

        Schema::table('admin', function (Blueprint $table) {
            $table->dropColumn(['username', 'peran', 'aktif']);
        });

        // Catatan: mengembalikan email ke NOT NULL gagal bila ada akun tanpa email.
        Schema::table('admin', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
