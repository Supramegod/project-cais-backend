<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\Position;
use App\Models\RequirementPosisi;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the JSON response shapes of PositionController
 * BEFORE/AFTER refactoring it onto the ApiResponser trait + FormRequest.
 *
 * Success envelopes ({success,[message],[data]}, plus the bespoke `total` on
 * list) are held byte-identical. The ONLY intentional change is the 422 body,
 * which moves from the old {success:false,message:'Validation failed',errors}
 * shape to the app-standard BaseRequest shape {message:{field:[...]}}.
 *
 * The three DB connections (sqlite/mysqlhris/mysql) are all pointed at the same
 * sqlite file so cross-connection `exists`/`unique` rules resolve.
 */
class PositionControllerTest extends TestCase
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

        // Reference rows for exists() rules.
        DB::table('m_company')->insert(['id' => 1, 'name' => 'Acme', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('m_kebutuhan')->insert(['id' => 1, 'nama' => 'IT Services', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('m_kebutuhan')->insert(['id' => 2, 'nama' => 'Security', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs(User::query()->findOrFail(1), 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);
    }

    private function makePosition(string $name = 'Developer'): Position
    {
        return Position::query()->create([
            'name' => $name,
            'description' => 'desc',
            'company_id' => 1,
            'layanan_id' => 1,
            'is_active' => true,
            'created_by' => 1,
            'updated_by' => 1,
        ]);
    }

    // ---- list ----

    public function test_list_returns_bespoke_envelope_with_total(): void
    {
        $this->makePosition('Alpha');
        $this->makePosition('Beta');

        $response = $this->getJson('/api/position/list');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data retrieved successfully',
                'total' => 2,
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['success', 'message', 'data', 'total']);
    }

    // ---- view ----

    public function test_view_found_returns_success_data(): void
    {
        $pos = $this->makePosition('Gamma');

        $response = $this->getJson("/api/position/view/{$pos->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data retrieved successfully',
            ])
            ->assertJsonPath('data.name', 'Gamma');
    }

    public function test_view_invalid_id_returns_400(): void
    {
        $response = $this->getJson('/api/position/view/0');

        $response->assertStatus(400)
            ->assertExactJson(['success' => false, 'message' => 'Invalid position ID']);
    }

    public function test_view_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/position/view/999');

        $response->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Position not found']);
    }

    // ---- save ----

    public function test_save_creates_and_returns_201(): void
    {
        $response = $this->postJson('/api/position/add', [
            'entitas' => 1,
            'layanan' => 1,
            'nama' => 'Software Engineer',
            'deskripsi' => 'Builds things',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Position created successfully',
            ])
            ->assertJsonPath('data.name', 'Software Engineer');

        $this->assertDatabaseHas('m_position', ['name' => 'Software Engineer']);
    }

    public function test_save_validation_error_returns_422_baserequest_shape(): void
    {
        $response = $this->postJson('/api/position/add', ['nama' => '']);

        // BaseRequest shape: { message: { field: [..] } } (intentional 422 change)
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['entitas', 'layanan', 'nama', 'deskripsi']]);
    }

    // ---- edit ----

    public function test_edit_found_returns_success(): void
    {
        $pos = $this->makePosition('Old');

        $response = $this->putJson("/api/position/edit/{$pos->id}", [
            'entitas' => 1,
            'layanan' => 2,
            'nama' => 'New Name',
            'deskripsi' => 'Updated',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Position updated successfully',
            ])
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('m_position', ['id' => $pos->id, 'layanan_id' => 2]);
    }

    public function test_edit_invalid_id_returns_400(): void
    {
        $response = $this->putJson('/api/position/edit/0', ['entitas' => 1, 'layanan' => 1]);

        $response->assertStatus(400)
            ->assertExactJson(['success' => false, 'message' => 'Invalid position ID']);
    }

    public function test_edit_not_found_returns_404(): void
    {
        $response = $this->putJson('/api/position/edit/999', ['entitas' => 1, 'layanan' => 1]);

        $response->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Position not found']);
    }

    public function test_edit_validation_error_returns_422(): void
    {
        $pos = $this->makePosition('EditMe');

        $response = $this->putJson("/api/position/edit/{$pos->id}", []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['entitas', 'layanan']]);
    }

    // ---- delete ----

    public function test_delete_found_returns_message_only(): void
    {
        $pos = $this->makePosition('Deletable');

        $response = $this->deleteJson("/api/position/delete/{$pos->id}");

        $response->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Position deleted successfully']);

        $this->assertDatabaseHas('m_position', ['id' => $pos->id, 'is_active' => false]);
    }

    public function test_delete_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/position/delete/999');

        $response->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Position not found']);
    }

    // ---- requirementList ----

    public function test_requirement_list_returns_success_data(): void
    {
        $pos = $this->makePosition('WithReq');
        RequirementPosisi::query()->create([
            'position_id' => $pos->id,
            'kebutuhan_id' => 1,
            'requirement' => 'S1 CS',
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        $response = $this->getJson("/api/position/requirement/list/{$pos->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data retrieved successfully',
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_requirement_list_position_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/position/requirement/list/999');

        $response->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Position not found']);
    }

    // ---- addRequirement ----

    public function test_add_requirement_returns_201(): void
    {
        $pos = $this->makePosition('ReqHost');

        $response = $this->postJson('/api/position/requirement/add', [
            'position_id' => $pos->id,
            'nama' => 'Minimal S1 unique text',
            'layanan_id' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Requirement added successfully',
            ])
            ->assertJsonPath('data.requirement', 'Minimal S1 unique text');
    }

    public function test_add_requirement_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/position/requirement/add', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['position_id', 'nama', 'layanan_id']]);
    }

    // ---- requirementEdit ----

    public function test_requirement_edit_returns_success(): void
    {
        $pos = $this->makePosition('ReqEditHost');
        $req = RequirementPosisi::query()->create([
            'position_id' => $pos->id,
            'kebutuhan_id' => 1,
            'requirement' => 'Old requirement',
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        $response = $this->putJson('/api/position/requirement/edit', [
            'id' => $req->id,
            'requirement' => 'New requirement',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Requirement updated successfully',
            ])
            ->assertJsonPath('data.requirement', 'New requirement');
    }

    public function test_requirement_edit_validation_error_returns_422(): void
    {
        $response = $this->putJson('/api/position/requirement/edit', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['id', 'requirement']]);
    }

    // ---- requirementDelete ----

    public function test_requirement_delete_returns_message_only(): void
    {
        $pos = $this->makePosition('ReqDelHost');
        $req = RequirementPosisi::query()->create([
            'position_id' => $pos->id,
            'kebutuhan_id' => 1,
            'requirement' => 'To delete',
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        $response = $this->deleteJson("/api/position/requirement/delete/{$req->id}");

        $response->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Requirement deleted successfully']);

        $this->assertSoftDeleted('m_requirement_posisi', ['id' => $req->id]);
    }

    public function test_requirement_delete_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/position/requirement/delete/999');

        $response->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Requirement not found']);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('m_requirement_posisi');
        Schema::dropIfExists('m_position');
        Schema::dropIfExists('m_kebutuhan');
        Schema::dropIfExists('m_company');
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

        Schema::create('m_company', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('m_kebutuhan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->string('icon')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_position', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('layanan_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('m_requirement_posisi', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('position_id')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->text('requirement')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
