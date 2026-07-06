<?php

namespace App\Services\Steps\Traits;

use App\DTO\QuotationCalculationResult;
use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\QuotationDetailCoss;
use App\Models\QuotationDetailHpp;
use App\Models\QuotationDetailTunjangan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait StepHelperTrait
{
    /**
     * Soft delete quotation detail and all its relations – Optimized batch
     */
    private function softDeleteQuotationDetail(QuotationDetail $detail, string $timestamp, string $user): void
    {
        $detailId = $detail->id;

        // Soft delete detail
        $detail->update([
            'deleted_at' => $timestamp,
            'deleted_by' => $user,
        ]);

        // Soft delete all related tables in batch
        // NOTE: Using DB::table() for bulk performance — model events not needed for soft-delete child rows
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
    private function softDeleteAllQuotationDetails(Quotation $quotation, string $timestamp, string $user): void
    {
        $detailIds = QuotationDetail::where('quotation_id', $quotation->id)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        if (empty($detailIds)) {
            return;
        }

        // Soft delete main details
        QuotationDetail::whereIn('id', $detailIds)->update([
            'deleted_at' => $timestamp,
            'deleted_by' => $user,
        ]);

        // Soft delete related data in batch
        // NOTE: Using DB::table() for bulk performance — model events not needed for soft-delete child rows
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
     * Sync tunjangan data – Optimized batch
     */
    private function syncTunjanganData(Quotation $quotation, array $tunjanganData, Carbon $currentDateTime, string $user): void
    {
        $detailIds = array_keys($tunjanganData);

        // 1. Ambil semua tunjangan yang ada saat ini
        $existingTunjangans = QuotationDetailTunjangan::whereIn('quotation_detail_id', $detailIds)
            ->whereNull('deleted_at')
            ->get()
            ->groupBy('quotation_detail_id');

        $insertData = [];
        $updateData = [];

        foreach ($tunjanganData as $detailId => $tunjangans) {
            $existing = $existingTunjangans->get($detailId, collect())->keyBy('nama_tunjangan');
            $processed = [];

            // Pastikan $tunjangans adalah array
            foreach ($tunjangans ?? [] as $item) {
                $nama = trim($item['nama_tunjangan'] ?? '');
                if (empty($nama)) {
                    continue;
                }

                $jenis = $item['jenis'] ?? 'Nominal';

                if (in_array($jenis, ['Normatif', 'Ditagihkan'])) {
                    $nominal = 0;
                    $nominalCoss = 0;
                } else {
                    $nominal = $this->parseNominal($item['nominal'] ?? 0);
                    $nominalCoss = $this->parseNominal($item['nominal_coss'] ?? 0);
                }

                $processed[] = $nama;

                if ($existing->has($nama)) {
                    $updateData[] = [
                        'id' => $existing[$nama]->id,
                        'nominal' => $nominal,
                        'nominal_coss' => $nominalCoss,
                        'jenis' => $jenis,
                        'updated_at' => $currentDateTime,
                        'updated_by' => $user,
                    ];
                } else {
                    $insertData[] = [
                        'quotation_id' => $quotation->id,
                        'quotation_detail_id' => $detailId,
                        'nama_tunjangan' => $nama,
                        'nominal' => $nominal,
                        'nominal_coss' => $nominalCoss,
                        'jenis' => $jenis,
                        'created_at' => $currentDateTime,
                        'created_by' => $user,
                        'created_by_user_id' => Auth::id(),
                    ];
                }
            }

            $toDelete = $existing->keys()->diff($processed);

            if ($toDelete->isNotEmpty()) {
                QuotationDetailTunjangan::where('quotation_detail_id', $detailId)
                    ->whereIn('nama_tunjangan', $toDelete->toArray())
                    ->whereNull('deleted_at')
                    ->update([
                        'deleted_at' => $currentDateTime,
                        'deleted_by' => $user,
                    ]);
            }
        }

        // Batch insert
        if (! empty($insertData)) {
            QuotationDetailTunjangan::insert($insertData);
        }

        // Batch update — using upsert for bulk performance
        if (! empty($updateData)) {
            foreach ($updateData as $data) {
                $id = $data['id'];
                unset($data['id']);
                QuotationDetailTunjangan::where('id', $id)->update($data);
            }
        }
    }

    /**
     * Update nominal BPJS KS di HPP dan COSS
     * Uses upsert for bulk performance
     */
    private function updateBpjsKsNominal(Quotation $quotation, array $bpjsKsData, string $user, Carbon $currentDateTime): void
    {
        Log::info('Updating BPJS KS nominal', [
            'quotation_id' => $quotation->id,
            'details_count' => count($bpjsKsData),
        ]);

        $hppUpdates = [];
        $cossUpdates = [];

        foreach ($bpjsKsData as $detailId => $nominalBpjsKs) {
            if (is_string($nominalBpjsKs)) {
                $nominalBpjsKs = (float) str_replace(['.', ','], ['', '.'], $nominalBpjsKs);
            }

            $hppUpdates[] = [
                'quotation_detail_id' => $detailId,
                'bpjs_ks' => $nominalBpjsKs,
                'updated_by' => $user,
                'updated_at' => $currentDateTime,
            ];

            $cossUpdates[] = [
                'quotation_detail_id' => $detailId,
                'bpjs_ks' => $nominalBpjsKs,
                'updated_by' => $user,
                'updated_at' => $currentDateTime,
            ];
        }

        // HPP/COSS records already exist — use individual updates (not upsert,
        // which would require position_id for INSERT path).
        foreach ($hppUpdates as $data) {
            QuotationDetailHpp::where('quotation_detail_id', $data['quotation_detail_id'])->update([
                'bpjs_ks' => $data['bpjs_ks'],
                'updated_by' => $data['updated_by'],
                'updated_at' => $data['updated_at'],
            ]);
        }

        foreach ($cossUpdates as $data) {
            QuotationDetailCoss::where('quotation_detail_id', $data['quotation_detail_id'])->update([
                'bpjs_ks' => $data['bpjs_ks'],
                'updated_by' => $data['updated_by'],
                'updated_at' => $data['updated_at'],
            ]);
        }

        Log::info('Updated BPJS KS nominal', [
            'quotation_id' => $quotation->id,
            'hpp_count' => count($hppUpdates),
            'coss_count' => count($cossUpdates),
        ]);
    }

    /**
     * Sync detail HC from array — shared between Step3 and others
     * Uses upsert for bulk performance on HPP/COSS updates
     */
    public function syncDetailHCFromArray(Quotation $quotation, array $details, string $timestamp, string $user): void
    {
        try {
            // Ambil semua detail existing (aktif) untuk quotation ini, jadikan key by id
            $existingDetails = QuotationDetail::where('quotation_id', $quotation->id)
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('id');

            // Kumpulkan id yang dikirim dari frontend (yang punya id)
            $incomingIds = collect($details)
                ->filter(fn ($d) => ! empty($d['id']))
                ->pluck('id')
                ->toArray();

            // Hapus (soft delete) detail yang id-nya tidak ada di request
            foreach ($existingDetails as $id => $detail) {
                if (! in_array($id, $incomingIds)) {
                    Log::info('Soft deleting quotation detail', [
                        'id' => $id,
                        'position_id' => $detail->position_id,
                        'quotation_site_id' => $detail->quotation_site_id,
                    ]);
                    $this->softDeleteQuotationDetail($detail, $timestamp, $user);
                }
            }

            // Preload existing HPP & COSS untuk update batch
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
                    // UPDATE EXISTING
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

                    // Siapkan update HPP & COSS (batch nanti)
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
                    // CREATE NEW (tidak punya id)
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

            // Batch insert & update HPP/COSS
            if (! empty($hppInsert)) {
                QuotationDetailHpp::insert($hppInsert);
            }
            if (! empty($cossInsert)) {
                QuotationDetailCoss::insert($cossInsert);
            }

            // Bulk update existing records — HPP/COSS rows are pre-created,
            // so use individual updates (not upsert, which would require position_id).
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
    private function updateUpahPerPosition(Quotation $quotation): void
    {
        try {
            Log::info('=== updateUpahPerPosition START ===', [
                'quotation_id' => $quotation->id,
            ]);

            // Ambil semua quotation details dengan relasi site dan wage
            $quotationDetails = QuotationDetail::with(['quotationSite', 'wage'])
                ->where('quotation_id', $quotation->id)
                ->get();

            foreach ($quotationDetails as $detail) {
                Log::info('Processing detail', [
                    'detail_id' => $detail->id,
                    'current_nominal_upah' => $detail->nominal_upah,
                    'has_wage' => ! is_null($detail->wage),
                    'wage_upah_type' => $detail->wage ? $detail->wage->upah : 'no_wage',
                ]);

                // JANGAN update nominal_upah jika wage type adalah Custom
                // Hanya update untuk UMP/UMK
                if ($detail->wage && $detail->wage->upah === 'Custom') {
                    Log::info('Skipping update for Custom upah', [
                        'detail_id' => $detail->id,
                        'reason' => 'Custom upah should not be overwritten by site nominal_upah',
                    ]);

                    continue;
                }

                // Jika detail memiliki quotation site, gunakan nominal_upah dari site tersebut
                // HANYA untuk UMP/UMK
                if ($detail->quotationSite) {
                    $newNominalUpah = $detail->quotationSite->nominal_upah;

                    Log::info('Updating UMP/UMK upah from site', [
                        'detail_id' => $detail->id,
                        'site_nominal_upah' => $newNominalUpah,
                        'current_detail_nominal_upah' => $detail->nominal_upah,
                    ]);

                    // Update nominal_upah di quotation_detail
                    $detail->update([
                        'nominal_upah' => $newNominalUpah,
                        'updated_by' => Auth::user()->full_name,
                    ]);

                    // Jika ada data wage, update juga hitungan_upah jika diperlukan
                    if ($detail->wage) {
                        // Jika upah type adalah UMP/UMK, update hitungan_upah ke "Per Bulan"
                        if (in_array($detail->wage->upah, ['UMP', 'UMK'])) {
                            $detail->wage->update([
                                'hitungan_upah' => 'Per Bulan',
                                'updated_by' => Auth::user()->full_name,
                            ]);
                        }
                    }
                }
            }

            Log::info('=== updateUpahPerPosition END ===');

        } catch (\Exception $e) {
            Log::error('Error updating upah per position', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to update upah per position: '.$e->getMessage());
        }
    }

    /**
     * Reset semua nilai calculated values (HPP & COSS)
     * Captures snapshot before reset for later comparison
     */
    private function resetAllCalculatedValues(Quotation $quotation, string $user, Carbon $currentDateTime): void
    {
        Log::info('=== RESET ALL CALCULATED VALUES (bulk) ===', [
            'quotation_id' => $quotation->id,
        ]);

        $detailIds = $quotation->quotationDetails->pluck('id')->toArray();

        if (empty($detailIds)) {
            return;
        }

        // Field yang di-reset untuk HPP
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
    private function saveAllCalculationResults(
        QuotationCalculationResult $calculationResult,
        string $user,
        Carbon $currentDateTime,
        ?Request $request = null,
        $preResetHppMap = null,
        $preResetCossMap = null
    ): void {
        // 1. Persiapan data di luar loop (Optimasi Performa)
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

        // Ambil fillable untuk filter kolom yang valid
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

        // Preload HPP & COSS DB map SEBELUM RESET untuk deteksi perubahan aktual.
        $hppReferenceMap = $preResetHppMap ?? ($calculationResult->quotation->_hpp_map ?? collect());
        $cossReferenceMap = $preResetCossMap ?? ($calculationResult->quotation->_coss_map ?? collect());

        // Field yang sync-nya satu arah: HPP berubah → COSS ikut HPP.
        $syncHppToCossFields = ['tunjangan_hari_raya', 'kompensasi'];

        // 2. Loop Utama
        foreach ($calculationResult->detail_calculations as $detailId => $detailCalculation) {
            $hppData = $detailCalculation->hpp_data;
            $cossData = $detailCalculation->coss_data;
            $detailForCheck = $detailsMap->get($detailId);
            $isRoDetail = $detailForCheck && $this->isRo($detailForCheck);
            if ($detailForCheck) {
                $hppData['gaji_pokok'] = $detailForCheck->nominal_upah;
                $cossData['gaji_pokok'] = $detailForCheck->nominal_upah;
            }

            // Nilai HPP & COSS sebelum reset — referensi untuk deteksi perubahan user
            $storedHpp = $hppReferenceMap->get($detailId);
            $storedCoss = $cossReferenceMap->get($detailId);

            Log::info('saveAllCalc: detail start', [
                'detail_id' => $detailId,
                'has_stored_hpp' => $storedHpp !== null,
                'stored_thr' => $storedHpp ? $storedHpp->tunjangan_hari_raya : 'N/A',
                'stored_kompensasi' => $storedHpp ? $storedHpp->kompensasi : 'N/A',
                'req_hpp_thr' => $request?->input("hpp_editable_data.$detailId.tunjangan_hari_raya"),
                'req_coss_thr' => $request?->input("coss_data.$detailId.tunjangan_hari_raya"),
                'req_hpp_kompensasi' => $request?->input("hpp_editable_data.$detailId.kompensasi"),
                'req_coss_kompensasi' => $request?->input("coss_data.$detailId.kompensasi"),
            ]);

            // A. PROSES USER EDITS
            foreach ($editableFields as $field) {
                $hppEdited = $request?->has("hpp_editable_data.$detailId.$field");
                $cossExplicit = ! $isRoDetail && $request?->has("coss_data.$detailId.$field");

                // Edit HPP
                if ($hppEdited) {
                    $hppData[$field] = $parseNumber($request->input("hpp_editable_data.$detailId.$field"));
                }

                // Edit COSS (Hanya jika bukan RO)
                if (! $isRoDetail) {
                    if (in_array($field, $syncHppToCossFields) && $hppEdited) {
                        $preResetValue = $storedHpp ? (float) ($storedHpp->{$field} ?? 0) : null;
                        $requestHppValue = (float) ($hppData[$field] ?? 0);
                        $hppActuallyChanged = $preResetValue !== null
                            && abs($requestHppValue - $preResetValue) > 0.01;

                        if ($hppActuallyChanged) {
                            $cossData[$field] = $hppData[$field];
                            Log::info('Synced HPP edit to COSS (HPP changed)', [
                                'detail_id' => $detailId,
                                'field' => $field,
                                'pre_reset_hpp' => $preResetValue,
                                'new_value' => $hppData[$field],
                            ]);
                        } elseif ($cossExplicit) {
                            $cossData[$field] = $parseNumber($request->input("coss_data.$detailId.$field"));
                            Log::info('COSS edited independently (HPP unchanged)', [
                                'detail_id' => $detailId,
                                'field' => $field,
                                'hpp_value' => $hppData[$field],
                                'coss_value' => $cossData[$field],
                            ]);
                        } else {
                            if ($storedCoss && $storedHpp) {
                                $preResetCossVal = (float) ($storedCoss->{$field} ?? 0);
                                $preResetHppVal = (float) ($storedHpp->{$field} ?? 0);

                                if (abs($preResetCossVal - $preResetHppVal) < 0.01) {
                                    $cossData[$field] = $hppData[$field];
                                    Log::info('COSS kept in sync with HPP (was already synced)', [
                                        'detail_id' => $detailId,
                                        'field' => $field,
                                        'value' => $hppData[$field],
                                    ]);
                                } else {
                                    $cossData[$field] = $preResetCossVal;
                                    Log::info('COSS restored (was independent from HPP)', [
                                        'detail_id' => $detailId,
                                        'field' => $field,
                                        'pre_reset_coss' => $preResetCossVal,
                                        'pre_reset_hpp' => $preResetHppVal,
                                    ]);
                                }
                            }
                        }
                    } elseif ($cossExplicit) {
                        $cossData[$field] = $parseNumber($request->input("coss_data.$detailId.$field"));
                    }
                }
            }

            // B. PROSES BPJS PERCENTAGE (HPP)
            foreach ($bpjsMap as $reqKey => $dbKey) {
                if ($request?->has("bpjs_persentase_data.$detailId.$reqKey")) {
                    $hppData[$dbKey] = $parseNumber($request->input("bpjs_persentase_data.$detailId.$reqKey"));
                }
            }

            // C. MERGE METADATA & SUMMARY
            $commonMetadata = [
                'quotation_detail_id' => $detailId,
                'persen_management_fee' => $persentase,
                'updated_by' => $user,
                'updated_at' => $currentDateTime,
            ];

            // HPP Final Prep
            $hppFull = array_merge($hppData, $commonMetadata, [
                'management_fee' => $summary->nominal_management_fee ?? 0,
                'grand_total' => $summary->grand_total_sebelum_pajak ?? 0,
                'ppn' => $summary->ppn ?? 0,
                'pph' => $summary->pph ?? 0,
                'total_invoice' => $summary->total_invoice ?? 0,
                'pembulatan' => $summary->pembulatan ?? 0,
                'is_pembulatan' => (($summary->pembulatan ?? 0) != ($summary->total_invoice ?? 0)) ? 1 : 0,
            ]);

            // COSS Final Prep
            $cossFull = array_merge($cossData, $commonMetadata, [
                'management_fee' => $summary->nominal_management_fee_coss ?? 0,
                'grand_total' => $summary->grand_total_sebelum_pajak_coss ?? 0,
                'ppn' => $summary->ppn_coss ?? 0,
                'pph' => $summary->pph_coss ?? 0,
                'total_invoice' => $summary->total_invoice_coss ?? 0,
                'pembulatan' => $summary->pembulatan_coss ?? 0,
                'is_pembulatan' => (($summary->pembulatan_coss ?? 0) != ($summary->total_invoice_coss ?? 0)) ? 1 : 0,
            ]);

            // Filter hanya kolom yang ada di fillable
            $hppFinalData[] = array_intersect_key($hppFull, $hppAllowed);
            $cossFinalData[] = array_intersect_key($cossFull, $cossAllowed);
        }

        // 3. EKSEKUSI DATABASE (Batch Update)
        // HPP/COSS records already exist from Step 3 — use updateOrCreate to
        // safely handle edge cases where a record might not exist yet.
        foreach ($hppFinalData as $data) {
            QuotationDetailHpp::updateOrCreate(
                ['quotation_detail_id' => $data['quotation_detail_id'] ?? 0],
                $data
            );
        }

        foreach ($cossFinalData as $data) {
            QuotationDetailCoss::updateOrCreate(
                ['quotation_detail_id' => $data['quotation_detail_id'] ?? 0],
                $data
            );
        }
    }

    /**
     * Prepare quotation data for update dari request Step 11
     */
    private function prepareQuotationDataForUpdate(Quotation $quotation, Request $request, string $user, Carbon $currentDateTime): array
    {
        $data = [
            'updated_by' => $user,
            'updated_at' => $currentDateTime,
        ];

        // Penagihan (wajib ada di Step 11)
        if ($request->has('penagihan')) {
            $data['penagihan'] = $request->penagihan;
        } else {
            $data['penagihan'] = $quotation->penagihan ?? 'Transfer';
        }

        // Persentase management fee - hanya update jika ada nilai baru
        if ($request->filled('persentase')) {
            $persentase = $request->persentase;
            if (is_string($persentase) && ! is_numeric($persentase)) {
                $persentase = (float) str_replace(['.', ','], ['', '.'], $persentase);
            }
            $data['persentase'] = $persentase;
        }

        // nama_perusahaan (opsional, bisa diedit di step 11)
        if ($request->filled('nama_perusahaan')) {
            $data['nama_perusahaan'] = $request->nama_perusahaan;
        }

        // Persen insentif (jika ada di request)
        if ($request->filled('persen_insentif')) {
            $persenInsentif = $request->persen_insentif;
            if (is_string($persenInsentif) && ! is_numeric($persenInsentif)) {
                $persenInsentif = (float) str_replace(['.', ','], ['', '.'], $persenInsentif);
            }
            $data['persen_insentif'] = $persenInsentif;
        }

        // Persen bunga bank (jika ada di request)
        if ($request->filled('persen_bunga_bank')) {
            $persenBungaBank = $request->persen_bunga_bank;
            if (is_string($persenBungaBank) && ! is_numeric($persenBungaBank)) {
                $persenBungaBank = (float) str_replace(['.', ','], ['', '.'], $persenBungaBank);
            }
            $data['persen_bunga_bank'] = $persenBungaBank;
        }

        // Note harga jual (opsional)
        if ($request->filled('note_harga_jual')) {
            $data['note_harga_jual'] = $request->note_harga_jual;
        }

        $optionalFields = ['is_ppn', 'ppn_pph_dipotong', 'management_fee_id'];
        foreach ($optionalFields as $field) {
            if ($request->filled($field)) {
                $data[$field] = $request->$field;
            }
        }

        // Pastikan hanya field yang ada di tabel yang dikembalikan
        $validFields = [
            'nama_perusahaan',
            'penagihan',
            'persentase',
            'persen_insentif',
            'persen_bunga_bank',
            'note_harga_jual',
            'is_ppn',
            'ppn_pph_dipotong',
            'management_fee_id',
            'updated_by',
            'updated_at',
        ];

        $data = array_intersect_key($data, array_flip($validFields));

        return $data;
    }

    /**
     * Clean non-database attributes from Quotation model
     */
    private function cleanQuotationAttributes(Quotation $quotation): void
    {
        $nonDatabaseAttributes = [
            'quotation_detail',
            'quotation_site',
            'management_fee',
            '_mf_config',
            'jumlah_hc',
            'provisi',
            'persen_bpjs_ketenagakerjaan',
            'persen_bpjs_kesehatan',
        ];

        foreach ($nonDatabaseAttributes as $attribute) {
            if (isset($quotation->$attribute)) {
                unset($quotation->$attribute);
            }
        }

        $quotation->unsetRelation('quotationDetails');
        $quotation->unsetRelation('quotationSites');
        $quotation->unsetRelation('wage');
    }

    private function parseNominal(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function isRo($detail): bool
    {
        return ($detail->position_id ?? null) === 224;
    }

    private function convertToFloat($value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        if (is_string($value) && ! is_numeric($value)) {
            return (float) str_replace(['.', ','], ['', '.'], $value);
        }

        return (float) $value;
    }

    private function toBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));

            return in_array($value, ['true', '1', 'yes', 'on']);
        }

        return (bool) $value;
    }
}
