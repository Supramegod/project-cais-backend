<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the exact JSON response shape of
 * DashboardPksController BEFORE refactoring it onto the ApiResponser trait.
 * All endpoints are read/aggregate; the envelope (keys present, status codes,
 * bespoke `pagination` block) must stay identical.
 */
class DashboardPksControllerTest extends TestCase
{
    const STATUS_BELUM_UPLOAD   = 5;
    const STATUS_BELUM_AKTIVASI = 6;
    const STATUS_AKTIF          = 7;

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

        DB::table('m_branch')->insert(['id' => 1, 'name' => 'Jakarta']);

        DB::table('m_status_pks')->insert([
            ['id' => self::STATUS_BELUM_UPLOAD, 'nama' => 'Menunggu Upload'],
            ['id' => self::STATUS_BELUM_AKTIVASI, 'nama' => 'Menunggu Aktivasi'],
            ['id' => self::STATUS_AKTIF, 'nama' => 'Kontrak Aktif'],
        ]);

        $this->actingAs(User::query()->findOrFail(1), 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);
    }

    private function insertPks(array $overrides = []): void
    {
        DB::table('sl_pks')->insert(array_merge([
            'nomor'           => 'PKS-'.Str::random(4),
            'nama_perusahaan' => 'PT Test',
            'branch_id'       => 1,
            'kontrak_awal'    => '2026-01-01',
            'kontrak_akhir'   => '2027-01-01',
            'status_pks_id'   => self::STATUS_AKTIF,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], $overrides));
    }

    public function test_summary_returns_success_data_envelope(): void
    {
        $today      = Carbon::today();
        // > 3 bulan
        $this->insertPks(['kontrak_akhir' => $today->copy()->addMonths(6)->format('Y-m-d')]);
        // mau habis (dalam 3 bulan)
        $this->insertPks(['kontrak_akhir' => $today->copy()->addMonth()->format('Y-m-d')]);
        // kontrak habis (sudah lewat)
        $this->insertPks(['kontrak_akhir' => $today->copy()->subMonth()->format('Y-m-d')]);
        // status non-aktif
        $this->insertPks(['status_pks_id' => self::STATUS_BELUM_UPLOAD, 'kontrak_akhir' => '2027-01-01']);
        $this->insertPks(['status_pks_id' => self::STATUS_BELUM_AKTIVASI, 'kontrak_akhir' => '2027-01-01']);

        $response = $this->getJson('/api/dashboard-pks/summary');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.lebih_dari_3_bulan', 1)
            ->assertJsonPath('data.mau_habis', 1)
            ->assertJsonPath('data.kontrak_habis', 1)
            ->assertJsonPath('data.belum_upload_pks', 1)
            ->assertJsonPath('data.belum_aktivasi_site', 1)
            ->assertJsonPath('data.total_kontrak_aktif', 3);

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_expiring_returns_data_and_pagination(): void
    {
        $today = Carbon::today();
        $this->insertPks(['kontrak_akhir' => $today->copy()->addDays(10)->format('Y-m-d'), 'nomor' => 'A']);
        $this->insertPks(['kontrak_akhir' => $today->copy()->addDays(200)->format('Y-m-d'), 'nomor' => 'B']);

        $response = $this->getJson('/api/dashboard-pks/expiring?per_page=5');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data')
            // ordered by kontrak_akhir asc → nearest first
            ->assertJsonPath('data.0.nomor', 'A')
            ->assertJsonPath('data.0.keterangan', 'Akan Habis')
            ->assertJsonPath('data.0.branch', 'Jakarta')
            ->assertJsonPath('data.1.keterangan', 'Berjalan')
            ->assertJsonStructure([
                'success',
                'data' => [['id', 'nomor', 'nama_perusahaan', 'branch', 'kontrak_awal', 'kontrak_akhir', 'sisa_hari', 'keterangan']],
                'pagination' => ['current_page', 'total_per_page', 'total', 'last_page'],
            ]);

        $this->assertSame(2, $response->json('pagination.total'));
        $this->assertSame(5, $response->json('pagination.total_per_page'));
    }

    public function test_list_returns_data_and_pagination(): void
    {
        $this->insertPks(['status_pks_id' => self::STATUS_AKTIF, 'nomor' => 'X']);
        $this->insertPks(['status_pks_id' => self::STATUS_BELUM_AKTIVASI, 'nomor' => 'Y']);

        $response = $this->getJson('/api/dashboard-pks/list?type=latest');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'success',
                'data' => [['id', 'nomor', 'nama_perusahaan', 'branch', 'kontrak_awal', 'status', 'status_pks_id']],
                'pagination' => ['current_page', 'total_per_page', 'total', 'last_page'],
            ]);
    }

    public function test_list_filtered_by_type_site_aktif(): void
    {
        $this->insertPks(['status_pks_id' => self::STATUS_AKTIF, 'nomor' => 'X']);
        $this->insertPks(['status_pks_id' => self::STATUS_BELUM_AKTIVASI, 'nomor' => 'Y']);

        $response = $this->getJson('/api/dashboard-pks/list?type=site-aktif');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status_pks_id', self::STATUS_AKTIF)
            ->assertJsonPath('data.0.status', 'Kontrak Aktif');
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('sl_pks');
        Schema::dropIfExists('m_branch');
        Schema::dropIfExists('m_status_pks');
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

        Schema::create('m_branch', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });

        Schema::create('m_status_pks', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
        });

        Schema::create('sl_pks', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nomor')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->string('kontrak_awal')->nullable();
            $table->string('kontrak_akhir')->nullable();
            $table->unsignedInteger('status_pks_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
