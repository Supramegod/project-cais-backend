<?php

namespace App\Services\Pks;

use App\Models\CustomerActivity;
use App\Models\Leads;
use App\Models\Pks;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PksUploadService
{
    public function __construct(
        private PksQueryService $queryService,
        private PksHelperService $helperService
    ) {}

    public function processUploadPks($id, $file): array
    {
        $pks = Pks::with('leads:id,nomor,kebutuhan_id,branch_id')->find($id);
        if (!$pks) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('PKS not found');
        }

        if (!$this->queryService->isWizardFinalized($pks)) {
            throw new \RuntimeException('PKS wizard belum finalized');
        }

        return DB::transaction(function () use ($pks, $file) {
            $fileName = null;

            try {
                if ($pks->link_pks_disetujui) {
                    $oldFileName = basename($pks->link_pks_disetujui);
                    if (Storage::disk('pks')->exists($oldFileName)) {
                        Storage::disk('pks')->delete($oldFileName);
                    }
                }

                $fileName = $this->storePksFile($file);
                $fileUrl = url('document/pks/' . $fileName);

                $pks->update([
                    'status_pks_id' => 6,
                    'link_pks_disetujui' => $fileUrl,
                    'updated_at' => now(),
                    'updated_by' => Auth::user()->full_name,
                ]);

                $leads = $pks->leads;
                if (!$leads) {
                    $leads = Leads::find($pks->leads_id);
                }

                $this->createUploadPksActivity($pks, $leads);

                $pks->load(['statusPks']);

                return [
                    'id' => $pks->id,
                    'nomor' => $pks->nomor,
                    'status_pks_id' => $pks->status_pks_id,
                    'status' => $pks->statusPks->nama ?? null,
                    'link_pks_disetujui' => $pks->link_pks_disetujui,
                ];
            } catch (\Throwable $e) {
                if ($fileName && Storage::disk('pks')->exists($fileName)) {
                    Storage::disk('pks')->delete($fileName);
                }
                throw $e;
            }
        });
    }

    public function storePksFile($file): string
    {
        $fileExtension = $file->getClientOriginalExtension();
        $originalFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $fileName = $originalFileName . date('YmdHis') . rand(10000, 99999) . '.' . $fileExtension;

        Storage::disk('pks')->put($fileName, file_get_contents($file));

        return $fileName;
    }

    public function createUploadPksActivity($pks, Leads $leads): void
    {
        $nomorActivity = $this->helperService->generateNomorActivity($leads);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'pks_id' => $pks->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $nomorActivity,
            'tipe' => 'PKS',
            'notes' => 'PKS dengan nomor : ' . $pks->nomor . ' telah diupload dan disetujui',
            'is_activity' => 0,
            'user_id' => Auth::id(),
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::id(),
        ]);

        if ($leads) {
            $leads->tgl_leads = Carbon::now()->toDateString();
            $leads->save();
        }
    }
}
