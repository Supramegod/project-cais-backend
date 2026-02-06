<?php

namespace App\Listeners;

use App\Events\CustomerListRequested;
use App\Models\Leads;
use Carbon\Carbon;

class BuildCustomerQuery
{
    public function handle(CustomerListRequested $event)
    {
        $request = $event->request;

        $query = Leads::select([
            'id',
            'nomor',
            'branch_id',
            'tgl_leads',
            'tim_sales_d_id',
            'nama_perusahaan',
            'telp_perusahaan',
            'provinsi',
            'kota',
            'no_telp',
            'email',
            'status_leads_id',
            'platform_id',
            'created_by',
            'notes',
            'pic',
            'created_at'
        ])
            ->with([
                'statusLeads:id,nama,warna_background,warna_font',
                'branch:id,name',
                'platform:id,nama',
                'timSalesD:id,nama',
                'kebutuhan'
            ])
            ->filterByUserRole()
            ->whereNull('customer_id')
            ->where('status_leads_id', 102);

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $searchTerm = str_contains($searchTerm, ' ')
                ? '"' . $searchTerm . '"'
                : $searchTerm . '*';

            $query->whereRaw("MATCH(nama_perusahaan) AGAINST(? IN BOOLEAN MODE)", [$searchTerm]);
        } else {
            $tglDari = $request->get('tgl_dari', Carbon::today()->subMonths(6)->toDateString());
            $tglSampai = $request->get('tgl_sampai', Carbon::today()->toDateString());
            $query->whereBetween('tgl_leads', [$tglDari, $tglSampai]);
        }

        if ($request->filled('branch')) {
            $query->where('branch_id', $request->branch);
        }

        if ($request->filled('platform')) {
            $query->where('platform_id', $request->platform);
        }

        if ($request->filled('status')) {
            $query->where('status_leads_id', $request->status);
        }

        return $query;
    }
}
