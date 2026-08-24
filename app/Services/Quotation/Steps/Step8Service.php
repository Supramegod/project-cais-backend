<?php

namespace App\Services\Quotation\Steps;

use App\Models\Quotation;
use App\Services\Quotation\QuotationBarangService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Step8Service
{
    public function __construct(
        protected QuotationBarangService $quotationBarangService,
    ) {}

    public function execute(Quotation $quotation, Request $request): void
    {
        DB::beginTransaction();
        try {
            $barangData = [];

            if ($request->has('devices') && is_array($request->devices)) {
                $barangData = $request->devices;
            } else {
                $barangData = $this->quotationBarangService->processLegacyFormat($quotation, $request, 'devices');
            }

            $syncResult = $this->quotationBarangService->syncBarangData($quotation, 'devices', $barangData);

            Log::info('Devices Sync Result', $syncResult);

            $quotation->update([
                'updated_by' => Auth::user()->full_name,
                'calculated_at' => null,
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating step 8', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
