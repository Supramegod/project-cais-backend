<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test for the 4 single-method barang list controllers
 * (Chemical/Devices/Kaporlap/Ohc) BEFORE refactoring them onto ApiResponser.
 * Locks the { success, data } envelope and the jenis_barang_id filtering.
 */
class BarangListControllersTest extends TestCase
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
            'id' => 1, 'username' => 'tester', 'password' => bcrypt('secret'),
            'full_name' => 'Tester', 'email' => 'tester@example.com',
            'cais_role_id' => 2, 'branch_id' => 1, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(1), 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);

        // 1 jenis row + 1 barang per jenis id used by the 4 controllers
        foreach ([1, 6, 9, 13] as $jenisId) {
            DB::table('m_jenis_barang')->insert(['id' => $jenisId, 'nama' => "Jenis $jenisId"]);
            DB::table('m_barang')->insert([
                'nama' => "Barang $jenisId", 'jenis_barang_id' => $jenisId,
                'harga' => 1000, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public static function endpointProvider(): array
    {
        return [
            'kaporlap' => ['/api/kaporlap/list', 1],
            'ohc' => ['/api/ohc/list', 6],
            'devices' => ['/api/devices/list', 9],
            'chemical' => ['/api/chemical/list', 13],
        ];
    }

    #[DataProvider('endpointProvider')]
    public function test_list_returns_success_envelope_filtered_by_jenis(string $url, int $expectedJenisId): void
    {
        $response = $this->getJson($url);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.jenis_barang_id', $expectedJenisId);

        $this->assertArrayNotHasKey('message', $response->json());
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
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_barang', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->unsignedInteger('jenis_barang_id')->nullable();
            $table->decimal('harga', 20, 2)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
