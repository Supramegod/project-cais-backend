<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\JenisBarang;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the JSON response shape of JenisBarangController
 * after refactoring it onto the ApiResponser trait + JenisBarangRequest.
 * The envelope (success/data payload, status codes) and messages must stay
 * stable; only the validation-422 shape follows the BaseRequest standard.
 */
class JenisBarangControllerTest extends TestCase
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

    public function test_list_returns_success_data_envelope(): void
    {
        JenisBarang::query()->create(['nama' => 'Elektronik']);
        JenisBarang::query()->create(['nama' => 'Furnitur']);

        $response = $this->getJson('/api/jenis-barang/list');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nama', 'Elektronik')
            ->assertJsonPath('data.1.nama', 'Furnitur');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_found_returns_success_data(): void
    {
        $row = JenisBarang::query()->create(['nama' => 'Elektronik']);

        $response = $this->getJson("/api/jenis-barang/view/{$row->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.nama', 'Elektronik');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_not_found_returns_404_with_message(): void
    {
        $response = $this->getJson('/api/jenis-barang/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ]);
    }

    public function test_list_detail_returns_success_data(): void
    {
        $jenis = JenisBarang::query()->create(['nama' => 'Elektronik']);

        DB::table('m_barang')->insert([
            'nama' => 'Laptop',
            'jenis_barang_id' => $jenis->id,
            'harga' => 15000000,
            'satuan' => 'Unit',
            'masa_pakai' => 5,
            'merk' => 'Dell',
        ]);

        $response = $this->getJson("/api/jenis-barang/list-detail/{$jenis->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.jenis_barang', 'Elektronik')
            ->assertJsonPath('data.0.nama_barang', 'Laptop');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_add_creates_and_returns_201(): void
    {
        $response = $this->postJson('/api/jenis-barang/add', ['nama' => 'Elektronik']);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Jenis Barang berhasil disimpan',
            ])
            ->assertJsonPath('data.nama', 'Elektronik');

        // created_by_user_id is now persisted (was silently dropped before the
        // column was added to $fillable).
        $this->assertDatabaseHas('m_jenis_barang', [
            'nama' => 'Elektronik',
            'created_by_user_id' => 1,
        ]);
    }

    public function test_add_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/jenis-barang/add', ['nama' => '']);

        // BaseRequest shape: { message: { field: [..] } } (standar aplikasi)
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama']]);
    }

    public function test_update_found_returns_success(): void
    {
        $row = JenisBarang::query()->create(['nama' => 'Lama']);

        $response = $this->putJson("/api/jenis-barang/update/{$row->id}", ['nama' => 'Baru']);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Jenis Barang berhasil diupdate',
            ])
            ->assertJsonPath('data.nama', 'Baru');
    }

    public function test_update_not_found_returns_404(): void
    {
        $response = $this->putJson('/api/jenis-barang/update/999', ['nama' => 'X']);

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ]);
    }

    public function test_update_validation_error_returns_422(): void
    {
        $row = JenisBarang::query()->create(['nama' => 'Lama']);

        $response = $this->putJson("/api/jenis-barang/update/{$row->id}", ['nama' => '']);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama']]);
    }

    public function test_delete_found_returns_message_only(): void
    {
        $row = JenisBarang::query()->create(['nama' => 'Hapus']);

        $response = $this->deleteJson("/api/jenis-barang/delete/{$row->id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Jenis Barang berhasil dihapus',
            ]);

        // Row is now genuinely soft-deleted (deleted_at set via SoftDeletes),
        // with the audit column deleted_by persisted.
        $this->assertSoftDeleted('m_jenis_barang', [
            'id' => $row->id,
            'deleted_by' => 'Tester',
        ]);
    }

    public function test_delete_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/jenis-barang/delete/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('m_barang');
        Schema::dropIfExists('m_jenis_barang');
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
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_barang', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->unsignedInteger('jenis_barang_id')->nullable();
            $table->decimal('harga', 15, 2)->nullable();
            $table->string('satuan')->nullable();
            $table->integer('masa_pakai')->nullable();
            $table->string('merk')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
