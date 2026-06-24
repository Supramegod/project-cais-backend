<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\QuotationSite;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class QuotationDuplicationService
{
    private $quotationBusinessService;

    public function __construct(QuotationBusinessService $quotationBusinessService)
    {
        $this->quotationBusinessService = $quotationBusinessService;
    }

    /** Mapping detail_id referensi → detail_id baru */
    private $detailIdMapping = [];

    /** Mapping site_id referensi → site_id baru */
    private $siteIdMapping = [];

    // =========================================================================
    // PUBLIC ENTRY POINTS
    // =========================================================================

    /**
     * Duplicate SEMUA quotation data termasuk sites (dipakai untuk tipe baru/full-copy).
     */
    public function duplicateQuotationData(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        DB::beginTransaction();
        try {
            $this->resetMappings();
            $quotationReferensi = $this->eagerLoadReferensi($quotationReferensi);

            $this->duplicateBasicQuotationData($newQuotation, $quotationReferensi);
            $this->duplicateSites($newQuotation, $quotationReferensi);
            $this->duplicateQuotationDetails($newQuotation, $quotationReferensi);
            $this->duplicateAplikasiPendukung($newQuotation, $quotationReferensi);
            $this->duplicateBarangData($newQuotation, $quotationReferensi);
            $this->duplicateTrainingData($newQuotation, $quotationReferensi);
            $this->duplicateKerjasamaData($newQuotation, $quotationReferensi);
            $this->duplicatePicsData($newQuotation, $quotationReferensi);

            DB::commit();
            \Log::info('duplicateQuotationData completed', ['new_id' => $newQuotation->id]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('duplicateQuotationData failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * Duplicate data TANPA sites (site sudah dibuat di luar; tidak ada site yang cocok by nama).
     * Semua detail referensi di-copy ke semua site baru.
     */
    public function duplicateQuotationWithoutSites(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        DB::beginTransaction();
        try {
            $this->resetMappings();
            $quotationReferensi = $this->eagerLoadReferensi($quotationReferensi);

            $this->duplicateBasicQuotationData($newQuotation, $quotationReferensi);
            $this->duplicateQuotationDetailsForNewSite($newQuotation, $quotationReferensi);
            $this->duplicateAplikasiPendukung($newQuotation, $quotationReferensi);
            $this->duplicateBarangDataWithMapping($newQuotation, $quotationReferensi);
            $this->duplicateTrainingData($newQuotation, $quotationReferensi);
            $this->duplicateKerjasamaData($newQuotation, $quotationReferensi);
            $this->duplicatePicsData($newQuotation, $quotationReferensi);

            DB::commit();
            \Log::info('duplicateQuotationWithoutSites completed', ['new_id' => $newQuotation->id]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('duplicateQuotationWithoutSites failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * Duplicate dengan site matching by nama (dipakai untuk revisi, rekontrak, adendum).
     * Site yang namanya tidak ada di quotation baru (dihapus) → detail & barang-nya di-skip.
     */
    public function duplicateQuotationWithSiteMapping(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        DB::beginTransaction();
        try {
            $this->resetMappings();
            $quotationReferensi = $this->eagerLoadReferensi($quotationReferensi);

            $this->duplicateBasicQuotationData($newQuotation, $quotationReferensi);
            $this->createSiteMapping($newQuotation, $quotationReferensi);
            $this->duplicateQuotationDetailsWithSiteMapping($newQuotation, $quotationReferensi);
            $this->duplicateAplikasiPendukung($newQuotation, $quotationReferensi);
            $this->duplicateBarangDataWithMapping($newQuotation, $quotationReferensi);
            $this->duplicateTrainingData($newQuotation, $quotationReferensi);
            $this->duplicateKerjasamaData($newQuotation, $quotationReferensi);
            $this->duplicatePicsData($newQuotation, $quotationReferensi);

            DB::commit();
            \Log::info('duplicateQuotationWithSiteMapping completed', ['new_id' => $newQuotation->id]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('duplicateQuotationWithSiteMapping failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    // =========================================================================
    // PRIVATE — SETUP HELPERS
    // =========================================================================

    private function resetMappings(): void
    {
        $this->detailIdMapping = [];
        $this->siteIdMapping = [];
    }

    /**
     * Eager-load semua relasi yang dibutuhkan dari quotation referensi agar tidak N+1.
     */
    private function eagerLoadReferensi(Quotation $quotationReferensi): Quotation
    {
        return $quotationReferensi->load([
            'quotationSites',
            'quotationDetails.wage',
            'quotationDetails.quotationDetailTunjangans',
            'quotationDetails.quotationDetailHpps',
            'quotationDetails.quotationDetailCosses',
            'quotationDetails.quotationDetailRequirements',
            'quotationAplikasis',
            'quotationKaporlaps',
            'quotationDevices',
            'quotationChemicals',
            'quotationOhcs',
            'quotationTrainings',
            'quotationKerjasamas',
            'quotationPics',
        ]);
    }

    // =========================================================================
    // PRIVATE — SITE MAPPING & DUPLICATION
    // =========================================================================

    /**
     * Buat mapping: site_id referensi → site_id baru, dicocokkan by nama_site.
     * Site referensi yang tidak punya pasangan (misal sudah dihapus di revisi) → TIDAK masuk mapping.
     */
    private function createSiteMapping(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        $newSites = $newQuotation->quotationSites()->orderBy('id')->get();

        foreach ($quotationReferensi->quotationSites as $refSite) {
            $matched = $newSites->firstWhere('nama_site', $refSite->nama_site);

            if ($matched) {
                $this->siteIdMapping[$refSite->id] = $matched->id;
                \Log::info('Site mapped', ['ref' => $refSite->id, 'new' => $matched->id, 'name' => $refSite->nama_site]);
            } else {
                \Log::info('Site unmatched (deleted in revisi), barang-nya akan di-skip', ['ref_site_name' => $refSite->nama_site]);
            }
        }
    }

    /** Copy semua site dari referensi ke quotation baru + bangun siteIdMapping. */
    private function duplicateSites(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        foreach ($quotationReferensi->quotationSites as $siteRef) {
            $newSite = $newQuotation->quotationSites()->create([
                'leads_id' => $newQuotation->leads_id,
                'nama_site' => $siteRef->nama_site,
                'provinsi_id' => $siteRef->provinsi_id,
                'provinsi' => $siteRef->provinsi,
                'kota_id' => $siteRef->kota_id,
                'kota' => $siteRef->kota,
                'ump' => $siteRef->ump,
                'umk' => $siteRef->umk,
                'umsk' => $siteRef->umsk,
                'nominal_upah' => $siteRef->nominal_upah,
                'penempatan' => $siteRef->penempatan,
                'created_by' => $newQuotation->created_by,
                'created_by_user_id' => $newQuotation->created_by_user_id,
            ]);

            $this->siteIdMapping[$siteRef->id] = $newSite->id;
        }
    }

    // =========================================================================
    // PRIVATE — BASIC DATA
    // =========================================================================

    private function duplicateBasicQuotationData(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        $newQuotation->update([
            'jenis_kontrak' => $quotationReferensi->jenis_kontrak,
            'mulai_kontrak' => $quotationReferensi->mulai_kontrak,
            'kontrak_selesai' => $quotationReferensi->kontrak_selesai,
            'tgl_penempatan' => $quotationReferensi->tgl_penempatan
                ? Carbon::parse($quotationReferensi->tgl_penempatan)->toDateString()
                : null,
            'salary_rule_id' => $quotationReferensi->salary_rule_id,
            'top' => $quotationReferensi->top,
            'jumlah_hari_invoice' => $quotationReferensi->jumlah_hari_invoice,
            'tipe_hari_invoice' => $quotationReferensi->tipe_hari_invoice,
            'upah' => $quotationReferensi->upah,
            'nominal_upah' => $quotationReferensi->nominal_upah,
            'hitungan_upah' => $quotationReferensi->hitungan_upah,
            'management_fee_id' => $quotationReferensi->management_fee_id,
            'persentase' => $quotationReferensi->persentase,
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
            'is_ppn' => $quotationReferensi->is_ppn,
            'ppn_pph_dipotong' => $quotationReferensi->ppn_pph_dipotong,
            'cuti' => $quotationReferensi->cuti,
            'hari_cuti_kematian' => $quotationReferensi->hari_cuti_kematian,
            'hari_istri_melahirkan' => $quotationReferensi->hari_istri_melahirkan,
            'hari_cuti_menikah' => $quotationReferensi->hari_cuti_menikah,
            'gaji_saat_cuti' => $quotationReferensi->gaji_saat_cuti,
            'prorate' => $quotationReferensi->prorate,
            'shift_kerja' => $quotationReferensi->shift_kerja,
            'hari_kerja' => $quotationReferensi->hari_kerja,
            'jam_kerja' => $quotationReferensi->jam_kerja,
            'evaluasi_kontrak' => $quotationReferensi->evaluasi_kontrak,
            'durasi_kerjasama' => $quotationReferensi->durasi_kerjasama,
            'durasi_karyawan' => $quotationReferensi->durasi_karyawan,
            'evaluasi_karyawan' => $quotationReferensi->evaluasi_karyawan,
            'jenis_perusahaan_id' => $quotationReferensi->jenis_perusahaan_id,
            'jenis_perusahaan' => $quotationReferensi->jenis_perusahaan,
            'bidang_perusahaan_id' => $quotationReferensi->bidang_perusahaan_id,
            'bidang_perusahaan' => $quotationReferensi->bidang_perusahaan,
            'resiko' => $quotationReferensi->resiko,
            'kunjungan_operasional' => $quotationReferensi->kunjungan_operasional,
            'kunjungan_tim_crm' => $quotationReferensi->kunjungan_tim_crm,
            'keterangan_kunjungan_operasional' => $quotationReferensi->keterangan_kunjungan_operasional,
            'keterangan_kunjungan_tim_crm' => $quotationReferensi->keterangan_kunjungan_tim_crm,
            'training' => $quotationReferensi->training,
            'persen_bunga_bank' => $quotationReferensi->persen_bunga_bank,
            'persen_insentif' => $quotationReferensi->persen_insentif,
            'penagihan' => $quotationReferensi->penagihan,
            'note_harga_jual' => $quotationReferensi->note_harga_jual,
            // Status reset
            'is_aktif' => 0,
            'revisi' => 0,
            'alasan_revisi' => null,
            'step' => 1,
            'materai' => match ($newQuotation->tipe_quotation) {
                'addendum', 'rekontrak' => $quotationReferensi->materai,
                default => false,
            },
        ]);
    }

    // =========================================================================
    // PRIVATE — DETAIL DUPLICATION (3 strategi)
    // =========================================================================

    /**
     * Strategi 1: detail disalin dengan site mapping yang sudah ada (dari duplicateSites).
     * Dipakai oleh duplicateQuotationData (full-copy).
     */
    private function duplicateQuotationDetails(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        foreach ($quotationReferensi->quotationDetails as $detailRef) {
            $newSiteId = $this->siteIdMapping[$detailRef->quotation_site_id] ?? null;

            if (!$newSiteId) {
                \Log::warning('duplicateQuotationDetails: site mapping missing', [
                    'ref_site_id' => $detailRef->quotation_site_id,
                ]);
                continue;
            }

            $newSite = $newQuotation->quotationSites->firstWhere('id', $newSiteId);
            $newDetail = $newQuotation->quotationDetails()->create(
                $this->buildDetailPayload($detailRef, $newSiteId, $newSite?->nama_site ?? $detailRef->nama_site, $newQuotation)
            );

            $this->detailIdMapping[$detailRef->id] = $newDetail->id;
            $this->copyDetailRelations($newDetail, $detailRef, $newQuotation);
        }
    }

    /**
     * Strategi 2: site matching by nama (revisi/rekontrak/adendum).
     * Site referensi yang tidak punya pasangan di quotation baru → di-SKIP (beserta detail & barangnya).
     */
    private function duplicateQuotationDetailsWithSiteMapping(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        if (empty($this->siteIdMapping)) {
            throw new \Exception('Site mapping kosong. Panggil createSiteMapping() terlebih dahulu.');
        }

        // Pre-index sites baru agar tidak query per detail
        $newSitesById = $newQuotation->quotationSites->keyBy('id');

        foreach ($quotationReferensi->quotationDetails as $detailRef) {
            $newSiteId = $this->siteIdMapping[$detailRef->quotation_site_id] ?? null;

            if (!$newSiteId) {
                // Site ini sudah dihapus di revisi → skip detail beserta barang-nya
                \Log::info('Detail di-skip karena sitenya dihapus di revisi', [
                    'detail_id' => $detailRef->id,
                    'ref_site_id' => $detailRef->quotation_site_id,
                ]);
                continue;
            }

            $newSite = $newSitesById[$newSiteId] ?? null;
            $newDetail = $newQuotation->quotationDetails()->create(
                $this->buildDetailPayload($detailRef, $newSiteId, $newSite?->nama_site ?? $detailRef->nama_site, $newQuotation)
            );

            $this->detailIdMapping[$detailRef->id] = $newDetail->id;
            $this->copyDetailRelations($newDetail, $detailRef, $newQuotation);
        }
    }

    /**
     * Strategi 3: site mapping by nama, index fallback (untuk kasus site baru tanpa nama match).
     * Semua detail referensi diupayakan masuk ke site yang sesuai.
     */
    private function duplicateQuotationDetailsForNewSite(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        $newSites = $newQuotation->quotationSites;

        if ($newSites->isEmpty()) {
            throw new \Exception('Tidak ada site pada quotation baru.');
        }

        // Bangun local site mapping: oldSiteId → newSiteId
        $localSiteMapping = [];
        $newSitesList = $newSites->values();

        foreach ($quotationReferensi->quotationSites->values() as $index => $oldSite) {
            $matched = $newSites->firstWhere('nama_site', $oldSite->nama_site)
                ?? ($newSitesList[$index] ?? null);

            if ($matched) {
                $localSiteMapping[$oldSite->id] = $matched->id;
            }
        }

        $newSitesById = $newSites->keyBy('id');

        foreach ($quotationReferensi->quotationDetails as $detailRef) {
            $newSiteId = $localSiteMapping[$detailRef->quotation_site_id] ?? null;

            if (!$newSiteId) {
                \Log::warning('duplicateQuotationDetailsForNewSite: tidak ada site match', [
                    'detail_id' => $detailRef->id,
                    'old_site_id' => $detailRef->quotation_site_id,
                ]);
                continue;
            }

            $newSite = $newSitesById[$newSiteId] ?? null;
            $newDetail = $newQuotation->quotationDetails()->create(
                $this->buildDetailPayload($detailRef, $newSiteId, $newSite?->nama_site ?? $detailRef->nama_site, $newQuotation)
            );

            $this->detailIdMapping[$detailRef->id] = $newDetail->id;
            $this->copyDetailRelations($newDetail, $detailRef, $newQuotation);
        }
    }

    // =========================================================================
    // PRIVATE — DETAIL HELPERS
    // =========================================================================

    /** Bangun array payload untuk QuotationDetail baru. */
    private function buildDetailPayload($detailRef, int $newSiteId, string $namaSite, Quotation $newQuotation): array
    {
        return [
            'quotation_site_id' => $newSiteId,
            'position_id' => $detailRef->position_id,
            'jabatan_kebutuhan' => $detailRef->jabatan_kebutuhan,
            'nama_site' => $namaSite,
            'jumlah_hc' => $detailRef->jumlah_hc,
            'nominal_upah' => $detailRef->nominal_upah,
            'penjamin_kesehatan' => $detailRef->penjamin_kesehatan,
            'is_bpjs_jkk' => $detailRef->is_bpjs_jkk,
            'is_bpjs_jkm' => $detailRef->is_bpjs_jkm,
            'is_bpjs_jht' => $detailRef->is_bpjs_jht,
            'is_bpjs_jp' => $detailRef->is_bpjs_jp,
            'nominal_takaful' => $detailRef->nominal_takaful,
            'biaya_monitoring_kontrol' => $detailRef->biaya_monitoring_kontrol,
            'created_by' => $newQuotation->created_by,
            'created_by_user_id' => $newQuotation->created_by_user_id,
        ];
    }

    /**
     * Copy semua relasi detail (wage, tunjangan, hpp, coss, requirements).
     * Dipanggil dari ketiga strategi detail — tidak ada duplikasi kode.
     */
    private function copyDetailRelations($newDetail, $detailRef, Quotation $newQuotation): void
    {
        // Wage
        if ($detailRef->wage) {
            $newDetail->wage()->create([
                'quotation_id' => $newQuotation->id,
                'upah' => $detailRef->wage->upah,
                'hitungan_upah' => $detailRef->wage->hitungan_upah,
                'lembur' => $detailRef->wage->lembur,
                'nominal_lembur' => $detailRef->wage->nominal_lembur,
                'jenis_bayar_lembur' => $detailRef->wage->jenis_bayar_lembur,
                'jam_per_bulan_lembur' => $detailRef->wage->jam_per_bulan_lembur,
                'lembur_ditagihkan' => $detailRef->wage->lembur_ditagihkan,
                'kompensasi' => $detailRef->wage->kompensasi,
                'thr' => $detailRef->wage->thr,
                'tunjangan_holiday' => $detailRef->wage->tunjangan_holiday,
                'nominal_tunjangan_holiday' => $detailRef->wage->nominal_tunjangan_holiday,
                'jenis_bayar_tunjangan_holiday' => $detailRef->wage->jenis_bayar_tunjangan_holiday,
                'created_by' => $newQuotation->created_by,
                'created_by_user_id' => $newQuotation->created_by_user_id,
            ]);
        }

        // Tunjangan
        foreach ($detailRef->quotationDetailTunjangans as $tunjangan) {
            $newDetail->quotationDetailTunjangans()->create([
                'quotation_id' => $newQuotation->id,
                'nama_tunjangan' => $tunjangan->nama_tunjangan,
                'nominal' => $tunjangan->nominal,
                'nominal_coss' => $tunjangan->nominal_coss,
                'created_by' => $newQuotation->created_by,
                'created_by_user_id' => $newQuotation->created_by_user_id,
            ]);
        }

        // HPP
        if ($detailRef->quotationDetailHpp) {
            $hpp = $detailRef->quotationDetailHpp;
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
                'created_by' => $newQuotation->created_by,
                'created_by_user_id' => $newQuotation->created_by_user_id,
            ]);
        }

        // COSS
        if ($detailRef->quotationDetailCoss) {
            $coss = $detailRef->quotationDetailCoss;
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
                'created_by' => $newQuotation->created_by,
                'created_by_user_id' => $newQuotation->created_by_user_id,
            ]);
        }

        // Requirements
        foreach ($detailRef->quotationDetailRequirements as $req) {
            $newDetail->quotationDetailRequirements()->create([
                'quotation_id' => $newQuotation->id,
                'requirement' => $req->requirement,
                'created_by' => $newQuotation->created_by,
                'created_by_user_id' => $newQuotation->created_by_user_id,
            ]);
        }
    }

    // =========================================================================
    // PRIVATE — BARANG DUPLICATION
    // =========================================================================

    /**
     * Copy barang data menggunakan siteIdMapping & detailIdMapping yang sudah ada.
     *
     * ✅ FIX UTAMA: Barang yang site/detail referensinya tidak ada di mapping
     * (artinya site tersebut dihapus saat revisi) akan di-SKIP, bukan di-fallback
     * ke site/detail lain yang mengakibatkan value salah.
     */
    private function duplicateBarangDataWithMapping(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        // Kaporlap — terikat ke detail
        foreach ($quotationReferensi->quotationKaporlaps as $kaporlap) {
            $newDetailId = $this->detailIdMapping[$kaporlap->quotation_detail_id] ?? null;

            if (!$newDetailId) {
                \Log::info('Kaporlap di-skip (detail dari site yg dihapus)', [
                    'kaporlap_id' => $kaporlap->id,
                    'ref_detail_id' => $kaporlap->quotation_detail_id,
                ]);
                continue; // ✅ Skip, bukan fallback ke detail lain
            }

            $newQuotation->quotationKaporlaps()->create([
                'quotation_detail_id' => $newDetailId,
                'barang_id' => $kaporlap->barang_id,
                'nama' => $kaporlap->nama,
                'jenis_barang_id' => $kaporlap->jenis_barang_id,
                'jenis_barang' => $kaporlap->jenis_barang,
                'jumlah' => $kaporlap->jumlah,
                'harga' => $kaporlap->harga,
                'created_by' => $newQuotation->created_by,
                'created_by_user_id' => $newQuotation->created_by_user_id,
            ]);
        }

        // Device, Chemical, OHC — terikat ke site
        foreach ([
            'quotationDevices' => fn($item) => $this->buildDevicePayload($item, $newQuotation),
            'quotationChemicals' => fn($item) => $this->buildChemicalPayload($item, $newQuotation),
            'quotationOhcs' => fn($item) => $this->buildOhcPayload($item, $newQuotation),
        ] as $relation => $buildPayload) {
            foreach ($quotationReferensi->$relation as $item) {
                $originalSiteId = $this->getOriginalSiteIdFromBarang($item);
                $newSiteId = $this->siteIdMapping[$originalSiteId] ?? null;

                if (!$newSiteId) {
                    \Log::info("$relation di-skip (site dari site yg dihapus)", [
                        'item_id' => $item->id,
                        'ref_site_id' => $originalSiteId,
                    ]);
                    continue; // ✅ Skip, bukan fallback ke site lain
                }

                $payload = $buildPayload($item);
                $payload['quotation_site_id'] = $newSiteId;
                $newQuotation->$relation()->create($payload);
            }
        }
    }

    /**
     * Copy barang data dengan site mapping dari duplicateSites (full-copy, semua site ikut).
     */
    private function duplicateBarangData(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        // Kaporlap
        foreach ($quotationReferensi->quotationKaporlaps as $kaporlap) {
            $newDetailId = $this->detailIdMapping[$kaporlap->quotation_detail_id] ?? null;

            if (!$newDetailId) {
                \Log::warning('duplicateBarangData: detail mapping missing untuk kaporlap', ['id' => $kaporlap->id]);
                continue;
            }

            $newQuotation->quotationKaporlaps()->create([
                'quotation_detail_id' => $newDetailId,
                'barang_id' => $kaporlap->barang_id,
                'nama' => $kaporlap->nama,
                'jenis_barang_id' => $kaporlap->jenis_barang_id,
                'jenis_barang' => $kaporlap->jenis_barang,
                'jumlah' => $kaporlap->jumlah,
                'harga' => $kaporlap->harga,
                'created_by' => $newQuotation->created_by,
                'created_by_user_id' => $newQuotation->created_by_user_id,
            ]);
        }

        foreach ($quotationReferensi->quotationDevices as $device) {
            $originalSiteId = $this->getOriginalSiteIdFromBarang($device);
            $newSiteId = $this->siteIdMapping[$originalSiteId] ?? null;
            if (!$newSiteId)
                continue;
            $payload = $this->buildDevicePayload($device, $newQuotation);
            $payload['quotation_site_id'] = $newSiteId;
            $newQuotation->quotationDevices()->create($payload);
        }

        foreach ($quotationReferensi->quotationChemicals as $chemical) {
            $originalSiteId = $this->getOriginalSiteIdFromBarang($chemical);
            $newSiteId = $this->siteIdMapping[$originalSiteId] ?? null;
            if (!$newSiteId)
                continue;
            $payload = $this->buildChemicalPayload($chemical, $newQuotation);
            $payload['quotation_site_id'] = $newSiteId;
            $newQuotation->quotationChemicals()->create($payload);
        }

        foreach ($quotationReferensi->quotationOhcs as $ohc) {
            $originalSiteId = $this->getOriginalSiteIdFromBarang($ohc);
            $newSiteId = $this->siteIdMapping[$originalSiteId] ?? null;
            if (!$newSiteId)
                continue;
            $payload = $this->buildOhcPayload($ohc, $newQuotation);
            $payload['quotation_site_id'] = $newSiteId;
            $newQuotation->quotationOhcs()->create($payload);
        }
    }

    // =========================================================================
    // PRIVATE — BARANG PAYLOAD BUILDERS
    // =========================================================================

    private function buildDevicePayload($device, Quotation $newQuotation): array
    {
        return [
            'barang_id' => $device->barang_id,
            'nama' => $device->nama,
            'jenis_barang_id' => $device->jenis_barang_id,
            'jenis_barang' => $device->jenis_barang,
            'jumlah' => $device->jumlah,
            'harga' => $device->harga,
            'created_by' => $newQuotation->created_by,
            'created_by_user_id' => $newQuotation->created_by_user_id,
        ];
    }

    private function buildChemicalPayload($chemical, Quotation $newQuotation): array
    {
        return [
            'barang_id' => $chemical->barang_id,
            'nama' => $chemical->nama,
            'jenis_barang_id' => $chemical->jenis_barang_id,
            'jenis_barang' => $chemical->jenis_barang,
            'jumlah' => $chemical->jumlah,
            'harga' => $chemical->harga,
            'masa_pakai' => $chemical->masa_pakai,
            'created_by' => $newQuotation->created_by,
            'created_by_user_id' => $newQuotation->created_by_user_id,
        ];
    }

    private function buildOhcPayload($ohc, Quotation $newQuotation): array
    {
        return [
            'barang_id' => $ohc->barang_id,
            'nama' => $ohc->nama,
            'jenis_barang_id' => $ohc->jenis_barang_id,
            'jenis_barang' => $ohc->jenis_barang,
            'jumlah' => $ohc->jumlah,
            'harga' => $ohc->harga,
            'created_by' => $newQuotation->created_by,
            'created_by_user_id' => $newQuotation->created_by_user_id,
        ];
    }

    // =========================================================================
    // PRIVATE — BARANG SITE ID RESOLVER (unified)
    // =========================================================================

    /**
     * Ambil site_id dari sebuah item barang (Device / Chemical / OHC).
     * Semua tipe punya struktur yang sama, jadi satu method cukup.
     */
    private function getOriginalSiteIdFromBarang($barang): int
    {
        if (!empty($barang->quotation_site_id)) {
            return (int) $barang->quotation_site_id;
        }

        // Fallback via detail jika data lama masih pakai quotation_detail_id
        if (!empty($barang->quotation_detail_id)) {
            $detail = QuotationDetail::find($barang->quotation_detail_id);
            if ($detail && $detail->quotation_site_id) {
                return (int) $detail->quotation_site_id;
            }
        }

        throw new \Exception(
            'Tidak dapat menentukan site_id untuk barang ' . class_basename($barang) . ' id=' . $barang->id
        );
    }

    // =========================================================================
    // PRIVATE — OTHER RELATIONS
    // =========================================================================

    private function duplicateAplikasiPendukung(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        foreach ($quotationReferensi->quotationAplikasis as $app) {
            $newQuotation->quotationAplikasis()->create([
                'aplikasi_pendukung_id' => $app->aplikasi_pendukung_id,
                'aplikasi_pendukung' => $app->aplikasi_pendukung,
                'harga' => $app->harga,
                'created_by' => $newQuotation->created_by,
                'created_by_user_id' => $newQuotation->created_by_user_id,
            ]);
        }
    }

    private function duplicateTrainingData(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        foreach ($quotationReferensi->quotationTrainings as $training) {
            $newQuotation->quotationTrainings()->create([
                'training_id' => $training->training_id,
                'nama' => $training->nama,
                'created_by' => $newQuotation->created_by,
                'created_by_user_id' => $newQuotation->created_by_user_id,
            ]);
        }
    }

    private function duplicateKerjasamaData(Quotation $newQuotation, Quotation $quotationReferensi): void
    {
        foreach ($quotationReferensi->quotationKerjasamas as $kerjasama) {
            $newQuotation->quotationKerjasamas()->create([
                'perjanjian' => $kerjasama->perjanjian,
                'created_by' => $newQuotation->created_by,
                'created_by_user_id' => $newQuotation->created_by_user_id,
            ]);
        }
    }

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
                'created_by' => $newQuotation->created_by,
                'created_by_user_id' => $newQuotation->created_by_user_id,
            ]);
        }
    }

    // =========================================================================
    // PUBLIC UTILITIES
    // =========================================================================

    public function isSiteExisting(int $leadsId, array $siteData): bool
    {
        return QuotationSite::where('leads_id', $leadsId)
            ->where('nama_site', $siteData['nama_site'])
            ->where('provinsi_id', $siteData['provinsi_id'])
            ->where('kota_id', $siteData['kota_id'])
            ->exists();
    }

    public function getExistingSites(int $leadsId, array $siteNames = []): array
    {
        $query = QuotationSite::where('leads_id', $leadsId);

        if (!empty($siteNames)) {
            $query->whereIn('nama_site', $siteNames);
        }

        return $query->get()->toArray();
    }
}