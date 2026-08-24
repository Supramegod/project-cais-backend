<?php

namespace App\Services\Pks;

use App\Models\CustomerActivity;
use App\Models\Leads;
use App\Models\Pks;
use App\Models\PksPerjanjian;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PksPasalService
{
    public function __construct(
        private PksHelperService $helperService
    ) {}

    public function storePasal($pksId, array $data): PksPerjanjian
    {
        $pks = Pks::with('leads:id,nomor,kebutuhan_id,branch_id')->find($pksId);
        if (!$pks) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('PKS tidak ditemukan');
        }

        if (PksPerjanjian::where('pks_id', $pksId)
            ->where('pasal', $data['pasal'])
            ->exists()
        ) {
            throw new \InvalidArgumentException("Pasal '{$data['pasal']}' sudah ada dalam perjanjian PKS ini.");
        }

        return DB::transaction(function () use ($pks, $data) {
            $perjanjian = PksPerjanjian::create([
                'pks_id' => $pks->id,
                'pasal' => $data['pasal'],
                'judul' => $data['judul'],
                'raw_text' => $data['raw_text'],
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id(),
                'updated_by' => Auth::user()->full_name,
            ]);

            if ($pks->leads) {
                $nomorActivity = $this->helperService->generateNomorActivity($pks->leads);
                CustomerActivity::create([
                    'leads_id' => $pks->leads->id,
                    'pks_id' => $pks->id,
                    'branch_id' => $pks->leads->branch_id,
                    'tgl_activity' => now(),
                    'nomor' => $nomorActivity,
                    'tipe' => 'PKS_PERJANJIAN',
                    'notes' => "Pasal {$perjanjian->pasal} - {$perjanjian->judul} ditambahkan oleh " . Auth::user()->full_name,
                    'is_activity' => 0,
                    'user_id' => Auth::id(),
                    'created_by' => Auth::user()->full_name,
                    'created_by_user_id' => Auth::id(),
                ]);
            }

            return $perjanjian;
        });
    }

    public function deletePasal($id): void
    {
        $perjanjian = PksPerjanjian::select('id', 'pks_id', 'pasal', 'judul')->find($id);
        if (!$perjanjian) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Pasal tidak ditemukan');
        }

        $pks = Pks::with('leads:id,nomor,kebutuhan_id,branch_id')
            ->find($perjanjian->pks_id);

        DB::transaction(function () use ($perjanjian, $pks) {
            $perjanjian->delete();

            if ($pks && $pks->leads) {
                $nomorActivity = $this->helperService->generateNomorActivity($pks->leads);
                CustomerActivity::create([
                    'leads_id' => $pks->leads->id,
                    'pks_id' => $pks->id,
                    'branch_id' => $pks->leads->branch_id,
                    'tgl_activity' => now(),
                    'nomor' => $nomorActivity,
                    'tipe' => 'PKS_PERJANJIAN',
                    'notes' => "Pasal {$perjanjian->pasal} - {$perjanjian->judul} dihapus oleh " . Auth::user()->full_name,
                    'is_activity' => 0,
                    'user_id' => Auth::id(),
                    'created_by' => Auth::user()->full_name,
                    'created_by_user_id' => Auth::id(),
                ]);
            }
        });
    }

    public function logPerjanjianChange($perjanjian, Leads $leads): void
    {
        $pks = Pks::find($perjanjian->pks_id);
        if ($pks && $pks->leads_id) {
            if ($leads) {
                $nomorActivity = $this->helperService->generateNomorActivity($leads);
                CustomerActivity::create([
                    'leads_id' => $leads->id,
                    'pks_id' => $perjanjian->pks_id,
                    'tgl_activity' => now(),
                    'nomor' => $nomorActivity,
                    'tipe' => 'PKS_PERJANJIAN',
                    'notes' => "Perubahan pasal {$perjanjian->pasal} diedit oleh " . Auth::user()->full_name,
                    'created_by' => Auth::user()->full_name,
                    'created_by_user_id' => Auth::id(),
                ]);
            }
        }
        if ($leads) {
            $leads->tgl_leads = Carbon::now()->toDateString();
            $leads->save();
        }
    }
}
