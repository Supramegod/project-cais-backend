<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging mentah untuk sheet "CRM Share - ALL CRM" (kepemilikan site per CRM).
 *
 * Semua kolom sheet disimpan apa adanya sebagai teks — nol transformasi — supaya
 * normalisasi/rekonsiliasi bisa diulang tanpa import ulang CSV. Satu baris sheet
 * = satu site (bukan satu PKS): satu NO PKS bisa dipakai beberapa site.
 *
 * Kolom match_* diisi oleh tahap rekonsiliasi, bukan oleh importer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stg_crm_site_share')) {
            return;
        }

        Schema::create('stg_crm_site_share', function (Blueprint $table) {
            $table->id();
            $table->string('import_batch', 64);
            $table->unsignedInteger('row_number')->comment('Nomor baris di CSV (header = 1)');

            // ── Kolom sheet, urut sesuai header CSV ──────────────────────
            $table->string('crm', 64)->nullable();
            $table->text('no')->nullable();
            $table->string('kode_site', 64)->nullable();
            $table->string('nama_perusahaan', 255)->nullable();
            $table->string('no_pks', 191)->nullable();
            $table->string('nama_proyek', 255)->nullable();
            $table->string('status_pks', 128)->nullable();
            $table->text('bidang_usaha')->nullable();
            $table->string('service', 64)->nullable();
            $table->string('wilayah', 64)->nullable();
            $table->text('alamat_perusahaan')->nullable();
            $table->text('jenis_perusahaan')->nullable();
            $table->text('lokasi_site')->nullable();
            $table->text('kota')->nullable();
            $table->text('provinsi')->nullable();
            $table->text('pma_pmdn')->nullable();
            $table->text('awal_gabung')->nullable();
            $table->text('masa_kontrak')->nullable();
            $table->text('bulan')->nullable();
            $table->text('loyality')->nullable();
            $table->text('start_date')->nullable();
            $table->text('end_contract')->nullable();
            $table->text('deadline')->nullable();
            $table->text('kode_tahap')->nullable();
            $table->text('tahap_rekontrak')->nullable();
            $table->text('kode_realisasi')->nullable();
            $table->text('realisasi_tahap_kontrak')->nullable();
            $table->text('tanggal_pengiriman_proposal')->nullable();
            $table->text('tanggal_terminate_site')->nullable();
            $table->text('jumlah_hc')->nullable();
            $table->text('total_invoice')->nullable();
            $table->text('persen_mf')->nullable();
            $table->text('nominal_mf')->nullable();
            $table->text('prosentase_bpjs_tk')->nullable();
            $table->text('nominal_bpjs_tk')->nullable();
            $table->text('prosentase_bpjs_kes')->nullable();
            $table->text('nominal_bpjs_kes')->nullable();
            $table->text('as_tk')->nullable();
            $table->text('as_kes')->nullable();
            $table->text('ohc')->nullable();
            $table->text('thr_provisi')->nullable();
            $table->text('thr_ditagihkan')->nullable();
            $table->text('penagihan_selisih_thr')->nullable();
            $table->text('kaporlap')->nullable();
            $table->text('devices')->nullable();
            $table->text('pelaksanaan_training_dalam_1_tahun')->nullable();
            $table->text('biaya_training_dalam_1_tahun')->nullable();
            $table->text('kirim_invoice')->nullable();
            $table->text('time_of_payment')->nullable();
            $table->text('tanggal_gaji')->nullable();
            $table->text('pendaftaran_pks')->nullable();
            $table->text('status_pendaftaran_pks')->nullable();
            $table->text('biaya_pendaftaran_pks')->nullable();
            $table->text('no_bukti_pendaftaran')->nullable();
            $table->text('no_bukti_pendaftaran_pkwt')->nullable();
            $table->text('pic_1')->nullable();
            $table->text('jabatan_1')->nullable();
            $table->text('gender_1')->nullable();
            $table->text('pic_2')->nullable();
            $table->text('jabatan_2')->nullable();
            $table->text('gender_2')->nullable();
            $table->text('no_telp')->nullable();
            $table->text('hut_perusahaan')->nullable();
            $table->text('kategori_sesuai_headcount')->nullable();
            $table->string('grup', 255)->nullable();
            $table->text('no_grup')->nullable();
            $table->string('induk', 16)->nullable();
            $table->text('bendera_saat_ini')->nullable();
            $table->text('umk')->nullable();
            $table->text('kompesasi_pkwt')->nullable();
            $table->text('alasan_putus_kontrak')->nullable();
            $table->text('kriteria_putus_kontrak')->nullable();
            $table->text('target_visit')->nullable();
            $table->text('realisasi_visit')->nullable();
            $table->text('email_pic')->nullable();
            $table->string('branch', 64)->nullable();
            $table->text('tanggal_email_internal')->nullable();
            $table->text('ref_grup')->nullable();
            $table->text('status_pkwt')->nullable();
            $table->text('spv_ops')->nullable();

            // ── Hasil rekonsiliasi ke data CAIS ─────────────────────────
            $table->unsignedBigInteger('matched_pks_id')->nullable();
            $table->unsignedBigInteger('matched_site_id')->nullable();
            $table->unsignedBigInteger('matched_leads_id')->nullable();
            $table->string('match_by', 32)->nullable()->comment('no_pks | nama_site | nama_perusahaan');
            // match_status: matched | ambiguous | not_found | terminated
            // terminated = Tanggal Terminate Site terisi, site sudah putus, tidak dicocokkan.
            $table->string('match_status', 32)->nullable()->comment('matched | ambiguous | not_found');
            $table->text('match_note')->nullable();

            $table->timestamps();

            $table->index('import_batch');
            $table->index('kode_site');
            $table->index('no_pks');
            $table->index('nama_perusahaan');
            $table->index('crm');
            $table->index('match_status');
            $table->unique(['import_batch', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stg_crm_site_share');
    }
};
