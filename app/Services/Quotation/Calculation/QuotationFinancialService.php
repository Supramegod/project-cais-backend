<?php

namespace App\Services\Quotation\Calculation;

use App\DTO\QuotationCalculationResult;
use App\Models\QuotationDetailCoss;
use App\Models\QuotationDetailHpp;

class QuotationFinancialService
{
    // ============================ HPP / COSS ENTRY ============================

    public function calculateHpp(&$quotation, $jumlahHc, $provisi, QuotationCalculationResult $result): void
    {
        $this->calculateFinancials($quotation, 'hpp', $result);
    }

    public function calculateCoss(&$quotation, $jumlahHc, $provisi, QuotationCalculationResult $result): void
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

    // ============================ BASE TOTALS ============================

    private function calculateBaseTotals(&$quotation, $suffix, QuotationCalculationResult $result): void
    {
        $summary = $result->calculation_summary;
        $jumlahHcField = ($suffix === '_coss') ? 'jumlah_hc_original' : 'jumlah_hc_hpp';

        $details = ($suffix === '_coss')
            ? $quotation->quotation_detail->filter(fn ($d) => ! $this->isRo($d))
            : $quotation->quotation_detail;

        $summary->{"total_sebelum_management_fee{$suffix}"} =
            $details->sum('sub_total_personil'.$suffix);

        $summary->{"total_base_manpower{$suffix}"} = $details->sum(
            function ($detail) use ($suffix, $jumlahHcField) {
                $total = ($suffix === '_coss')
                    ? ($detail->total_base_manpower_coss ?? 0)
                    : ($detail->total_base_manpower ?? 0);
                $jumlahHc = $detail->{$jumlahHcField} ?? $detail->jumlah_hc;

                return $total * $jumlahHc;
            }
        );

        $summary->{"upah_pokok{$suffix}"} = $details->sum(
            fn ($d) => ($d->nominal_upah_bulanan ?? $d->nominal_upah) * ($d->{$jumlahHcField} ?? $d->jumlah_hc)
        );

        $summary->{"total_bpjs{$suffix}"} = $details->sum(
            fn ($d) => ($d->bpjs_ketenagakerjaan ?? 0) * ($d->{$jumlahHcField} ?? $d->jumlah_hc)
        );

        $summary->{"total_bpjs_kesehatan{$suffix}"} = $details->sum(
            fn ($d) => ($d->bpjs_kesehatan ?? 0) * ($d->{$jumlahHcField} ?? $d->jumlah_hc)
        );

        $summary->total_potongan_bpu = $details->sum(
            fn ($d) => ($d->penjamin_kesehatan === 'BPU')
            ? 16800 * ($d->{$jumlahHcField} ?? $d->jumlah_hc)
            : 0
        );
        $summary->potongan_bpu_per_orang = 16800;

        $totalHc = $details->sum(fn ($d) => $d->{$jumlahHcField} ?? $d->jumlah_hc);

        if ($totalHc > 0 && ($firstDetail = $details->first())) {
            $fields = [
                'persen_bpjs_ketenagakerjaan', 'persen_bpjs_kesehatan',
                'persen_bpjs_jkk', 'persen_bpjs_jkm', 'persen_bpjs_jht',
                'persen_bpjs_jp', 'persen_bpjs_kes',
            ];
            foreach ($fields as $f) {
                $summaryField = $suffix === '' ? $f : "{$f}_coss";
                $summary->{$summaryField} = $firstDetail->{$f} ?? 0;
            }
        }

        $isHpp = ($suffix !== '_coss');

        $summary->{"total_thr{$suffix}"} = $details->sum(
            fn ($d) => ($isHpp ? ($d->tunjangan_hari_raya_hpp ?? 0) : ($d->tunjangan_hari_raya_coss ?? 0))
            * ($d->{$jumlahHcField} ?? $d->jumlah_hc)
        );

        $summary->{"total_kompensasi{$suffix}"} = $details->sum(
            fn ($d) => ($isHpp ? ($d->kompensasi_hpp ?? 0) : ($d->kompensasi_coss ?? 0))
            * ($d->{$jumlahHcField} ?? $d->jumlah_hc)
        );

        $summary->{"total_thl{$suffix}"} = $details->sum(
            fn ($d) => ($isHpp ? ($d->tunjangan_holiday_hpp ?? 0) : ($d->tunjangan_holiday_coss ?? 0))
            * ($d->{$jumlahHcField} ?? $d->jumlah_hc)
        );

        $summary->{"total_lembur{$suffix}"} = $details->sum(
            fn ($d) => ($isHpp ? ($d->lembur_hpp ?? 0) : ($d->lembur_coss ?? 0))
            * ($d->{$jumlahHcField} ?? $d->jumlah_hc)
        );

        $summary->{"total_kaporlap{$suffix}"} = $details->sum(
            fn ($d) => ($isHpp ? ($d->personil_kaporlap ?? 0) : ($d->personil_kaporlap_coss ?? 0))
            * ($d->{$jumlahHcField} ?? $d->jumlah_hc)
        );

        $summary->{"total_device{$suffix}"} = $details->sum(
            fn ($d) => ($isHpp ? ($d->personil_devices ?? 0) : ($d->personil_devices_coss ?? 0))
            * ($d->{$jumlahHcField} ?? $d->jumlah_hc)
        );

        $summary->{"total_chemical{$suffix}"} = $details->sum(
            fn ($d) => ($isHpp ? ($d->personil_chemical ?? 0) : ($d->personil_chemical_coss ?? 0))
            * ($d->{$jumlahHcField} ?? $d->jumlah_hc)
        );

        $summary->{"total_ohc{$suffix}"} = $details->sum(
            fn ($d) => ($isHpp ? ($d->personil_ohc ?? 0) : ($d->personil_ohc_coss ?? 0))
            * ($d->{$jumlahHcField} ?? $d->jumlah_hc)
        );

        $summary->{"total_tunjangan_lain{$suffix}"} = $details->sum(
            fn ($d) => ($isHpp ? ($d->total_tunjangan ?? 0) : ($d->total_tunjangan_coss ?? 0))
            * ($d->{$jumlahHcField} ?? $d->jumlah_hc)
        );
    }

