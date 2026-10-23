<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bahan & finishing diubah menjadi data master yang dipilih (centang)
     * per produk. Data lama di bahan_produk / finishing_produk dipindahkan
     * ke tabel master + tabel pivot, lalu tabel lama dihapus.
     */
    public function up(): void
    {
        // ── Tabel master ──
        Schema::create('bahan', function (Blueprint $table) {
            $table->id();
            $table->string('nama_bahan')->unique();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        Schema::create('finishing', function (Blueprint $table) {
            $table->id();
            $table->string('nama_finishing')->unique();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        // ── Tabel pivot ──
        Schema::create('produk_bahan', function (Blueprint $table) {
            $table->foreignId('produk_id')->constrained('produk')->onDelete('cascade');
            $table->foreignId('bahan_id')->constrained('bahan')->onDelete('cascade');
            $table->primary(['produk_id', 'bahan_id']);
        });

        Schema::create('produk_finishing', function (Blueprint $table) {
            $table->foreignId('produk_id')->constrained('produk')->onDelete('cascade');
            $table->foreignId('finishing_id')->constrained('finishing')->onDelete('cascade');
            $table->primary(['produk_id', 'finishing_id']);
        });

        // ── Pindahkan data lama ──
        $this->pindahkan('bahan_produk', 'nama_bahan', 'bahan', 'produk_bahan', 'bahan_id');
        $this->pindahkan('finishing_produk', 'nama_finishing', 'finishing', 'produk_finishing', 'finishing_id');

        Schema::dropIfExists('bahan_produk');
        Schema::dropIfExists('finishing_produk');
    }

    public function down(): void
    {
        Schema::create('bahan_produk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->constrained('produk')->onDelete('cascade');
            $table->string('nama_bahan');
            $table->text('deskripsi')->nullable();
            $table->decimal('harga_tambahan', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('finishing_produk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->constrained('produk')->onDelete('cascade');
            $table->string('nama_finishing');
            $table->text('deskripsi')->nullable();
            $table->decimal('harga_tambahan', 15, 2)->nullable();
            $table->timestamps();
        });

        // Kembalikan data dari pivot ke tabel lama
        $now = now();

        foreach (DB::table('produk_bahan')->join('bahan', 'bahan.id', '=', 'produk_bahan.bahan_id')->get() as $row) {
            DB::table('bahan_produk')->insert([
                'produk_id'  => $row->produk_id,
                'nama_bahan' => $row->nama_bahan,
                'deskripsi'  => $row->deskripsi,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('produk_finishing')->join('finishing', 'finishing.id', '=', 'produk_finishing.finishing_id')->get() as $row) {
            DB::table('finishing_produk')->insert([
                'produk_id'      => $row->produk_id,
                'nama_finishing' => $row->nama_finishing,
                'deskripsi'      => $row->deskripsi,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }

        Schema::dropIfExists('produk_bahan');
        Schema::dropIfExists('produk_finishing');
        Schema::dropIfExists('bahan');
        Schema::dropIfExists('finishing');
    }

    // Nama yang sama di beberapa produk digabung menjadi satu data master
    private function pindahkan(string $tabelLama, string $kolomNama, string $tabelMaster, string $tabelPivot, string $kolomFk): void
    {
        if (! Schema::hasTable($tabelLama)) {
            return;
        }

        $now   = now();
        $idMap = [];

        foreach (DB::table($tabelLama)->orderBy('id')->get() as $row) {
            $nama  = trim($row->$kolomNama);
            $kunci = mb_strtolower($nama);

            if ($nama === '') {
                continue;
            }

            if (! isset($idMap[$kunci])) {
                $idMap[$kunci] = DB::table($tabelMaster)->insertGetId([
                    $kolomNama   => $nama,
                    'deskripsi'  => $row->deskripsi,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table($tabelPivot)->insertOrIgnore([
                'produk_id' => $row->produk_id,
                $kolomFk    => $idMap[$kunci],
            ]);
        }
    }
};
