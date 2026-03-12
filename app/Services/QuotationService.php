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
use App\Services\QuotationNotificationService;


class QuotationService
{
    protected $quotationNotificationService;

    public function __construct(

        QuotationNotificationService $quotationNotificationService,
        QuotationStepService $quotationStepService
    ) {

        $this->quotationNotificationService = $quotationNotificationService;
        $this->quotationStepService = $quotationStepService;
    }
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

        $detailIds = $quotationDetails->pluck('id')->all();

        // Preload HPP dan COSS sekaligus (1 query masing-masing, bukan N query per detail)
        // Di-index by quotation_detail_id agar lookup O(1) di dalam loop
        $quotation->_hpp_map = QuotationDetailHpp::whereIn('quotation_detail_id', $detailIds)
            ->get()->keyBy('quotation_detail_id');

        $quotation->_coss_map = QuotationDetailCoss::whereIn('quotation_detail_id', $detailIds)
            ->get()->keyBy('quotation_detail_id');

        $quotationSites = QuotationSite::where('quotation_id', $quotation->id)->get();

        // Index sites by ID agar QuotationSite::find() tidak dipanggil per-detail di loop
        $quotation->_sites_map = $quotationSites->keyBy('id');

        // Calculate site details count
        $quotationSites->each(function ($site) use ($quotationDetails) {
            $site->jumlah_detail = $quotationDetails
                ->where('quotation_site_id', $site->id)->count();
        });

        // Preload daftar tunjangan sekali — dipakai di calculateFirstPass DAN recalculateWithGrossUp
        $quotation->_daftar_tunjangan = QuotationDetailTunjangan::where('quotation_id', $quotation->id)
            ->distinct('nama_tunjangan')->get(['nama_tunjangan as nama']);

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
        // Gunakan _daftar_tunjangan yang sudah di-preload di loadQuotationData()
        $daftarTunjangan = $quotation->_daftar_tunjangan;

        // ✅ PERBAIKAN: Initialize semua detail TERLEBIH DAHULU sebelum calculateAllItems
        // Ini memastikan jumlah_hc_hpp dari HPP sudah di-set sebelum loop di calculateAllItems()
        $this->initializeAllDetails($quotation);

        $this->processAllDetails($quotation, $daftarTunjangan, $jumlahHc, $result);
        $this->calculateHpp($quotation, $jumlahHc, $quotation->provisi, $result);
        $this->calculateCoss($quotation, $jumlahHc, $quotation->provisi, $result);

