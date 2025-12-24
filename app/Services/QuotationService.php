<?php

namespace App\Services;

use App\Models\{
    LogApproval,
    LogNotification,
    Quotation,
    QuotationDetail,
    QuotationSite,
    ManagementFee,
    QuotationAplikasi,
    QuotationDetailTunjangan,
    QuotationDetailHpp,
    QuotationDetailCoss,
    QuotationChemical,
    QuotationOhc,
    QuotationKaporlap,
    QuotationDevices,
    QuotationDetailWage,
    SalaryRule,
    User
};
use App\DTO\QuotationCalculationResult;
use App\DTO\CalculationSummary;
use App\DTO\DetailCalculation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    // ============================ MAIN CALCULATION FLOW ============================

    /**
     * Calculate quotation dengan return object terpisah untuk calculated values
     */
    public function calculateQuotation($quotation): QuotationCalculationResult
    {
        try {
            $result = new QuotationCalculationResult($quotation);

            $this->initializeQuotation($quotation);
            $this->loadQuotationData($quotation);

            // FIX: Ensure every detail has wage data
            foreach ($quotation->quotation_detail as $detail) {
                if (!$detail->wage) {
                    $this->createDefaultWage($detail);
                }
            }

            // Cek apakah ada quotation details
            if ($quotation->quotation_detail->isEmpty()) {
                return $result;
            }

            $jumlahHc = $quotation->quotation_detail->sum('jumlah_hc');
            $quotation->jumlah_hc = $jumlahHc;
            $quotation->provisi = $this->calculateProvisi($quotation->durasi_kerjasama);

            // First pass calculation
            $this->calculateFirstPass($quotation, $jumlahHc, $result);

            // Recalculate with gross-up adjustments
            $this->recalculateWithGrossUp($quotation, $jumlahHc, $result);

            return $result;

        } catch (\Exception $e) {
            \Log::error("Error in calculateQuotation: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    private function createDefaultWage($detail)
    {
        try {
            $wage = QuotationDetailWage::create([
                'quotation_detail_id' => $detail->id,
                'quotation_id' => $detail->quotation_id,
                'upah' => null,
                'hitungan_upah' => null,
                'lembur' => 'Tidak Ada',
                'nominal_lembur' => 0,
                'jenis_bayar_lembur' => null,
                'jam_per_bulan_lembur' => 0,
                'lembur_ditagihkan' => 'Tidak Ditagihkan',
                'kompensasi' => 'Tidak Ada',
                'thr' => 'Tidak Ada',
                'tunjangan_holiday' => 'Tidak Ada',
                'nominal_tunjangan_holiday' => 0,
                'jenis_bayar_tunjangan_holiday' => null,
                'created_by' => Auth::user()->full_name,
            ]);

            // Reload the relation
            $detail->load('wage');

            return $wage;
        } catch (\Exception $e) {
            \Log::error("Failed to create default wage for detail {$detail->id}: " . $e->getMessage());
            return null;
        }
    }

    // ============================ INITIALIZATION ============================
    private function initializeQuotation($quotation)
    {
        // HAPUS inisialisasi kolom yang tidak ada di tabel
        // $quotation->persen_bpjs_ketenagakerjaan = 0;
        // $quotation->persen_bpjs_kesehatan = 0;
    }

    private function loadQuotationData($quotation)
    {
        // Load dengan relasi wage dan tunjangan
        $quotationDetails = QuotationDetail::with(['wage', 'quotationDetailTunjangans'])
            ->where('quotation_id', $quotation->id)->get();

        $quotationSites = QuotationSite::where('quotation_id', $quotation->id)->get();

        // Calculate site details count
        $quotationSites->each(function ($site) use ($quotationDetails) {
            $site->jumlah_detail = $quotationDetails
                ->where('quotation_site_id', $site->id)->count();
        });

        // Get management fee
        $managementFee = ManagementFee::find($quotation->management_fee_id);
        $managementFeeName = $managementFee->nama ?? '';

        $quotation->quotation_detail = $quotationDetails;
        $quotation->quotation_site = $quotationSites;
        $quotation->management_fee = $managementFeeName;
    }


    // ============================ CORE CALCULATION METHODS ============================
    private function calculateFirstPass($quotation, $jumlahHc, QuotationCalculationResult $result): void
    {
        $daftarTunjangan = QuotationDetailTunjangan::where('quotation_id', $quotation->id)
            ->distinct('nama_tunjangan')->get(['nama_tunjangan as nama']);

        $this->processAllDetails($quotation, $daftarTunjangan, $jumlahHc, $result);
        $this->calculateHpp($quotation, $jumlahHc, $quotation->provisi, $result);
        $this->calculateCoss($quotation, $jumlahHc, $quotation->provisi, $result);

        // HAPUS assignment yang tidak perlu ke model Quotation
        // $quotation->jumlah_hc = $jumlahHc; // JANGAN lakukan ini
        // $quotation->provisi = $this->calculateProvisi($quotation->durasi_kerjasama); // JANGAN lakukan ini
    }
    private function recalculateWithGrossUp($quotation, $jumlahHc, QuotationCalculationResult $result): void
    {
        $daftarTunjangan = QuotationDetailTunjangan::where('quotation_id', $quotation->id)
            ->distinct('nama_tunjangan')->get(['nama_tunjangan as nama']);

        $this->calculateBankInterestAndIncentive($quotation, $jumlahHc, $result);
        $this->updateDetailsWithGrossUp($quotation, $daftarTunjangan, $jumlahHc, $result);
    }

    // ============================ DETAIL PROCESSING ============================
    private function processAllDetails($quotation, $daftarTunjangan, $jumlahHc, QuotationCalculationResult $result): void
    {
        $quotation->quotation_detail->each(function ($detail) use ($quotation, $daftarTunjangan, $jumlahHc, $result) {
            try {
                $this->processSingleDetail($detail, $quotation, $daftarTunjangan, $jumlahHc, $result);
            } catch (\Exception $e) {
                \Log::error("Failed to process detail {$detail->id}, skipping: " . $e->getMessage());
                // Skip this detail but continue with others
            }
        });
    }

    private function processSingleDetail($detail, $quotation, $daftarTunjangan, $jumlahHc, QuotationCalculationResult $result): void
    {
        try {
            $detailCalculation = new DetailCalculation($detail->id);

            $hpp = QuotationDetailHpp::where('quotation_detail_id', $detail->id)->first();
            $coss = QuotationDetailCoss::where('quotation_detail_id', $detail->id)->first();
            $site = QuotationSite::find($detail->quotation_site_id);
            $wage = $detail->wage;

            // Jika wage null, buat object kosong untuk menghindari error
            if (!$wage) {
                $wage = new \stdClass();
                $wage->upah = null;
                $wage->hitungan_upah = null;
                $wage->lembur = "Tidak";
                $wage->nominal_lembur = 0;
                $wage->jenis_bayar_lembur = null;
                $wage->jam_per_bulan_lembur = 0;
                $wage->lembur_ditagihkan = "Tidak Ditagihkan";
                $wage->kompensasi = "Tidak";
                $wage->thr = "Tidak";
                $wage->tunjangan_holiday = "Tidak";
                $wage->nominal_tunjangan_holiday = 0;
                $wage->jenis_bayar_tunjangan_holiday = null;
            }

            $this->initializeDetail($detail, $hpp, $site, $wage);
            $this->calculateDetailComponents($detail, $quotation, $daftarTunjangan, $jumlahHc, $hpp, $coss, $wage, $detailCalculation);

            // Simpan detail calculation ke result
            $result->detail_calculations[$detail->id] = $detailCalculation;

        } catch (\Exception $e) {
            \Log::error("Error processing detail ID {$detail->id}: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    private function initializeDetail($detail, $hpp, $site, $wage)
    {
        $detail->nominal_upah = $detail->nominal_upah ?? $hpp->gaji_pokok ?? $site->nominal_upah;
        $detail->umk = $site->umk ?? 0;
        $detail->ump = $site->ump ?? 0;

        // Jangan set bunga_bank dan insentif jika sudah ada (misalnya dari gross-up)
        if (!isset($detail->bunga_bank)) {
            $detail->bunga_bank = $hpp->bunga_bank ?? 0;
        }
        if (!isset($detail->insentif)) {
            $detail->insentif = $hpp->insentif ?? 0;
        }

        $detail->upah = $wage->upah ?? null;
        $detail->hitungan_upah = $wage->hitungan_upah ?? null;
        $detail->lembur = $wage->lembur ?? "Tidak";
        $detail->nominal_lembur = $wage->nominal_lembur ?? 0;
        $detail->jenis_bayar_lembur = $wage->jenis_bayar_lembur ?? null;
        $detail->jam_per_bulan_lembur = $wage->jam_per_bulan_lembur ?? 0;
        $detail->lembur_ditagihkan = $wage->lembur_ditagihkan ?? "Tidak Ditagihkan";
        $detail->kompensasi = $wage->kompensasi ?? "Tidak";
        $detail->thr = $wage->thr ?? "Tidak";
        $detail->tunjangan_holiday = $wage->tunjangan_holiday ?? "Tidak";
        $detail->nominal_tunjangan_holiday = $wage->nominal_tunjangan_holiday ?? 0;
        $detail->jenis_bayar_tunjangan_holiday = $wage->jenis_bayar_tunjangan_holiday ?? null;
    }
    private function calculateDetailComponents($detail, $quotation, $daftarTunjangan, $jumlahHc, $hpp, $coss, $wage, DetailCalculation $detailCalculation): void
    {
        try {
            // Calculate core components
            $totalTunjangan = $this->calculateTunjangan($detail, $daftarTunjangan);

            $this->calculateBpjs($detail, $quotation, $hpp);

            $this->calculateExtras($detail, $quotation, $hpp, $wage);

            // Calculate items
            $this->calculateAllItems($detail, $quotation, $jumlahHc, $hpp, $coss);

            // Final totals
            $this->calculateFinalTotals($detail, $quotation, $totalTunjangan);

            // Simpan data ke DTO
            $this->populateDetailCalculation($detail, $quotation, $detailCalculation);

        } catch (\Exception $e) {
            \Log::error("Error in calculateDetailComponents for detail {$detail->id}: " . $e->getMessage());
            \Log::error("Stack trace in calculateDetailComponents: " . $e->getTraceAsString());
            throw $e;
        }
    }
    private function populateDetailCalculation($detail, $quotation, DetailCalculation $detailCalculation): void
    {
        // Calculate BPU deduction if applicable
        $potonganBpu = 0;
        if ($detail->penjamin_kesehatan === 'BPU') {
            $potonganBpu = 16800;
        }

        // PERBAIKAN CRITICAL: Gunakan nilai dari $detail yang sudah dihitung di calculateExtras
        // JANGAN ambil dari HPP karena HPP mungkin masih 0
        $tunjanganHariRaya = $detail->tunjangan_hari_raya ?? 0;
        $kompensasi = $detail->kompensasi ?? 0;
        $tunjanganHoliday = $detail->tunjangan_holiday ?? 0;

        // DEBUG: Bandingkan nilai dari detail vs HPP
        $hpp = QuotationDetailHpp::where('quotation_detail_id', $detail->id)->first();
        \Log::info("THR Value Comparison", [
            'detail_id' => $detail->id,
            'from_detail' => $tunjanganHariRaya,
            'from_hpp' => $hpp ? $hpp->tunjangan_hari_raya : 'no_hpp',
            'from_wage_thr' => $detail->thr ?? 'no_thr'
        ]);

        // Data untuk HPP
        $detailCalculation->hpp_data = [
            'quotation_detail_id' => $detail->id,
            'quotation_id' => $quotation->id,
            'leads_id' => $quotation->leads_id,
            'position_id' => $detail->position_id,
            'jumlah_hc' => $detail->jumlah_hc,
            'gaji_pokok' => $detail->nominal_upah,
            'total_tunjangan' => $detail->total_tunjangan ?? 0,
            'tunjangan_hari_raya' => $tunjanganHariRaya, // ← GUNAKAN NILAI INI
            'kompensasi' => $kompensasi, // ← GUNAKAN NILAI INI
            'tunjangan_hari_libur_nasional' => $tunjanganHoliday, // ← GUNAKAN NILAI INI
            'lembur' => $detail->lembur ?? 0,
            'takaful' => $detail->nominal_takaful ?? 0,
            'bpjs_jkk' => $detail->bpjs_jkk ?? 0,
            'bpjs_jkm' => $detail->bpjs_jkm ?? 0,
            'bpjs_jht' => $detail->bpjs_jht ?? 0,
            'bpjs_jp' => $detail->bpjs_jp ?? 0,
            'bpjs_ks' => $detail->bpjs_kes ?? 0,
            'persen_bpjs_jkk' => $detail->persen_bpjs_jkk ?? 0,
            'persen_bpjs_jkm' => $detail->persen_bpjs_jkm ?? 0,
            'persen_bpjs_jht' => $detail->persen_bpjs_jht ?? 0,
            'persen_bpjs_jp' => $detail->persen_bpjs_jp ?? 0,
            'persen_bpjs_ks' => $detail->persen_bpjs_kes ?? 0,
            'provisi_seragam' => $detail->personil_kaporlap ?? 0,
            'provisi_peralatan' => $detail->personil_devices ?? 0,
            'provisi_chemical' => $detail->personil_chemical ?? 0,
            'provisi_ohc' => $detail->personil_ohc ?? 0,
            'bunga_bank' => $detail->bunga_bank ?? 0,
            'insentif' => $detail->insentif ?? 0,
            'potongan_bpu' => $potonganBpu,
            'total_biaya_per_personil' => $detail->total_personil ?? 0,
            'total_biaya_all_personil' => $detail->sub_total_personil ?? 0,
        ];

        // Data untuk COSS
        $detailCalculation->coss_data = [
            'quotation_detail_id' => $detail->id,
            'quotation_id' => $quotation->id,
            'leads_id' => $quotation->leads_id,
            'position_id' => $detail->position_id,
            'jumlah_hc' => $detail->jumlah_hc,
            'gaji_pokok' => $detail->nominal_upah,
            'total_tunjangan' => $detail->total_tunjangan ?? 0,
            'total_base_manpower' => $detail->total_base_manpower ?? 0,
            'tunjangan_hari_raya' => $tunjanganHariRaya, // ← GUNAKAN NILAI INI
            'kompensasi' => $kompensasi, // ← GUNAKAN NILAI INI
            'tunjangan_hari_libur_nasional' => $tunjanganHoliday, // ← GUNAKAN NILAI INI
            'lembur' => $detail->lembur ?? 0,
            'bpjs_jkk' => $detail->bpjs_jkk ?? 0,
            'bpjs_jkm' => $detail->bpjs_jkm ?? 0,
            'bpjs_jht' => $detail->bpjs_jht ?? 0,
            'bpjs_jp' => $detail->bpjs_jp ?? 0,
            'bpjs_ks' => $detail->bpjs_kes ?? 0,
            'persen_bpjs_jkk' => $detail->persen_bpjs_jkk ?? 0,
            'persen_bpjs_jkm' => $detail->persen_bpjs_jkm ?? 0,
            'persen_bpjs_jht' => $detail->persen_bpjs_jht ?? 0,
            'persen_bpjs_jp' => $detail->persen_bpjs_jp ?? 0,
            'persen_bpjs_ks' => $detail->persen_bpjs_kes ?? 0,
            'provisi_seragam' => $detail->personil_kaporlap_coss ?? 0,
            'provisi_peralatan' => $detail->personil_devices_coss ?? 0,
            'provisi_chemical' => $detail->personil_chemical_coss ?? 0,
            'provisi_ohc' => $detail->personil_ohc_coss ?? 0,
            'total_exclude_base_manpower' => $detail->total_exclude_base_manpower ?? 0,
            'bunga_bank' => $detail->bunga_bank ?? 0,
            'insentif' => $detail->insentif ?? 0,
            'potongan_bpu' => $potonganBpu,
        ];

        \Log::info("Populated THR values to DTO", [
            'detail_id' => $detail->id,
            'thr_value' => $tunjanganHariRaya,
            'kompensasi_value' => $kompensasi,
            'tunjangan_holiday_value' => $tunjanganHoliday
        ]);
    }

    // ============================ COMPONENT CALCULATIONS ============================
    private function calculateTunjangan($detail, $daftarTunjangan)
    {
        $totalTunjangan = 0;
        foreach ($daftarTunjangan as $tunjangan) {
            $dtTunjangan = QuotationDetailTunjangan::where('quotation_detail_id', $detail->id)
                ->where('nama_tunjangan', $tunjangan->nama)->first();

            $value = (float) ($dtTunjangan->nominal ?? 0);
            $detail->{$tunjangan->nama} = $value;
            $totalTunjangan += $value;
        }
        $detail->total_tunjangan = $totalTunjangan;
        return $totalTunjangan;
    }
    private function calculateBpjs($detail, $quotation, $hpp)
    {
        // DEBUG: Log untuk troubleshooting
        \Log::info("Calculating BPJS for detail", [
            'detail_id' => $detail->id,
            'program_bpjs' => $quotation->program_bpjs,
            'penjamin_kesehatan' => $detail->penjamin_kesehatan,
            'nominal_upah' => $detail->nominal_upah,
            'umk' => $detail->umk,
            'ump' => $detail->ump,
            'nominal_takaful' => $detail->nominal_takaful // Tambahkan log untuk nominal_takaful
        ]);

        // PERBAIKAN: Terima kedua nilai "BPJS" dan "BPJS Kesehatan"
        if ($detail->penjamin_kesehatan === 'BPU') {
            // BPU = potong 16 ribu dari nominal upah, tidak ada BPJS sama sekali
            $detail->bpjs_jkk = 0;
            $detail->bpjs_jkm = 0;
            $detail->bpjs_jht = 0;
            $detail->bpjs_jp = 0;
            $detail->bpjs_kes = 0;

            $detail->persen_bpjs_jkk = 0;
            $detail->persen_bpjs_jkm = 0;
            $detail->persen_bpjs_jht = 0;
            $detail->persen_bpjs_jp = 0;
            $detail->persen_bpjs_kes = 0;

            // Potong 16 ribu dari nominal upah
            $detail->nominal_upah = $detail->nominal_upah - 16800;

            $this->updateQuotationBpjs($detail, $quotation);

            \Log::info("BPU mode - BPJS set to 0", ['detail_id' => $detail->id]);
            return;
        }

        // PERBAIKAN: Terima kedua nilai "BPJS" dan "BPJS Kesehatan"
        if ($quotation->program_bpjs === 'BPJS' || $quotation->program_bpjs === 'BPJS Kesehatan') {
            $upahBpjs = $this->calculateUpahBpjs($detail->nominal_upah, $detail->umk, $detail->ump);

            // PERBAIKAN: Standardize penjamin kesehatan untuk konsistensi
            if ($detail->penjamin_kesehatan === 'BPJS Kesehatan') {
                $detail->penjamin_kesehatan = 'BPJS';
            }
            // PERBAIKAN: Sesuaikan dengan peraturan Indonesia 2024
            $bpjsConfig = [
                'jkk' => [
                    'field' => 'bpjs_jkk',
                    'percent' => 'persen_bpjs_jkk',
                    'default' => $this->getJkkPercentage($quotation->resiko),
                    'keterangan' => 'Ditanggung perusahaan'
                ],
                'jkm' => [
                    'field' => 'bpjs_jkm',
                    'percent' => 'persen_bpjs_jkm',
                    'default' => 0.30, // Sesuai peraturan
                    'keterangan' => 'Ditanggung perusahaan'
                ],
                'jht' => [
                    'field' => 'bpjs_jht',
                    'percent' => 'persen_bpjs_jht',
                    'default' => 3.70, // Perusahaan 3.7%, karyawan 2%
                    'keterangan' => 'Ditanggung perusahaan'
                ],
                'jp' => [
                    'field' => 'bpjs_jp',
                    'percent' => 'persen_bpjs_jp',
                    'default' => 2.00, // Perusahaan 2%, karyawan 1%
                    'keterangan' => 'Ditanggung perusahaan'
                ],
                'kes' => [
                    'field' => 'bpjs_kes',
                    'percent' => 'persen_bpjs_kes',
                    'default' => 4.00, // Perusahaan 4%, karyawan 1% (total 5%)
                    'keterangan' => 'Ditanggung perusahaan',
                    'base' => $upahBpjs
                ]
            ];

            foreach ($bpjsConfig as $key => $config) {
                $hppValue = $hpp->{$config['field']} ?? null;

                // PERBAIKAN: Cek apakah nilai dari HPP null atau 0
                if ($hppValue === null || $hppValue === 0) {
                    $base = $config['base'] ?? $upahBpjs;
                    $persentase = $config['default'];

                    // PERBAIKAN: Gunakan persentase dari detail jika ada, otherwise gunakan default
                    $detail->{$config['percent']} = $detail->{$config['percent']} ?? $persentase;

                    // PERBAIKAN KHUSUS: Untuk BPJS Kesehatan, jika menggunakan asuransi swasta, gunakan nominal_takaful
                    if ($key === 'kes' && ($detail->penjamin_kesehatan === "Asuransi Swasta" || $detail->penjamin_kesehatan === "Takaful")) {
                        $detail->{$config['field']} = $detail->nominal_takaful ?? 0;
                        \Log::info("Using Takaful for BPJS Kesehatan", [
                            'detail_id' => $detail->id,
                            'nominal_takaful' => $detail->nominal_takaful,
                            'penjamin_kesehatan' => $detail->penjamin_kesehatan
                        ]);
                    } else {
                        $detail->{$config['field']} = $base * $detail->{$config['percent']} / 100;
                    }

                    \Log::info("BPJS calculated", [
                        'detail_id' => $detail->id,
                        'type' => $key,
                        'base' => $base,
                        'persentase' => $detail->{$config['percent']},
                        'nilai' => $detail->{$config['field']},
                        'keterangan' => $config['keterangan']
                    ]);
                } else {
                    // Gunakan nilai dari HPP
                    $detail->{$config['field']} = $hppValue;
                    $detail->{$config['percent']} = $hpp->{"persen_{$config['field']}"} ?? $config['default'];

                    \Log::info("BPJS from HPP", [
                        'detail_id' => $detail->id,
                        'type' => $key,
                        'nilai' => $detail->{$config['field']},
                        'persentase' => $detail->{$config['percent']}
                    ]);
                }
            }

            // Apply BPJS opt-out berdasarkan data dari quotation detail
            $this->applyBpjsOptOut($detail);
            $this->updateQuotationBpjs($detail, $quotation);

            \Log::info("Final BPJS values", [
                'detail_id' => $detail->id,
                'bpjs_jkk' => $detail->bpjs_jkk,
                'bpjs_jkm' => $detail->bpjs_jkm,
                'bpjs_jht' => $detail->bpjs_jht,
                'bpjs_jp' => $detail->bpjs_jp,
                'bpjs_kes' => $detail->bpjs_kes,
                'penjamin_kesehatan' => $detail->penjamin_kesehatan,
                'nominal_takaful' => $detail->nominal_takaful
            ]);
        } else {
            \Log::warning("Program BPJS tidak dikenali, tidak menghitung BPJS", [
                'detail_id' => $detail->id,
                'program_bpjs' => $quotation->program_bpjs
            ]);
        }
    }
    // PERBAIKAN 1: Pastikan nilai THR dihitung dengan benar
    private function calculateExtras($detail, $quotation, $hpp, $wage)
    {
        try {
            // DEBUG DETIL: Log semua properti wage
            \Log::info("=== DEBUG CALCULATE EXTRAS ===", [
                'detail_id' => $detail->id,
                'wage_exists' => !empty($wage),
                'wage_id' => $wage ? ($wage->id ?? 'no_id') : 'no_wage',
                'wage_data' => $wage ? [
                    'thr' => $wage->thr ?? 'null',
                    'kompensasi' => $wage->kompensasi ?? 'null',
                    'tunjangan_holiday' => $wage->tunjangan_holiday ?? 'null',
                    'nominal_tunjangan_holiday' => $wage->nominal_tunjangan_holiday ?? 0
                ] : 'no_wage_data'
            ]);

            // THR & Kompensasi - GUNAKAN DATA DARI WAGE DENGAN FALLBACK
            $thrValue = $wage->thr ?? "Tidak";
            $kompensasiValue = $wage->kompensasi ?? "Tidak";

            // DEBUG: Tampilkan nilai sebenarnya
            \Log::info("THR Raw Values", [
                'detail_id' => $detail->id,
                'thr_raw' => $thrValue,
                'kompensasi_raw' => $kompensasiValue,
                'thr_type' => gettype($thrValue),
                'kompensasi_type' => gettype($kompensasiValue)
            ]);

            // PERBAIKAN CRITICAL: Normalisasi string comparison
            $thrIsDiprovisikan = (is_string($thrValue) && strtolower(trim($thrValue)) === 'diprovisikan');
            $kompensasiIsDiprovisikan = (is_string($kompensasiValue) && strtolower(trim($kompensasiValue)) === 'diprovisikan');
            $kompensasiIsDitagihkan = (is_string($kompensasiValue) && strtolower(trim($kompensasiValue)) === 'ditagihkan');

            \Log::info("THR Check Results", [
                'detail_id' => $detail->id,
                'thr_is_diprovisikan' => $thrIsDiprovisikan,
                'kompensasi_is_diprovisikan' => $kompensasiIsDiprovisikan,
                'kompensasi_is_ditagihkan' => $kompensasiIsDitagihkan
            ]);

            // PERBAIKAN CRITICAL: Jika HPP nilai 0, hitung ulang dari wage
            // THR: Gunakan nilai dari HPP hanya jika > 0, jika 0 hitung dari wage
            if ($hpp && $hpp->tunjangan_hari_raya > 0) {
                $detail->tunjangan_hari_raya = (float) $hpp->tunjangan_hari_raya;
                \Log::info("Using THR from HPP (non-zero)", [
                    'detail_id' => $detail->id,
                    'value' => $hpp->tunjangan_hari_raya
                ]);
            } else if ($thrIsDiprovisikan) {
                $detail->tunjangan_hari_raya = round((float) $detail->nominal_upah / 12, 2);
                \Log::info("Calculated THR from nominal_upah", [
                    'detail_id' => $detail->id,
                    'nominal_upah' => $detail->nominal_upah,
                    'thr_calculated' => $detail->tunjangan_hari_raya,
                    'reason' => 'HPP value is 0 and wage thr is Diprovisikan'
                ]);
            } else {
                $detail->tunjangan_hari_raya = 0;
                \Log::info("THR set to 0", [
                    'detail_id' => $detail->id,
                    'reason' => 'thr is not Diprovisikan'
                ]);
            }

            // Tunjangan Holiday - dengan logging
            $tunjanganHolidayValue = $wage->tunjangan_holiday ?? "Tidak";
            \Log::info("Tunjangan Holiday Check", [
                'detail_id' => $detail->id,
                'raw_value' => $tunjanganHolidayValue,
                'is_flat' => (is_string($tunjanganHolidayValue) && strtolower(trim($tunjanganHolidayValue)) === 'flat')
            ]);

            $isTunjanganHolidayFlat = (is_string($tunjanganHolidayValue) && strtolower(trim($tunjanganHolidayValue)) === 'flat');

            // PERBAIKAN CRITICAL: Jika HPP nilai 0, hitung ulang dari wage
            if ($hpp && $hpp->tunjangan_hari_libur_nasional > 0) {
                $detail->tunjangan_holiday = $hpp->tunjangan_hari_libur_nasional;
            } else if ($isTunjanganHolidayFlat) {
                $detail->tunjangan_holiday = $wage->nominal_tunjangan_holiday ?? 0;
            } else {
                $detail->tunjangan_holiday = 0;
            }

            // Log hasil akhir
            \Log::info("Extras Calculation Result", [
                'detail_id' => $detail->id,
                'tunjangan_hari_raya' => $detail->tunjangan_hari_raya,
                'kompensasi' => $detail->kompensasi,
                'tunjangan_holiday' => $detail->tunjangan_holiday,
                'lembur' => $detail->lembur
            ]);

        } catch (\Exception $e) {
            \Log::error("Error in calculateExtras for detail {$detail->id}: " . $e->getMessage());
            throw $e;
        }
    }
    private function calculateAllItems($detail, $quotation, $jumlahHc, $hpp, $coss)
    {
        $items = [
            'kaporlap' => ['hpp_field' => 'provisi_seragam', 'coss_field' => 'provisi_seragam', 'model' => QuotationKaporlap::class, 'detail_id' => $detail->id],
            'devices' => ['hpp_field' => 'provisi_peralatan', 'coss_field' => 'provisi_peralatan', 'model' => QuotationDevices::class, 'divider' => $jumlahHc],
            'ohc' => ['hpp_field' => 'provisi_ohc', 'coss_field' => 'provisi_ohc', 'model' => QuotationOhc::class, 'divider' => $jumlahHc],
            'chemical' => ['hpp_field' => 'provisi_chemical', 'coss_field' => 'provisi_chemical', 'model' => QuotationChemical::class, 'special' => 'chemical']
        ];

        foreach ($items as $key => $config) {
            $this->calculateItem($detail, $quotation, $key, $config, $hpp, $coss, $jumlahHc);
        }
    }

    private function calculateItem($detail, $quotation, $itemName, $config, $hpp, $coss, $jumlahHc)
    {
        $hppValue = $hpp->{$config['hpp_field']} ?? $this->calculateItemTotal(
            $config['model'],
            $quotation->id,
            $config['detail_id'] ?? null,
            $quotation->provisi,
            $config['divider'] ?? 1,
            $config['special'] ?? null,
            $jumlahHc
        );

        $detail->{"personil_$itemName"} = $hppValue;
        $detail->{"personil_{$itemName}_coss"} = $coss->{$config['coss_field']} ?? $hppValue;
    }

    // ============================ FINAL TOTALS ============================
    private function calculateFinalTotals($detail, $quotation, $totalTunjangan)
    {
        // Hitung potongan BPU jika ada
        $potonganBpu = 0;
        if ($detail->penjamin_kesehatan === 'BPU') {
            $potonganBpu = 16800; // Fixed 16 ribu per karyawan
            $detail->potongan_bpu = $potonganBpu;

            \Log::info("BPU potongan applied", [
                'detail_id' => $detail->id,
                'potongan_bpu' => $potonganBpu
            ]);
        }

        // PERBAIKAN CRITICAL: Pastikan THR dan komponen lainnya digunakan
        // DAN PASTIKAN SEMUA VARIABEL DIKONVERSI KE FLOAT/INTEGER
        $tunjanganHariRaya = (float) ($detail->tunjangan_hari_raya ?? 0);
        $kompensasi = (float) ($detail->kompensasi ?? 0);
        $tunjanganHoliday = (float) ($detail->tunjangan_holiday ?? 0);
        $nominalUpah = (float) ($detail->nominal_upah ?? 0);
        $totalTunjangan = (float) $totalTunjangan;
        $lembur = (float) ($detail->lembur ?? 0);
        $bpjsKetenagakerjaan = (float) ($detail->bpjs_ketenagakerjaan ?? 0);
        $biayaKesehatan = (float) ($detail->bpjs_kesehatan ?? 0);
        $personilKaporlap = (float) ($detail->personil_kaporlap ?? 0);
        $personilDevices = (float) ($detail->personil_devices ?? 0);
        $personilChemical = (float) ($detail->personil_chemical ?? 0);
        $personilOhc = (float) ($detail->personil_ohc ?? 0);
        $bungaBank = (float) ($detail->bunga_bank ?? 0);
        $insentif = (float) ($detail->insentif ?? 0);
        $jumlahHc = (int) ($detail->jumlah_hc ?? 0);

        // Debug tipe data
        \Log::info("Data Types Check", [
            'detail_id' => $detail->id,
            'nominal_upah_type' => gettype($detail->nominal_upah ?? 0),
            'tunjangan_hari_raya_type' => gettype($detail->tunjangan_hari_raya ?? 0),
            'kompensasi_type' => gettype($detail->kompensasi ?? 0),
            'total_tunjangan_type' => gettype($totalTunjangan)
        ]);

        // Log komponen perhitungan dengan nilai sudah dikonversi
        \Log::info("Final Totals Components (Converted)", [
            'detail_id' => $detail->id,
            'tunjangan_hari_raya' => $tunjanganHariRaya,
            'kompensasi' => $kompensasi,
            'tunjangan_holiday' => $tunjanganHoliday,
            'total_tunjangan' => $totalTunjangan,
            'bpjs_ketenagakerjaan' => $bpjsKetenagakerjaan,
            'biaya_kesehatan' => $biayaKesehatan
        ]);

        // PERBAIKAN: Pastikan semua komponen dijumlahkan dengan aman
        $detail->total_personil = $nominalUpah
            + $totalTunjangan
            + $tunjanganHariRaya
            + $kompensasi
            + $tunjanganHoliday
            + $lembur
            + $bpjsKetenagakerjaan
            + $biayaKesehatan
            + $personilKaporlap
            + $personilDevices
            + $personilChemical
            + $personilOhc
            + $bungaBank
            + $insentif
            - $potonganBpu;

        $detail->sub_total_personil = $detail->total_personil * $jumlahHc;

        // COSS Calculations
        $detail->total_base_manpower = round($nominalUpah + $totalTunjangan, 2);

        $detail->total_exclude_base_manpower = round(
            $tunjanganHariRaya
            + $kompensasi
            + $tunjanganHoliday
            + $lembur
            + $biayaKesehatan
            + $bpjsKetenagakerjaan
            + ($detail->personil_kaporlap_coss ?? 0)
            + ($detail->personil_devices_coss ?? 0)
            + ($detail->personil_chemical_coss ?? 0),
            2
        );

        $detail->total_personil_coss = round(
            $detail->total_base_manpower
            + $detail->total_exclude_base_manpower
            + ($detail->personil_ohc_coss ?? 0)
            - $potonganBpu,
            2
        );

        $detail->sub_total_personil_coss = round($detail->total_personil_coss * $jumlahHc, 2);

        \Log::info("Final totals calculated", [
            'detail_id' => $detail->id,
            'total_personil' => $detail->total_personil,
            'sub_total_personil' => $detail->sub_total_personil,
            'total_personil_coss' => $detail->total_personil_coss,
            'sub_total_personil_coss' => $detail->sub_total_personil_coss
        ]);
    }
    // ============================ GROSS UP RECALCULATION ============================
    private function calculateBankInterestAndIncentive($quotation, $jumlahHc, QuotationCalculationResult $result): void
    {
        $summary = $result->calculation_summary;

        $persenBungaBank = $quotation->top != "Non TOP" ? $quotation->persen_bunga_bank : 0;

        $summary->bunga_bank_total = $persenBungaBank ?
            $summary->total_sebelum_management_fee * ($persenBungaBank / 100) / $jumlahHc : 0;

        $summary->insentif_total = $quotation->persen_insentif ?
            $summary->nominal_management_fee_coss * ($quotation->persen_insentif / 100) / $jumlahHc : 0;
        \Log::info('Bunga Bank Calculation', [
            'top' => $quotation->top,
            'persen_bunga_bank' => $quotation->persen_bunga_bank,
            'bunga_dihitung' => ($quotation->top != "Non TOP") ? 'YA' : 'TIDAK',
            'bunga_bank_total' => $summary->bunga_bank_total
        ]);
    }

    private function updateDetailsWithGrossUp($quotation, $daftarTunjangan, $jumlahHc, QuotationCalculationResult $result): void
    {
        $summary = $result->calculation_summary;

        \Log::info('Setting bunga_bank to details', [
            'bunga_bank_total' => $summary->bunga_bank_total,
            'insentif_total' => $summary->insentif_total,
            'detail_count' => $quotation->quotation_detail->count()
        ]);

        $quotation->quotation_detail->each(function ($detail) use ($quotation, $summary) {
            $detail->bunga_bank = $summary->bunga_bank_total;
            $detail->insentif = $summary->insentif_total;

            \Log::info('Detail bunga_bank set', [
                'detail_id' => $detail->id,
                'bunga_bank_set' => $detail->bunga_bank,
                'insentif_set' => $detail->insentif
            ]);
        });

        // ... rest of the method
    }

    // ============================ HPP & COSS CALCULATIONS ============================
    private function calculateHpp(&$quotation, $jumlahHc, $provisi, QuotationCalculationResult $result): void
    {
        $this->calculateFinancials($quotation, 'hpp', $result);
    }

    private function calculateCoss(&$quotation, $jumlahHc, $provisi, QuotationCalculationResult $result): void
    {
        $this->calculateFinancials($quotation, 'coss', $result);
    }

    private function calculateFinancials(&$quotation, $type, QuotationCalculationResult $result): void
    {
        $suffix = $type === 'coss' ? '_coss' : '';
        $model = $type === 'coss' ? QuotationDetailCoss::class : QuotationDetailHpp::class;

        // Calculate base totals
        $this->calculateBaseTotals($quotation, $suffix, $result);

        // Calculate management fee
        $this->calculateManagementFee($quotation, $suffix, $result);

        // Calculate taxes
        $this->calculateTaxes($quotation, $suffix, $model, $result);

        // Final calculations
        $this->finalizeCalculations($quotation, $suffix, $result);
    }

    private function calculateBaseTotals(&$quotation, $suffix, QuotationCalculationResult $result): void
    {
        $summary = $result->calculation_summary;

        $summary->{"total_sebelum_management_fee{$suffix}"} = $quotation->quotation_detail->sum('sub_total_personil' . $suffix);
        $summary->{"total_base_manpower{$suffix}"} = $quotation->quotation_detail->sum(fn($kbd) => $kbd->total_base_manpower * $kbd->jumlah_hc);
        $summary->{"upah_pokok{$suffix}"} = $quotation->quotation_detail->sum(fn($kbd) => $kbd->nominal_upah * $kbd->jumlah_hc);

        $summary->{"total_bpjs{$suffix}"} = $quotation->quotation_detail->sum(fn($kbd) => $kbd->bpjs_ketenagakerjaan * $kbd->jumlah_hc);

        // PERBAIKAN: Hindari double counting untuk BPJS Kesehatan
        $summary->{"total_bpjs_kesehatan{$suffix}"} = $quotation->quotation_detail->sum(
            fn($kbd) => $kbd->bpjs_kesehatan * $kbd->jumlah_hc
        );

        // ✅ PERBAIKAN: Hitung total potongan BPU dengan benar
        $summary->total_potongan_bpu = $quotation->quotation_detail->sum(
            fn($kbd) => ($kbd->penjamin_kesehatan === 'BPU') ? 16800 * $kbd->jumlah_hc : 0
        );

        $summary->potongan_bpu_per_orang = 16800; // Fixed amount per person

        // ✅ TAMBAHKAN: Hitung persentase BPJS untuk HPP dan COSS
        $totalHc = $quotation->quotation_detail->sum('jumlah_hc');

        if ($totalHc > 0) {
            $totalPersenBpjsKetenagakerjaan = 0;
            $totalPersenBpjsKesehatan = 0;
            $totalPersenBpjsJkk = 0;
            $totalPersenBpjsJkm = 0;
            $totalPersenBpjsJht = 0;
            $totalPersenBpjsJp = 0;
            $totalPersenBpjsKes = 0;

            foreach ($quotation->quotation_detail as $detail) {

                $persenBpjsKesehatanDetail = $detail->persen_bpjs_kesehatan ?? 0;
                $persenBpjsJkkDetail = $detail->persen_bpjs_jkk ?? 0;
                $persenBpjsJkmDetail = $detail->persen_bpjs_jkm ?? 0;
                $persenBpjsJhtDetail = $detail->persen_bpjs_jht ?? 0;
                $persenBpjsJpDetail = $detail->persen_bpjs_jp ?? 0;
                $persenBpjsKesDetail = $detail->persen_bpjs_kes ?? 0;
                $jumlahHcDetail = $detail->jumlah_hc;
                $persenBpjsKetenagakerjaanDetail = $detail->persen_bpjs_ketenagakerjaan ?? 0;

            }

            // Set untuk HPP (suffix kosong) dan COSS (suffix '_coss')
            if ($suffix === '') {
                // Untuk HPP
                $summary->persen_bpjs_ketenagakerjaan = $persenBpjsKetenagakerjaanDetail;
                $summary->persen_bpjs_kesehatan = $persenBpjsKesehatanDetail;
                $summary->persen_bpjs_jkk = $persenBpjsJkkDetail;
                $summary->persen_bpjs_jkm = $persenBpjsJkmDetail;
                $summary->persen_bpjs_jht = $persenBpjsJhtDetail;
                $summary->persen_bpjs_jp = $persenBpjsJpDetail;
                $summary->persen_bpjs_kes = $persenBpjsKesDetail;
            } else if ($suffix === '_coss') {
                // Untuk COSS (gunakan nilai yang sama karena perhitungannya sama)
                $summary->persen_bpjs_ketenagakerjaan_coss = $persenBpjsKetenagakerjaanDetail;
                $summary->persen_bpjs_kesehatan_coss = $persenBpjsKesehatanDetail;
                $summary->persen_bpjs_jkk_coss = $persenBpjsJkkDetail;
                $summary->persen_bpjs_jkm_coss = $persenBpjsJkmDetail;
                $summary->persen_bpjs_jht_coss = $persenBpjsJhtDetail;
                $summary->persen_bpjs_jp_coss = $persenBpjsJpDetail;
                $summary->persen_bpjs_kes_coss = $persenBpjsKesDetail;
            }
        }

        \Log::info("BPU totals calculated", [
            'quotation_id' => $quotation->id,
            'total_potongan_bpu' => $summary->total_potongan_bpu,
            'potongan_bpu_per_orang' => $summary->potongan_bpu_per_orang,
            'total_hc' => $quotation->quotation_detail->sum('jumlah_hc')
        ]);
    }
    private function calculateManagementFee(&$quotation, $suffix, QuotationCalculationResult $result): void
    {
        $summary = $result->calculation_summary;

        // GUNAKAN DATA DARI QUOTATION, BUKAN DARI WAGE
        $managementFeeCalculations = [
            1 => fn() => $summary->{"total_base_manpower{$suffix}"} * $quotation->persentase / 100,
            4 => fn() => $summary->{"total_sebelum_management_fee{$suffix}"} * $quotation->persentase / 100,
            5 => fn() => $summary->{"upah_pokok{$suffix}"} * $quotation->persentase / 100,
            6 => fn() => ($summary->{"upah_pokok{$suffix}"} + $summary->{"total_bpjs{$suffix}"}) * $quotation->persentase / 100,
            7 => fn() => ($summary->{"upah_pokok{$suffix}"} + $summary->{"total_bpjs{$suffix}"} + $summary->{"total_bpjs_kesehatan{$suffix}"}) * $quotation->persentase / 100,
            8 => fn() => ($summary->{"upah_pokok{$suffix}"} + $summary->{"total_bpjs_kesehatan{$suffix}"}) * $quotation->persentase / 100,
        ];

        $calculation = $managementFeeCalculations[$quotation->management_fee_id] ?? $managementFeeCalculations[1];
        $summary->{"nominal_management_fee{$suffix}"} = $calculation();
        $summary->{"grand_total_sebelum_pajak{$suffix}"} = $summary->{"total_sebelum_management_fee{$suffix}"} + $summary->{"nominal_management_fee{$suffix}"};
    }

    private function calculateTaxes(&$quotation, $suffix, $model, QuotationCalculationResult $result): void
    {
        $summary = $result->calculation_summary;

        // Calculate existing taxes
        $summary->{"ppn{$suffix}"} = 0;
        $summary->{"pph{$suffix}"} = 0;

        // $quotation->quotation_detail->each(function ($kbd) use (&$summary, $suffix, $model) {
        //     $detail = $model::where('quotation_detail_id', $kbd->id)->first();
        //     if ($detail) {
        //         $summary->{"ppn{$suffix}"} += $detail->ppn ?? 0;
        //         $summary->{"pph{$suffix}"} += $detail->pph ?? 0;
        //     }
        // });

        // PERBAIKAN: Validasi nilai PPN/PPH tidak wajar
        $grandTotal = $summary->{"grand_total_sebelum_pajak{$suffix}"};
        $maxReasonableTax = $grandTotal * 0.5; // Maksimal 50% dari grand total

        if ($summary->{"ppn{$suffix}"} > $maxReasonableTax) {
            \Log::warning("PPN value too large, resetting to zero", [
                'current_ppn' => $summary->{"ppn{$suffix}"},
                'max_reasonable' => $maxReasonableTax,
                'grand_total' => $grandTotal
            ]);
            $summary->{"ppn{$suffix}"} = 0;
        }

        if ($summary->{"pph{$suffix}"} > $maxReasonableTax) {
            \Log::warning("PPH value too large, resetting to zero", [
                'current_pph' => $summary->{"pph{$suffix}"},
                'max_reasonable' => $maxReasonableTax,
                'grand_total' => $grandTotal
            ]);
            $summary->{"pph{$suffix}"} = 0;
        }

        // Calculate taxes if not set
        if ($summary->{"ppn{$suffix}"} == 0 || $summary->{"pph{$suffix}"} == 0) {
            $this->calculateDefaultTaxes($quotation, $suffix, $result);
        }
    }
    private function calculateDefaultTaxes(&$quotation, $suffix, QuotationCalculationResult $result): void
    {
        $summary = $result->calculation_summary;

        $ppnPphDipotong = $quotation->ppn_pph_dipotong ?? "Management Fee";
        $isPpn = $quotation->is_ppn ?? "Tidak";

        $isPpnBoolean = false;
        if (is_numeric($isPpn)) {
            $isPpnBoolean = (int) $isPpn === 1;
        } else {
            $isPpnBoolean = $isPpn === "Ya";
        }

        // ✅ PERBAIKAN: Base amount untuk PPN/PPH dengan faktor 11/12
        $baseAmount = 0;
        if ($ppnPphDipotong == "Management Fee") {
            // Hanya management fee yang dikenakan pajak, dengan faktor 11/12
            $managementFee = $summary->{"nominal_management_fee{$suffix}"};
            $baseAmount = $managementFee * (11 / 12); // Faktor 11/12
        } else {
            // "Total Invoice" atau "Lainnya" - gunakan grand total sebelum pajak
            $baseAmount = $summary->{"grand_total_sebelum_pajak{$suffix}"};
        }

        $summary->{"dpp{$suffix}"} = $baseAmount;

        // ✅ PERBAIKAN: Hitung PPN (12% dari baseAmount setelah faktor 11/12)
        if ($summary->{"ppn{$suffix}"} == 0 && $isPpnBoolean) {
            $summary->{"ppn{$suffix}"} = round($baseAmount * 0.12, 2); // PPN 12%
        }

        // ✅ PERBAIKAN: Hitung PPH (2% dari baseAmount setelah faktor 11/12) dengan validasi
        if ($summary->{"pph{$suffix}"} == 0) {
            $calculatedPph = round($baseAmount * -0.02, 2); // PPH 2% (negatif karena potongan)

            // Validasi: PPH tidak boleh lebih besar dari 10% base amount
            $maxPph = abs($baseAmount * 0.1);
            if (abs($calculatedPph) > $maxPph) {
                \Log::warning("PPH calculation seems incorrect", [
                    'calculated_pph' => $calculatedPph,
                    'base_amount' => $baseAmount,
                    'max_reasonable' => $maxPph,
                    'suffix' => $suffix
                ]);
                $calculatedPph = -$maxPph;
            }

            $summary->{"pph{$suffix}"} = $calculatedPph;
        }

        \Log::info("Default taxes calculated with 11/12 factor", [
            'suffix' => $suffix,
            'ppn_pph_dipotong' => $ppnPphDipotong,
            'management_fee' => $summary->{"nominal_management_fee{$suffix}"} ?? 0,
            'base_amount_after_11_12' => $baseAmount,
            'is_ppn' => $isPpnBoolean,
            'ppn' => $summary->{"ppn{$suffix}"},
            'pph' => $summary->{"pph{$suffix}"},
            'dpp' => $summary->{"dpp{$suffix}"},
            'ppn_formula' => 'base_amount * 0.12',
            'pph_formula' => 'base_amount * -0.02'
        ]);
    }
    private function finalizeCalculations(&$quotation, $suffix, QuotationCalculationResult $result): void
    {
        $summary = $result->calculation_summary;

        $summary->{"total_invoice{$suffix}"} = $summary->{"grand_total_sebelum_pajak{$suffix}"} +
            $summary->{"ppn{$suffix}"} + $summary->{"pph{$suffix}"};
        $summary->{"pembulatan{$suffix}"} = ceil($summary->{"total_invoice{$suffix}"} / 1000) * 1000;

        $summary->{"margin{$suffix}"} = $summary->{"grand_total_sebelum_pajak{$suffix}"} - $summary->total_sebelum_management_fee;

        // FIX: Tambahkan pengecekan untuk menghindari division by zero
        if ($summary->{"grand_total_sebelum_pajak{$suffix}"} != 0) {
            $summary->{"gpm{$suffix}"} = $summary->{"margin{$suffix}"} / $summary->{"grand_total_sebelum_pajak{$suffix}"} * 100;
        } else {
            $summary->{"gpm{$suffix}"} = 0;
        }
    }

    // ============================ HELPER METHODS ============================
    private function calculateProvisi($durasiKerjasama)
    {
        if (!$durasiKerjasama)
            return 12;
        return !str_contains($durasiKerjasama, 'tahun')
            ? (int) str_replace(" bulan", "", $durasiKerjasama)
            : 12;
    }

    private function calculateUpahBpjs($nominalUpah, $umk, $ump)
    {
        if ($nominalUpah > $umk)
            return $nominalUpah;
        if ($nominalUpah == $umk)
            return $umk;
        if ($nominalUpah < $umk && $nominalUpah >= $ump)
            return $nominalUpah;
        return $ump;
    }

    private function getJkkPercentage($resiko)
    {
        $percentages = [
            "Sangat Rendah" => 0.24,
            "Rendah" => 0.54,
            "Sedang" => 0.89,
            "Tinggi" => 1.27,
            "Sangat Tinggi" => 1.74
        ];
        return $percentages[$resiko] ?? 0.24;
    }

    private function calculateLembur($detail, $quotation, $hpp, $wage)
    {
        if ($hpp && $hpp->lembur !== null) {
            return $hpp->lembur;
        }

        $lemburValue = $wage->lembur ?? "Tidak";

        if ($lemburValue != "Flat") {
            return 0;
        }

        $lemburDitagihkan = $wage->lembur_ditagihkan ?? "Tidak Ditagihkan";

        if ($lemburDitagihkan == "Ditagihkan Terpisah") {
            return 0;
        }

        $jenisBayar = $wage->jenis_bayar_lembur ?? null;
        $nominalLembur = $wage->nominal_lembur ?? 0;
        $jamPerBulan = $wage->jam_per_bulan_lembur ?? 0;

        $result = match ($jenisBayar) {
            "Per Jam" => $nominalLembur * $jamPerBulan,
            "Per Hari" => $nominalLembur * 25,
            default => $nominalLembur
        };

        return $result;
    }

    private function calculateItemTotal($model, $quotationId, $detailId, $provisi, $divider = 1, $special = null, $jumlahHc = 1)
    {
        $query = $model::where('quotation_id', $quotationId);
        if ($detailId)
            $query->where('quotation_detail_id', $detailId);

        return $query->get()->sum(function ($item) use ($provisi, $divider, $special, $jumlahHc) {
            if ($special === 'chemical') {
                return ((($item->jumlah * $item->harga) / $item->masa_pakai)) / $jumlahHc;
            }
            return (($item->harga * $item->jumlah) / $provisi) / $divider;
        });
    }

    private function applyBpjsOptOut($detail)
    {
        $optOuts = [
            'is_bpjs_jkk' => ['bpjs_jkk', 'persen_bpjs_jkk'],
            'is_bpjs_jkm' => ['bpjs_jkm', 'persen_bpjs_jkm'],
            'is_bpjs_jht' => ['bpjs_jht', 'persen_bpjs_jht'],
            'is_bpjs_jp' => ['bpjs_jp', 'persen_bpjs_jp']
        ];

        foreach ($optOuts as $optField => $targetFields) {
            // PERBAIKAN: Cek dengan lebih teliti nilai opt-out
            $isOptOut = false;

            if (isset($detail->{$optField})) {
                $optValue = $detail->{$optField};

                // Handle berbagai format nilai opt-out
                if ($optValue === "0" || $optValue === 0 || $optValue === false || $optValue === "false") {
                    $isOptOut = true;
                } elseif (is_string($optValue) && strtolower($optValue) === 'tidak') {
                    $isOptOut = true;
                }
            }

            if ($isOptOut) {
                $detail->{$targetFields[0]} = 0;
                $detail->{$targetFields[1]} = 0;

                \Log::info("BPJS opt-out applied", [
                    'detail_id' => $detail->id,
                    'field' => $optField,
                    'bpjs_field' => $targetFields[0],
                    'opt_value' => $detail->{$optField}
                ]);
            }
        }
    }

    private function updateQuotationBpjs($detail, $quotation)
    {
        // Hitung total BPJS ketenagakerjaan
        $detail->persen_bpjs_ketenagakerjaan = $detail->persen_bpjs_jkk + $detail->persen_bpjs_jkm +
            $detail->persen_bpjs_jht + $detail->persen_bpjs_jp;
        $detail->bpjs_ketenagakerjaan = $detail->bpjs_jkk + $detail->bpjs_jkm + $detail->bpjs_jht + $detail->bpjs_jp;

        // PERBAIKAN: Tambahkan logika sesuai permintaan
        if ($detail->penjamin_kesehatan == "BPJS" || $detail->penjamin_kesehatan == "BPJS Kesehatan") {
            $detail->bpjs_kesehatan = $detail->bpjs_kes;
            $detail->persen_bpjs_kesehatan = $detail->persen_bpjs_kes;
            // PERBAIKAN: Set persentase BPJS kesehatan di quotation jika diperlukan
            // $quotation->persen_bpjs_kesehatan = $detail->persen_bpjs_kesehatan;

            \Log::info("Using BPJS for health insurance", [
                'detail_id' => $detail->id,
                'bpjs_kes' => $detail->bpjs_kes,
                'persen_bpjs_kes' => $detail->persen_bpjs_kes
            ]);
        } else if ($detail->penjamin_kesehatan == "Asuransi Swasta" || $detail->penjamin_kesehatan == "Takaful") {
            // PERBAIKAN: Jika menggunakan asuransi swasta/takaful, gunakan nominal_takaful sebagai bpjs_kesehatan
            $detail->bpjs_kesehatan = $detail->nominal_takaful ?? 0;
            $detail->persen_bpjs_kesehatan = 0; // Karena menggunakan asuransi swasta, persentase BPJS = 0

            \Log::info("Using Takaful for health insurance", [
                'detail_id' => $detail->id,
                'nominal_takaful' => $detail->nominal_takaful,
                'bpjs_kesehatan' => $detail->bpjs_kesehatan
            ]);
        } else {
            $detail->bpjs_kesehatan = 0;
            $detail->persen_bpjs_kesehatan = 0;
            // $quotation->persen_bpjs_kesehatan = 0;

            \Log::info("No health insurance", [
                'detail_id' => $detail->id,
                'penjamin_kesehatan' => $detail->penjamin_kesehatan
            ]);
        }

        \Log::info("Quotation BPJS updated", [
            'detail_id' => $detail->id,
            'penjamin_kesehatan' => $detail->penjamin_kesehatan,
            'bpjs_ketenagakerjaan' => $detail->bpjs_ketenagakerjaan,
            'bpjs_kesehatan' => $detail->bpjs_kesehatan,
            'persen_bpjs_ketenagakerjaan' => $detail->persen_bpjs_ketenagakerjaan,
            'persen_bpjs_kesehatan' => $detail->persen_bpjs_kesehatan,
            'bpjs_kes' => $detail->bpjs_kes,
            'persen_bpjs_kes' => $detail->persen_bpjs_kes,
            'nominal_takaful' => $detail->nominal_takaful
        ]);
    }
    // ============================ BPU CALCULATION ============================

    /**
     * Calculate BPU (Biaya Penyelenggaraan Umum) - potongan 16.000 per karyawan
     */
    private function calculateBpu($detail, $quotation)
    {
        $bpuAmount = 0;

        if ($detail->penjamin_kesehatan === 'BPU') {
            $bpuAmount = 16800; // Fixed 16 ribu per karyawan
            \Log::info("BPU calculated", [
                'detail_id' => $detail->id,
                'bpu_amount' => $bpuAmount,
                'program_bpjs' => $quotation->program_bpjs
            ]);
        }

        return $bpuAmount;
    }
    // ============================ OTHER SERVICE METHODS ============================

    /**
     * Copy quotation data from source to target
     */
    public function copyQuotationData(Quotation $sourceQuotation, Quotation $targetQuotation, User $user)
    {
        DB::beginTransaction();
        try {
            // Copy quotation sites
            foreach ($sourceQuotation->quotationSites as $site) {
                $targetQuotation->quotationSites()->create([
                    'leads_id' => $targetQuotation->leads_id,
                    'nama_site' => $site->nama_site,
                    'provinsi_id' => $site->provinsi_id,
                    'provinsi' => $site->provinsi,
                    'kota_id' => $site->kota_id,
                    'kota' => $site->kota,
                    'ump' => $site->ump,
                    'umk' => $site->umk,
                    'nominal_upah' => $site->nominal_upah,
                    'penempatan' => $site->penempatan,
                    'created_by' => $user->full_name
                ]);
            }

            // Copy quotation details and related data
            foreach ($sourceQuotation->quotationDetails as $detail) {
                $newDetail = $targetQuotation->quotationDetails()->create([
                    'quotation_site_id' => $this->getMappedSiteId($targetQuotation, $detail->quotation_site_id),
                    'position_id' => $detail->position_id,
                    'position' => $detail->position,
                    'jumlah_hc' => $detail->jumlah_hc,
                    'nominal_upah' => $detail->nominal_upah,
                    'penjamin_kesehatan' => $detail->penjamin_kesehatan,
                    'is_bpjs_jkk' => $detail->is_bpjs_jkk,
                    'is_bpjs_jkm' => $detail->is_bpjs_jkm,
                    'is_bpjs_jht' => $detail->is_bpjs_jht,
                    'is_bpjs_jp' => $detail->is_bpjs_jp,
                    'nominal_takaful' => $detail->nominal_takaful,
                    'created_by' => $user->full_name
                ]);

                // Copy related data
                $this->copyDetailRelatedData($detail, $newDetail, $user);
            }

            // Copy other quotation data
            $this->copyOtherQuotationData($sourceQuotation, $targetQuotation, $user);

            DB::commit();
            return $targetQuotation;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function copyDetailRelatedData($sourceDetail, $targetDetail, $user)
    {
        // Copy tunjangan
        foreach ($sourceDetail->quotationDetailTunjangans as $tunjangan) {
            $targetDetail->quotationDetailTunjangans()->create([
                'nama_tunjangan' => $tunjangan->nama_tunjangan,
                'nominal' => $tunjangan->nominal,
                'created_by' => $user->full_name
            ]);
        }

        // Copy HPP
        if ($sourceDetail->quotationDetailHpp) {
            $targetDetail->quotationDetailHpp()->create(
                $this->getHppData($sourceDetail->quotationDetailHpp, $user)
            );
        }

        // Copy COSS
        if ($sourceDetail->quotationDetailCoss) {
            $targetDetail->quotationDetailCoss()->create(
                $this->getCossData($sourceDetail->quotationDetailCoss, $user)
            );
        }
    }

    private function copyOtherQuotationData($sourceQuotation, $targetQuotation, $user)
    {
        $relations = [
            'quotationAplikasis' => ['aplikasi_pendukung_id', 'aplikasi_pendukung', 'harga'],
            'quotationKaporlaps' => ['quotation_detail_id', 'barang_id', 'jumlah', 'harga', 'nama', 'jenis_barang_id', 'jenis_barang'],
            'quotationDevices' => ['barang_id', 'jumlah', 'harga', 'nama', 'jenis_barang_id', 'jenis_barang'],
            'quotationChemicals' => ['barang_id', 'jumlah', 'harga', 'nama', 'jenis_barang_id', 'jenis_barang', 'masa_pakai'],
            'quotationOhcs' => ['barang_id', 'jumlah', 'harga', 'nama', 'jenis_barang_id', 'jenis_barang'],
            'quotationTrainings' => ['training_id', 'nama'],
            'quotationKerjasamas' => ['perjanjian']
        ];

        foreach ($relations as $relation => $fields) {
            foreach ($sourceQuotation->$relation as $item) {
                $data = array_combine($fields, array_map(fn($field) => $item->$field, $fields));
                $data['created_by'] = $user->full_name;

                if ($relation === 'quotationKaporlaps') {
                    $data['quotation_detail_id'] = $this->getMappedDetailId($targetQuotation, $data['quotation_detail_id']);
                }

                $targetQuotation->$relation()->create($data);
            }
        }
    }

    private function getHppData($hpp, $user)
    {
        return [
            'gaji_pokok' => $hpp->gaji_pokok,
            'tunjangan_hari_raya' => $hpp->tunjangan_hari_raya,
            'kompensasi' => $hpp->kompensasi,
            'tunjangan_hari_libur_nasional' => $hpp->tunjangan_hari_libur_nasional,
            'lembur' => $hpp->lembur,
            'takaful' => $hpp->takaful,
            'bpjs_jkm' => $hpp->bpjs_jkm,
            'bpjs_jkk' => $hpp->bpjs_jkk,
            'bpjs_jht' => $hpp->bpjs_jht,
            'bpjs_jp' => $hpp->bpjs_jp,
            'bpjs_ks' => $hpp->bpjs_ks,
            'persen_bpjs_jkm' => $hpp->persen_bpjs_jkm,
            'persen_bpjs_jkk' => $hpp->persen_bpjs_jkk,
            'persen_bpjs_jht' => $hpp->persen_bpjs_jht,
            'persen_bpjs_jp' => $hpp->persen_bpjs_jp,
            'persen_bpjs_ks' => $hpp->persen_bpjs_ks,
            'provisi_seragam' => $hpp->provisi_seragam,
            'provisi_peralatan' => $hpp->provisi_peralatan,
            'provisi_chemical' => $hpp->provisi_chemical,
            'provisi_ohc' => $hpp->provisi_ohc,
            'bunga_bank' => $hpp->bunga_bank,
            'insentif' => $hpp->insentif,
            'ppn' => $hpp->ppn,
            'pph' => $hpp->pph,
            'created_by' => $user->full_name
        ];
    }

    private function getCossData($coss, $user)
    {
        return [
            'provisi_seragam' => $coss->provisi_seragam,
            'provisi_peralatan' => $coss->provisi_peralatan,
            'provisi_chemical' => $coss->provisi_chemical,
            'provisi_ohc' => $coss->provisi_ohc,
            'ppn' => $coss->ppn,
            'pph' => $coss->pph,
            'created_by' => $user->full_name
        ];
    }

    /**
     * Resubmit quotation dengan membuat quotation baru
     */
    public function resubmitQuotation(Quotation $originalQuotation, string $alasan, User $user)
    {
        DB::beginTransaction();
        try {
            $newNomor = $this->generateResubmitNomor($originalQuotation->nomor);

            $newQuotation = Quotation::create([
                'nomor' => $newNomor,
                'tgl_quotation' => Carbon::now()->toDateString(),
                'leads_id' => $originalQuotation->leads_id,
                'nama_perusahaan' => $originalQuotation->nama_perusahaan,
                'kebutuhan_id' => $originalQuotation->kebutuhan_id,
                'kebutuhan' => $originalQuotation->kebutuhan,
                'company_id' => $originalQuotation->company_id,
                'company' => $originalQuotation->company,
                'jumlah_site' => $originalQuotation->jumlah_site,
                'step' => 1,
                'status_quotation_id' => 1,
                'is_aktif' => 1,
                'alasan_resubmit' => $alasan,
                'quotation_sebelumnya_id' => $originalQuotation->id,
                'created_by' => $user->full_name
            ]);

            $this->copyQuotationData($originalQuotation, $newQuotation, $user);

            $originalQuotation->update([
                'is_aktif' => 0,
                'updated_by' => $user->full_name
            ]);

            DB::commit();
            return $newQuotation;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Submit quotation untuk approval dengan logika role-based yang diperbaiki
     */
    public function submitForApproval(Quotation $quotation, array $data, User $user)
    {
        DB::beginTransaction();
        try {
            $currentDateTime = Carbon::now()->toDateTimeString();

            // Validasi data
            $approve = $data['is_approved'] ?? false;
            $notes = $data['notes'] ?? null;

            // Pastikan approve adalah boolean
            if (is_string($approve)) {
                $approve = filter_var($approve, FILTER_VALIDATE_BOOLEAN);
            }

            $tingkat = 0;
            $isApproved = (bool) $approve;

            // Role-based approval logic
            if (in_array($user->role_id, [96])) {
                // ====== LEVEL 1 APPROVAL (OT1) ======
                $tingkat = 1;
                $updateData = [
                    'updated_at' => $currentDateTime,
                    'updated_by' => $user->full_name
                ];

                if ($isApproved) {
                    // ✅ DISETUJUI - Update OT1
                    $updateData['ot1'] = $user->full_name;

                    // Cek apakah perlu approval level 2
                    $needsLevel2 = (
                        $quotation->top == "Lebih Dari 7 Hari" 
                        // ($quotation->jenis_kontrak == "Reguler" && $quotation->kompensasi == "Tidak Ada")
                    );

                    if ($needsLevel2) {
                        // Butuh approval level 2
                        $updateData['status_quotation_id'] = 2; // Pending Level 2 Approval
                        $updateData['is_aktif'] = 0;

                        \Log::info("Level 1 approved, waiting for Level 2", [
                            'quotation_id' => $quotation->id,
                            'nomor' => $quotation->nomor
                        ]);
                    } else {
                        // Langsung approved (tidak butuh level 2)
                        $updateData['status_quotation_id'] = 3; // Approved
                        $updateData['is_aktif'] = 1;

                        \Log::info("Level 1 approved, auto-approved (no Level 2 needed)", [
                            'quotation_id' => $quotation->id,
                            'nomor' => $quotation->nomor
                        ]);
                    }
                } else {
                    // ❌ DITOLAK
                    $updateData['status_quotation_id'] = 8; // Rejected
                    $updateData['is_aktif'] = 0;
                    $updateData['ot1'] = "Ditolak oleh " . $user->full_name;

                    \Log::info("Level 1 rejected", [
                        'quotation_id' => $quotation->id,
                        'nomor' => $quotation->nomor,
                        'reason' => $notes
                    ]);
                }

            } elseif (in_array($user->role_id, [97])) {
                // ====== LEVEL 2 APPROVAL (OT2) ======
                $tingkat = 2;
                $updateData = [
                    'updated_at' => $currentDateTime,
                    'updated_by' => $user->full_name
                ];

                // Validasi: pastikan OT1 sudah approve
                if (empty($quotation->ot1)) {
                    throw new \Exception('Quotation belum disetujui di Level 1');
                }

                if ($isApproved) {
                    // ✅ DISETUJUI
                    $updateData['ot2'] = $user->full_name;
                    $updateData['status_quotation_id'] = 3; // Approved
                    $updateData['is_aktif'] = 1;

                    \Log::info("Level 2 approved, quotation fully approved", [
                        'quotation_id' => $quotation->id,
                        'nomor' => $quotation->nomor
                    ]);
                } else {
                    // ❌ DITOLAK
                    $updateData['status_quotation_id'] = 8; // Rejected
                    $updateData['is_aktif'] = 0;
                    $updateData['ot1'] = "Ditolak oleh " . $user->full_name;
                    $updateData['ot2'] = "Ditolak oleh " . $user->full_name;

                    \Log::info("Level 2 rejected", [
                        'quotation_id' => $quotation->id,
                        'nomor' => $quotation->nomor,
                        'reason' => $notes
                    ]);
                }

            } else {
                throw new \Exception('User tidak memiliki akses untuk approval. Role ID: ' . $user->role_id);
            }

            // Update quotation
            $quotation->update($updateData);

            \Log::info("Quotation updated", [
                'quotation_id' => $quotation->id,
                'status_quotation_id' => $updateData['status_quotation_id'],
                'is_aktif' => $updateData['is_aktif'],
                'ot1' => $updateData['ot1'] ?? null,
                'ot2' => $updateData['ot2'] ?? null
            ]);

            // Log approval ke tabel log_approval
            LogApproval::create([
                'tabel' => 'sl_quotation', // ✅ Sesuaikan dengan nama tabel
                'doc_id' => $quotation->id,
                'tingkat' => $tingkat,
                'is_approve' => $isApproved,
                'note' => $notes,
                'user_id' => $user->id,
                'approval_date' => $currentDateTime,
                'created_at' => $currentDateTime,
                'created_by' => $user->full_name
            ]);

            \Log::info("Log approval created", [
                'quotation_id' => $quotation->id,
                'tingkat' => $tingkat,
                'is_approve' => $isApproved
            ]);

            // Send notification ke sales yang membuat quotation
            if ($quotation->leads && $quotation->leads->timSalesDetail) {
                $timSalesDetail = $quotation->leads->timSalesDetail;

                LogNotification::createQuotationApprovalNotification(
                    $timSalesDetail->user_id,
                    $quotation->id,
                    $quotation->nomor,
                    $user->full_name,
                    $isApproved,
                    $notes
                );

                \Log::info("Notification sent", [
                    'quotation_id' => $quotation->id,
                    'user_id' => $timSalesDetail->user_id
                ]);
            }

            DB::commit();

            // Reload quotation dengan status terbaru
            $quotation->refresh();
            $quotation->load('statusQuotation');

            return $quotation;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error in submitForApproval", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'quotation_id' => $quotation->id ?? null,
                'user_id' => $user->id ?? null
            ]);
            throw $e;
        }
    }
    // ============================ HELPER METHODS (tambahan jika diperlukan) ============================



    /**
     * Generate konten perjanjian kerjasama
     */
    public function generateKerjasamaContent(Quotation $quotation)
    {
        $kebutuhanPerjanjian = "<b>" . $quotation->kebutuhan . "</b>";

        // Get salary rule data
        $salaryRuleQ = SalaryRule::select('cutoff', 'pengiriman_invoice', 'rilis_payroll')
            ->whereNull('deleted_at')
            ->where('id', $quotation->salary_rule_id)
            ->first();


        // Build salary schedule table
        $tableSalary = '<table class="table table-bordered" style="width:100%">
                      <thead>
                        <tr>
                          <th class="text-center"><b>No.</b></th>
                          <th class="text-center"><b>Schedule Plan</b></th>
                          <th class="text-center"><b>Periode</b></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td class="text-center">1</td>
                          <td>Cut Off</td>
                          <td>' . $salaryRuleQ->cutoff . '</td>
                        </tr>
                        <tr>
                          <td class="text-center">2</td>
                          <td>Pengiriman <i>Invoice</i></td>
                          <td>' . $salaryRuleQ->pengiriman_invoice . '</td>
                        </tr>
                        <tr>
                          <td class="text-center">3</td>
                          <td>Rilis <i>Payroll</i> / Gaji</td>
                          <td>' . $salaryRuleQ->rilis_payroll . '</td>
                        </tr>
                      </tbody>
                    </table>';

        // Build kunjungan operasional text
        $kunjunganOperasional = "";
        if ($quotation->kunjungan_operasional != null) {
            $kunjunganParts = explode(" ", $quotation->kunjungan_operasional);
            if (count($kunjunganParts) >= 2) {
                $kunjunganOperasional = $kunjunganParts[0] . " kali dalam 1 " . $kunjunganParts[1];
            }
        }

        // Get aplikasi pendukung
        $appPendukung = QuotationAplikasi::select('aplikasi_pendukung')
            ->whereNull('deleted_at')
            ->where('quotation_id', $quotation->id)
            ->get();

        $sAppPendukung = "<b>";
        foreach ($appPendukung as $kduk => $dukung) {
            if ($kduk != 0) {
                $sAppPendukung .= ", ";
            }
            $sAppPendukung .= $dukung->aplikasi_pendukung;
        }
        $sAppPendukung .= "</b>";

        // Build perjanjian array
        $perjanjian = [];

        $perjanjian[] = "Penawaran harga ini berlaku 30 hari sejak tanggal diterbitkan.";

        $perjanjian[] = "Akan dilakukan <i>survey</i> area untuk kebutuhan " . $kebutuhanPerjanjian . " sebagai tahapan <i>assesment</i> area untuk memastikan efektifitas pekerjaan.";

        $perjanjian[] = "Komponen dan nilai dalam penawaran harga ini berdasarkan kesepakatan para pihak dalam pengajuan harga awal, apabila ada perubahan, pengurangan maupun penambahan pada komponen dan nilai pada penawaran, maka <b>para pihak</b> sepakat akan melanjutkan ke tahap negosiasi selanjutnya.";

        $perjanjian[] = "Skema cut-off, pengiriman <i>invoice</i>, pembayaran <i>invoice</i> dan penggajian adalah <b>TOP/talangan</b> maksimal 30 hari kalender dengan skema sebagai berikut: <br>" . $tableSalary . "<i><br>*Rilis gaji adalah talangan.<br>*Maksimal pembayaran invoice 30 hari kalender setelah invoice</i>";

        $perjanjian[] = "Kunjungan tim operasional " . $kunjunganOperasional . ", untuk monitoring dan supervisi dengan karyawan dan wajib bertemu dengan pic <b>Pihak Pertama</b> untuk koordinasi.";

        $perjanjian[] = "Tim operasional bersifat <i>on call</i> apabila terjadi <i>case</i> atau insiden yang terjadi yang mengharuskan untuk datang ke lokasi kerja Pihak Pertama.";

        $perjanjian[] = "Pemenuhan kandidat dilakukan dengan 2 tahap <i>screening</i> :<br>a. Tahap ke -1 : dilakukan oleh tim rekrutmen <b>Pihak Kedua</b> untuk memastikan bahwa kandidat sudah sesuai dengan kualifikasi <b>dari Pihak Pertama</b>.<br>b. Tahap ke -2 : dilakukan oleh user <b>Pihak Pertama</b>, dan dijadwalkan setelah adanya <i>report</i> hasil <i>screening</i> dari <b>Pihak Kedua</b>.";

        $perjanjian[] = "<i>Support</i> aplikasi digital :" . $sAppPendukung . ".";

        return $perjanjian;
    }


    // ============================ HELPER METHODS ============================

    /**
     * Get mapped site ID for copying
     */
    private function getMappedSiteId(Quotation $targetQuotation, $originalSiteId)
    {
        $targetSites = $targetQuotation->quotationSites;
        return $targetSites->first()->id; // Simplifikasi, asumsi urutan sama
    }

    /**
     * Get mapped detail ID for copying
     */
    private function getMappedDetailId(Quotation $targetQuotation, $originalDetailId)
    {
        $targetDetails = $targetQuotation->quotationDetails;
        return $targetDetails->first()->id; // Simplifikasi, asumsi urutan sama
    }

    /**
     * Generate nomor untuk resubmit
     */
    public function generateResubmitNomor($originalNomor)
    {
        $base = explode('-', $originalNomor)[0];
        $now = Carbon::now();
        $month = $now->month < 10 ? "0" . $now->month : $now->month;

        $count = Quotation::where('nomor', 'like', $base . $month . $now->year . "-%")
            ->where('nomor', 'like', '%/RESUB/%')
            ->count();

        $urutan = sprintf("%05d", $count + 1);
        return $base . $month . $now->year . "-" . $urutan . "/RESUB/" . ($count + 1);
    }

    /**
     * Get site locations for kerjasama content
     */
    private function getSiteLocations(Quotation $quotation)
    {
        $locations = [];
        foreach ($quotation->quotationSites as $site) {
            $locations[] = $site->nama_site . " - " . $site->kota;
        }
        return implode(", ", $locations);
    }

    /**
     * Method untuk QuotationStepService agar bisa mendapatkan calculated values
     */
    public function getCalculatedValues(Quotation $quotation): QuotationCalculationResult
    {
        return $this->calculateQuotation($quotation);
    }
}