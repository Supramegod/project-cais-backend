<?php

namespace App\Services\Quotation\Steps;

use App\Models\Quotation;
use App\Services\Quotation\QuotationBarangService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Step9Service
{
    public function __construct(
        protected QuotationBarangService $quotationBarangService,
    ) {}

    public function execute(Quotation $quotation, Request $request): void
    {
        DB::beginTransaction();
        try {
            $barangData = [];

            if ($request->has('chemicals') && is_array($request->chemicals)) {
                $barangData = $request->chemicals;
            } elseif ($request->has('barang_id') && $request->has('jumlah')) {
                $barangData = [
                    [
                        'barang_id' => $request->barang_id,
                        'jumlah' => $request->jumlah,
                        'masa_pakai' => $request->masa_pakai,
                        'harga' => $request->harga,
                    ],
                ];
            } else {
                $barangData = $this->quotationBarangService->processLegacyFormat($quotation, $request, 'chemicals');
            }

            $syncResult = $this->quotationBarangService->syncBarangData($quotation, 'chemicals', $barangData);

            Log::info('Chemicals Sync Result', $syncResult);

            $quotation->update([
                'updated_by' => Auth::user()->full_name,
                'calculated_at' => null,
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating step 9', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
