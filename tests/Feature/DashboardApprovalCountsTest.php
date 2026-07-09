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
 * Characterization test for getListDashboardApprovalData's {counts, pending_approval_summary}
 * block, ahead of deduping getAllCaseCounts/getPendingApprovalSummary (audit #11 — both
 * computed the identical dir_sales/dir_keu COUNT queries twice). Locks the exact numbers
 * for a scenario covering all four buckets (menunggu_anda / dir_sales / dir_keu / belum_lengkap)
 * so the merge can be verified to produce byte-identical output.
 */
class DashboardApprovalCountsTest extends TestCase
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

        // Superadmin -> scopeByUserRole is a no-op, keeps the leads/whereHas filter simple.
        DB::table('m_user')->insert([
            'id' => 1, 'username' => 'admin', 'password' => bcrypt('x'),
            'full_name' => 'Admin', 'email' => 'admin@example.com',
            'cais_role_id' => 2, 'branch_id' => 1, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(1), 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);
    }

    public function test_counts_and_pending_summary_match_expected_buckets(): void
    {
        DB::table('sl_leads')->insert(['id' => 1, 'nama_perusahaan' => 'PT A']);

        // 1) dir_sales bucket: step 100, aktif=0, status=2, ot1 null.
        $this->seedQuotation(['id' => 1, 'leads_id' => 1, 'step' => 100, 'is_aktif' => 0, 'status_quotation_id' => 2, 'ot1' => null, 'ot2' => null, 'top' => null]);

        // 2) dir_keu bucket: ot1 set, ot2 null, top = 'Lebih Dari 7 Hari', has a detail whose wage.thr != 'diprovisikan'.
        $this->seedQuotation(['id' => 2, 'leads_id' => 1, 'step' => 100, 'is_aktif' => 0, 'status_quotation_id' => 2, 'ot1' => now(), 'ot2' => null, 'top' => 'Lebih Dari 7 Hari']);
        $detailId = DB::table('sl_quotation_detail')->insertGetId(['quotation_id' => 2, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sl_quotation_detail_wages')->insert(['quotation_detail_id' => $detailId, 'thr' => 'Tidak Diprovisikan', 'created_at' => now(), 'updated_at' => now()]);

        // 3) belum lengkap bucket: step != 100, status = 1.
        $this->seedQuotation(['id' => 3, 'leads_id' => 1, 'step' => 50, 'is_aktif' => 0, 'status_quotation_id' => 1, 'ot1' => null, 'ot2' => null, 'top' => null]);

        // 4) neither bucket: step 100, status 2, ot1+ot2 both set (fully approved, out of every filter).
        $this->seedQuotation(['id' => 4, 'leads_id' => 1, 'step' => 100, 'is_aktif' => 0, 'status_quotation_id' => 2, 'ot1' => now(), 'ot2' => now(), 'top' => 'Lebih Dari 7 Hari']);

        $response = $this->getJson('/api/dashboard-approval/list');

        $response->assertOk();
        $response->assertJsonPath('counts.semua', 4);
        $response->assertJsonPath('counts.menunggu_anda', 0); // superadmin (role 2) matches no menunggu-anda condition
        $response->assertJsonPath('counts.menunggu_approval', 2); // 1 dir_sales + 1 dir_keu
        $response->assertJsonPath('counts.quotation_belum_lengkap', 1);
        $response->assertJsonPath('pending_approval_summary.dir_sales', 1);
        $response->assertJsonPath('pending_approval_summary.dir_keu', 1);
    }

    private function seedQuotation(array $overrides): void
    {
        DB::table('sl_quotation')->insert(array_merge([
            'top' => null, 'ot5' => null, 'ot4' => null, 'ot3' => null, 'ot2' => null, 'ot1' => null,
            'jenis_kontrak' => null, 'company' => 'PT A', 'kebutuhan' => 'Security',
            'created_by' => 'Tester', 'nomor' => 'QUO/1', 'nama_perusahaan' => 'PT A',
            'tgl_quotation' => now(), 'created_at' => now(), 'updated_at' => now(),
        ], $overrides));
    }

    private function rebuildSchema(): void
    {
        foreach (['m_user', 'sl_leads', 'sl_quotation', 'sl_quotation_detail', 'sl_quotation_detail_wages', 'm_status_quotation', 'sl_quotation_site'] as $t) {
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
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('step')->nullable();
            $table->boolean('is_aktif')->default(0);
            $table->unsignedInteger('status_quotation_id')->nullable();
            $table->string('top')->nullable();
            $table->timestamp('ot5')->nullable();
            $table->timestamp('ot4')->nullable();
            $table->timestamp('ot3')->nullable();
            $table->timestamp('ot2')->nullable();
            $table->timestamp('ot1')->nullable();
            $table->string('jenis_kontrak')->nullable();
            $table->string('company')->nullable();
            $table->string('kebutuhan')->nullable();
            $table->string('created_by')->nullable();
            $table->string('nomor')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->date('tgl_quotation')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_detail', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_detail_wages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_detail_id')->nullable();
            $table->string('thr')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_status_quotation', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
        });

        Schema::create('sl_quotation_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->string('nama_site')->nullable();
            $table->softDeletes();
        });
    }
}
