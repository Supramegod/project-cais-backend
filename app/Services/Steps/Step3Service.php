<?php

namespace App\Services\Steps;

use App\Models\Quotation;
use App\Services\Steps\Traits\StepHelperTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Step3Service
{
    use StepHelperTrait;

    public function execute(Quotation $quotation, Request $request): void
    {
        DB::beginTransaction();
        try {
            $currentDateTime = Carbon::now()->toDateTimeString();
            $user = Auth::user()->full_name;

            // ============================================================
            // DETERMINE DATA TO PROCESS
            // ============================================================

            $dataToProcess = null;

            // CASE 1: headCountData exists (bulk format)
            if ($request->has('headCountData') && is_array($request->headCountData)) {
                $dataToProcess = $request->headCountData;
            }
            // CASE 2: Legacy single data format
            elseif ($request->has('position_id') && $request->has('quotation_site_id')) {
                $dataToProcess = [
                    [
                        'quotation_site_id' => $request->quotation_site_id,
                        'position_id' => $request->position_id,
                        'jumlah_hc' => $request->jumlah_hc ?? 0,
                        'jabatan_kebutuhan' => $request->jabatan_kebutuhan ?? null,
                        'nama_site' => $request->nama_site ?? null,
                    ],
                ];
            }
            // CASE 3: No data sent - will delete all
            else {
                $dataToProcess = [];
            }

            // ============================================================
            // PROCESS DATA
            // ============================================================

            if (empty($dataToProcess)) {
                // Delete all existing details
                $this->softDeleteAllQuotationDetails($quotation, $currentDateTime, $user);
            } else {
                // Sync data (will handle create/update/delete)
                $this->syncDetailHCFromArray($quotation, $dataToProcess, $currentDateTime, $user);
            }

            // Update quotation timestamp
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
