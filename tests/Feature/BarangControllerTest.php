<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test locking the JSON response envelope of BarangController.
 * Success/404 shapes are byte-identical to the pre-refactor controller; the
 * 422 shape follows BaseRequest (the deliberate FormRequest contract change).
 */
class BarangControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = storage_path('framework/testing-'.Str::random(8).'.sqlite');
        touch($databasePath);

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $databasePath);
        Config::set('database.connections.mysqlhris', array_merge(Config::get('database.connections.sqlite'), ['database' => $databasePath]));
        Config::set('database.connections.mysql', array_merge(Config::get('database.connections.sqlite'), ['database' => $databasePath]));

        DB::purge('sqlite');
        DB::purge('mysqlhris');
        DB::purge('mysql');
        DB::reconnect('sqlite');
        DB::reconnect('mysqlhris');
        DB::reconnect('mysql');

        $this->rebuildSchema();

        DB::table('m_user')->insert([
            'id' => 1,
            'username' => 'tester',
            'password' => bcrypt('secret'),
            'full_name' => 'Tester',
            'email' => 'tester@example.com',
            'cais_role_id' => 2,
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(1), 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);
    }

    private function seedJenis(): int
    {
        return DB::table('m_jenis_barang')->insertGetId(['nama' => 'Kaporlap']);
    }

    private function seedLayanan(string $nama = 'Cleaning'): int
    {
        return DB::table('m_kebutuhan')->insertGetId(['nama' => $nama]);
    }

    private function seedBarang(int $jenisId): int
    {
        return DB::table('m_barang')->insertGetId([
            'nama' => 'Sapu',
            'jenis_barang_id' => $jenisId,
            'jenis_barang' => 'Kaporlap',
            'harga' => 10000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ---------- list / view ----------

    public function test_list_returns_success_data_envelope(): void
    {
        $jenis = $this->seedJenis();
        $this->seedBarang($jenis);

        $response = $this->getJson('/api/barang/list');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data');
        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_found_returns_success_data(): void
    {
        $jenis = $this->seedJenis();
        $id = $this->seedBarang($jenis);

        $response = $this->getJson("/api/barang/view/{$id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.nama', 'Sapu');
    }

    public function test_view_not_found_returns_404(): void
    {
        $this->getJson('/api/barang/view/999')
            ->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Data tidak ditemukan']);
    }

    // ---------- add ----------

    public function test_add_creates_and_persists_created_by_user_id(): void
    {
        $jenis = $this->seedJenis();

        $response = $this->postJson('/api/barang/add', [
            'nama' => 'Pel',
            'jenis_barang_id' => $jenis,
            'harga' => '150,000',
        ]);

        $response->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Barang berhasil disimpan']);

        $this->assertDatabaseHas('m_barang', [
            'nama' => 'Pel',
            'harga' => 150000,
            'created_by_user_id' => 1,
        ]);
    }

    public function test_add_missing_jenis_returns_404(): void
    {
        $this->postJson('/api/barang/add', [
            'nama' => 'Pel',
            'jenis_barang_id' => 999,
            'harga' => '1000',
        ])
            ->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Jenis barang tidak ditemukan']);
    }

    public function test_add_validation_error_returns_422_baserequest_shape(): void
    {
        $this->postJson('/api/barang/add', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama', 'jenis_barang_id', 'harga']]);
    }

    // ---------- update ----------

    public function test_update_found_returns_message(): void
    {
        $jenis = $this->seedJenis();
        $id = $this->seedBarang($jenis);

        $this->putJson("/api/barang/update/{$id}", [
            'nama' => 'Sapu Baru',
            'jenis_barang_id' => $jenis,
            'harga' => '20,000',
        ])
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Barang berhasil diupdate']);

        $this->assertDatabaseHas('m_barang', ['id' => $id, 'nama' => 'Sapu Baru', 'harga' => 20000]);
    }

    public function test_update_not_found_returns_404(): void
    {
        $this->putJson('/api/barang/update/999', ['nama' => 'X', 'jenis_barang_id' => 1, 'harga' => '1'])
            ->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Data tidak ditemukan']);
    }

    public function test_update_validation_error_returns_422(): void
    {
        $jenis = $this->seedJenis();
        $id = $this->seedBarang($jenis);

        $this->putJson("/api/barang/update/{$id}", [])
            ->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama', 'jenis_barang_id', 'harga']]);
    }

    // ---------- delete ----------

    public function test_delete_found_returns_message(): void
    {
        $jenis = $this->seedJenis();
        $id = $this->seedBarang($jenis);

        $this->deleteJson("/api/barang/delete/{$id}")
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Barang berhasil dihapus']);

        $this->assertNotNull(DB::table('m_barang')->where('id', $id)->value('deleted_at'));
    }

    public function test_delete_not_found_returns_404(): void
    {
        $this->deleteJson('/api/barang/delete/999')
            ->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Data tidak ditemukan']);
    }

    // ---------- default qty ----------

    public function test_get_default_qty_returns_success_data(): void
    {
        $jenis = $this->seedJenis();
        $barang = $this->seedBarang($jenis);
        $layanan = $this->seedLayanan();
        DB::table('m_barang_default_qty')->insert([
            'barang_id' => $barang,
            'layanan_id' => $layanan,
            'layanan' => 'Cleaning',
            'qty_default' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/barang/default-qty/{$barang}")
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data');
    }

    public function test_save_default_qty_creates(): void
    {
        $jenis = $this->seedJenis();
        $barang = $this->seedBarang($jenis);
        $layanan = $this->seedLayanan();

        $this->postJson('/api/barang/default-qty/save', [
            'barang_id' => $barang,
            'layanan_id' => $layanan,
            'qty_default' => 7,
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data default qty berhasil disimpan',
                'data' => ['action' => 'created'],
            ]);

        $this->assertDatabaseHas('m_barang_default_qty', [
            'barang_id' => $barang,
            'layanan_id' => $layanan,
            'qty_default' => 7,
            'created_by_user_id' => 1,
        ]);
    }

    public function test_save_default_qty_updates_existing(): void
    {
        $jenis = $this->seedJenis();
        $barang = $this->seedBarang($jenis);
        $layanan = $this->seedLayanan();
        DB::table('m_barang_default_qty')->insert([
            'barang_id' => $barang,
            'layanan_id' => $layanan,
            'layanan' => 'Cleaning',
            'qty_default' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/barang/default-qty/save', [
            'barang_id' => $barang,
            'layanan_id' => $layanan,
            'qty_default' => 12,
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data default qty berhasil diupdate',
                'data' => ['action' => 'updated'],
            ]);
    }

    public function test_save_default_qty_layanan_not_found_returns_404(): void
    {
        $this->postJson('/api/barang/default-qty/save', [
            'barang_id' => 1,
            'layanan_id' => 999,
            'qty_default' => 1,
        ])
            ->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Layanan tidak ditemukan']);
    }

    public function test_save_default_qty_validation_error_returns_422(): void
    {
        $this->postJson('/api/barang/default-qty/save', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message' => ['barang_id', 'layanan_id', 'qty_default']]);
    }

    public function test_delete_default_qty_found(): void
    {
        $jenis = $this->seedJenis();
        $barang = $this->seedBarang($jenis);
        $layanan = $this->seedLayanan();
        $id = DB::table('m_barang_default_qty')->insertGetId([
            'barang_id' => $barang,
            'layanan_id' => $layanan,
            'layanan' => 'Cleaning',
            'qty_default' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson("/api/barang/default-qty/delete/{$id}")
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Data default qty berhasil dihapus']);
    }

    public function test_delete_default_qty_not_found_returns_404(): void
    {
        $this->deleteJson('/api/barang/default-qty/delete/999')
            ->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Data tidak ditemukan']);
    }

    public function test_get_default_qty_by_layanan_found(): void
    {
        $jenis = $this->seedJenis();
        $barang = $this->seedBarang($jenis);
        $layanan = $this->seedLayanan();
        DB::table('m_barang_default_qty')->insert([
            'barang_id' => $barang,
            'layanan_id' => $layanan,
            'layanan' => 'Cleaning',
            'qty_default' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/barang/{$barang}/default-qty/{$layanan}")
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.qty_default', 5);
    }

    public function test_get_default_qty_by_layanan_not_found_returns_404(): void
    {
        $this->getJson('/api/barang/1/default-qty/1')
            ->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Data tidak ditemukan']);
    }

    public function test_bulk_save_default_qty(): void
    {
        $jenis = $this->seedJenis();
        $barang = $this->seedBarang($jenis);
        $l1 = $this->seedLayanan('Cleaning');
        $l2 = $this->seedLayanan('Security');

        $this->postJson('/api/barang/default-qty/bulk-save', [
            'barang_id' => $barang,
            'quantities' => [
                ['layanan_id' => $l1, 'qty_default' => 3],
                ['layanan_id' => $l2, 'qty_default' => 4],
            ],
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Berhasil menyimpan 2 default qty',
                'data' => ['created' => 2, 'updated' => 0, 'total' => 2],
            ]);
    }

    public function test_bulk_save_validation_error_returns_422(): void
    {
        $this->postJson('/api/barang/default-qty/bulk-save', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message' => ['barang_id', 'quantities']]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('m_barang');
        Schema::dropIfExists('m_barang_default_qty');
        Schema::dropIfExists('m_jenis_barang');
        Schema::dropIfExists('m_kebutuhan');
        Schema::dropIfExists('m_user');

        Schema::create('m_user', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('cais_role_id')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('m_jenis_barang', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_kebutuhan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_barang', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->unsignedInteger('jenis_barang_id')->nullable();
            $table->string('jenis_barang')->nullable();
            $table->decimal('harga', 15, 2)->nullable();
            $table->string('satuan')->nullable();
            $table->integer('masa_pakai')->nullable();
            $table->string('merk')->nullable();
            $table->integer('jumlah_default')->nullable();
            $table->integer('urutan')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_barang_default_qty', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('barang_id')->nullable();
            $table->unsignedInteger('layanan_id')->nullable();
            $table->string('layanan')->nullable();
            $table->integer('qty_default')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
