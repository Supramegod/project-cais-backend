<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\QuotationSite;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class QuotationDuplicationService
{
    public function duplicateQuotationData(Quotation $newQuotation, Quotation $referensiQuotation): void
    {
        DB::beginTransaction();
        try {
            \Log::info('Starting duplication', [
                'new_id' => $newQuotation->id,
                'ref_id' => $referensiQuotation->id
            ]);

            // ✅ 1. COPY BASIC QUOTATION DATA FIRST
            $this->duplicateBasicQuotationData($newQuotation, $referensiQuotation);

            // ✅ 2. COPY SITES FIRST (sebelum details)
            $this->duplicateSites($newQuotation, $referensiQuotation);

            // ✅ 3. COPY QUOTATION DETAILS & RELATED DATA
            $this->duplicateQuotationDetails($newQuotation, $referensiQuotation);

            // ✅ 4. COPY APPLIKASI PENDUKUNG
            $this->duplicateAplikasiPendukung($newQuotation, $referensiQuotation);

            // ✅ 5. COPY BARANG DATA (Kaporlap, Devices, Chemicals, OHC)
            $this->duplicateBarangData($newQuotation, $referensiQuotation);

            // ✅ 6. COPY TRAINING DATA
            $this->duplicateTrainingData($newQuotation, $referensiQuotation);

            // ✅ 7. COPY KERJASAMA DATA
            $this->duplicateKerjasamaData($newQuotation, $referensiQuotation);

            // ✅ 8. COPY PICS DATA
            $this->duplicatePicsData($newQuotation, $referensiQuotation);

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
     * ✅ TAMBAHKAN METHOD BARU UNTUK DUPLICATE SITES
     */
    private function duplicateSites(Quotation $newQuotation, Quotation $referensiQuotation): void
    {
        \Log::info('Duplicating sites', [
            'sites_count' => $referensiQuotation->quotationSites->count()
        ]);

        foreach ($referensiQuotation->quotationSites as $siteReferensi) {
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
    private function duplicateBasicQuotationData(Quotation $newQuotation, Quotation $referensiQuotation): void
    {
        $newQuotation->update([
            // Contract details
            'jenis_kontrak' => $referensiQuotation->jenis_kontrak,
            'mulai_kontrak' => $referensiQuotation->mulai_kontrak,
            'kontrak_selesai' => $referensiQuotation->kontrak_selesai,
            'tgl_penempatan' => $referensiQuotation->tgl_penempatan? Carbon::parse($referensiQuotation->tgl_penempatan)->isoFormat('Y-MM-DD') : null,

            // Payment & Salary details
            'salary_rule_id' => $referensiQuotation->salary_rule_id,
            'top' => $referensiQuotation->top,
            'jumlah_hari_invoice' => $referensiQuotation->jumlah_hari_invoice,
            'tipe_hari_invoice' => $referensiQuotation->tipe_hari_invoice,
            'upah' => $referensiQuotation->upah,
            'nominal_upah' => $referensiQuotation->nominal_upah,
            'hitungan_upah' => $referensiQuotation->hitungan_upah,

            // Management fee
            'management_fee_id' => $referensiQuotation->management_fee_id,
            'persentase' => $referensiQuotation->persentase,

            // Allowances
            'thr' => $referensiQuotation->thr,
            'kompensasi' => $referensiQuotation->kompensasi,
            'lembur' => $referensiQuotation->lembur,
            'nominal_lembur' => $referensiQuotation->nominal_lembur,
            'jenis_bayar_lembur' => $referensiQuotation->jenis_bayar_lembur,
            'lembur_ditagihkan' => $referensiQuotation->lembur_ditagihkan,
            'jam_per_bulan_lembur' => $referensiQuotation->jam_per_bulan_lembur,
            'tunjangan_holiday' => $referensiQuotation->tunjangan_holiday,
            'nominal_tunjangan_holiday' => $referensiQuotation->nominal_tunjangan_holiday,
            'jenis_bayar_tunjangan_holiday' => $referensiQuotation->jenis_bayar_tunjangan_holiday,

            // Tax
            'is_ppn' => $referensiQuotation->is_ppn,
            'ppn_pph_dipotong' => $referensiQuotation->ppn_pph_dipotong,

            // Leave
            'cuti' => $referensiQuotation->cuti,
            'hari_cuti_kematian' => $referensiQuotation->hari_cuti_kematian,
            'hari_istri_melahirkan' => $referensiQuotation->hari_istri_melahirkan,
            'hari_cuti_menikah' => $referensiQuotation->hari_cuti_menikah,
            'gaji_saat_cuti' => $referensiQuotation->gaji_saat_cuti,
            'prorate' => $referensiQuotation->prorate,

            // Work details
            'shift_kerja' => $referensiQuotation->shift_kerja,
            'hari_kerja' => $referensiQuotation->hari_kerja,
            'jam_kerja' => $referensiQuotation->jam_kerja,
            'evaluasi_kontrak' => $referensiQuotation->evaluasi_kontrak,
            'durasi_kerjasama' => $referensiQuotation->durasi_kerjasama,
            'durasi_karyawan' => $referensiQuotation->durasi_karyawan,
            'evaluasi_karyawan' => $referensiQuotation->evaluasi_karyawan,

            // Company details
            'jenis_perusahaan_id' => $referensiQuotation->jenis_perusahaan_id,
            'jenis_perusahaan' => $referensiQuotation->jenis_perusahaan,
            'bidang_perusahaan_id' => $referensiQuotation->bidang_perusahaan_id,
            'bidang_perusahaan' => $referensiQuotation->bidang_perusahaan,
            'resiko' => $referensiQuotation->resiko,

            // Visit & Training
            'kunjungan_operasional' => $referensiQuotation->kunjungan_operasional,
            'kunjungan_tim_crm' => $referensiQuotation->kunjungan_tim_crm,
            'keterangan_kunjungan_operasional' => $referensiQuotation->keterangan_kunjungan_operasional,
            'keterangan_kunjungan_tim_crm' => $referensiQuotation->keterangan_kunjungan_tim_crm,
            'training' => $referensiQuotation->training,

            // Financial
            'persen_bunga_bank' => $referensiQuotation->persen_bunga_bank,
            'persen_insentif' => $referensiQuotation->persen_insentif,
            'penagihan' => $referensiQuotation->penagihan,
            'note_harga_jual' => $referensiQuotation->note_harga_jual,

            // Status (kecuali approval status yang harus reset)
            'is_aktif' => 1,
            'revisi' => 0,
            'alasan_revisi' => null,
            'step' => $referensiQuotation->step,
        ]);
    }

    /**
     * Duplicate quotation details and related data
     */
    private function duplicateQuotationDetails(Quotation $newQuotation, Quotation $referensiQuotation): void
    {
        foreach ($referensiQuotation->quotationDetails as $detailReferensi) {
            // Create new detail
            $newDetail = $newQuotation->quotationDetails()->create([
                'quotation_site_id' => $this->getMappedSiteId($newQuotation, $detailReferensi->quotation_site_id),
                'position_id' => $detailReferensi->position_id,
                'position' => $detailReferensi->position,
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

            // Copy tunjangan
            foreach ($detailReferensi->quotationDetailTunjangans as $tunjangan) {
                $newDetail->quotationDetailTunjangans()->create([
                    'nama_tunjangan' => $tunjangan->nama_tunjangan,
                    'nominal' => $tunjangan->nominal,
                    'created_by' => $newQuotation->created_by
                ]);
            }

            // Copy HPP data
            if ($detailReferensi->quotationDetailHpp) {
                $hpp = $detailReferensi->quotationDetailHpp;
                $newDetail->quotationDetailHpp()->create([
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
                    'created_by' => $newQuotation->created_by
                ]);
            }

            // Copy COSS data
            if ($detailReferensi->quotationDetailCoss) {
                $coss = $detailReferensi->quotationDetailCoss;
                $newDetail->quotationDetailCoss()->create([
                    'provisi_seragam' => $coss->provisi_seragam,
                    'provisi_peralatan' => $coss->provisi_peralatan,
                    'provisi_chemical' => $coss->provisi_chemical,
                    'provisi_ohc' => $coss->provisi_ohc,
                    'management_fee' => $coss->management_fee,
                    'ppn' => $coss->ppn,
                    'pph' => $coss->pph,
                    'created_by' => $newQuotation->created_by
                ]);
            }

            // Copy requirements
            foreach ($detailReferensi->quotationDetailRequirements as $requirement) {
                $newDetail->quotationDetailRequirements()->create([
                    'requirement' => $requirement->requirement,
                    'created_by' => $newQuotation->created_by
                ]);
            }
        }
    }

    /**
     * Duplicate aplikasi pendukung data
     */
    private function duplicateAplikasiPendukung(Quotation $newQuotation, Quotation $referensiQuotation): void
    {
        foreach ($referensiQuotation->quotationAplikasis as $aplikasi) {
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
    private function duplicateBarangData(Quotation $newQuotation, Quotation $referensiQuotation): void
    {
        // Copy Kaporlap data
        foreach ($referensiQuotation->quotationKaporlaps as $kaporlap) {
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
        foreach ($referensiQuotation->quotationDevices as $device) {
            $newQuotation->quotationDevices()->create([
                'barang_id' => $device->barang_id,
                'nama' => $device->nama,
                'jenis_barang_id' => $device->jenis_barang_id,
                'jenis_barang' => $device->jenis_barang,
                'jumlah' => $device->jumlah,
                'harga' => $device->harga,
                'created_by' => $newQuotation->created_by
            ]);
        }

        // Copy Chemicals data
        foreach ($referensiQuotation->quotationChemicals as $chemical) {
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
        foreach ($referensiQuotation->quotationOhcs as $ohc) {
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
     * Duplicate training data
     */
    private function duplicateTrainingData(Quotation $newQuotation, Quotation $referensiQuotation): void
    {
        foreach ($referensiQuotation->quotationTrainings as $training) {
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
    private function duplicateKerjasamaData(Quotation $newQuotation, Quotation $referensiQuotation): void
    {
        foreach ($referensiQuotation->quotationKerjasamas as $kerjasama) {
            $newQuotation->quotationKerjasamas()->create([
                'perjanjian' => $kerjasama->perjanjian,
                'created_by' => $newQuotation->created_by
            ]);
        }
    }

    /**
     * Duplicate PICS data
     */
    private function duplicatePicsData(Quotation $newQuotation, Quotation $referensiQuotation): void
    {
        foreach ($referensiQuotation->quotationPics as $pic) {
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
}