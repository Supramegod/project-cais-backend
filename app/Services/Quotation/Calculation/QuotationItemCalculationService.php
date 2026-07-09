<?php

namespace App\Services\Quotation\Calculation;

use App\DTO\DetailCalculation;
use App\DTO\QuotationCalculationResult;
use Illuminate\Support\Collection;

class QuotationItemCalculationService
{
    private const PKHL_DEFAULT_HARI_KERJA = 25;

    private array $_site_hc_cache = [];

    private QuotationComponentCalculationService $componentService;

    public function __construct(QuotationComponentCalculationService $componentService)
    {
        $this->componentService = $componentService;
    }

    // ============================ DETAIL PROCESSING ============================

    public function processAllDetails($quotation, $daftarTunjangan, $jumlahHc, QuotationCalculationResult $result): void
    {
        $this->_site_hc_cache = [];

        foreach ($quotation->quotation_detail as $detail) {
            try {
                $this->processSingleDetail($detail, $quotation, $daftarTunjangan, $jumlahHc, $result);
            } catch (\Exception $e) {
                \Log::warning("Skipped detail {$detail->id}: ".$e->getMessage());
            }
        }
    }

    private function processSingleDetail($detail, $quotation, $daftarTunjangan, $jumlahHc, QuotationCalculationResult $result): void
    {
        $detailCalculation = new DetailCalculation($detail->id);

        $hpp = $quotation->_hpp_map->get($detail->id);
        $coss = $quotation->_coss_map->get($detail->id);
        $site = $quotation->_sites_map->get($detail->quotation_site_id);
        $wage = $detail->wage ?? $this->componentService->makeEmptyWageObject();

        $this->initializeDetail($detail, $hpp, $site, $wage, $quotation);
        $this->calculateDetailComponents($detail, $quotation, $daftarTunjangan, $jumlahHc, $hpp, $coss, $wage, $detailCalculation);

        $result->detail_calculations[$detail->id] = $detailCalculation;
    }

    public function initializeAllDetails($quotation): void
    {
        foreach ($quotation->quotation_detail as $detail) {
            $hpp = $quotation->_hpp_map->get($detail->id);
            $detail->jumlah_hc_original = $detail->jumlah_hc;
            $detail->jumlah_hc_hpp = $hpp && $hpp->jumlah_hc !== null
                ? (int) $hpp->jumlah_hc
                : $detail->jumlah_hc;
        }
    }

    private function initializeDetail($detail, $hpp, $site, $wage, $quotation): void
    {
        $detail->nominal_upah = $detail->nominal_upah ?? $site->nominal_upah;
        $detail->umk = $site->umk ?? 0;
        $detail->ump = $site->ump ?? 0;

        if (! isset($detail->bunga_bank)) $detail->bunga_bank = $hpp->bunga_bank ?? 0;
        if (! isset($detail->insentif)) $detail->insentif = $hpp->insentif ?? 0;

        $detail->upah = $wage->upah ?? null;
        $detail->hitungan_upah = $wage->hitungan_upah ?? null;
        $detail->lembur = $wage->lembur ?? 'Tidak';
        $detail->nominal_lembur = $wage->nominal_lembur ?? 0;
        $detail->jenis_bayar_lembur = $wage->jenis_bayar_lembur ?? null;
        $detail->jam_per_bulan_lembur = $wage->jam_per_bulan_lembur ?? 0;
        $detail->lembur_ditagihkan = $wage->lembur_ditagihkan ?? 'Tidak Ditagihkan';
        $detail->kompensasi = $wage->kompensasi ?? 'Tidak';
        $detail->thr = $wage->thr ?? 'Tidak';
        $detail->tunjangan_holiday = $wage->tunjangan_holiday ?? 'Tidak';
        $detail->nominal_tunjangan_holiday = $wage->nominal_tunjangan_holiday ?? 0;
        $detail->jenis_bayar_tunjangan_holiday = $wage->jenis_bayar_tunjangan_holiday ?? null;

        $this->normalizeUpahForKontrak($detail, $quotation);
    }

