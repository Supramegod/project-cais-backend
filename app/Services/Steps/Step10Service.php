<?php

namespace App\Services\Steps;

use App\Models\Quotation;
use App\Models\QuotationTraining;
use App\Models\Training;
use App\Services\QuotationBarangService;
use App\Services\Steps\Traits\StepHelperTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Step10Service
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

            if ($request->has('ohcs') && is_array($request->ohcs)) {
                $barangData = $request->ohcs;
            } else {
                $barangData = $this->quotationBarangService->processLegacyFormat($quotation, $request, 'ohc');
            }

            // Sync barang data dengan approach baru
            $syncResult = $this->quotationBarangService->syncBarangData($quotation, 'ohc', $barangData);

            Log::info('OHC Sync Result', $syncResult);

            // Handle training data dari quotation_trainings
            if ($request->has('quotation_trainings') && is_array($request->quotation_trainings)) {
                $this->updateTrainingDataFromArray($quotation, $request->quotation_trainings, Carbon::now());
            } else {
                // Jika tidak ada training data, hapus semua training yang ada
                $this->clearAllTrainingData($quotation, Carbon::now());
            }

            // Update data kunjungan
            $quotation->update([
                'kunjungan_operasional' => $request->jumlah_kunjungan_operasional.' '.$request->bulan_tahun_kunjungan_operasional,
                'kunjungan_tim_crm' => $request->jumlah_kunjungan_tim_crm.' '.$request->bulan_tahun_kunjungan_tim_crm,
                'keterangan_kunjungan_operasional' => $request->keterangan_kunjungan_operasional,
                'keterangan_kunjungan_tim_crm' => $request->keterangan_kunjungan_tim_crm,
                'training' => $request->training,
                'calculated_at' => null,
                'updated_by' => Auth::user()->full_name,
            ]);

            DB::commit();

            Log::info('Step 10 updated successfully', [
                'quotation_id' => $quotation->id,
                'ohc_items' => count($barangData),
                'training_count' => $request->has('quotation_trainings') ? count($request->quotation_trainings) : 0,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating step 10', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function updateTrainingDataFromArray(Quotation $quotation, array $trainingIds, Carbon $currentDateTime): void
    {
        $user = Auth::user()->full_name;

        Log::info('Updating training data from array', [
            'quotation_id' => $quotation->id,
            'training_ids' => $trainingIds,
            'count' => count($trainingIds),
        ]);

        $existingTrainingIds = QuotationTraining::where('quotation_id', $quotation->id)
            ->whereNull('deleted_at')
            ->pluck('training_id')
            ->toArray();

        $trainingIdsToDelete = array_diff($existingTrainingIds, $trainingIds);
        $trainingIdsToAdd = array_diff($trainingIds, $existingTrainingIds);

        if (! empty($trainingIdsToDelete)) {
            QuotationTraining::where('quotation_id', $quotation->id)
                ->whereIn('training_id', $trainingIdsToDelete)
                ->update([
                    'deleted_at' => $currentDateTime,
                    'deleted_by' => $user,
                ]);

            Log::info('Deleted training associations', [
                'quotation_id' => $quotation->id,
                'deleted_training_ids' => $trainingIdsToDelete,
            ]);
        }

        foreach ($trainingIdsToAdd as $trainingId) {
            $training = Training::find($trainingId);
            if ($training) {
                QuotationTraining::create([
                    'training_id' => $trainingId,
                    'quotation_id' => $quotation->id,
                    'nama' => $training->nama,
                    'harga' => $training->harga,
                    'created_by' => $user,
                    'created_by_user_id' => Auth::id(),
                ]);
            }
        }

        Log::info('Added training associations', [
            'quotation_id' => $quotation->id,
            'added_training_ids' => $trainingIdsToAdd,
        ]);
    }

    private function clearAllTrainingData(Quotation $quotation, Carbon $currentDateTime): void
    {
        $user = Auth::user()->full_name;

        QuotationTraining::where('quotation_id', $quotation->id)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $currentDateTime,
                'deleted_by' => $user,
            ]);

        Log::info('Cleared all training data', [
            'quotation_id' => $quotation->id,
        ]);
    }
}
