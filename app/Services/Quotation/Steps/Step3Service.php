<?php

namespace App\Services\Quotation\Steps;

use App\Models\Quotation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Step3Service
{
    public function __construct(
        protected StepQuotationDetailService $quotationDetailService,
    ) {}

    public function execute(Quotation $quotation, Request $request): void
    {
        DB::beginTransaction();
        try {
            $currentDateTime = Carbon::now()->toDateTimeString();
            $user = Auth::user()->full_name;

            $dataToProcess = null;

            if ($request->has('headCountData') && is_array($request->headCountData)) {
                $dataToProcess = $request->headCountData;
            } elseif ($request->has('position_id') && $request->has('quotation_site_id')) {
                $dataToProcess = [
                    [
                        'quotation_site_id' => $request->quotation_site_id,
                        'position_id' => $request->position_id,
                        'jumlah_hc' => $request->jumlah_hc ?? 0,
                        'jabatan_kebutuhan' => $request->jabatan_kebutuhan ?? null,
                        'nama_site' => $request->nama_site ?? null,
                    ],
                ];
            } else {
                $dataToProcess = [];
            }

            if (empty($dataToProcess)) {
                $this->quotationDetailService->softDeleteAllQuotationDetails($quotation, $currentDateTime, $user);
            } else {
                $this->quotationDetailService->syncDetailHCFromArray($quotation, $dataToProcess, $currentDateTime, $user);
            }

            $quotation->update([
                'updated_by' => $user,
                'updated_at' => $currentDateTime,
                'calculated_at' => null,
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in updateStep3', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