    private function calculateDetailComponents($detail, $quotation, $daftarTunjangan, $jumlahHc, $hpp, $coss, $wage, DetailCalculation $detailCalculation): void
    {
        $isGC = (strtoupper($quotation->jenis_kontrak ?? '') === 'GENERAL CLEANING');
        $hariKerja = $isGC ? max(1, $this->parseHariKerja($quotation->hari_kerja)) : 1;

        $totalTunjangan = $this->componentService->calculateTunjangan($detail, $daftarTunjangan);
        $this->componentService->calculateBpjs($detail, $quotation, $hpp);
        $this->componentService->calculateExtras($detail, $quotation, $hpp, $coss, $wage, $isGC, $hariKerja);
        $this->calculateAllItems($detail, $quotation, $jumlahHc, $hpp, $coss, $isGC, $hariKerja);
        $this->componentService->calculateFinalTotals($detail, $quotation, $totalTunjangan, $hpp, $coss);
        $this->componentService->populateDetailCalculation($detail, $quotation, $detailCalculation);
    }

    // ============================ ITEM CALCULATIONS ============================

    private function calculateAllItems($detail, $quotation, $totalJumlahHc, $hpp, $coss, bool $isGC = false, int $hariKerja = 1): void
    {
        if (! isset($this->_site_hc_cache['quotation_id']) || $this->_site_hc_cache['quotation_id'] !== $quotation->id) {
            $siteHcHpp = []; $siteHcCoss = []; $globalHpp = 0; $globalCoss = 0;
            foreach ($quotation->quotation_detail as $det) {
                $sid = $det->quotation_site_id;
                $siteHcHpp[$sid] = ($siteHcHpp[$sid] ?? 0) + $det->jumlah_hc_hpp;
                $siteHcCoss[$sid] = ($siteHcCoss[$sid] ?? 0) + $det->jumlah_hc_original;
                $globalHpp += $det->jumlah_hc_hpp; $globalCoss += $det->jumlah_hc_original;
            }
            $firstDetail = $quotation->quotation_detail->first();
            $this->_site_hc_cache = [
                'quotation_id' => $quotation->id,
                'site_hc_hpp' => $siteHcHpp, 'site_hc_coss' => $siteHcCoss,
                'global_hpp' => $globalHpp, 'global_coss' => $globalCoss,
                'primary_detail_id' => $firstDetail->id ?? null,
            ];
        }

        $cache = $this->_site_hc_cache;
        $currentSiteId = $detail->quotation_site_id;
        $primaryDetailId = $cache['primary_detail_id'];
        $totalJumlahHcHppSite = $cache['site_hc_hpp'][$currentSiteId] ?? 0;
        $totalJumlahHcCossSite = $cache['site_hc_coss'][$currentSiteId] ?? 0;

        $items = [
            'kaporlap' => ['hpp_field' => 'provisi_seragam', 'coss_field' => 'provisi_seragam', 'preload_key' => '_kaporlap_items', 'group_by' => 'detail', 'is_general' => false, 'site_specific' => false, 'special' => 'kaporlap'],
            'devices'  => ['hpp_field' => 'provisi_peralatan', 'coss_field' => 'provisi_peralatan', 'preload_key' => '_devices_items', 'group_by' => 'site', 'is_general' => true, 'site_specific' => true, 'special' => 'device'],
            'ohc'      => ['hpp_field' => 'provisi_ohc', 'coss_field' => 'provisi_ohc', 'preload_key' => '_ohc_items', 'group_by' => 'site', 'is_general' => true, 'site_specific' => true, 'special' => null],
            'chemical' => ['hpp_field' => 'provisi_chemical', 'coss_field' => 'provisi_chemical', 'preload_key' => '_chemical_items', 'group_by' => 'site', 'is_general' => true, 'site_specific' => true, 'special' => 'chemical'],
        ];

        foreach ($items as $key => $config) {
            if ($config['is_general'] && $config['site_specific']) {
                $hppDivider = $totalJumlahHcHppSite; $cossDivider = $totalJumlahHcCossSite;
            } elseif ($config['is_general']) {
                $hppDivider = $cache['global_hpp']; $cossDivider = $cache['global_coss'];
            } else {
                $hppDivider = $detail->jumlah_hc_hpp; $cossDivider = $detail->jumlah_hc_original;
            }
            $hppDivider = max($hppDivider, 1); $cossDivider = max($cossDivider, 1);

            $hppManualValue = ($hpp && $hpp->{$config['hpp_field']} !== null) ? (float) $hpp->{$config['hpp_field']} : null;
            $cossManualValue = ($coss && $coss->{$config['coss_field']} !== null) ? (float) $coss->{$config['coss_field']} : null;

            $includeLegacy = ($detail->id === $primaryDetailId);
            if ($config['group_by'] === 'detail') {
                $loadedItems = $quotation->{$config['preload_key']}->get($detail->id, collect());
                if ($includeLegacy) $loadedItems = $loadedItems->merge($quotation->{$config['preload_key']}->get('__legacy__', collect()));
            } else {
                $loadedItems = $quotation->{$config['preload_key']}->get($currentSiteId, collect());
                if ($includeLegacy) $loadedItems = $loadedItems->merge($quotation->{$config['preload_key']}->get('__legacy__', collect()));
            }

            $hppValue = $hppManualValue ?? $this->computeItemValue($loadedItems, $config['special'], $hppDivider, $quotation->provisi, $detail->jumlah_hc_hpp);
            $cossValue = $cossManualValue ?? $this->computeItemValue($loadedItems, $config['special'], $cossDivider, $quotation->provisi, $detail->jumlah_hc_original);

            if ($isGC) { $hppValue /= $hariKerja; $cossValue /= $hariKerja; }

            $detail->{"personil_$key"} = $hppValue;
            $detail->{"personil_{$key}_coss"} = $cossValue;
        }
    }

