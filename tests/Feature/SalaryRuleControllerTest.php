<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\SalaryRule;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the exact JSON response shape of
 * SalaryRuleController BEFORE refactoring it onto the ApiResponser trait.
 * The success envelope (keys present, status codes, success/data payload)
 * must stay identical; only the validation-422 shape moves to the
 * BaseRequest standard ({ message: { field: [..] } }).
 */
class SalaryRuleControllerTest extends TestCase
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

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'nama_salary_rule' => 'Salary Rule January 2024',
            'cutoff_awal' => 1,
            'cutoff_akhir' => 15,
            'crosscheck_absen_awal' => 16,
            'crosscheck_absen_akhir' => 20,
            'pengiriman_invoice_awal' => 21,
            'pengiriman_invoice_akhir' => 25,
            'perkiraan_invoice_diterima_awal' => 26,
            'perkiraan_invoice_diterima_akhir' => 30,
            'pembayaran_invoice' => 5,
            'rilis_payroll' => 10,
        ], $overrides);
    }

    private function seedRule(array $overrides = []): SalaryRule
    {
        return SalaryRule::query()->create(array_merge([
            'nama_salary_rule' => 'Rule A',
            'cutoff' => 'Tanggal 1 - 15',
            'cutoff_awal' => 1,
            'cutoff_akhir' => 15,
            'crosscheck_absen' => 'Tanggal 16 - 20',
            'crosscheck_absen_awal' => 16,
            'crosscheck_absen_akhir' => 20,
            'pengiriman_invoice' => 'Tanggal 21 - 25',
            'pengiriman_invoice_awal' => 21,
            'pengiriman_invoice_akhir' => 25,
            'perkiraan_invoice_diterima' => 'Tanggal 26 - 30',
            'perkiraan_invoice_diterima_awal' => 26,
            'perkiraan_invoice_diterima_akhir' => 30,
            'pembayaran_invoice' => 'Tanggal 5 bulan berikutnya',
            'tgl_pembayaran_invoice' => 5,
            'rilis_payroll' => 'Tanggal 10 bulan berikutnya',
            'tgl_rilis_payroll' => 10,
        ], $overrides));
    }

    public function test_list_returns_success_data_envelope(): void
    {
        $this->seedRule(['nama_salary_rule' => 'Rule A']);
        $this->seedRule(['nama_salary_rule' => 'Rule B']);

        $response = $this->getJson('/api/salary-rule/list');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_found_returns_success_data(): void
    {
        $row = $this->seedRule(['nama_salary_rule' => 'Rule View']);

        $response = $this->getJson("/api/salary-rule/view/{$row->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.nama_salary_rule', 'Rule View');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_not_found_returns_404_with_message(): void
    {
        $response = $this->getJson('/api/salary-rule/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Salary Rule tidak ditemukan',
            ]);
    }

    public function test_add_creates_and_returns_201(): void
    {
        $response = $this->postJson('/api/salary-rule/add', $this->validPayload());

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Salary Rule berhasil dibuat',
            ])
            ->assertJsonPath('data.nama_salary_rule', 'Salary Rule January 2024')
            ->assertJsonPath('data.cutoff', 'Tanggal 1 - 15')
            ->assertJsonPath('data.pembayaran_invoice', 'Tanggal 5 bulan berikutnya')
            ->assertJsonPath('data.rilis_payroll', 'Tanggal 10 bulan berikutnya');

        $this->assertDatabaseHas('m_salary_rule', [
            'nama_salary_rule' => 'Salary Rule January 2024',
            'tgl_pembayaran_invoice' => 5,
            'tgl_rilis_payroll' => 10,
            'created_by' => 'Tester',
        ]);
    }

    public function test_add_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/salary-rule/add', []);

        // BaseRequest shape: { message: { field: [..] } } (standar aplikasi)
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => [
                'nama_salary_rule',
                'cutoff_awal',
                'cutoff_akhir',
                'crosscheck_absen_awal',
                'crosscheck_absen_akhir',
                'pengiriman_invoice_awal',
                'pengiriman_invoice_akhir',
                'perkiraan_invoice_diterima_awal',
                'perkiraan_invoice_diterima_akhir',
                'pembayaran_invoice',
                'rilis_payroll',
            ]]);
    }

    public function test_update_found_returns_success(): void
    {
        $row = $this->seedRule(['nama_salary_rule' => 'Rule Lama']);

        $response = $this->putJson("/api/salary-rule/update/{$row->id}", $this->validPayload([
            'nama_salary_rule' => 'Rule Baru',
        ]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Salary Rule berhasil diupdate',
            ])
            ->assertJsonPath('data.nama_salary_rule', 'Rule Baru');

        $this->assertDatabaseHas('m_salary_rule', ['nama_salary_rule' => 'Rule Baru']);
    }

    public function test_update_not_found_returns_404(): void
    {
        $response = $this->putJson('/api/salary-rule/update/999', $this->validPayload());

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Salary Rule tidak ditemukan',
            ]);
    }

    public function test_delete_found_returns_message_only(): void
    {
        $row = $this->seedRule(['nama_salary_rule' => 'Rule Hapus']);

        $response = $this->deleteJson("/api/salary-rule/delete/{$row->id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Salary Rule berhasil dihapus',
            ]);

        $this->assertSoftDeleted('m_salary_rule', ['id' => $row->id]);
    }

    public function test_delete_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/salary-rule/delete/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Salary Rule tidak ditemukan',
            ]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('m_salary_rule');
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

        Schema::create('m_salary_rule', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_salary_rule')->nullable();
            $table->string('cutoff')->nullable();
            $table->integer('cutoff_awal')->nullable();
            $table->integer('cutoff_akhir')->nullable();
            $table->string('crosscheck_absen')->nullable();
            $table->integer('crosscheck_absen_awal')->nullable();
            $table->integer('crosscheck_absen_akhir')->nullable();
            $table->string('pengiriman_invoice')->nullable();
            $table->integer('pengiriman_invoice_awal')->nullable();
            $table->integer('pengiriman_invoice_akhir')->nullable();
            $table->string('perkiraan_invoice_diterima')->nullable();
            $table->integer('perkiraan_invoice_diterima_awal')->nullable();
            $table->integer('perkiraan_invoice_diterima_akhir')->nullable();
            $table->string('pembayaran_invoice')->nullable();
            $table->integer('tgl_pembayaran_invoice')->nullable();
            $table->string('rilis_payroll')->nullable();
            $table->integer('tgl_rilis_payroll')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
