<?php

namespace App\Services\Steps;

use App\Models\Quotation;
use App\Services\QuotationBarangService;
use App\Services\Steps\Traits\StepHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Step7Service
{
    use StepHelperTrait;

    public function __construct(
        protected QuotationBarangService $quotationBarangService,
    ) {}

    public function execute(Quotation $quotation, Request $request): void
    {
        DB::beginTransaction();
        try {
            $barangData = [];

            if ($request->has('kaporlaps') && is_array($request->kaporlaps)) {
                $barangData = $request->kaporlaps;
            } else {
                $barangData = $this->quotationBarangService->processLegacyFormat($quotation, $request, 'kaporlap');
            }

            // Sync barang data
            $syncResult = $this->quotationBarangService->syncBarangData($quotation, 'kaporlap', $barangData);

            Log::info('Kaporlap Sync Result', $syncResult);

            $quotation->update([
                'updated_by' => Auth::user()->full_name,
                'calculated_at' => null,
            ]);

            DB::commit();

            Log::info('Step 7 updated successfully', [
                'quotation_id' => $quotation->id,
                'kaporlap_items' => count($barangData),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating step 7', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
