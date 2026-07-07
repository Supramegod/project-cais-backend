<?php

namespace App\Services\Steps;

use App\Models\Quotation;
use App\Services\Steps\Traits\StepHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Step1Service
{
    use StepHelperTrait;

    public function execute(Quotation $quotation, Request $request): void
    {
        Log::info('Updating Step 1', [
            'quotation_id' => $quotation->id,
            'current_jenis_kontrak' => $quotation->jenis_kontrak,
            'new_jenis_kontrak' => $request->jenis_kontrak,
            'user' => Auth::user()->full_name,
        ]);

        try {
            $quotation->jenis_kontrak = $request->jenis_kontrak;
            $quotation->updated_by = Auth::user()->full_name;
            $quotation->calculated_at = null;

            $saved = $quotation->save();

            Log::info('Save result', [
                'success' => $saved,
                'changes' => $quotation->getChanges(),
                'dirty' => $quotation->getDirty(),
            ]);

            if (! $saved) {
                throw new \Exception('Failed to save quotation step 1');
            }

            $updatedQuotation = Quotation::find($quotation->id);
            Log::info('Database verification', [
                'jenis_kontrak_in_db' => $updatedQuotation->jenis_kontrak,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in updateStep1', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
