<?php

namespace App\Services\Quotation\Steps;

use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\QuotationDetailCoss;
use App\Models\QuotationDetailHpp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Manages QuotationDetail CRUD — soft-delete, sync from array, upah sync.
 * Extracted from StepHelperTrait for single responsibility.
 */
class StepQuotationDetailService
{
    /**
     * Soft delete quotation detail and all its relations – Optimized batch
     */
    public function softDeleteQuotationDetail(QuotationDetail $detail, string $timestamp, string $user): void
    {
        $detailId = $detail->id;

        $detail->update([
            'deleted_at' => $timestamp,
            'deleted_by' => $user,
        ]);

        $tables = [
            'sl_quotation_detail_hpp',
            'sl_quotation_detail_coss',
            'sl_quotation_detail_tunjangan',
            'sl_quotation_detail_wages',
            'sl_quotation_detail_requirement',
        ];

        foreach ($tables as $table) {
            DB::table($table)
                ->where('quotation_detail_id', $detailId)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => $timestamp,
                    'deleted_by' => $user,
                ]);
        }
    }

    /**
     * Soft delete all details for a quotation – Batch version
     */
    public function softDeleteAllQuotationDetails(Quotation $quotation, string $timestamp, string $user): void
    {
        $detailIds = QuotationDetail::where('quotation_id', $quotation->id)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        if (empty($detailIds)) {
            return;
        }

        QuotationDetail::whereIn('id', $detailIds)->update([
            'deleted_at' => $timestamp,
            'deleted_by' => $user,
        ]);

        $tables = [
            'sl_quotation_detail_hpp',
            'sl_quotation_detail_coss',
            'sl_quotation_detail_tunjangan',
            'sl_quotation_detail_wages',
            'sl_quotation_detail_requirement',
        ];

        foreach ($tables as $table) {
            DB::table($table)
                ->whereIn('quotation_detail_id', $detailIds)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => $timestamp,
                    'deleted_by' => $user,
                ]);
        }
    }

    /**
     * Sync detail HC from array – handles create/update/delete
     */
    public function syncDetailHCFromArray(Quotation $quotation, array $details, string $timestamp, string $user): void
    {
        try {
            $existingDetails = QuotationDetail::where('quotation_id', $quotation->id)
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('id');

            $incomingIds = collect($details)
                ->filter(fn ($d) => ! empty($d['id']))
                ->pluck('id')
                ->toArray();

            foreach ($existingDetails as $id => $detail) {
                if (! in_array($id, $incomingIds)) {
                    $this->softDeleteQuotationDetail($detail, $timestamp, $user);
                }
            }

            $existingHpp = QuotationDetailHpp::whereIn('quotation_detail_id', $incomingIds)
                ->get()
                ->keyBy('quotation_detail_id');
            $existingCoss = QuotationDetailCoss::whereIn('quotation_detail_id', $incomingIds)
                ->get()
                ->keyBy('quotation_detail_id');

            $hppInsert = [];
            $cossInsert = [];
            $hppUpdate = [];
            $cossUpdate = [];

            foreach ($details as $detailData) {
                $detailId = $detailData['id'] ?? null;

                if ($detailId && $existingDetails->has($detailId)) {
                    $detail = $existingDetails->get($detailId);
                    $detail->update([
                        'quotation_site_id' => $detailData['quotation_site_id'],
                        'position_id' => $detailData['position_id'],
                        'jumlah_hc' => $detailData['jumlah_hc'] ?? 0,
                        'jabatan_kebutuhan' => $detailData['jabatan_kebutuhan'] ?? $detail->jabatan_kebutuhan,
                        'nama_site' => $detailData['nama_site'] ?? $detail->nama_site,
                        'nominal_upah' => $detailData['nominal_upah'] ?? $detail->nominal_upah,
                        'updated_at' => $timestamp,
                        'updated_by' => $user,
                    ]);

                    $hpp = $existingHpp->get($detailId);
                    if ($hpp) {
                        $hppUpdate[] = [
                            'id' => $hpp->id,
                            'jumlah_hc' => $detailData['jumlah_hc'] ?? 0,
                            'updated_at' => $timestamp,
                            'updated_by' => $user,
                        ];
                    } else {
                        $hppInsert[] = [
                            'quotation_id' => $quotation->id,
                            'quotation_detail_id' => $detailId,
                            'leads_id' => $quotation->leads_id,
                            'position_id' => $detailData['position_id'],
                            'jumlah_hc' => $detailData['jumlah_hc'] ?? 0,
                            'created_at' => $timestamp,
                            'created_by' => $user,
                            'created_by_user_id' => Auth::id(),
                        ];
                    }

                    $coss = $existingCoss->get($detailId);
                    if ($coss) {
                        $cossUpdate[] = [
                            'id' => $coss->id,
                            'jumlah_hc' => $detailData['jumlah_hc'] ?? 0,
                            'updated_at' => $timestamp,
                            'updated_by' => $user,
                        ];
                    } else {
                        $cossInsert[] = [
                            'quotation_id' => $quotation->id,
                            'quotation_detail_id' => $detailId,
                            'leads_id' => $quotation->leads_id,
                            'position_id' => $detailData['position_id'],
                            'jumlah_hc' => $detailData['jumlah_hc'] ?? 0,
                            'created_at' => $timestamp,
                            'created_by' => $user,
                            'created_by_user_id' => Auth::id(),
                        ];
                    }
                } else {
                    $newDetail = QuotationDetail::create([
                        'quotation_id' => $quotation->id,
                        'quotation_site_id' => $detailData['quotation_site_id'],
                        'nama_site' => $detailData['nama_site'] ?? null,
                        'position_id' => $detailData['position_id'],
                        'jabatan_kebutuhan' => $detailData['jabatan_kebutuhan'] ?? null,
                        'jumlah_hc' => $detailData['jumlah_hc'] ?? 0,
                        'nominal_upah' => $detailData['nominal_upah'] ?? 0,
                        'created_at' => $timestamp,
                        'created_by' => $user,
                        'created_by_user_id' => Auth::id(),
                    ]);

                    $hppInsert[] = [
                        'quotation_id' => $quotation->id,
                        'quotation_detail_id' => $newDetail->id,
                        'leads_id' => $quotation->leads_id,
                        'position_id' => $detailData['position_id'],
                        'jumlah_hc' => $detailData['jumlah_hc'] ?? 0,
                        'created_at' => $timestamp,
                        'created_by' => $user,
                        'created_by_user_id' => Auth::id(),
                    ];

                    $cossInsert[] = [
                        'quotation_id' => $quotation->id,
                        'quotation_detail_id' => $newDetail->id,
                        'leads_id' => $quotation->leads_id,
                        'position_id' => $detailData['position_id'],
                        'jumlah_hc' => $detailData['jumlah_hc'] ?? 0,
                        'created_at' => $timestamp,
                        'created_by' => $user,
                        'created_by_user_id' => Auth::id(),
                    ];
                }
            }

            if (! empty($hppInsert)) {
                QuotationDetailHpp::insert($hppInsert);
            }
            if (! empty($cossInsert)) {
                QuotationDetailCoss::insert($cossInsert);
            }

            foreach ($hppUpdate as $data) {
                QuotationDetailHpp::where('id', $data['id'])->update([
                    'jumlah_hc' => $data['jumlah_hc'],
                    'updated_at' => $data['updated_at'] ?? now(),
                    'updated_by' => $data['updated_by'] ?? auth()->user()->full_name,
                ]);
            }
            foreach ($cossUpdate as $data) {
                QuotationDetailCoss::where('id', $data['id'])->update([
                    'jumlah_hc' => $data['jumlah_hc'],
                    'updated_at' => $data['updated_at'] ?? now(),
                    'updated_by' => $data['updated_by'] ?? auth()->user()->full_name,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error in syncDetailHCFromArray', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update nominal upah untuk setiap position berdasarkan site yang terkait
     */
    public function updateUpahPerPosition(Quotation $quotation): void
    {
        try {
            $quotationDetails = QuotationDetail::with(['quotationSite', 'wage'])
                ->where('quotation_id', $quotation->id)
                ->get();

            foreach ($quotationDetails as $detail) {
                if ($detail->wage && $detail->wage->upah === 'Custom') {
                    continue;
                }

                if ($detail->quotationSite) {
                    $newNominalUpah = $detail->quotationSite->nominal_upah;

                    $detail->update([
                        'nominal_upah' => $newNominalUpah,
                        'updated_by' => Auth::user()->full_name,
                    ]);

                    if ($detail->wage) {
                        if (in_array($detail->wage->upah, ['UMP', 'UMK'])) {
                            $detail->wage->update([
                                'hitungan_upah' => 'Per Bulan',
                                'updated_by' => Auth::user()->full_name,
                            ]);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error updating upah per position', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to update upah per position: '.$e->getMessage());
        }
    }
}
