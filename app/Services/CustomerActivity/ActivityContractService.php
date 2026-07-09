<?php

namespace App\Services\CustomerActivity;

use App\Models\CustomerActivity;
use App\Models\Leads;
use App\Models\Pks;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivityContractService
{
    use ActivityHelperTrait;

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
                'tgl_activity', 'tipe', 'notes', 'start', 'end', 'durasi',
                'tgl_realisasi', 'jam_realisasi', 'jenis_visit_id', 'notulen', 'email'
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
            $roUser = DB::connection('mysqlhris')
                ->table('m_user')
                ->where('id', $request->ro_id)
                ->first();

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
            $crmUser = DB::connection('mysqlhris')
                ->table('m_user')
                ->where('id', $request->crm_id)
                ->first();

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

            Pks::where('id', $request->pks_id)->update([
                'status_pks_id' => $request->status_pks_id,
                'updated_at' => $current_date_time,
                'updated_by' => Auth::user()->full_name
            ]);
        });

        return $nomor;
    }
}
