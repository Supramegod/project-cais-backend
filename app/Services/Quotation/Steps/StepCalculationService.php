<?php

namespace App\Services\Quotation\Steps;

use App\DTO\QuotationCalculationResult;
use App\Models\Quotation;
use App\Models\QuotationDetailCoss;
use App\Models\QuotationDetailHpp;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Handles resetting and persisting calculation results (HPP & COSS).
 * Extracted from StepHelperTrait for single responsibility.
 */
class StepCalculationService
{
    public function __construct(
        protected StepHelperService $helper,
    ) {}

    /**
     * Reset semua nilai calculated values (HPP & COSS)
     */
    public function resetAllCalculatedValues(Quotation $quotation, string $user, Carbon $currentDateTime): void
    {
        Log::info('=== RESET ALL CALCULATED VALUES (bulk) ===', [
            'quotation_id' => $quotation->id,
        ]);

        $detailIds = $quotation->quotationDetails->pluck('id')->toArray();

        if (empty($detailIds)) {
            return;
        }

        $hppResetFields = [
            'tunjangan_hari_raya' => null,
            'kompensasi' => null,
            'tunjangan_hari_libur_nasional' => null,
            'lembur' => null,
            'provisi_seragam' => null,
            'provisi_peralatan' => null,
            'provisi_chemical' => null,
            'provisi_ohc' => null,
            'bpjs_jkk' => null,
            'bpjs_jkm' => null,
            'bpjs_jht' => null,
            'bpjs_jp' => null,
            'bpjs_ks' => null,
            'persen_bpjs_jkk' => null,
            'persen_bpjs_jkm' => null,
            'persen_bpjs_jht' => null,
            'persen_bpjs_jp' => null,
            'persen_bpjs_ks' => null,
            'gaji_pokok' => null,
            'updated_by' => $user,
            'updated_at' => $currentDateTime,
        ];

        QuotationDetailHpp::whereIn('quotation_detail_id', $detailIds)->update($hppResetFields);

        $cossResetFields = [
            'tunjangan_hari_raya' => null,
            'kompensasi' => null,
            'tunjangan_hari_libur_nasional' => null,
            'lembur' => null,
            'bpjs_jkk' => null,
            'bpjs_jkm' => null,
            'bpjs_jht' => null,
            'bpjs_jp' => null,
            'bpjs_ks' => null,
            'persen_bpjs_jkk' => null,
            'persen_bpjs_jkm' => null,
            'persen_bpjs_jht' => null,
            'persen_bpjs_jp' => null,
            'persen_bpjs_ks' => null,
            'updated_by' => $user,
            'updated_at' => $currentDateTime,
        ];

        QuotationDetailCoss::whereIn('quotation_detail_id', $detailIds)->update($cossResetFields);

        Log::info('Reset selesai untuk '.count($detailIds).' detail (termasuk persentase BPJS)');
    }