        // HAPUS assignment yang tidak perlu ke model Quotation
        // $quotation->jumlah_hc = $jumlahHc; // JANGAN lakukan ini
        // $quotation->provisi = $this->calculateProvisi($quotation->durasi_kerjasama); // JANGAN lakukan ini
    }
    private function recalculateWithGrossUp($quotation, $jumlahHc, QuotationCalculationResult $result): void
    {
        // Gunakan _daftar_tunjangan yang sudah di-preload di loadQuotationData()
        $daftarTunjangan = $quotation->_daftar_tunjangan;

        // ✅ PERBAIKAN: Ensure semua detail sudah re-initialize dengan nilai HPP terbaru
        $this->initializeAllDetails($quotation);

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

            // Gunakan preloaded map dari loadQuotationData() — tidak ada query per-detail
            $hpp = $quotation->_hpp_map->get($detail->id);
            $coss = $quotation->_coss_map->get($detail->id);
            $site = $quotation->_sites_map->get($detail->quotation_site_id);
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

    /**
     * ✅ PERBAIKAN: Initialize semua detail SEKALIGUS di awal
     * Ini memastikan jumlah_hc_hpp dari HPP sudah di-set sebelum calculateAllItems() loop
     */
    private function initializeAllDetails($quotation): void
    {
        foreach ($quotation->quotation_detail as $detail) {
            $hpp = $quotation->_hpp_map->get($detail->id);

            // PERBAIKAN: Jumlah HC untuk HPP ambil dari HPP (Step 11 input)
            // Tapi untuk detail object, tetap gunakan yang dari detail (untuk COSS)
            $detail->jumlah_hc_original = $detail->jumlah_hc; // Simpan nilai asli untuk COSS
            $detail->jumlah_hc_hpp = $hpp && $hpp->jumlah_hc !== null ? (int) $hpp->jumlah_hc : $detail->jumlah_hc;

            \Log::debug('Initialized detail', [
                'detail_id' => $detail->id,
                'jumlah_hc_original' => $detail->jumlah_hc_original,
                'jumlah_hc_hpp' => $detail->jumlah_hc_hpp,
                'hpp_jumlah_hc_from_db' => $hpp?->jumlah_hc
            ]);
        }
    }

    private function initializeDetail($detail, $hpp, $site, $wage)
    {
        // ✅ PERBAIKAN: jumlah_hc_hpp sudah di-set di initializeAllDetails()
        // Di sini hanya set properties lainnya

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
            'tunjangan_hari_libur_nasional' => $detail->tunjangan_holiday_hpp ?? 0,
            'lembur' => $detail->lembur_hpp ?? 0,
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
            'tunjangan_hari_libur_nasional' => $detail->tunjangan_holiday_coss ?? 0,
            'lembur' => $detail->lembur_coss ?? 0,
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
            'total_personil_coss' => $detail->total_personil_coss ?? 0,
            'sub_total_personil_coss' => $detail->sub_total_personil_coss ?? 0,
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
            // Gunakan relasi yang sudah di-eager load — tidak ada query DB per-tunjangan
            $dtTunjangan = $detail->quotationDetailTunjangans
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

        $programBpjs = $quotation->program_bpjs ?? '';
        $isBpjsProgram = (stripos($programBpjs, 'BPJS') !== false)
            || ($programBpjs == 'Ya')
            || ($programBpjs == '1')
            || ($programBpjs == true)
            || ($programBpjs === '' || $programBpjs === null); // Support legacy: empty flag means active if not explicitly turned off

        if ($isBpjsProgram) {
            // FIX N+1: Gunakan $detail->umk / $detail->ump yang sudah di-set di initializeDetail()
            // dari preloaded _sites_map. Jangan akses $detail->quotationSite karena
            // itu lazy-load → 1 query DB per detail dalam loop!
            $umk = $detail->umk ?? 0;
            $ump = $detail->ump ?? 0;
            $nominalUpah = $detail->nominal_upah;

            // Base untuk BPJS Ketenagakerjaan: jika upah < UMP gunakan UMP, selain itu gunakan nominal upah
            $baseKetenagakerjaan = ($nominalUpah < $ump) ? $ump : $nominalUpah;

            // Base untuk BPJS Kesehatan: selalu gunakan UMK
            $baseKesehatan = $umk;

            $bpjsConfig = [
                'jkk' => ['field' => 'bpjs_jkk', 'percent' => 'persen_bpjs_jkk', 'default' => $this->getJkkPercentage($quotation->resiko), 'base' => $baseKetenagakerjaan],
                'jkm' => ['field' => 'bpjs_jkm', 'percent' => 'persen_bpjs_jkm', 'default' => 0.30, 'base' => $baseKetenagakerjaan],
                'jht' => ['field' => 'bpjs_jht', 'percent' => 'persen_bpjs_jht', 'default' => 3.70, 'base' => $baseKetenagakerjaan],
                'jp' => ['field' => 'bpjs_jp', 'percent' => 'persen_bpjs_jp', 'default' => 2.00, 'base' => $baseKetenagakerjaan],
                'kes' => ['field' => 'bpjs_kes', 'percent' => 'persen_bpjs_kes', 'default' => 4.00, 'base' => $baseKesehatan]
            ];

            foreach ($bpjsConfig as $key => $config) {
                $persentase = 0;
                $base = $config['base'];

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
                        ($optValue === "0" || $optValue === 0 || $optValue === false ||
                            (is_string($optValue) && strtolower(trim($optValue)) === 'tidak'))
                        && !($key === 'kes' && $detail->penjamin_kesehatan === 'BPJS') // FIX: Force active if explicitly BPJS Kesehatan
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
            // TUNJANGAN HARI RAYA (THR)
            $tunjanganHariRayaHpp = $hpp ? (float) ($hpp->tunjangan_hari_raya ?? 0) : 0;
            $tunjanganHariRayaCoss = $coss ? (float) ($coss->tunjangan_hari_raya ?? 0) : 0;

            if ($tunjanganHariRayaHpp == 0 && $wage && isset($wage->thr)) {
                $thrWageValue = strtolower(trim($wage->thr ?? 'Tidak Ada'));
                if (in_array($thrWageValue, ['diprovisikan'])) {
                    $tunjanganHariRayaHpp = ($detail->nominal_upah ?? 0) / 12;
                    $tunjanganHariRayaCoss = ($detail->nominal_upah ?? 0) / 12;
                }
            }

            // KOMPENSASI
            $kompensasiHpp = $hpp ? (float) ($hpp->kompensasi ?? 0) : 0;
            $kompensasiCoss = $coss ? (float) ($coss->kompensasi ?? 0) : 0;

            if ($kompensasiHpp == 0 && $wage && isset($wage->kompensasi)) {
                $kompensasiWageValue = strtolower(trim($wage->kompensasi ?? 'Tidak Ada'));
                if (in_array($kompensasiWageValue, ['diprovisikan'])) {
                    $kompensasiDefault = ($detail->nominal_upah ?? 0) / 12;
                    $kompensasiHpp = $kompensasiDefault;
                    $kompensasiCoss = $kompensasiDefault;
                }
            }

            // TUNJANGAN HOLIDAY (LIBUR NASIONAL)
            $tunjanganHolidayHpp = $hpp ? (float) ($hpp->tunjangan_hari_libur_nasional ?? 0) : 0;
            $tunjanganHolidayCoss = $coss ? (float) ($coss->tunjangan_hari_libur_nasional ?? 0) : 0;

            if ($tunjanganHolidayHpp == 0 && $wage && isset($wage->tunjangan_holiday)) {
                $tunjanganHolidayValue = strtolower(trim($wage->tunjangan_holiday ?? 'Tidak Ada'));
                if (str_contains($tunjanganHolidayValue, 'flat')) {
                    $calculated = $this->calculateTunjanganHolidayFromWage($wage);
                    $tunjanganHolidayHpp = $calculated;
                    $tunjanganHolidayCoss = $calculated;
                }
            }

            // LEMBUR
            $lemburHpp = $hpp ? (float) ($hpp->lembur ?? 0) : 0;
            $lemburCoss = $coss ? (float) ($coss->lembur ?? 0) : 0;

            if ($lemburHpp == 0 && $wage && isset($wage->lembur)) {
                $lemburValue = strtolower(trim($wage->lembur ?? 'Tidak Ada'));
                $lemburditagihkanValue = strtolower(trim($wage->lembur_ditagihkan ?? null));
                if (str_contains($lemburValue, 'flat')) {
                    $calculated = $this->calculateLemburFromWage($wage);
                    $lemburHpp = $calculated;
                    $lemburCoss = $calculated;
                }
            }

            // INSENTIF
            $insentifHpp = $hpp ? (float) ($hpp->insentif ?? 0) : 0;
            $insentifCoss = $coss ? (float) ($coss->insentif ?? 0) : 0;

            // Assign ke detail dengan prefix HPP/COSS
            $detail->tunjangan_hari_raya_hpp = round($tunjanganHariRayaHpp, 2);
            $detail->tunjangan_hari_raya_coss = round($tunjanganHariRayaCoss, 2);
            $detail->kompensasi_hpp = round($kompensasiHpp, 2);
            $detail->kompensasi_coss = round($kompensasiCoss, 2);
            $detail->tunjangan_holiday_hpp = round($tunjanganHolidayHpp, 2);
            $detail->tunjangan_holiday_coss = round($tunjanganHolidayCoss, 2);
            $detail->lembur_hpp = round($lemburHpp, 2);
            $detail->lembur_coss = round($lemburCoss, 2);
            $detail->insentif_hpp = round($insentifHpp, 2);
            $detail->insentif_coss = round($insentifCoss, 2);

            // Untuk backward compatibility
            $detail->tunjangan_hari_raya = $tunjanganHariRayaHpp;
            $detail->kompensasi = $kompensasiHpp;
            $detail->tunjangan_holiday = $tunjanganHolidayHpp;
            $detail->lembur = $lemburHpp;
            $detail->insentif = $insentifHpp;

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
        // FIX: Ganti `static` variables dengan instance property (_site_hc_cache).
        // `static` variables bertahan sepanjang proses PHP (shared antar request pada
        // server long-lived seperti FPM/Octane), menyebabkan cache stale untuk quotation
        // berbeda jika dipanggil dalam 1 siklus yang sama.

        if (!isset($this->_site_hc_cache) || $this->_site_hc_cache['quotation_id'] !== $quotation->id) {
            $siteHcHpp = [];
            $siteHcCoss = [];

            foreach ($quotation->quotation_detail as $det) {
                $siteId = $det->quotation_site_id;
                $siteHcHpp[$siteId] = ($siteHcHpp[$siteId] ?? 0) + $det->jumlah_hc_hpp;
                $siteHcCoss[$siteId] = ($siteHcCoss[$siteId] ?? 0) + $det->jumlah_hc_original;
            }

            $firstDetail = $quotation->quotation_detail->first();
            $this->_site_hc_cache = [
                'quotation_id' => $quotation->id,
                'site_hc_hpp' => $siteHcHpp,
                'site_hc_coss' => $siteHcCoss,
                'primary_site_id' => $firstDetail->quotation_site_id ?? null,
                'primary_detail_id' => $firstDetail->id ?? null,
            ];

            \Log::info("=== SITE HC TOTALS PRECOMPUTED ===", [
                'quotation_id' => $quotation->id,
                'site_hc_hpp' => $siteHcHpp,
                'site_hc_coss' => $siteHcCoss,
                'total_details' => $quotation->quotation_detail->count(),
            ]);
        }

        $cache = $this->_site_hc_cache;
        $currentSiteId = $detail->quotation_site_id;
        $primarySiteId = $cache['primary_site_id'];
        $primaryDetailId = $cache['primary_detail_id'];
        $totalJumlahHcHppSite = $cache['site_hc_hpp'][$currentSiteId] ?? 0;
        $totalJumlahHcCossSite = $cache['site_hc_coss'][$currentSiteId] ?? 0;
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
                'special' => 'device'
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
                'is_general' => true,  // Chemical punya rumus khusus
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
                        $config['special'] ?? null,
                        $detail->jumlah_hc_hpp,
                        $config['site_specific'] ? $currentSiteId : null,
                        ($detail->id === $primaryDetailId) // includeLegacy
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
                        $config['special'] ?? null,
                        $detail->jumlah_hc_original,
                        $config['site_specific'] ? $currentSiteId : null,
                        ($detail->id === $primaryDetailId) // includeLegacy
                    );
                }
                $detail->{"personil_{$key}_coss"} = $cossValue;
            }
        }
    }

    /**
     * Calculate item total khusus untuk HPP dengan filter site dan soft delete
     */
    private function calculateItemTotalForHpp($model, $quotationId, $detailId, $provisi, $divider = 1, $special = null, $jumlahHc = 1, $siteId = null, $includeLegacy = false)
    {
        // Query dengan filter soft delete
        $query = $model::whereNull('deleted_at');

        // Logic routing query Hybrid (v1 & v2 support)
        $query->where(function ($q) use ($quotationId, $detailId, $siteId, $includeLegacy) {
            // 1. Ambil data spesifik (Apps v2 logic)
            $q->where(function ($q2) use ($detailId, $siteId) {
                if ($detailId) {
                    $q2->where('quotation_detail_id', $detailId);
                } elseif ($siteId !== null) {
                    $q2->where('quotation_site_id', $siteId);
                } else {
                    $q2->where('id', 0); // Failsafe agar tidak narik semua jika ID kosong
                }
            });

            // 2. ATAU Ambil data Global/Legacy (Apps v1 logic)
            // Hanya jika ini site/detail utama, ambil data yang site/detail-nya NULL
            if ($includeLegacy) {
                $q->orWhere(function ($q2) use ($quotationId) {
                    $q2->where('quotation_id', $quotationId)
                        ->whereNull('quotation_detail_id')
                        ->whereNull('quotation_site_id');
                });
            }
        });

        $items = $query->get();
        if ($items->isEmpty()) {
            return 0;
        }

        $total = 0;
        foreach ($items as $item) {
            if ($special === 'chemical') {
                // 1. Hitung total biaya bulanan
                $itemTotal = (($item->jumlah * $item->harga) / $item->masa_pakai);

                $perPerson = $itemTotal / max($divider, 1);

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

    private function calculateItemTotalForCoss($model, $quotationId, $detailId, $provisi, $divider = 1, $special = null, $jumlahHc = 1, $siteId = null, $includeLegacy = false)
    {
        // Query dengan filter soft delete
        $query = $model::whereNull('deleted_at');

        // Logic routing query Hybrid (v1 & v2 support)
        $query->where(function ($q) use ($quotationId, $detailId, $siteId, $includeLegacy) {
            // 1. Ambil data spesifik (Apps v2 logic)
            $q->where(function ($q2) use ($detailId, $siteId) {
                if ($detailId) {
                    $q2->where('quotation_detail_id', $detailId);
                } elseif ($siteId !== null) {
                    $q2->where('quotation_site_id', $siteId);
                } else {
                    $q2->where('id', 0); // Failsafe
                }
            });

            // 2. ATAU Ambil data Global/Legacy (Apps v1 logic)
            if ($includeLegacy) {
                $q->orWhere(function ($q2) use ($quotationId) {
                    $q2->where('quotation_id', $quotationId)
                        ->whereNull('quotation_detail_id')
                        ->whereNull('quotation_site_id');
                });
            }
        });

        $items = $query->get();
        if ($items->isEmpty()) {
            return 0;
        }

        $total = 0;
        foreach ($items as $item) {
            if ($special === 'chemical') {
                $itemTotal = ((($item->jumlah * $item->harga) / $item->masa_pakai));
                $perPerson = $itemTotal / max($divider, 1);
                $total += $perPerson;
            } elseif ($special === 'kaporlap') {
                // Untuk kaporlap: dikali dengan HC, bukan dibagi
                // PERBAIKAN: Jangan dikali HC di sini karena calculateFinalTotals akan mengali HC lagi di akhir (Double Multiplication)
                $itemTotal = (($item->harga * $item->jumlah) / $provisi);
                //$perPerson = $itemTotal * max($jumlahHc, 1); // DIKALI, bukan dibagi
                $perPerson = $itemTotal; // Tetap per personil agar di akhir dikali HC satu kali saja
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
            $nominalUpah = (float) ($detail->nominal_upah ?? 0);
            ;
            $tunjanganHoliday = (float) ($detail->tunjangan_holiday_hpp ?? 0);
            $lembur = (float) ($detail->lembur_hpp ?? 0);
            $tunjanganHolidayCoss = (float) ($detail->tunjangan_holiday_coss ?? 0);
            $lemburCoss = (float) ($detail->lembur_coss ?? 0);

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
                + $tunjanganHolidayCoss
                + $lemburCoss
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

        // Pastikan persen_bunga_bank sebagai float
        $persenBungaBank = (float) $quotation->persen_bunga_bank;
        if ($quotation->top == "Non TOP") {
            $persenBungaBank = 0;
        }

        $summary->bunga_bank_total = $persenBungaBank > 0
            ? $summary->total_sebelum_management_fee * ($persenBungaBank / 100) / $jumlahHc
            : 0;

        // Pastikan persen_insentif sebagai float
        $persenInsentif = (float) $quotation->persen_insentif;
        // insentif_total disimpan tanpa dibagi HC — pembagian per jumlah_hc_hpp
        // dilakukan di updateDetailsWithGrossUp karena tiap detail bisa beda HC-nya.
        $summary->insentif_total = $persenInsentif > 0
            ? $summary->nominal_management_fee * ($persenInsentif / 100)
            : 0;
    }

    private function updateDetailsWithGrossUp($quotation, $daftarTunjangan, $jumlahHc, QuotationCalculationResult $result): void
    {
        $summary = $result->calculation_summary;

        $quotation->quotation_detail->each(function ($detail) use ($quotation, $summary, $daftarTunjangan, $result) {
            $detail->bunga_bank = $summary->bunga_bank_total;
            $jumlahHcDetail = max((int) ($detail->jumlah_hc_hpp ?? $detail->jumlah_hc), 1);
            $detail->insentif = $summary->insentif_total > 0
                ? round($summary->insentif_total / $jumlahHcDetail, 10)
                : 0;

            // Gunakan preloaded map — tidak ada query DB per-detail
            $hpp = $quotation->_hpp_map->get($detail->id);
            $coss = $quotation->_coss_map->get($detail->id);

            $totalTunjanganResult = [
                'total' => $detail->total_tunjangan ?? 0,
                'total_coss' => $detail->total_tunjangan_coss ?? 0
            ];

            $this->calculateFinalTotals($detail, $quotation, $totalTunjanganResult, $hpp, $coss);

            // ✅ PERBARUI DTO DENGAN NILAI TERBARU
            if (isset($result->detail_calculations[$detail->id])) {
                $dto = $result->detail_calculations[$detail->id];

                // Update HPP data
                $dto->hpp_data['bunga_bank'] = $detail->bunga_bank;
                $dto->hpp_data['insentif'] = $detail->insentif;
                $dto->hpp_data['total_biaya_per_personil'] = $detail->total_personil;
                $dto->hpp_data['total_biaya_all_personil'] = $detail->sub_total_personil;

                // Update COSS data
                $dto->coss_data['bunga_bank'] = $detail->bunga_bank;
                $dto->coss_data['insentif'] = $detail->insentif;
                $dto->coss_data['total_personil_coss'] = $detail->total_personil_coss ?? 0;
                $dto->coss_data['sub_total_personil_coss'] = $detail->sub_total_personil_coss ?? 0;
            }
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
        $summary->{"ppn{$suffix}"} = 0;
        $summary->{"pph{$suffix}"} = 0;

        $this->calculateDefaultTaxes($quotation, $suffix, $result);
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
                if (
                    ($optValue === "0" || $optValue === 0 || $optValue === false || $optValue === "false" ||
                        (is_string($optValue) && strtolower(trim($optValue)) === 'tidak'))
                    && !($optField === 'is_bpjs_kes' && $detail->penjamin_kesehatan === 'BPJS') // FIX: Force active if explicitly BPJS Kesehatan
                ) {
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


}