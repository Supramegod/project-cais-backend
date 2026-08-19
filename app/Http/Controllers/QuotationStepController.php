<?php

namespace App\Http\Controllers;

use App\Http\Requests\Quotation\QuotationStepRequest;
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
use App\Models\QuotationManagementFee;
use App\Models\SalaryRule;
use App\Models\Top;
use App\Models\Training;
use App\Models\Umk;
use App\Models\Ump;
use App\Models\Umsk;
use App\Models\Umsp;
use App\Services\Quotation\QuotationBarangService;
use App\Services\Quotation\QuotationService;
use App\Services\Quotation\Steps\QuotationStepService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Quotations",
 *     description="API Endpoints for Quotation Management"
 * )
 *
 * Refactored: Logic for GET step data is delegated to step-specific build methods.
 * UPDATE is delegated to QuotationStepService (→ StepUpdateService) unchanged.
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
    // =========================================================================

    private const STEP_RELATIONS = [
        1 => ['kebutuhan'],
        2 => ['quotationSites'],
        3 => ['quotationDetails.quotationDetailRequirements', 'quotationDetails.quotationDetailTunjangans', 'quotationSites'],
        4 => ['quotationDetails.wage', 'quotationDetails.quotationSite', 'quotationSites', 'managementFeeConfig'],
        5 => ['quotationDetails', 'jenisPerusahaan', 'leads.jenisperusahaan'],
        6 => ['quotationAplikasis'],
        7 => ['quotationDetails', 'quotationKaporlaps'],
        8 => ['quotationDetails', 'quotationDevices'],
        9 => ['quotationChemicals', 'quotationSites'],
        10 => ['quotationTrainings', 'quotationOhcs', 'quotationSites'],
        11 => [
            'quotationDetails.quotationDetailHpps',
            'quotationDetails.quotationDetailCosses',
            'quotationDetails.wage',
            'quotationDetails.quotationDetailTunjangans',
            'quotationDetails.quotationSite',
            'quotationSites',
            'quotationKaporlaps',
            'quotationDevices',
            'quotationChemicals',
            'quotationOhcs',
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
     * @OA\Get(
     *     path="/api/quotations-step/{id}/step/{step}",
     *     summary="Get quotation data for a specific step",
     *     tags={"Quotations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="step", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Step data retrieved successfully"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Quotation not found")
     * )
     */
    public function getStep(string $id, int $step): JsonResponse
    {
        $startTime = microtime(true);

        try {
            set_time_limit(0);

            $quotation = Quotation::notDeleted()->findOrFail($id);
            $relations = $this->resolveStepRelations($quotation, $step);
            $quotation->load($relations);

            if ($quotation->step == 100 && $quotation->status_quotation_id != 1 && Auth::user()->cais_role_id != 2) {
                return $this->errorResponse('Quotation has been finalized and cannot be accessed.', 403);
            }

            $stepData = $this->prepareStepData($quotation, $step);

            return response()->json([
                'success' => true,
                'data' => $stepData,
                'message' => 'Step data retrieved successfully',
                'processing_time' => $this->elapsedMs($startTime),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Quotation not found');
        } catch (\Exception $e) {
            Log::error('QuotationStepController@getStep: ' . $e->getMessage(), [
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
     * @OA\Post(
     *     path="/api/quotations-step/{id}/step/{step}",
     *     summary="Update quotation data for a specific step",
     *     tags={"Quotations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Quotation ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="step", in="path", required=true, description="Step Number (1-12)", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Payload varies depending on the step number. Select from the examples dropdown.",
     *         @OA\JsonContent(
     *             @OA\Examples(example="step_1_data_site", summary="Step 1: Data Site (version-2)", value={"nama_perusahaan": "PT Angin Ribut (Required)", "kota": "Jakarta (Required)", "cabang": "Sudirman (Required)", "jenis_perusahaan": "Manufaktur (Required)", "jenis_perusahaan_id": 1, "status_gedung": "Milik Sendiri (Required)", "alamat_lengkap": "Jl. Sudirman No 1 (Required)", "link_maps": "https://maps.app.goo.gl/example (Optional)", "jumlah_lantai": 3, "luas_estimasi_area": "1000 m2 (Optional)", "area_khusus": "Basement (Optional)", "hari_operasional": "Senin-Jumat (Required)", "pengaturan_shift_kerja": "2 Shift (Optional)", "jumlah_hc": 10, "catatan": "Catatan tambahan (Optional)", "edit": false}),
     *             @OA\Examples(example="step_1", summary="Step 1 (version-1)/ Step 2: Jenis Kontrak (version-2)", value={"jenis_kontrak": "Reguler (Required)", "edit": false}),
     *             @OA\Examples(example="step_2", summary="Step 2 (version-1) / Step 3 (version-2): Detail Kontrak", value={"mulai_kontrak": "2024-01-01 (Required v1)", "kontrak_selesai": "2024-12-31 (Required v1)", "tgl_penempatan": "2024-01-01 (Required v1)", "top": "Lebih Dari 7 Hari (Required v1)", "salary_rule": 1, "jumlah_hari_invoice": 14, "tipe_hari_invoice": "Kerja (Required v1)", "evaluasi_kontrak": "Tahunan (Required v1)", "durasi_kerjasama": "12 Bulan (Required v1)", "durasi_karyawan": "12 Bulan (Required v1)", "evaluasi_karyawan": "Tahunan (Required v1)", "ada_cuti": "Ada (Required v1)", "cuti": {"Cuti Tahunan (Required v1)"}, "gaji_saat_cuti": "Prorate (Optional v1)", "prorate": 20, "hari_kerja": "Senin - Jumat (Required v1/v2)", "shift_kerja": "Non Shift (Required v2)", "jam_kerja": "08:00 - 17:00 (Required v1/v2)", "hari_off": "Sabtu - Minggu (Required v2)", "jam_lembur": "2 Jam (Required v2)", "cuti_izin": {"Cuti Tahunan (Required v2)", "Izin Sakit"}, "status_rekrutmen": "Internal (Required v2)", "pendaftaran_pkwt": "Ya (Required v2)", "jaminan": "BPJS (Required v2)", "penanggung_jawab_aset": "Manager (Required v2)", "detail_penanggung_jawab_aset": "Detail aset... (Required v2)", "edit": false}),
     *             @OA\Examples(example="step_3", summary="Step 3: Headcount", value={"headCountData": {{"quotation_site_id": 1, "position_id": 5, "jumlah_hc": 10, "jabatan_kebutuhan": "Security Guard (Required)", "nama_site": "Head Office (Required)"}}, "edit": false}),
     *             @OA\Examples(example="step_4", summary="Step 4: Costing", value={"is_ppn": 1, "ppn_pph_dipotong": "Total Invoice (Required)", "management_fee_id": 1, "persentase": 10, "position_data": {{"quotation_detail_id": 1, "upah": "Custom (Required)", "hitungan_upah": "Per Bulan (Required if Custom)", "nominal_upah": 5000000, "lembur": "Flat (Optional)", "nominal_lembur": 100000, "jenis_bayar_lembur": "Per Jam (Required if Flat)", "jam_per_bulan_lembur": 10, "lembur_ditagihkan": "Ditagihkan (Required if Flat/Normatif)", "kompensasi": "Diprovisikan (Optional)", "thr": "Diprovisikan (Optional)", "tunjangan_holiday": "Flat (Optional)", "nominal_tunjangan_holiday": 150000, "jenis_bayar_tunjangan_holiday": "Per Hari (Required if Flat)"}}, "edit": false}),
     *             @OA\Examples(example="step_5", summary="Step 5: BPJS", value={"jenis-perusahaan": 21, "bidang-perusahaan": 12, "resiko": "Sangat Rendah (Required)", "program-bpjs": "BPJS Kesehatan (Required)", "penjamin": {"1": "BPJS Kesehatan (Optional)"}, "jkk": {"1": true}, "jkm": {"1": true}, "jht": {"1": false}, "jp": {"1": false}, "kes": {"1": true}, "nominal_takaful": {"1": 0}, "edit": false}),
     *             @OA\Examples(example="step_6", summary="Step 6: Aplikasi Pendukung", value={"aplikasi_pendukung": {1, 2, 3}, "edit": false}),
     *             @OA\Examples(example="step_7", summary="Step 7: Kaporlap / APD", value={"kaporlaps": {{"barang_id": 1, "quotation_detail_id": 1, "jumlah": 5, "harga": 150000}}, "edit": false}),
     *             @OA\Examples(example="step_8", summary="Step 8: Devices", value={"devices": {{"barang_id": 10, "jumlah": 2, "harga": 5000000}}, "edit": false}),
     *             @OA\Examples(example="step_9", summary="Step 9: Chemical / Peralatan", value={"barang_id": 10, "jumlah": 5, "masa_pakai": 12, "harga": 150000, "chemicals": {{"barang_id": 12, "jumlah": 2, "masa_pakai": 6, "harga": 50000}}, "edit": false}),
     *             @OA\Examples(example="step_10", summary="Step 10: Operasional", value={"jumlah_kunjungan_operasional": 2, "bulan_tahun_kunjungan_operasional": "Bulan (Required)", "jumlah_kunjungan_tim_crm": 1, "bulan_tahun_kunjungan_tim_crm": "Tahun (Required)", "keterangan_kunjungan_operasional": "Kunjungan rutin (Optional)", "keterangan_kunjungan_tim_crm": "Evaluasi tahunan (Optional)", "ada_training": "Ada (Optional)", "training": "Basic Security Training (Optional)", "persen_bunga_bank": 5.5, "ohcs": {{"quotation_site_id": 1, "barang_id": 5, "jumlah": 2}, {"quotation_site_id": 1, "is_custom": true, "nama": "Barang Custom OHC", "jumlah": 1, "harga": 50000}}, "edit": false}),
     *             @OA\Examples(example="step_10_driver", summary="Step 10: Driver", value={"drivers": {{"status_kendaraan": "Milik Sendiri (Optional)", "jenis_kendaraan": "Mobil (Optional)", "nama_kendaraan": "Avanza (Optional)", "kepemilikan_sim": "A (Optional)", "asuransi_mobil": "Ada (Optional)", "gps_map": "Ada (Optional)", "tipe_layanan_angkut": "Barang (Optional)", "area_dihandle": "Jabodetabek (Optional)", "kapasitas_bobot_maksimal": "1000kg (Optional)", "asuransi_barang": "Ada (Optional)", "biaya_khusus_kecelakaan": 1500000}}, "edit": false}),
     *             @OA\Examples(example="step_11", summary="Step 11: Pricing", value={"penagihan": "Sesuai BAST (Required)", "tunjangan_data": {{{"nama_tunjangan": "Tunjangan Makan (Optional)", "nominal": 50000}}}, "edit": false}),
     *             @OA\Examples(example="step_12", summary="Step 12: Finalization", value={"is_draft": false})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Step updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", description="Quotation data along with relations and step-specific additional_data",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="step", type="integer", example=1),
     *                 @OA\Property(property="leads_id", type="integer", example=10),
     *                 @OA\Property(property="quotation_details", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="quotation_sites", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="additional_data", type="object", description="Step-specific reference data or calculated data")
     *             ),
     *             @OA\Property(property="message", type="string", example="Step 1 updated successfully"),
     *             @OA\Property(property="processing_time", type="string", example="120.50ms")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation Error / Sequence Violation")
     * )
     */
    public function updateStep(QuotationStepRequest $request, $id, $step): JsonResponse
    {
        $startTime = microtime(true);

        // DDD Domain Services Interception for non-strict steps
        $quotation = Quotation::notDeleted()->findOrFail($id);
        $logicalStepName = \App\Services\Quotation\Steps\StepMapper::resolveUpdateMethod($quotation->version ?? 1, (int)$step);

        if ($logicalStepName === 'notFound') {
            return $this->notFoundResponse('Step method not found');
        }

        if ($step > $quotation->step + 1) {
            return $this->errorResponse("Cannot update step {$step}. Please complete previous steps first (current step: {$quotation->step}).", 422);
        }

        DB::transaction(function () use ($request, $id, $step, $logicalStepName) {
            $quotation = Quotation::notDeleted()->findOrFail($id);

            if ($quotation->step == 100 && $quotation->status_quotation_id != 1 && Auth::user()->cais_role_id != 2) {
                throw new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'Quotation has been finalized and cannot be updated.');
            }

            // Execute the corresponding service
            if (in_array($logicalStepName, ['updateCosting', 'updatePricing', 'updateFinalization'])) {
                if ($logicalStepName === 'updateCosting') {
                    app(\App\Services\Quotation\Domain\CostingService::class)->execute($quotation, $request);
                } elseif ($logicalStepName === 'updatePricing') {
                    app(\App\Services\Quotation\Domain\PricingService::class)->execute($quotation, $request);
                } elseif ($logicalStepName === 'updateFinalization') {
                    app(\App\Services\Quotation\Domain\FinalizationService::class)->execute($quotation, $request);
                }
            } else {
                $this->quotationStepService->$logicalStepName($quotation, $request);
            }

            // Step increment logic
            $maxOperationalStep = $quotation->version === 1 ? 10 : 11;

            if ($quotation->step <= $maxOperationalStep) {
                $nextStep = $step + 1;

                if ($quotation->version === 1) {
                    if ($nextStep == 5) {
                        $nextStep = 6;
                    }
                    if ($nextStep == 12) {
                        $nextStep = 11;
                    }
                }

                $quotation->update([
                    'step' => max($quotation->step, $nextStep),
                    'updated_by' => Auth::user()->full_name,
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $this->prepareStepData(Quotation::notDeleted()->findOrFail($id), $step),
            'message' => "Step {$step} updated successfully",
            'processing_time' => $this->elapsedMs($startTime),
        ]);
    }

    // =========================================================================
    // PRIVATE — RELATIONS RESOLVER
    // =========================================================================

    private function resolveStepRelations(Quotation $quotation, int $step): array
    {
        $logicalStepName = \App\Services\Quotation\Steps\StepMapper::resolveUpdateMethod($quotation->version ?? 1, $step);

        if ($logicalStepName === 'updateDriver') {
            return ['quotationDrivers'];
        }

        return self::STEP_RELATIONS[$step] ?? [];
    }

    private function prepareStepData(Quotation $quotation, int $step): array
    {
        $logicalStepName = \App\Services\Quotation\Steps\StepMapper::resolveUpdateMethod($quotation->version ?? 1, $step);

        $additionalDataMethod = 'buildAdditionalDataStep'.$step;
        $additionalData = method_exists($this, $additionalDataMethod)
            ? $this->$additionalDataMethod($quotation)
            : [];

        $stepData = [];
        if ($logicalStepName === 'updateDriver') {
            $stepData = $this->buildStepDataDriver($quotation, $additionalData);
        } else {
            $stepDataMethod = 'buildStepDataStep'.$step;
            $stepData = method_exists($this, $stepDataMethod)
                ? $this->$stepDataMethod($quotation, $additionalData)
                : [];
        }

        $baseData = [
            'id' => $quotation->id,
            'step' => $step,
            'step_data' => $stepData,
            'additional_data' => $additionalData,
            'metadata' => $quotation->step,
        ];
        if (in_array($step, [1])) {
            $baseData['nama_perusahaan'] = $this->nama_perusahaan ?? $quotation->nama_perusahaan;
            $baseData['kebutuhan'] = $this->kebutuhan ?? $quotation->kebutuhan;
        }

        return $baseData;
    }

    private function buildStepDataStep1(Quotation $quotation, array $additionalData): array
    {
        return [
            'jenis_kontrak' => $quotation->jenis_kontrak,
            'layanan_id' => $quotation->kebutuhan_id,
            'layanan_nama' => $quotation->kebutuhan->nama ?? null,
        ];
    }

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

    private function buildStepDataStep3(Quotation $quotation, array $additionalData): array
    {
        $quotationDetails = [];

        if ($quotation->relationLoaded('quotationDetails')) {
            $quotationDetails = $quotation->quotationDetails->map(function ($detail) {
                $data = [
                    'id' => $detail->id,
                    'nama_site' => $detail->nama_site,
                    'quotation_site_id' => $detail->quotation_site_id,
                    'position_id' => $detail->position_id,
                    'jabatan_kebutuhan' => $detail->jabatan_kebutuhan,
                    'jumlah_hc' => $detail->jumlah_hc,
                    'nominal_upah' => $detail->nominal_upah,
                ];

                $requirements = [];
                if (method_exists($detail, 'quotationDetailRequirements') && $detail->relationLoaded('quotationDetailRequirements')) {
                    $requirements = $detail->quotationDetailRequirements->pluck('requirement')->toArray();
                } else {
                    try {
                        $requirements = $detail->quotationDetailRequirements()->pluck('requirement')->toArray();
                    } catch (\Exception $e) {
                        $requirements = [];
                    }
                }
                $data['requirements'] = $requirements;

                $tunjangans = [];
                if (method_exists($detail, 'quotationDetailTunjangans') && $detail->relationLoaded('quotationDetailTunjangans')) {
                    $tunjangans = $detail->quotationDetailTunjangans->map(function ($tunjangan) {
                        return [
                            'nama_tunjangan' => $tunjangan->nama_tunjangan,
                            'nominal' => $tunjangan->nominal,
                            'nominal_coss' => $tunjangan->nominal_coss,
                            'jenis' => $tunjangan->jenis,
                        ];
                    })->toArray();
                } else {
                    try {
                        $tunjangans = $detail->quotationDetailTunjangans()->get()->map(function ($tunjangan) {
                            return [
                                'nama_tunjangan' => $tunjangan->nama_tunjangan,
                                'nominal' => $tunjangan->nominal,
                                'nominal_coss' => $tunjangan->nominal_coss,
                                'jenis' => $tunjangan->jenis,
                            ];
                        })->toArray();
                    } catch (\Exception $e) {
                        $tunjangans = [];
                    }
                }
                $data['tunjangans'] = $tunjangans;

                return $data;
            })->toArray();
        }

        return [
            'quotation_details' => $quotationDetails,
        ];
    }

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
                        $keteranganMinUpah = 'Upah kurang dari 85% UMK ( Rp ' . number_format($minUpahNominal, 0, ',', '.') . ' ) membutuhkan approval ';
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
                'is_ppn' => $quotation->is_ppn ?? false,
                'ppn_pph_dipotong' => $quotation->ppn_pph_dipotong ?? false,
                'management_fee_id' => $quotation->management_fee_id ?? null,
                'persentase' => $quotation->persentase ?? 0,
                'management_fee_components' => $this->resolveMfConfig($quotation),
            ],
        ];
    }

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

    private function buildStepDataStep6(Quotation $quotation, array $additionalData): array
    {
        return [
            'aplikasi_pendukung' => $quotation->relationLoaded('quotationAplikasis')
                ? $quotation->quotationAplikasis->pluck('aplikasi_pendukung_id')->toArray()
                : [],
        ];
    }

    private function buildStepDataStep7(Quotation $quotation, array $additionalData): array
    {
        $kaporlapData = $this->quotationBarangService->prepareBarangData($quotation, 'kaporlap');

        return [
            'quotation_kaporlaps' => $kaporlapData['data'],
            'kaporlap_total' => $kaporlapData['total'],
        ];
    }

    private function buildStepDataStep8(Quotation $quotation, array $additionalData): array
    {
        $devicesData = $this->quotationBarangService->prepareBarangData($quotation, 'devices');

        return [
            'quotation_devices' => $devicesData['data'],
            'devices_total' => $devicesData['total'],
        ];
    }

    private function buildStepDataStep9(Quotation $quotation, array $additionalData): array
    {
        $chemicalData = $this->quotationBarangService->prepareBarangData($quotation, 'chemicals');

        return [
            'quotation_chemicals' => $chemicalData['data'],
            'chemicals_total' => $chemicalData['total'],
        ];
    }

    private function buildStepDataStep10(Quotation $quotation, array $additionalData): array
    {
        [$jumlahOps, $periodeOps] = $this->parseKunjungan($quotation->kunjungan_operasional ?? '');
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

    private function buildStepDataStep11(Quotation $quotation, array $additionalData): array
    {
        $calculatedQuotation = $additionalData['calculated_quotation'] ?? null;
        $summary = null;
        $persenBpjsTotalHpp = 0;
        $persenBpjsBreakdownHpp = [];
        $persenBpjsTotalCoss = 0;
        $persenBpjsBreakdownCoss = [];

        if ($calculatedQuotation && $calculatedQuotation->calculation_summary) {
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

        if ($calculatedQuotation && $calculatedQuotation->quotation) {
            $calculatedQuotation->quotation->quotationDetails->loadMissing([
                'quotationDetailHpps',
                'quotationDetailCosses',
                'wage',
                'quotationDetailTunjangans' => fn($q) => $q->whereNull('deleted_at'),
            ]);
        }

        $resolveDisplay = function ($wage, $jenisField, $hppValue, $cossValue, $fieldDitagihkan = null) {
            if (!$wage) {
                return ['hpp' => 'Tidak Ada', 'coss' => 'Tidak Ada'];
            }
            $jenis = strtolower(trim($wage->$jenisField ?? ''));
            if ($fieldDitagihkan && isset($wage->$fieldDitagihkan)) {
                $ditagihkan = strtolower(trim($wage->$fieldDitagihkan));
                if ($ditagihkan === 'ditagihkan terpisah') {
                    return ['hpp' => 'Ditagihkan terpisah', 'coss' => 'Ditagihkan terpisah'];
                }
                if (in_array($ditagihkan, ['diberikan langsung', 'diberikan langsung oleh client'])) {
                    return ['hpp' => 'Diberikan Langsung Oleh Client', 'coss' => 'Diberikan Langsung Oleh Client'];
                }
            }
            if (in_array($jenis, ['normatif', 'ditagihkan'])) {
                return ['hpp' => 'Ditagihkan terpisah', 'coss' => 'Ditagihkan terpisah'];
            }
            if (in_array($jenis, ['flat', 'diprovisikan'])) {
                return [
                    'hpp' => $hppValue > 0 ? $hppValue : 'Tidak Ada',
                    'coss' => $cossValue > 0 ? $cossValue : 'Tidak Ada',
                ];
            }
            if (in_array($jenis, ['diberikan langsung', 'diberikan langsung oleh client'])) {
                return ['hpp' => 'Diberikan Langsung Oleh Client', 'coss' => 'Diberikan Langsung Oleh Client'];
            }

            return ['hpp' => 'Tidak Ada', 'coss' => 'Tidak Ada'];
        };

        $quotationDetails = [];
        if ($calculatedQuotation && $calculatedQuotation->quotation) {
            foreach ($calculatedQuotation->quotation->quotationDetails as $detail) {
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
                    'jenis' => $t->jenis,
                ])->values()->toArray();

                $thrDisplay = $resolveDisplay($wage, 'thr', $hppData['tunjangan_hari_raya'] ?? 0, $cossData['tunjangan_hari_raya'] ?? 0);
                $kompDisplay = $resolveDisplay($wage, 'kompensasi', $hppData['kompensasi'] ?? 0, $cossData['kompensasi'] ?? 0);
                $lemburDisplay = $resolveDisplay($wage, 'lembur', $hppData['lembur'] ?? 0, $cossData['lembur'] ?? 0, 'lembur_ditagihkan');
                $holidayDisplay = $resolveDisplay($wage, 'tunjangan_holiday', $hppData['tunjangan_hari_libur_nasional'] ?? 0, $cossData['tunjangan_hari_libur_nasional'] ?? 0);
                $isRoDetail = $this->isRo($detail);
                $hppArray = [
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
                ];


                $detailItem = [
                    'id' => $detail->id,
                    'position_name' => $detail->jabatan_kebutuhan,
                    'nama_site' => $detail->nama_site,
                    'quotation_site_id' => $detail->quotation_site_id,
                    'penjamin_kesehatan' => $detail->penjamin_kesehatan,
                    'upah' => $wage?->upah ?? 0,
                    'jumlah_hc_hpp' => $hppData['jumlah_hc'] ?? 0,
                    'jumlah_hc_coss' => $isRoDetail ? 0 : ($cossData['jumlah_hc'] ?? 0),
                    'tunjangan_data' => $tunjanganData,
                    'hpp' => $hppArray,
                ];

                // **Hanya tambahkan key 'coss' jika bukan RO**
                if (!$isRoDetail) {
                    $detailItem['coss'] = [
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
                    ];
                }
                $quotationDetails[] = $detailItem;
            }
        }

        return [
            'jenis_kontrak' => $quotation->jenis_kontrak ?? '',
            'hari_kerja' => $quotation->hari_kerja ?? 0,
            'penagihan' => $quotation->penagihan ?? '',
            'nama_perusahaan' => $quotation->nama_perusahaan ?? '',
            'persentase' => $quotation->persentase ?? 0,
            'management_fee_nama' => $quotation->managementFee->nama ?? null,
            'ppn_pph_dipotong' => $quotation->ppn_pph_dipotong ?? false,
            'note_harga_jual' => $quotation->note_harga_jual ?? '',
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
            'calculation' => ($calculatedQuotation && $summary) ? [
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
                'quotation_details' => $quotationDetails,
            ] : null,
        ];
    }

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
                'total_invoice' => $calculatedQuotation->calculation_summary->total_invoice ?? 0,
                'total_invoice_coss' => $calculatedQuotation->calculation_summary->total_invoice_coss ?? 0,
                'pembulatan' => $calculatedQuotation->calculation_summary->pembulatan ?? 0,
                'pembulatan_coss' => $calculatedQuotation->calculation_summary->pembulatan_coss ?? 0,
                'grand_total_sebelum_pajak' => $calculatedQuotation->calculation_summary->grand_total_sebelum_pajak ?? 0,
                'grand_total_sebelum_pajak_coss' => $calculatedQuotation->calculation_summary->grand_total_sebelum_pajak_coss ?? 0,
                'margin' => $calculatedQuotation->calculation_summary->margin ?? 0,
                'margin_coss' => $calculatedQuotation->calculation_summary->margin_coss ?? 0,
                'gpm' => $calculatedQuotation->calculation_summary->gpm ?? 0,
                'gpm_coss' => $calculatedQuotation->calculation_summary->gpm_coss ?? 0,
            ];
        }

        return $finalData;
    }

    private function buildAdditionalDataStep1(Quotation $quotation): array
    {
        return [];
    }

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

    private function buildAdditionalDataStep3(Quotation $quotation): array
    {
        return [
            'positions' => Position::where('is_active', 1)
                ->where(function ($query) use ($quotation) {
                    $query->where('layanan_id', $quotation->kebutuhan_id)
                        ->orWhere('id', 224);
                })
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

    private function buildAdditionalDataStep4(Quotation $quotation): array
    {
        $umpPerSite = [];
        $umspPerSite = [];
        $umkPerSite = [];
        $umskPerSite = [];

        if (!$quotation->relationLoaded('quotationSites')) {
            $quotation->load([
                'quotationSites' => function ($query) {
                    $query->whereNull('deleted_at');
                },
            ]);
        }

        $sites = $quotation->quotationSites;
        $kotaIds = $sites->pluck('kota_id')->unique()->toArray();
        $provinsiIds = $sites->pluck('provinsi_id')->unique()->toArray();

        $umkList = Umk::whereIn('city_id', $kotaIds)->active()->get()->keyBy('city_id');
        $umpList = Ump::whereIn('province_id', $provinsiIds)->active()->get()->keyBy('province_id');
        $umskList = Umsk::whereIn('city_id', $kotaIds)->active()->get()->keyBy('city_id');
        $umspList = Umsp::whereIn('province_id', $provinsiIds)->active()->get()->keyBy('province_id');

        foreach ($sites as $site) {
            /** @var \App\Models\Umk|null $umk */
            $umk = $umkList->get($site->kota_id);
            /** @var \App\Models\Ump|null $ump */
            $ump = $umpList->get($site->provinsi_id);
            /** @var \App\Models\Umsk|null $umsk */
            $umsk = $umskList->get($site->kota_id);
            /** @var \App\Models\Umsp|null $umsp */
            $umsp = $umspList->get($site->provinsi_id);

            $umpPerSite[] = [
                'site_id' => $site->id,
                'site_name' => $site->nama_site,
                'province_id' => $site->provinsi_id,
                'province_name' => $site->provinsi,
                'ump_value' => $ump?->ump ?? 0,
                'formatted_ump' => $ump ? $ump->formatump() : 'UMP : Rp. 0',
            ];
            $umspPerSite[] = [
                'site_id' => $site->id,
                'site_name' => $site->nama_site,
                'province_id' => $site->provinsi_id,
                'province_name' => $site->provinsi,
                'umsp_value' => $umsp?->umsp ?? 0,
                'formatted_umsp' => $umsp ? $umsp->formatumsp() : 'UMSP : Rp. 0',
            ];

            $umkPerSite[] = [
                'site_id' => $site->id,
                'site_name' => $site->nama_site,
                'city_id' => $site->kota_id,
                'city_name' => $site->kota,
                'umk_value' => $umk?->umk ?? 0,
                'formatted_umk' => $umk ? $umk->formatumk() : 'UMK : Rp. 0',
            ];
            $umskPerSite[] = [
                'site_id' => $site->id,
                'site_name' => $site->nama_site,
                'city_id' => $site->kota_id,
                'city_name' => $site->kota,
                'umsk_value' => $umsk?->umsk ?? 0,
                'formatted_umsk' => $umsk ? $umsk->formatumsk() : 'UMSK : Rp. 0',
            ];
        }

        return [
            'management_fees' => ManagementFee::select('id', 'nama')->get(),
            'upah_options' => ['UMP', 'UMK', 'Custom'],
            'hitungan_upah_options' => ['Per Bulan', 'Per Hari', 'Per Jam'],
            'jenis_bayar_options' => ['Per Bulan', 'Per Hari', 'Per Jam'],
            'lembur_options' => ['Tidak', 'Flat'],
            'kompensasi_options' => ['Tidak', 'Diprovisikan'],
            'thr_options' => ['Tidak', 'Diprovisikan'],
            'tunjangan_holiday_options' => ['Tidak', 'Flat'],
            'lembur_ditagihkan_options' => ['Tidak Ditagihkan', 'Ditagihkan Terpisah'],
            'is_ppn_options' => ['Ya', 'Tidak'],
            'ppn_pph_dipotong_options' => ['Management Fee', 'Lainnya'],
            'ump_per_site' => $umpPerSite,
            'umsp_per_site' => $umspPerSite,
            'umk_per_site' => $umkPerSite,
            'umsk_per_site' => $umskPerSite,
            'quotation_sites' => $quotation->quotationSites->map(fn($site) => [
                'id' => $site->id,
                'nama_site' => $site->nama_site,
            ])->values()->toArray(),
            'quotation_details' => $quotation->relationLoaded('quotationDetails')
                ? $quotation->quotationDetails->map(fn($detail) => [
                    'id' => $detail->id,
                    'position_id' => $detail->position_id,
                    'position_name' => $detail->jabatan_kebutuhan,
                    'site_id' => $detail->quotation_site_id,
                    'site_name' => $detail->nama_site,
                    'jumlah_hc' => $detail->jumlah_hc,
                    'nominal_upah' => $detail->nominal_upah,
                ])->values()->toArray()
                : [],
        ];
    }

    private function buildAdditionalDataStep5(Quotation $quotation): array
    {
        return [
            'jenis_perusahaan_list' => JenisPerusahaan::select('id', 'nama', 'resiko')->get(),
            'bidang_perusahaan_list' => BidangPerusahaan::select('id', 'nama')->get(),
        ];
    }

    private function buildAdditionalDataStep6(Quotation $quotation): array
    {
        return [
            'aplikasi_pendukung_list' => AplikasiPendukung::select('id', 'nama', 'link_icon')->get(),
        ];
    }

    private function buildAdditionalDataStep7(Quotation $quotation): array
    {
        $arrKaporlap = $quotation->kebutuhan_id != 1 ? [5] : [1, 2, 3, 4, 5];
        $listJenis = JenisBarang::whereIn('id', $arrKaporlap)->select('id', 'nama')->get();

        $listKaporlap = Barang::whereIn('jenis_barang_id', $arrKaporlap)
            ->select('id', 'nama', 'jenis_barang_id')
            ->ordered()
            ->get();
        $barangIds = $listKaporlap->pluck('id')->toArray();
        $detailIds = $quotation->relationLoaded('quotationDetails')
            ? $quotation->quotationDetails->pluck('id')->toArray()
            : [];

        if ($quotation->revisi == 0) {
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
                ? $quotation->quotationDetails->map(fn($d) => [
                    'id' => $d->id,
                    'position_id' => $d->position_id,
                    'jumlah_hc' => $d->jumlah_hc,
                    'jabatan_kebutuhan' => $d->jabatan_kebutuhan,
                    'nama_site' => $d->nama_site,
                ])->values()->toArray()
                : [],
        ];
    }

    private function buildAdditionalDataStep8(Quotation $quotation): array
    {
        $listJenis = JenisBarang::whereIn('id', [9, 10, 11, 12, 17])
            ->select('id', 'nama')
            ->get();

        $listDevices = Barang::whereIn('jenis_barang_id', [8, 9, 10, 11, 12, 17])
            ->select('id', 'nama', 'jenis_barang_id')
            ->ordered()
            ->get();

        $barangIds = $listDevices->pluck('id')->toArray();

        if ($quotation->revisi == 0) {
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

        if (!$quotation->relationLoaded('quotationSites')) {
            $quotation->load([
                'quotationSites' => function ($query) {
                    $query->whereNull('deleted_at');
                },
            ]);
        }

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
                    'nama_site' => $site->nama_site,
                    'jumlah_hc' => isset($hcPerSite[$site->id]) ? (int) $hcPerSite[$site->id] : 0,
                ];
            })->values()->toArray(),
        ];
    }

    private function buildAdditionalDataStep9(Quotation $quotation): array
    {
        $chemicalList = Barang::whereIn('jenis_barang_id', [13, 14, 15, 16, 18, 19])
            ->select('id', 'nama')
            ->ordered()
            ->get()
            ->map(function ($chemical) {
                $chemical->jumlah = 0;
                $chemical->masa_pakai = $chemical->masa_pakai ?? 12;
                $chemical->jumlah_pertahun = 0;

                return $chemical;
            });

        if (!$quotation->relationLoaded('quotationSites')) {
            $quotation->load([
                'quotationSites' => function ($query) {
                    $query->whereNull('deleted_at');
                },
            ]);
        }

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

    private function buildAdditionalDataStep10(Quotation $quotation): array
    {
        $listJenis = JenisBarang::whereIn('id', [6, 7, 8])
            ->select('id', 'nama')
            ->get();

        if (!$quotation->relationLoaded('quotationSites')) {
            $quotation->load([
                'quotationSites' => function ($query) {
                    $query->whereNull('deleted_at');
                },
            ]);
        }

        $hcPerSite = QuotationDetail::where('quotation_id', $quotation->id)
            ->selectRaw('quotation_site_id, SUM(jumlah_hc) as total_hc')
            ->groupBy('quotation_site_id')
            ->pluck('total_hc', 'quotation_site_id')
            ->toArray();

        return [
            'ohc_list' => Barang::whereIn('jenis_barang_id', [6, 7, 8])
                ->select('id', 'nama', 'jenis_barang_id', 'urutan')
                ->orderBy('urutan', 'asc')
                ->orderBy('nama', 'asc')
                ->get(),
            'quotation_sites' => $quotation->quotationSites->map(function ($site) use ($hcPerSite) {
                return [
                    'id' => $site->id,
                    'nama_site' => $site->nama_site,
                    'jumlah_hc' => isset($hcPerSite[$site->id]) ? (int) $hcPerSite[$site->id] : 0,
                ];
            })->values()->toArray(),
            'jenis_barang_list' => $listJenis,
            'training_list' => Training::select('id', 'nama', 'jenis')->get(),
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

    private function buildAdditionalDataStep12(Quotation $quotation): array
    {
        return [];
    }

    private function parseKunjungan(string $value): array
    {
        if (empty($value)) {
            return ['', ''];
        }

        $parts = explode(' ', $value, 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function elapsedMs(float $startTime): string
    {
        return round((microtime(true) - $startTime) * 1000, 2) . 'ms';
    }
    private function isRo($detail): bool
    {
        return ($detail->position_id ?? null) === 224;
    }

    private function buildStepDataDriver(Quotation $quotation, array $additionalData): array
    {
        $drivers = [];
        if ($quotation->relationLoaded('quotationDrivers')) {
            $drivers = $quotation->quotationDrivers->map(function ($driver) {
                return [
                    'id' => $driver->id,
                    'status_kendaraan' => $driver->status_kendaraan,
                    'jenis_kendaraan' => $driver->jenis_kendaraan,
                    'nama_kendaraan' => $driver->nama_kendaraan,
                    'kepemilikan_sim' => $driver->kepemilikan_sim,
                    'asuransi_mobil' => $driver->asuransi_mobil,
                    'gps_map' => $driver->gps_map,
                    'tipe_layanan_angkut' => $driver->tipe_layanan_angkut,
                    'area_dihandle' => $driver->area_dihandle,
                    'kapasitas_bobot_maksimal' => $driver->kapasitas_bobot_maksimal,
                    'asuransi_barang' => $driver->asuransi_barang,
                    'biaya_khusus_kecelakaan' => $driver->biaya_khusus_kecelakaan,
                ];
            })->toArray();
        }

        return [
            'quotation_drivers' => $drivers,
            'drivers_total' => count($drivers),
        ];
    }

    private function resolveMfConfig(Quotation $quotation): array
    {
        $config = $quotation->relationLoaded('managementFeeConfig')
            ? $quotation->managementFeeConfig
            : null;

        $flags = QuotationManagementFee::componentFlags();

        if (!$config) {
            return array_fill_keys($flags, true);
        }

        return collect($flags)
            ->mapWithKeys(fn($flag) => [$flag => (bool) ($config->{$flag} ?? true)])
            ->all();
    }
}
