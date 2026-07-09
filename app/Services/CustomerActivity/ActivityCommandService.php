<?php

namespace App\Services\CustomerActivity;

use App\Models\CustomerActivity;
use App\Models\Leads;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivityCommandService
{
    use ActivityHelperTrait;

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
            $activityData = $request->only($allowedFields);
            $activityData['nomor'] = $nomor;
            $activityData['branch_id'] = $leads->branch_id;
            $activityData['user_id'] = Auth::id();
            $activityData['created_by'] = Auth::user()->full_name;
            $activityData['created_at'] = $current_date_time;

            $activity = CustomerActivity::create($activityData);

            if ($request->hasFile('files')) {
                $uploadedFiles = $request->file('files');

                foreach ($uploadedFiles as $file) {
                    $this->storeActivityFile($activity->id, $file);
                }
            }

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
            $allowedUpdateFields = array_diff($allowedFields, ['leads_id']);
            $updateData = $request->only($allowedUpdateFields);

            if (!empty($updateData)) {
                $current_time = Carbon::now();
                $updateData['updated_by'] = Auth::user()->full_name;
                $updateData['updated_at'] = $current_time;

                $activity->update($updateData);

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
}
