<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalaryRuleRequest;
use App\Models\SalaryRule;
use Illuminate\Support\Facades\Auth;

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
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function list()
    {
        $data = SalaryRule::all();

        return $this->successResponse($data);
    }

    /**
     * @OA\Post(
     *     path="/api/salary-rule/add",
     *     summary="Create a new salary rule",
     *     tags={"Salary Rule"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=201, description="Salary rule created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function add(SalaryRuleRequest $request)
    {
        $salaryRule = SalaryRule::create($this->buildAttributes($request) + [
            'created_by' => Auth::user()->full_name ?? 'System',
            'created_by_user_id' => Auth::id(),
        ]);

        return $this->successResponse($salaryRule, 'Salary Rule berhasil dibuat', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/salary-rule/view/{id}",
     *     summary="Get salary rule by ID",
     *     tags={"Salary Rule"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Salary rule not found")
     * )
     */
    public function view($id)
    {
        $salaryRule = SalaryRule::find($id);

        if (! $salaryRule) {
            return $this->notFoundResponse('Salary Rule tidak ditemukan');
        }

        return $this->successResponse($salaryRule);
    }

    /**
     * @OA\Put(
     *     path="/api/salary-rule/update/{id}",
     *     summary="Update salary rule",
     *     tags={"Salary Rule"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Salary rule updated successfully"),
     *     @OA\Response(response=404, description="Salary rule not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(SalaryRuleRequest $request, $id)
    {
        $salaryRule = SalaryRule::find($id);

        if (! $salaryRule) {
            return $this->notFoundResponse('Salary Rule tidak ditemukan');
        }

        $salaryRule->update($this->buildAttributes($request) + [
            'updated_by' => Auth::user()->full_name ?? 'System',
        ]);

        return $this->successResponse($salaryRule, 'Salary Rule berhasil diupdate');
    }

    /**
     * @OA\Delete(
     *     path="/api/salary-rule/delete/{id}",
     *     summary="Delete salary rule",
     *     tags={"Salary Rule"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Salary rule deleted successfully"),
     *     @OA\Response(response=404, description="Salary rule not found")
     * )
     */
    public function delete($id)
    {
        $salaryRule = SalaryRule::find($id);

        if (! $salaryRule) {
            return $this->notFoundResponse('Salary Rule tidak ditemukan');
        }

        $salaryRule->update([
            'deleted_by' => Auth::user()->full_name ?? 'System',
        ]);
        $salaryRule->delete();

        return $this->messageResponse('Salary Rule berhasil dihapus');
    }

    /**
     * Build the persisted attributes (descriptive strings + raw dates)
     * shared by add() and update().
     */
    private function buildAttributes(SalaryRuleRequest $request): array
    {
        return [
            'nama_salary_rule' => $request->nama_salary_rule,
            'cutoff' => 'Tanggal '.$request->cutoff_awal.' - '.$request->cutoff_akhir,
            'cutoff_awal' => $request->cutoff_awal,
            'cutoff_akhir' => $request->cutoff_akhir,
            'crosscheck_absen' => 'Tanggal '.$request->crosscheck_absen_awal.' - '.$request->crosscheck_absen_akhir,
            'crosscheck_absen_awal' => $request->crosscheck_absen_awal,
            'crosscheck_absen_akhir' => $request->crosscheck_absen_akhir,
            'pengiriman_invoice' => 'Tanggal '.$request->pengiriman_invoice_awal.' - '.$request->pengiriman_invoice_akhir,
            'pengiriman_invoice_awal' => $request->pengiriman_invoice_awal,
            'pengiriman_invoice_akhir' => $request->pengiriman_invoice_akhir,
            'perkiraan_invoice_diterima' => 'Tanggal '.$request->perkiraan_invoice_diterima_awal.' - '.$request->perkiraan_invoice_diterima_akhir,
            'perkiraan_invoice_diterima_awal' => $request->perkiraan_invoice_diterima_awal,
            'perkiraan_invoice_diterima_akhir' => $request->perkiraan_invoice_diterima_akhir,
            'pembayaran_invoice' => 'Tanggal '.$request->pembayaran_invoice.' bulan berikutnya',
            'tgl_pembayaran_invoice' => $request->pembayaran_invoice,
            'rilis_payroll' => 'Tanggal '.$request->rilis_payroll.' bulan berikutnya',
            'tgl_rilis_payroll' => $request->rilis_payroll,
        ];
    }
}
