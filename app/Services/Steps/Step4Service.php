<?php

namespace App\Services\Steps;

use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\QuotationDetailCoss;
use App\Models\QuotationDetailHpp;
use App\Models\QuotationDetailWage;
use App\Models\QuotationManagementFee;
use App\Models\QuotationSite;
use App\Models\Umk;
use App\Models\Ump;
use App\Services\Steps\Traits\StepHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Step4Service
{
    use StepHelperTrait;

    public function execute(Quotation $quotation, Request $request): void
    {
        DB::beginTransaction();
        try {
            Log::info('Starting updateStep4', [
                'quotation_id' => $quotation->id,
                'has_position_data' => $request->has('position_data'),
                'has_global_data' => $request->hasAny(['is_ppn', 'ppn_pph_dipotong', 'management_fee_id', 'persentase']),
                'global_data_received' => $request->only(['is_ppn', 'ppn_pph_dipotong', 'management_fee_id', 'persentase']),
            ]);

            // ============================================================
            // UPDATE GLOBAL QUOTATION DATA (jika ada di request)
            // ============================================================
            $globalData = [];

            $globalFields = [
                'is_ppn' => 'is_ppn',
                'ppn_pph_dipotong' => 'ppn_pph_dipotong',
                'management_fee_id' => 'management_fee_id',
                'persentase' => 'persentase',
            ];

            foreach ($globalFields as $field => $requestField) {
                if ($request->filled($requestField)) {
                    $globalData[$field] = $request->$requestField;
                    Log::info('Global data found', [
                        'field' => $field,
                        'value' => $request->$requestField,
                    ]);
                } else {
                    Log::info('Global field not filled, keeping existing value', [
                        'field' => $field,
                        'request_value' => $request->$requestField ?? 'null',
                    ]);
                }
            }

            if (! empty($globalData)) {
                $globalData['updated_by'] = Auth::user()->full_name;
                $quotation->update($globalData);

                Log::info('Updated global quotation data', [
                    'quotation_id' => $quotation->id,
                    'global_data' => $globalData,
                ]);
            } else {
                Log::warning('No global data found in request for step 4');
            }

            // ============================================================
            // UPDATE POSITION DATA (jika ada)
            // ============================================================
            if ($request->has('position_data') && is_array($request->position_data)) {
                foreach ($request->position_data as $positionData) {
                    $this->updatePositionStep4($quotation, $positionData);
                }

                // Synchronize upah for all positions after update
                $this->updateUpahPerPosition($quotation);

                Log::info('Updated position data', [
                    'quotation_id' => $quotation->id,
                    'position_count' => count($request->position_data),
                ]);
            }

            if ($request->has('management_fee_components')) {
                $this->saveManagementFeeConfig($quotation, $request->management_fee_components);
            }

            // Update quotation timestamp
            $quotation->update([
                'updated_by' => Auth::user()->full_name,
                'calculated_at' => null,
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in updateStep4', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);
            throw $e;
        }
    }

    private function saveManagementFeeConfig(Quotation $quotation, array $components): void
    {
        $allowedFlags = QuotationManagementFee::componentFlags();

        $sanitizedFlags = [];
        foreach ($allowedFlags as $flag) {
            $sanitizedFlags[$flag] = isset($components[$flag]) && (bool) $components[$flag];
        }

        QuotationManagementFee::upsertForQuotation($quotation->id, $sanitizedFlags);

        Log::info('Saved management fee component ', [
            'quotation_id' => $quotation->id,
            'flags' => $sanitizedFlags,
        ]);
    }

    private function updatePositionStep4(Quotation $quotation, array $positionData): void
    {
        try {
            $detail = QuotationDetail::where('id', $positionData['quotation_detail_id'])
                ->where('quotation_id', $quotation->id)
                ->first();

            if (! $detail) {
                Log::warning('Quotation detail not found', [
                    'quotation_detail_id' => $positionData['quotation_detail_id'],
                    'quotation_id' => $quotation->id,
                ]);

                return;
            }

            // Calculate upah data untuk position ini
            $upahData = $this->calculateUpahForPosition($detail, $positionData);

            // Data untuk wage table
            $wageData = [
                'quotation_id' => $quotation->id,
                'upah' => $positionData['upah'] ?? 'UMK',
                'hitungan_upah' => $upahData['hitungan_upah'],
                'lembur' => $positionData['lembur'] ?? 'Tidak Ada',
                'nominal_upah' => $upahData['nominal_upah'] ?? null,
                'nominal_lembur' => isset($positionData['nominal_lembur']) ? str_replace('.', '', $positionData['nominal_lembur']) : null,
                'jenis_bayar_lembur' => $positionData['jenis_bayar_lembur'] ?? null,
                'jam_per_bulan_lembur' => $positionData['jam_per_bulan_lembur'] ?? null,
                'lembur_ditagihkan' => $positionData['lembur_ditagihkan'] ?? null,
                'kompensasi' => $positionData['kompensasi'] ?? 'Tidak Ada',
                'thr' => $positionData['thr'] ?? 'Tidak Ada',
                'tunjangan_holiday' => $positionData['tunjangan_holiday'] ?? 'Tidak Ada',
                'nominal_tunjangan_holiday' => isset($positionData['nominal_tunjangan_holiday']) ? str_replace('.', '', $positionData['nominal_tunjangan_holiday']) : null,
                'jenis_bayar_tunjangan_holiday' => $positionData['jenis_bayar_tunjangan_holiday'] ?? null,
                'updated_by' => Auth::user()->full_name,
            ];

            // Simpan data wage
            $wage = QuotationDetailWage::updateOrCreate(
                ['quotation_detail_id' => $detail->id],
                array_merge($wageData, [
                    'created_by' => Auth::user()->full_name,
                    'created_by_user_id' => Auth::id(),
                ])
            );

            // Reset HPP/COSS values for recalculation
            $hpp = QuotationDetailHpp::where('quotation_detail_id', $detail->id)->first();
            $coss = QuotationDetailCoss::where('quotation_detail_id', $detail->id)->first();
            if ($hpp) {
                $hppUpdateData = [];

                if (in_array(strtolower($wageData['thr']), ['diprovisikan'])) {
                    $hppUpdateData['tunjangan_hari_raya'] = $upahData['nominal_upah'] / 12;
                }

                if (in_array(strtolower($wageData['kompensasi']), ['diprovisikan'])) {
                    $hppUpdateData['kompensasi'] = $upahData['nominal_upah'] / 12;
                }

                $hppUpdateData['tunjangan_hari_libur_nasional'] = null;
                $hppUpdateData['lembur'] = null;

                if (! empty($hppUpdateData)) {
                    $hppUpdateData['updated_by'] = Auth::user()->full_name;
                    $hpp->update($hppUpdateData);
                }
                if ($coss) {
                    $cossUpdateData = [];
                    if (in_array(strtolower($wageData['thr']), ['diprovisikan'])) {
                        $cossUpdateData['tunjangan_hari_raya'] = $upahData['nominal_upah'] / 12;
                    }

                    if (in_array(strtolower($wageData['kompensasi']), ['diprovisikan'])) {
                        $cossUpdateData['kompensasi'] = $upahData['nominal_upah'] / 12;
                    }

                    $cossUpdateData['tunjangan_hari_libur_nasional'] = null;
                    $cossUpdateData['lembur'] = null;

                    if (! empty($cossUpdateData)) {
                        $cossUpdateData['updated_by'] = Auth::user()->full_name;
                        $coss->update($cossUpdateData);
                    }
                }
            }

            // Update nominal_upah di quotation_detail
            $detail->update([
                'nominal_upah' => $upahData['nominal_upah'],
                'updated_by' => Auth::user()->full_name,
            ]);

            Log::info('Successfully updated step 4 wage for position', [
                'quotation_detail_id' => $detail->id,
                'position_id' => $detail->position_id,
                'thr' => $wageData['thr'],
                'kompensasi' => $wageData['kompensasi'],
                'tunjangan_holiday' => $wageData['tunjangan_holiday'],
                'nominal_tunjangan_holiday' => $wageData['nominal_tunjangan_holiday'],
                'lembur' => $wageData['lembur'],
                'nominal_lembur' => $wageData['nominal_lembur'],
            ]);

        } catch (\Exception $e) {
            Log::error('Error in updatePositionStep4', [
                'quotation_detail_id' => $positionData['quotation_detail_id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function calculateUpahForPosition(QuotationDetail $detail, array $positionData): array
    {
        $nominalUpah = $detail->nominal_upah;
        $hitunganUpah = 'Per Bulan';

        if (($positionData['upah'] ?? null) == 'Custom') {
            $hitunganUpah = $positionData['hitungan_upah'] ?? 'Per Bulan';
            $customUpah = $positionData['nominal_upah'] ?? 0;

            if (is_string($customUpah)) {
                $customUpah = str_replace('.', '', $customUpah);
            }

            $nominalUpah = $customUpah;
        } else {
            $site = QuotationSite::find($detail->quotation_site_id);
            if ($site) {
                if (($positionData['upah'] ?? null) == 'UMP') {
                    $dataUmp = Ump::byProvince($site->provinsi_id)
                        ->active()
                        ->first();
                    $nominalUpah = $dataUmp ? $dataUmp->ump : 0;
                } elseif (($positionData['upah'] ?? null) == 'UMK') {
                    $dataUmk = Umk::byCity($site->kota_id)
                        ->active()
                        ->first();
                    $nominalUpah = $dataUmk ? $dataUmk->umk : 0;
                }

                $site->update([
                    'nominal_upah' => $nominalUpah,
                    'updated_by' => Auth::user()->full_name,
                ]);
            }
        }

        return [
            'nominal_upah' => $nominalUpah,
            'hitungan_upah' => $hitunganUpah,
        ];
    }
}
