<?php

namespace App\Services\CustomerActivity;

use App\Models\CustomerActivity;
use App\Models\CustomerActivityFile;
use App\Models\Leads;
use App\Models\LeadsKebutuhan;
use App\Models\SalesActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait ActivityHelperTrait
{
    /**
     * Generate nomor customer activity
     */
    public function generateNomor($leadsId): string
    {
        $now = Carbon::now();
        $leads = Leads::find($leadsId);

        $prefix = "CAT/";
        if ($leads) {
            switch ($leads->kebutuhan_id) {
                case 2:
                    $prefix .= "LS/";
                    break;
                case 1:
                    $prefix .= "SG/";
                    break;
                case 3:
                    $prefix .= "CS/";
                    break;
                case 4:
                    $prefix .= "LL/";
                    break;
                default:
                    $prefix .= "NN/";
                    break;
            }
            $prefix .= $leads->nomor . "-";
        } else {
            $prefix .= "NN/NNNNN-";
        }

        $month = str_pad($now->month, 2, '0', STR_PAD_LEFT);
        $year = $now->year;

        $count = CustomerActivity::where('nomor', 'like', $prefix . $month . $year . "-%")->count();
        $sequence = str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        return $prefix . $month . $year . "-" . $sequence;
    }

    /**
     * Store activity file - Konsisten dengan storeSpkFile di SpkController
     *
     * @param int $activityId
     * @param \Illuminate\Http\UploadedFile $file
     * @return string
     */
    public function storeActivityFile($activityId, $file)
    {
        try {
            $fileExtension = $file->getClientOriginalExtension();
            $originalFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $fileName = $originalFileName . date("YmdHis") . rand(10000, 99999) . "." . $fileExtension;

            Storage::disk('customer-activity')->put($fileName, file_get_contents($file));

            $fileUrl = url('document/customer-activity/' . $fileName);

            CustomerActivityFile::create([
                'customer_activity_id' => $activityId,
                'nama_file' => $file->getClientOriginalName(),
                'url_file' => $fileUrl,
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id(),
                'created_at' => Carbon::now()
            ]);

            return $fileName;

        } catch (\Exception $e) {
            Log::error('Error storing activity file: ' . $e->getMessage());
            throw new \Exception('Gagal menyimpan file: ' . $e->getMessage());
        }
    }

    public function createSalesActivity($leadsId, $notulen)
    {
        $user = Auth::user();
        $leadsKebutuhanList = LeadsKebutuhan::where('leads_id', $leadsId)
            ->whereNotNull('tim_sales_d_id')
            ->get();

        $firstActivity = null;

        foreach ($leadsKebutuhanList as $leadsKebutuhan) {
            $activity = SalesActivity::create([
                'leads_id' => $leadsId,
                'leads_kebutuhan_id' => $leadsKebutuhan->id,
                'tgl_activity' => Carbon::now(),
                'jenis_activity' => 'Email',
                'notulen' => $notulen,
                'created_by' => $user->full_name,
                'created_by_user_id' => $user->id
            ]);

            if (!$firstActivity) {
                $firstActivity = $activity;
            }
        }

        return $firstActivity;
    }
}
