<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\QuotationSite;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Log;

class QuotationDuplicationService
{
    private $quotationBusinessService;

    public function __construct(

        QuotationBusinessService $quotationBusinessService,

    ) {
        $this->quotationBusinessService = $quotationBusinessService;
    }
    /**
     * Mapping untuk detail_id dari referensi ke quotation baru
     */
    private $detailIdMapping = [];
    /**
     * Mapping untuk site_id dari referensi ke quotation baru
     */
    private $siteIdMapping = [];

    /**
     * ✅ Duplicate SEMUA quotation data (termasuk sites)
     */
    public function duplicateQuotationData(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        DB::beginTransaction();
        try {
            \Log::info('Starting duplication', [
                'new_id' => $newQuotation->id,
                'ref_id' => $quotationReferensi->id,
                'new_jenis_kontrak_before' => $newQuotation->jenis_kontrak,
                'ref_jenis_kontrak' => $quotationReferensi->jenis_kontrak
            ]);
            // Reset mapping
            $this->detailIdMapping = [];
            $this->siteIdMapping = [];

            // 1. COPY BASIC QUOTATION DATA FIRST
            $this->duplicateBasicQuotationData($newQuotation, $quotationReferensi);

            // 2. COPY SITES FIRST (sebelum details)
            $this->duplicateSites($newQuotation, $quotationReferensi);

            // 3. COPY QUOTATION DETAILS & RELATED DATA
            $this->duplicateQuotationDetails($newQuotation, $quotationReferensi);

            // 4. COPY APPLIKASI PENDUKUNG
            $this->duplicateAplikasiPendukung($newQuotation, $quotationReferensi);

            // 5. COPY BARANG DATA (Kaporlap, Devices, Chemicals, OHC) - DIPERBARUI
            $this->duplicateBarangData($newQuotation, $quotationReferensi);

            // 6. COPY TRAINING DATA
            $this->duplicateTrainingData($newQuotation, $quotationReferensi);

            // 7. COPY KERJASAMA DATA
            $this->duplicateKerjasamaData($newQuotation, $quotationReferensi);

            // 8. COPY PICS DATA
            $this->duplicatePicsData($newQuotation, $quotationReferensi);

            DB::commit();

            \Log::info('Duplication completed successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Duplication failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * ✅ Duplicate quotation data TANPA sites (untuk kasus site baru)
     * Pastikan sites sudah dibuat di quotation baru sebelum memanggil method ini
     */
    public function duplicateQuotationWithoutSites(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        DB::beginTransaction();
        try {
            \Log::info('Starting duplication WITHOUT sites', [
                'new_id' => $newQuotation->id,
                'ref_id' => $quotationReferensi->id,
                'new_sites' => $newQuotation->quotationSites->count(),
                'ref_sites' => $quotationReferensi->quotationSites->count()
            ]);

            // Reset mapping
            $this->detailIdMapping = [];

            // ✅ 1. COPY BASIC QUOTATION DATA
            $this->duplicateBasicQuotationData($newQuotation, $quotationReferensi);

            // ✅ 2. COPY QUOTATION DETAILS & RELATED DATA (dengan mapping ke site baru)
            $this->duplicateQuotationDetailsForNewSite($newQuotation, $quotationReferensi);

            // ✅ 3. COPY APPLIKASI PENDUKUNG
            $this->duplicateAplikasiPendukung($newQuotation, $quotationReferensi);

            // ✅ 4. COPY BARANG DATA dengan mapping yang baru - DIPERBARUI
            $this->duplicateBarangDataWithMapping($newQuotation, $quotationReferensi);

            // ✅ 5. COPY TRAINING DATA
            $this->duplicateTrainingData($newQuotation, $quotationReferensi);

            // ✅ 6. COPY KERJASAMA DATA
            $this->duplicateKerjasamaData($newQuotation, $quotationReferensi);

            // ✅ 7. COPY PICS DATA
            $this->duplicatePicsData($newQuotation, $quotationReferensi);

            DB::commit();

            \Log::info('Duplication WITHOUT sites completed successfully', [
                'detail_mapping_count' => count($this->detailIdMapping)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Duplication WITHOUT sites failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * ✅ Duplicate quotation data dengan mapping site per site
     * Digunakan ketika jumlah site baru sama dengan referensi
     */
    public function duplicateQuotationWithSiteMapping(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        DB::beginTransaction();
        try {
            \Log::info('Starting duplication WITH site mapping', [
                'new_id' => $newQuotation->id,
                'ref_id' => $quotationReferensi->id,
                'new_sites_count' => $newQuotation->quotationSites->count(),
                'ref_sites_count' => $quotationReferensi->quotationSites->count()
            ]);

            // Reset mapping
            $this->siteIdMapping = [];
            $this->detailIdMapping = [];

            // 1. COPY BASIC QUOTATION DATA
            $this->duplicateBasicQuotationData($newQuotation, $quotationReferensi);

            // 2. BUAT MAPPING SITE (asumsi urutan sama)
            $this->createSiteMapping($newQuotation, $quotationReferensi);

            // 3. COPY QUOTATION DETAILS dengan mapping site yang benar
            $this->duplicateQuotationDetailsWithSiteMapping($newQuotation, $quotationReferensi);

            // 4. COPY APPLIKASI PENDUKUNG
            $this->duplicateAplikasiPendukung($newQuotation, $quotationReferensi);

            // 5. COPY BARANG DATA dengan mapping detail yang baru - DIPERBARUI
            $this->duplicateBarangDataWithMapping($newQuotation, $quotationReferensi);

            // 6. COPY TRAINING DATA
            $this->duplicateTrainingData($newQuotation, $quotationReferensi);

            // 7. COPY KERJASAMA DATA
            $this->duplicateKerjasamaData($newQuotation, $quotationReferensi);

            // 8. COPY PICS DATA
            $this->duplicatePicsData($newQuotation, $quotationReferensi);

            DB::commit();

            \Log::info('Duplication WITH site mapping completed successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Duplication WITH site mapping failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * ✅ TAMBAHKAN METHOD BARU UNTUK DUPLICATE SITES
     */
    private function duplicateSites(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        \Log::info('Duplicating sites', [
            'sites_count' => $quotationReferensi->quotationSites->count()
        ]);

        foreach ($quotationReferensi->quotationSites as $siteReferensi) {
            $newSite = $newQuotation->quotationSites()->create([
                'leads_id' => $newQuotation->leads_id,
                'nama_site' => $siteReferensi->nama_site,
                'provinsi_id' => $siteReferensi->provinsi_id,
                'provinsi' => $siteReferensi->provinsi,
                'kota_id' => $siteReferensi->kota_id,
                'kota' => $siteReferensi->kota,
                'ump' => $siteReferensi->ump,
                'umk' => $siteReferensi->umk,
                'nominal_upah' => $siteReferensi->nominal_upah,
                'penempatan' => $siteReferensi->penempatan,
                'created_by' => $newQuotation->created_by
            ]);

            // Simpan mapping site
            $this->siteIdMapping[$siteReferensi->id] = $newSite->id;

            \Log::info('Site duplicated and mapped', [
                'old_id' => $siteReferensi->id,
                'new_id' => $newSite->id,
                'nama' => $newSite->nama_site
            ]);
        }
    }

    /**
     * Duplicate basic quotation data
     */
    private function duplicateBasicQuotationData(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        $newQuotation->update([
            // Contract details
            'jenis_kontrak' => $quotationReferensi->jenis_kontrak,
            'mulai_kontrak' => $quotationReferensi->mulai_kontrak,
            'kontrak_selesai' => $quotationReferensi->kontrak_selesai,
            'tgl_penempatan' => $quotationReferensi->tgl_penempatan ? Carbon::parse($quotationReferensi->tgl_penempatan)->isoFormat('Y-MM-DD') : null,

            // Payment & Salary details
            'salary_rule_id' => $quotationReferensi->salary_rule_id,
            'top' => $quotationReferensi->top,
            'jumlah_hari_invoice' => $quotationReferensi->jumlah_hari_invoice,
            'tipe_hari_invoice' => $quotationReferensi->tipe_hari_invoice,
            'upah' => $quotationReferensi->upah,
            'nominal_upah' => $quotationReferensi->nominal_upah,
            'hitungan_upah' => $quotationReferensi->hitungan_upah,

            // Management fee
            'management_fee_id' => $quotationReferensi->management_fee_id,
            'persentase' => $quotationReferensi->persentase,

            // Allowances
            'thr' => $quotationReferensi->thr,
            'kompensasi' => $quotationReferensi->kompensasi,
            'lembur' => $quotationReferensi->lembur,
            'nominal_lembur' => $quotationReferensi->nominal_lembur,
            'jenis_bayar_lembur' => $quotationReferensi->jenis_bayar_lembur,
            'lembur_ditagihkan' => $quotationReferensi->lembur_ditagihkan,
            'jam_per_bulan_lembur' => $quotationReferensi->jam_per_bulan_lembur,
            'tunjangan_holiday' => $quotationReferensi->tunjangan_holiday,
            'nominal_tunjangan_holiday' => $quotationReferensi->nominal_tunjangan_holiday,
            'jenis_bayar_tunjangan_holiday' => $quotationReferensi->jenis_bayar_tunjangan_holiday,

            // Tax
            'is_ppn' => $quotationReferensi->is_ppn,
            'ppn_pph_dipotong' => $quotationReferensi->ppn_pph_dipotong,

            // Leave
            'cuti' => $quotationReferensi->cuti,
            'hari_cuti_kematian' => $quotationReferensi->hari_cuti_kematian,
            'hari_istri_melahirkan' => $quotationReferensi->hari_istri_melahirkan,
            'hari_cuti_menikah' => $quotationReferensi->hari_cuti_menikah,
            'gaji_saat_cuti' => $quotationReferensi->gaji_saat_cuti,
            'prorate' => $quotationReferensi->prorate,

            // Work details
            'shift_kerja' => $quotationReferensi->shift_kerja,
            'hari_kerja' => $quotationReferensi->hari_kerja,
            'jam_kerja' => $quotationReferensi->jam_kerja,
            'evaluasi_kontrak' => $quotationReferensi->evaluasi_kontrak,
            'durasi_kerjasama' => $quotationReferensi->durasi_kerjasama,
            'durasi_karyawan' => $quotationReferensi->durasi_karyawan,
            'evaluasi_karyawan' => $quotationReferensi->evaluasi_karyawan,

            // Company details
            'jenis_perusahaan_id' => $quotationReferensi->jenis_perusahaan_id,
            'jenis_perusahaan' => $quotationReferensi->jenis_perusahaan,
            'bidang_perusahaan_id' => $quotationReferensi->bidang_perusahaan_id,
            'bidang_perusahaan' => $quotationReferensi->bidang_perusahaan,
            'resiko' => $quotationReferensi->resiko,

            // Visit & Training
            'kunjungan_operasional' => $quotationReferensi->kunjungan_operasional,
            'kunjungan_tim_crm' => $quotationReferensi->kunjungan_tim_crm,
            'keterangan_kunjungan_operasional' => $quotationReferensi->keterangan_kunjungan_operasional,
            'keterangan_kunjungan_tim_crm' => $quotationReferensi->keterangan_kunjungan_tim_crm,
            'training' => $quotationReferensi->training,

            // Financial
            'persen_bunga_bank' => $quotationReferensi->persen_bunga_bank,
            'persen_insentif' => $quotationReferensi->persen_insentif,
            'penagihan' => $quotationReferensi->penagihan,
            'note_harga_jual' => $quotationReferensi->note_harga_jual,

            // Status (kecuali approval status yang harus reset)
            'is_aktif' => 1,
            'revisi' => 0,
            'alasan_revisi' => null,
            'step' => 1,
        ]);
    }

    /**
     * ✅ Duplicate quotation details (untuk kasus normal dengan sites)
     */
    private function duplicateQuotationDetails(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        foreach ($quotationReferensi->quotationDetails as $detailReferensi) {
            // Create new detail
            $newDetail = $newQuotation->quotationDetails()->create([
                'quotation_site_id' => $this->getMappedSiteId($newQuotation, $detailReferensi->quotation_site_id),
                'position_id' => $detailReferensi->position_id,
                'jabatan_kebutuhan' => $detailReferensi->jabatan_kebutuhan,
                'nama_site' => $detailReferensi->nama_site,
                'jumlah_hc' => $detailReferensi->jumlah_hc,
                'nominal_upah' => $detailReferensi->nominal_upah,
                'penjamin_kesehatan' => $detailReferensi->penjamin_kesehatan,
                'is_bpjs_jkk' => $detailReferensi->is_bpjs_jkk,
                'is_bpjs_jkm' => $detailReferensi->is_bpjs_jkm,
                'is_bpjs_jht' => $detailReferensi->is_bpjs_jht,
                'is_bpjs_jp' => $detailReferensi->is_bpjs_jp,
                'nominal_takaful' => $detailReferensi->nominal_takaful,
                'biaya_monitoring_kontrol' => $detailReferensi->biaya_monitoring_kontrol,
                'created_by' => $newQuotation->created_by
            ]);

            // ✅ FIX: Simpan mapping detail_id lama -> baru (ini yang hilang!)
            $this->detailIdMapping[$detailReferensi->id] = $newDetail->id;

            \Log::info('Created detail with mapping', [
                'old_detail_id' => $detailReferensi->id,
                'new_detail_id' => $newDetail->id,
                'position_id' => $detailReferensi->position_id,
                'quotation_site_id' => $newDetail->quotation_site_id
            ]);

            // Copy wage data
            if ($detailReferensi->relationLoaded('wage') && $detailReferensi->wage) {
                $newDetail->wage()->create([
                    'quotation_id' => $newQuotation->id,
                    'upah' => $detailReferensi->wage->upah,
                    'hitungan_upah' => $detailReferensi->wage->hitungan_upah,
                    'lembur' => $detailReferensi->wage->lembur,
                    'nominal_lembur' => $detailReferensi->wage->nominal_lembur,
                    'jenis_bayar_lembur' => $detailReferensi->wage->jenis_bayar_lembur,
                    'jam_per_bulan_lembur' => $detailReferensi->wage->jam_per_bulan_lembur,
                    'lembur_ditagihkan' => $detailReferensi->wage->lembur_ditagihkan,
                    'kompensasi' => $detailReferensi->wage->kompensasi,
                    'thr' => $detailReferensi->wage->thr,
                    'tunjangan_holiday' => $detailReferensi->wage->tunjangan_holiday,
                    'nominal_tunjangan_holiday' => $detailReferensi->wage->nominal_tunjangan_holiday,
                    'jenis_bayar_tunjangan_holiday' => $detailReferensi->wage->jenis_bayar_tunjangan_holiday,
                    'created_by' => $newQuotation->created_by
                ]);
            }

            // Copy tunjangan
            foreach ($detailReferensi->quotationDetailTunjangans as $tunjangan) {
                $newDetail->quotationDetailTunjangans()->create([
                    'quotation_id' => $newQuotation->id,
                    'nama_tunjangan' => $tunjangan->nama_tunjangan,
                    'nominal' => $tunjangan->nominal,
                    'nominal_coss' => $tunjangan->nominal_coss,
                    'created_by' => $newQuotation->created_by
                ]);
            }

            // Copy HPP data
            if ($detailReferensi->quotationDetailHpp) {
                $hpp = $detailReferensi->quotationDetailHpp;
                $newDetail->quotationDetailHpp()->create([
                    'quotation_id' => $newQuotation->id,
                    'jumlah_hc' => $hpp->jumlah_hc,
                    'gaji_pokok' => $hpp->gaji_pokok,
                    'tunjangan_hari_raya' => $hpp->tunjangan_hari_raya,
                    'kompensasi' => $hpp->kompensasi,
                    'tunjangan_hari_libur_nasional' => $hpp->tunjangan_hari_libur_nasional,
                    'lembur' => $hpp->lembur,
                    'bpjs_jkk' => $hpp->bpjs_jkk,
                    'bpjs_jkm' => $hpp->bpjs_jkm,
                    'bpjs_jht' => $hpp->bpjs_jht,
                    'bpjs_jp' => $hpp->bpjs_jp,
                    'bpjs_ks' => $hpp->bpjs_ks,
                    'takaful' => $hpp->takaful,
                    'provisi_seragam' => $hpp->provisi_seragam,
                    'provisi_peralatan' => $hpp->provisi_peralatan,
                    'provisi_chemical' => $hpp->provisi_chemical,
                    'provisi_ohc' => $hpp->provisi_ohc,
                    'bunga_bank' => $hpp->bunga_bank,
                    'insentif' => $hpp->insentif,
                    'total_hpp' => $hpp->total_hpp,
                    'created_by' => $newQuotation->created_by
                ]);
            }

            // Copy COSS data
            if ($detailReferensi->quotationDetailCoss) {
                $coss = $detailReferensi->quotationDetailCoss;
                $newDetail->quotationDetailCoss()->create([
                    'quotation_id' => $newQuotation->id,
                    'jumlah_hc' => $coss->jumlah_hc,
                    'gaji_pokok' => $coss->gaji_pokok,
                    'tunjangan_hari_raya' => $coss->tunjangan_hari_raya,
                    'kompensasi' => $coss->kompensasi,
                    'tunjangan_hari_libur_nasional' => $coss->tunjangan_hari_libur_nasional,
                    'lembur' => $coss->lembur,
                    'bpjs_jkk' => $coss->bpjs_jkk,
                    'bpjs_jkm' => $coss->bpjs_jkm,
                    'bpjs_jht' => $coss->bpjs_jht,
                    'bpjs_jp' => $coss->bpjs_jp,
                    'bpjs_ks' => $coss->bpjs_ks,
                    'takaful' => $coss->takaful,
                    'provisi_seragam' => $coss->provisi_seragam,
                    'provisi_peralatan' => $coss->provisi_peralatan,
                    'provisi_chemical' => $coss->provisi_chemical,
                    'provisi_ohc' => $coss->provisi_ohc,
                    'bunga_bank' => $coss->bunga_bank,
                    'insentif' => $coss->insentif,
                    'management_fee' => $coss->management_fee,
                    'ppn' => $coss->ppn,
                    'pph' => $coss->pph,
                    'total_coss' => $coss->total_coss,
                    'created_by' => $newQuotation->created_by
                ]);
            }

            // Copy requirements
            foreach ($detailReferensi->quotationDetailRequirements as $requirement) {
                $newDetail->quotationDetailRequirements()->create([
                    'quotation_id' => $newQuotation->id,
                    'requirement' => $requirement->requirement,
                    'created_by' => $newQuotation->created_by
                ]);
            }
        }
    }

    private function duplicateQuotationDetailsForNewSite(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        \Log::info('Duplicating quotation details for ALL sites', [
            'new_quotation_id' => $newQuotation->id,
            'referensi_quotation_id' => $quotationReferensi->id,
            'detail_count' => $quotationReferensi->quotationDetails->count()
        ]);

        // ✅ AMBIL SEMUA SITE dari quotation baru
        $newSites = $newQuotation->quotationSites;

        if ($newSites->isEmpty()) {
            throw new \Exception('No sites found in new quotation. Sites must be created before details.');
        }

        // ✅ BUAT MAPPING: site lama -> site baru (berdasarkan nama atau urutan)
        $siteMapping = [];
        $oldSites = $quotationReferensi->quotationSites;

        foreach ($oldSites as $index => $oldSite) {
            // Cari site baru yang matching (by nama_site)
            $matchedNewSite = $newSites->firstWhere('nama_site', $oldSite->nama_site);

            // Jika tidak ketemu by nama, pakai index
            if (!$matchedNewSite && isset($newSites[$index])) {
                $matchedNewSite = $newSites[$index];
            }

            if ($matchedNewSite) {
                $siteMapping[$oldSite->id] = $matchedNewSite->id;
            }
        }

        \Log::info('Site mapping created', ['mapping' => $siteMapping]);

        foreach ($quotationReferensi->quotationDetails as $detailReferensi) {
            // ✅ GUNAKAN SITE YANG SESUAI dari mapping
            $newSiteId = $siteMapping[$detailReferensi->quotation_site_id] ?? null;
            $newSite = $newSites->firstWhere('id', $newSiteId);

            if (!$newSiteId) {
                \Log::warning('No matching site found for detail', [
                    'detail_id' => $detailReferensi->id,
                    'old_site_id' => $detailReferensi->quotation_site_id
                ]);
                continue; // Skip detail ini
            }
            // Create new detail linked to the NEW site
            $newDetail = $newQuotation->quotationDetails()->create([
                'quotation_site_id' => $newSiteId,
                'position_id' => $detailReferensi->position_id,
                'jabatan_kebutuhan' => $detailReferensi->jabatan_kebutuhan,
                'nama_site' => $newSite->nama_site,
                'jumlah_hc' => $detailReferensi->jumlah_hc,
                'nominal_upah' => $detailReferensi->nominal_upah,
                'penjamin_kesehatan' => $detailReferensi->penjamin_kesehatan,
                'is_bpjs_jkk' => $detailReferensi->is_bpjs_jkk,
                'is_bpjs_jkm' => $detailReferensi->is_bpjs_jkm,
                'is_bpjs_jht' => $detailReferensi->is_bpjs_jht,
                'is_bpjs_jp' => $detailReferensi->is_bpjs_jp,
                'nominal_takaful' => $detailReferensi->nominal_takaful,
                'biaya_monitoring_kontrol' => $detailReferensi->biaya_monitoring_kontrol,
                'created_by' => $newQuotation->created_by
            ]);

            // Simpan mapping detail_id lama -> baru - PASTIKAN INI DIISI
            $this->detailIdMapping[$detailReferensi->id] = $newDetail->id;

            \Log::info('Created detail with new site mapping', [
                'old_detail_id' => $detailReferensi->id,
                'new_detail_id' => $newDetail->id,
                'position_id' => $detailReferensi->position_id,
                'mapping_saved' => isset($this->detailIdMapping[$detailReferensi->id])
            ]);

            // ✅ COPY WAGE DATA
            if ($detailReferensi->relationLoaded('wage') && $detailReferensi->wage) {
                $newDetail->wage()->create([
                    'quotation_id' => $newQuotation->id,
                    'upah' => $detailReferensi->wage->upah,
                    'hitungan_upah' => $detailReferensi->wage->hitungan_upah,
                    'lembur' => $detailReferensi->wage->lembur,
                    'nominal_lembur' => $detailReferensi->wage->nominal_lembur,
                    'jenis_bayar_lembur' => $detailReferensi->wage->jenis_bayar_lembur,
                    'jam_per_bulan_lembur' => $detailReferensi->wage->jam_per_bulan_lembur,
                    'lembur_ditagihkan' => $detailReferensi->wage->lembur_ditagihkan,
                    'kompensasi' => $detailReferensi->wage->kompensasi,
                    'thr' => $detailReferensi->wage->thr,
                    'tunjangan_holiday' => $detailReferensi->wage->tunjangan_holiday,
                    'nominal_tunjangan_holiday' => $detailReferensi->wage->nominal_tunjangan_holiday,
                    'jenis_bayar_tunjangan_holiday' => $detailReferensi->wage->jenis_bayar_tunjangan_holiday,
                    'created_by' => $newQuotation->created_by
                ]);
            }

            // Copy tunjangan
            foreach ($detailReferensi->quotationDetailTunjangans as $tunjangan) {
                $newDetail->quotationDetailTunjangans()->create([
                    'quotation_id' => $newQuotation->id,
                    'nama_tunjangan' => $tunjangan->nama_tunjangan,
                    'nominal' => $tunjangan->nominal,
                    'nominal_coss' => $tunjangan->nominal_coss,
                    'created_by' => $newQuotation->created_by
                ]);
            }

            // Copy HPP data
            if ($detailReferensi->quotationDetailHpp) {
                $hpp = $detailReferensi->quotationDetailHpp;
                $newDetail->quotationDetailHpp()->create([
                    'quotation_id' => $newQuotation->id,
                    'jumlah_hc' => $hpp->jumlah_hc,
                    'gaji_pokok' => $hpp->gaji_pokok,
                    'tunjangan_hari_raya' => $hpp->tunjangan_hari_raya,
                    'kompensasi' => $hpp->kompensasi,
                    'tunjangan_hari_libur_nasional' => $hpp->tunjangan_hari_libur_nasional,
                    'lembur' => $hpp->lembur,
                    'bpjs_jkk' => $hpp->bpjs_jkk,
                    'bpjs_jkm' => $hpp->bpjs_jkm,
                    'bpjs_jht' => $hpp->bpjs_jht,
                    'bpjs_jp' => $hpp->bpjs_jp,
                    'bpjs_ks' => $hpp->bpjs_ks,
                    'takaful' => $hpp->takaful,
                    'provisi_seragam' => $hpp->provisi_seragam,
                    'provisi_peralatan' => $hpp->provisi_peralatan,
                    'provisi_chemical' => $hpp->provisi_chemical,
                    'provisi_ohc' => $hpp->provisi_ohc,
                    'bunga_bank' => $hpp->bunga_bank,
                    'insentif' => $hpp->insentif,
                    'total_hpp' => $hpp->total_hpp,
                    'created_by' => $newQuotation->created_by
                ]);
            }

            // Copy COSS data
            if ($detailReferensi->quotationDetailCoss) {
                $coss = $detailReferensi->quotationDetailCoss;
                $newDetail->quotationDetailCoss()->create([
                    'quotation_id' => $newQuotation->id,
                    'jumlah_hc' => $coss->jumlah_hc,
                    'gaji_pokok' => $coss->gaji_pokok,
                    'tunjangan_hari_raya' => $coss->tunjangan_hari_raya,
                    'kompensasi' => $coss->kompensasi,
                    'tunjangan_hari_libur_nasional' => $coss->tunjangan_hari_libur_nasional,
                    'lembur' => $coss->lembur,
                    'bpjs_jkk' => $coss->bpjs_jkk,
                    'bpjs_jkm' => $coss->bpjs_jkm,
                    'bpjs_jht' => $coss->bpjs_jht,
                    'bpjs_jp' => $coss->bpjs_jp,
                    'bpjs_ks' => $coss->bpjs_ks,
                    'takaful' => $coss->takaful,
                    'provisi_seragam' => $coss->provisi_seragam,
                    'provisi_peralatan' => $coss->provisi_peralatan,
                    'provisi_chemical' => $coss->provisi_chemical,
                    'provisi_ohc' => $coss->provisi_ohc,
                    'bunga_bank' => $coss->bunga_bank,
                    'insentif' => $coss->insentif,
                    'management_fee' => $coss->management_fee,
                    'ppn' => $coss->ppn,
                    'pph' => $coss->pph,
                    'total_coss' => $coss->total_coss,
                    'created_by' => $newQuotation->created_by
                ]);
            }

            // Copy requirements
            foreach ($detailReferensi->quotationDetailRequirements as $requirement) {
                $newDetail->quotationDetailRequirements()->create([
                    'quotation_id' => $newQuotation->id,
                    'requirement' => $requirement->requirement,
                    'created_by' => $newQuotation->created_by
                ]);
            }
        }

        // Load ulang details untuk memastikan data fresh
        $newQuotation->load('quotationDetails');
    }

    /**
     * Duplicate aplikasi pendukung data
     */
    private function duplicateAplikasiPendukung(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        foreach ($quotationReferensi->quotationAplikasis as $aplikasi) {
            $newQuotation->quotationAplikasis()->create([
                'aplikasi_pendukung_id' => $aplikasi->aplikasi_pendukung_id,
                'aplikasi_pendukung' => $aplikasi->aplikasi_pendukung,
                'harga' => $aplikasi->harga,
                'created_by' => $newQuotation->created_by
            ]);
        }
    }

    /**
     * Duplicate all barang-related data (Kaporlap, Devices, Chemicals, OHC)
     * ✅ DIPERBARUI: Devices, Chemicals, OHC menggunakan quotation_site_id (barang general)
     * ✅ Kaporlap tetap menggunakan quotation_detail_id (spesifik per detail)
     */
    private function duplicateBarangData(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        \Log::info('Starting duplicateBarangData (UPDATED)', [
            'new_quotation_id' => $newQuotation->id,
            'detail_mapping_count' => count($this->detailIdMapping),
            'site_mapping_count' => count($this->siteIdMapping),
            'kaporlap_count' => $quotationReferensi->quotationKaporlaps->count(),
            'devices_count' => $quotationReferensi->quotationDevices->count(),
            'chemicals_count' => $quotationReferensi->quotationChemicals->count(),
            'ohcs_count' => $quotationReferensi->quotationOhcs->count()
        ]);

        // ✅ 1. Copy Kaporlap data (tetap menggunakan quotation_detail_id)
        foreach ($quotationReferensi->quotationKaporlaps as $kaporlap) {
            $newDetailId = $this->getMappedDetailIdWithMapping($newQuotation, $kaporlap->quotation_detail_id);

            \Log::info('Creating Kaporlap (detail-specific)', [
                'old_detail_id' => $kaporlap->quotation_detail_id,
                'new_detail_id' => $newDetailId,
                'nama' => $kaporlap->nama,
                'jumlah' => $kaporlap->jumlah
            ]);

            $newQuotation->quotationKaporlaps()->create([
                'quotation_detail_id' => $newDetailId,
                'barang_id' => $kaporlap->barang_id,
                'nama' => $kaporlap->nama,
                'jenis_barang_id' => $kaporlap->jenis_barang_id,
                'jenis_barang' => $kaporlap->jenis_barang,
                'jumlah' => $kaporlap->jumlah,
                'harga' => $kaporlap->harga,
                'created_by' => $newQuotation->created_by
            ]);
        }

        // ✅ 2. Copy Devices data (menggunakan quotation_site_id - barang general)
        foreach ($quotationReferensi->quotationDevices as $device) {
            // Dapatkan site_id lama dari referensi
            $originalSiteId = $this->getOriginalSiteIdFromDevice($device);
            $newSiteId = $this->getMappedSiteId($newQuotation, $originalSiteId);

            \Log::info('Creating Device (site-specific)', [
                'original_site_id' => $originalSiteId,
                'new_site_id' => $newSiteId,
                'nama' => $device->nama,
                'jumlah' => $device->jumlah
            ]);

            $newQuotation->quotationDevices()->create([
                'quotation_site_id' => $newSiteId, // ✅ Menggunakan quotation_site_id
                'barang_id' => $device->barang_id,
                'nama' => $device->nama,
                'jenis_barang_id' => $device->jenis_barang_id,
                'jenis_barang' => $device->jenis_barang,
                'jumlah' => $device->jumlah,
                'harga' => $device->harga,
                'created_by' => $newQuotation->created_by
            ]);
        }

        // ✅ 3. Copy Chemicals data (menggunakan quotation_site_id - barang general)
        foreach ($quotationReferensi->quotationChemicals as $chemical) {
            // Dapatkan site_id lama dari referensi
            $originalSiteId = $this->getOriginalSiteIdFromChemical($chemical);
            $newSiteId = $this->getMappedSiteId($newQuotation, $originalSiteId);

            \Log::info('Creating Chemical (site-specific)', [
                'original_site_id' => $originalSiteId,
                'new_site_id' => $newSiteId,
                'nama' => $chemical->nama,
                'masa_pakai' => $chemical->masa_pakai
            ]);

            $newQuotation->quotationChemicals()->create([
                'quotation_site_id' => $newSiteId, // ✅ Menggunakan quotation_site_id
                'barang_id' => $chemical->barang_id,
                'nama' => $chemical->nama,
                'jenis_barang_id' => $chemical->jenis_barang_id,
                'jenis_barang' => $chemical->jenis_barang,
                'jumlah' => $chemical->jumlah,
                'harga' => $chemical->harga,
                'masa_pakai' => $chemical->masa_pakai,
                'created_by' => $newQuotation->created_by
            ]);
        }

        // ✅ 4. Copy OHC data (menggunakan quotation_site_id - barang general)
        foreach ($quotationReferensi->quotationOhcs as $ohc) {
            // Dapatkan site_id lama dari referensi
            $originalSiteId = $this->getOriginalSiteIdFromOhc($ohc);
            $newSiteId = $this->getMappedSiteId($newQuotation, $originalSiteId);

            \Log::info('Creating OHC (site-specific)', [
                'original_site_id' => $originalSiteId,
                'new_site_id' => $newSiteId,
                'nama' => $ohc->nama
            ]);

            $newQuotation->quotationOhcs()->create([
                'quotation_site_id' => $newSiteId, // ✅ Menggunakan quotation_site_id
                'barang_id' => $ohc->barang_id,
                'nama' => $ohc->nama,
                'jenis_barang_id' => $ohc->jenis_barang_id,
                'jenis_barang' => $ohc->jenis_barang,
                'jumlah' => $ohc->jumlah,
                'harga' => $ohc->harga,
                'created_by' => $newQuotation->created_by
            ]);
        }
    }

    /**
     * Duplicate barang data dengan mapping yang sudah ada
     * ✅ DIPERBARUI: Devices, Chemicals, OHC menggunakan quotation_site_id (barang general)
     * ✅ Kaporlap tetap menggunakan quotation_detail_id (spesifik per detail)
     */
    private function duplicateBarangDataWithMapping(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        \Log::info('Starting duplicateBarangDataWithMapping (UPDATED)', [
            'new_quotation_id' => $newQuotation->id,
            'detail_mapping_count' => count($this->detailIdMapping),
            'site_mapping_count' => count($this->siteIdMapping),
            'kaporlap_count' => $quotationReferensi->quotationKaporlaps->count(),
            'devices_count' => $quotationReferensi->quotationDevices->count(),
            'chemicals_count' => $quotationReferensi->quotationChemicals->count(),
            'ohcs_count' => $quotationReferensi->quotationOhcs->count()
        ]);

        // ✅ 1. Copy Kaporlap data (tetap menggunakan quotation_detail_id)
        foreach ($quotationReferensi->quotationKaporlaps as $kaporlap) {
            try {
                $newDetailId = $this->getMappedDetailIdWithMapping($newQuotation, $kaporlap->quotation_detail_id);

                \Log::info('Creating Kaporlap (detail-specific)', [
                    'old_detail_id' => $kaporlap->quotation_detail_id,
                    'new_detail_id' => $newDetailId,
                    'nama' => $kaporlap->nama,
                    'jumlah' => $kaporlap->jumlah
                ]);

                $newQuotation->quotationKaporlaps()->create([
                    'quotation_detail_id' => $newDetailId,
                    'barang_id' => $kaporlap->barang_id,
                    'nama' => $kaporlap->nama,
                    'jenis_barang_id' => $kaporlap->jenis_barang_id,
                    'jenis_barang' => $kaporlap->jenis_barang,
                    'jumlah' => $kaporlap->jumlah,
                    'harga' => $kaporlap->harga,
                    'created_by' => $newQuotation->created_by
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to create Kaporlap', [
                    'error' => $e->getMessage(),
                    'kaporlap_id' => $kaporlap->id
                ]);
            }
        }

        // ✅ 2. Copy Devices data (menggunakan quotation_site_id - barang general)
        foreach ($quotationReferensi->quotationDevices as $device) {
            try {
                // Dapatkan site_id lama dari referensi
                $originalSiteId = $this->getOriginalSiteIdFromDevice($device);
                $newSiteId = $this->getMappedSiteId($newQuotation, $originalSiteId);

                \Log::info('Creating Device (site-specific)', [
                    'original_site_id' => $originalSiteId,
                    'new_site_id' => $newSiteId,
                    'nama' => $device->nama,
                    'jumlah' => $device->jumlah
                ]);

                $newQuotation->quotationDevices()->create([
                    'quotation_site_id' => $newSiteId, // ✅ Menggunakan quotation_site_id
                    'barang_id' => $device->barang_id,
                    'nama' => $device->nama,
                    'jenis_barang_id' => $device->jenis_barang_id,
                    'jenis_barang' => $device->jenis_barang,
                    'jumlah' => $device->jumlah,
                    'harga' => $device->harga,
                    'created_by' => $newQuotation->created_by
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to create Device', [
                    'error' => $e->getMessage(),
                    'device_id' => $device->id
                ]);
            }
        }

        // ✅ 3. Copy Chemicals data (menggunakan quotation_site_id - barang general)
        foreach ($quotationReferensi->quotationChemicals as $chemical) {
            try {
                // Dapatkan site_id lama dari referensi
                $originalSiteId = $this->getOriginalSiteIdFromChemical($chemical);
                $newSiteId = $this->getMappedSiteId($newQuotation, $originalSiteId);

                \Log::info('Creating Chemical (site-specific)', [
                    'original_site_id' => $originalSiteId,
                    'new_site_id' => $newSiteId,
                    'nama' => $chemical->nama,
                    'masa_pakai' => $chemical->masa_pakai
                ]);

                $newQuotation->quotationChemicals()->create([
                    'quotation_site_id' => $newSiteId, // ✅ Menggunakan quotation_site_id
                    'barang_id' => $chemical->barang_id,
                    'nama' => $chemical->nama,
                    'jenis_barang_id' => $chemical->jenis_barang_id,
                    'jenis_barang' => $chemical->jenis_barang,
                    'jumlah' => $chemical->jumlah,
                    'harga' => $chemical->harga,
                    'masa_pakai' => $chemical->masa_pakai,
                    'created_by' => $newQuotation->created_by
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to create Chemical', [
                    'error' => $e->getMessage(),
                    'chemical_id' => $chemical->id
                ]);
            }
        }

        // ✅ 4. Copy OHC data (menggunakan quotation_site_id - barang general)
        foreach ($quotationReferensi->quotationOhcs as $ohc) {
            try {
                // Dapatkan site_id lama dari referensi
                $originalSiteId = $this->getOriginalSiteIdFromOhc($ohc);
                $newSiteId = $this->getMappedSiteId($newQuotation, $originalSiteId);

                \Log::info('Creating OHC (site-specific)', [
                    'original_site_id' => $originalSiteId,
                    'new_site_id' => $newSiteId,
                    'nama' => $ohc->nama
                ]);

                $newQuotation->quotationOhcs()->create([
                    'quotation_site_id' => $newSiteId, // ✅ Menggunakan quotation_site_id
                    'barang_id' => $ohc->barang_id,
                    'nama' => $ohc->nama,
                    'jenis_barang_id' => $ohc->jenis_barang_id,
                    'jenis_barang' => $ohc->jenis_barang,
                    'jumlah' => $ohc->jumlah,
                    'harga' => $ohc->harga,
                    'created_by' => $newQuotation->created_by
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to create OHC', [
                    'error' => $e->getMessage(),
                    'ohc_id' => $ohc->id
                ]);
            }
        }
    }

    /**
     * ✅ HELPER METHOD: Dapatkan original site_id dari Device
     */
    private function getOriginalSiteIdFromDevice($device): int
    {
        // Jika sudah ada quotation_site_id di data referensi, gunakan itu
        if ($device->quotation_site_id) {
            return $device->quotation_site_id;
        }

        // Jika tidak, coba dapatkan dari quotation_detail yang terkait
        if ($device->quotation_detail_id) {
            $detail = QuotationDetail::find($device->quotation_detail_id);
            if ($detail && $detail->quotation_site_id) {
                return $detail->quotation_site_id;
            }
        }

        throw new \Exception('Cannot determine original site_id for device: ' . $device->id);
    }

    /**
     * ✅ HELPER METHOD: Dapatkan original site_id dari Chemical
     */
    private function getOriginalSiteIdFromChemical($chemical): int
    {
        // Jika sudah ada quotation_site_id di data referensi, gunakan itu
        if ($chemical->quotation_site_id) {
            return $chemical->quotation_site_id;
        }

        // Jika tidak, coba dapatkan dari quotation_detail yang terkait
        if ($chemical->quotation_detail_id) {
            $detail = QuotationDetail::find($chemical->quotation_detail_id);
            if ($detail && $detail->quotation_site_id) {
                return $detail->quotation_site_id;
            }
        }

        throw new \Exception('Cannot determine original site_id for chemical: ' . $chemical->id);
    }

    /**
     * ✅ HELPER METHOD: Dapatkan original site_id dari OHC
     */
    private function getOriginalSiteIdFromOhc($ohc): int
    {
        // Jika sudah ada quotation_site_id di data referensi, gunakan itu
        if ($ohc->quotation_site_id) {
            return $ohc->quotation_site_id;
        }

        // Jika tidak, coba dapatkan dari quotation_detail yang terkait
        if ($ohc->quotation_detail_id) {
            $detail = QuotationDetail::find($ohc->quotation_detail_id);
            if ($detail && $detail->quotation_site_id) {
                return $detail->quotation_site_id;
            }
        }

        throw new \Exception('Cannot determine original site_id for OHC: ' . $ohc->id);
    }

    /**
     * Duplicate training data
     */
    private function duplicateTrainingData(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        foreach ($quotationReferensi->quotationTrainings as $training) {
            $newQuotation->quotationTrainings()->create([
                'training_id' => $training->training_id,
                'nama' => $training->nama,
                'created_by' => $newQuotation->created_by
            ]);
        }
    }

    /**
     * Duplicate kerjasama data
     */
    private function duplicateKerjasamaData(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        foreach ($quotationReferensi->quotationKerjasamas as $kerjasama) {
            $newQuotation->quotationKerjasamas()->create([
                'perjanjian' => $kerjasama->perjanjian,
                'created_by' => $newQuotation->created_by
            ]);
        }
    }

    /**
     * Duplicate PICS data
     */
    private function duplicatePicsData(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        foreach ($quotationReferensi->quotationPics as $pic) {
            $newQuotation->quotationPics()->create([
                'leads_id' => $newQuotation->leads_id,
                'nama' => $pic->nama,
                'jabatan_id' => $pic->jabatan_id,
                'jabatan' => $pic->jabatan,
                'no_telp' => $pic->no_telp,
                'email' => $pic->email,
                'is_kuasa' => $pic->is_kuasa,
                'created_by' => $newQuotation->created_by
            ]);
        }
    }

    private function getMappedSiteId(Quotation $newQuotation, $originalSiteId): int
    {
        // ✅ GUNAKAN MAPPING JIKA ADA
        if (isset($this->siteIdMapping[$originalSiteId])) {
            return $this->siteIdMapping[$originalSiteId];
        }

        // Fallback: reload sites dan coba logic lama
        $newQuotation->load('quotationSites');
        $sites = $newQuotation->quotationSites;

        \Log::info('Mapping site ID (fallback)', [
            'original_site_id' => $originalSiteId,
            'available_sites' => $sites->count(),
            'site_mapping_exists' => isset($this->siteIdMapping[$originalSiteId])
        ]);

        if ($sites->isEmpty()) {
            throw new \Exception('No sites found in new quotation. Sites must be created before details.');
        }

        // Jika hanya ada satu site di quotation baru, gunakan itu
        if ($sites->count() === 1) {
            return $sites->first()->id;
        }

        // Jika multi-site, coba match berdasarkan nama
        $originalSite = QuotationSite::find($originalSiteId);
        if ($originalSite) {
            // Cari site dengan nama yang sama di quotation baru
            $matchedSite = $sites->firstWhere('nama_site', $originalSite->nama_site);
            if ($matchedSite) {
                \Log::info('Site matched by name (fallback)', [
                    'original_name' => $originalSite->nama_site,
                    'new_id' => $matchedSite->id
                ]);
                return $matchedSite->id;
            }

            // Atau match berdasarkan urutan (index)
            $originalSites = $originalSite->quotation->quotationSites->sortBy('id')->values();
            $originalIndex = $originalSites->pluck('id')->search($originalSiteId);

            $newSitesSorted = $sites->sortBy('id')->values();
            if ($originalIndex !== false && isset($newSitesSorted[$originalIndex])) {
                \Log::info('Site matched by index (fallback)', [
                    'index' => $originalIndex,
                    'new_id' => $newSitesSorted[$originalIndex]->id
                ]);
                return $newSitesSorted[$originalIndex]->id;
            }
        }

        // Fallback: gunakan site pertama
        \Log::warning('Site mapping fallback to first site');
        return $sites->first()->id;
    }

    /**
     * Get mapped detail ID dengan matching logic yang lebih baik
     */
    private function getMappedDetailId(Quotation $newQuotation, $originalDetailId): int
    {
        $originalDetail = QuotationDetail::find($originalDetailId);

        if ($originalDetail) {
            // Gunakan 'where' untuk mengambil semua detail dengan position_id yang sama
            $matchedDetails = $newQuotation->quotationDetails
                ->where('position_id', $originalDetail->position_id);

            if ($matchedDetails->isNotEmpty()) {
                return $matchedDetails->first()->id;
            }
        }

        // Fallback jika tidak ada yang cocok: ambil ID detail pertama yang tersedia
        return $newQuotation->quotationDetails->first()->id;
    }

    /**
     * Get mapped detail ID menggunakan mapping yang sudah ada
     */
    private function getMappedDetailIdWithMapping(Quotation $newQuotation, $originalDetailId): int
    {
        \Log::debug('Getting mapped detail ID', [
            'original_detail_id' => $originalDetailId,
            'mapping_available' => isset($this->detailIdMapping[$originalDetailId]),
            'mapping_value' => $this->detailIdMapping[$originalDetailId] ?? null
        ]);

        // Jika sudah ada mapping dari proses sebelumnya, gunakan itu
        if (isset($this->detailIdMapping[$originalDetailId])) {
            $newDetailId = $this->detailIdMapping[$originalDetailId];

            // Verifikasi bahwa detail dengan ID ini ada
            $detailExists = $newQuotation->quotationDetails->contains('id', $newDetailId);

            if ($detailExists) {
                \Log::debug('Found in mapping', [
                    'original' => $originalDetailId,
                    'new' => $newDetailId
                ]);
                return $newDetailId;
            } else {
                \Log::warning('Mapped detail ID not found, falling back', [
                    'original' => $originalDetailId,
                    'mapped_new' => $newDetailId
                ]);
                unset($this->detailIdMapping[$originalDetailId]);
            }
        }

        // Fallback: gunakan logic berdasarkan position_id
        $newDetailId = $this->getMappedDetailId($newQuotation, $originalDetailId);

        \Log::debug('Using fallback logic for detail ID', [
            'original' => $originalDetailId,
            'new' => $newDetailId
        ]);

        return $newDetailId;
    }

    /**
     * ✅ Buat mapping antara site referensi dengan site baru
     */
    private function createSiteMapping(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        $newSites = $newQuotation->quotationSites()->orderBy('id')->get();
        $refSites = $quotationReferensi->quotationSites()->orderBy('id')->get();

        $unmatchedRefSites = collect();

        // STEP 1: Cari yang MATCH berdasarkan nama
        foreach ($refSites as $refSite) {
            $matchedSite = $newSites->firstWhere('nama_site', $refSite->nama_site);

            if ($matchedSite) {
                // ✅ ADA PASANGAN - Pakai site yang sudah ada
                $this->siteIdMapping[$refSite->id] = $matchedSite->id;

                \Log::info('Site MATCHED - using existing site', [
                    'ref_site_id' => $refSite->id,
                    'ref_site_name' => $refSite->nama_site,
                    'new_site_id' => $matchedSite->id,
                    'action' => 'USE_EXISTING'
                ]);
            } else {
                // ❌ TIDAK ADA PASANGAN - Tandai untuk dibuat
                $unmatchedRefSites->push($refSite);

                \Log::info('Site UNMATCHED - will create new', [
                    'ref_site_id' => $refSite->id,
                    'ref_site_name' => $refSite->nama_site,
                    'action' => 'CREATE_NEW'
                ]);
            }
        }

        // STEP 2: Buat site BARU untuk yang unmatched
        foreach ($unmatchedRefSites as $refSite) {
            // ✅ BUAT SITE BARU menggunakan data dari refSite
            // Tapi dengan UMK/UMP yang TERBARU
            $createdSite = $this->quotationBusinessService->createQuotationSiteFromReference(
                $newQuotation,
                $refSite,  // ← Pakai data dari ref (nama, provinsi, kota)
                $newQuotation->created_by
            );

            // Mapping ref site ke site yang baru dibuat
            $this->siteIdMapping[$refSite->id] = $createdSite->id;

            // Tambahkan ke collection (biar kalau ada ref site lain dengan nama sama, bisa matched)
            $newSites->push($createdSite);

            \Log::info('Created NEW site from reference', [
                'ref_site_id' => $refSite->id,
                'ref_site_name' => $refSite->nama_site,
                'new_site_id' => $createdSite->id,
                'new_umk' => $createdSite->umk,  // ← UMK TERBARU
                'ref_umk' => $refSite->umk,      // ← UMK LAMA (untuk perbandingan)
                'action' => 'CREATED_NEW'
            ]);
        }
    }

    /**
     * ✅ Duplicate quotation details dengan mapping site yang tepat
     */
    private function duplicateQuotationDetailsWithSiteMapping(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        Log::info('Duplicating quotation details for ALL NEW sites', [
            'new_quotation_id' => $newQuotation->id,
            'referensi_quotation_id' => $quotationReferensi->id,
            'detail_count' => $quotationReferensi->quotationDetails->count(),
            'new_site_count' => $newQuotation->quotationSites->count()
        ]);

        // ✅ AMBIL SEMUA SITE DARI QUOTATION BARU
        $newSites = $newQuotation->quotationSites()->get();

        if ($newSites->isEmpty()) {
            throw new \Exception('No site found in new quotation. Sites must be created before details.');
        }

        // Untuk setiap detail dari referensi, buat salinan untuk SETIAP site baru
        foreach ($quotationReferensi->quotationDetails as $detailReferensi) {
            foreach ($newSites as $newSite) {
                // Create new detail linked to each NEW site
                $newDetail = $newQuotation->quotationDetails()->create([
                    'quotation_site_id' => $newSite->id,
                    'position_id' => $detailReferensi->position_id,
                    'jabatan_kebutuhan' => $detailReferensi->jabatan_kebutuhan,
                    'nama_site' => $newSite->nama_site,
                    'jumlah_hc' => $detailReferensi->jumlah_hc,
                    'nominal_upah' => $detailReferensi->nominal_upah,
                    'penjamin_kesehatan' => $detailReferensi->penjamin_kesehatan,
                    'is_bpjs_jkk' => $detailReferensi->is_bpjs_jkk,
                    'is_bpjs_jkm' => $detailReferensi->is_bpjs_jkm,
                    'is_bpjs_jht' => $detailReferensi->is_bpjs_jht,
                    'is_bpjs_jp' => $detailReferensi->is_bpjs_jp,
                    'nominal_takaful' => $detailReferensi->nominal_takaful,
                    'biaya_monitoring_kontrol' => $detailReferensi->biaya_monitoring_kontrol,
                    'created_by' => $newQuotation->created_by
                ]);

                // Simpan mapping detail_id lama -> baru
                $this->detailIdMapping[$detailReferensi->id . '_site_' . $newSite->id] = $newDetail->id;

                \Log::info('Created detail for new site', [
                    'old_detail_id' => $detailReferensi->id,
                    'new_detail_id' => $newDetail->id,
                    'new_site_id' => $newSite->id,
                    'new_site_name' => $newSite->nama_site
                ]);

                // ✅ COPY WAGE DATA
                if ($detailReferensi->relationLoaded('wage') && $detailReferensi->wage) {
                    $newDetail->wage()->create([
                        'quotation_id' => $newQuotation->id,
                        'upah' => $detailReferensi->wage->upah,
                        'hitungan_upah' => $detailReferensi->wage->hitungan_upah,
                        'lembur' => $detailReferensi->wage->lembur,
                        'nominal_lembur' => $detailReferensi->wage->nominal_lembur,
                        'jenis_bayar_lembur' => $detailReferensi->wage->jenis_bayar_lembur,
                        'jam_per_bulan_lembur' => $detailReferensi->wage->jam_per_bulan_lembur,
                        'lembur_ditagihkan' => $detailReferensi->wage->lembur_ditagihkan,
                        'kompensasi' => $detailReferensi->wage->kompensasi,
                        'thr' => $detailReferensi->wage->thr,
                        'tunjangan_holiday' => $detailReferensi->wage->tunjangan_holiday,
                        'nominal_tunjangan_holiday' => $detailReferensi->wage->nominal_tunjangan_holiday,
                        'jenis_bayar_tunjangan_holiday' => $detailReferensi->wage->jenis_bayar_tunjangan_holiday,
                        'created_by' => $newQuotation->created_by
                    ]);
                }

                // Copy tunjangan
                foreach ($detailReferensi->quotationDetailTunjangans as $tunjangan) {
                    $newDetail->quotationDetailTunjangans()->create([
                        'quotation_id' => $newQuotation->id,
                        'nama_tunjangan' => $tunjangan->nama_tunjangan,
                        'nominal' => $tunjangan->nominal,
                        'nominal_coss' => $tunjangan->nominal_coss,
                        'created_by' => $newQuotation->created_by
                    ]);
                }

                // Copy HPP data
                if ($detailReferensi->quotationDetailHpp) {
                    $hpp = $detailReferensi->quotationDetailHpp;
                    $newDetail->quotationDetailHpp()->create([
                        'quotation_id' => $newQuotation->id,
                        'jumlah_hc' => $hpp->jumlah_hc,
                        'gaji_pokok' => $hpp->gaji_pokok,
                        'tunjangan_hari_raya' => $hpp->tunjangan_hari_raya,
                        'kompensasi' => $hpp->kompensasi,
                        'tunjangan_hari_libur_nasional' => $hpp->tunjangan_hari_libur_nasional,
                        'lembur' => $hpp->lembur,
                        'bpjs_jkk' => $hpp->bpjs_jkk,
                        'bpjs_jkm' => $hpp->bpjs_jkm,
                        'bpjs_jht' => $hpp->bpjs_jht,
                        'bpjs_jp' => $hpp->bpjs_jp,
                        'bpjs_ks' => $hpp->bpjs_ks,
                        'takaful' => $hpp->takaful,
                        'provisi_seragam' => $hpp->provisi_seragam,
                        'provisi_peralatan' => $hpp->provisi_peralatan,
                        'provisi_chemical' => $hpp->provisi_chemical,
                        'provisi_ohc' => $hpp->provisi_ohc,
                        'bunga_bank' => $hpp->bunga_bank,
                        'insentif' => $hpp->insentif,
                        'total_hpp' => $hpp->total_hpp,
                        'created_by' => $newQuotation->created_by
                    ]);
                }

                // Copy COSS data
                if ($detailReferensi->quotationDetailCoss) {
                    $coss = $detailReferensi->quotationDetailCoss;
                    $newDetail->quotationDetailCoss()->create([
                        'quotation_id' => $newQuotation->id,
                        'jumlah_hc' => $coss->jumlah_hc,
                        'gaji_pokok' => $coss->gaji_pokok,
                        'tunjangan_hari_raya' => $coss->tunjangan_hari_raya,
                        'kompensasi' => $coss->kompensasi,
                        'tunjangan_hari_libur_nasional' => $coss->tunjangan_hari_libur_nasional,
                        'lembur' => $coss->lembur,
                        'bpjs_jkk' => $coss->bpjs_jkk,
                        'bpjs_jkm' => $coss->bpjs_jkm,
                        'bpjs_jht' => $coss->bpjs_jht,
                        'bpjs_jp' => $coss->bpjs_jp,
                        'bpjs_ks' => $coss->bpjs_ks,
                        'takaful' => $coss->takaful,
                        'provisi_seragam' => $coss->provisi_seragam,
                        'provisi_peralatan' => $coss->provisi_peralatan,
                        'provisi_chemical' => $coss->provisi_chemical,
                        'provisi_ohc' => $coss->provisi_ohc,
                        'bunga_bank' => $coss->bunga_bank,
                        'insentif' => $coss->insentif,
                        'management_fee' => $coss->management_fee,
                        'ppn' => $coss->ppn,
                        'pph' => $coss->pph,
                        'total_coss' => $coss->total_coss,
                        'created_by' => $newQuotation->created_by
                    ]);
                }

                // Copy requirements
                foreach ($detailReferensi->quotationDetailRequirements as $requirement) {
                    $newDetail->quotationDetailRequirements()->create([
                        'quotation_id' => $newQuotation->id,
                        'requirement' => $requirement->requirement,
                        'created_by' => $newQuotation->created_by
                    ]);
                }
            }
        }
    }
    /**
     * Check if site already exists for the leads
     */
    public function isSiteExisting(int $leadsId, array $siteData): bool
    {
        return QuotationSite::where('leads_id', $leadsId)
            ->where('nama_site', $siteData['nama_site'])
            ->where('provinsi_id', $siteData['provinsi_id'])
            ->where('kota_id', $siteData['kota_id'])
            ->exists();
    }

    /**
     * Get existing sites for leads
     */
    public function getExistingSites(int $leadsId, array $siteNames = []): array
    {
        $query = QuotationSite::where('leads_id', $leadsId);

        if (!empty($siteNames)) {
            $query->whereIn('nama_site', $siteNames);
        }

        return $query->get()->toArray();
    }
}