    // ============================ MANAGEMENT FEE ============================

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

    // ============================ TAXES ============================

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
        $ppnPphDipotong = $quotation->ppn_pph_dipotong ?? 'Management Fee';
        $isPpn = $quotation->is_ppn ?? 'Tidak';

        $isPpnBoolean = is_numeric($isPpn) ? ((int) $isPpn === 1) : ($isPpn === 'Ya');
        if (! $isPpnBoolean) {
            $summary->{"dpp{$suffix}"} = 0;
            $summary->{"ppn{$suffix}"} = 0;
            $summary->{"pph{$suffix}"} = 0;

            return;
        }

        $baseAmount = 0;
        if ($ppnPphDipotong == 'Management Fee') {
            $managementFee = $summary->{"nominal_management_fee{$suffix}"};
            $baseAmount = $managementFee * (11 / 12);
        } else {
            $baseAmount = $summary->{"grand_total_sebelum_pajak{$suffix}"} * (11 / 12);
        }

        $summary->{"dpp{$suffix}"} = $baseAmount;

        if ($summary->{"ppn{$suffix}"} == 0 && $isPpnBoolean) {
            $summary->{"ppn{$suffix}"} = round($baseAmount * 0.12, 2);
        }

        if ($summary->{"pph{$suffix}"} == 0 && $ppnPphDipotong == 'Management Fee') {
            $calculatedPph = round($managementFee * -0.02, 2);
            $maxPph = abs($baseAmount * 0.1);
            if (abs($calculatedPph) > $maxPph) {
                $calculatedPph = -$maxPph;
            }
            $summary->{"pph{$suffix}"} = $calculatedPph;
        } elseif ($summary->{"pph{$suffix}"} == 0 && $ppnPphDipotong == 'Total Invoice') {
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

    // ============================ FINALIZATION ============================

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

    // ============================ GROSS UP ============================

    public function calculateBankInterestAndIncentive($quotation, $jumlahHc, QuotationCalculationResult $result): void
    {
        $summary = $result->calculation_summary;
        $persenBungaBank = (float) $quotation->persen_bunga_bank;

        if ($quotation->top == 'Non TOP') {
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

    private function isRo($detail): bool
    {
        return ($detail->position_id ?? null) === 224;
    }
}
