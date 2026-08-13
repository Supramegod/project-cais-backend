<?php

namespace App\Services\Spk;

use App\Models\CustomerActivity;
use App\Models\Leads;
use App\Models\Spk;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Handles SPK file upload, storage, and status updates.
 */
class SpkUploadService
{
    use SpkActivityTrait;

    /**
     * Upload file SPK.
     */
    public function uploadSpk(int $id, $file): array
    {
        return DB::transaction(function () use ($id, $file) {
            $spk = Spk::find($id);

            if (! $spk) {
                throw new \Exception('SPK not found');
            }

            // Hapus file lama jika ada
            if ($spk->link_spk_disetujui) {
                $oldFileName = basename($spk->link_spk_disetujui);
                if (Storage::disk('spk')->exists($oldFileName)) {
                    Storage::disk('spk')->delete($oldFileName);
                }
            }

            // Upload file baru
            $fileName = $this->storeSpkFile($file);
            $fileUrl = url('document/spk/'.$fileName);

            Log::info('Generated URL: '.$fileUrl);
            Log::info('Filename: '.$fileName);

            $spk->update([
                'status_spk_id' => 2,
                'link_spk_disetujui' => $fileUrl,
                'updated_by' => Auth::user()->full_name,
            ]);

            // Catat aktivitas
            $this->createUploadActivity($spk);

            $spk->load(['statusSpk']);

            return [
                'id' => $spk->id,
                'nomor' => $spk->nomor,
                'status_spk_id' => $spk->status_spk_id,
                'status' => $spk->statusSpk->nama ?? null,
                'link_spk_disetujui' => $spk->link_spk_disetujui,
            ];
        });
    }

    private function storeSpkFile($file): string
    {
        $fileExtension = $file->getClientOriginalExtension();
        $originalFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $fileName = $originalFileName.date('YmdHis').rand(10000, 99999).'.'.$fileExtension;

        Storage::disk('spk')->put($fileName, file_get_contents($file));

        return $fileName;
    }

    private function createUploadActivity($spk): void
    {
        $leads = Leads::find($spk->leads_id);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'spk_id' => $spk->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $this->generateActivityNomor($leads->id),
            'tipe' => 'SPK',
            'notes' => 'SPK dengan nomor : '.$spk->nomor.' telah diupload dan disetujui',
            'is_activity' => 0,
            'user_id' => Auth::user()->id,
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::user()->id,
        ]);

        if ($leads) {
            $leads->tgl_leads = Carbon::now()->toDateString();
            $leads->save();
        }
    }
}
