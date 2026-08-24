<?php

namespace App\Services\Quotation\Calculation;

use App\DTO\QuotationCalculationResult;
use App\Models\ManagementFee;
use App\Models\Quotation;
use App\Models\QuotationChemical;
use App\Models\QuotationDetail;
use App\Models\QuotationDetailCoss;
use App\Models\QuotationDetailHpp;
use App\Models\QuotationDetailWage;
use App\Models\QuotationDevices;
use App\Models\QuotationKaporlap;
use App\Models\QuotationManagementFee;
use App\Models\QuotationOhc;
use App\Models\QuotationSite;
use App\Services\Quotation\QuotationNotificationService;
use Illuminate\Support\Facades\Auth;

class QuotationCalculationService
{
    private QuotationItemCalculationService $itemService;

    private QuotationFinancialService $financialService;

    private array $_management_fee_cache = [];

    public function __construct(
        QuotationItemCalculationService $itemService,
        QuotationFinancialService $financialService,
        protected QuotationNotificationService $quotationNotificationService,
    ) {
        $this->itemService = $itemService;
        $this->financialService = $financialService;
    }

    // ============================ MAIN CALCULATION FLOW ============================

    public function calculateQuotation($quotation): QuotationCalculationResult
    {
        try {
            $result = new QuotationCalculationResult($quotation);

            $this->loadQuotationData($quotation);

            $this->ensureAllWagesExist($quotation);

            if ($quotation->quotation_detail->isEmpty()) {
                return $result;
            }

            $jumlahHc = $quotation->quotation_detail->sum('jumlah_hc');
            $quotation->jumlah_hc = $jumlahHc;
            $quotation->provisi = $this->itemService->calculateProvisi($quotation->durasi_kerjasama);

            $this->itemService->initializeAllDetails($quotation);

            $this->calculateFirstPass($quotation, $jumlahHc, $result);
            $this->recalculateWithGrossUp($quotation, $jumlahHc, $result);

            $quotation->where('id', $quotation->id)->update(['calculated_at' => now()]);

            return $result;

        } catch (\Exception $e) {
            \Log::error('Error in calculateQuotation: '.$e->getMessage());
            \Log::error('Stack trace: '.$e->getTraceAsString());
            throw $e;
        }
    }

    // ============================ DATA LOADING ============================

    private function loadQuotationData($quotation): void
    {
        $quotationDetails = QuotationDetail::with(['wage', 'quotationDetailTunjangans'])
            ->where('quotation_id', $quotation->id)
            ->get();

        $detailIds = $quotationDetails->pluck('id')->all();
        $quotationSites = QuotationSite::where('quotation_id', $quotation->id)->get();
        $siteIds = $quotationSites->pluck('id')->all();

        $quotation->_hpp_map = QuotationDetailHpp::whereIn('quotation_detail_id', $detailIds)
            ->get()->keyBy('quotation_detail_id');

        $quotation->_coss_map = QuotationDetailCoss::whereIn('quotation_detail_id', $detailIds)
            ->get()->keyBy('quotation_detail_id');

        $quotation->_sites_map = $quotationSites->keyBy('id');

        $quotationSites->each(function ($site) use ($quotationDetails) {
            $site->jumlah_detail = $quotationDetails
                ->where('quotation_site_id', $site->id)->count();
        });

        $quotation->_daftar_tunjangan = $quotationDetails
            ->pluck('quotationDetailTunjangans')
            ->flatten()
            ->pluck('nama_tunjangan')
            ->unique()
            ->values()
            ->map(fn ($nama) => (object) ['nama' => $nama]);

        $mfId = $quotation->management_fee_id;
        if (! isset($this->_management_fee_cache[$mfId])) {
            $mf = ManagementFee::find($mfId);
            $this->_management_fee_cache[$mfId] = $mf->nama ?? '';
        }
        $quotation->management_fee = $this->_management_fee_cache[$mfId];

        $quotation->_mf_config = QuotationManagementFee::resolveForQuotation($quotation->id);

        $quotation->_kaporlap_items = QuotationKaporlap::whereNull('deleted_at')
            ->where(function ($q) use ($quotation, $detailIds) {
                $q->whereIn('quotation_detail_id', $detailIds)
                    ->orWhere(function ($q2) use ($quotation) {
                        $q2->where('quotation_id', $quotation->id)
                            ->whereNull('quotation_detail_id');
                    });
            })
            ->get()
            ->groupBy(fn ($item) => $item->quotation_detail_id ?? '__legacy__');

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
            ->groupBy(fn ($item) => $item->quotation_site_id ?? '__legacy__');

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
            ->groupBy(fn ($item) => $item->quotation_site_id ?? '__legacy__');

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
            ->groupBy(fn ($item) => $item->quotation_site_id ?? '__legacy__');

        $quotation->quotation_detail = $quotationDetails;
        $quotation->quotation_site = $quotationSites;
    }

    private function ensureAllWagesExist($quotation): void
    {
        $detailsWithoutWage = $quotation->quotation_detail->filter(fn ($d) => ! $d->wage);

        if ($detailsWithoutWage->isEmpty()) {
            return;
        }

        $createdBy = Auth::user()->full_name ?? 'system';
        $now = now();

        $rows = $detailsWithoutWage->map(fn ($detail) => [
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
            'created_by_user_id' => Auth::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();

        QuotationDetailWage::insert($rows);

        $detailIds = $quotation->quotation_detail->pluck('id');
        $wagesById = QuotationDetailWage::whereIn('quotation_detail_id', $detailIds)
            ->get()
            ->keyBy('quotation_detail_id');

        $quotation->quotation_detail->each(function ($detail) use ($wagesById) {
            if (! $detail->wage) {
                $detail->setRelation('wage', $wagesById->get($detail->id));
            }
        });
    }

    // ============================ TWO-PASS FLOW ============================

    private function calculateFirstPass($quotation, $jumlahHc, QuotationCalculationResult $result): void
    {
        $daftarTunjangan = $quotation->_daftar_tunjangan;

        $this->itemService->processAllDetails($quotation, $daftarTunjangan, $jumlahHc, $result);
        $this->financialService->calculateHpp($quotation, $jumlahHc, $quotation->provisi, $result);
        $this->financialService->calculateCoss($quotation, $jumlahHc, $quotation->provisi, $result);
    }

    private function recalculateWithGrossUp($quotation, $jumlahHc, QuotationCalculationResult $result): void
    {
        $daftarTunjangan = $quotation->_daftar_tunjangan;

        $this->financialService->calculateBankInterestAndIncentive($quotation, $jumlahHc, $result);
        $this->itemService->updateDetailsWithGrossUp($quotation, $daftarTunjangan, $jumlahHc, $result);

        $this->financialService->calculateHpp($quotation, $jumlahHc, $quotation->provisi, $result);
        $this->financialService->calculateCoss($quotation, $jumlahHc, $quotation->provisi, $result);
    }
}
