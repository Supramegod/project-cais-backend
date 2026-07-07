<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the exact JSON response shape of
 * SupplierController BEFORE refactoring it onto the ApiResponser trait.
 * The success envelope (keys present, status codes, messages) must stay
 * identical. The only deliberate change is the 422 validation envelope,
 * which moves to the app-standard BaseRequest shape { message: { field: [..] } }.
 */
class SupplierControllerTest extends TestCase
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

    private function makeSupplier(array $overrides = []): Supplier
    {
        return Supplier::query()->create(array_merge([
            'nama_supplier' => 'PT Contoh',
            'alamat' => 'Jl. Contoh',
            'kontak' => '08123456789',
            'pic' => 'John Doe',
            'npwp' => '01.234.567.8-910.000',
            'kategori_barang' => 'Chemical',
            'created_by' => 'Tester',
        ], $overrides));
    }

    public function test_list_returns_success_data_envelope(): void
    {
        $this->makeSupplier(['nama_supplier' => 'PT A']);
        $this->makeSupplier(['nama_supplier' => 'PT B']);

        $response = $this->getJson('/api/supplier/list');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_found_returns_success_data(): void
    {
        $row = $this->makeSupplier(['nama_supplier' => 'PT View']);

        $response = $this->getJson("/api/supplier/view/{$row->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.nama_supplier', 'PT View');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_not_found_returns_404_with_message(): void
    {
        $response = $this->getJson('/api/supplier/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data supplier tidak ditemukan',
            ]);
    }

    public function test_add_creates_and_returns_201(): void
    {
        $response = $this->postJson('/api/supplier/add', [
            'nama' => 'PT Baru',
            'pic' => 'Jane',
            'alamat' => 'Jl. Baru',
            'kontak' => '0811',
            'npwp' => '02.345.678.9-101.000',
            'kategori_barang' => 'Equipment',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Supplier berhasil dibuat',
            ])
            ->assertJsonPath('data.nama_supplier', 'PT Baru');

        $this->assertDatabaseHas('m_supplier', ['nama_supplier' => 'PT Baru']);
    }

    public function test_add_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/supplier/add', [
            'nama' => '',
            'pic' => '',
            'alamat' => '',
            'kontak' => '',
        ]);

        // BaseRequest shape: { message: { field: [..] } } (standar aplikasi)
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama', 'pic', 'alamat', 'kontak']]);
    }

    public function test_update_found_returns_success(): void
    {
        $row = $this->makeSupplier(['nama_supplier' => 'PT Lama']);

        $response = $this->putJson("/api/supplier/update/{$row->id}", [
            'nama' => 'PT Update',
            'pic' => 'Jane',
            'alamat' => 'Jl. Update',
            'kontak' => '0899',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Supplier berhasil diupdate',
            ])
            ->assertJsonPath('data.nama_supplier', 'PT Update');
    }

    public function test_update_not_found_returns_404(): void
    {
        $response = $this->putJson('/api/supplier/update/999', [
            'nama' => 'X',
            'pic' => 'X',
            'alamat' => 'X',
            'kontak' => 'X',
        ]);

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Supplier tidak ditemukan',
            ]);
    }

    public function test_update_validation_error_returns_422(): void
    {
        $row = $this->makeSupplier();

        $response = $this->putJson("/api/supplier/update/{$row->id}", [
            'nama' => '',
            'pic' => '',
            'alamat' => '',
            'kontak' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama', 'pic', 'alamat', 'kontak']]);
    }

    public function test_delete_found_returns_message_only(): void
    {
        $row = $this->makeSupplier();

        $response = $this->deleteJson("/api/supplier/delete/{$row->id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Supplier berhasil dihapus',
            ]);

        $this->assertSoftDeleted('m_supplier', ['id' => $row->id]);
    }

    public function test_delete_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/supplier/delete/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Supplier tidak ditemukan',
            ]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('m_supplier');
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

        Schema::create('m_supplier', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_supplier')->nullable();
            $table->text('alamat')->nullable();
            $table->string('kontak')->nullable();
            $table->string('pic')->nullable();
            $table->string('npwp')->nullable();
            $table->string('kategori_barang')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
