<?php

namespace App\Services\Leads;

use App\Http\Requests\Leads\AssignSalesRequest;
use App\Http\Requests\Leads\RemoveSalesRequest;
use App\Models\CustomerActivity;
use App\Models\Kebutuhan;
use App\Models\Leads;
use App\Models\LeadsKebutuhan;
use App\Models\TimSalesDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeadsAssignService
{
    /**
     * Get sales per kebutuhan for a lead.
     */
    public function getSalesKebutuhan(int $id): array
    {
        $salesKebutuhan = LeadsKebutuhan::with([
            'kebutuhan:id,nama',
            'timSalesD.user:id,full_name',
            'timSalesD.timSales:id,nama'
        ])->where('leads_id', $id)->get();

        $salesSummary = [];
        $groupedBySales = $salesKebutuhan->groupBy('tim_sales_d_id');

        foreach ($groupedBySales as $timSalesDId => $items) {
            if ($timSalesDId) {
                $firstItem = $items->first();
                $salesSummary[] = [
                    'tim_sales_d_id' => $timSalesDId,
                    'sales_name' => $firstItem->timSalesD->user->full_name ?? $firstItem->timSalesD->nama,
                    'tim_sales_name' => $firstItem->timSalesD->timSales->nama ?? 'N/A',
                    'kebutuhan_count' => $items->count(),
                    'kebutuhan_list' => $items->map(function ($item) {
                        return [
                            'kebutuhan_id' => $item->kebutuhan_id,
                            'kebutuhan_nama' => $item->kebutuhan->nama ?? 'N/A'
                        ];
                    })->toArray()
                ];
            }
        }

        $unassignedKebutuhan = $salesKebutuhan->whereNull('tim_sales_d_id');
        if ($unassignedKebutuhan->count() > 0) {
            $salesSummary[] = [
                'tim_sales_d_id' => null,
                'sales_name' => 'Belum diassign',
                'tim_sales_name' => 'N/A',
                'kebutuhan_count' => $unassignedKebutuhan->count(),
                'kebutuhan_list' => $unassignedKebutuhan->map(function ($item) {
                    return [
                        'kebutuhan_id' => $item->kebutuhan_id,
                        'kebutuhan_nama' => $item->kebutuhan->nama ?? 'N/A'
                    ];
                })->toArray()
            ];
        }

        return [
            'sales_summary' => $salesSummary,
            'detailed_data' => $salesKebutuhan,
        ];
    }

    /**
     * Get available sales for assignment to a lead.
     */
    public function getAvailableSales(int $id): array
    {
        $user = Auth::user();
        $allowedRoles = [30, 31, 32, 33, 53, 96, 2];

        if (!in_array($user->cais_role_id, $allowedRoles)) {
            return ['error' => 'Anda tidak memiliki akses untuk melihat daftar tim sales', 'error_code' => 403];
        }

        $lead = Leads::find($id);
        if (!$lead) {
            return ['error' => 'Lead tidak ditemukan', 'error_code' => 404];
        }

        if (!is_numeric($lead->branch_id)) {
            return ['error' => 'Lead tidak memiliki branch_id yang valid', 'error_code' => 400];
        }

        $query = TimSalesDetail::with([
            'user:id,full_name',
            'timSales:id,nama,branch_id',
            'timSales.branch:id,name'
        ]);

        if ($user->cais_role_id == 31) {
            $leaderTim = TimSalesDetail::where('user_id', $user->id)->first();

            if ($leaderTim) {
                $query->where('tim_sales_id', $leaderTim->tim_sales_id);
            } else {
                return [
                    'sales' => collect(),
                    'lead' => $lead,
                ];
            }
        }

        $query->whereHas('timSales', function ($q) use ($lead) {
            $q->where('branch_id', (int) $lead->branch_id);
        });

        $sales = $query->get();

        $salesData = $sales->map(function ($item) {
            return [
                'id' => $item->id,
                'nama' => $item->nama,
                'tim_sales_id' => $item->tim_sales_id,
                'user_id' => $item->user_id,
                'is_leader' => (bool) $item->is_leader,
                'is_active' => (bool) $item->is_active,
                'user' => $item->user ? [
                    'id' => $item->user->id,
                    'full_name' => $item->user->full_name,
                ] : null,
                'tim_sales' => $item->timSales ? [
                    'id' => $item->timSales->id,
                    'nama' => $item->timSales->nama,
                    'branch_id' => $item->timSales->branch_id,
                    'branch' => $item->timSales->branch ? [
                        'id' => $item->timSales->branch->id,
                        'nama' => $item->timSales->branch->name
                    ] : null
                ] : null
            ];
        });

        return [
            'sales' => $salesData,
            'lead' => $lead,
        ];
    }

    /**
     * Assign sales ke kebutuhan lead (transactional).
     */
    public function assignSales(AssignSalesRequest $request, Leads $lead, $user): array
    {
        return DB::transaction(function () use ($request, $lead, $user) {
            $kebutuhanIds = [];
            foreach ($request->assignments as $assignment) {
                $kebutuhanIds = array_merge($kebutuhanIds, $assignment['kebutuhan_ids']);
            }
            $kebutuhanIds = array_unique($kebutuhanIds);
            $kebutuhanMap = Kebutuhan::whereIn('id', $kebutuhanIds)->pluck('nama', 'id');

            $timSalesDIds = array_column($request->assignments, 'tim_sales_d_id');
            $timSalesDMap = TimSalesDetail::with('user', 'timSales')
                ->whereIn('id', $timSalesDIds)
                ->get()
                ->keyBy('id');

            $assignmentResults = [];
            $allAssignedKebutuhanNames = [];
            $allAssignedSalesNames = [];

            foreach ($request->assignments as $assignment) {
                $timSalesD = $timSalesDMap->get($assignment['tim_sales_d_id']);

                if (!$timSalesD)
                    continue;

                $salesName = $timSalesD->user->full_name ?? $timSalesD->nama;
                $allAssignedSalesNames[] = $salesName;

                $assignedKebutuhanForThisSales = [];

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

                    $assignedKebutuhanForThisSales[] = $kebutuhan_id;

                    if (isset($kebutuhanMap[$kebutuhan_id])) {
                        $allAssignedKebutuhanNames[] = $kebutuhanMap[$kebutuhan_id];
                    }
                }

                $assignmentResults[] = [
                    'sales_assigned' => [
                        'tim_sales_d_id' => $timSalesD->id,
                        'sales_name' => $salesName,
                        'tim_sales_id' => $timSalesD->tim_sales_id,
                        'tim_sales_name' => $timSalesD->timSales->nama ?? 'N/A'
                    ],
                    'kebutuhan_assigned' => $assignedKebutuhanForThisSales
                ];
            }

            $nomorActivity = $this->generateNomorActivity($lead->id);
            CustomerActivity::create([
                'leads_id' => $lead->id,
                'branch_id' => $lead->branch_id,
                'tgl_activity' => Carbon::now()->toDateTimeString(),
                'nomor' => $nomorActivity,
                'notes' => implode(', ', array_unique($allAssignedSalesNames)) . ' diassign ke kebutuhan: ' . implode(', ', array_unique($allAssignedKebutuhanNames)),
                'tipe' => 'Assignment',
                'status_leads_id' => $lead->status_leads_id,
                'is_activity' => 0,
                'user_id' => $user->id,
                'created_by' => $user->full_name,
                'created_by_user_id' => $user->id
            ]);

            if ($lead) {
                $lead->tgl_leads = Carbon::now()->toDateString();
                $lead->save();
            }

            return [
                'lead_id' => $lead->id,
                'assignments' => $assignmentResults
            ];
        });
    }

    /**
     * Hapus assignment sales dari kebutuhan tertentu (transactional).
     */
    public function removeSales(RemoveSalesRequest $request, Leads $lead): array
    {
        return DB::transaction(function () use ($request, $lead) {
            $id = $lead->id;
            $removedCount = LeadsKebutuhan::where('leads_id', $id)
                ->whereIn('kebutuhan_id', $request->kebutuhan_ids)
                ->update([
                    'tim_sales_id' => null,
                    'tim_sales_d_id' => null
                ]);

            $nomorActivity = $this->generateNomorActivity($lead->id);
            CustomerActivity::create([
                'leads_id' => $lead->id,
                'branch_id' => $lead->branch_id,
                'tgl_activity' => Carbon::now()->toDateTimeString(),
                'nomor' => $nomorActivity,
                'notes' => 'Assignment sales dihapus dari kebutuhan: ' . implode(', ', $request->kebutuhan_ids),
                'tipe' => 'Assignment Removal',
                'status_leads_id' => $lead->status_leads_id,
                'is_activity' => 0,
                'user_id' => Auth::id(),
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id()
            ]);

            return [
                'lead_id' => $id,
                'removed_kebutuhan' => $request->kebutuhan_ids,
                'removed_count' => $removedCount
            ];
        });
    }

    private function generateNomorActivity($leadsId)
    {
        $now = Carbon::now();
        $leads = Leads::find($leadsId);

        $prefix = "CAT/";
        if ($leads) {
            $prefix .= match ($leads->kebutuhan_id) {
                1 => "SG/",
                2 => "LS/",
                3 => "CS/",
                4 => "LL/",
                default => "NN/"
            };
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
}
