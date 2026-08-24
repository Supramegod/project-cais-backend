<?php

namespace Tests\Unit\Database\Seeders;

use Database\Seeders\BackfillItemRequestReceivedBatchSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Backfill tautan baris permintaan ke batch penerimaannya.
 *
 * Jalur ini tidak pernah tersentuh alur maju (request lalu receive mengisi
 * kolomnya langsung), jadi hanya test inilah yang membuktikan seeder-nya benar.
 *
 * @group pks-fulfillment
 */
class BackfillItemRequestReceivedBatchSeederTest extends TestCase
{
    private const BATCH_TERIMA = '11111111-1111-4111-8111-111111111111';

    private const BATCH_KIRIM = '22222222-2222-4222-8222-222222222222';

    protected ?string $tempDbPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = $this->tempDbPath = storage_path('framework/testing-'.Str::random(8).'.sqlite');
        touch($databasePath);

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $databasePath);
        Config::set('database.connections.mysql', array_merge(Config::get('database.connections.sqlite'), ['database' => $databasePath]));

        DB::purge('sqlite');
        DB::purge('mysql');
        DB::reconnect('sqlite');
        DB::reconnect('mysql');

        $this->rebuildSchema();
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('sl_pks_item_request');
        Schema::dropIfExists('sl_pks_fulfillment_log');

        Schema::create('sl_pks_item_request', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_id');
            $table->unsignedInteger('site_id');
            $table->unsignedInteger('fulfillment_id');
            $table->uuid('batch_id');
            $table->unsignedInteger('batch_ke')->nullable();
            $table->uuid('received_batch_id')->nullable();
            $table->unsignedInteger('received_batch_ke')->nullable();
            $table->string('item_type', 32);
            $table->unsignedInteger('item_id');
            $table->unsignedInteger('qty_request');
            $table->unsignedInteger('qty_diterima')->default(0);
            $table->string('status', 32)->default('open');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sl_pks_fulfillment_log', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_id');
            $table->string('jenis', 32);
            $table->unsignedInteger('reference_id');
            $table->uuid('batch_id')->nullable();
            $table->unsignedInteger('batch_ke')->nullable();
            $table->string('aksi', 32);
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function requestRow(int $id, array $overrides = []): void
    {
        DB::table('sl_pks_item_request')->insert(array_merge([
            'id' => $id,
            'pks_id' => 1,
            'site_id' => 1,
            'fulfillment_id' => 1,
            'batch_id' => self::BATCH_KIRIM,
            'batch_ke' => 1,
            'item_type' => 'kaporlap',
            'item_id' => 100 + $id,
            'qty_request' => 2,
            'qty_diterima' => 2,
            'status' => 'received',
            'created_at' => now(),
            'updated_at' => '2020-01-01 00:00:00',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function receiveLog(?array $meta, int $batchKe = 1, string $batchId = self::BATCH_TERIMA): void
    {
        DB::table('sl_pks_fulfillment_log')->insert([
            'pks_id' => 1,
            'jenis' => 'item',
            'reference_id' => 1,
            'batch_id' => $batchId,
            'batch_ke' => $batchKe,
            'aksi' => 'receive',
            'meta' => $meta === null ? null : json_encode($meta),
            'created_at' => now(),
        ]);
    }

    private function runSeeder(): void
    {
        (new BackfillItemRequestReceivedBatchSeeder)->run();
    }

    /** @test */
    public function test_fills_link_from_receive_log_request_ids(): void
    {
        $this->requestRow(1);
        $this->requestRow(2);
        $this->receiveLog(['request_ids' => [1, 2], 'qty_diterima' => 4], batchKe: 3);

        $this->runSeeder();

        $rows = DB::table('sl_pks_item_request')->orderBy('id')->get();

        $this->assertCount(2, $rows);

        foreach ($rows as $row) {
            $this->assertSame(self::BATCH_TERIMA, $row->received_batch_id);
            $this->assertSame(3, (int) $row->received_batch_ke);
        }
    }

    /** @test */
    public function test_is_idempotent_and_never_overwrites_existing_link(): void
    {
        $this->requestRow(1);
        $this->requestRow(2, ['received_batch_id' => self::BATCH_KIRIM, 'received_batch_ke' => 9]);
        $this->receiveLog(['request_ids' => [1, 2]], batchKe: 3);

        $this->runSeeder();
        $this->runSeeder();

        // Baris 1 terisi; baris 2 yang sudah punya tautan tidak pernah ditimpa.
        $this->assertSame(
            self::BATCH_TERIMA,
            DB::table('sl_pks_item_request')->where('id', 1)->value('received_batch_id')
        );
        $this->assertSame(
            self::BATCH_KIRIM,
            DB::table('sl_pks_item_request')->where('id', 2)->value('received_batch_id')
        );
        $this->assertSame(
            9,
            (int) DB::table('sl_pks_item_request')->where('id', 2)->value('received_batch_ke')
        );
    }

    /** @test */
    public function test_leaves_rows_null_when_log_has_no_request_ids(): void
    {
        $this->requestRow(1);
        $this->receiveLog(['qty_diterima' => 2]);   // log lama: tanpa request_ids
        $this->receiveLog(null, batchKe: 2, batchId: '33333333-3333-4333-8333-333333333333');

        $this->runSeeder();

        $this->assertNull(
            DB::table('sl_pks_item_request')->where('id', 1)->value('received_batch_id')
        );
    }

    /** @test */
    public function test_ignores_logs_of_other_aksi_and_jenis(): void
    {
        $this->requestRow(1);

        DB::table('sl_pks_fulfillment_log')->insert([
            ['pks_id' => 1, 'jenis' => 'item', 'reference_id' => 1, 'batch_id' => self::BATCH_KIRIM, 'batch_ke' => 1, 'aksi' => 'request', 'meta' => json_encode(['request_ids' => [1]]), 'created_at' => now()],
            ['pks_id' => 1, 'jenis' => 'visit', 'reference_id' => 1, 'batch_id' => self::BATCH_TERIMA, 'batch_ke' => 1, 'aksi' => 'receive', 'meta' => json_encode(['request_ids' => [1]]), 'created_at' => now()],
        ]);

        $this->runSeeder();

        $this->assertNull(
            DB::table('sl_pks_item_request')->where('id', 1)->value('received_batch_id')
        );
    }

    /** @test */
    public function test_does_not_touch_updated_at(): void
    {
        $this->requestRow(1);
        $this->receiveLog(['request_ids' => [1]]);

        $this->runSeeder();

        $this->assertSame(
            '2020-01-01 00:00:00',
            DB::table('sl_pks_item_request')->where('id', 1)->value('updated_at')
        );
    }

    protected function tearDown(): void
    {
        foreach (['sqlite', 'mysql'] as $connection) {
            try {
                DB::purge($connection);
            } catch (\Throwable) {
                // koneksi mungkin tidak terdaftar — abaikan
            }
        }

        if ($this->tempDbPath && file_exists($this->tempDbPath)) {
            @unlink($this->tempDbPath);
        }

        parent::tearDown();
    }
}
