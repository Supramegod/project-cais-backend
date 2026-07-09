<?php

namespace App\Services\Leads;

use App\Models\Kebutuhan;
use App\Models\Leads;
use App\Models\LeadsKebutuhan;
use App\Models\TimSalesDetail;
use Illuminate\Support\Facades\Auth;

class LeadsAssignSetupService
{
    /**
     * Auto assign sales ke lead (untuk user dengan role 29, 31, 32, 33).
     */
    public function autoAssignSalesToKebutuhan($lead, $kebutuhanIds)
    {
        $user = Auth::user();
        $assignmentResults = [];

        if (in_array($user->cais_role_id, [29, 31, 32, 33])) {
            $timSalesD = TimSalesDetail::where('user_id', $user->id)->first();

            if ($timSalesD) {
                $lead->update([
                    'tim_sales_id' => $timSalesD->tim_sales_id,
                    'tim_sales_d_id' => $timSalesD->id
                ]);

                $kebutuhanData = [];
                foreach ($kebutuhanIds as $kebutuhan_id) {
                    $kebutuhanData[$kebutuhan_id] = [
                        'tim_sales_id' => $timSalesD->tim_sales_id,
                        'tim_sales_d_id' => $timSalesD->id
                    ];
                }

                $lead->kebutuhan()->sync($kebutuhanData);

                $assignmentResults[] = [
                    'type' => 'auto_assign',
                    'sales_assigned' => [
                        'tim_sales_d_id' => $timSalesD->id,
                        'sales_name' => $timSalesD->user->full_name ?? $timSalesD->nama,
                        'tim_sales_id' => $timSalesD->tim_sales_id
                    ],
                    'kebutuhan_assigned' => $kebutuhanIds
                ];
            }
        }

        return $assignmentResults;
    }

    /**
     * Manual assignment sales ke kebutuhan (untuk user berwenang).
     */
    public function manualAssignSalesToKebutuhan($lead, $assignments)
    {
        $assignmentResults = [];

        $timSalesDIds = array_column($assignments, 'tim_sales_d_id');
        $timSalesDMap = TimSalesDetail::with('user', 'timSales')
            ->whereIn('id', $timSalesDIds)
            ->get()
            ->keyBy('id');

        foreach ($assignments as $assignment) {
            $timSalesD = $timSalesDMap->get($assignment['tim_sales_d_id']);

            if ($timSalesD) {
                $assignedKebutuhan = [];
                foreach ($assignment['kebutuhan_ids'] as $kebutuhan_id) {

                    LeadsKebutuhan::updateOrCreate(
                        [
                            'leads_id' => $lead->id,
                            'kebutuhan_id' => $kebutuhan_id,
                            'tim_sales_d_id' => $timSalesD->id
                        ],
                        [
                            'tim_sales_id' => $timSalesD->tim_sales_id
                        ]
                    );

                    LeadsKebutuhan::where('leads_id', $lead->id)
                        ->where('kebutuhan_id', $kebutuhan_id)
                        ->whereNull('tim_sales_d_id')
                        ->delete();

                    $assignedKebutuhan[] = $kebutuhan_id;
                }

                $assignmentResults[] = [
                    'sales_assigned' => [
                        'tim_sales_d_id' => $timSalesD->id,
                        'sales_name' => $timSalesD->user->full_name ?? $timSalesD->nama,
                        'tim_sales_id' => $timSalesD->tim_sales_id,
                        'tim_sales_name' => $timSalesD->timSales->nama ?? 'N/A'
                    ],
                    'kebutuhan_assigned' => $assignedKebutuhan
                ];
            }
        }

        return $assignmentResults;
    }

    /**
     * Sync kebutuhan tanpa assignment sales.
     */
    public function syncKebutuhanTanpaSales($lead, $kebutuhanIds)
    {
        $kebutuhanData = [];
        foreach ($kebutuhanIds as $kebutuhan_id) {
            $kebutuhanData[$kebutuhan_id] = [
                'tim_sales_d_id' => null,
                'tim_sales_id' => null
            ];
        }

        $lead->kebutuhan()->sync($kebutuhanData);
        return [];
    }

    /**
     * Sync kebutuhan dengan mempertahankan sales existing.
     */
    public function syncKebutuhanDenganSalesExisting($lead, $kebutuhanIds)
    {
        $existingKebutuhan = LeadsKebutuhan::where('leads_id', $lead->id)
            ->whereIn('kebutuhan_id', $kebutuhanIds)
            ->get()
            ->keyBy('kebutuhan_id');

        $kebutuhanData = [];

        foreach ($kebutuhanIds as $kebutuhan_id) {
            $existing = $existingKebutuhan->get($kebutuhan_id);

            $kebutuhanData[$kebutuhan_id] = [
                'tim_sales_id' => $existing ? $existing->tim_sales_id : null,
                'tim_sales_d_id' => $existing ? $existing->tim_sales_d_id : null
            ];
        }

        $lead->kebutuhan()->sync($kebutuhanData);

        return [];
    }
}
