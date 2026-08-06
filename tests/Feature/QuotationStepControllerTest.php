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
 * Characterization test locking the CURRENT JSON response shapes of
 * QuotationStepController::getStep / updateStep BEFORE adopting the
 * ApiResponser trait. The success envelope is bespoke (it carries an extra
 * `processing_time` key the trait cannot emit) and MUST stay identical; the
 * plain error envelopes (404 / 403 / 422-sequence) are what the refactor
 * converts to trait helpers, so they are pinned exactly here.
 *
 * The base sl_quotation* tables are not created by migrations (prod imports a
 * MySQL dump), so we hand-build a minimal schema in setUp(), mirroring
 * tests/Feature/QuotationCalculationCharacterizationTest.php. Only Step 1 of
 * getStep is exercised end-to-end (simplest builder — needs just kebutuhan);
 * steps requiring the full calc graph are skipped with a note.
 */
class QuotationStepControllerTest extends TestCase
{
    private const QUOTATION_ID = 100;

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
        $this->withoutMiddleware(CheckTokenExpiry::class);
    }   

    private function seedUser(int $roleId = 2, int $id = 1): User
    {
        DB::table('m_user')->insert([
            'id' => $id,
            'username' => 'tester'.$id,
            'password' => bcrypt('secret'),
            'full_name' => 'Tester '.$id,
            'email' => 'tester'.$id.'@example.com',
            'cais_role_id' => $roleId,
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    /**
     * One quotation sitting on step 1 (not finalized), with its kebutuhan row.
     */
    private function seedQuotation(int $step = 1, int $status = 3): void
    {
        DB::table('m_kebutuhan')->insert([
            'id' => 3, 'nama' => 'Cleaning Service', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('sl_quotation')->insert([
            'id' => self::QUOTATION_ID,
            'leads_id' => 10,
            'kebutuhan_id' => 3,
            'kebutuhan' => 'Cleaning Service',
            'nama_perusahaan' => 'PT Contoh',
            'jenis_kontrak' => 'Reguler',
            'status_quotation_id' => $status,
            'step' => $step,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_get_step_success_returns_processing_time_envelope(): void
    {
        $this->actingAs($this->seedUser(), 'web');
        $this->seedQuotation();

        $response = $this->getJson('/api/quotations-step/'.self::QUOTATION_ID.'/step/1');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Step data retrieved successfully',
            ])
            ->assertJsonPath('data.id', self::QUOTATION_ID)
            ->assertJsonPath('data.step', 1)
            ->assertJsonPath('data.step_data.jenis_kontrak', 'Reguler')
            ->assertJsonPath('data.step_data.layanan_id', 3)
            ->assertJsonPath('data.nama_perusahaan', 'PT Contoh');

        // Bespoke key that the ApiResponser trait cannot produce — must survive.
        $this->assertArrayHasKey('processing_time', $response->json());
        $this->assertStringEndsWith('ms', $response->json('processing_time'));
    }

    public function test_get_step_not_found_returns_404(): void
    {
        $this->actingAs($this->seedUser(), 'web');

        $response = $this->getJson('/api/quotations-step/999999/step/1');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Quotation not found',
            ]);
    }

    public function test_get_step_finalized_returns_403_for_non_privileged_role(): void
    {
        // Finalized quotation: step 100, status != 1, and a user whose role != 2.
        $this->actingAs($this->seedUser(5), 'web');
        $this->seedQuotation(step: 100, status: 3);

        $response = $this->getJson('/api/quotations-step/'.self::QUOTATION_ID.'/step/1');

        $response->assertStatus(403)
            ->assertExactJson([
                'success' => false,
                'message' => 'Quotation has been finalized and cannot be accessed.',
            ]);
    }

    public function test_update_step_sequence_violation_returns_422(): void
    {
        $this->actingAs($this->seedUser(), 'web');
        $this->seedQuotation(step: 1);

        // Step 6 should trigger the sequence guard because current step is 1.
        $response = $this->postJson('/api/quotations-step/'.self::QUOTATION_ID.'/step/6', []);

        $response->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'message' => 'Cannot update step 6. Please complete previous steps first (current step: 1).',
            ]);
    }

    public function test_update_step_success_skipped_needs_full_calc_graph(): void
    {
        // updateStep delegates to QuotationStepService::updateStepN which touches the
        // full wage/hpp/coss/site calculation graph (and, for step >= 11, the
        // ProcessQuotationFinalization path). Reproducing a green write here would
        // duplicate QuotationCalculationCharacterizationTest's heavy fixture without
        // adding coverage of THIS controller's response wrapping. Skipped by design.
        $this->markTestSkipped('updateStep success requires the full calculation graph; covered by QuotationCalculationCharacterizationTest.');
    }

    private function rebuildSchema(): void
    {
        foreach (['m_user', 'm_kebutuhan', 'sl_quotation'] as $table) {
            Schema::dropIfExists($table);
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

        Schema::create('m_kebutuhan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->string('kebutuhan')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->string('jenis_kontrak')->nullable();
            $table->unsignedInteger('status_quotation_id')->nullable();
            $table->integer('step')->nullable();
            $table->integer('version')->default(1);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