    /**
     * Save all calculation results — bulk upsert for HPP & COSS
     */
    public function saveAllCalculationResults(
        QuotationCalculationResult $calculationResult,
        string $user,
        Carbon $currentDateTime,
        ?Request $request = null,
        $preResetHppMap = null,
        $preResetCossMap = null
    ): void {
        $detailsMap = $calculationResult->quotation->quotation_detail->keyBy('id');
        $summary = $calculationResult->calculation_summary;
        $persentase = $calculationResult->quotation->persentase ?? 0;

        $editableFields = [
            'tunjangan_hari_raya',
            'kompensasi',
            'jumlah_hc',
            'tunjangan_hari_libur_nasional',
            'lembur',
            'provisi_seragam',
            'provisi_peralatan',
            'provisi_chemical',
            'provisi_ohc',
            'bunga_bank',
            'insentif',
            'bpjs_jkk',
            'bpjs_jkm',
            'bpjs_jht',
            'bpjs_jp',
            'bpjs_ks',
            'persen_bpjs_jkk',
            'persen_bpjs_jkm',
            'persen_bpjs_jht',
            'persen_bpjs_jp',
            'persen_bpjs_ks',
        ];

        $bpjsMap = [
            'jkk' => 'persen_bpjs_jkk',
            'jkm' => 'persen_bpjs_jkm',
            'jht' => 'persen_bpjs_jht',
            'jp' => 'persen_bpjs_jp',
            'kes' => 'persen_bpjs_ks',
        ];

        $hppAllowed = array_flip((new QuotationDetailHpp)->getFillable());
        $cossAllowed = array_flip((new QuotationDetailCoss)->getFillable());

        $hppFinalData = [];
        $cossFinalData = [];

        $parseNumber = function ($val) {
            if ($val === '' || $val === null) {
                return null;
            }
            if (is_numeric($val)) {
                return (float) $val;
            }

            return (float) str_replace(',', '.', str_replace('.', '', $val));
        };

        $hppReferenceMap = $preResetHppMap ?? ($calculationResult->quotation->_hpp_map ?? collect());
        $cossReferenceMap = $preResetCossMap ?? ($calculationResult->quotation->_coss_map ?? collect());

        $syncHppToCossFields = ['tunjangan_hari_raya', 'kompensasi'];

        foreach ($calculationResult->detail_calculations as $detailId => $detailCalculation) {
            $hppData = $detailCalculation->hpp_data;
            $cossData = $detailCalculation->coss_data;
            $detailForCheck = $detailsMap->get($detailId);
            $isRoDetail = $detailForCheck && $this->helper->isRo($detailForCheck);
            if ($detailForCheck) {
                $hppData['gaji_pokok'] = $detailForCheck->nominal_upah;
                $cossData['gaji_pokok'] = $detailForCheck->nominal_upah;
            }

            $storedHpp = $hppReferenceMap->get($detailId);
            $storedCoss = $cossReferenceMap->get($detailId);

            foreach ($editableFields as $field) {
                $hppEdited = $request?->has("hpp_editable_data.$detailId.$field");
                $cossExplicit = ! $isRoDetail && $request?->has("coss_data.$detailId.$field");

                if ($hppEdited) {
                    $hppData[$field] = $parseNumber($request->input("hpp_editable_data.$detailId.$field"));
                }

                if (! $isRoDetail) {
                    if (in_array($field, $syncHppToCossFields) && $hppEdited) {
                        $preResetValue = $storedHpp ? (float) ($storedHpp->{$field} ?? 0) : null;
                        $requestHppValue = (float) ($hppData[$field] ?? 0);
                        $hppActuallyChanged = $preResetValue !== null
                            && abs($requestHppValue - $preResetValue) > 0.01;

                        if ($hppActuallyChanged) {
                            $cossData[$field] = $hppData[$field];
                        } elseif ($cossExplicit) {
                            $cossData[$field] = $parseNumber($request->input("coss_data.$detailId.$field"));
                        } else {
                            if ($storedCoss && $storedHpp) {
                                $preResetCossVal = (float) ($storedCoss->{$field} ?? 0);
                                $preResetHppVal = (float) ($storedHpp->{$field} ?? 0);

                                if (abs($preResetCossVal - $preResetHppVal) < 0.01) {
                                    $cossData[$field] = $hppData[$field];
                                } else {
                                    $cossData[$field] = $preResetCossVal;
                                }
                            }
                        }
                    } elseif ($cossExplicit) {
                        $cossData[$field] = $parseNumber($request->input("coss_data.$detailId.$field"));
                    }
                }
            }

            foreach ($bpjsMap as $reqKey => $dbKey) {
                if ($request?->has("bpjs_persentase_data.$detailId.$reqKey")) {
                    $hppData[$dbKey] = $parseNumber($request->input("bpjs_persentase_data.$detailId.$reqKey"));
                }
            }

            $commonMetadata = [
                'quotation_detail_id' => $detailId,
                'persen_management_fee' => $persentase,
                'updated_by' => $user,
                'updated_at' => $currentDateTime,
            ];

            $hppFull = array_merge($hppData, $commonMetadata, [
                'management_fee' => $summary->nominal_management_fee ?? 0,
                'grand_total' => $summary->grand_total_sebelum_pajak ?? 0,
                'ppn' => $summary->ppn ?? 0,
                'pph' => $summary->pph ?? 0,
                'total_invoice' => $summary->total_invoice ?? 0,
                'pembulatan' => $summary->pembulatan ?? 0,
                'is_pembulatan' => (($summary->pembulatan ?? 0) != ($summary->total_invoice ?? 0)) ? 1 : 0,
            ]);

            $cossFull = array_merge($cossData, $commonMetadata, [
                'management_fee' => $summary->nominal_management_fee_coss ?? 0,
                'grand_total' => $summary->grand_total_sebelum_pajak_coss ?? 0,
                'ppn' => $summary->ppn_coss ?? 0,
                'pph' => $summary->pph_coss ?? 0,
                'total_invoice' => $summary->total_invoice_coss ?? 0,
                'pembulatan' => $summary->pembulatan_coss ?? 0,
                'is_pembulatan' => (($summary->pembulatan_coss ?? 0) != ($summary->total_invoice_coss ?? 0)) ? 1 : 0,
            ]);

            $hppFinalData[] = array_intersect_key($hppFull, $hppAllowed);
            $cossFinalData[] = array_intersect_key($cossFull, $cossAllowed);
        }

        $this->persistCalculationRows(QuotationDetailHpp::class, $hppFinalData, $currentDateTime);
        $this->persistCalculationRows(QuotationDetailCoss::class, $cossFinalData, $currentDateTime);
    }

    /**
     * Persist baris HPP/COSS: update existing rows, batch-insert missing rows.
     */
    public function persistCalculationRows(string $modelClass, array $rows, Carbon $currentDateTime): void
    {
        if (empty($rows)) {
            return;
        }

        $detailIds = array_values(array_filter(array_map(
            fn ($row) => $row['quotation_detail_id'] ?? null,
            $rows
        )));

        $existingIds = empty($detailIds)
            ? collect()
            : $modelClass::whereIn('quotation_detail_id', $detailIds)
                ->pluck('quotation_detail_id')
                ->flip();

        $inserts = [];
        foreach ($rows as $data) {
            $detailId = $data['quotation_detail_id'] ?? 0;

            if ($existingIds->has($detailId)) {
                $modelClass::where('quotation_detail_id', $detailId)->update($data);
            } else {
                $inserts[] = $data + ['created_at' => $currentDateTime];
            }
        }

        if (! empty($inserts)) {
            $modelClass::insert($inserts);
        }
    }
}
