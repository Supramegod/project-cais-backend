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
     * @OA\Post(
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
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Request tidak valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Data tidak valid"),
     *             @OA\Property(property="errors", type="object")
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
                    'message' => $validator->errors()
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
     * @OA\Post(
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
     * @OA\Post(
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
     *                     @OA\Property(property="harga", type="number", example=5000000)
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
     * @OA\Post(
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
     * @OA\Post(
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
     *                     @OA\Property(property="harga", type="number", example=1000000)
     *                 )
     *             )
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
            // $validator = Validator::make($request->all(), [
            //     'ohcs' => 'nullable|array',
            //     'ohcs.*.barang_id' => 'required_with:ohcs|integer|exists:m_barang,id',
            //     'ohcs.*.jumlah' => 'required_with:ohcs|integer|min:0',
            //     'ohcs.*.harga' => 'nullable|numeric|min:0',
            //     'quotation_trainings' => 'nullable|array',
            //     'quotation_trainings.*' => 'integer|exists:m_training,id',
            //     'jumlah_kunjungan_operasional' => 'nullable|integer|min:0',
            //     'bulan_tahun_kunjungan_operasional' => 'nullable|string|in:Bulan,Tahun',
            //     'keterangan_kunjungan_operasional' => 'nullable|string|max:500',
            //     'jumlah_kunjungan_tim_crm' => 'nullable|integer|min:0',
            //     'bulan_tahun_kunjungan_tim_crm' => 'nullable|string|in:Bulan,Tahun',
            //     'keterangan_kunjungan_tim_crm' => 'nullable|string|max:500',
            //     'training' => 'nullable|string|max:500',
            //     'persen_bunga_bank' => 'nullable|numeric|min:0',
            // ]);

            // if ($validator->fails()) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => $validator->errors()
            //     ], 400);
            // }

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
     * @OA\Post(
     *     path="/api/admin-panel/quotations/{quotation}/harga-jual",
     *     summary="Update step 11 (Penagihan, Management Fee, HPP, COSS, BPJS, Tunjangan, dan Nominal Upah)",
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
     *         description="Data lengkap untuk step 11 termasuk penagihan, management fee, HPP, COSS, BPJS, tunjangan, dan nominal upah",
     *         @OA\JsonContent(
     *             required={"penagihan"},
     *             @OA\Property(
     *                 property="penagihan",
     *                 type="string",
     *                 enum={"Transfer", "Dengan Pembulatan"},
     *                 example="Dengan Pembulatan",
     *                 description="Metode penagihan"
     *             ),
     *             @OA\Property(
     *                 property="persentase",
     *                 type="number",
     *                 format="float",
     *                 example=7.5,
     *                 description="Persentase management fee"
     *             ),
     *             @OA\Property(
     *                 property="persen_insentif",
     *                 type="number",
     *                 format="float",
     *                 example=20,
     *                 description="Persentase insentif global"
     *             ),
     *             @OA\Property(
     *                 property="persen_bunga_bank",
     *                 type="number",
     *                 format="float",
     *                 example=1.3,
     *                 description="Persentase bunga bank"
     *             ),
     *             @OA\Property(
     *                 property="note_harga_jual",
     *                 type="string",
     *                 example="<b>Upah pokok base on UMK 2024</b><br>Tunjangan overtime flat total 75 jam.",
     *                 description="Note atau catatan untuk harga jual (HTML format)"
     *             ),
     *             @OA\Property(
     *                 property="nominal_upah_data",
     *                 type="object",
     *                 description="Data nominal upah per quotation detail (key: quotation_detail_id, value: nominal upah)",
     *                 example={"8436": 5000000, "8437": 4800000},
     *                 additionalProperties={
     *                     "type": "number",
     *                     "format": "float"
     *                 }
     *             ),
     *             @OA\Property(
     *                 property="bpjs_persentase_data",
     *                 type="object",
     *                 description="Data persentase BPJS per quotation detail",
     *                 @OA\AdditionalProperties(
     *                     type="object",
     *                     @OA\Property(property="jkk", type="number", format="float", example=0.24, description="Persentase JKK"),
     *                     @OA\Property(property="jkm", type="number", format="float", example=0.3, description="Persentase JKM"),
     *                     @OA\Property(property="jht", type="number", format="float", example=3.7, description="Persentase JHT"),
     *                     @OA\Property(property="jp", type="number", format="float", example=2, description="Persentase JP"),
     *                     @OA\Property(property="kes", type="number", format="float", example=4, description="Persentase Kesehatan")
     *                 ),
     *                 example={
     *                     "8436": {
     *                         "jkk": 0.24,
     *                         "jkm": 0.3,
     *                         "jht": 3.7,
     *                         "jp": 2,
     *                         "kes": 4
     *                     }
     *                 }
     *             ),
     *             @OA\Property(
     *                 property="hpp_editable_data",
     *                 type="object",
     *                 description="Data HPP yang bisa diedit per quotation detail (THR dan Kompensasi yang diprovisikan)",
     *                 @OA\AdditionalProperties(
     *                     type="object",
     *                     @OA\Property(property="tunjangan_hari_raya", type="number", format="float", example=416666.67, description="THR (Tunjangan Hari Raya) - hanya jika diprovisikan"),
     *                     @OA\Property(property="kompensasi", type="number", format="float", example=100000, description="Kompensasi - hanya jika diprovisikan")
     *                 ),
     *                 example={
     *                     "8436": {
     *                         "tunjangan_hari_raya": 416666.67,
     *                         "kompensasi": 100000
     *                     }
     *                 }
     *             ),
     *             @OA\Property(
     *                 property="coss_data",
     *                 type="object",
     *                 description="Data COSS (provisi barang) per quotation detail",
     *                 @OA\AdditionalProperties(
     *                     type="object",
     *                     @OA\Property(property="provisi_seragam", type="number", format="float", example=180000, description="Provisi Seragam"),
     *                     @OA\Property(property="provisi_peralatan", type="number", format="float", example=220000, description="Provisi Peralatan"),
     *                     @OA\Property(property="provisi_chemical", type="number", format="float", example=120000, description="Provisi Chemical"),
     *                     @OA\Property(property="provisi_ohc", type="number", format="float", example=20000, description="Provisi OHC")
     *                 ),
     *                 example={
     *                     "8436": {
     *                         "provisi_seragam": 180000,
     *                         "provisi_peralatan": 220000,
     *                         "provisi_chemical": 120000,
     *                         "provisi_ohc": 20000
     *                     }
     *                 }
     *             ),
     *             @OA\Property(
     *                 property="tunjangan_data",
     *                 type="object",
     *                 description="Data tunjangan per quotation detail",
     *                 @OA\AdditionalProperties(
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         required={"nama_tunjangan", "nominal"},
     *                         @OA\Property(property="nama_tunjangan", type="string", example="JABATAN", description="Nama tunjangan"),
     *                         @OA\Property(property="nominal", type="number", format="float", example=20333, description="Nominal tunjangan")
     *                     )
     *                 ),
     *                 example={
     *                     "8436": {
     *                         {"nama_tunjangan": "JABATAN", "nominal": 20333},
     *                         {"nama_tunjangan": "TRANSPORT", "nominal": 50000}
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
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="quotation_id", type="integer", example=123),
     *                 @OA\Property(property="penagihan", type="string", example="Dengan Pembulatan"),
     *                 @OA\Property(property="persentase_updated", type="boolean", example=true),
     *                 @OA\Property(property="nominal_upah_updated", type="boolean", example=true),
     *                 @OA\Property(property="bpjs_persentase_updated", type="boolean", example=true),
     *                 @OA\Property(property="hpp_updated", type="boolean", example=true),
     *                 @OA\Property(property="coss_updated", type="boolean", example=true),
     *                 @OA\Property(property="tunjangan_updated", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="object", description="Error validasi")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Quotation tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Quotation tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error server",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Gagal update step 11: Error message")
     *         )
     *     )
     * )
     */
    public function updateStep11(Request $request, Quotation $quotation)
    {
        try {
            // Validasi input - DIPERBAIKI sesuai dengan nama field yang benar
            $validator = Validator::make($request->all(), [
                'penagihan' => 'required|string|max:100',
                'persentase' => 'nullable|numeric|min:0|max:100',
                'persen_insentif' => 'nullable|numeric|min:0|max:100',
                'persen_bunga_bank' => 'nullable|numeric|min:0|max:100',
                'note_harga_jual' => 'nullable|string',

                // Nominal upah data
                'nominal_upah_data' => 'nullable|array',
                'nominal_upah_data.*' => 'nullable|numeric|min:0',

                // BPJS persentase data
                'bpjs_persentase_data' => 'nullable|array',
                'bpjs_persentase_data.*.jkk' => 'nullable|numeric|min:0|max:100',
                'bpjs_persentase_data.*.jkm' => 'nullable|numeric|min:0|max:100',
                'bpjs_persentase_data.*.jht' => 'nullable|numeric|min:0|max:100',
                'bpjs_persentase_data.*.jp' => 'nullable|numeric|min:0|max:100',
                'bpjs_persentase_data.*.kes' => 'nullable|numeric|min:0|max:100',

                // HPP editable data (bukan hpp_data)
                'hpp_editable_data' => 'nullable|array',
                'hpp_editable_data.*.tunjangan_hari_raya' => 'nullable|numeric|min:0',
                'hpp_editable_data.*.kompensasi' => 'nullable|numeric|min:0',

                // COSS data
                'coss_data' => 'nullable|array',
                'coss_data.*.provisi_seragam' => 'nullable|numeric|min:0',
                'coss_data.*.provisi_peralatan' => 'nullable|numeric|min:0',
                'coss_data.*.provisi_chemical' => 'nullable|numeric|min:0',
                'coss_data.*.provisi_ohc' => 'nullable|numeric|min:0',

                // Tunjangan data
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
                'has_persentase' => $request->has('persentase'),
                'has_nominal_upah_data' => $request->has('nominal_upah_data'),
                'has_bpjs_persentase_data' => $request->has('bpjs_persentase_data'),
                'has_hpp_editable_data' => $request->has('hpp_editable_data'),
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
                    'persentase_updated' => $request->has('persentase'),
                    'nominal_upah_updated' => $request->has('nominal_upah_data'),
                    'bpjs_persentase_updated' => $request->has('bpjs_persentase_data'),
                    'hpp_updated' => $request->has('hpp_editable_data'),
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