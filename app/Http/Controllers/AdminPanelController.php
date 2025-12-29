<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Services\QuotationStepService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Admin Panel",
 *     description="Endpoints untuk admin panel - update step quotation"
 * )
 */
class AdminPanelController extends Controller
{
    protected $quotationStepService;

    public function __construct(QuotationStepService $quotationStepService)
    {
        $this->quotationStepService = $quotationStepService;
    }

    /**
     * @OA\Put(
     *     path="/api/admin-panel/quotations/{quotation}/hc",
     *     summary="Update step 3 (Headcount dan Position)",
     *     tags={"Admin Panel"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="quotation",
     *         in="path",
     *         required=true,
     *         description="ID Quotation",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data untuk update step 3",
     *         @OA\JsonContent(
     *             required={"headCountData"},
     *             @OA\Property(
     *                 property="headCountData",
     *                 type="array",
     *                 description="Array data headcount per position per site",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"quotation_site_id", "position_id", "jumlah_hc"},
     *                     @OA\Property(property="quotation_site_id", type="integer", example=1),
     *                     @OA\Property(property="position_id", type="integer", example=1820),
     *                     @OA\Property(property="jumlah_hc", type="integer", example=3),
     *                     @OA\Property(property="jabatan_kebutuhan", type="string", example="Security Supervisor"),
     *                     @OA\Property(property="nama_site", type="string", example="Site A")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Step 3 berhasil diupdate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Step 3 berhasil diupdate"),
     *             @OA\Property(property="data", type="object", example={})
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Request tidak valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Data tidak valid"),
     *             @OA\Property(property="errors", type="object", example={"headCountData": "Data headcount diperlukan"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Quotation tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Quotation tidak ditemukan")
     *         )
     *     )
     * )
     */
    public function updateStep3(Request $request, Quotation $quotation)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'headCountData' => 'required|array',
                'headCountData.*.quotation_site_id' => 'required|integer|exists:sl_quotation_site,id',
                'headCountData.*.position_id' => 'required|integer|exists:mysqlhris.m_position,id',
                'headCountData.*.jumlah_hc' => 'required|integer|min:0',
                'headCountData.*.jabatan_kebutuhan' => 'nullable|string|max:255',
                'headCountData.*.nama_site' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' =>  $validator->errors()
                ], 400);
            }

            Log::info('Admin Panel - Update Step 3', [
                'quotation_id' => $quotation->id,
                'user_id' => auth()->id(),
                'headcount_count' => count($request->headCountData)
            ]);

            // Panggil service untuk update step 3
            $this->quotationStepService->updateStep3($quotation, $request);

            return response()->json([
                'success' => true,
                'message' => 'Step 3 berhasil diupdate',
                'data' => [
                    'quotation_id' => $quotation->id,
                    'headcount_updated' => count($request->headCountData)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin Panel - Error update step 3', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal update step 3: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/admin-panel/quotations/{quotation}/kaporlap",
     *     summary="Update step 7 (Kaporlap/APD)",
     *     tags={"Admin Panel"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="quotation",
     *         in="path",
     *         required=true,
     *         description="ID Quotation",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data kaporlap/APD",
     *         @OA\JsonContent(
     *             required={"kaporlaps"},
     *             @OA\Property(
     *                 property="kaporlaps",
     *                 type="array",
     *                 description="Array data kaporlap per barang per detail",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"barang_id", "quotation_detail_id", "jumlah"},
     *                     @OA\Property(property="barang_id", type="integer", example=1),
     *                     @OA\Property(property="quotation_detail_id", type="integer", example=1),
     *                     @OA\Property(property="jumlah", type="integer", example=5),
     *                     @OA\Property(property="harga", type="number", example=150000),
     *                     @OA\Property(property="masa_pakai", type="integer", example=12, description="Masa pakai dalam bulan")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Step 7 berhasil diupdate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Step 7 berhasil diupdate"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function updateStep7(Request $request, Quotation $quotation)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'kaporlaps' => 'required|array',
                'kaporlaps.*.barang_id' => 'required|integer|exists:m_barang,id',
                'kaporlaps.*.quotation_detail_id' => 'required|integer|exists:sl_quotation_detail,id',
                'kaporlaps.*.jumlah' => 'required|integer|min:0',
                'kaporlaps.*.harga' => 'nullable|numeric|min:0',
                'kaporlaps.*.masa_pakai' => 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()
                ], 400);
            }

            Log::info('Admin Panel - Update Step 7', [
                'quotation_id' => $quotation->id,
                'user_id' => auth()->id(),
                'kaporlap_count' => count($request->kaporlaps)
            ]);

            // Panggil service untuk update step 7
            $this->quotationStepService->updateStep7($quotation, $request);

            return response()->json([
                'success' => true,
                'message' => 'Step 7 berhasil diupdate',
                'data' => [
                    'quotation_id' => $quotation->id,
                    'step' => 7,
                    'kaporlap_items' => count($request->kaporlaps)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin Panel - Error update step 7', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal update step 7: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/admin-panel/quotations/{quotation}/devices",
     *     summary="Update step 8 (Devices/Peralatan)",
     *     tags={"Admin Panel"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="quotation",
     *         in="path",
     *         required=true,
     *         description="ID Quotation",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data devices/peralatan",
     *         @OA\JsonContent(
     *             required={"devices"},
     *             @OA\Property(
     *                 property="devices",
     *                 type="array",
     *                 description="Array data devices",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"barang_id", "jumlah"},
     *                     @OA\Property(property="barang_id", type="integer", example=10),
     *                     @OA\Property(property="jumlah", type="integer", example=2),
     *                     @OA\Property(property="harga", type="number", example=5000000),
     *                     @OA\Property(property="masa_pakai", type="integer", example=24, description="Masa pakai dalam bulan")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Step 8 berhasil diupdate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Step 8 berhasil diupdate"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function updateStep8(Request $request, Quotation $quotation)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'devices' => 'required|array',
                'devices.*.barang_id' => 'required|integer|exists:m_barang,id',
                'devices.*.jumlah' => 'required|integer|min:0',
                'devices.*.harga' => 'nullable|numeric|min:0',
                'devices.*.masa_pakai' => 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 400);
            }

            Log::info('Admin Panel - Update Step 8', [
                'quotation_id' => $quotation->id,
                'user_id' => auth()->id(),
                'devices_count' => count($request->devices)
            ]);

            // Panggil service untuk update step 8
            $this->quotationStepService->updateStep8($quotation, $request);

            return response()->json([
                'success' => true,
                'message' => 'Step 8 berhasil diupdate',
                'data' => [
                    'quotation_id' => $quotation->id,
                    'devices_items' => count($request->devices)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin Panel - Error update step 8', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal update step 8: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/admin-panel/quotations/{quotation}/chemical",
     *     summary="Update step 9 (Chemical/Bahan Kimia)",
     *     tags={"Admin Panel"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="quotation",
     *         in="path",
     *         required=true,
     *         description="ID Quotation",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data chemical/bahan kimia",
     *         @OA\JsonContent(
     *             required={"chemicals"},
     *             @OA\Property(
     *                 property="chemicals",
     *                 type="array",
     *                 description="Array data chemical",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"barang_id", "jumlah"},
     *                     @OA\Property(property="barang_id", type="integer", example=15),
     *                     @OA\Property(property="jumlah", type="integer", example=10),
     *                     @OA\Property(property="harga", type="number", example=250000),
     *                     @OA\Property(property="masa_pakai", type="integer", example=6, description="Masa pakai dalam bulan")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Step 9 berhasil diupdate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Step 9 berhasil diupdate"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function updateStep9(Request $request, Quotation $quotation)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'chemicals' => 'required|array',
                'chemicals.*.barang_id' => 'required|integer|exists:m_barang,id',
                'chemicals.*.jumlah' => 'required|integer|min:0',
                'chemicals.*.harga' => 'nullable|numeric|min:0',
                'chemicals.*.masa_pakai' => 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()
                ], 400);
            }

            Log::info('Admin Panel - Update Step 9', [
                'quotation_id' => $quotation->id,
                'user_id' => auth()->id(),
                'chemicals_count' => count($request->chemicals)
            ]);

            // Panggil service untuk update step 9
            $this->quotationStepService->updateStep9($quotation, $request);

            return response()->json([
                'success' => true,
                'message' => 'Step 9 berhasil diupdate',
                'data' => [
                    'quotation_id' => $quotation->id,
                    'chemicals_items' => count($request->chemicals)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin Panel - Error update step 9', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal update step 9: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/admin-panel/quotations/{quotation}/ohc",
     *     summary="Update step 10 (OHC, Training, dan Kunjungan)",
     *     tags={"Admin Panel"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="quotation",
     *         in="path",
     *         required=true,
     *         description="ID Quotation",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data OHC, training, dan kunjungan",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="ohcs",
     *                 type="array",
     *                 description="Array data OHC",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"barang_id", "jumlah"},
     *                     @OA\Property(property="barang_id", type="integer", example=6),
     *                     @OA\Property(property="jumlah", type="integer", example=1),
     *                     @OA\Property(property="harga", type="number", example=1000000),
     *                     @OA\Property(property="masa_pakai", type="integer", example=12)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="quotation_trainings",
     *                 type="array",
     *                 description="Array ID training yang dipilih",
     *                 @OA\Items(type="integer", example=1)
     *             ),
     *             @OA\Property(property="jumlah_kunjungan_operasional", type="integer", example=2),
     *             @OA\Property(property="bulan_tahun_kunjungan_operasional", type="string", example="Bulan"),
     *             @OA\Property(property="keterangan_kunjungan_operasional", type="string", example="Kunjungan rutin bulanan"),
     *             @OA\Property(property="jumlah_kunjungan_tim_crm", type="integer", example=1),
     *             @OA\Property(property="bulan_tahun_kunjungan_tim_crm", type="string", example="Tahun"),
     *             @OA\Property(property="keterangan_kunjungan_tim_crm", type="string", example="Kunjungan tahunan"),
     *             @OA\Property(property="training", type="string", example="Training dasar"),
     *             @OA\Property(property="persen_bunga_bank", type="number", example=1.3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Step 10 berhasil diupdate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Step 10 berhasil diupdate"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function updateStep10(Request $request, Quotation $quotation)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'ohcs' => 'nullable|array',
                'ohcs.*.barang_id' => 'required_with:ohcs|integer|exists:m_barang,id',
                'ohcs.*.jumlah' => 'required_with:ohcs|integer|min:0',
                'ohcs.*.harga' => 'nullable|numeric|min:0',
                'ohcs.*.masa_pakai' => 'nullable|integer|min:1',
                'quotation_trainings' => 'nullable|array',
                'quotation_trainings.*' => 'integer|exists:m_training,id',
                'jumlah_kunjungan_operasional' => 'nullable|integer|min:0',
                'bulan_tahun_kunjungan_operasional' => 'nullable|string|in:Bulan,Tahun',
                'keterangan_kunjungan_operasional' => 'nullable|string|max:500',
                'jumlah_kunjungan_tim_crm' => 'nullable|integer|min:0',
                'bulan_tahun_kunjungan_tim_crm' => 'nullable|string|in:Bulan,Tahun',
                'keterangan_kunjungan_tim_crm' => 'nullable|string|max:500',
                'training' => 'nullable|string|max:500',
                'persen_bunga_bank' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()
                ], 400);
            }

            Log::info('Admin Panel - Update Step 10', [
                'quotation_id' => $quotation->id,
                'user_id' => auth()->id(),
                'ohc_count' => $request->has('ohcs') ? count($request->ohcs) : 0,
                'training_count' => $request->has('quotation_trainings') ? count($request->quotation_trainings) : 0
            ]);

            // Panggil service untuk update step 10
            $this->quotationStepService->updateStep10($quotation, $request);

            return response()->json([
                'success' => true,
                'message' => 'Step 10 berhasil diupdate',
                'data' => [
                    'quotation_id' => $quotation->id,
                    'ohc_items' => $request->has('ohcs') ? count($request->ohcs) : 0,
                    'training_selected' => $request->has('quotation_trainings') ? count($request->quotation_trainings) : 0
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin Panel - Error update step 10', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal update step 10: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/admin-panel/quotations/{quotation}/harga-jual",
     *     summary="Update step 11 (Penagihan, HPP, COSS, dan Tunjangan)",
     *     tags={"Admin Panel"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="quotation",
     *         in="path",
     *         required=true,
     *         description="ID Quotation",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data penagihan, HPP, COSS, dan tunjangan",
     *         @OA\JsonContent(
     *             required={"penagihan"},
     *             @OA\Property(property="penagihan", type="string", example="Bulanan", description="Metode penagihan"),
     *             @OA\Property(
     *                 property="hpp_data",
     *                 type="object",
     *                 description="Data HPP per quotation detail",
     *                 additionalProperties={
     *                     "type": "object",
     *                     "properties": {
     *                         "thr": {"type": "number", "example": 450000},
     *                         "kompensasi": {"type": "number", "example": 300000},
     *                         "persen_insentif": {"type": "number", "example": 5}
     *                     }
     *                 }
     *             ),
     *             @OA\Property(
     *                 property="coss_data",
     *                 type="object",
     *                 description="Data COSS per quotation detail",
     *                 additionalProperties={
     *                     "type": "object",
     *                     "properties": {
     *                         "provisi_seragam": {"type": "number", "example": 250000},
     *                         "provisi_peralatan": {"type": "number", "example": 150000},
     *                         "provisi_chemical": {"type": "number", "example": 100000},
     *                         "provisi_ohc": {"type": "number", "example": 50000}
     *                     }
     *                 }
     *             ),
     *             @OA\Property(property="persen_insentif", type="number", example=5, description="Persentase insentif global"),
     *             @OA\Property(
     *                 property="tunjangan_data",
     *                 type="object",
     *                 description="Data tunjangan per quotation detail",
     *                 additionalProperties={
     *                     "type": "array",
     *                     "items": {
     *                         "type": "object",
     *                         "properties": {
     *                             "nama_tunjangan": {"type": "string", "example": "Tunjangan Transport"},
     *                             "nominal": {"type": "number", "example": 300000}
     *                         }
     *                     }
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Step 11 berhasil diupdate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Step 11 berhasil diupdate"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function updateStep11(Request $request, Quotation $quotation)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'penagihan' => 'required|string|max:100',
                'hpp_data' => 'nullable|array',
                'hpp_data.*.thr' => 'nullable|numeric|min:0',
                'hpp_data.*.kompensasi' => 'nullable|numeric|min:0',
                'hpp_data.*.persen_insentif' => 'nullable|numeric|min:0|max:100',
                'coss_data' => 'nullable|array',
                'coss_data.*.provisi_seragam' => 'nullable|numeric|min:0',
                'coss_data.*.provisi_peralatan' => 'nullable|numeric|min:0',
                'coss_data.*.provisi_chemical' => 'nullable|numeric|min:0',
                'coss_data.*.provisi_ohc' => 'nullable|numeric|min:0',
                'persen_insentif' => 'nullable|numeric|min:0|max:100',
                'tunjangan_data' => 'nullable|array',
                'tunjangan_data.*' => 'array',
                'tunjangan_data.*.*.nama_tunjangan' => 'required_with:tunjangan_data.*|string|max:255',
                'tunjangan_data.*.*.nominal' => 'required_with:tunjangan_data.*|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()
                ], 400);
            }

            Log::info('Admin Panel - Update Step 11', [
                'quotation_id' => $quotation->id,
                'user_id' => auth()->id(),
                'has_hpp_data' => $request->has('hpp_data'),
                'has_coss_data' => $request->has('coss_data'),
                'has_tunjangan_data' => $request->has('tunjangan_data')
            ]);

            // Panggil service untuk update step 11
            $this->quotationStepService->updateStep11($quotation, $request);

            return response()->json([
                'success' => true,
                'message' => 'Step 11 berhasil diupdate',
                'data' => [
                    'quotation_id' => $quotation->id,
                    'penagihan' => $request->penagihan,
                    'hpp_updated' => $request->has('hpp_data'),
                    'coss_updated' => $request->has('coss_data'),
                    'tunjangan_updated' => $request->has('tunjangan_data')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin Panel - Error update step 11', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal update step 11: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin-panel/quotations/{quotation}/step-data/{step}",
     *     summary="Get data untuk step tertentu",
     *     tags={"Admin Panel"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="quotation",
     *         in="path",
     *         required=true,
     *         description="ID Quotation",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="step",
     *         in="path",
     *         required=true,
     *         description="Step number (3,7,8,9,10,11)",
     *         @OA\Schema(type="integer", enum={3,7,8,9,10,11})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data step berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Quotation tidak ditemukan"
     *     )
     * )
     */
    public function getStepData(Quotation $quotation, $step)
    {
        try {
            // Validasi step yang diperbolehkan
            $allowedSteps = [3, 7, 8, 9, 10, 11];
            if (!in_array($step, $allowedSteps)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Step tidak valid. Step yang diperbolehkan: ' . implode(', ', $allowedSteps)
                ], 400);
            }

            // Ambil relations untuk step ini
            $relations = $this->quotationStepService->getStepRelations($step);
            $quotation->load($relations);

            // Persiapkan data untuk step
            $stepData = $this->quotationStepService->prepareStepData($quotation, $step);

            return response()->json([
                'success' => true,
                'data' => $stepData
            ]);

        } catch (\Exception $e) {
            Log::error('Admin Panel - Error get step data', [
                'quotation_id' => $quotation->id,
                'step' => $step,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data step: ' . $e->getMessage()
            ], 500);
        }
    }
}