<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SalaryRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Salary Rule",
 *     description="API Endpoints untuk Management Salary Rule"
 * )
 */
class SalaryRuleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/salary-rule/list",
     *     summary="Get list of salary rules",
     *     tags={"Salary Rule"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama_salary_rule", type="string", example="Salary Rule January 2024"),
     *                         @OA\Property(property="cutoff", type="string", example="Tanggal 1 - 15"),
     *                         @OA\Property(property="cutoff_awal", type="integer", example=1),
     *                         @OA\Property(property="cutoff_akhir", type="integer", example=15),
     *                         @OA\Property(property="crosscheck_absen", type="string", example="Tanggal 16 - 20"),
     *                         @OA\Property(property="crosscheck_absen_awal", type="integer", example=16),
     *                         @OA\Property(property="crosscheck_absen_akhir", type="integer", example=20),
     *                         @OA\Property(property="pengiriman_invoice", type="string", example="Tanggal 21 - 25"),
     *                         @OA\Property(property="pengiriman_invoice_awal", type="integer", example=21),
     *                         @OA\Property(property="pengiriman_invoice_akhir", type="integer", example=25),
     *                         @OA\Property(property="perkiraan_invoice_diterima", type="string", example="Tanggal 26 - 30"),
     *                         @OA\Property(property="perkiraan_invoice_diterima_awal", type="integer", example=26),
     *                         @OA\Property(property="perkiraan_invoice_diterima_akhir", type="integer", example=30),
     *                         @OA\Property(property="pembayaran_invoice", type="string", example="Tanggal 5 bulan berikutnya"),
     *                         @OA\Property(property="tgl_pembayaran_invoice", type="integer", example=5),
     *                         @OA\Property(property="rilis_payroll", type="string", example="Tanggal 10 bulan berikutnya"),
     *                         @OA\Property(property="tgl_rilis_payroll", type="integer", example=10),
     *                         @OA\Property(property="created_by", type="string", example="John Doe"),
     *                         @OA\Property(property="created_at", type="string", format="date-time")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function list(Request $request)
    {
        try {
            $data = SalaryRule::all();

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/salary-rule/add",
     *     summary="Create a new salary rule",
     *     tags={"Salary Rule"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={
     *                 "nama_salary_rule",
     *                 "cutoff_awal",
     *                 "cutoff_akhir",
     *                 "crosscheck_absen_awal",
     *                 "crosscheck_absen_akhir",
     *                 "pengiriman_invoice_awal",
     *                 "pengiriman_invoice_akhir",
     *                 "perkiraan_invoice_diterima_awal",
     *                 "perkiraan_invoice_diterima_akhir",
     *                 "pembayaran_invoice",
     *                 "rilis_payroll"
     *             },
     *             @OA\Property(property="nama_salary_rule", type="string", example="Salary Rule January 2024"),
     *             @OA\Property(property="cutoff_awal", type="integer", example=1, description="Tanggal awal cutoff"),
     *             @OA\Property(property="cutoff_akhir", type="integer", example=15, description="Tanggal akhir cutoff"),
     *             @OA\Property(property="crosscheck_absen_awal", type="integer", example=16, description="Tanggal awal crosscheck absen"),
     *             @OA\Property(property="crosscheck_absen_akhir", type="integer", example=20, description="Tanggal akhir crosscheck absen"),
     *             @OA\Property(property="pengiriman_invoice_awal", type="integer", example=21, description="Tanggal awal pengiriman invoice"),
     *             @OA\Property(property="pengiriman_invoice_akhir", type="integer", example=25, description="Tanggal akhir pengiriman invoice"),
     *             @OA\Property(property="perkiraan_invoice_diterima_awal", type="integer", example=26, description="Tanggal awal perkiraan invoice diterima"),
     *             @OA\Property(property="perkiraan_invoice_diterima_akhir", type="integer", example=30, description="Tanggal akhir perkiraan invoice diterima"),
     *             @OA\Property(property="pembayaran_invoice", type="integer", example=5, description="Tanggal pembayaran invoice bulan berikutnya"),
     *             @OA\Property(property="rilis_payroll", type="integer", example=10, description="Tanggal rilis payroll bulan berikutnya")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Salary rule created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Salary Rule berhasil dibuat"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function add(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama_salary_rule' => 'required|string|max:255|unique:m_salary_rule,nama_salary_rule',
                'cutoff_awal' => 'required|integer|min:1|max:31',
                'cutoff_akhir' => 'required|integer|min:1|max:31',
                'crosscheck_absen_awal' => 'required|integer|min:1|max:31',
                'crosscheck_absen_akhir' => 'required|integer|min:1|max:31',
                'pengiriman_invoice_awal' => 'required|integer|min:1|max:31',
                'pengiriman_invoice_akhir' => 'required|integer|min:1|max:31',
                'perkiraan_invoice_diterima_awal' => 'required|integer|min:1|max:31',
                'perkiraan_invoice_diterima_akhir' => 'required|integer|min:1|max:31',
                'pembayaran_invoice' => 'required|integer|min:1|max:31',
                'rilis_payroll' => 'required|integer|min:1|max:31',
            ], [
                'required' => ':attribute harus diisi',
                'integer' => ':attribute harus berupa angka',
                'min' => ':attribute minimal :min',
                'max' => ':attribute maksimal :max',
                'unique' => ':attribute sudah ada'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Generate descriptive strings
            $cutoff = "Tanggal ".$request->cutoff_awal." - ".$request->cutoff_akhir;
            $crosscheckAbsen = "Tanggal ".$request->crosscheck_absen_awal." - ".$request->crosscheck_absen_akhir;
            $pengirimanInvoice = "Tanggal ".$request->pengiriman_invoice_awal." - ".$request->pengiriman_invoice_akhir;
            $perkiraanInvoiceDiterima = "Tanggal ".$request->perkiraan_invoice_diterima_awal." - ".$request->perkiraan_invoice_diterima_akhir;
            $pembayaranInvoice = "Tanggal ".$request->pembayaran_invoice." bulan berikutnya";
            $rilisPayroll = "Tanggal ".$request->rilis_payroll." bulan berikutnya";

            $salaryRule = SalaryRule::create([
                'nama_salary_rule' => $request->nama_salary_rule,
                'cutoff' => $cutoff,
                'cutoff_awal' => $request->cutoff_awal,
                'cutoff_akhir' => $request->cutoff_akhir,
                'crosscheck_absen' => $crosscheckAbsen,
                'crosscheck_absen_awal' => $request->crosscheck_absen_awal,
                'crosscheck_absen_akhir' => $request->crosscheck_absen_akhir,
                'pengiriman_invoice' => $pengirimanInvoice,
                'pengiriman_invoice_awal' => $request->pengiriman_invoice_awal,
                'pengiriman_invoice_akhir' => $request->pengiriman_invoice_akhir,
                'perkiraan_invoice_diterima' => $perkiraanInvoiceDiterima,
                'perkiraan_invoice_diterima_awal' => $request->perkiraan_invoice_diterima_awal,
                'perkiraan_invoice_diterima_akhir' => $request->perkiraan_invoice_diterima_akhir,
                'pembayaran_invoice' => $pembayaranInvoice,
                'tgl_pembayaran_invoice' => $request->pembayaran_invoice,
                'rilis_payroll' => $rilisPayroll,
                'tgl_rilis_payroll' => $request->rilis_payroll,
                'created_by' => Auth::user()->full_name ?? 'System'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Salary Rule berhasil dibuat',
                'data' => $salaryRule
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/salary-rule/view/{id}",
     *     summary="Get salary rule by ID",
     *     tags={"Salary Rule"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Salary Rule ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Salary rule not found"
     *     )
     * )
     */
    public function view($id)
    {
        try {
            $salaryRule = SalaryRule::find($id);

            if (!$salaryRule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Salary Rule tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $salaryRule
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/salary-rule/update/{id}",
     *     summary="Update salary rule",
     *     tags={"Salary Rule"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Salary Rule ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={
     *                 "nama_salary_rule",
     *                 "cutoff_awal",
     *                 "cutoff_akhir",
     *                 "crosscheck_absen_awal",
     *                 "crosscheck_absen_akhir",
     *                 "pengiriman_invoice_awal",
     *                 "pengiriman_invoice_akhir",
     *                 "perkiraan_invoice_diterima_awal",
     *                 "perkiraan_invoice_diterima_akhir",
     *                 "pembayaran_invoice",
     *                 "rilis_payroll"
     *             },
     *             @OA\Property(property="nama_salary_rule", type="string", example="Salary Rule Updated"),
     *             @OA\Property(property="cutoff_awal", type="integer", example=1),
     *             @OA\Property(property="cutoff_akhir", type="integer", example=15),
     *             @OA\Property(property="crosscheck_absen_awal", type="integer", example=16),
     *             @OA\Property(property="crosscheck_absen_akhir", type="integer", example=20),
     *             @OA\Property(property="pengiriman_invoice_awal", type="integer", example=21),
     *             @OA\Property(property="pengiriman_invoice_akhir", type="integer", example=25),
     *             @OA\Property(property="perkiraan_invoice_diterima_awal", type="integer", example=26),
     *             @OA\Property(property="perkiraan_invoice_diterima_akhir", type="integer", example=30),
     *             @OA\Property(property="pembayaran_invoice", type="integer", example=5),
     *             @OA\Property(property="rilis_payroll", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Salary rule updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Salary Rule berhasil diupdate"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Salary rule not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $salaryRule = SalaryRule::find($id);

            if (!$salaryRule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Salary Rule tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'nama_salary_rule' => 'required|string|max:255|unique:m_salary_rule,nama_salary_rule,' . $id,
                'cutoff_awal' => 'required|integer|min:1|max:31',
                'cutoff_akhir' => 'required|integer|min:1|max:31',
                'crosscheck_absen_awal' => 'required|integer|min:1|max:31',
                'crosscheck_absen_akhir' => 'required|integer|min:1|max:31',
                'pengiriman_invoice_awal' => 'required|integer|min:1|max:31',
                'pengiriman_invoice_akhir' => 'required|integer|min:1|max:31',
                'perkiraan_invoice_diterima_awal' => 'required|integer|min:1|max:31',
                'perkiraan_invoice_diterima_akhir' => 'required|integer|min:1|max:31',
                'pembayaran_invoice' => 'required|integer|min:1|max:31',
                'rilis_payroll' => 'required|integer|min:1|max:31',
            ], [
                'required' => ':attribute harus diisi',
                'integer' => ':attribute harus berupa angka',
                'min' => ':attribute minimal :min',
                'max' => ':attribute maksimal :max',
                'unique' => ':attribute sudah ada'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Generate descriptive strings
            $cutoff = "Tanggal ".$request->cutoff_awal." - ".$request->cutoff_akhir;
            $crosscheckAbsen = "Tanggal ".$request->crosscheck_absen_awal." - ".$request->crosscheck_absen_akhir;
            $pengirimanInvoice = "Tanggal ".$request->pengiriman_invoice_awal." - ".$request->pengiriman_invoice_akhir;
            $perkiraanInvoiceDiterima = "Tanggal ".$request->perkiraan_invoice_diterima_awal." - ".$request->perkiraan_invoice_diterima_akhir;
            $pembayaranInvoice = "Tanggal ".$request->pembayaran_invoice." bulan berikutnya";
            $rilisPayroll = "Tanggal ".$request->rilis_payroll." bulan berikutnya";

            $salaryRule->update([
                'nama_salary_rule' => $request->nama_salary_rule,
                'cutoff' => $cutoff,
                'cutoff_awal' => $request->cutoff_awal,
                'cutoff_akhir' => $request->cutoff_akhir,
                'crosscheck_absen' => $crosscheckAbsen,
                'crosscheck_absen_awal' => $request->crosscheck_absen_awal,
                'crosscheck_absen_akhir' => $request->crosscheck_absen_akhir,
                'pengiriman_invoice' => $pengirimanInvoice,
                'pengiriman_invoice_awal' => $request->pengiriman_invoice_awal,
                'pengiriman_invoice_akhir' => $request->pengiriman_invoice_akhir,
                'perkiraan_invoice_diterima' => $perkiraanInvoiceDiterima,
                'perkiraan_invoice_diterima_awal' => $request->perkiraan_invoice_diterima_awal,
                'perkiraan_invoice_diterima_akhir' => $request->perkiraan_invoice_diterima_akhir,
                'pembayaran_invoice' => $pembayaranInvoice,
                'tgl_pembayaran_invoice' => $request->pembayaran_invoice,
                'rilis_payroll' => $rilisPayroll,
                'tgl_rilis_payroll' => $request->rilis_payroll,
                'updated_by' => Auth::user()->full_name ?? 'System'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Salary Rule berhasil diupdate',
                'data' => $salaryRule
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/salary-rule/delete/{id}",
     *     summary="Delete salary rule",
     *     tags={"Salary Rule"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Salary Rule ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Salary rule deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Salary Rule berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Salary rule not found"
     *     )
     * )
     */
    public function delete($id)
    {
        try {
            $salaryRule = SalaryRule::find($id);

            if (!$salaryRule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Salary Rule tidak ditemukan'
                ], 404);
            }

            $salaryRule->update([
                'deleted_by' => Auth::user()->full_name ?? 'System'
            ]);
            $salaryRule->delete();

            return response()->json([
                'success' => true,
                'message' => 'Salary Rule berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/salary-rule/list-all",
     *     summary="Get all salary rules without pagination (optional)",
     *     tags={"Salary Rule"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_salary_rule", type="string", example="Salary Rule January 2024"),
     *                     @OA\Property(property="cutoff", type="string", example="Tanggal 1 - 15")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function listAll()
    {
        try {
            $data = SalaryRule::all(['id', 'nama_salary_rule', 'cutoff']);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }
}