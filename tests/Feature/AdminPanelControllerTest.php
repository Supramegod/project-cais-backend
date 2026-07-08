<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\Consultation;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the JSON response shapes of AdminPanelController
 * BEFORE/through refactoring it onto the ApiResponser trait + FormRequest.
 *
 * IMPORTANT: The consultation endpoints (GET/POST /admin-panel/consultations)
 * are PUBLIC (registered above the auth:sanctum group in routes/api.php).
 * Their tests deliberately do NOT authenticate, proving the routes stay public.
 * The quotation-step endpoints are behind auth and use authenticate().
 */
class AdminPanelControllerTest extends TestCase
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

        // NOTE: no actingAs() here — public endpoints must work unauthenticated.
        $this->withoutMiddleware(CheckTokenExpiry::class);
    }

    private function authenticate(): void
    {
        $this->actingAs(User::query()->findOrFail(1), 'web');
    }

    // ---- PUBLIC: GET /admin-panel/consultations ----

    public function test_get_consultations_is_public_and_returns_success_data(): void
    {
        Consultation::query()->create([
            'nama_lengkap' => 'Budi Utomo',
            'perusahaan' => 'PT Maju Bersama',
            'aplikasi' => 'shelter guard',
            'no_whatsapp' => '08123456789',
            'alamat_email' => 'budi@perusahaan.com',
            'jadwal_konsultasi' => '2026-04-20',
        ]);

        // No authenticate() — hitting the route unauthenticated.
        $response = $this->getJson('/api/admin-panel/consultations');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_lengkap', 'Budi Utomo');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    // ---- PUBLIC: POST /admin-panel/consultations ----

    public function test_store_consultation_is_public_and_creates_201(): void
    {
        $payload = [
            'nama_lengkap' => 'Budi Utomo',
            'perusahaan' => 'PT Maju Bersama',
            'aplikasi' => 'shelter guard',
            'no_whatsapp' => '08123456789',
            'alamat_email' => 'budi@perusahaan.com',
            'jadwal_konsultasi' => '2026-04-20',
        ];

        // No authenticate() — hitting the route unauthenticated.
        $response = $this->postJson('/api/admin-panel/consultations', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Jadwal konsultasi berhasil disimpan',
            ])
            ->assertJsonPath('data.nama_lengkap', 'Budi Utomo');

        $this->assertDatabaseHas('consultations', ['nama_lengkap' => 'Budi Utomo']);
    }

    public function test_store_consultation_validation_returns_422(): void
    {
        // BaseRequest shape after FormRequest adoption: { message: { field: [..] } }
        $response = $this->postJson('/api/admin-panel/consultations', [
            'nama_lengkap' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message' => [
                    'perusahaan',
                    'aplikasi',
                    'no_whatsapp',
                    'alamat_email',
                    'jadwal_konsultasi',
                ],
            ]);
    }

    // ---- AUTH: GET /admin-panel/quotations/{quotation}/step-data/{step} ----

    public function test_get_step_data_invalid_step_returns_400(): void
    {
        $this->authenticate();

        DB::table('sl_quotation')->insert([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/admin-panel/quotations/1/step-data/99');

        $response->assertStatus(400)
            ->assertJson(['success' => false])
            ->assertJsonPath('message', 'Step tidak valid. Step yang diperbolehkan: 3, 7, 8, 9, 10, 11');
    }

    // ---- Heavy step-update endpoints: skipped (require deep quotation graph) ----

    public function test_update_step_endpoints_skipped(): void
    {
        $this->markTestSkipped(
            'updateStep3/7/8/9/10/11 drive QuotationStepService against the full '
            .'quotation graph (sites, details, barang, positions, wages/hpp/coss). '
            .'Out of scope for an envelope characterization test; their response '
            .'envelopes are covered structurally by the trait swap.'
        );
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('consultations');
        Schema::dropIfExists('sl_quotation');
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

        Schema::create('consultations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_lengkap')->nullable();
            $table->string('perusahaan')->nullable();
            $table->string('aplikasi')->nullable();
            $table->string('no_whatsapp')->nullable();
            $table->string('alamat_email')->nullable();
            $table->date('jadwal_konsultasi')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation', function (Blueprint $table) {
            $table->increments('id');
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