    private function computeItemValue(Collection $items, ?string $special, int $divider, int $provisi, int $jumlahHc): float
    {
        if ($items->isEmpty()) return 0.0;
        $total = 0.0;

        foreach ($items as $item) {
            if ($special === 'chemical') {
                $total += ($item->jumlah * $item->harga) / $item->masa_pakai / max($divider, 1);
            } elseif ($special === 'kaporlap') {
                $total += ($item->harga * $item->jumlah) / $provisi;
            } else {
                $total += ($item->harga * $item->jumlah) / $provisi / max($divider, 1);
            }
        }
        return $total;
    }

    // ============================ GROSS UP UPDATE ============================

    public function updateDetailsWithGrossUp($quotation, $daftarTunjangan, $jumlahHc, QuotationCalculationResult $result): void
    {
        $summary = $result->calculation_summary;

        foreach ($quotation->quotation_detail as $detail) {
            $detail->bunga_bank = $summary->bunga_bank_total;
            $detail->insentif = $summary->insentif_total > 0 ? round($summary->insentif_total, 10) : 0;

            $hpp = $quotation->_hpp_map->get($detail->id);
            $coss = $quotation->_coss_map->get($detail->id);

            $totalTunjanganResult = [
                'total' => $detail->total_tunjangan ?? 0,
                'total_coss' => $detail->total_tunjangan_coss ?? 0,
            ];

            $this->componentService->calculateFinalTotals($detail, $quotation, $totalTunjanganResult, $hpp, $coss);

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
        }
    }

    public function calculateProvisi($durasiKerjasama): int
    {
        if (! $durasiKerjasama) return 12;
        return ! str_contains($durasiKerjasama, 'tahun')
            ? (int) str_replace(' bulan', '', $durasiKerjasama) : 12;
    }

    public function parseHariKerja(?string $hariKerja): int
    {
        if (!$hariKerja) return self::PKHL_DEFAULT_HARI_KERJA;
        return max((int) $hariKerja, 1);
    }

    public function normalizeUpahForKontrak($detail, $quotation): void
    {
        $jenisKontrak = strtoupper($quotation->jenis_kontrak ?? '');
        if ($jenisKontrak === 'PKHL' || $jenisKontrak === 'GENERAL CLEANING') {
            $hariKerja = max(1, $this->parseHariKerja($quotation->hari_kerja));
            $detail->nominal_upah_harian = (float) $detail->nominal_upah;
            $detail->hari_kerja_pkhl = $hariKerja;
            $detail->nominal_upah_bulanan = round($detail->nominal_upah_harian * $hariKerja, 2);
            return;
        }
        $detail->nominal_upah_bulanan = (float) $detail->nominal_upah;
    }
}
