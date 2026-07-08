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
 * Characterization test for SpkController AFTER migrating its bespoke
 * private successResponse/errorResponse/notFoundResponse helpers onto the
 * shared App\Traits\ApiResponser trait (inherited via base Controller).
 *
 * These assertions lock the intended post-refactor JSON envelope:
 *   - success: { success: true, [message], [data] }
 *   - error:   { success: false, message, [errors] }
 *   - 404:     { success: false, message }
 *
 * NOTE: the class was un-loadable before the refactor (private helpers
 * narrowed the inherited protected trait methods → PHP fatal), so this test
 * is written to the target shapes rather than the pre-refactor behavior.
 */
class SpkControllerTest extends TestCase
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

    /** The controller class must load (it fatally could not before the refactor). */
    public function test_controller_class_loads(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\SpkController::class));
        new \ReflectionClass(\App\Http\Controllers\SpkController::class);
    }

    public function test_list_returns_success_data_envelope(): void
    {
        $this->seedLeads(1, 'PT Contoh', 'LEAD001');
        DB::table('m_status_spk')->insert(['id' => 1, 'nama' => 'Draft', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sl_spk')->insert([
            'id' => 1,
            'leads_id' => 1,
            'nomor' => 'SPK/LEAD001-012024-00001',
            'tgl_spk' => now()->toDateString(),
            'nama_perusahaan' => 'PT Contoh',
            'status_spk_id' => 1,
            'created_by' => 'Tester',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('sl_spk_site')->insert([
            'id' => 1,
            'spk_id' => 1,
            'nama_site' => 'Site A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/spk/list');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'SPK data retrieved successfully',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'list',
                    'pagination' => ['current_page', 'last_page', 'total', 'total_per_page'],
                ],
            ])
            ->assertJsonCount(1, 'data.list')
            ->assertJsonPath('data.list.0.nomor_spk', 'SPK/LEAD001-012024-00001')
            ->assertJsonPath('data.list.0.status', 'Draft');
    }

    public function test_view_not_found_returns_404_message_only(): void
    {
        $response = $this->getJson('/api/spk/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'SPK not found',
            ]);
    }

    public function test_delete_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/spk/delete/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'SPK not found',
            ]);
    }

    public function test_delete_site_not_found_returns_404(): void
    {
        // route: PUT /spk/delete-site/{id}
        $response = $this->putJson('/api/spk/delete-site/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'SPK site not found',
            ]);
    }

    public function test_delete_existing_spk_returns_message_only_envelope(): void
    {
        $this->seedLeads(1, 'PT Contoh', 'LEAD001', 1);
        DB::table('sl_spk')->insert([
            'id' => 1,
            'leads_id' => 1,
            'nomor' => 'SPK/LEAD001-012024-00001',
            'tgl_spk' => now()->toDateString(),
            'nama_perusahaan' => 'PT Contoh',
            'status_spk_id' => 1,
            'created_by' => 'Tester',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('sl_spk_site')->insert([
            'id' => 1,
            'spk_id' => 1,
            'nama_site' => 'Site A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson('/api/spk/delete/1');

        // messageResponse(): { success: true, message } — no data key.
        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'SPK deleted successfully',
            ]);

        $this->assertSoftDeleted('sl_spk', ['id' => 1]);
    }

    /**
     * Heavy multi-table endpoints (available-quotation / available-leads /
     * view-found / cetak / add / ajukan-ulang / upload / submit-checklist)
     * require seeding across the full quotation calculation graph
     * (quotation details, coss, wage, salary rule, tim sales, etc.) which is
     * out of scope for an envelope characterization test.
     */
    public function test_heavy_endpoints_skipped(): void
    {
        $this->markTestSkipped(
            'available-quotation/available-leads/view-found/cetak/add/ajukan-ulang/upload/submit-checklist '
            .'need the full quotation+site+calculation graph seeded; not feasible for an envelope test.'
        );
    }

    private function seedLeads(int $id, string $nama, string $nomor, ?int $kebutuhanId = null): void
    {
        DB::table('sl_leads')->insert([
            'id' => $id,
            'nama_perusahaan' => $nama,
            'nomor' => $nomor,
            'branch_id' => 1,
            'kebutuhan_id' => $kebutuhanId,
            'status_leads_id' => 1,
            'tgl_leads' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function rebuildSchema(): void
    {
        foreach (['m_user', 'sl_leads', 'm_status_spk', 'sl_spk', 'sl_spk_site', 'sl_customer_activity'] as $t) {
            Schema::dropIfExists($t);
        }

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

        Schema::create('sl_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_perusahaan')->nullable();
            $table->string('nomor')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->unsignedInteger('status_leads_id')->nullable();
            $table->date('tgl_leads')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_status_spk', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->string('kode')->nullable();
            $table->boolean('is_active')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_spk', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('quotation_id')->nullable();
            $table->string('nomor')->nullable();
            $table->date('tgl_spk')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->unsignedInteger('tim_sales_id')->nullable();
            $table->unsignedInteger('tim_sales_d_id')->nullable();
            $table->string('link_spk_disetujui')->nullable();
            $table->unsignedInteger('status_spk_id')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_spk_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('spk_id')->nullable();
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('quotation_site_id')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->string('nama_site')->nullable();
            $table->string('kota')->nullable();
            $table->string('penempatan')->nullable();
            $table->string('created_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_customer_activity', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('spk_id')->nullable();
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->dateTime('tgl_activity')->nullable();
            $table->string('nomor')->nullable();
            $table->string('tipe')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_activity')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
