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
    LeadsKebutuhan,
    CustomerActivity,
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

        // **PERBAIKAN: Recalculate HPP dan COSS setelah bunga bank di-update**
        $this->calculateHpp($quotation, $jumlahHc, $quotation->provisi, $result);
        $this->calculateCoss($quotation, $jumlahHc, $quotation->provisi, $result);
    }

    // ============================ DETAIL PROCESSING ============================
    private function processAllDetails($quotation, $daftarTunjangan, $jumlahHc, QuotationCalculationResult $result): void
    {
        $quotation->quotation_detail->each(function ($detail) use ($quotation, $daftarTunjangan, $jumlahHc, $result) {
            try {
                $this->processSingleDetail($detail, $quotation, $daftarTunjangan, $jumlahHc, $result);
            } catch (\Exception $e) {
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
            throw $e;
        }
    }

    private function initializeDetail($detail, $hpp, $site, $wage)
    {

        // PERBAIKAN: Jumlah HC untuk HPP ambil dari HPP (Step 11 input)
        // Tapi untuk detail object, tetap gunakan yang dari detail (untuk COSS)
        $detail->jumlah_hc_original = $detail->jumlah_hc; // Simpan nilai asli untuk COSS
        $detail->jumlah_hc_hpp = $hpp && $hpp->jumlah_hc !== null ? (int) $hpp->jumlah_hc : $detail->jumlah_hc;

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

            // Di method calculateDetailComponents, ubah pemanggilan:
            $this->calculateExtras($detail, $quotation, $hpp, $coss, $wage);

            // Calculate items
            $this->calculateAllItems($detail, $quotation, $jumlahHc, $hpp, $coss);

            $this->calculateFinalTotals($detail, $quotation, $totalTunjangan, $hpp, $coss);

            // Simpan data ke DTO
            $this->populateDetailCalculation($detail, $quotation, $detailCalculation);

        } catch (\Exception $e) {
            throw $e;
        }
    }
    private function populateDetailCalculation($detail, $quotation, DetailCalculation $detailCalculation): void
    {
        $potonganBpu = 0;
        if ($detail->penjamin_kesehatan === 'BPU') {
            $potonganBpu = 16800;
        }

        // **PERBAIKAN: Gunakan jumlah_hc_hpp untuk HPP dan jumlah_hc_original untuk COSS**
        $detailCalculation->hpp_data = [
            'quotation_detail_id' => $detail->id,
            'quotation_id' => $quotation->id,
            'leads_id' => $quotation->leads_id,
            'position_id' => $detail->position_id,
            'jumlah_hc' => $detail->jumlah_hc_hpp, // ← GUNAKAN jumlah_hc_hpp
            'gaji_pokok' => $detail->nominal_upah,
            'total_tunjangan' => $detail->total_tunjangan ?? 0,
            'tunjangan_hari_raya' => $detail->tunjangan_hari_raya_hpp ?? 0,
            'kompensasi' => $detail->kompensasi_hpp ?? 0,
            'tunjangan_hari_libur_nasional' => $detail->tunjangan_holiday ?? 0,
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
            'potongan_bpu' => $potonganBpu ?? 0,
            'total_biaya_per_personil' => $detail->total_personil ?? 0,
            'total_biaya_all_personil' => $detail->sub_total_personil ?? 0,
        ];

        $detailCalculation->coss_data = [
            'quotation_detail_id' => $detail->id,
            'quotation_id' => $quotation->id,
            'leads_id' => $quotation->leads_id,
            'position_id' => $detail->position_id,
            'jumlah_hc' => $detail->jumlah_hc_original, // ← GUNAKAN jumlah_hc_original
            'gaji_pokok' => $detail->nominal_upah,
            'total_tunjangan' => $detail->total_tunjangan ?? 0,
            'total_base_manpower' => $detail->total_base_manpower_coss ?? 0,
            'tunjangan_hari_raya' => $detail->tunjangan_hari_raya_coss ?? 0,
            'kompensasi' => $detail->kompensasi_coss ?? 0,
            'tunjangan_hari_libur_nasional' => $detail->tunjangan_holiday ?? 0,
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
            'potongan_bpu' => $potonganBpu ?? 0,
        ];

    }

    // ============================ COMPONENT CALCULATIONS ============================
    private function calculateTunjangan($detail, $daftarTunjangan)
    {
        $totalTunjangan = 0;
        $totalTunjanganCoss = 0;
        foreach ($daftarTunjangan as $tunjangan) {
            $dtTunjangan = QuotationDetailTunjangan::where('quotation_detail_id', $detail->id)
                ->where('nama_tunjangan', $tunjangan->nama)->first();

            // ============================================
            // PERBAIKAN: Konversi ke FLOAT dengan benar
            // ============================================
            $value = 0.0;
            $valuecoss = 0.0;

            if ($dtTunjangan) {
                // Pastikan nilai numeric dan konversi ke float
                $value = is_numeric($dtTunjangan->nominal) ? (float) $dtTunjangan->nominal : 0.0;
                $valuecoss = is_numeric($dtTunjangan->nominal_coss) ? (float) $dtTunjangan->nominal_coss : 0.0;
            }

            $detail->{$tunjangan->nama} = $value;
            $totalTunjangan += $value;
            $totalTunjanganCoss += $valuecoss;
        }
        $detail->total_tunjangan = $totalTunjangan;
        $detail->total_tunjangan_coss = $totalTunjanganCoss;
        return [
            'total' => $totalTunjangan,
            'total_coss' => $totalTunjanganCoss
        ];
    }
    private function calculateBpjs($detail, $quotation, $hpp)
    {
        // Jika BPU, langsung return dengan setting ke 0
        if ($detail->penjamin_kesehatan === 'BPU') {
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
            // $detail->nominal_upah = $detail->nominal_upah - 16800;

            $this->updateQuotationBpjs($detail, $quotation);
            return;
        }

        // PERBAIKAN: Gunakan kondisi yang lebih akurat
        $programBpjs = $quotation->program_bpjs ?? '';
        $isBpjsProgram = (stripos($programBpjs, 'BPJS') !== false)
            || ($programBpjs == 'Ya')
            || ($programBpjs == '1')
            || ($programBpjs == true);

        if ($isBpjsProgram) {

            $upahBpjs = $this->calculateUpahBpjs(
                $detail->nominal_upah,
                $detail->quotationSite->umk ?? 0,
                $detail->quotationSite->ump ?? 0
            );
            // Konfigurasi BPJS dengan nilai default
            $umk = $detail->quotationSite->umk ?? 0;
            $nominalUpah = $detail->nominal_upah;

            // Tentukan base untuk BPJS Kesehatan
            $baseKes = ($nominalUpah > $umk) ? $nominalUpah : $umk;

            $bpjsConfig = [
                'jkk' => ['field' => 'bpjs_jkk', 'percent' => 'persen_bpjs_jkk', 'default' => $this->getJkkPercentage($quotation->resiko)],
                'jkm' => ['field' => 'bpjs_jkm', 'percent' => 'persen_bpjs_jkm', 'default' => 0.30],
                'jht' => ['field' => 'bpjs_jht', 'percent' => 'persen_bpjs_jht', 'default' => 3.70],
                'jp' => ['field' => 'bpjs_jp', 'percent' => 'persen_bpjs_jp', 'default' => 2.00],
                'kes' => ['field' => 'bpjs_kes', 'percent' => 'persen_bpjs_kes', 'default' => 4.00, 'base' => $baseKes]
            ];

            foreach ($bpjsConfig as $key => $config) {
                // Tentukan persentase dengan logika PRIORITAS YANG DIPERBAIKI

                $persentase = 0;
                $base = $config['base'] ?? $upahBpjs;

                // **PERBAIKAN KRITIS: JIKA NILAI DARI HPP ADALAH 0, GUNAKAN DEFAULT**

                // 1. Cek apakah ada nilai di detail object (langsung dari form)
                if (isset($detail->{$config['percent']}) && $detail->{$config['percent']} !== null) {
                    $persentase = (float) $detail->{$config['percent']};
                }
                // 2. Cek di HPP table (Step 11) - ABGAIKAN JIKA 0
                else if ($hpp && isset($hpp->{$config['percent']}) && $hpp->{$config['percent']} !== null) {
                    $hppValue = (float) $hpp->{$config['percent']};

                    // **PERUBAHAN PENTING: Jika nilai HPP adalah 0, gunakan default**
                    if ($hppValue == 0) {
                        $persentase = $config['default'];
                    } else {
                        $persentase = $hppValue;

                    }
                }
                // 3. Gunakan default jika semua sumber null
                else {
                    $persentase = $config['default'];

                }

                // **PERHITUNGAN NOMINAL**

                // Cek apakah BPJS ini di-opt-out
                $optOutField = 'is_bpjs_' . $key;
                $isOptOut = false;

                if (isset($detail->{$optOutField})) {
                    $optValue = $detail->{$optOutField};

                    // Jika nilai adalah string "tidak" atau boolean/numeric false/0
                    if (
                        $optValue === "0" || $optValue === 0 || $optValue === false ||
                        (is_string($optValue) && strtolower(trim($optValue)) === 'tidak')
                    ) {
                        $isOptOut = true;
                    }
                }

                if ($isOptOut) {
                    // Jika di-opt-out, set ke 0
                    $detail->{$config['field']} = 0;
                    $detail->{$config['percent']} = 0;
                } else {
                    // Jika tidak di-opt-out, hitung normal
                    if ($key === 'kes' && in_array($detail->penjamin_kesehatan, ["Asuransi Swasta", "Takaful"])) {
                        // Gunakan takaful untuk kesehatan
                        $detail->{$config['field']} = $detail->nominal_takaful ?? 0;
                        $detail->{$config['percent']} = 0;
                    } else if ($key === 'kes' && $hpp && $hpp->bpjs_ks !== null && $hpp->bpjs_ks > 0) {
                        // **PERBAIKAN: Gunakan nilai dari HPP jika sudah diedit**
                        $detail->{$config['field']} = $hpp->bpjs_ks;
                        $detail->{$config['percent']} = $persentase;
                    } else {
                        // Hitung berdasarkan persentase
                        $detail->{$config['field']} = $base * $persentase / 100;
                        $detail->{$config['percent']} = $persentase;

                    }
                }
            }

            // Apply BPJS opt-out (sebagai backup)
            $this->applyBpjsOptOut($detail);
            $this->updateQuotationBpjs($detail, $quotation);

        } else {
            \Log::warning("BPJS program is NOT ACTIVE", ['program' => $programBpjs]);
            // Set semua BPJS ke 0
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

            $this->updateQuotationBpjs($detail, $quotation);
        }
    }
    // PERBAIKAN 1: Pastikan nilai THR dihitung dengan bena
    private function calculateExtras($detail, $quotation, $hpp, $coss, $wage): void
    {
        try {
            // 1. TUNJANGAN HARI RAYA (THR) - Prioritaskan dari HPP (step 11)
            $tunjanganHariRayaHpp = $hpp ? (float) ($hpp->tunjangan_hari_raya ?? 0) : 0;
            $tunjanganHariRayaCoss = $coss ? (float) ($coss->tunjangan_hari_raya ?? 0) : 0;

            // Jika HPP null atau 0, coba ambil dari wage (step 4)
            if ($tunjanganHariRayaHpp == 0 && $wage && isset($wage->thr)) {
                $thrWageValue = strtolower(trim($wage->thr ?? 'Tidak Ada'));
                if (in_array($thrWageValue, ['diprovisikan'])) {
                    // Hitung THR berdasarkan upah bulanan (1/12 dari gaji)
                    $tunjanganHariRayaHpp = ($detail->nominal_upah ?? 0) / 12;
                    $tunjanganHariRayaCoss = ($detail->nominal_upah ?? 0) / 12;

                } else if ($thrWageValue == 'ditagihkan' || $thrWageValue == 'diberikan langsung' || $thrWageValue == 'tidak ada') {
                    $tunjanganHariRayaHpp = 0;
                    $tunjanganHariRayaCoss = 0;
                }
            }

            // 2. KOMPENSASI - Prioritaskan dari HPP (step 11)
            $kompensasiHpp = $hpp ? (float) ($hpp->kompensasi ?? 0) : 0;
            $kompensasiCoss = $coss ? (float) ($coss->kompensasi ?? 0) : 0;

            // Jika HPP null atau 0, coba ambil dari wage (step 4)
            if ($kompensasiHpp == 0 && $wage && isset($wage->kompensasi)) {
                $kompensasiWageValue = strtolower(trim($wage->kompensasi ?? 'Tidak Ada'));
                if (in_array($kompensasiWageValue, ['diprovisikan'])) {
                    // Tentukan nilai kompensasi default (10% dari gaji)
                    $kompensasiDefault = ($detail->nominal_upah ?? 0) / 12;
                    $kompensasiHpp = $kompensasiDefault;
                    $kompensasiCoss = $kompensasiDefault;
                } else if ($kompensasiWageValue == 'ditagihkan' || $kompensasiWageValue == 'tidak ada') {
                    $kompensasiHpp = 0;
                    $kompensasiCoss = 0;
                }
            }

            // 3. TUNJANGAN HOLIDAY (LIBUR NASIONAL) - UTAMAKAN WAGE (step 4) DAN JANGAN OVERRIDE DENGAN HPP
            $tunjanganHoliday = 0;

            // **PERBAIKAN KRITIKAL: Ambil dari wage terlebih dahulu, JANGAN override dengan HPP**
            if ($wage && isset($wage->tunjangan_holiday)) {
                $tunjanganHolidayValue = strtolower(trim($wage->tunjangan_holiday ?? 'Tidak Ada'));

                if (str_contains($tunjanganHolidayValue, 'flat')) {
                    $tunjanganHoliday = $this->calculateTunjanganHolidayFromWage($wage);
                } else if ($tunjanganHolidayValue == 'normatif' || $tunjanganHolidayValue == 'tidak ada') {
                    $tunjanganHoliday = 0;
                }
            } else {
                // Fallback ke HPP hanya jika tidak ada data wage sama sekali
                $tunjanganHoliday = $hpp ? (float) ($hpp->tunjangan_hari_libur_nasional ?? 0) : 0;
            }

            // 4. LEMBUR - UTAMAKAN WAGE (step 4) DAN JANGAN OVERRIDE DENGAN HPP
            $lembur = 0;

            // **PERBAIKAN KRITIKAL: Ambil dari wage terlebih dahulu, JANGAN override dengan HPP**
            if ($wage && isset($wage->lembur)) {
                $lemburValue = strtolower(trim($wage->lembur ?? 'Tidak Ada'));
                $lemburditagihkanValue = strtolower(trim($wage->lembur_ditagihkan ?? null));

                if (str_contains($lemburValue, 'flat')) {
                    $lembur = $this->calculateLemburFromWage($wage);
                } else if ($lemburditagihkanValue == 'ditagihkan terpisah' || $lemburValue == 'tidak ada') {
                    $lembur = 0;
                }
            } else {
                // Fallback ke HPP hanya jika tidak ada data wage sama sekali
                $lembur = $hpp ? (float) ($hpp->lembur ?? 0) : 0;
            }

            // 5. INSENTIF - Ambil dari HPP (step 11)
            $insentifHpp = $hpp ? (float) ($hpp->insentif ?? 0) : 0;

            // Assign nilai ke detail object
            $detail->tunjangan_hari_raya_hpp = round($tunjanganHariRayaHpp, 2);
            $detail->tunjangan_hari_raya_coss = round($tunjanganHariRayaCoss, 2);
            $detail->kompensasi_hpp = round($kompensasiHpp, 2);
            $detail->kompensasi_coss = round($kompensasiCoss, 2);
            $detail->tunjangan_holiday = round($tunjanganHoliday, 2);
            $detail->lembur = round($lembur, 2);
            $detail->insentif_hpp = round($insentifHpp, 2);
        } catch (\Exception $e) {
            \Log::error("Error in calculateExtras for detail {$detail->id}: " . $e->getMessage());
            throw $e;
        }
    }
    /**
     * Hitung tunjangan holiday dari data wage (step 4)
     */
    private function calculateTunjanganHolidayFromWage($wage)
    {
        if (!$wage) {
            return 0;
        }

        $tunjanganHolidayValue = $wage->tunjangan_holiday ?? "Tidak";
        $tunjanganHolidayNormalized = strtolower(trim($tunjanganHolidayValue));

        if (!str_contains($tunjanganHolidayNormalized, 'flat')) {
            return 0;
        }

        $jenisBayar = $wage->jenis_bayar_tunjangan_holiday ?? null;
        $nominalTunjanganHoliday = (float) ($wage->nominal_tunjangan_holiday ?? 0);

        // Bersihkan nominal jika string
        if (is_string($nominalTunjanganHoliday)) {
            $nominalTunjanganHoliday = (float) str_replace(['.', ','], ['', '.'], $nominalTunjanganHoliday);
        }

        $result = match ($jenisBayar) {
            // "Per Jam" => $nominalTunjanganHoliday * 75, // 75 jam per bulan
            // "Per Hari" => $nominalTunjanganHoliday * 14, // 14 hari libur nasional
            "Per Bulan" => $nominalTunjanganHoliday,
            default => $nominalTunjanganHoliday
        };

        return round($result, 2);
    }

    /**
     * Hitung lembur dari data wage (step 4)
     */
    private function calculateLemburFromWage($wage)
    {
        if (!$wage) {
            return 0;
        }

        $lemburValue = $wage->lembur ?? "Tidak";
        $lemburNormalized = strtolower(trim($lemburValue));

        if (!str_contains($lemburNormalized, 'flat')) {
            return 0;
        }

        $lemburDitagihkan = $wage->lembur_ditagihkan ?? "Tidak Ditagihkan";

        // PERBAIKAN: Cek apakah "Ditagihkan Terpisah" dengan berbagai format
        if (str_contains(strtolower($lemburDitagihkan), 'terpisah')) {
            return 0;
        }

        $jenisBayar = $wage->jenis_bayar_lembur ?? null;
        $nominalLembur = (float) ($wage->nominal_lembur ?? 0);
        $jamPerBulan = (float) ($wage->jam_per_bulan_lembur ?? 0);

        // Bersihkan nominal jika string
        if (is_string($nominalLembur)) {
            $nominalLembur = (float) str_replace(['.', ','], ['', '.'], $nominalLembur);
        }

        $result = match ($jenisBayar) {
            // "Per Jam" => $nominalLembur * $jamPerBulan,
            // "Per Hari" => $nominalLembur * 25, // 25 hari kerja
            "Per Bulan" => $nominalLembur,
            default => $nominalLembur
        };
        return round($result, 2);
    }

    // ============================ ITEM CALCULATIONS ============================
    private function calculateAllItems($detail, $quotation, $totalJumlahHc, $hpp, $coss)
    {
        // **PERBAIKAN: Hitung total HC per site SEKALI di awal, bukan per detail**
        static $siteTotalsCalculated = false;
        static $siteHcHpp = [];
        static $siteHcCoss = [];

        // Hitung total HC per site hanya sekali untuk quotation ini
        if (!$siteTotalsCalculated) {
            // Reset arrays
            $siteHcHpp = [];
            $siteHcCoss = [];

            // Kelompokkan jumlah HC per site dari SEMUA detail
            foreach ($quotation->quotation_detail as $det) {
                $siteId = $det->quotation_site_id;
                if (!isset($siteHcHpp[$siteId])) {
                    $siteHcHpp[$siteId] = 0;
                    $siteHcCoss[$siteId] = 0;
                }
        
                if (!isset($det->jumlah_hc_hpp)) {
                    $det->jumlah_hc_hpp = $det->jumlah_hc;
                }
                if (!isset($det->jumlah_hc_original)) {
                    $det->jumlah_hc_original = $det->jumlah_hc;
                }

                $siteHcHpp[$siteId] += $det->jumlah_hc_hpp;
                $siteHcCoss[$siteId] += $det->jumlah_hc_original;
            }

            $siteTotalsCalculated = true;

            \Log::info("=== SITE HC TOTALS CALCULATED ===", [
                'quotation_id' => $quotation->id,
                'site_hc_hpp' => $siteHcHpp,
                'site_hc_coss' => $siteHcCoss,
                'total_details' => $quotation->quotation_detail->count()
            ]);
        }
        $currentSiteId = $detail->quotation_site_id;
        $totalJumlahHcHppSite = $siteHcHpp[$currentSiteId] ?? 0;
        $totalJumlahHcCossSite = $siteHcCoss[$currentSiteId] ?? 0;
        $items = [
            'kaporlap' => [
                'hpp_field' => 'provisi_seragam',
                'coss_field' => 'provisi_seragam',
                'model' => QuotationKaporlap::class,
                'detail_id' => $detail->id,
                'is_general' => false,  
                'site_specific' => false, 
                'special' => 'kaporlap'
            ],
            'devices' => [
                'hpp_field' => 'provisi_peralatan',
                'coss_field' => 'provisi_peralatan',
                'model' => QuotationDevices::class,
                'is_general' => true,   
                'site_specific' => true, 
                'site_field' => 'quotation_site_id',
                'special' => null
            ],
            'ohc' => [
                'hpp_field' => 'provisi_ohc',
                'coss_field' => 'provisi_ohc',
                'model' => QuotationOhc::class,
                'is_general' => true,   
                'site_specific' => true, 
                'site_field' => 'quotation_site_id',
                'special' => null
            ],
            'chemical' => [
                'hpp_field' => 'provisi_chemical',
                'coss_field' => 'provisi_chemical',
                'model' => QuotationChemical::class,
                'special' => 'chemical',
                'is_general' => false,  // Chemical punya rumus khusus
                'site_specific' => true, // Filter berdasarkan site
                'site_field' => 'quotation_site_id',
                'detail_id' => null // Chemical tidak terkait langsung dengan detail
            ]
        ];

        foreach ($items as $key => $config) {
            // ============================================
            // **PERBAIKAN: Tentukan divider berdasarkan konfigurasi**
            // ============================================

            // Tentukan divider untuk HPP
            if ($config['is_general'] && $config['site_specific']) {
                // Item general per site: gunakan total HC di site tersebut
                $hppDivider = $totalJumlahHcHppSite;
            } elseif ($config['is_general'] && !$config['site_specific']) {
                // Item general seluruh quotation: gunakan total semua HC
                $hppDivider = $quotation->quotation_detail->sum('jumlah_hc_hpp');
            } else {
                // Item spesifik per detail
                $hppDivider = $detail->jumlah_hc_hpp;
            }

            // Tentukan divider untuk COSS
            if ($config['is_general'] && $config['site_specific']) {
                // Item general per site: gunakan total HC di site tersebut
                $cossDivider = $totalJumlahHcCossSite;
            } elseif ($config['is_general'] && !$config['site_specific']) {
                // Item general seluruh quotation: gunakan total semua HC
                $cossDivider = $quotation->quotation_detail->sum('jumlah_hc_original');
            } else {
                // Item spesifik per detail
                $cossDivider = $detail->jumlah_hc_original;
            }

            $hppDivider = max($hppDivider, 1);
            $cossDivider = max($cossDivider, 1);


            // ============================================
            // **PERBAIKAN: Gunakan total yang berbeda untuk HPP dan COSS**
            // ============================================

            // Untuk HPP: Cek apakah ada nilai manual yang bukan 0
            $hppManualValue = null;
            if ($hpp && $hpp->{$config['hpp_field']} !== null) {
                $hppManualValue = (float) $hpp->{$config['hpp_field']};
            }

            // Untuk COSS: Cek apakah ada nilai manual yang bukan 0
            $cossManualValue = null;
            if ($coss && $coss->{$config['coss_field']} !== null) {
                $cossManualValue = (float) $coss->{$config['coss_field']};

            }

            // Jika ada nilai manual NON-ZERO, gunakan nilai manual (Step 11 input)
            // Jika 0 atau null, hitung otomatis
            if ($hppManualValue !== null) {
                $detail->{"personil_$key"} = $hppManualValue;
            } else {
                // Hitung otomatis untuk HPP
                if (isset($config['special']) && $config['special'] === 'chemical') {
                    $hppValue = $this->calculateItemTotalForHpp(
                        $config['model'],
                        $quotation->id,
                        $config['detail_id'] ?? null,
                        $quotation->provisi,
                        $hppDivider,
                        'chemical',
                        $detail->jumlah_hc_hpp,
                        $config['site_specific'] ? $currentSiteId : null
                    );
                } elseif (isset($config['special']) && $config['special'] === 'kaporlap') {
                    // Kaporlap: dikali dengan HC, bukan dibagi
                    $hppValue = $this->calculateItemTotalForHpp(
                        $config['model'],
                        $quotation->id,
                        $config['detail_id'] ?? null,
                        $quotation->provisi,
                        1, // Tidak dibagi, langsung dikali HC
                        'kaporlap',
                        $detail->jumlah_hc_hpp,
                        $config['site_specific'] ? $currentSiteId : null
                    );
                } else {
                    $hppValue = $this->calculateItemTotalForHpp(
                        $config['model'],
                        $quotation->id,
                        $config['detail_id'] ?? null,
                        $quotation->provisi,
                        $hppDivider,
                        null,
                        $detail->jumlah_hc_hpp,
                        $config['site_specific'] ? $currentSiteId : null
                    );
                }
                $detail->{"personil_$key"} = $hppValue;
            }

            if ($cossManualValue !== null) {
                $detail->{"personil_{$key}_coss"} = $cossManualValue;
            } else {
                // Hitung otomatis untuk COSS
                if (isset($config['special']) && $config['special'] === 'chemical') {
                    $cossValue = $this->calculateItemTotalForCoss(
                        $config['model'],
                        $quotation->id,
                        $config['detail_id'] ?? null,
                        $quotation->provisi,
                        $cossDivider,
                        'chemical',
                        $detail->jumlah_hc_original,
                        $config['site_specific'] ? $currentSiteId : null
                    );
                } elseif (isset($config['special']) && $config['special'] === 'kaporlap') {
                    // Kaporlap: dikali dengan HC, bukan dibagi
                    $cossValue = $this->calculateItemTotalForCoss(
                        $config['model'],
                        $quotation->id,
                        $config['detail_id'] ?? null,
                        $quotation->provisi,
                        1, // Tidak dibagi, langsung dikali HC
                        'kaporlap',
                        $detail->jumlah_hc_original,
                        $config['site_specific'] ? $currentSiteId : null
                    );
                } else {
                    $cossValue = $this->calculateItemTotalForCoss(
                        $config['model'],
                        $quotation->id,
                        $config['detail_id'] ?? null,
                        $quotation->provisi,
                        $cossDivider,
                        null,
                        $detail->jumlah_hc_original,
                        $config['site_specific'] ? $currentSiteId : null
                    );
                }
                $detail->{"personil_{$key}_coss"} = $cossValue;
            }
        }
    }
    /**
     * Calculate item total khusus untuk HPP dengan filter site dan soft delete
     */
    private function calculateItemTotalForhpp($model, $quotationId, $detailId, $provisi, $divider = 1, $special = null, $jumlahHc = 1, $siteId = null)
    {
        // Query dengan filter soft delete
        $query = $model::where('quotation_id', $quotationId)
            ->whereNull('deleted_at');

        // Filter berdasarkan detail_id jika ada (untuk kaporlap)
        if ($detailId) {
            $query->where('quotation_detail_id', $detailId);
        }

        // Filter berdasarkan site_id jika ada (untuk devices, ohc, chemical)
        if ($siteId !== null) {
            $query->where('quotation_site_id', $siteId);
        }

        $items = $query->get();
        if ($items->isEmpty()) {
            return 0;
        }

        $total = 0;
        $itemIndex = 0;

        foreach ($items as $item) {
            $itemIndex++;
            if ($special === 'chemical') {
                // Untuk chemical: total dibagi jumlah HC detail ini
                $itemTotal = ((($item->jumlah * $item->harga) / $item->masa_pakai) / $provisi);
                $perPerson = $itemTotal / max($jumlahHc, 1);
                $total += $perPerson;
            } elseif ($special === 'kaporlap') {
                // Untuk kaporlap: dikali dengan HC, bukan dibagi
                $itemTotal = (($item->harga * $item->jumlah) / $provisi);
                $perPerson = $itemTotal; // DIKALI, bukan dibagi
                $total += $perPerson;
            } else {
                // Untuk item lain: total dibagi jumlah HC (bisa detail atau total tergantung config)
                $itemTotal = (($item->harga * $item->jumlah) / $provisi);
                $perPerson = $itemTotal / max($divider, 1);
                $total += $perPerson;
            }
        }
        return $total;
    }
    /**
     * Calculate item total khusus untuk COSS dengan filter site dan soft delete
     */
    private function calculateItemTotalForCoss($model, $quotationId, $detailId, $provisi, $divider = 1, $special = null, $jumlahHc = 1, $siteId = null)
    {
        // Query dengan filter soft delete
        $query = $model::where('quotation_id', $quotationId)
            ->whereNull('deleted_at');

        // Filter berdasarkan detail_id jika ada (untuk kaporlap)
        if ($detailId) {
            $query->where('quotation_detail_id', $detailId);
        }

        // Filter berdasarkan site_id jika ada (untuk devices, ohc, chemical)
        if ($siteId !== null) {
            $query->where('quotation_site_id', $siteId);
        }

        $items = $query->get();
        if ($items->isEmpty()) {
            return 0;
        }

        $total = 0;
        $itemIndex = 0;

        foreach ($items as $item) {
            $itemIndex++;
            if ($special === 'chemical') {
                $itemTotal = ((($item->jumlah * $item->harga) / $item->masa_pakai) / $provisi);
                $perPerson = $itemTotal / max($jumlahHc, 1);
                $total += $perPerson;
            } elseif ($special === 'kaporlap') {
                // Untuk kaporlap: dikali dengan HC, bukan dibagi
                $itemTotal = (($item->harga * $item->jumlah) / $provisi);
                $perPerson = $itemTotal * max($jumlahHc, 1); // DIKALI, bukan dibagi
                $total += $perPerson;
            } else {
                $itemTotal = (($item->harga * $item->jumlah) / $provisi);
                $perPerson = $itemTotal / max($divider, 1);
                $total += $perPerson;
            }
        }
        return $total;
    }

    // ============================ FINAL TOTALS ============================
    private function calculateFinalTotals($detail, $quotation, $totalTunjanganResult, $hpp, $coss)
    {
        try {
            // ============================================
            // EXTRACT TUNJANGAN DATA
            // ============================================
            if (is_array($totalTunjanganResult)) {
                $totalTunjanganHpp = (float) ($totalTunjanganResult['total'] ?? 0);
                $totalTunjanganCoss = (float) ($totalTunjanganResult['total_coss'] ?? 0);
            } else {
                $totalTunjanganHpp = (float) ($detail->total_tunjangan ?? 0);
                $totalTunjanganCoss = (float) ($detail->total_tunjangan_coss ?? 0);
            }

            $potonganBpu = 0;
            if ($detail->penjamin_kesehatan === 'BPU') {
                $potonganBpu = 16800;
                $detail->potongan_bpu = $potonganBpu;
            }

            // ============================================
            // GET ALL NECESSARY VALUES
            // ============================================

            $tunjanganHariRayaHpp = (float) ($detail->tunjangan_hari_raya_hpp ?? 0);
            $kompensasiHpp = (float) ($detail->kompensasi_hpp ?? 0);
            $tunjanganHariRayaCoss = (float) ($detail->tunjangan_hari_raya_coss ?? 0);
            $kompensasiCoss = (float) ($detail->kompensasi_coss ?? 0);
            $tunjanganHoliday = (float) ($detail->tunjangan_holiday ?? 0);
            $nominalUpah = (float) ($detail->nominal_upah ?? 0);
            $lembur = (float) ($detail->lembur ?? 0);

            // BPJS - karena persentase sudah di-set di Step 11
            $bpjsJkk = (float) ($detail->bpjs_jkk ?? 0);
            $bpjsJkm = (float) ($detail->bpjs_jkm ?? 0);
            $bpjsJht = (float) ($detail->bpjs_jht ?? 0);
            $bpjsJp = (float) ($detail->bpjs_jp ?? 0);
            $bpjsKes = (float) ($detail->bpjs_kes ?? 0);

            $bpjsKetenagakerjaanHpp = $bpjsJkk + $bpjsJkm + $bpjsJht + $bpjsJp;
            $bpjsKetenagakerjaanCoss = $bpjsKetenagakerjaanHpp; // Sama karena menggunakan persentase yang sama

            // PERBAIKAN: Untuk kesehatan, bisa berbeda antara HPP dan COSS
            $biayaKesehatanHpp = $bpjsKes;
            $biayaKesehatanCoss = $bpjsKes;

            // Item provisi - dengan nilai yang sudah dihitung dengan jumlah HC berbeda
            $personilKaporlap = (float) ($detail->personil_kaporlap ?? 0);
            $personilDevices = (float) ($detail->personil_devices ?? 0);
            $personilChemical = (float) ($detail->personil_chemical ?? 0);
            $personilOhc = (float) ($detail->personil_ohc ?? 0);
            $personilKaporlapCoss = (float) ($detail->personil_kaporlap_coss ?? 0);
            $personilDevicesCoss = (float) ($detail->personil_devices_coss ?? 0);
            $personilChemicalCoss = (float) ($detail->personil_chemical_coss ?? 0);
            $personilOhcCoss = (float) ($detail->personil_ohc_coss ?? 0);

            $bungaBank = (float) ($detail->bunga_bank ?? 0);
            $insentif = (float) ($detail->insentif ?? 0);

            // **PERUBAHAN KRITIKAL: Gunakan jumlah HC yang berbeda untuk HPP dan COSS**
            $jumlahHcHpp = $detail->jumlah_hc_hpp;
            $jumlahHcCoss = $detail->jumlah_hc_original;

            // ============================================
            // ✅ PERBAIKAN: HITUNG total_base_manpower UNTUK HPP DAN COSS
            // ============================================
            $detail->total_base_manpower = round($nominalUpah + $totalTunjanganHpp, 2);
            $detail->total_base_manpower_coss = round($nominalUpah + $totalTunjanganCoss, 2);
            // ============================================
            // HPP CALCULATION (dengan jumlah_hc_hpp)
            // ============================================
            $detail->total_personil = round(
                $nominalUpah
                + $totalTunjanganHpp
                + $tunjanganHariRayaHpp
                + $kompensasiHpp
                + $tunjanganHoliday
                + $lembur
                + $bpjsKetenagakerjaanHpp
                + $biayaKesehatanHpp
                + $personilKaporlap
                + $personilDevices
                + $personilChemical
                + $personilOhc
                + $bungaBank
                + $insentif
                + $potonganBpu,
                2
            );

            $detail->sub_total_personil = round($detail->total_personil * $jumlahHcHpp, 2);

            // ============================================
            // COSS CALCULATIONS (dengan jumlah_hc_original)
            // ============================================
            $detail->total_exclude_base_manpower = round(
                $tunjanganHariRayaCoss
                + $kompensasiCoss
                + $tunjanganHoliday
                + $lembur
                + $biayaKesehatanCoss
                + $bpjsKetenagakerjaanCoss
                + $personilKaporlapCoss
                + $personilDevicesCoss
                + $personilChemicalCoss,
                2
            );

            $detail->total_personil_coss = round(
                $detail->total_base_manpower_coss
                + $detail->total_exclude_base_manpower
                + $personilOhcCoss
                + $potonganBpu,
                2
            );

            $detail->sub_total_personil_coss = round($detail->total_personil_coss * $jumlahHcCoss, 2);

        } catch (\Exception $e) {
            \Log::error("Error in calculateFinalTotals for detail {$detail->id}: " . $e->getMessage());
            throw $e;
        }
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
    }

    private function updateDetailsWithGrossUp($quotation, $daftarTunjangan, $jumlahHc, QuotationCalculationResult $result): void
    {
        $summary = $result->calculation_summary;


        $quotation->quotation_detail->each(function ($detail) use ($quotation, $summary, $daftarTunjangan) {
            $detail->bunga_bank = $summary->bunga_bank_total;
            $detail->insentif = $summary->insentif_total;

            // **PERBAIKAN: Recalculate total_personil setelah bunga_bank di-update**
            $hpp = QuotationDetailHpp::where('quotation_detail_id', $detail->id)->first();
            $coss = QuotationDetailCoss::where('quotation_detail_id', $detail->id)->first();

            $totalTunjanganResult = [
                'total' => $detail->total_tunjangan ?? 0,
                'total_coss' => $detail->total_tunjangan_coss ?? 0
            ];

            $this->calculateFinalTotals($detail, $quotation, $totalTunjanganResult, $hpp, $coss);
        });
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

        // Gunakan suffix untuk menentukan field jumlah_hc yang benar
        $jumlahHcField = ($suffix === '_coss') ? 'jumlah_hc_original' : 'jumlah_hc_hpp';
        $summary->{"total_sebelum_management_fee{$suffix}"} =
            $quotation->quotation_detail->sum('sub_total_personil' . $suffix);
        $summary->{"total_base_manpower{$suffix}"} = $quotation->quotation_detail->sum(
            function ($detail) use ($suffix, $jumlahHcField) {
                $totalBaseManpower = ($suffix === '_coss')
                    ? ($detail->total_base_manpower_coss ?? 0)
                    : ($detail->total_base_manpower ?? 0);

                $jumlahHc = $detail->{$jumlahHcField} ?? $detail->jumlah_hc;

                $result = $totalBaseManpower * $jumlahHc;
                return $result;
            }
        );

        // 3. Hitung upah pokok dengan jumlah_hc yang benar
        $summary->{"upah_pokok{$suffix}"} = $quotation->quotation_detail->sum(
            fn($detail) => $detail->nominal_upah * ($detail->{$jumlahHcField} ?? $detail->jumlah_hc)
        );

        // 4. Hitung total BPJS ketenagakerjaan dengan jumlah_hc yang benar
        $summary->{"total_bpjs{$suffix}"} = $quotation->quotation_detail->sum(
            fn($detail) => ($detail->bpjs_ketenagakerjaan ?? 0) * ($detail->{$jumlahHcField} ?? $detail->jumlah_hc)
        );

        // 5. Hitung total BPJS kesehatan dengan jumlah_hc yang benar
        $summary->{"total_bpjs_kesehatan{$suffix}"} = $quotation->quotation_detail->sum(
            fn($detail) => ($detail->bpjs_kesehatan ?? 0) * ($detail->{$jumlahHcField} ?? $detail->jumlah_hc)
        );

        // 6. Hitung total potongan BPU untuk informasi (sudah termasuk di total_personil)
        $summary->total_potongan_bpu = $quotation->quotation_detail->sum(
            fn($detail) => ($detail->penjamin_kesehatan === 'BPU')
            ? 16800 * ($detail->{$jumlahHcField} ?? $detail->jumlah_hc)
            : 0
        );

        $summary->potongan_bpu_per_orang = 16800;

        // 7. Hitung persentase BPJS rata-rata
        $totalHc = $quotation->quotation_detail->sum(
            fn($detail) => $detail->{$jumlahHcField} ?? $detail->jumlah_hc
        );

        if ($totalHc > 0) {
            $firstDetail = $quotation->quotation_detail->first();
            if ($firstDetail) {
                if ($suffix === '') {
                    // HPP
                    $summary->persen_bpjs_ketenagakerjaan = $firstDetail->persen_bpjs_ketenagakerjaan ?? 0;
                    $summary->persen_bpjs_kesehatan = $firstDetail->persen_bpjs_kesehatan ?? 0;
                    $summary->persen_bpjs_jkk = $firstDetail->persen_bpjs_jkk ?? 0;
                    $summary->persen_bpjs_jkm = $firstDetail->persen_bpjs_jkm ?? 0;
                    $summary->persen_bpjs_jht = $firstDetail->persen_bpjs_jht ?? 0;
                    $summary->persen_bpjs_jp = $firstDetail->persen_bpjs_jp ?? 0;
                    $summary->persen_bpjs_kes = $firstDetail->persen_bpjs_kes ?? 0;
                } else {
                    // COSS
                    $summary->persen_bpjs_ketenagakerjaan_coss = $firstDetail->persen_bpjs_ketenagakerjaan ?? 0;
                    $summary->persen_bpjs_kesehatan_coss = $firstDetail->persen_bpjs_kesehatan ?? 0;
                    $summary->persen_bpjs_jkk_coss = $firstDetail->persen_bpjs_jkk ?? 0;
                    $summary->persen_bpjs_jkm_coss = $firstDetail->persen_bpjs_jkm ?? 0;
                    $summary->persen_bpjs_jht_coss = $firstDetail->persen_bpjs_jht ?? 0;
                    $summary->persen_bpjs_jp_coss = $firstDetail->persen_bpjs_jp ?? 0;
                    $summary->persen_bpjs_kes_coss = $firstDetail->persen_bpjs_kes ?? 0;
                }
            }
        }
    }

    // ============================ MANAGEMENT FEE CALCULATIONS ============================

    /**
     * Calculate management fee untuk HPP dan COSS
     */
    private function calculateManagementFee(&$quotation, $suffix, QuotationCalculationResult $result): void
    {
        $summary = $result->calculation_summary;

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
        $grandTotal = $summary->{"grand_total_sebelum_pajak{$suffix}"};
        $maxReasonableTax = $grandTotal * 0.5;

        if ($summary->{"ppn{$suffix}"} > $maxReasonableTax) {
            $summary->{"ppn{$suffix}"} = 0;
        }

        if ($summary->{"pph{$suffix}"} > $maxReasonableTax) {
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
        $baseAmount = 0;
        if ($ppnPphDipotong == "Management Fee") {
            $managementFee = $summary->{"nominal_management_fee{$suffix}"};
            $baseAmount = $managementFee * (11 / 12);
        } else {
            $baseAmount = $summary->{"grand_total_sebelum_pajak{$suffix}"} * (11 / 12);
        }
        $summary->{"dpp{$suffix}"} = $baseAmount;
        if ($summary->{"ppn{$suffix}"} == 0 && $isPpnBoolean) {
            $summary->{"ppn{$suffix}"} = round($baseAmount * 0.12, 2);
        }
        if ($summary->{"pph{$suffix}"} == 0 && $ppnPphDipotong == "Management Fee") {
            $calculatedPph = round($managementFee * -0.02, 2);
            $maxPph = abs($baseAmount * 0.1);
            if (abs($calculatedPph) > $maxPph) {
                $calculatedPph = -$maxPph;
            }
            $summary->{"pph{$suffix}"} = $calculatedPph;
        } else if ($summary->{"pph{$suffix}"} == 0 && $ppnPphDipotong != "Total Invoice") {
            $calculatedPph = round($summary->{"grand_total_sebelum_pajak{$suffix}"} * -0.02, 2);
            $maxPph = abs($baseAmount * 0.1);
            if (abs($calculatedPph) > $maxPph) {
                $calculatedPph = -$maxPph;
            }
            $summary->{"pph{$suffix}"} = $calculatedPph;
        } else {
            // Jika PPH sudah ada, pastikan nilainya negatif
            if ($summary->{"pph{$suffix}"} > 0) {
                $summary->{"pph{$suffix}"} = -abs($summary->{"pph{$suffix}"});
            }
        }
    }
    private function finalizeCalculations(&$quotation, $suffix, QuotationCalculationResult $result): void
    {
        $summary = $result->calculation_summary;

        $summary->{"total_invoice{$suffix}"} = $summary->{"grand_total_sebelum_pajak{$suffix}"} +
            $summary->{"ppn{$suffix}"} + $summary->{"pph{$suffix}"};
        $summary->{"pembulatan{$suffix}"} = ceil($summary->{"total_invoice{$suffix}"} / 1000) * 1000;
        $summary->{"margin{$suffix}"} = $summary->{"grand_total_sebelum_pajak{$suffix}"} - $summary->total_sebelum_management_fee;
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
        $result = 0;

        if ($nominalUpah > $umk) {
            $result = $nominalUpah;
        } elseif ($nominalUpah == $umk) {
            $result = $umk;
        } elseif ($nominalUpah < $umk && $nominalUpah >= $ump) {
            $result = $nominalUpah;
        } else {
            $result = $ump;
        }
        return $result;
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
    private function applyBpjsOptOut($detail)
    {
        $optOuts = [
            'is_bpjs_jkk' => ['bpjs_jkk', 'persen_bpjs_jkk'],
            'is_bpjs_jkm' => ['bpjs_jkm', 'persen_bpjs_jkm'],
            'is_bpjs_jht' => ['bpjs_jht', 'persen_bpjs_jht'],
            'is_bpjs_jp' => ['bpjs_jp', 'persen_bpjs_jp'],
            'is_bpjs_kes' => ['bpjs_kes', 'persen_bpjs_kes']
        ];
        foreach ($optOuts as $optField => $targetFields) {
            $isOptOut = false;
            $optValue = $detail->{$optField} ?? null;

            if (isset($detail->{$optField})) {
                $optValue = $detail->{$optField};

                // Handle berbagai format nilai opt-out
                if ($optValue === "0" || $optValue === 0 || $optValue === false || $optValue === "false") {
                    $isOptOut = true;
                } elseif (is_string($optValue) && strtolower(trim($optValue)) === 'tidak') {
                    $isOptOut = true;
                } elseif (is_string($optValue) && strtolower(trim($optValue)) === 'ya') {
                    $isOptOut = false;
                }
            }

            if ($isOptOut) {
                $detail->{$targetFields[0]} = 0;
                $detail->{$targetFields[1]} = 0;
            }
        }
    }

    private function updateQuotationBpjs($detail, $quotation)
    {
        $detail->persen_bpjs_ketenagakerjaan =
            ($detail->persen_bpjs_jkk ?? 0) +
            ($detail->persen_bpjs_jkm ?? 0) +
            ($detail->persen_bpjs_jht ?? 0) +
            ($detail->persen_bpjs_jp ?? 0);

        $detail->bpjs_ketenagakerjaan =
            ($detail->bpjs_jkk ?? 0) +
            ($detail->bpjs_jkm ?? 0) +
            ($detail->bpjs_jht ?? 0) +
            ($detail->bpjs_jp ?? 0);

        if (in_array($detail->penjamin_kesehatan, ["BPJS", "BPJS Kesehatan"])) {
            $detail->bpjs_kesehatan = $detail->bpjs_kes ?? 0;
            $detail->persen_bpjs_kesehatan = $detail->persen_bpjs_kes ?? 0;
        } elseif (in_array($detail->penjamin_kesehatan, ["Asuransi Swasta", "Takaful"])) {
            $detail->bpjs_kesehatan = $detail->nominal_takaful ?? 0;
            $detail->persen_bpjs_kesehatan = 0;
        } else {
            $detail->bpjs_kesehatan = 0;
            $detail->persen_bpjs_kesehatan = 0;
        }
    }

    private function calculateBpu($detail, $quotation)
    {
        $bpuAmount = 0;

        if ($detail->penjamin_kesehatan === 'BPU') {
            $bpuAmount = 16800; // Fixed 16 ribu per karyawan
        }

        return $bpuAmount;
    }

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

    public function submitForApproval(Quotation $quotation, array $data, User $user)
    {
        $isApproved = filter_var($data['is_approved'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $currentDateTime = Carbon::now();
        $tingkat = 1;

        if ($user->cais_role_id == 96) {
            $updateData = [
                'ot1' => $user->full_name,
                'updated_at' => $currentDateTime->toDateTimeString(),
                'updated_by' => $user->full_name
            ];

            if ($isApproved) {
                $needsLevel2 = ($quotation->top == "Lebih Dari 7 Hari");
                $updateData['status_quotation_id'] = $needsLevel2 ? 2 : 3;
                $updateData['is_aktif'] = $needsLevel2 ? 0 : 1;
            } else {
                $updateData['status_quotation_id'] = 8;
                $updateData['is_aktif'] = 0;
            }
            $tingkat = 1;
        } elseif ($user->cais_role_id == 97) {
            if ($quotation->status_quotation_id != 2 || empty($quotation->ot1)) {
                return ['success' => false, 'message' => 'Quotation belum disetujui oleh Direktur Sales.'];
            }

            $updateData = [
                'ot2' => $user->full_name,
                'status_quotation_id' => $isApproved ? 3 : 8,
                'is_aktif' => $isApproved ? 1 : 0,
                'updated_at' => $currentDateTime->toDateTimeString(),
                'updated_by' => $user->full_name
            ];
            $tingkat = 2;
        } else {
            return ['success' => false, 'message' => 'User tidak memiliki akses approval.'];
        }

        $quotation->update($updateData);

        // Log approval
        LogApproval::create([
            'tabel' => 'quotation',
            'doc_id' => $quotation->id,
            'tingkat' => $tingkat,
            'is_approve' => $isApproved,
            'note' => $data['notes'] ?? null,
            'user_id' => $user->id,
            'approval_date' => $currentDateTime,
            'created_at' => $currentDateTime,
            'created_by' => $user->full_name
        ]);

        // Log notification untuk sales dari leads kebutuhan
        $leadsKebutuhan = LeadsKebutuhan::with('timSalesD')
            ->where('leads_id', $quotation->leads_id)
            ->where('kebutuhan_id', $quotation->kebutuhan_id)
            ->first();

        if ($leadsKebutuhan && $leadsKebutuhan->timSalesD) {
            $quotationNumber = $quotation->nomor;
            $approverName = $user->full_name;
            $reason = $data['notes'] ?? null;

            $msg = $isApproved
                ? "Quotation dengan nomor: {$quotationNumber} di approve oleh {$approverName}"
                : "Quotation dengan nomor: {$quotationNumber} di reject oleh {$approverName}" . ($reason ? " dengan alasan: {$reason}" : "");

            LogNotification::create([
                'user_id' => $leadsKebutuhan->timSalesD->user_id,
                'doc_id' => $quotation->id,
                'transaksi' => 'Quotation',
                'tabel' => 'sl_quotation',
                'pesan' => $msg,
                'is_read' => 0,
                'created_at' => $currentDateTime,
                'created_by' => $user->full_name
            ]);
        }
        if ($isApproved && $quotation->tipe_quotation == 'addendum') {
            $addendumService = app(AddendumService::class);
            $addendumResult = $addendumService->process($quotation);
        }

        return ['success' => true, 'data' => $quotation->fresh()];
    }

    /**
     * Generate activity nomor
     */
    private function generateActivityNomor($leadsId): string
    {
        $now = Carbon::now();
        $count = DB::table('customer_activities')
            ->where('leads_id', $leadsId)
            ->whereYear('tgl_activity', $now->year)
            ->count();

        return 'ACT/' . $leadsId . '/' . $now->year . '/' . sprintf('%04d', $count + 1);
    }

    public function resetApproval(Quotation $quotation, User $user)
    {
        // Cek role - tambahkan role lain yang boleh reset
        $allowedRoles = [2, 96, 97]; // Admin, OT1, OT2
        if (!in_array($user->cais_role_id, $allowedRoles)) {
            return ['success' => false, 'message' => 'Anda tidak memiliki akses untuk reset approval. Role: ' . $user->cais_role_id];
        }

        $quotation->update([
            'status_quotation_id' => 2,
            'is_aktif' => 0,
            'ot1' => null,
            'ot2' => null,
            'updated_at' => Carbon::now()->toDateTimeString(),
            'updated_by' => $user->full_name
        ]);

        \Log::info('Reset approval success', [
            'quotation_id' => $quotation->id,
            'reset_by' => $user->full_name
        ]);

        return ['success' => true, 'data' => $quotation->fresh()];
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
                      <td>' . ($quotation->pengiriman_invoice ?: $salaryRuleQ->pengiriman_invoice) . '</td>
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


        $perjanjianContent = "Skema cut-off, pengiriman <i>invoice</i>, pembayaran <i>invoice</i> dan penggajian dengan skema sebagai berikut: <br>" . $tableSalary;

        $catatanKaki = "<i><br>*Rilis gaji adalah talangan.";

        // Jika bukan Non TOP, tampilkan detail maksimal pembayaran
        if ($quotation->top !== 'Non TOP') {
            $topValue = ($quotation->top === 'Lebih Dari 7 Hari')
                ? $quotation->jumlah_hari_invoice
                : $quotation->top;

            $catatanKaki .= "<br>*Maksimal pembayaran invoice " . $topValue . " hari " . $quotation->tipe_hari_invoice . " setelah invoice";
        }

        $catatanKaki .= "</i>";
        $perjanjian[] = $perjanjianContent . $catatanKaki;

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