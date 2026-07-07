<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected array $tables = [
        // P0
        'sl_activity_sales',
        'sl_customer_activity',
        'log_approval',
        'log_notification',
        // P1
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
        // P2
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
        // P3
        'sysmenu',
        'sysmenu_role',
        'm_barang', 'm_barang_default_qty', 'm_barang_import',
        'm_jenis_barang', 'm_jenis_visit',
        'm_kebutuhan', 'm_kebutuhan_detail',
        'm_kebutuhan_detail_requirement', 'm_kebutuhan_detail_tunjangan',
        'm_management_fee', 'm_requirement_posisi', 'm_salary_rule',
        'm_tim_sales', 'm_tim_sales_d', 'm_tunjangan', 'm_tunjangan_posisi',
        'm_training', 'm_top', 'm_ump', 'm_umk', 'm_umsk', 'm_umsp',
        'm_platform', 'm_status_leads', 'm_status_pks',
        'm_status_quotation', 'm_status_spk',
        'm_aplikasi_pendukung', 'm_bidang_perusahaan', 'm_jabatan_pic',
        'm_kategori_sesuai_hc', 'm_loyalty', 'm_rule_thr',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasColumn($table, 'created_by_user_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('created_by_user_id')
                      ->nullable()
                      ->after('created_by');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'created_by_user_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('created_by_user_id');
                });
            }
        }
    }
};
