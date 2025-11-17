<?php

namespace App\Services;

use App\Models\{
    Quotation,
    QuotationDetail,
    QuotationSite,
    ManagementFee,
    QuotationDetailTunjangan,
    QuotationDetailHpp,
    QuotationDetailCoss,
    QuotationChemical,
    QuotationOhc,
    QuotationKaporlap,
    QuotationDevices,
    QuotationDetailWage,
    User
};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    // ============================ MAIN CALCULATION FLOW ============================
    public function calculateQuotation($quotation)
    {
        try {
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
                return $quotation;
            }

            $jumlahHc = $quotation->quotation_detail->sum('jumlah_hc');
            $quotation->jumlah_hc = $jumlahHc;
            $quotation->provisi = $this->calculateProvisi($quotation->durasi_kerjasama);

            // First pass calculation
            $this->calculateFirstPass($quotation, $jumlahHc);

            // Recalculate with gross-up adjustments
            $this->recalculateWithGrossUp($quotation, $jumlahHc);

            return $quotation;
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
                'lembur' => 'Tidak',
                'nominal_lembur' => 0,
                'jenis_bayar_lembur' => null,
                'jam_per_bulan_lembur' => 0,
                'lembur_ditagihkan' => 'Tidak Ditagihkan',
                'kompensasi' => 'Tidak',
                'thr' => 'Tidak',
                'tunjangan_holiday' => 'Tidak',
                'nominal_tunjangan_holiday' => 0,
                'jenis_bayar_tunjangan_holiday' => null,
                'created_by' => 'system-auto'
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
        $quotation->persen_bpjs_ketenagakerjaan = 0;
        $quotation->persen_bpjs_kesehatan = 0;
    }

    private function loadQuotationData($quotation)
    {
        // Load dengan relasi wage
        $quotation->quotation_detail = QuotationDetail::with('wage')
            ->where('quotation_id', $quotation->id)->get();
        $quotation->quotation_site = QuotationSite::where('quotation_id', $quotation->id)->get();

        // Calculate site details count
        $quotation->quotation_site->each(function ($site) use ($quotation) {
            $site->jumlah_detail = $quotation->quotation_detail
                ->where('quotation_site_id', $site->id)->count();
        });

        // Get management fee
        $managementFee = ManagementFee::find($quotation->management_fee_id);
        $quotation->management_fee = $managementFee->nama ?? '';
    }

    // ============================ CORE CALCULATION METHODS ============================
    private function calculateFirstPass($quotation, $jumlahHc)
    {
        $daftarTunjangan = QuotationDetailTunjangan::where('quotation_id', $quotation->id)
            ->distinct('nama_tunjangan')->get(['nama_tunjangan as nama']);

        $this->processAllDetails($quotation, $daftarTunjangan, $jumlahHc);
        $this->calculateHpp($quotation, $jumlahHc, $quotation->provisi);
        $this->calculateCoss($quotation, $jumlahHc, $quotation->provisi);
    }

    private function recalculateWithGrossUp($quotation, $jumlahHc)
    {
        $daftarTunjangan = QuotationDetailTunjangan::where('quotation_id', $quotation->id)
            ->distinct('nama_tunjangan')->get(['nama_tunjangan as nama']);

        $this->calculateBankInterestAndIncentive($quotation, $jumlahHc);
        $this->updateDetailsWithGrossUp($quotation, $daftarTunjangan, $jumlahHc);
    }

    // ============================ DETAIL PROCESSING ============================
    private function processAllDetails($quotation, $daftarTunjangan, $jumlahHc)
    {
        $quotation->quotation_detail->each(function ($detail) use ($quotation, $daftarTunjangan, $jumlahHc) {
            try {
                $this->processSingleDetail($detail, $quotation, $daftarTunjangan, $jumlahHc);
            } catch (\Exception $e) {
                \Log::error("Failed to process detail {$detail->id}, skipping: " . $e->getMessage());
                // Skip this detail but continue with others
            }
        });
    }

    private function processSingleDetail($detail, $quotation, $daftarTunjangan, $jumlahHc)
    {
        try {
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
            $this->calculateDetailComponents($detail, $quotation, $daftarTunjangan, $jumlahHc, $hpp, $coss, $wage);

        } catch (\Exception $e) {
            \Log::error("Error processing detail ID {$detail->id}: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    private function initializeDetail($detail, $hpp, $site, $wage)
    {
        $detail->nominal_upah = $hpp->gaji_pokok ?? $site->nominal_upah ?? 0;
        $detail->umk = $site->umk ?? 0;
        $detail->ump = $site->ump ?? 0;
        $detail->bunga_bank = $hpp->bunga_bank ?? 0;
        $detail->insentif = $hpp->insentif ?? 0;

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

    private function calculateDetailComponents($detail, $quotation, $daftarTunjangan, $jumlahHc, $hpp, $coss, $wage)
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

        } catch (\Exception $e) {
            \Log::error("Error in calculateDetailComponents for detail {$detail->id}: " . $e->getMessage());
            \Log::error("Stack trace in calculateDetailComponents: " . $e->getTraceAsString());
            throw $e;
        }
    }
    // ============================ COMPONENT CALCULATIONS ============================
    private function calculateTunjangan($detail, $daftarTunjangan)
    {
        $totalTunjangan = 0;
        foreach ($daftarTunjangan as $tunjangan) {
            $dtTunjangan = QuotationDetailTunjangan::where('quotation_detail_id', $detail->id)
                ->where('nama_tunjangan', $tunjangan->nama)->first();

            $value = $dtTunjangan->nominal ?? 0;
            $detail->{$tunjangan->nama} = $value;
            $totalTunjangan += $value;
        }
        $detail->total_tunjangan = $totalTunjangan;
        return $totalTunjangan;
    }

    private function calculateBpjs($detail, $quotation, $hpp)
    {
        if ($quotation->program_bpjs === 'BPU') {
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
            $detail->nominal_upah = $detail->nominal_upah - 16000;

            $this->updateQuotationBpjs($detail, $quotation);
            return;
        }
        $upahBpjs = $this->calculateUpahBpjs($detail->nominal_upah, $detail->umk, $detail->ump);

        $bpjsConfig = [
            'jkk' => ['field' => 'bpjs_jkk', 'percent' => 'persen_bpjs_jkk', 'default' => $this->getJkkPercentage($quotation->resiko)],
            'jkm' => ['field' => 'bpjs_jkm', 'percent' => 'persen_bpjs_jkm', 'default' => 0.3],
            'jht' => ['field' => 'bpjs_jht', 'percent' => 'persen_bpjs_jht', 'default' => 3.7],
            'jp' => ['field' => 'bpjs_jp', 'percent' => 'persen_bpjs_jp', 'default' => 2],
            'kes' => ['field' => 'bpjs_kes', 'percent' => 'persen_bpjs_kes', 'default' => 4, 'base' => $detail->umk]
        ];

        foreach ($bpjsConfig as $config) {
            if (($hpp->{$config['field']} ?? null) === null) {
                $base = $config['base'] ?? $upahBpjs;
                $detail->{$config['percent']} = $detail->{$config['percent']} ?? $config['default'];
                $detail->{$config['field']} = $base * $detail->{$config['percent']} / 100;
            } else {
                $detail->{$config['field']} = $hpp->{$config['field']};
                $detail->{$config['percent']} = $hpp->{"persen_{$config['field']}"};
            }
        }

        // Apply BPJS opt-out berdasarkan data dari quotation detail
        $this->applyBpjsOptOut($detail);
        $this->updateQuotationBpjs($detail, $quotation);
    }
    private function calculateExtras($detail, $quotation, $hpp, $wage)
    {
        try {
            // THR & Kompensasi - GUNAKAN DATA DARI WAGE DENGAN FALLBACK
            $thrValue = $wage->thr ?? "Tidak";

            $detail->tunjangan_hari_raya = $hpp->tunjangan_hari_raya ??
                ($thrValue == "Diprovisikan" ? $detail->nominal_upah / 12 : 0);

            $kompensasiValue = $wage->kompensasi ?? "Tidak";

            $detail->kompensasi = $hpp->kompensasi ??
                ($kompensasiValue == "Diprovisikan" ? $detail->nominal_upah / 12 : 0);

            // Tunjangan Holiday - GUNAKAN DATA DARI WAGE DENGAN FALLBACK
            $tunjanganHolidayValue = $wage->tunjangan_holiday ?? "Tidak";

            $detail->tunjangan_holiday = $tunjanganHolidayValue == "Flat"
                ? ($hpp->tunjangan_hari_libur_nasional ?? ($wage->nominal_tunjangan_holiday ?? 0))
                : 0;

            // Lembur - GUNAKAN DATA DARI WAGE DENGAN FALLBACK
            $detail->lembur = $this->calculateLembur($detail, $quotation, $hpp, $wage);

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
        if ($quotation->program_bpjs === 'BPU') {
            $potonganBpu = 16000; // Fixed 16 ribu
            $detail->potongan_bpu = $potonganBpu;
        }

        // HPP Calculations
        $detail->total_personil = $detail->nominal_upah + $totalTunjangan + $detail->tunjangan_hari_raya +
            $detail->kompensasi + $detail->tunjangan_holiday + $detail->lembur + $detail->nominal_takaful +
            $detail->bpjs_ketenagakerjaan + $detail->bpjs_kesehatan + $detail->personil_kaporlap +
            $detail->personil_devices + $detail->personil_chemical + $detail->personil_ohc +
            $detail->bunga_bank + $detail->insentif - $potonganBpu;

        $detail->sub_total_personil = $detail->total_personil * $detail->jumlah_hc;

        // COSS Calculations - sama seperti sebelumnya
        $detail->total_base_manpower = round($detail->nominal_upah + $detail->total_tunjangan, 2);

        $detail->total_exclude_base_manpower = round(
            $detail->tunjangan_hari_raya + $detail->kompensasi + $detail->tunjangan_holiday +
            $detail->lembur + $detail->nominal_takaful + $detail->bpjs_kesehatan +
            $detail->bpjs_ketenagakerjaan + $detail->personil_kaporlap_coss +
            $detail->personil_devices_coss + $detail->personil_chemical_coss,
            2
        );

        $detail->total_personil_coss = round($detail->total_base_manpower + $detail->total_exclude_base_manpower + $detail->personil_ohc_coss - $potonganBpu, 2);
        $detail->sub_total_personil_coss = round($detail->total_personil_coss * $detail->jumlah_hc, 2);
    }

    // ============================ GROSS UP RECALCULATION ============================
    private function calculateBankInterestAndIncentive($quotation, $jumlahHc)
    {
        $persenBungaBank = $quotation->top != "Non TOP" ? $quotation->persen_bunga_bank : 0;

        $quotation->bunga_bank_total = $persenBungaBank ?
            $quotation->total_sebelum_management_fee * ($persenBungaBank / 100) / $jumlahHc : 0;

        $quotation->insentif_total = $quotation->persen_insentif ?
            $quotation->nominal_management_fee_coss * ($quotation->persen_insentif / 100) / $jumlahHc : 0;
    }

    private function updateDetailsWithGrossUp($quotation, $daftarTunjangan, $jumlahHc)
    {
        $quotation->quotation_detail->each(function ($detail) use ($quotation) {
            $hpp = QuotationDetailHpp::where('quotation_detail_id', $detail->id)->first();
            if ($hpp && $hpp->bunga_bank === null)
                $detail->bunga_bank = $quotation->bunga_bank_total;
            if ($hpp && $hpp->insentif === null)
                $detail->insentif = $quotation->insentif_total;
        });

        $this->processAllDetails($quotation, $daftarTunjangan, $jumlahHc);
        $this->calculateHpp($quotation, $jumlahHc, $quotation->provisi);
        $this->calculateCoss($quotation, $jumlahHc, $quotation->provisi);
    }

    // ============================ HPP & COSS CALCULATIONS ============================
    private function calculateHpp(&$quotation, $jumlahHc, $provisi)
    {
        $this->calculateFinancials($quotation, 'hpp');
    }

    private function calculateCoss(&$quotation, $jumlahHc, $provisi)
    {
        $this->calculateFinancials($quotation, 'coss');
    }

    private function calculateFinancials(&$quotation, $type)
    {
        $suffix = $type === 'coss' ? '_coss' : '';
        $model = $type === 'coss' ? QuotationDetailCoss::class : QuotationDetailHpp::class;

        // Calculate base totals
        $this->calculateBaseTotals($quotation, $suffix);

        // Calculate management fee
        $this->calculateManagementFee($quotation, $suffix);

        // Calculate taxes
        $this->calculateTaxes($quotation, $suffix, $model);

        // Final calculations
        $this->finalizeCalculations($quotation, $suffix);
    }

    private function calculateBaseTotals(&$quotation, $suffix)
    {
        $quotation->{"total_sebelum_management_fee{$suffix}"} = $quotation->quotation_detail->sum('sub_total_personil' . $suffix);
        $quotation->{"total_base_manpower{$suffix}"} = $quotation->quotation_detail->sum(fn($kbd) => $kbd->total_base_manpower * $kbd->jumlah_hc);
        $quotation->{"upah_pokok{$suffix}"} = $quotation->quotation_detail->sum(fn($kbd) => $kbd->nominal_upah * $kbd->jumlah_hc);

        $quotation->{"total_bpjs{$suffix}"} = $quotation->quotation_detail->sum(fn($kbd) => $kbd->bpjs_ketenagakerjaan * $kbd->jumlah_hc);
        $quotation->{"total_bpjs_kesehatan{$suffix}"} = $quotation->quotation_detail->sum(
            fn($kbd) =>
            ($kbd->bpjs_kesehatan + $kbd->nominal_takaful) * $kbd->jumlah_hc
        );
    }

    private function calculateManagementFee(&$quotation, $suffix)
    {
        // GUNAKAN DATA DARI QUOTATION, BUKAN DARI WAGE
        $managementFeeCalculations = [
            1 => fn() => $quotation->{"total_base_manpower{$suffix}"} * $quotation->persentase / 100,
            4 => fn() => $quotation->{"total_sebelum_management_fee{$suffix}"} * $quotation->persentase / 100,
            5 => fn() => $quotation->{"upah_pokok{$suffix}"} * $quotation->persentase / 100,
            6 => fn() => ($quotation->{"upah_pokok{$suffix}"} + $quotation->{"total_bpjs{$suffix}"}) * $quotation->persentase / 100,
            7 => fn() => ($quotation->{"upah_pokok{$suffix}"} + $quotation->{"total_bpjs{$suffix}"} + $quotation->{"total_bpjs_kesehatan{$suffix}"}) * $quotation->persentase / 100,
            8 => fn() => ($quotation->{"upah_pokok{$suffix}"} + $quotation->{"total_bpjs_kesehatan{$suffix}"}) * $quotation->persentase / 100,
        ];

        $calculation = $managementFeeCalculations[$quotation->management_fee_id] ?? $managementFeeCalculations[1];
        $quotation->{"nominal_management_fee{$suffix}"} = $calculation();
        $quotation->{"grand_total_sebelum_pajak{$suffix}"} = $quotation->{"total_sebelum_management_fee{$suffix}"} + $quotation->{"nominal_management_fee{$suffix}"};
    }

    private function calculateTaxes(&$quotation, $suffix, $model)
    {
        // Calculate existing taxes
        $quotation->{"ppn{$suffix}"} = 0;
        $quotation->{"pph{$suffix}"} = 0;

        $quotation->quotation_detail->each(function ($kbd) use (&$quotation, $suffix, $model) {
            $detail = $model::where('quotation_detail_id', $kbd->id)->first();
            if ($detail) {
                $quotation->{"ppn{$suffix}"} += $detail->ppn ?? 0;
                $quotation->{"pph{$suffix}"} += $detail->pph ?? 0;
            }
        });

        // Calculate taxes if not set
        if ($quotation->{"ppn{$suffix}"} == 0 || $quotation->{"pph{$suffix}"} == 0) {
            $this->calculateDefaultTaxes($quotation, $suffix);
        }
    }


    private function calculateDefaultTaxes(&$quotation, $suffix)
    {
        // GUNAKAN DATA DARI QUOTATION
        $ppnPphDipotong = $quotation->ppn_pph_dipotong ?? "Management Fee";
        $isPpn = $quotation->is_ppn ?? "Tidak";

        $baseAmount = $ppnPphDipotong == "Management Fee"
            ? $quotation->{"nominal_management_fee{$suffix}"}
            : $quotation->{"grand_total_sebelum_pajak{$suffix}"};

        $quotation->{"dpp{$suffix}"} = 11 / 12 * $baseAmount;

        if ($quotation->{"ppn{$suffix}"} == 0 && $isPpn == "Ya") {
            $quotation->{"ppn{$suffix}"} = $quotation->{"dpp{$suffix}"} * 12 / 100;
        }

        if ($quotation->{"pph{$suffix}"} == 0) {
            $quotation->{"pph{$suffix}"} = $baseAmount * -2 / 100;
        }
    }

    private function finalizeCalculations(&$quotation, $suffix)
    {
        $quotation->{"total_invoice{$suffix}"} = $quotation->{"grand_total_sebelum_pajak{$suffix}"} +
            $quotation->{"ppn{$suffix}"} + $quotation->{"pph{$suffix}"};
        $quotation->{"pembulatan{$suffix}"} = ceil($quotation->{"total_invoice{$suffix}"} / 1000) * 1000;

        $quotation->{"margin{$suffix}"} = $quotation->{"grand_total_sebelum_pajak{$suffix}"} - $quotation->total_sebelum_management_fee;

        // FIX: Tambahkan pengecekan untuk menghindari division by zero
        if ($quotation->{"grand_total_sebelum_pajak{$suffix}"} != 0) {
            $quotation->{"gpm{$suffix}"} = $quotation->{"margin{$suffix}"} / $quotation->{"grand_total_sebelum_pajak{$suffix}"} * 100;
        } else {
            $quotation->{"gpm{$suffix}"} = 0;
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
            if ($detail->{$optField} == "0") {
                $detail->{$targetFields[0]} = 0;
                $detail->{$targetFields[1]} = 0;
            }
        }
    }

    private function updateQuotationBpjs($detail, $quotation)
    {
        $detail->persen_bpjs_ketenagakerjaan = $detail->persen_bpjs_jkk + $detail->persen_bpjs_jkm +
            $detail->persen_bpjs_jht + $detail->persen_bpjs_jp;
        $detail->bpjs_ketenagakerjaan = $detail->bpjs_jkk + $detail->bpjs_jkm + $detail->bpjs_jht + $detail->bpjs_jp;

        if ($detail->persen_bpjs_ketenagakerjaan) {
            $quotation->persen_bpjs_ketenagakerjaan = $detail->persen_bpjs_ketenagakerjaan;
        }

        if ($detail->penjamin_kesehatan == "BPJS") {
            $detail->bpjs_kesehatan = $detail->bpjs_kes;
            $detail->persen_bpjs_kesehatan = $detail->persen_bpjs_kes;
            $quotation->persen_bpjs_kesehatan = $detail->persen_bpjs_kesehatan;
        } else {
            $detail->bpjs_kesehatan = 0;
            $detail->persen_bpjs_kesehatan = 0;
        }
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
     * Submit quotation untuk approval
     */
    public function submitForApproval(Quotation $quotation, array $data, User $user)
    {
        DB::beginTransaction();
        try {
            $approvalType = $data['approval_type'];
            $isApproved = $data['is_approved'];
            $notes = $data['notes'] ?? null;

            $updateData = [
                'updated_by' => $user->full_name
            ];

            switch ($approvalType) {
                case 'ot1':
                    $updateData['status_ot1'] = $isApproved ? 'approved' : 'rejected';
                    $updateData['tgl_approval_ot1'] = $isApproved ? Carbon::now() : null;
                    $updateData['notes_ot1'] = $notes;
                    break;

                case 'ot2':
                    $updateData['status_ot2'] = $isApproved ? 'approved' : 'rejected';
                    $updateData['tgl_approval_ot2'] = $isApproved ? Carbon::now() : null;
                    $updateData['notes_ot2'] = $notes;
                    break;

                case 'ot3':
                    $updateData['status_ot3'] = $isApproved ? 'approved' : 'rejected';
                    $updateData['tgl_approval_ot3'] = $isApproved ? Carbon::now() : null;
                    $updateData['notes_ot3'] = $notes;

                    // Jika OT3 approved, update status quotation
                    if ($isApproved) {
                        $updateData['status_quotation_id'] = 4; // Status approved
                    }
                    break;
            }

            $quotation->update($updateData);

            // Jika rejected di level manapun, update status menjadi rejected
            if (!$isApproved) {
                $quotation->update([
                    'status_quotation_id' => 5, // Status rejected
                    'updated_by' => $user->full_name
                ]);
            }

            DB::commit();
            return $quotation;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate konten perjanjian kerjasama
     */
    public function generateKerjasamaContent(Quotation $quotation)
    {
        $perjanjian = [];

        // 1. Basic information
        $perjanjian[] = "PERJANJIAN KERJASAMA";
        $perjanjian[] = "Nomor: " . $quotation->nomor;
        $perjanjian[] = "Pada hari ini " . Carbon::parse($quotation->tgl_quotation)->translatedFormat('l, d F Y') . " bertempat di " . $quotation->company;
        $perjanjian[] = "";

        // 2. Parties involved
        $perjanjian[] = "PIHAK PERTAMA:";
        $perjanjian[] = "Nama: " . $quotation->company;
        $perjanjian[] = "Alamat: [ALAMAT_PERUSAHAAN]";
        $perjanjian[] = "";

        $perjanjian[] = "PIHAK KEDUA:";
        $perjanjian[] = "Nama: " . $quotation->nama_perusahaan;
        $perjanjian[] = "Alamat: [ALAMAT_CLIENT]";
        $perjanjian[] = "";

        // 3. Service details
        $perjanjian[] = "BENTUK KERJASAMA:";
        $perjanjian[] = "Pihak Pertama akan menyediakan jasa " . $quotation->kebutuhan . " kepada Pihak Kedua";
        $perjanjian[] = "Jumlah personil: " . $quotation->jumlah_hc . " orang";
        $perjanjian[] = "Lokasi penempatan: " . $this->getSiteLocations($quotation);
        $perjanjian[] = "";

        // 4. Contract period
        $perjanjian[] = "JANGKA WAKTU PERJANJIAN:";
        $perjanjian[] = "Perjanjian ini berlaku mulai " . Carbon::parse($quotation->mulai_kontrak)->translatedFormat('d F Y') . " sampai dengan " . Carbon::parse($quotation->kontrak_selesai)->translatedFormat('d F Y');
        $perjanjian[] = "Durasi: " . $quotation->durasi_kerjasama;
        $perjanjian[] = "";

        // 5. Financial terms
        $perjanjian[] = "NILAI KONTRAK:";
        $perjanjian[] = "Total nilai kontrak: Rp " . number_format($quotation->total_invoice, 0, ',', '.');
        $perjanjian[] = "Management fee: " . $quotation->persentase . "%";
        $perjanjian[] = "Terms of payment: " . $quotation->top;
        $perjanjian[] = "";

        // 6. Additional clauses
        $perjanjian[] = "KETENTUAN LAIN-LAIN:";
        $perjanjian[] = "1. Perjanjian ini dapat diperpanjang dengan kesepakatan kedua belah pihak";
        $perjanjian[] = "2. Segala perubahan terhadap perjanjian ini harus dibuat secara tertulis";
        $perjanjian[] = "3. Penyelesaian perselisihan akan dilakukan melalui musyawarah";

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
    private function generateResubmitNomor($originalNomor)
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
}