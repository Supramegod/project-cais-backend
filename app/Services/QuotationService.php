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
    protected $quotationStepService;

    private const PKHL_DEFAULT_HARI_KERJA = 25;


    private array $_management_fee_cache = [];

    public function __construct(
        QuotationNotificationService $quotationNotificationService,
        QuotationStepService $quotationStepService
    ) {
        $this->quotationNotificationService = $quotationNotificationService;
        $this->quotationStepService = $quotationStepService;
    }

    // ============================ MAIN CALCULATION FLOW ============================

    public function calculateQuotation($quotation): QuotationCalculationResult
    {
        try {
            $result = new QuotationCalculationResult($quotation);

            $this->initializeQuotation($quotation);

            $this->loadQuotationData($quotation);

            $this->ensureAllWagesExist($quotation);

            if ($quotation->quotation_detail->isEmpty()) {
                return $result;
            }

            $jumlahHc = $quotation->quotation_detail->sum('jumlah_hc');
            $quotation->jumlah_hc = $jumlahHc;
            $quotation->provisi = $this->calculateProvisi($quotation->durasi_kerjasama);

            $this->initializeAllDetails($quotation);

            $this->calculateFirstPass($quotation, $jumlahHc, $result);
            $this->recalculateWithGrossUp($quotation, $jumlahHc, $result);

            return $result;

        } catch (\Exception $e) {
            \Log::error("Error in calculateQuotation: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    // ============================ INITIALIZATION ============================

    private function initializeQuotation($quotation): void
    {
        // Placeholder — tidak ada side effect yang diperlukan saat ini.
    }


    private function loadQuotationData($quotation): void
    {
        $quotationDetails = QuotationDetail::with(['wage', 'quotationDetailTunjangans'])
            ->where('quotation_id', $quotation->id)
            ->get();

        $detailIds = $quotationDetails->pluck('id')->all();
        $quotationSites = QuotationSite::where('quotation_id', $quotation->id)->get();
        $siteIds = $quotationSites->pluck('id')->all();

        // ── Preload HPP & COSS ─────────────────────────────────────────────
        $quotation->_hpp_map = QuotationDetailHpp::whereIn('quotation_detail_id', $detailIds)
            ->get()->keyBy('quotation_detail_id');

        $quotation->_coss_map = QuotationDetailCoss::whereIn('quotation_detail_id', $detailIds)
            ->get()->keyBy('quotation_detail_id');

        // ── Index sites by ID ──────────────────────────────────────────────
        $quotation->_sites_map = $quotationSites->keyBy('id');

        $quotationSites->each(function ($site) use ($quotationDetails) {
            $site->jumlah_detail = $quotationDetails
                ->where('quotation_site_id', $site->id)->count();
        });

        // ── Daftar tunjangan unik ──────────────────────────────────────────
        $quotation->_daftar_tunjangan = QuotationDetailTunjangan::where('quotation_id', $quotation->id)
            ->distinct('nama_tunjangan')->get(['nama_tunjangan as nama']);

        // ── ManagementFee (gunakan instance cache) ─────────────────────────
        $mfId = $quotation->management_fee_id;
        if (!isset($this->_management_fee_cache[$mfId])) {
            $mf = ManagementFee::find($mfId);
            $this->_management_fee_cache[$mfId] = $mf->nama ?? '';
        }
        $quotation->management_fee = $this->_management_fee_cache[$mfId];


        // Kaporlap: scope per quotation_detail_id
        $quotation->_kaporlap_items = QuotationKaporlap::whereNull('deleted_at')
            ->where(function ($q) use ($quotation, $detailIds) {
                $q->whereIn('quotation_detail_id', $detailIds)
                    ->orWhere(function ($q2) use ($quotation) {
                        $q2->where('quotation_id', $quotation->id)
                            ->whereNull('quotation_detail_id');
                    });
            })
            ->get()
            ->groupBy(fn($item) => $item->quotation_detail_id ?? '__legacy__');

        // Devices: scope per quotation_site_id
        $quotation->_devices_items = QuotationDevices::whereNull('deleted_at')
            ->where(function ($q) use ($quotation, $siteIds) {
                $q->whereIn('quotation_site_id', $siteIds)
                    ->orWhere(function ($q2) use ($quotation) {
                        $q2->where('quotation_id', $quotation->id)
                            ->whereNull('quotation_detail_id')
                            ->whereNull('quotation_site_id');
                    });
            })
            ->get()
            ->groupBy(fn($item) => $item->quotation_site_id ?? '__legacy__');

        // OHC: scope per quotation_site_id
        $quotation->_ohc_items = QuotationOhc::whereNull('deleted_at')
            ->where(function ($q) use ($quotation, $siteIds) {
                $q->whereIn('quotation_site_id', $siteIds)
                    ->orWhere(function ($q2) use ($quotation) {
                        $q2->where('quotation_id', $quotation->id)
                            ->whereNull('quotation_detail_id')
                            ->whereNull('quotation_site_id');
                    });
            })
            ->get()
            ->groupBy(fn($item) => $item->quotation_site_id ?? '__legacy__');

        // Chemical: scope per quotation_site_id
        $quotation->_chemical_items = QuotationChemical::whereNull('deleted_at')
            ->where(function ($q) use ($quotation, $siteIds) {
                $q->whereIn('quotation_site_id', $siteIds)
                    ->orWhere(function ($q2) use ($quotation) {
                        $q2->where('quotation_id', $quotation->id)
                            ->whereNull('quotation_detail_id')
                            ->whereNull('quotation_site_id');
                    });
            })
            ->get()
            ->groupBy(fn($item) => $item->quotation_site_id ?? '__legacy__');

        $quotation->quotation_detail = $quotationDetails;
        $quotation->quotation_site = $quotationSites;
    }

    private function ensureAllWagesExist($quotation): void
    {
        $detailsWithoutWage = $quotation->quotation_detail->filter(fn($d) => !$d->wage);

        if ($detailsWithoutWage->isEmpty()) {
            return;
        }

        $createdBy = Auth::user()->full_name ?? 'system';
        $now = now();

        // Satu kali INSERT untuk semua detail yang tidak punya wage
        $rows = $detailsWithoutWage->map(fn($detail) => [
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
            'created_by' => $createdBy,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();

        QuotationDetailWage::insert($rows);

        // Reload semua wages sekaligus (1 query whereIn)
        $detailIds = $quotation->quotation_detail->pluck('id');
        $wagesById = QuotationDetailWage::whereIn('quotation_detail_id', $detailIds)
            ->get()
            ->keyBy('quotation_detail_id');

        // Pasang kembali relasi ke setiap detail tanpa query tambahan
        $quotation->quotation_detail->each(function ($detail) use ($wagesById) {
            if (!$detail->wage) {
                $detail->setRelation('wage', $wagesById->get($detail->id));
            }
        });
    }

    // ============================ CORE CALCULATION METHODS ============================

    private function calculateFirstPass($quotation, $jumlahHc, QuotationCalculationResult $result): void
    {
        $daftarTunjangan = $quotation->_daftar_tunjangan;

        $this->processAllDetails($quotation, $daftarTunjangan, $jumlahHc, $result);
        $this->calculateHpp($quotation, $jumlahHc, $quotation->provisi, $result);
        $this->calculateCoss($quotation, $jumlahHc, $quotation->provisi, $result);
    }

    private function recalculateWithGrossUp($quotation, $jumlahHc, QuotationCalculationResult $result): void
    {
        $daftarTunjangan = $quotation->_daftar_tunjangan;


        $this->calculateBankInterestAndIncentive($quotation, $jumlahHc, $result);
        $this->updateDetailsWithGrossUp($quotation, $daftarTunjangan, $jumlahHc, $result);

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
                \Log::warning("Skipped detail {$detail->id}: " . $e->getMessage());
            }
        });
    }

    private function processSingleDetail($detail, $quotation, $daftarTunjangan, $jumlahHc, QuotationCalculationResult $result): void
    {
        $detailCalculation = new DetailCalculation($detail->id);

        $hpp = $quotation->_hpp_map->get($detail->id);
        $coss = $quotation->_coss_map->get($detail->id);
        $site = $quotation->_sites_map->get($detail->quotation_site_id);
        $wage = $detail->wage;

        if (!$wage) {
            $wage = $this->makeEmptyWageObject();
        }

        $this->initializeDetail($detail, $hpp, $site, $wage, $quotation);
        $this->calculateDetailComponents($detail, $quotation, $daftarTunjangan, $jumlahHc, $hpp, $coss, $wage, $detailCalculation);

        $result->detail_calculations[$detail->id] = $detailCalculation;
    }


    private function initializeAllDetails($quotation): void
    {
        $debugData = [];

        foreach ($quotation->quotation_detail as $detail) {
            $hpp = $quotation->_hpp_map->get($detail->id);

            $detail->jumlah_hc_original = $detail->jumlah_hc;
            $detail->jumlah_hc_hpp = $hpp && $hpp->jumlah_hc !== null
                ? (int) $hpp->jumlah_hc
                : $detail->jumlah_hc;

            // Kumpulkan debug data — hanya ditulis jika LOG_LEVEL=debug
            if (config('logging.level') === 'debug') {
                $debugData[] = [
                    'detail_id' => $detail->id,
                    'jumlah_hc_original' => $detail->jumlah_hc_original,
                    'jumlah_hc_hpp' => $detail->jumlah_hc_hpp,
                ];
            }
        }

        // Satu log entry untuk semua detail (bukan N entry)
        \Log::debug('Initialized all details', [
            'quotation_id' => $quotation->id,
            'total' => $quotation->quotation_detail->count(),
            'details' => $debugData,  // [] jika bukan mode debug
        ]);
    }

    private function initializeDetail($detail, $hpp, $site, $wage, $quotation): void
    {
        $detail->nominal_upah = $detail->nominal_upah ?? $hpp->gaji_pokok ?? $site->nominal_upah;
        $detail->umk = $site->umk ?? 0;
        $detail->ump = $site->ump ?? 0;

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

        $this->normalizeUpahForKontrak($detail, $quotation);
    }

    private function calculateDetailComponents($detail, $quotation, $daftarTunjangan, $jumlahHc, $hpp, $coss, $wage, DetailCalculation $detailCalculation): void
    {
        $totalTunjangan = $this->calculateTunjangan($detail, $daftarTunjangan);
        $this->calculateBpjs($detail, $quotation, $hpp);
        $this->calculateExtras($detail, $quotation, $hpp, $coss, $wage);
        $this->calculateAllItems($detail, $quotation, $jumlahHc, $hpp, $coss);
        $this->calculateFinalTotals($detail, $quotation, $totalTunjangan, $hpp, $coss);
        $this->populateDetailCalculation($detail, $quotation, $detailCalculation);
    }

    // ============================ ITEM CALCULATIONS (CORE REFACTOR) ============================


    private function calculateAllItems($detail, $quotation, $totalJumlahHc, $hpp, $coss): void
    {
        // ── OPTIMASI #4: Pre-compute site & global HC totals sekali per quotation ──
        if (!isset($this->_site_hc_cache) || $this->_site_hc_cache['quotation_id'] !== $quotation->id) {
            $siteHcHpp = [];
            $siteHcCoss = [];
            $globalHpp = 0;
            $globalCoss = 0;

            foreach ($quotation->quotation_detail as $det) {
                $sid = $det->quotation_site_id;
                $siteHcHpp[$sid] = ($siteHcHpp[$sid] ?? 0) + $det->jumlah_hc_hpp;
                $siteHcCoss[$sid] = ($siteHcCoss[$sid] ?? 0) + $det->jumlah_hc_original;
                $globalHpp += $det->jumlah_hc_hpp;
                $globalCoss += $det->jumlah_hc_original;
            }

            $firstDetail = $quotation->quotation_detail->first();
            $this->_site_hc_cache = [
                'quotation_id' => $quotation->id,
                'site_hc_hpp' => $siteHcHpp,
                'site_hc_coss' => $siteHcCoss,
                'global_hpp' => $globalHpp,   // ← PRE-COMPUTED, bukan sum() per-loop
                'global_coss' => $globalCoss,  // ← PRE-COMPUTED, bukan sum() per-loop
                'primary_site_id' => $firstDetail->quotation_site_id ?? null,
                'primary_detail_id' => $firstDetail->id ?? null,
            ];

            \Log::info("Site HC totals precomputed", [
                'quotation_id' => $quotation->id,
                'site_hc_hpp' => $siteHcHpp,
                'site_hc_coss' => $siteHcCoss,
                'total_details' => $quotation->quotation_detail->count(),
            ]);
        }

        $cache = $this->_site_hc_cache;
        $currentSiteId = $detail->quotation_site_id;
        $primaryDetailId = $cache['primary_detail_id'];

        $totalJumlahHcHppSite = $cache['site_hc_hpp'][$currentSiteId] ?? 0;
        $totalJumlahHcCossSite = $cache['site_hc_coss'][$currentSiteId] ?? 0;

        // ── Mapping config untuk 4 item types ─────────────────────────────────
        $items = [
            'kaporlap' => [
                'hpp_field' => 'provisi_seragam',
                'coss_field' => 'provisi_seragam',
                'preload_key' => '_kaporlap_items',
                'group_by' => 'detail',          // group by detail_id
                'is_general' => false,
                'site_specific' => false,
                'special' => 'kaporlap',
            ],
            'devices' => [
                'hpp_field' => 'provisi_peralatan',
                'coss_field' => 'provisi_peralatan',
                'preload_key' => '_devices_items',
                'group_by' => 'site',            // group by site_id
                'is_general' => true,
                'site_specific' => true,
                'special' => 'device',
            ],
            'ohc' => [
                'hpp_field' => 'provisi_ohc',
                'coss_field' => 'provisi_ohc',
                'preload_key' => '_ohc_items',
                'group_by' => 'site',
                'is_general' => true,
                'site_specific' => true,
                'special' => null,
            ],
            'chemical' => [
                'hpp_field' => 'provisi_chemical',
                'coss_field' => 'provisi_chemical',
                'preload_key' => '_chemical_items',
                'group_by' => 'site',
                'is_general' => true,
                'site_specific' => true,
                'special' => 'chemical',
            ],
        ];

        foreach ($items as $key => $config) {
            // ── Tentukan divider ────────────────────────────────────────────
            if ($config['is_general'] && $config['site_specific']) {
                $hppDivider = $totalJumlahHcHppSite;
                $cossDivider = $totalJumlahHcCossSite;
            } elseif ($config['is_general'] && !$config['site_specific']) {
                $hppDivider = $cache['global_hpp'];   // O(1) lookup
                $cossDivider = $cache['global_coss'];  // O(1) lookup
            } else {
                $hppDivider = $detail->jumlah_hc_hpp;
                $cossDivider = $detail->jumlah_hc_original;
            }

            $hppDivider = max($hppDivider, 1);
            $cossDivider = max($cossDivider, 1);

            // ── Cek nilai manual dari HPP / COSS ───────────────────────────
            $hppManualValue = ($hpp && $hpp->{$config['hpp_field']} !== null) ? (float) $hpp->{$config['hpp_field']} : null;
            $cossManualValue = ($coss && $coss->{$config['coss_field']} !== null) ? (float) $coss->{$config['coss_field']} : null;

            // ── Ambil items dari preloaded collection (ZERO DB query) ───────
            $includeLegacy = ($detail->id === $primaryDetailId);

            if ($config['group_by'] === 'detail') {
                $loadedItems = $quotation->{$config['preload_key']}->get($detail->id, collect());
                if ($includeLegacy) {
                    $legacyItems = $quotation->{$config['preload_key']}->get('__legacy__', collect());
                    $loadedItems = $loadedItems->merge($legacyItems);
                }
            } else {
                // group_by = 'site'
                $loadedItems = $quotation->{$config['preload_key']}->get($currentSiteId, collect());
                if ($includeLegacy) {
                    $legacyItems = $quotation->{$config['preload_key']}->get('__legacy__', collect());
                    $loadedItems = $loadedItems->merge($legacyItems);
                }
            }

            // ── HPP value ──────────────────────────────────────────────────
            $detail->{"personil_$key"} = $hppManualValue !== null
                ? $hppManualValue
                : $this->computeItemValue($loadedItems, $config['special'], $hppDivider, $quotation->provisi, $detail->jumlah_hc_hpp);

            // ── COSS value ─────────────────────────────────────────────────
            $detail->{"personil_{$key}_coss"} = $cossManualValue !== null
                ? $cossManualValue
                : $this->computeItemValue($loadedItems, $config['special'], $cossDivider, $quotation->provisi, $detail->jumlah_hc_original);
        }
    }


    private function computeItemValue(
        \Illuminate\Support\Collection $items,
        ?string $special,
        int $divider,
        int $provisi,
        int $jumlahHc
    ): float {
        if ($items->isEmpty()) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($items as $item) {
            if ($special === 'chemical') {
                $itemTotal = ($item->jumlah * $item->harga) / $item->masa_pakai;
                $total += $itemTotal / max($divider, 1);
            } elseif ($special === 'kaporlap') {
                $itemTotal = ($item->harga * $item->jumlah) / $provisi;
                $total += $itemTotal;  // per personil, akan dikali HC di calculateFinalTotals
            } else {
                $itemTotal = ($item->harga * $item->jumlah) / $provisi;
                $total += $itemTotal / max($divider, 1);
            }
        }

        return $total;
    }

    // ============================ SEMUA METHOD LAIN (TIDAK BERUBAH) ============================
    // Method di bawah ini tidak diubah karena sudah benar secara logika dan
    // performanya sudah O(1) per-detail (menggunakan data yang sudah di-preload).

    private function populateDetailCalculation($detail, $quotation, DetailCalculation $detailCalculation): void
    {
        $potonganBpu = ($detail->penjamin_kesehatan === 'BPU') ? 16800 : 0;

        $detailCalculation->hpp_data = [
            'quotation_detail_id' => $detail->id,
            'quotation_id' => $quotation->id,
            'leads_id' => $quotation->leads_id,
            'position_id' => $detail->position_id,
            'jumlah_hc' => $detail->jumlah_hc_hpp,
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
            'potongan_bpu' => $potonganBpu,
            'total_biaya_per_personil' => $detail->total_personil ?? 0,
            'total_biaya_all_personil' => $detail->sub_total_personil ?? 0,
        ];

        $detailCalculation->coss_data = [
            'quotation_detail_id' => $detail->id,
            'quotation_id' => $quotation->id,
            'leads_id' => $quotation->leads_id,
            'position_id' => $detail->position_id,
            'jumlah_hc' => $detail->jumlah_hc_original,
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
            'potongan_bpu' => $potonganBpu,
        ];
    }

    // ============================ COMPONENT CALCULATIONS (TIDAK BERUBAH) ============================

    private function calculateTunjangan($detail, $daftarTunjangan): array
    {
        $totalTunjangan = 0;
        $totalTunjanganCoss = 0;

        foreach ($daftarTunjangan as $tunjangan) {
            $dtTunjangan = $detail->quotationDetailTunjangans
                ->where('nama_tunjangan', $tunjangan->nama)->first();

            $value = $dtTunjangan && is_numeric($dtTunjangan->nominal) ? (float) $dtTunjangan->nominal : 0.0;
            $valuecoss = $dtTunjangan && is_numeric($dtTunjangan->nominal_coss) ? (float) $dtTunjangan->nominal_coss : 0.0;

            $detail->{$tunjangan->nama} = $value;
            $totalTunjangan += $value;
            $totalTunjanganCoss += $valuecoss;
        }

        $detail->total_tunjangan = $totalTunjangan;
        $detail->total_tunjangan_coss = $totalTunjanganCoss;

        return ['total' => $totalTunjangan, 'total_coss' => $totalTunjanganCoss];
    }

    private function calculateBpjs($detail, $quotation, $hpp): void
    {
        // 1. Cek jika Penjamin Kesehatan adalah BPU (Bukan Penerima Upah)
        // Jika BPU, semua BPJS dipaksa nol.
        if ($detail->penjamin_kesehatan === 'BPU') {
            foreach ([
                'bpjs_jkk',
                'bpjs_jkm',
                'bpjs_jht',
                'bpjs_jp',
                'bpjs_kes',
                'persen_bpjs_jkk',
                'persen_bpjs_jkm',
                'persen_bpjs_jht',
                'persen_bpjs_jp',
                'persen_bpjs_kes'
            ] as $f) {
                $detail->{$f} = 0;
            }
            $this->updateQuotationBpjs($detail, $quotation);
            return;
        }

        // 2. Cek apakah Program BPJS di Quotation aktif
        $programBpjs = $quotation->program_bpjs ?? '';
        $isBpjsProgram = (stripos($programBpjs, 'BPJS') !== false)
            || in_array($programBpjs, ['Ya', '1', true, '', null], true);

        if (!$isBpjsProgram) {
            \Log::warning("BPJS program is NOT ACTIVE", ['program' => $programBpjs]);
            foreach ([
                'bpjs_jkk',
                'bpjs_jkm',
                'bpjs_jht',
                'bpjs_jp',
                'bpjs_kes',
                'persen_bpjs_jkk',
                'persen_bpjs_jkm',
                'persen_bpjs_jht',
                'persen_bpjs_jp',
                'persen_bpjs_kes'
            ] as $f) {
                $detail->{$f} = 0;
            }
            $this->updateQuotationBpjs($detail, $quotation);
            return;
        }

        // 3. Tentukan Dasar Perhitungan (Base)
        $nominalUpah = $detail->nominal_upah_bulanan ?? $detail->nominal_upah;
        $umk = $detail->umk ?? 0;
        $ump = $detail->ump ?? 0;

        // Dasar Ketenagakerjaan pakai UMP jika upah di bawah UMP
        $baseKetenagakerjaan = ($nominalUpah < $ump) ? $ump : $nominalUpah;
        // Dasar Kesehatan pakai UMK jika upah di bawah UMK
        $baseKesehatan = ($nominalUpah < $umk) ? $umk : $nominalUpah;

        // Configuration Map
        $bpjsConfig = [
            'jkk' => ['field' => 'bpjs_jkk', 'percent' => 'persen_bpjs_jkk', 'default' => $this->getJkkPercentage($quotation->resiko), 'base' => $baseKetenagakerjaan],
            'jkm' => ['field' => 'bpjs_jkm', 'percent' => 'persen_bpjs_jkm', 'default' => 0.30, 'base' => $baseKetenagakerjaan],
            'jht' => ['field' => 'bpjs_jht', 'percent' => 'persen_bpjs_jht', 'default' => 3.70, 'base' => $baseKetenagakerjaan],
            'jp' => ['field' => 'bpjs_jp', 'percent' => 'persen_bpjs_jp', 'default' => 2.00, 'base' => $baseKetenagakerjaan],
            'kes' => [
                'field' => 'bpjs_kes',
                'hpp_field' => 'bpjs_ks',
                'percent' => 'persen_bpjs_kes',
                'default' => 4.00,
                'base' => $baseKesehatan
            ],
        ];

        foreach ($bpjsConfig as $key => $config) {
            $persentase = 0.0;
            $base = $config['base'];
            $optOutField = 'is_bpjs_' . $key;
            $hppField = $config['hpp_field'] ?? ('bpjs_' . $key); // Asumsi di HPP namanya: bpjs_jkk, bpjs_jkm, bpjs_kes, dll.

            // A. Tentukan Persentase
            if (isset($detail->{$config['percent']}) && (float) $detail->{$config['percent']} != 0) {
                $persentase = (float) $detail->{$config['percent']};
            } elseif ($hpp && isset($hpp->{$config['percent']}) && (float) $hpp->{$config['percent']} != 0) {
                $persentase = (float) $hpp->{$config['percent']};
            } else {
                $persentase = $config['default'];
            }

            // B. Cek Opt-Out (Jika User memilih "Tidak" untuk program tertentu)
            $isOptOut = false;
            if (isset($detail->{$optOutField})) {
                $optValue = $detail->{$optOutField};
                if (
                    ($optValue === "0" || $optValue === 0 || $optValue === false ||
                        (is_string($optValue) && strtolower(trim($optValue)) === 'tidak'))
                    && !($key === 'kes' && $detail->penjamin_kesehatan === 'BPJS')
                ) {
                    $isOptOut = true;
                }
            }

            // C. Eksekusi Pengisian Nilai Nominal
            if ($isOptOut) {
                $detail->{$config['field']} = 0;
                $detail->{$config['percent']} = 0;
            } elseif ($key === 'kes' && in_array($detail->penjamin_kesehatan, ["Asuransi Swasta", "Takaful"])) {
                // Khusus Kesehatan jika menggunakan provider non-BPJS
                $detail->{$config['field']} = $detail->nominal_takaful ?? 0;
                $detail->{$config['percent']} = 0;
            } elseif ($hpp && isset($hpp->{$hppField}) && (float) $hpp->{$hppField} > 0) {
                // PRIORITAS: Ambil nominal langsung dari HPP jika tersedia
                $detail->{$config['field']} = (float) $hpp->{$hppField};
                $detail->{$config['percent']} = $persentase;
            } else {
                // FALLBACK: Hitung otomatis (Base * Persentase / 100)
                $detail->{$config['field']} = ($base * $persentase) / 100;
                $detail->{$config['percent']} = $persentase;
            }
        }

        $this->applyBpjsOptOut($detail);
        $this->updateQuotationBpjs($detail, $quotation);
    }

    private function calculateExtras($detail, $quotation, $hpp, $coss, $wage): void
    {
        try {
            $baseUpahBulanan = $detail->nominal_upah_bulanan ?? $detail->nominal_upah;

            // THR
            $tunjanganHariRayaHpp = $hpp ? (float) ($hpp->tunjangan_hari_raya ?? 0) : 0;
            $tunjanganHariRayaCoss = $coss ? (float) ($coss->tunjangan_hari_raya ?? 0) : 0;

            if ($tunjanganHariRayaHpp == 0 && $wage && isset($wage->thr)) {
                $thrWageValue = strtolower(trim($wage->thr ?? 'Tidak Ada'));
                if (in_array($thrWageValue, ['diprovisikan'])) {
                    $tunjanganHariRayaHpp = $baseUpahBulanan / 12;
                    $tunjanganHariRayaCoss = $baseUpahBulanan / 12;
                }
            }

            // KOMPENSASI
            $kompensasiHpp = $hpp ? (float) ($hpp->kompensasi ?? 0) : 0;
            $kompensasiCoss = $coss ? (float) ($coss->kompensasi ?? 0) : 0;

            if ($kompensasiHpp == 0 && $wage && isset($wage->kompensasi)) {
                if (in_array(strtolower(trim($wage->kompensasi ?? 'Tidak Ada')), ['diprovisikan'])) {
                    $kompensasiHpp = $baseUpahBulanan / 12;
                    $kompensasiCoss = $baseUpahBulanan / 12;
                }
            }

            // TUNJANGAN HOLIDAY
            $tunjanganHolidayHpp = $hpp ? (float) ($hpp->tunjangan_hari_libur_nasional ?? 0) : 0;
            $tunjanganHolidayCoss = $coss ? (float) ($coss->tunjangan_hari_libur_nasional ?? 0) : 0;

            if ($tunjanganHolidayHpp == 0 && $wage && isset($wage->tunjangan_holiday)) {
                if (str_contains(strtolower(trim($wage->tunjangan_holiday ?? 'Tidak Ada')), 'flat')) {
                    $calculated = $this->calculateTunjanganHolidayFromWage($wage);
                    $tunjanganHolidayHpp = $calculated;
                    $tunjanganHolidayCoss = $calculated;
                }
            }

            // LEMBUR
            $lemburHpp = $hpp ? (float) ($hpp->lembur ?? 0) : 0;
            $lemburCoss = $coss ? (float) ($coss->lembur ?? 0) : 0;

            if ($lemburHpp == 0 && $wage && isset($wage->lembur)) {
                if (str_contains(strtolower(trim($wage->lembur ?? 'Tidak Ada')), 'flat')) {
                    $calculated = $this->calculateLemburFromWage($wage);
                    $lemburHpp = $calculated;
                    $lemburCoss = $calculated;
                }
            }

            // INSENTIF
            $insentifHpp = $hpp ? (float) ($hpp->insentif ?? 0) : 0;
            $insentifCoss = $coss ? (float) ($coss->insentif ?? 0) : 0;

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

            // Backward compatibility
            $detail->tunjangan_hari_raya = $tunjanganHariRayaHpp;
            $detail->kompensasi = $kompensasiHpp;
            $detail->tunjangan_holiday = $tunjanganHolidayHpp;
            $detail->lembur = $lemburHpp;
            $detail->insentif = $insentifHpp;
            \Log::info("Calculated extras for detail {$detail->id}", [
                'tunjangan_hari_raya_hpp' => $detail->tunjangan_hari_raya_hpp,
                'tunjangan_hari_raya_coss' => $detail->tunjangan_hari_raya_coss,
                'kompensasi_hpp' => $detail->kompensasi_hpp,
                'kompensasi_coss' => $detail->kompensasi_coss,
                'tunjangan_holiday_hpp' => $detail->tunjangan_holiday_hpp,
                'tunjangan_holiday_coss' => $detail->tunjangan_holiday_coss,
                'lembur_hpp' => $detail->lembur_hpp,
                'lembur_coss' => $detail->lembur_coss,
                'insentif_hpp' => $detail->insentif_hpp,
                'insentif_coss' => $detail->insentif_coss,
            ]);

        } catch (\Exception $e) {
            \Log::error("Error in calculateExtras for detail {$detail->id}: " . $e->getMessage());
            throw $e;
        }
    }

    // ============================ FINAL TOTALS (TIDAK BERUBAH) ============================

    private function calculateFinalTotals($detail, $quotation, $totalTunjanganResult, $hpp, $coss): void
    {
        try {
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

            $nominalUpah = (float) ($detail->nominal_upah_bulanan ?? $detail->nominal_upah ?? 0);
            $tunjanganHariRayaHpp = (float) ($detail->tunjangan_hari_raya_hpp ?? 0);
            $kompensasiHpp = (float) ($detail->kompensasi_hpp ?? 0);
            $tunjanganHariRayaCoss = (float) ($detail->tunjangan_hari_raya_coss ?? 0);
            $kompensasiCoss = (float) ($detail->kompensasi_coss ?? 0);
            $tunjanganHoliday = (float) ($detail->tunjangan_holiday_hpp ?? 0);
            $lembur = (float) ($detail->lembur_hpp ?? 0);
            $tunjanganHolidayCoss = (float) ($detail->tunjangan_holiday_coss ?? 0);
            $lemburCoss = (float) ($detail->lembur_coss ?? 0);
            $bpjsJkk = (float) ($detail->bpjs_jkk ?? 0);
            $bpjsJkm = (float) ($detail->bpjs_jkm ?? 0);
            $bpjsJht = (float) ($detail->bpjs_jht ?? 0);
            $bpjsJp = (float) ($detail->bpjs_jp ?? 0);
            $bpjsKes = (float) ($detail->bpjs_kes ?? 0);
            $bpjsKetenagakerjaanHpp = $bpjsJkk + $bpjsJkm + $bpjsJht + $bpjsJp;
            $bpjsKetenagakerjaanCoss = $bpjsKetenagakerjaanHpp;
            $biayaKesehatanHpp = $bpjsKes;
            $biayaKesehatanCoss = $bpjsKes;
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
            $jumlahHcHpp = $detail->jumlah_hc_hpp;
            $jumlahHcCoss = $detail->jumlah_hc_original;

            $detail->total_base_manpower = round($nominalUpah + $totalTunjanganHpp, 2);
            $detail->total_base_manpower_coss = round($nominalUpah + $totalTunjanganCoss, 2);

            $detail->total_personil = round(
                $nominalUpah + $totalTunjanganHpp + $tunjanganHariRayaHpp + $kompensasiHpp
                + $tunjanganHoliday + $lembur + $bpjsKetenagakerjaanHpp + $biayaKesehatanHpp
                + $personilKaporlap + $personilDevices + $personilChemical + $personilOhc
                + $bungaBank + $insentif + $potonganBpu,
                2
            );
            $detail->sub_total_personil = round($detail->total_personil * $jumlahHcHpp, 2);

            $detail->total_exclude_base_manpower = round(
                $tunjanganHariRayaCoss + $kompensasiCoss + $tunjanganHolidayCoss + $lemburCoss
                + $biayaKesehatanCoss + $bpjsKetenagakerjaanCoss
                + $personilKaporlapCoss + $personilDevicesCoss + $personilChemicalCoss,
                2
            );
            $detail->total_personil_coss = round(
                $detail->total_base_manpower_coss + $detail->total_exclude_base_manpower
                + $personilOhcCoss + $potonganBpu,
                2
            );
            $detail->sub_total_personil_coss = round($detail->total_personil_coss * $jumlahHcCoss, 2);
            \Log::info("Calculated totals for detail {$detail->id}", [
                'total_personil' => $detail->total_personil,
                'sub_total_personil' => $detail->sub_total_personil,
                'total_personil_coss' => $detail->total_personil_coss,
                'sub_total_personil_coss' => $detail->sub_total_personil_coss,
            ]);

        } catch (\Exception $e) {
            \Log::error("Error in calculateFinalTotals for detail {$detail->id}: " . $e->getMessage());
            throw $e;
        }
    }

    // ============================ GROSS UP RECALCULATION (TIDAK BERUBAH) ============================

    private function calculateBankInterestAndIncentive($quotation, $jumlahHc, QuotationCalculationResult $result): void
    {
        $summary = $result->calculation_summary;
        $persenBungaBank = (float) $quotation->persen_bunga_bank;

        if ($quotation->top == "Non TOP") {
            $persenBungaBank = 0;
        }

        $summary->bunga_bank_total = $persenBungaBank > 0
            ? $summary->total_sebelum_management_fee * ($persenBungaBank / 100) / $jumlahHc
            : 0;

        $persenInsentif = (float) $quotation->persen_insentif;
        $summary->insentif_total = $persenInsentif > 0
            ? $summary->nominal_management_fee * ($persenInsentif / 100) / $jumlahHc
            : 0;
    }

    private function updateDetailsWithGrossUp($quotation, $daftarTunjangan, $jumlahHc, QuotationCalculationResult $result): void
    {
        $summary = $result->calculation_summary;

        $quotation->quotation_detail->each(function ($detail) use ($quotation, $summary, $result) {
            $detail->bunga_bank = $summary->bunga_bank_total;
            $detail->insentif = $summary->insentif_total > 0
                ? round($summary->insentif_total, 10)
                : 0;

            $hpp = $quotation->_hpp_map->get($detail->id);
            $coss = $quotation->_coss_map->get($detail->id);

            $totalTunjanganResult = [
                'total' => $detail->total_tunjangan ?? 0,
                'total_coss' => $detail->total_tunjangan_coss ?? 0,
            ];

            $this->calculateFinalTotals($detail, $quotation, $totalTunjanganResult, $hpp, $coss);

            if (isset($result->detail_calculations[$detail->id])) {
                $dto = $result->detail_calculations[$detail->id];

                $dto->hpp_data['bunga_bank'] = $detail->bunga_bank;
                $dto->hpp_data['insentif'] = $detail->insentif;
                $dto->hpp_data['total_biaya_per_personil'] = $detail->total_personil;
                $dto->hpp_data['total_biaya_all_personil'] = $detail->sub_total_personil;

                $dto->coss_data['bunga_bank'] = $detail->bunga_bank;
                $dto->coss_data['insentif'] = $detail->insentif;
                $dto->coss_data['total_personil_coss'] = $detail->total_personil_coss ?? 0;
                $dto->coss_data['sub_total_personil_coss'] = $detail->sub_total_personil_coss ?? 0;
            }
        });
    }

    // ============================ HPP & COSS CALCULATIONS (TIDAK BERUBAH) ============================

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

        $this->calculateBaseTotals($quotation, $suffix, $result);
        $this->calculateManagementFee($quotation, $suffix, $result);
        $this->calculateTaxes($quotation, $suffix, $model, $result);
        $this->finalizeCalculations($quotation, $suffix, $result);
    }

    private function calculateBaseTotals(&$quotation, $suffix, QuotationCalculationResult $result): void
    {
        $summary = $result->calculation_summary;
        $jumlahHcField = ($suffix === '_coss') ? 'jumlah_hc_original' : 'jumlah_hc_hpp';

        $summary->{"total_sebelum_management_fee{$suffix}"} =
            $quotation->quotation_detail->sum('sub_total_personil' . $suffix);

        $summary->{"total_base_manpower{$suffix}"} = $quotation->quotation_detail->sum(
            function ($detail) use ($suffix, $jumlahHcField) {
                $total = ($suffix === '_coss') ? ($detail->total_base_manpower_coss ?? 0) : ($detail->total_base_manpower ?? 0);
                $jumlahHc = $detail->{$jumlahHcField} ?? $detail->jumlah_hc;
                return $total * $jumlahHc;
            }
        );

        $summary->{"upah_pokok{$suffix}"} = $quotation->quotation_detail->sum(
            fn($d) => ($d->nominal_upah_bulanan ?? $d->nominal_upah) * ($d->{$jumlahHcField} ?? $d->jumlah_hc)
        );

        $summary->{"total_bpjs{$suffix}"} = $quotation->quotation_detail->sum(
            fn($d) => ($d->bpjs_ketenagakerjaan ?? 0) * ($d->{$jumlahHcField} ?? $d->jumlah_hc)
        );

        $summary->{"total_bpjs_kesehatan{$suffix}"} = $quotation->quotation_detail->sum(
            fn($d) => ($d->bpjs_kesehatan ?? 0) * ($d->{$jumlahHcField} ?? $d->jumlah_hc)
        );

        $summary->total_potongan_bpu = $quotation->quotation_detail->sum(
            fn($d) => ($d->penjamin_kesehatan === 'BPU') ? 16800 * ($d->{$jumlahHcField} ?? $d->jumlah_hc) : 0
        );
        $summary->potongan_bpu_per_orang = 16800;

        $totalHc = $quotation->quotation_detail->sum(fn($d) => $d->{$jumlahHcField} ?? $d->jumlah_hc);

        if ($totalHc > 0 && ($firstDetail = $quotation->quotation_detail->first())) {
            $prefix = ($suffix === '') ? '' : '_coss';
            $fields = [
                'persen_bpjs_ketenagakerjaan',
                'persen_bpjs_kesehatan',
                'persen_bpjs_jkk',
                'persen_bpjs_jkm',
                'persen_bpjs_jht',
                'persen_bpjs_jp',
                'persen_bpjs_kes'
            ];
            foreach ($fields as $f) {
                $summaryField = $suffix === '' ? $f : "{$f}_coss";
                $summary->{$summaryField} = $firstDetail->{$f} ?? 0;
            }
        }
    }

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

        $isPpnBoolean = is_numeric($isPpn) ? ((int) $isPpn === 1) : ($isPpn === "Ya");

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
        } elseif ($summary->{"pph{$suffix}"} == 0 && $ppnPphDipotong != "Total Invoice") {
            $calculatedPph = round($summary->{"grand_total_sebelum_pajak{$suffix}"} * -0.02, 2);
            $maxPph = abs($baseAmount * 0.1);
            if (abs($calculatedPph) > $maxPph) {
                $calculatedPph = -$maxPph;
            }
            $summary->{"pph{$suffix}"} = $calculatedPph;
        } else {
            if ($summary->{"pph{$suffix}"} > 0) {
                $summary->{"pph{$suffix}"} = -abs($summary->{"pph{$suffix}"});
            }
        }
    }

    private function finalizeCalculations(&$quotation, $suffix, QuotationCalculationResult $result): void
    {
        $summary = $result->calculation_summary;

        $summary->{"total_invoice{$suffix}"} = $summary->{"grand_total_sebelum_pajak{$suffix}"}
            + $summary->{"ppn{$suffix}"}
            + $summary->{"pph{$suffix}"};

        $summary->{"pembulatan{$suffix}"} = ceil($summary->{"total_invoice{$suffix}"} / 1000) * 1000;
        $summary->{"margin{$suffix}"} = $summary->{"grand_total_sebelum_pajak{$suffix}"} - $summary->total_sebelum_management_fee;

        $summary->{"gpm{$suffix}"} = $summary->{"grand_total_sebelum_pajak{$suffix}"} != 0
            ? $summary->{"margin{$suffix}"} / $summary->{"grand_total_sebelum_pajak{$suffix}"} * 100
            : 0;
    }

    // ============================ HELPER METHODS (TIDAK BERUBAH) ============================

    private function makeEmptyWageObject(): \stdClass
    {
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
        return $wage;
    }

    private function calculateProvisi($durasiKerjasama): int
    {
        if (!$durasiKerjasama)
            return 12;
        return !str_contains($durasiKerjasama, 'tahun')
            ? (int) str_replace(" bulan", "", $durasiKerjasama)
            : 12;
    }

    private function parseHariKerja(?string $hariKerja): int
    {
        if (!$hariKerja)
            return self::PKHL_DEFAULT_HARI_KERJA;
        $parsed = (int) $hariKerja;
        return $parsed > 0 ? $parsed : self::PKHL_DEFAULT_HARI_KERJA;
    }

    private function normalizeUpahForKontrak($detail, $quotation): void
    {
        if (($quotation->jenis_kontrak ?? '') !== 'PKHL') {
            $detail->nominal_upah_bulanan = (float) $detail->nominal_upah;
            return;
        }
        $hariKerja = max(1, $this->parseHariKerja($quotation->hari_kerja));
        $detail->nominal_upah_harian = (float) $detail->nominal_upah;
        $detail->hari_kerja_pkhl = $hariKerja;
        $detail->nominal_upah_bulanan = round($detail->nominal_upah_harian * $hariKerja, 2);
    }

    private function calculateUpahBpjs($nominalUpah, $umk, $ump): float
    {
        if ($nominalUpah > $umk)
            return $nominalUpah;
        if ($nominalUpah == $umk)
            return $umk;
        if ($nominalUpah < $umk && $nominalUpah >= $ump)
            return $nominalUpah;
        return $ump;
    }

    private function getJkkPercentage($resiko): float
    {
        return [
            "Sangat Rendah" => 0.24,
            "Rendah" => 0.54,
            "Sedang" => 0.89,
            "Tinggi" => 1.27,
            "Sangat Tinggi" => 1.74,
        ][$resiko] ?? 0.24;
    }

    private function applyBpjsOptOut($detail): void
    {
        $optOuts = [
            'is_bpjs_jkk' => ['bpjs_jkk', 'persen_bpjs_jkk'],
            'is_bpjs_jkm' => ['bpjs_jkm', 'persen_bpjs_jkm'],
            'is_bpjs_jht' => ['bpjs_jht', 'persen_bpjs_jht'],
            'is_bpjs_jp' => ['bpjs_jp', 'persen_bpjs_jp'],
            'is_bpjs_kes' => ['bpjs_kes', 'persen_bpjs_kes'],
        ];

        foreach ($optOuts as $optField => $targetFields) {
            if (!isset($detail->{$optField}))
                continue;

            $optValue = $detail->{$optField};
            $isOptOut = (
                ($optValue === "0" || $optValue === 0 || $optValue === false || $optValue === "false" ||
                    (is_string($optValue) && strtolower(trim($optValue)) === 'tidak'))
                && !($optField === 'is_bpjs_kes' && $detail->penjamin_kesehatan === 'BPJS')
            );

            if (!$isOptOut && is_string($optValue) && strtolower(trim($optValue)) === 'ya') {
                $isOptOut = false;
            }

            if ($isOptOut) {
                $detail->{$targetFields[0]} = 0;
                $detail->{$targetFields[1]} = 0;
            }
        }
    }

    private function updateQuotationBpjs($detail, $quotation): void
    {
        $detail->persen_bpjs_ketenagakerjaan =
            ($detail->persen_bpjs_jkk ?? 0) + ($detail->persen_bpjs_jkm ?? 0) +
            ($detail->persen_bpjs_jht ?? 0) + ($detail->persen_bpjs_jp ?? 0);

        $detail->bpjs_ketenagakerjaan =
            ($detail->bpjs_jkk ?? 0) + ($detail->bpjs_jkm ?? 0) +
            ($detail->bpjs_jht ?? 0) + ($detail->bpjs_jp ?? 0);

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

    private function calculateBpu($detail, $quotation): int
    {
        return $detail->penjamin_kesehatan === 'BPU' ? 16800 : 0;
    }

    private function calculateTunjanganHolidayFromWage($wage): float
    {
        if (!$wage)
            return 0.0;

        $tunjanganHolidayNormalized = strtolower(trim($wage->tunjangan_holiday ?? "Tidak"));
        if (!str_contains($tunjanganHolidayNormalized, 'flat'))
            return 0.0;

        $jenisBayar = $wage->jenis_bayar_tunjangan_holiday ?? null;
        $nominal = (float) str_replace(['.', ','], ['', '.'], (string) ($wage->nominal_tunjangan_holiday ?? 0));

        return round(match ($jenisBayar) {
            "Per Bulan" => $nominal,
            default => $nominal,
        }, 2);
    }

    private function calculateLemburFromWage($wage): float
    {
        if (!$wage)
            return 0.0;

        $lemburNormalized = strtolower(trim($wage->lembur ?? "Tidak"));
        if (!str_contains($lemburNormalized, 'flat'))
            return 0.0;

        $lemburDitagihkan = $wage->lembur_ditagihkan ?? "Tidak Ditagihkan";
        if (str_contains(strtolower($lemburDitagihkan), 'terpisah'))
            return 0.0;

        $jenisBayar = $wage->jenis_bayar_lembur ?? null;
        $nominalLembur = (float) str_replace(['.', ','], ['', '.'], (string) ($wage->nominal_lembur ?? 0));

        return round(match ($jenisBayar) {
            "Per Bulan" => $nominalLembur,
            default => $nominalLembur,
        }, 2);
    }
}