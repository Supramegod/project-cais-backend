<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil resolusi visit anchor dari staging stg_crm_site_share.
 *
 * Satu baris = satu perusahaan (bukan satu PKS, bukan satu site). Kolom INDUK
 * di sheet CRM diisi TRUE sekali per perusahaan, walaupun perusahaan itu punya
 * beberapa PKS dengan kebutuhan berbeda. Baris di sini menyimpan site mana yang
 * jadi anchor untuk perusahaan tersebut plus jejak kenapa keputusan itu diambil,
 * supaya penulisan ke sl_site.is_visit_anchor bisa diaudit dan diulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_visit_anchor_map')) {
            return;
        }

        Schema::create('crm_visit_anchor_map', function (Blueprint $table) {
            $table->id();
            $table->string('import_batch', 64);

            // Kunci pengelompokan perusahaan. Sumbernya bertingkat: leads_id
            // hasil match kalau ada, kalau tidak nomor PKS, kalau tidak nama
            // perusahaan mentah dari sheet.
            $table->string('group_key', 191);
            $table->string('group_by', 32)->comment('leads_id | no_pks | nama_perusahaan');

            // Baris sheet yang dipilih sebagai induk.
            $table->unsignedInteger('induk_row_number')->nullable()->comment('row_number di stg_crm_site_share');
            $table->string('induk_kode_site', 64)->nullable();
            $table->string('induk_nama_perusahaan', 255)->nullable();
            $table->string('induk_no_pks', 191)->nullable();
            $table->string('induk_service', 64)->nullable();

            // Hasil match ke data CAIS.
            $table->unsignedBigInteger('matched_leads_id')->nullable();
            $table->unsignedBigInteger('matched_pks_id')->nullable();
            $table->unsignedBigInteger('matched_site_id')->nullable();

            $table->unsignedInteger('row_count')->default(0)->comment('Jumlah baris sheet dalam grup ini');
            $table->unsignedInteger('induk_count')->default(0)->comment('Jumlah baris INDUK=TRUE dalam grup ini');

            $table->string('resolve_status', 32)->comment('resolved | inherited | no_induk | multi_induk | site_not_found');
            $table->string('company_core', 191)->nullable()->comment('Nama perusahaan tanpa suffix cabang, dipakai mewarisi anchor antar PKS');
            $table->text('resolve_note')->nullable();

            // Diisi saat flag benar-benar ditulis ke sl_site.
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['import_batch', 'group_key']);
            $table->index('resolve_status');
            $table->index('company_core');
            $table->index('matched_site_id');
            $table->index('matched_pks_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_visit_anchor_map');
    }
};
