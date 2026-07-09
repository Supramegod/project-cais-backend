<?php

namespace App\Services;

use App\Models\CustomerActivity;
use App\Models\CustomerActivityFile;
use App\Models\Leads;
use App\Models\LeadsKebutuhan;
use App\Models\Pks;
use App\Models\SalesActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CustomerActivityService
{
    /**
     * Create a customer activity (write flow, owns its transaction).
     *
     * @param  \App\Http\Requests\StoreCustomerActivityRequest  $request
     * @param  array  $allowedFields
     */
    public function createActivity($request, Leads $leads, array $allowedFields): CustomerActivity
    {
        $nomor = $this->generateNomor($request->leads_id);
        $current_date_time = Carbon::now();

        return DB::transaction(function () use ($request, $leads, $nomor, $current_date_time, $allowedFields) {
            // Prepare activity data using allowed fields
            $activityData = $request->only($allowedFields);
            $activityData['nomor'] = $nomor;
            $activityData['branch_id'] = $leads->branch_id;
            $activityData['user_id'] = Auth::id();
            $activityData['created_by'] = Auth::user()->full_name;
            $activityData['created_at'] = $current_date_time;

            $activity = CustomerActivity::create($activityData);

            // Handle file uploads dari multipart/form-data
            if ($request->hasFile('files')) {
                $uploadedFiles = $request->file('files');

                foreach ($uploadedFiles as $file) {
                    $this->storeActivityFile($activity->id, $file);
                }
            }

            // Update status leads jika ada
            if ($request->status_leads_id) {
                $leads->update([
                    'status_leads_id' => $request->status_leads_id,
                    'updated_by' => Auth::user()->full_name,
                    'updated_at' => $current_date_time
                ]);
            }

            return $activity;
        });
    }

    /**
     * Update a customer activity (write flow, owns its transaction).
     *
     * @param  \App\Http\Requests\UpdateCustomerActivityRequest  $request
     * @param  array  $allowedFields
     */
    public function updateActivity($request, CustomerActivity $activity, array $allowedFields): CustomerActivity
    {
        DB::transaction(function () use ($request, $activity, $allowedFields) {
            // Get allowed fields (exclude leads_id untuk update)
            $allowedUpdateFields = array_diff($allowedFields, ['leads_id']);
            $updateData = $request->only($allowedUpdateFields);

            // Only update if there's actual data
            if (!empty($updateData)) {
                $current_time = Carbon::now();
                $updateData['updated_by'] = Auth::user()->full_name;
                $updateData['updated_at'] = $current_time;

                $activity->update($updateData);

                // Update status leads jika ada dan berubah
                if ($request->has('status_leads_id') && $request->status_leads_id != $activity->leads->status_leads_id) {
                    $activity->leads->update([
                        'status_leads_id' => $request->status_leads_id,
                        'updated_by' => Auth::user()->full_name,
                        'updated_at' => $current_time
                    ]);
                }
            }
        });

        return $activity;
    }

    /**
     * Soft-delete a customer activity (write flow, owns its transaction).
     */
    public function deleteActivity(CustomerActivity $activity): void
    {
        DB::transaction(function () use ($activity) {
            $activity->update([
                'deleted_at' => Carbon::now(),
                'deleted_by' => Auth::user()->full_name
            ]);
        });
    }

    /**
     * Create a contract (PKS) activity (write flow, owns its transaction).
     *
     * @param  \App\Http\Requests\StoreContractActivityRequest  $request
     */
    public function addContractActivity($request): CustomerActivity
    {
        $pks = Pks::find($request->pks_id);
        $leads = Leads::find($pks->leads_id);
        $nomor = $this->generateNomor($pks->leads_id);
        $current_date_time = Carbon::now();

        return DB::transaction(function () use ($request, $leads, $nomor, $current_date_time) {
            $activityData = $request->only([
                'tgl_activity',
                'tipe',
                'notes',
                'start',
                'end',
                'durasi',
                'tgl_realisasi',
                'jam_realisasi',
                'jenis_visit_id',
                'notulen',
                'email'
            ]);

            $activityData['nomor'] = $nomor;
            $activityData['pks_id'] = $request->pks_id;
            $activityData['leads_id'] = $leads->id;
            $activityData['branch_id'] = $leads->branch_id;
            $activityData['is_activity'] = 1;
            $activityData['user_id'] = Auth::id();
            $activityData['created_by'] = Auth::user()->full_name;
            $activityData['created_at'] = $current_date_time;

            $activity = CustomerActivity::create($activityData);

            // Handle file uploads jika ada
            if ($request->has('files')) {
                foreach ($request->files as $file) {
                    $this->storeActivityFile($activity->id, $file);
                }
            }

            return $activity;
        });
    }

    /**
     * Assign RO to leads (write flow, owns its transaction).
     *
     * @param  \App\Http\Requests\AssignRoRequest  $request
     * @return string  Generated nomor
     */
    public function assignRO($request): string
    {
        $leads = Leads::find($request->leads_id);
        $nomor = $this->generateNomor($request->leads_id);
        $current_date_time = Carbon::now();

        DB::transaction(function () use ($request, $leads, $nomor, $current_date_time) {
            // Get RO name
            $roUser = DB::connection('mysqlhris')
                ->table('m_user')
                ->where('id', $request->ro_id)
                ->first();

            // Create activity
            $activityData = [
                'nomor' => $nomor,
                'tgl_activity' => $current_date_time->toDateString(),
                'leads_id' => $request->leads_id,
                'branch_id' => $leads->branch_id,
                'tipe' => 'Pilih RO',
                'notes' => $request->notes,
                'ro_id' => $request->ro_id,
                'ro' => $roUser ? $roUser->full_name : null,
                'is_activity' => 1,
                'user_id' => Auth::id(),
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id(),
                'created_at' => $current_date_time
            ];

            if ($request->pks_id) {
                $activityData['pks_id'] = $request->pks_id;
            }

            CustomerActivity::create($activityData);

            // Update leads
            $updateData = [
                'ro_id' => $request->ro_id,
                'ro' => $roUser ? $roUser->full_name : null,
                'updated_at' => $current_date_time,
                'updated_by' => Auth::user()->full_name
            ];

            if ($request->ro_team) {
                $updateData['ro_id_1'] = $request->ro_team[0] ?? null;
                $updateData['ro_id_2'] = $request->ro_team[1] ?? null;
                $updateData['ro_id_3'] = $request->ro_team[2] ?? null;
            }

            Leads::where('id', $request->leads_id)->update($updateData);
        });

        return $nomor;
    }

    /**
     * Assign CRM to leads (write flow, owns its transaction).
     *
     * @param  \App\Http\Requests\AssignCrmRequest  $request
     * @return string  Generated nomor
     */
    public function assignCRM($request): string
    {
        $leads = Leads::find($request->leads_id);
        $nomor = $this->generateNomor($request->leads_id);
        $current_date_time = Carbon::now();

        DB::transaction(function () use ($request, $leads, $nomor, $current_date_time) {
            // Get CRM name
            $crmUser = DB::connection('mysqlhris')
                ->table('m_user')
                ->where('id', $request->crm_id)
                ->first();

            // Create activity
            $activityData = [
                'nomor' => $nomor,
                'tgl_activity' => $current_date_time->toDateString(),
                'leads_id' => $request->leads_id,
                'branch_id' => $leads->branch_id,
                'tipe' => 'Pilih CRM',
                'notes' => $request->notes,
                'crm_id' => $request->crm_id,
                'crm' => $crmUser ? $crmUser->full_name : null,
                'is_activity' => 1,
                'user_id' => Auth::id(),
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id(),
                'created_at' => $current_date_time
            ];

            if ($request->pks_id) {
                $activityData['pks_id'] = $request->pks_id;
            }

            CustomerActivity::create($activityData);

            // Update leads
            $updateData = [
                'crm_id' => $request->crm_id,
                'crm' => $crmUser ? $crmUser->full_name : null,
                'updated_at' => $current_date_time,
                'updated_by' => Auth::user()->full_name
            ];

            if ($request->crm_team) {
                $updateData['crm_id_1'] = $request->crm_team[0] ?? null;
                $updateData['crm_id_2'] = $request->crm_team[1] ?? null;
            }

            Leads::where('id', $request->leads_id)->update($updateData);
        });

        return $nomor;
    }

    /**
     * Update contract (PKS) status (write flow, owns its transaction).
     *
     * @param  \App\Http\Requests\UpdateContractStatusRequest  $request
     * @return string  Generated nomor
     */
    public function updateContractStatus($request): string
    {
        $pks = Pks::find($request->pks_id);
        $leads = Leads::find($pks->leads_id);
        $nomor = $this->generateNomor($pks->leads_id);
        $current_date_time = Carbon::now();

        DB::transaction(function () use ($request, $leads, $nomor, $current_date_time) {
            // Create activity
            CustomerActivity::create([
                'nomor' => $nomor,
                'pks_id' => $request->pks_id,
                'tgl_activity' => $current_date_time->toDateString(),
                'leads_id' => $leads->id,
                'branch_id' => $leads->branch_id,
                'tipe' => 'Update Status',
                'notes' => $request->notes,
                'is_activity' => 1,
                'user_id' => Auth::id(),
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id(),
                'created_at' => $current_date_time
            ]);

            // Update PKS status
            Pks::where('id', $request->pks_id)->update([
                'status_pks_id' => $request->status_pks_id,
                'updated_at' => $current_date_time,
                'updated_by' => Auth::user()->full_name
            ]);
        });

        return $nomor;
    }

    // ===== business helpers =====//

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

            // ✅ Simpan file ke disk 'customer-activity' yang sudah dikonfigurasi
            Storage::disk('customer-activity')->put($fileName, file_get_contents($file));

            // ✅ Generate URL manual (konsisten dengan uploadSpk)
            $fileUrl = url('document/customer-activity/' . $fileName);

            \Log::info('Customer Activity File Generated URL: ' . $fileUrl);
            \Log::info('Filename: ' . $fileName);
            \Log::info('File path: ' . Storage::disk('customer-activity')->path($fileName));
            \Log::info('File exists: ' . (Storage::disk('customer-activity')->exists($fileName) ? 'Yes' : 'No'));

            // Simpan ke database
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

    public function createSalesActivity($leadsId, $notulen) // Hapus :void
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

            // Simpan activity pertama sebagai referensi lampiran
            if (!$firstActivity) {
                $firstActivity = $activity;
            }
        }

        return $firstActivity;
    }
}
