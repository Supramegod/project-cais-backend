<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\QuotationSite;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class QuotationDuplicationService
{
    /**
     * Mapping untuk detail_id dari referensi ke quotation baru
     */
    private $detailIdMapping = [];

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

            // 1. COPY BASIC QUOTATION DATA FIRST
            $this->duplicateBasicQuotationData($newQuotation, $quotationReferensi);

            // 2. COPY SITES FIRST (sebelum details)
            $this->duplicateSites($newQuotation, $quotationReferensi);

            // 3. COPY QUOTATION DETAILS & RELATED DATA
            $this->duplicateQuotationDetails($newQuotation, $quotationReferensi);

            // 4. COPY APPLIKASI PENDUKUNG
            $this->duplicateAplikasiPendukung($newQuotation, $quotationReferensi);

            // 5. COPY BARANG DATA (Kaporlap, Devices, Chemicals, OHC)
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
                'ref_id' => $quotationReferensi->id
            ]);

            // Reset mapping
            $this->detailIdMapping = [];

            // ✅ 1. COPY BASIC QUOTATION DATA
            $this->duplicateBasicQuotationData($newQuotation, $quotationReferensi);

            // ✅ 2. COPY QUOTATION DETAILS & RELATED DATA (dengan mapping ke site baru)
            $this->duplicateQuotationDetailsForNewSite($newQuotation, $quotationReferensi);

            // ✅ 3. COPY APPLIKASI PENDUKUNG
            $this->duplicateAplikasiPendukung($newQuotation, $quotationReferensi);

            // ✅ 4. COPY BARANG DATA dengan mapping yang baru
            $this->duplicateBarangDataWithMapping($newQuotation, $quotationReferensi);

            // ✅ 5. COPY TRAINING DATA
            $this->duplicateTrainingData($newQuotation, $quotationReferensi);

            // ✅ 6. COPY KERJASAMA DATA
            $this->duplicateKerjasamaData($newQuotation, $quotationReferensi);

            // ✅ 7. COPY PICS DATA
            $this->duplicatePicsData($newQuotation, $quotationReferensi);

            DB::commit();

            \Log::info('Duplication WITHOUT sites completed successfully');

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

            \Log::info('Site duplicated', [
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
                    'created_by' => $newQuotation->created_by
                ]);
            }

            // Copy HPP data
            if ($detailReferensi->quotationDetailHpp) {
                $hpp = $detailReferensi->quotationDetailHpp;
                $newDetail->quotationDetailHpp()->create([
                    'quotation_id' => $newQuotation->id,
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

    /**
     * ✅ Duplicate quotation details untuk site baru
     * Digunakan ketika quotation baru sudah memiliki site yang dibuat dari request
     */
    private function duplicateQuotationDetailsForNewSite(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        \Log::info('Duplicating quotation details for NEW site', [
            'new_quotation_id' => $newQuotation->id,
            'referensi_quotation_id' => $quotationReferensi->id
        ]);

        // ✅ AMBIL SITE PERTAMA DARI QUOTATION BARU (yang sudah dibuat dari request)
        $newSite = $newQuotation->quotationSites()->first();
        
        if (!$newSite) {
            throw new \Exception('No site found in new quotation. Sites must be created before details.');
        }

        \Log::info('Using new site for all details', [
            'site_id' => $newSite->id,
            'site_name' => $newSite->nama_site
        ]);

        foreach ($quotationReferensi->quotationDetails as $detailReferensi) {
            // Create new detail linked to the NEW site
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
            $this->detailIdMapping[$detailReferensi->id] = $newDetail->id;

            \Log::info('Created detail with new site mapping', [
                'old_detail_id' => $detailReferensi->id,
                'new_detail_id' => $newDetail->id,
                'old_site_id' => $detailReferensi->quotation_site_id,
                'new_site_id' => $newSite->id
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
                    'created_by' => $newQuotation->created_by
                ]);
            }

            // Copy HPP data
            if ($detailReferensi->quotationDetailHpp) {
                $hpp = $detailReferensi->quotationDetailHpp;
                $newDetail->quotationDetailHpp()->create([
                    'quotation_id' => $newQuotation->id,
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
     */
    private function duplicateBarangData(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        // Copy Kaporlap data
        foreach ($quotationReferensi->quotationKaporlaps as $kaporlap) {
            $newDetailId = $this->getMappedDetailId($newQuotation, $kaporlap->quotation_detail_id);

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

        // Copy Devices data
        foreach ($quotationReferensi->quotationDevices as $device) {
            $newDetailId = $this->getMappedDetailId($newQuotation, $device->quotation_detail_id);

            $newQuotation->quotationDevices()->create([
                'barang_id' => $device->barang_id,
                'quotation_detail_id' => $newDetailId,
                'nama' => $device->nama,
                'jenis_barang_id' => $device->jenis_barang_id,
                'jenis_barang' => $device->jenis_barang,
                'jumlah' => $device->jumlah,
                'harga' => $device->harga,
                'created_by' => $newQuotation->created_by
            ]);
        }

        // Copy Chemicals data
        foreach ($quotationReferensi->quotationChemicals as $chemical) {
            $newDetailId = $this->getMappedDetailId($newQuotation, $chemical->quotation_detail_id);

            $newQuotation->quotationChemicals()->create([
                'quotation_detail_id' => $newDetailId,
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

        // Copy OHC data
        foreach ($quotationReferensi->quotationOhcs as $ohc) {
            $newDetailId = $this->getMappedDetailId($newQuotation, $ohc->quotation_detail_id);

            $newQuotation->quotationOhcs()->create([
                'quotation_detail_id' => $newDetailId,
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
     */
    private function duplicateBarangDataWithMapping(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        // Copy Kaporlap data
        foreach ($quotationReferensi->quotationKaporlaps as $kaporlap) {
            $newDetailId = $this->getMappedDetailIdWithMapping($newQuotation, $kaporlap->quotation_detail_id);

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

        // Copy Devices data
        foreach ($quotationReferensi->quotationDevices as $device) {
            $newDetailId = $this->getMappedDetailIdWithMapping($newQuotation, $device->quotation_detail_id);

            $newQuotation->quotationDevices()->create([
                'barang_id' => $device->barang_id,
                'quotation_detail_id' => $newDetailId,
                'nama' => $device->nama,
                'jenis_barang_id' => $device->jenis_barang_id,
                'jenis_barang' => $device->jenis_barang,
                'jumlah' => $device->jumlah,
                'harga' => $device->harga,
                'created_by' => $newQuotation->created_by
            ]);
        }

        // Copy Chemicals data
        foreach ($quotationReferensi->quotationChemicals as $chemical) {
            $newDetailId = $this->getMappedDetailIdWithMapping($newQuotation, $chemical->quotation_detail_id);

            $newQuotation->quotationChemicals()->create([
                'quotation_detail_id' => $newDetailId,
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

        // Copy OHC data
        foreach ($quotationReferensi->quotationOhcs as $ohc) {
            $newDetailId = $this->getMappedDetailIdWithMapping($newQuotation, $ohc->quotation_detail_id);

            $newQuotation->quotationOhcs()->create([
                'quotation_detail_id' => $newDetailId,
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
        // ✅ RELOAD SITES UNTUK MEMASTIKAN DATA TERBARU
        $newQuotation->load('quotationSites');
        $sites = $newQuotation->quotationSites;

        \Log::info('Mapping site ID', [
            'original_site_id' => $originalSiteId,
            'available_sites' => $sites->count()
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
                \Log::info('Site matched by name', [
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
                \Log::info('Site matched by index', [
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
            // Cari detail dengan position_id yang sama
            $matchedDetail = $newQuotation->quotationDetails->firstWhere('position_id', $originalDetail->position_id);
            if ($matchedDetail) {
                return $matchedDetail->id;
            }
        }

        // Fallback: gunakan detail pertama
        $details = $newQuotation->quotationDetails;
        if ($details->count() > 0) {
            return $details->first()->id;
        }

        throw new \Exception('No quotation details found in new quotation');
    }

    /**
     * Get mapped detail ID menggunakan mapping yang sudah ada
     */
    private function getMappedDetailIdWithMapping(Quotation $newQuotation, $originalDetailId): int
    {
        // Jika sudah ada mapping dari proses sebelumnya, gunakan itu
        if (isset($this->detailIdMapping[$originalDetailId])) {
            return $this->detailIdMapping[$originalDetailId];
        }

        // Fallback: gunakan logic biasa
        return $this->getMappedDetailId($newQuotation, $originalDetailId);
    }
}