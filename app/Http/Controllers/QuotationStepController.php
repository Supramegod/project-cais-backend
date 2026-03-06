<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AplikasiPendukung;
use App\Models\Barang;
use App\Models\BarangDefaultQty;
use App\Models\BidangPerusahaan;
use App\Models\JenisBarang;
use App\Models\JenisPerusahaan;
use App\Models\ManagementFee;
use App\Models\Position;
use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\QuotationDevices;
use App\Models\QuotationKaporlap;
use App\Models\SalaryRule;
use App\Models\Top;
use App\Models\Training;
use App\Models\Umk;
use App\Models\Ump;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\QuotationStepService;
use App\Services\QuotationBarangService;
use App\Services\QuotationService;
use App\Http\Requests\QuotationStepRequest;
use App\Http\Resources\QuotationStepResource;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="Quotations",
 *     description="API Endpoints for Quotation Management"
 * )
 *
 * Refactored: Seluruh logika GET (step_data + additional_data) diintegrasikan
 * ke controller. UPDATE tetap didelegasikan ke QuotationStepService tanpa
 * perubahan apapun.
 *
 * PERLU SATU PERUBAHAN KECIL DI QuotationStepResource::getStepSpecificData():
 * Tambahkan baris berikut di paling atas method tersebut (sebelum switch):
 *
 *   if (isset($this['step_data'])) {
 *       return $this['step_data'];
 *   }
 *
 * (Pola yang sama sudah ada di getAdditionalData() untuk $this['additional_data'])
 */
class QuotationStepController extends Controller
{
    protected QuotationStepService $quotationStepService;
    protected QuotationBarangService $quotationBarangService;
    protected QuotationService $quotationService;

    public function __construct(
        QuotationStepService $quotationStepService,
        QuotationBarangService $quotationBarangService,
        QuotationService $quotationService
    ) {
        $this->quotationStepService = $quotationStepService;
        $this->quotationBarangService = $quotationBarangService;
        $this->quotationService = $quotationService;
    }

    // =========================================================================
    // STEP REGISTRY — Relasi Eloquent per step
    // Menggantikan QuotationStepService::getStepRelations()
    // =========================================================================

    private const STEP_RELATIONS = [
        1 => ['kebutuhan'],
        2 => ['quotationSites'],
        3 => ['quotationDetails.quotationDetailRequirements', 'quotationDetails.quotationDetailTunjangans', 'quotationSites'],
        4 => ['quotationDetails.wage', 'quotationDetails.quotationSite', 'quotationSites'],
        5 => ['quotationDetails', 'jenisPerusahaan', 'leads.jenisperusahaan'],
        6 => ['quotationAplikasis'],
        7 => ['quotationDetails', 'quotationKaporlaps'],
        8 => ['quotationDetails', 'quotationDevices'],
        9 => ['quotationChemicals', 'quotationSites'],
        10 => ['quotationTrainings', 'quotationOhcs', 'quotationSites'],
        11 => [
            // Relasi detail level
            'quotationDetails.quotationDetailHpps',
            'quotationDetails.quotationDetailCosses',
            'quotationDetails.wage',
            'quotationDetails.quotationDetailTunjangans',
            'quotationDetails.quotationSite',
            // Relasi site level — dibutuhkan calculateQuotation untuk UMK/UMP
            'quotationSites',
            // Relasi barang — dibutuhkan calculateQuotation untuk provisi
            'quotationKaporlaps',
            'quotationDevices',
            'quotationChemicals',
            'quotationOhcs',
            // Relasi lainnya
            'quotationPics',
            'managementFee',
            'jenisPerusahaan',
        ],
        12 => ['quotationKerjasamas', 'quotationPics'],
    ];


    // =========================================================================
    // PUBLIC ENDPOINTS
    // =========================================================================

