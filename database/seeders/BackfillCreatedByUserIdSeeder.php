<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillCreatedByUserIdSeeder extends Seeder
{
    protected array $tables = [
        // P0 — Critical (report & relationship)
        'sl_activity_sales',
        'log_approval',
        'log_notification',
        // P1 — Core bisnis
        'sl_leads',
        // 'sl_leads_pic',
        'sl_quotation',
        'sl_quotation_site',
        'sl_quotation_detail',
        'sl_quotation_detail_wages',
        'sl_quotation_detail_hpp',
        'sl_quotation_detail_coss',
        'sl_quotation_detail_requirement',
        'sl_quotation_detail_tunjangan',
        'sl_quotation_aplikasi',
        'sl_quotation_chemical',
        'sl_quotation_devices',
        'sl_quotation_kaporlap',
        'sl_quotation_kerjasama',
        'sl_quotation_margin',
        'sl_quotation_ohc',
        'sl_quotation_pic',
        'sl_quotation_training',
        'sl_spk',
        'sl_spk_site',
        'sl_pks',
        'sl_pks_perjanjian',
        'sl_pks_import',
        'sl_site',
        'sl_putus_kontrak',
        'sl_perusahaan_groups',
        'sl_perusahaan_groups_d',
        'sl_issue',
        'sl_activity_sales_file',
        // P2 — Transaksi pendukung
        'sl_submission',
        // 'sl_submission_v2',
        'sl_good_receipt',
        'sl_good_receipt_d',
        'sl_purchase_order',
        'sl_purchase_order_d',
        'sl_purchase_request',
        'sl_purchase_request_d',
        'sl_receiving_notes',
        'sl_receiving_notes_d',
        'sl_customer',
        'log_error',
        // P3 — Master data
        'm_barang',
        'm_barang_default_qty',
        'm_barang_import',
        'm_jenis_barang',
        'm_jenis_visit',
        'm_kebutuhan',
        'm_kebutuhan_detail',
        'm_kebutuhan_detail_requirement',
        'm_kebutuhan_detail_tunjangan',
        'm_management_fee',
        'm_requirement_posisi',
        'm_salary_rule',
        'm_tim_sales',
        'm_tim_sales_d',
        'm_tunjangan',
        'm_tunjangan_posisi',
        'm_training',
        'm_top',
        'm_ump',
        'm_umk',
        'm_umsk',
        'm_umsp',
        'm_platform',
        'm_status_leads',
        'm_status_pks',
        'm_status_quotation',
        'm_status_spk',
        'm_aplikasi_pendukung',
        'm_bidang_perusahaan',
        'm_jabatan_pic',
        'm_kategori_sesuai_hc',
        'm_loyalty',
        'm_rule_thr',
        'sysmenu',
        'sysmenu_role',
    ];

    protected int $chunkSize = 500;

    public function run(): void
    {
        foreach ($this->tables as $table) {
            $this->processTable($table);
        }

        $this->command->info(PHP_EOL . '✅ All tables processed.');
        $this->outputSummary();
    }

    protected function processTable(string $table): void
    {
        $query = DB::table($table)
            ->whereNotNull('created_by')
            ->where('created_by', '!=', '')
            ->whereNull('created_by_user_id');

        if (Schema::hasColumn($table, 'created_at')) {
            $query->whereYear('created_at', '>=', 2026);
        }

        $totalPending = $query->count();

        if ($totalPending === 0) {
            $this->command->warn("  ⏭  {$table}: nothing to backfill");
            return;
        }

        $progress = $this->command->getOutput()->createProgressBar($totalPending);
        $progress->setFormat("  %message%: %current%/%max% [%bar%] %percent:3s%%");
        $progress->setMessage($table);
        $progress->start();

        $updated = 0;
        $skipped = 0;

        $chunkQuery = DB::table($table)
            ->whereNotNull('created_by')
            ->where('created_by', '!=', '')
            ->whereNull('created_by_user_id');

        if (Schema::hasColumn($table, 'created_at')) {
            $chunkQuery->whereYear('created_at', '>=', 2026);
        }

        $chunkQuery->orderBy('id')
            ->chunkById($this->chunkSize, function ($rows) use ($table, $progress, &$updated, &$skipped) {
                $names = $rows->pluck('created_by')->unique()->values()->toArray();

                $userMap = DB::connection('mysqlhris')
                    ->table('m_user')
                    ->whereIn('full_name', $names)
                    ->pluck('id', 'full_name');

                foreach ($rows as $row) {
                    if (isset($userMap[$row->created_by])) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update(['created_by_user_id' => $userMap[$row->created_by]]);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    $progress->advance();
                }
            });

        $progress->finish();
        $this->command->info('');
        $this->command->line("     ✓ {$updated} updated, {$skipped} skipped (unmatched names)");
    }

    protected function outputSummary(): void
    {
        $this->command->info(PHP_EOL . '=== Tables with NULL created_by_user_id (unmatched names) ===');
        foreach ($this->tables as $table) {
            $query = DB::table($table)
                ->whereNotNull('created_by')
                ->where('created_by', '!=', '')
                ->whereNull('created_by_user_id');

            if (Schema::hasColumn($table, 'created_at')) {
                $query->whereYear('created_at', '>=', 2026);
            }

            $nulls = $query->count();
            if ($nulls > 0) {
                $this->command->line("  {$table}: {$nulls} rows");
            }
        }
    }
}