    /**
     * Get quotation data for specific step
     *
     * @OA\Get(
     *     path="/api/quotations-step/{id}/step/{step}",
     *    summary="Get quotation data for specific step",
     *     tags={"Quotations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="step", in="path", required=true, @OA\Schema(type="integer", minimum=1, maximum=12)),
     *     @OA\Response(
     *         response=200, description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string", example="Step data retrieved successfully"),
     *             @OA\Property(property="processing_time", type="string", example="42.30ms")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found",
     *         @OA\JsonContent(@OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Quotation not found"))
     *     ),
     *     @OA\Response(response=500, description="Server error",
     *         @OA\JsonContent(@OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to get step data"),
     *             @OA\Property(property="error", type="string"))
     *     )
     * )
     */
    public function getStep(string $id, int $step): JsonResponse
    {
        $startTime = microtime(true);

        try {
            set_time_limit(0);

            $relations = $this->resolveStepRelations($step);
            $quotation = Quotation::with($relations)->notDeleted()->findOrFail($id);

            $stepData = $this->prepareStepData($quotation, $step);

            return response()->json([
                'success' => true,
                'data' => new QuotationStepResource($stepData),
                'message' => 'Step data retrieved successfully',
                'processing_time' => $this->elapsedMs($startTime),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error("QuotationStepController@getStep: " . $e->getMessage(), [
                'id' => $id,
                'step' => $step,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get step data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update specific step
     *
     * @OA\Post(
     *     path="/api/quotations-step/{id}/step/{step}",
     *    summary="Update specific quotation step",
     *     tags={"Quotations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="step", in="path", required=true, @OA\Schema(type="integer", minimum=1, maximum=12)),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="edit", type="boolean", example=false)
     *     )),
     *     @OA\Response(response=200, description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string", example="Step 1 updated successfully"),
     *             @OA\Property(property="processing_time", type="string", example="85.10ms")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found",
     *         @OA\JsonContent(@OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Step method not found"))
     *     ),
     *     @OA\Response(response=422, description="Validation error",
     *         @OA\JsonContent(@OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object"))
     *     ),
     *     @OA\Response(response=500, description="Server error",
     *         @OA\JsonContent(@OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to update step 1"),
     *             @OA\Property(property="error", type="string"))
     *     )
     * )
     */
    public function updateStep(QuotationStepRequest $request, $id, $step): JsonResponse
    {
        $startTime = microtime(true);
        set_time_limit(0);

        $updateMethod = 'updateStep' . $step;

        if (!method_exists($this->quotationStepService, $updateMethod)) {
            return response()->json([
                'success' => false,
                'message' => 'Step method not found',
            ], 404);
        }

        DB::beginTransaction();

        try {
            $quotation = Quotation::notDeleted()->findOrFail($id);

            // Delegasi sepenuhnya ke service — logika update tidak diubah
            $this->quotationStepService->$updateMethod($quotation, $request);

            if ($quotation->step < 12) {
                $quotation->update([
                    'step' => max($quotation->step, $step + 1),
                    'updated_by' => Auth::user()->full_name,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new QuotationStepResource($quotation, $step),
                'message' => "Step {$step} updated successfully",
                'processing_time' => $this->elapsedMs($startTime),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("QuotationStepController@updateStep [{$step}]: " . $e->getMessage(), [
                'id' => $id,
                'step' => $step,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Failed to update step {$step}",
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // PRIVATE — RELATIONS RESOLVER
    // =========================================================================

    private function resolveStepRelations(int $step): array
    {
        return self::STEP_RELATIONS[$step] ?? [];
    }

    // =========================================================================
    // PRIVATE — STEP DATA PREPARER
    //
    // Menggantikan QuotationStepService::prepareStepData().
    // Format output identik — QuotationStepResource tidak perlu diubah strukturnya,
    // hanya perlu tambah shortcut di getStepSpecificData() (lihat komentar di atas).
    //
    // Urutan eksekusi:
    //   1. buildAdditionalDataStep{N} — siapkan master data / lookup
    //   2. buildStepDataStep{N}       — siapkan data utama step (bisa pakai additional_data)
    // =========================================================================

    private function prepareStepData(Quotation $quotation, int $step): array
    {
        // additional_data dibangun lebih dulu karena step_data step 11 & 12
        // membutuhkan calculated_quotation dari sana (menghindari double compute)
        $additionalDataMethod = 'buildAdditionalDataStep' . $step;
        $additionalData = method_exists($this, $additionalDataMethod)
            ? $this->$additionalDataMethod($quotation)
            : [];

        $stepDataMethod = 'buildStepDataStep' . $step;
        $stepData = method_exists($this, $stepDataMethod)
            ? $this->$stepDataMethod($quotation, $additionalData)
            : [];

        return [
            'quotation' => $quotation,
            'step' => $step,
            'step_data' => $stepData,       // dibaca via $this['step_data'] di Resource
            'additional_data' => $additionalData, // dibaca via $this['additional_data'] di Resource
            'metadata' => [
                'actual_step' => $quotation->step,
                'is_final' => $quotation->step >= 100,
                'readonly' => $quotation->step >= 100,
            ],
        ];
    }

    // =========================================================================
    // PRIVATE — STEP DATA BUILDERS  (buildStepDataStep{N})
    //
    // Tanggung jawab : membangun isi `step_data` yang dikembalikan Resource.
    //                  Memindahkan logika dari Resource::getStepSpecificData().
    // Signature      : (Quotation $quotation, array $additionalData): array
    //                  $additionalData tersedia untuk step yang membutuhkan
    //                  data kalkulasi dari buildAdditionalDataStep{N}.
    // =========================================================================

    /**
     * Step 1 — Jenis Kontrak
     * Estimasi: < 20ms
     */
    private function buildStepDataStep1(Quotation $quotation, array $additionalData): array
    {
        return [
            'jenis_kontrak' => $quotation->jenis_kontrak,
            'layanan_id' => $quotation->kebutuhan_id,
            'layanan_nama' => $quotation->kebutuhan->nama ?? null,
        ];
    }

    /**
     * Step 2 — Detail Kontrak
     * Estimasi: < 20ms (semua dari kolom quotation)
     */
    private function buildStepDataStep2(Quotation $quotation, array $additionalData): array
    {
        return [
            'jenis_kontrak' => $quotation->jenis_kontrak,
            'mulai_kontrak' => $quotation->mulai_kontrak,
            'kontrak_selesai' => $quotation->kontrak_selesai,
            'tgl_penempatan' => $quotation->tgl_penempatan
                ? Carbon::parse($quotation->tgl_penempatan)->isoFormat('Y-MM-DD')
                : null,
            'top' => $quotation->top,
            'salary_rule_id' => $quotation->salary_rule_id,
            'pengiriman_invoice' => $quotation->pengiriman_invoice,
            'jumlah_hari_invoice' => $quotation->jumlah_hari_invoice,
            'tipe_hari_invoice' => $quotation->tipe_hari_invoice,
            'evaluasi_kontrak' => $quotation->evaluasi_kontrak,
            'durasi_kerjasama' => $quotation->durasi_kerjasama,
            'durasi_karyawan' => $quotation->durasi_karyawan,
            'evaluasi_karyawan' => $quotation->evaluasi_karyawan,
            'ada_cuti' => $quotation->ada_cuti,
            'cuti' => $quotation->cuti,
            'hari_cuti_kematian' => $quotation->hari_cuti_kematian,
            'hari_istri_melahirkan' => $quotation->hari_istri_melahirkan,
            'hari_cuti_menikah' => $quotation->hari_cuti_menikah,
            'gaji_saat_cuti' => $quotation->gaji_saat_cuti,
            'prorate' => $quotation->prorate,
            'shift_kerja' => $quotation->shift_kerja,
            'hari_kerja' => $quotation->hari_kerja,
            'jam_kerja' => $quotation->jam_kerja,
        ];
    }

    /**
     * Step 3 — Detail Posisi per Site (requirements + tunjangan)
     * Estimasi: 30–80ms
     */
    private function buildStepDataStep3(Quotation $quotation, array $additionalData): array
    {
        $quotationDetails = [];

        if ($quotation->relationLoaded('quotationDetails')) {
            $quotationDetails = $quotation->quotationDetails->map(function ($detail) {
                // Requirements
                // $requirements = [];
                // if ($detail->relationLoaded('quotationDetailRequirements')) {
                //     $requirements = $detail->quotationDetailRequirements->pluck('requirement')->toArray();
                // } else {
                //     try {
                //         $requirements = $detail->quotationDetailRequirements()->pluck('requirement')->toArray();
                //     } catch (\Exception $e) {
                //         $requirements = [];
                //     }
                // }

                // // Tunjangan
                // $tunjangans = [];
                // if ($detail->relationLoaded('quotationDetailTunjangans')) {
                //     $tunjangans = $detail->quotationDetailTunjangans->map(fn($t) => [
                //         'nama_tunjangan' => $t->nama_tunjangan,
                //         'nominal' => $t->nominal,
                //     ])->toArray();
                // } else {
                //     try {
                //         $tunjangans = $detail->quotationDetailTunjangans()->get()->map(fn($t) => [
                //             'nama_tunjangan' => $t->nama_tunjangan,
                //             'nominal' => $t->nominal,
                //         ])->toArray();
                //     } catch (\Exception $e) {
                //         $tunjangans = [];
                //     }
                // }

                return [
                    'id' => $detail->id,
                    'nama_site' => $detail->nama_site,
                    'quotation_site_id' => $detail->quotation_site_id,
                    'position_id' => $detail->position_id,
                    'jabatan_kebutuhan' => $detail->jabatan_kebutuhan,
                    'jumlah_hc' => $detail->jumlah_hc,
                    'nominal_upah' => $detail->nominal_upah,
                    // 'requirements' => $requirements,
                    // 'tunjangans' => $tunjangans,
                ];
            })->toArray();
        }

        return [
            'quotation_details' => $quotationDetails,
        ];
    }

    /**
     * Step 4 — Data Upah per Posisi (wage + keterangan UMK)
     * Estimasi: 50–150ms (query UMK per detail)
     */
    private function buildStepDataStep4(Quotation $quotation, array $additionalData): array
    {
        $positionData = [];

        if ($quotation->relationLoaded('quotationDetails')) {
            foreach ($quotation->quotationDetails as $detail) {
                $wage = $detail->wage;
                $site = $detail->quotationSite;

                $keteranganMinUpah = 'Data UMK tidak ditemukan';

                if ($site && $site->kota_id) {
                    $umkData = Umk::byCity($site->kota_id)->active()->first();

                    if ($umkData) {
                        $minUpahNominal = $umkData->umk * 0.85;
                        $keteranganMinUpah = 'Upah kurang dari 85% UMK ( Rp '
                            . number_format($minUpahNominal, 0, ',', '.')
                            . ' ) membutuhkan approval ';
                    }
                }

                $positionData[] = [
                    'quotation_detail_id' => $detail->id,
                    'position_id' => $detail->position_id,
                    'position_name' => $detail->jabatan_kebutuhan,
                    'site_id' => $detail->quotation_site_id,
                    'site_name' => $detail->nama_site,
                    'jumlah_hc' => $detail->jumlah_hc,
                    'nominal_upah' => $detail->nominal_upah,
                    'keterangan_minimal_upah' => $keteranganMinUpah,
                    'upah' => $wage->upah ?? null,
                    'hitungan_upah' => $wage->hitungan_upah ?? null,
                    'lembur' => $wage->lembur ?? null,
                    'nominal_lembur' => $wage->nominal_lembur ?? 0,
                    'jenis_bayar_lembur' => $wage->jenis_bayar_lembur ?? null,
                    'jam_per_bulan_lembur' => $wage->jam_per_bulan_lembur ?? 0,
                    'lembur_ditagihkan' => $wage->lembur_ditagihkan ?? null,
                    'kompensasi' => $wage->kompensasi ?? null,
                    'thr' => $wage->thr ?? null,
                    'tunjangan_holiday' => $wage->tunjangan_holiday ?? null,
                    'nominal_tunjangan_holiday' => $wage->nominal_tunjangan_holiday ?? 0,
                    'jenis_bayar_tunjangan_holiday' => $wage->jenis_bayar_tunjangan_holiday ?? null,
                    'is_bpjs_jkk' => $detail->is_bpjs_jkk ?? null,
                    'is_bpjs_jkm' => $detail->is_bpjs_jkm ?? null,
                    'is_bpjs_jht' => $detail->is_bpjs_jht ?? null,
                    'is_bpjs_jp' => $detail->is_bpjs_jp ?? null,
                    'penjamin_kesehatan' => $detail->penjamin_kesehatan ?? null,
                ];
            }
        }

        return [
            'position_data' => $positionData,
            'global_data' => [
                'is_ppn' => $quotation->is_ppn,
                'ppn_pph_dipotong' => $quotation->ppn_pph_dipotong,
                'management_fee_id' => $quotation->management_fee_id,
                'persentase' => $quotation->persentase,
            ],
        ];
    }

    /**
     * Step 5 — BPJS per Posisi + Data Perusahaan
     * Estimasi: 20–50ms (semua dari relasi yang sudah dimuat)
     */
    private function buildStepDataStep5(Quotation $quotation, array $additionalData): array
    {
        $bpjsPerPosition = [];

        if ($quotation->relationLoaded('quotationDetails')) {
            $bpjsPerPosition = $quotation->quotationDetails->map(fn($detail) => [
                'detail_id' => $detail->id,
                'position_id' => $detail->position_id,
                'position_name' => $detail->jabatan_kebutuhan,
                'site_id' => $detail->quotation_site_id,
                'site_name' => $detail->nama_site,
                'penjamin_kesehatan' => $detail->penjamin_kesehatan,
                'is_bpjs_jkk' => (bool) $detail->is_bpjs_jkk,
                'is_bpjs_jkm' => (bool) $detail->is_bpjs_jkm,
                'is_bpjs_jht' => (bool) $detail->is_bpjs_jht,
                'is_bpjs_jp' => (bool) $detail->is_bpjs_jp,
                'nominal_takaful' => $detail->nominal_takaful,
            ])->toArray();
        }

        return [
            'jenis_perusahaan_id' => $quotation->jenis_perusahaan_id ?? $quotation->leads->jenis_perusahaan_id,
            'bidang_perusahaan_id' => $quotation->bidang_perusahaan_id ?? $quotation->leads->bidang_perusahaan_id,
            'resiko' => $quotation->jenisPerusahaan->resiko
                ?? $quotation->leads->jenisperusahaan->resiko
                ?? null,
            'program_bpjs' => $quotation->program_bpjs,
            'bpjs_per_position' => $bpjsPerPosition,
        ];
    }

    /**
     * Step 6 — Aplikasi Pendukung (yang sudah dipilih)
     * Estimasi: < 20ms
     */
    private function buildStepDataStep6(Quotation $quotation, array $additionalData): array
    {
        return [
            'aplikasi_pendukung' => $quotation->relationLoaded('quotationAplikasis')
                ? $quotation->quotationAplikasis->pluck('aplikasi_pendukung_id')->toArray()
                : [],
        ];
    }

    /**
     * Step 7 — Kaporlap (data aktual quotation)
     * Estimasi: 50–120ms
     */
    private function buildStepDataStep7(Quotation $quotation, array $additionalData): array
    {
        $kaporlapData = $this->quotationBarangService->prepareBarangData($quotation, 'kaporlap');

        return [
            'quotation_kaporlaps' => $kaporlapData['data'],
            'kaporlap_total' => $kaporlapData['total'],
        ];
    }

    /**
     * Step 8 — Devices (data aktual quotation)
     * Estimasi: 50–120ms
     */
    private function buildStepDataStep8(Quotation $quotation, array $additionalData): array
    {
        $devicesData = $this->quotationBarangService->prepareBarangData($quotation, 'devices');

        return [
            'quotation_devices' => $devicesData['data'],
            'devices_total' => $devicesData['total'],
        ];
    }

    /**
     * Step 9 — Chemicals (data aktual quotation)
     * Estimasi: 30–80ms
     */
    private function buildStepDataStep9(Quotation $quotation, array $additionalData): array
    {
        $chemicalData = $this->quotationBarangService->prepareBarangData($quotation, 'chemicals');

        return [
            'quotation_chemicals' => $chemicalData['data'],
            'chemicals_total' => $chemicalData['total'],
        ];
    }

    /**
     * Step 10 — OHC, Training, Kunjungan
     * Estimasi: 30–80ms
     */
    private function buildStepDataStep10(Quotation $quotation, array $additionalData): array
    {
        // Parse kunjungan_operasional: "jumlah periode"
        [$jumlahOps, $periodeOps] = $this->parseKunjungan($quotation->kunjungan_operasional ?? '');

        // Parse kunjungan_tim_crm: "jumlah periode"
        [$jumlahCrm, $periodeCrm] = $this->parseKunjungan($quotation->kunjungan_tim_crm ?? '');

        $quotationTrainings = $quotation->relationLoaded('quotationTrainings')
            ? $quotation->quotationTrainings->pluck('training_id')->toArray()
            : [];

        $ohcData = $this->quotationBarangService->prepareBarangData($quotation, 'ohc');

        return [
            'jumlah_kunjungan_operasional' => $jumlahOps,
            'bulan_tahun_kunjungan_operasional' => $periodeOps,
            'keterangan_kunjungan_operasional' => $quotation->keterangan_kunjungan_operasional,
            'jumlah_kunjungan_tim_crm' => $jumlahCrm,
            'bulan_tahun_kunjungan_tim_crm' => $periodeCrm,
            'keterangan_kunjungan_tim_crm' => $quotation->keterangan_kunjungan_tim_crm,
            'ada_training' => !empty($quotationTrainings) ? 'Ada' : 'Tidak Ada',
            'training' => $quotation->training,
            'persen_bunga_bank' => $quotation->persen_bunga_bank,
            'quotation_ohcs' => $ohcData['data'],
            'ohc_total' => $ohcData['total'],
            'quotation_trainings' => $quotationTrainings,
        ];
    }

    /**
     * Step 11 — Review & Kalkulasi Akhir (HPP + COSS)
     * Estimasi: 500ms–3000ms ⚠️ Step terberat
     *
     * $additionalData['calculated_quotation'] sudah disiapkan oleh
     * buildAdditionalDataStep11 — tidak ada double compute di sini.
     */
    private function buildStepDataStep11(Quotation $quotation, array $additionalData): array
    {
        try {
            $calculatedQuotation = $additionalData['calculated_quotation'] ?? null;
            Log::info('Calculated quotation', ['quotation_id' => $quotation->id, 'calculated' => !!$calculatedQuotation]);

            if (!$calculatedQuotation) {
                Log::error('calculateQuotation returned null', ['quotation_id' => $quotation->id]);
            }
        } catch (\Exception $e) {
            Log::error('Error calculating quotation in step 11', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $calculatedQuotation = null;
        }

        // Hitung persentase BPJS dari calculation_summary
        $persenBpjsTotalHpp = 0;
        $persenBpjsTotalCoss = 0;
        $persenBpjsBreakdownHpp = [];
        $persenBpjsBreakdownCoss = [];

        if ($calculatedQuotation && isset($summary)) {
            $summary = $calculatedQuotation->calculation_summary;

            $persenBpjsTotalHpp = round($summary->persen_bpjs_ketenagakerjaan ?? 0, 2);
            $persenBpjsBreakdownHpp = [
                'persen_bpjs_jkk' => round($summary->persen_bpjs_jkk ?? 0, 2),
                'persen_bpjs_jkm' => round($summary->persen_bpjs_jkm ?? 0, 2),
                'persen_bpjs_jht' => round($summary->persen_bpjs_jht ?? 0, 2),
                'persen_bpjs_jp' => round($summary->persen_bpjs_jp ?? 0, 2),
            ];

            $persenBpjsTotalCoss = round($summary->persen_bpjs_ketenagakerjaan_coss ?? 0, 2);
            $persenBpjsBreakdownCoss = [
                'persen_bpjs_jkk' => round($summary->persen_bpjs_jkk_coss ?? 0, 2),
                'persen_bpjs_jkm' => round($summary->persen_bpjs_jkm_coss ?? 0, 2),
                'persen_bpjs_jht' => round($summary->persen_bpjs_jht_coss ?? 0, 2),
                'persen_bpjs_jp' => round($summary->persen_bpjs_jp_coss ?? 0, 2),
            ];
        }

        // Pastikan relasi detail termuat untuk kalkulasi
        if ($calculatedQuotation && $calculatedQuotation->quotation) {
            $calculatedQuotation->quotation->quotationDetails->loadMissing([
                'quotationDetailHpps',
                'quotationDetailCosses',
                'wage',
                'quotationDetailTunjangans' => fn($q) => $q->whereNull('deleted_at'),
            ]);
        }

        return [
            'penagihan' => $quotation->penagihan,
            'nama_perusahaan' => $quotation->nama_perusahaan,
            'persentase' => $quotation->persentase,
            'management_fee_nama' => $quotation->managementFee->nama ?? null,
            'ppn_pph_dipotong' => $quotation->ppn_pph_dipotong,
            'note_harga_jual' => $quotation->note_harga_jual,
            'persen_bunga_bank' => $quotation->persen_bunga_bank ?? 0,
            'persen_insentif' => $quotation->persen_insentif ?? 0,
            'quotation_pics' => $quotation->relationLoaded('quotationPics')
                ? $quotation->quotationPics->map(fn($pic) => [
                    'id' => $pic->id,
                    'nama' => $pic->nama,
                    'jabatan_id' => $pic->jabatan_id,
                    'no_telp' => $pic->no_telp,
                    'email' => $pic->email,
                    'is_kuasa' => $pic->is_kuasa,
                ])->values()->toArray()
                : [],
            'calculation' => $calculatedQuotation ? [
                'bpu' => [
                    'total_potongan_bpu' => $summary->total_potongan_bpu ?? 0,
                    'potongan_bpu_per_orang' => $summary->potongan_bpu_per_orang ?? 0,
                ],
                'hpp' => [
                    'total_sebelum_management_fee' => $summary->total_sebelum_management_fee ?? 0,
                    'nominal_management_fee' => $summary->nominal_management_fee ?? 0,
                    'grand_total_sebelum_pajak' => $summary->grand_total_sebelum_pajak ?? 0,
                    'ppn' => $summary->ppn ?? 0,
                    'pph' => $summary->pph ?? 0,
                    'dpp' => $summary->dpp ?? 0,
                    'total_invoice' => $summary->total_invoice ?? 0,
                    'pembulatan' => $summary->pembulatan ?? 0,
                    'margin' => $summary->margin ?? 0,
                    'gpm' => $summary->gpm ?? 0,
                    'persen_bunga_bank' => $quotation->persen_bunga_bank ?? 0,
                    'persen_insentif' => $quotation->persen_insentif ?? 0,
                    'persen_bpjs_ksht' => $summary->persen_bpjs_kesehatan ?? 0,
                    'persen_bpjs_ketenagakerjaan' => $persenBpjsTotalHpp,
                    'breakdown_bpjs' => $persenBpjsBreakdownHpp,
                ],
                'coss' => [
                    'total_sebelum_management_fee_coss' => $summary->total_sebelum_management_fee_coss ?? 0,
                    'nominal_management_fee_coss' => $summary->nominal_management_fee_coss ?? 0,
                    'grand_total_sebelum_pajak_coss' => $summary->grand_total_sebelum_pajak_coss ?? 0,
                    'ppn_coss' => $summary->ppn_coss ?? 0,
                    'pph_coss' => $summary->pph_coss ?? 0,
                    'dpp_coss' => $summary->dpp_coss ?? 0,
                    'total_invoice_coss' => $summary->total_invoice_coss ?? 0,
                    'pembulatan_coss' => $summary->pembulatan_coss ?? 0,
                    'margin_coss' => $summary->margin_coss ?? 0,
                    'gpm_coss' => $summary->gpm_coss ?? 0,
                    'persen_bunga_bank' => $quotation->persen_bunga_bank ?? 0,
                    'persen_insentif' => $quotation->persen_insentif ?? 0,
                    'persen_bpjs_ksht' => $summary->persen_bpjs_kesehatan_coss ?? 0,
                    'persen_bpjs_ketenagakerjaan' => $persenBpjsTotalCoss,
                    'breakdown_bpjs' => $persenBpjsBreakdownCoss,
                ],
                'quotation_details' => $calculatedQuotation->quotation
                    ? $calculatedQuotation->quotation->quotationDetails->map(
                        function ($detail) use ($calculatedQuotation) {
                            $wage = $detail->wage ?? null;
                            $detailCalc = $calculatedQuotation->detail_calculations[$detail->id] ?? null;

                            if ($detailCalc) {
                                $hppData = $detailCalc->hpp_data ?? [];
                                $cossData = $detailCalc->coss_data ?? [];
                            } else {
                                $hpp = $detail->quotationDetailHpps->first();
                                $coss = $detail->quotationDetailCosses->first();
                                $hppData = $hpp ? $hpp->toArray() : [];
                                $cossData = $coss ? $coss->toArray() : [];
                            }

                            $tunjanganData = $detail->quotationDetailTunjangans->map(fn($t) => [
                                'nama_tunjangan' => $t->nama_tunjangan,
                                'nominal' => $t->nominal,
                                'nominal_coss' => $t->nominal_coss,
                            ])->values()->toArray();

                            $thrDisplay = $this->resolveTunjanganDisplay($wage, 'thr', $hppData['tunjangan_hari_raya'] ?? 0, $cossData['tunjangan_hari_raya'] ?? 0);
                            $kompDisplay = $this->resolveTunjanganDisplay($wage, 'kompensasi', $hppData['kompensasi'] ?? 0, $cossData['kompensasi'] ?? 0);
                            $lemburDisplay = $this->resolveTunjanganDisplay($wage, 'lembur', $hppData['lembur'] ?? 0, $cossData['lembur'] ?? 0, 'lembur_ditagihkan');
                            $holidayDisplay = $this->resolveTunjanganDisplay($wage, 'tunjangan_holiday', $hppData['tunjangan_hari_libur_nasional'] ?? 0, $cossData['tunjangan_hari_libur_nasional'] ?? 0);

                            return [
                                'id' => $detail->id,
                                'position_name' => $detail->jabatan_kebutuhan,
                                'nama_site' => $detail->nama_site,
                                'quotation_site_id' => $detail->quotation_site_id,
                                'penjamin_kesehatan' => $detail->penjamin_kesehatan,
                                'upah' => $wage?->upah ?? 0,
                                'jumlah_hc_hpp' => $hppData['jumlah_hc'] ?? 0,
                                'jumlah_hc_coss' => $cossData['jumlah_hc'] ?? 0,
                                'tunjangan_data' => $tunjanganData,
                                'hpp' => [
                                    'nominal_upah' => $hppData['gaji_pokok'] ?? 0,
                                    'total_tunjangan' => $hppData['total_tunjangan'] ?? 0,
                                    'tunjangan_hari_raya' => $thrDisplay['hpp'],
                                    'kompensasi' => $kompDisplay['hpp'],
                                    'lembur' => $lemburDisplay['hpp'],
                                    'tunjangan_holiday' => $holidayDisplay['hpp'],
                                    'bpjs_ketenagakerjaan' => ($hppData['bpjs_jkk'] ?? 0) + ($hppData['bpjs_jkm'] ?? 0) + ($hppData['bpjs_jht'] ?? 0) + ($hppData['bpjs_jp'] ?? 0),
                                    'bpjs_kesehatan' => $hppData['bpjs_ks'] ?? 0,
                                    'bpjs_jkk' => $hppData['bpjs_jkk'] ?? 0,
                                    'bpjs_jkm' => $hppData['bpjs_jkm'] ?? 0,
                                    'bpjs_jht' => $hppData['bpjs_jht'] ?? 0,
                                    'bpjs_jp' => $hppData['bpjs_jp'] ?? 0,
                                    'bpjs_kes' => $hppData['bpjs_ks'] ?? 0,
                                    'persen_bpjs_jkk' => $hppData['persen_bpjs_jkk'] ?? 0,
                                    'persen_bpjs_jkm' => $hppData['persen_bpjs_jkm'] ?? 0,
                                    'persen_bpjs_jht' => $hppData['persen_bpjs_jht'] ?? 0,
                                    'persen_bpjs_jp' => $hppData['persen_bpjs_jp'] ?? 0,
                                    'persen_bpjs_kes' => $hppData['persen_bpjs_ks'] ?? 0,
                                    'potongan_bpu' => $hppData['potongan_bpu'] ?? 0,
                                    'personil_kaporlap' => $hppData['provisi_seragam'] ?? 0,
                                    'personil_devices' => $hppData['provisi_peralatan'] ?? 0,
                                    'personil_ohc' => $hppData['provisi_ohc'] ?? 0,
                                    'personil_chemical' => $hppData['provisi_chemical'] ?? 0,
                                    'total_personil' => $hppData['total_biaya_per_personil'] ?? 0,
                                    'sub_total_personil' => $hppData['total_biaya_all_personil'] ?? 0,
                                    'bunga_bank' => $hppData['bunga_bank'] ?? 0,
                                    'insentif' => $hppData['insentif'] ?? 0,
                                ],
                                'coss' => [
                                    'nominal_upah' => $cossData['gaji_pokok'] ?? 0,
                                    'total_tunjangan' => $cossData['total_tunjangan'] ?? 0,
                                    'tunjangan_hari_raya' => $thrDisplay['coss'],
                                    'kompensasi' => $kompDisplay['coss'],
                                    'lembur' => $lemburDisplay['coss'],
                                    'tunjangan_holiday' => $holidayDisplay['coss'],
                                    'bpjs_ketenagakerjaan' => ($cossData['bpjs_jkk'] ?? 0) + ($cossData['bpjs_jkm'] ?? 0) + ($cossData['bpjs_jht'] ?? 0) + ($cossData['bpjs_jp'] ?? 0),
                                    'bpjs_kesehatan' => $cossData['bpjs_ks'] ?? 0,
                                    'bpjs_jkk' => $cossData['bpjs_jkk'] ?? 0,
                                    'bpjs_jkm' => $cossData['bpjs_jkm'] ?? 0,
                                    'bpjs_jht' => $cossData['bpjs_jht'] ?? 0,
                                    'bpjs_jp' => $cossData['bpjs_jp'] ?? 0,
                                    'bpjs_kes' => $cossData['bpjs_ks'] ?? 0,
                                    'persen_bpjs_jkk' => $cossData['persen_bpjs_jkk'] ?? 0,
                                    'persen_bpjs_jkm' => $cossData['persen_bpjs_jkm'] ?? 0,
                                    'persen_bpjs_jht' => $cossData['persen_bpjs_jht'] ?? 0,
                                    'persen_bpjs_jp' => $cossData['persen_bpjs_jp'] ?? 0,
                                    'persen_bpjs_kes' => $cossData['persen_bpjs_ks'] ?? 0,
                                    'potongan_bpu' => $cossData['potongan_bpu'] ?? 0,
                                    'personil_kaporlap_coss' => $cossData['provisi_seragam'] ?? 0,
                                    'personil_devices_coss' => $cossData['provisi_peralatan'] ?? 0,
                                    'personil_ohc_coss' => $cossData['provisi_ohc'] ?? 0,
                                    'personil_chemical_coss' => $cossData['provisi_chemical'] ?? 0,
                                    'total_personil' => $cossData['total_personil_coss'] ?? 0,
                                    'sub_total_personil' => $cossData['sub_total_personil_coss'] ?? 0,
                                    'total_base_manpower' => $cossData['total_base_manpower'] ?? 0,
                                    'total_exclude_base_manpower' => $cossData['total_exclude_base_manpower'] ?? 0,
                                    'bunga_bank' => $cossData['bunga_bank'] ?? 0,
                                    'insentif' => $cossData['insentif'] ?? 0,
                                ],
                            ];
                        }
                    )->toArray()
                    : null,
            ] : null,
        ];
    }

    /**
     * Step 12 — Finalisasi & Kerjasama
     * Estimasi: 100–500ms
     *
     * $additionalData['calculated_quotation'] dari buildAdditionalDataStep12.
     */
    private function buildStepDataStep12(Quotation $quotation, array $additionalData): array
    {
        $calculatedQuotation = $additionalData['calculated_quotation'] ?? null;

        $kerjasamas = $quotation->relationLoaded('quotationKerjasamas')
            ? $quotation->quotationKerjasamas
                ->whereNull('deleted_at')
                ->sortBy('id')
                ->values()
                ->map(fn($kerjasama, $index) => [
                    'id' => $kerjasama->id,
                    'order' => $index + 1,
                    'perjanjian' => $kerjasama->perjanjian,
                    'is_delete' => $kerjasama->is_delete ?? 1,
                    'is_editable' => $kerjasama->is_delete == 1,
                ])->toArray()
            : [];

        $finalData = [
            'quotation_kerjasamas' => $kerjasamas,
            'total_kerjasamas' => count($kerjasamas),
            'can_edit' => $quotation->step < 100,
            'final_confirmation' => true,
        ];

        if ($calculatedQuotation) {
            $finalData['final_calculation'] = [
                'total_invoice' => $summary->total_invoice ?? 0,
                'total_invoice_coss' => $summary->total_invoice_coss ?? 0,
                'pembulatan' => $summary->pembulatan ?? 0,
                'pembulatan_coss' => $summary->pembulatan_coss ?? 0,
                'grand_total_sebelum_pajak' => $summary->grand_total_sebelum_pajak ?? 0,
                'grand_total_sebelum_pajak_coss' => $summary->grand_total_sebelum_pajak_coss ?? 0,
                'margin' => $summary->margin ?? 0,
                'margin_coss' => $summary->margin_coss ?? 0,
                'gpm' => $summary->gpm ?? 0,
                'gpm_coss' => $summary->gpm_coss ?? 0,
            ];
        }

        return $finalData;
    }

    // =========================================================================
    // PRIVATE — ADDITIONAL DATA BUILDERS  (buildAdditionalDataStep{N})
    //
    // Tanggung jawab : menyiapkan master data, lookup, dan opsi dropdown.
    //                  Memindahkan logika dari Resource::getAdditionalData().
    // =========================================================================

    /** Step 1 — tidak ada additional_data */
    private function buildAdditionalDataStep1(Quotation $quotation): array
    {
        return [];
    }

    /**
     * Step 2 — Salary rules (filter role), TOP list, pengiriman invoice
     * Estimasi: 20–50ms
     */
    private function buildAdditionalDataStep2(Quotation $quotation): array
    {
        $roleId = Auth::user()->cais_role_id;
        $salaryRules = in_array($roleId, [29, 30, 31, 32, 33])
            ? SalaryRule::whereIn('id', [1, 2])->get()
            : SalaryRule::all();

        return [
            'salary_rules' => $salaryRules,
            'top_list' => Top::orderBy('nama', 'asc')->get(),
            'pengiriman_invoice' => Quotation::distinct()->pluck('pengiriman_invoice'),
        ];
    }

    /**
     * Step 3 — Daftar posisi aktif + quotation sites
     * Estimasi: 30–80ms
     */
    private function buildAdditionalDataStep3(Quotation $quotation): array
    {
        return [
            'positions' => Position::where('is_active', 1)
                ->where('layanan_id', $quotation->kebutuhan_id)
                ->orderBy('name', 'asc')
                ->select('id', 'name')
                ->get(),
            'quotation_sites' => $quotation->relationLoaded('quotationSites')
                ? $quotation->quotationSites->map(fn($site) => [
                    'id' => $site->id,
                    'nama_site' => $site->nama_site,
                ])->toArray()
                : [],
        ];
    }

    /**
     * Step 4 — UMK/UMP per site, management fees, opsi dropdown
     * Estimasi: 80–200ms (query UMK + UMP per site)
     */
    private function buildAdditionalDataStep4(Quotation $quotation): array
    {
        $umkPerSite = [];
        $umpPerSite = [];

        if ($quotation->relationLoaded('quotationSites')) {
            foreach ($quotation->quotationSites as $site) {
                $umk = Umk::byCity($site->kota_id)->active()->first();
                $ump = Ump::byProvince($site->provinsi_id)->active()->first();

                $umkPerSite[$site->id] = [
                    'site_id' => $site->id,
                    'site_name' => $site->nama_site,
                    'city_id' => $site->kota_id,
                    'city_name' => $site->kota,
                    'umk_value' => $umk?->umk ?? 0,
                    'formatted_umk' => $umk ? $umk->formatUmk() : 'UMK : Rp. 0',
                ];

                $umpPerSite[$site->id] = [
                    'site_id' => $site->id,
                    'site_name' => $site->nama_site,
                    'province_id' => $site->provinsi_id,
                    'province_name' => $site->provinsi,
                    'ump_value' => $ump?->ump ?? 0,
                    'formatted_ump' => $ump ? $ump->formatUmp() : 'UMP : Rp. 0',
                ];
            }
        }
                // Hitung jumlah HC per site
        // 1. Pastikan relasi loaded, jika tidak, muat secara manual
        if (!$quotation->relationLoaded('quotationSites')) {
            $quotation->load([
                'quotationSites' => function ($query) {
                    $query->whereNull('deleted_at'); 
                }
            ]);
        }

        // 2. Ambil data HC per site
        $hcPerSite = QuotationDetail::where('quotation_id', $quotation->id)
            ->selectRaw('quotation_site_id, SUM(jumlah_hc) as total_hc')
            ->groupBy('quotation_site_id')
            ->pluck('total_hc', 'quotation_site_id')
            ->toArray();

        return [
            'management_fees' => ManagementFee::select('id', 'nama')->get(),
            'umk_per_site' => $umkPerSite,
            'ump_per_site' => $umpPerSite,
          'quotation_sites' => $quotation->relationLoaded('quotationSites')
                ? $quotation->quotationSites->map(fn($site) => [
                    'id' => $site->id,
                    'nama_site' => $site->nama_site,
                ])->toArray()
                : [],
        ];
    }

    /**
     * Step 5 — Master jenis & bidang perusahaan, opsi BPJS
     * Estimasi: 20–50ms
     */
    private function buildAdditionalDataStep5(Quotation $quotation): array
    {
        return [
            'jenis_perusahaan_list' => JenisPerusahaan::select('id', 'nama', 'resiko')->get(),
            'bidang_perusahaan_list' => BidangPerusahaan::select('id', 'nama')->get(),

        ];
    }

    /**
     * Step 6 — Master semua aplikasi pendukung
     * Estimasi: < 20ms
     */
    private function buildAdditionalDataStep6(Quotation $quotation): array
    {
        return [
            'aplikasi_pendukung_list' => AplikasiPendukung::select('id', 'nama', 'harga', 'link_icon')->get(),
        ];
    }

    /**
     * Step 7 — Master kaporlap: jenis_barang, kaporlap_items, quotation_details
     * Mereplikasi Resource::getKaporlapData() persis.
     * N+1 diperbaiki: BarangDefaultQty & QuotationKaporlap di-preload dengan whereIn.
     * Estimasi: 30–80ms
     */
    private function buildAdditionalDataStep7(Quotation $quotation): array
    {
        $arrKaporlap = $quotation->kebutuhan_id != 1 ? [5] : [1, 2, 3, 4, 5];
        // Menggunakan select() untuk mengambil kolom tertentu
        $listJenis = JenisBarang::whereIn('id', $arrKaporlap)
            ->select('id', 'nama')
            ->get();

        $listKaporlap = Barang::whereIn('jenis_barang_id', $arrKaporlap)
            ->select('id', 'nama', 'harga', 'jenis_barang_id')
            ->ordered()
            ->get();
        $barangIds = $listKaporlap->pluck('id')->toArray();
        $detailIds = $quotation->relationLoaded('quotationDetails')
            ? $quotation->quotationDetails->pluck('id')->toArray()
            : [];

        if ($quotation->revisi == 0) {
            // Preload semua qty default dalam 1 query
            $defaultQtyMap = BarangDefaultQty::where('layanan_id', $quotation->kebutuhan_id)
                ->whereIn('barang_id', $barangIds)
                ->get()
                ->keyBy('barang_id');

            foreach ($listKaporlap as $kaporlap) {
                foreach ($quotation->quotationDetails as $detail) {
                    $fieldName = 'jumlah_' . $detail->id;
                    $kaporlap->$fieldName = $defaultQtyMap->has($kaporlap->id)
                        ? $defaultQtyMap[$kaporlap->id]->qty_default
                        : 0;
                }
            }
        } else {
            // Preload semua existing kaporlap dalam 1 query
            $existingMap = QuotationKaporlap::whereIn('barang_id', $barangIds)
                ->whereIn('quotation_detail_id', $detailIds)
                ->get()
                ->groupBy(fn($item) => $item->barang_id . '_' . $item->quotation_detail_id);

            foreach ($listKaporlap as $kaporlap) {
                foreach ($quotation->quotationDetails as $detail) {
                    $fieldName = 'jumlah_' . $detail->id;
                    $key = $kaporlap->id . '_' . $detail->id;
                    $kaporlap->$fieldName = $existingMap->has($key)
                        ? $existingMap[$key]->first()->jumlah
                        : 0;
                }
            }
        }

        return [
            'jenis_barang_list' => $listJenis,
            'kaporlap_list' => $listKaporlap,
            'quotation_details' => $quotation->relationLoaded('quotationDetails')
                ? $quotation->quotationDetails
                    ->map(fn($d) => [
                        'id' => $d->id,
                        'position_id' => $d->position_id,
                        'jumlah_hc' => $d->jumlah_hc,
                        'jabatan_kebutuhan' => $d->jabatan_kebutuhan,
                        'nama_site' => $d->nama_site,
                    ])
                    ->values()
                    ->toArray()
                : [],
        ];
    }

    private function buildAdditionalDataStep8(Quotation $quotation): array
    {
        $listJenis = JenisBarang::whereIn('id', [9, 10, 11, 12, 17])
            ->select('id', 'nama')
            ->get();

        $listDevices = Barang::whereIn('jenis_barang_id', [8, 9, 10, 11, 12, 17])
            ->select('id', 'nama', 'harga', 'jenis_barang_id')
            ->ordered()
            ->get();

        $barangIds = $listDevices->pluck('id')->toArray();

        if ($quotation->revisi == 0) {
            // Preload semua qty default dalam 1 query
            $defaultQtyMap = BarangDefaultQty::where('layanan_id', $quotation->kebutuhan_id)
                ->whereIn('barang_id', $barangIds)
                ->get()
                ->keyBy('barang_id');

            foreach ($listDevices as $device) {
                $device->jumlah = $defaultQtyMap->has($device->id)
                    ? $defaultQtyMap[$device->id]->qty_default
                    : 0;
            }
        } else {
            // Preload semua existing devices dalam 1 query
            $existingMap = QuotationDevices::where('quotation_id', $quotation->id)
                ->whereIn('barang_id', $barangIds)
                ->get()
                ->keyBy('barang_id');

            foreach ($listDevices as $device) {
                $device->jumlah = $existingMap->has($device->id)
                    ? $existingMap[$device->id]->jumlah
                    : 0;
            }
        }
        // Hitung jumlah HC per site
        // 1. Pastikan relasi loaded, jika tidak, muat secara manual
        if (!$quotation->relationLoaded('quotationSites')) {
            $quotation->load([
                'quotationSites' => function ($query) {
                    $query->whereNull('deleted_at');
                }
            ]);
        }

        // 2. Ambil data HC per site
        $hcPerSite = QuotationDetail::where('quotation_id', $quotation->id)
            ->selectRaw('quotation_site_id, SUM(jumlah_hc) as total_hc')
            ->groupBy('quotation_site_id')
            ->pluck('total_hc', 'quotation_site_id')
            ->toArray();

        return [
            'jenis_barang_list' => $listJenis,
            'devices_list' => $listDevices,
            'quotation_sites' => $quotation->quotationSites->map(function ($site) use ($hcPerSite) {
                return [
                    'id' => $site->id,
                    'nama_site' => $site->nama_site, // Diambil dari fillable sl_quotation_site
                    'jumlah_hc' => isset($hcPerSite[$site->id]) ? (int) $hcPerSite[$site->id] : 0,
                ];
            })->values()->toArray(),
        ];
    }

    /**
     * Step 9 — Master chemicals dengan nilai default form
     * Estimasi: 30–80ms
     */
    private function buildAdditionalDataStep9(Quotation $quotation): array
    {
        $chemicalList = Barang::whereIn('jenis_barang_id', [13, 14, 15, 16, 18, 19])
            ->select('id', 'nama', 'harga') // Hanya ambil kolom yang diproses
            ->ordered()
            ->get()
            ->map(function ($chemical) {
                // Tambahkan properti tambahan untuk frontend
                $chemical->harga_formatted = number_format($chemical->harga, 0, ',', '.');
                $chemical->jumlah = 0;
                $chemical->masa_pakai = $chemical->masa_pakai ?? 12;
                $chemical->jumlah_pertahun = 0;
                $chemical->total_formatted = 'Rp 0';

                return $chemical;
            });
        // Hitung jumlah HC per site
        // 1. Pastikan relasi loaded, jika tidak, muat secara manual
        if (!$quotation->relationLoaded('quotationSites')) {
            $quotation->load([
                'quotationSites' => function ($query) {
                    $query->whereNull('deleted_at');
                }
            ]);
        }

        // 2. Ambil data HC per site
        $hcPerSite = QuotationDetail::where('quotation_id', $quotation->id)
            ->selectRaw('quotation_site_id, SUM(jumlah_hc) as total_hc')
            ->groupBy('quotation_site_id')
            ->pluck('total_hc', 'quotation_site_id')
            ->toArray();

        return [
            'chemical_list' => $chemicalList,
            'quotation_sites' => $quotation->quotationSites->map(function ($site) use ($hcPerSite) {
                return [
                    'id' => $site->id,
                    'nama_site' => $site->nama_site,
                    'jumlah_hc' => isset($hcPerSite[$site->id]) ? (int) $hcPerSite[$site->id] : 0,
                ];
            })->values()->toArray(),
        ];
    }

    /**
     * Step 10 — Master OHC, training, opsi statis
     * Estimasi: 30–80ms
     */
    private function buildAdditionalDataStep10(Quotation $quotation): array
    {   $listJenis = JenisBarang::whereIn('id', [6, 7, 8])
            ->select('id', 'nama')
            ->get();
        if (!$quotation->relationLoaded('quotationSites')) {
            $quotation->load([
                'quotationSites' => function ($query) {
                    $query->whereNull('deleted_at');
                }
            ]);
        }

        // 2. Ambil data HC per site
        $hcPerSite = QuotationDetail::where('quotation_id', $quotation->id)
            ->selectRaw('quotation_site_id, SUM(jumlah_hc) as total_hc')
            ->groupBy('quotation_site_id')
            ->pluck('total_hc', 'quotation_site_id')
            ->toArray();
        return [
            'ohc_list' => Barang::whereIn('jenis_barang_id', [6, 7, 8])
                ->select('id', 'nama', 'harga', 'jenis_barang_id', 'urutan') // Pilih kolom yang diperlukan saja
                ->orderBy('urutan', 'asc')
                ->orderBy('nama', 'asc')
                ->get()
                ->map(function ($ohc) {
                    $ohc->harga_formatted = number_format($ohc->harga, 0, ',', '.');
                    return $ohc;
                }),
            'quotation_sites' => $quotation->quotationSites->map(function ($site) use ($hcPerSite) {
                return [
                    'id' => $site->id,
                    'nama_site' => $site->nama_site,
                    'jumlah_hc' => isset($hcPerSite[$site->id]) ? (int) $hcPerSite[$site->id] : 0,
                ];
            })->values()->toArray(),
            'jenis_barang_list' => $listJenis,
            'training_list' => Training::select('id', 'nama', 'jenis')->get(), // Hindari all() untuk performa lebih baik
            'bulan_tahun_options' => ['Bulan', 'Tahun'],
            'ada_training_options' => ['Ada', 'Tidak Ada'],
        ];
    }

    private function buildAdditionalDataStep11(Quotation $quotation): array
    {
        $calculatedQuotation = $this->quotationService->calculateQuotation($quotation);
        return [
            'calculated_quotation' => $calculatedQuotation,
        ];
    }

    /**
     * Step 12 —summary kalkulasi akhir untuk konfirmasi final
     * Estimasi: 100–500ms
     * Hasil disimpan di $additionalData dan dipakai oleh buildStepDataStep12.
     */
    private function buildAdditionalDataStep12(Quotation $quotation): array
    {
        return [
            // 'calculated_quotation' => $this->quotationService->calculateQuotation($quotation),
        ];
    }

    // =========================================================================
    // PRIVATE — HELPERS
    // =========================================================================

    /**
     * Tentukan nilai display tunjangan (HPP & COSS) berdasarkan jenis wage.
     * Memindahkan closure $getTunjanganDisplayForBoth dari Resource ke sini.
     */
    private function resolveTunjanganDisplay(
        $wage,
        string $jenisField,
        $hppValue,
        $cossValue,
        ?string $fieldDitagihkanTerpisah = null
    ): array {
        if (!$wage) {
            return ['hpp' => 'Tidak Ada', 'coss' => 'Tidak Ada'];
        }

        $jenisValue = strtolower(trim(is_string($wage->$jenisField ?? null) ? $wage->$jenisField : ''));
        $ditagihkanValue = '';

        if ($fieldDitagihkanTerpisah && isset($wage->$fieldDitagihkanTerpisah)) {
            $ditagihkanValue = strtolower(trim(is_string($wage->$fieldDitagihkanTerpisah) ? $wage->$fieldDitagihkanTerpisah : ''));

            if ($ditagihkanValue === 'ditagihkan terpisah') {
                return ['hpp' => 'Ditagihkan terpisah', 'coss' => 'Ditagihkan terpisah'];
            }

            if (in_array($ditagihkanValue, ['diberikan langsung', 'diberikan langsung oleh client'])) {
                return ['hpp' => 'Diberikan Langsung Oleh Client', 'coss' => 'Diberikan Langsung Oleh Client'];
            }
        }

        if (in_array($jenisValue, ['normatif', 'ditagihkan'])) {
            return ['hpp' => 'Ditagihkan terpisah', 'coss' => 'Ditagihkan terpisah'];
        }

        if (in_array($jenisValue, ['flat', 'diprovisikan'])) {
            return [
                'hpp' => $hppValue > 0 ? $hppValue : 'Tidak Ada',
                'coss' => $cossValue > 0 ? $cossValue : 'Tidak Ada',
            ];
        }

        if (in_array($jenisValue, ['diberikan langsung', 'diberikan langsung oleh client'])) {
            return ['hpp' => 'Diberikan Langsung Oleh Client', 'coss' => 'Diberikan Langsung Oleh Client'];
        }

        return ['hpp' => 'Tidak Ada', 'coss' => 'Tidak Ada'];
    }

    /**
     * Parse string kunjungan "jumlah periode" menjadi array [jumlah, periode].
     * Contoh: "3 Bulan" → ['3', 'Bulan']
     */
    private function parseKunjungan(string $value): array
    {
        if (empty($value)) {
            return ['', ''];
        }

        $parts = explode(' ', $value, 2);
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    /**
     * Hitung selisih waktu dalam milidetik sejak $startTime.
     */
    private function elapsedMs(float $startTime): string
    {
        return round((microtime(true) - $startTime) * 1000, 2) . 'ms';
    }
}