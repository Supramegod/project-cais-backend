<?php

namespace App\Services\Leads;

use App\Models\Leads;
use App\Models\CustomerActivity;
use App\Models\SalesActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeadsQueryService
{
    /**
     * Pure lead/tim permission check.
     */
    public function canViewLead($lead, $tim)
    {
        if (Auth::user()->cais_role_id == 29) {
            return $tim && $lead->tim_sales_d_id == $tim->id;
        }
        return true;
    }

    /**
     * Get paginated leads list with search, filter, and role-based access.
     */
    public function getLeadsList(Request $request): array
    {
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
            'created_at',
        ])
            ->with([
                'statusLeads:id,nama',
                'branch:id,name',
                'platform:id,nama',
                'timSalesD:id,nama',
                'leadsKebutuhan:id,leads_id,kebutuhan_id,tim_sales_d_id',
                'leadsKebutuhan.timSalesD:id,nama',
                'leadsKebutuhan.kebutuhan:id,nama',
            ])
            ->where('status_leads_id', '!=', 102)
            ->filterByUserRole();

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $searchBy = $request->get('search_by', 'nama_perusahaan');

            if ($searchBy === 'nama_perusahaan') {
                $searchTerm = str_contains($searchTerm, ' ')
                    ? '"' . $searchTerm . '"'
                    : $searchTerm . '*';
                $query->whereRaw("MATCH(nama_perusahaan) AGAINST(? IN BOOLEAN MODE)", [$searchTerm]);
            } elseif ($searchBy === 'kebutuhan') {
                $query->whereHas('leadsKebutuhan.kebutuhan', function ($q) use ($searchTerm) {
                    $q->where('nama', 'LIKE', '%' . $searchTerm . '%');
                });
            } elseif (in_array($searchBy, ['nomor', 'created_by'])) {
                $query->where("{$searchBy}", 'LIKE', '%' . $searchTerm . '%');
            }
        } else {
            $tglDari = $request->get('tgl_dari', Carbon::today()->subMonths(6)->toDateString());
            $tglSampai = $request->get('tgl_sampai', Carbon::today()->toDateString());
            $query->whereBetween('tgl_leads', [$tglDari, $tglSampai]);
        }

        if ($request->filled('branch'))
            $query->where('branch_id', $request->branch);
        if ($request->filled('platform'))
            $query->where('platform_id', $request->platform);
        if ($request->filled('status'))
            $query->where('status_leads_id', $request->status);

        $data = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        $items = $data->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'nomor' => $item->nomor,
                'wilayah' => $item->branch->name ?? null,
                'wilayah_id' => $item->branch_id,
                'tgl_leads' => Carbon::parse($item->getRawOriginal('tgl_leads'))->isoFormat('D MMMM Y'),
                'sales' => $item->timSalesD->nama ?? null,
                'nama_perusahaan' => $item->nama_perusahaan,
                'telp_perusahaan' => $item->telp_perusahaan,
                'provinsi' => $item->provinsi,
                'kota' => $item->kota,
                'no_telp' => $item->no_telp,
                'email' => $item->email,
                'status_leads' => $item->statusLeads->nama ?? null,
                'status_leads_id' => $item->status_leads_id,
                'sumber_leads' => $item->platform->nama ?? null,
                'sumber_leads_id' => $item->platform_id,
                'created_by' => $item->created_by,
                'notes' => $item->notes,
                'kebutuhan' => $item->leadsKebutuhan->map(fn($lk) => [
                    'id' => $lk->kebutuhan_id,
                    'nama' => $lk->kebutuhan->nama ?? null,
                    'tim_sales_d_id' => $lk->tim_sales_d_id,
                    'sales_name' => $lk->timSalesD->nama ?? null,
                ]),
            ];
        });

        return [
            'data' => $items,
            'pagination' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'total' => $data->total(),
                'total_per_page' => $data->count(),
            ],
        ];
    }

    /**
     * Get lead detail with all relationships.
     */
    public function getLeadDetail(int $id): ?Leads
    {
        $lead = Leads::with([
            'branch',
            'kebutuhan',
            'timSales',
            'timSalesD',
            'statusLeads',
            'jenisPerusahaan',
            'company',
            'groupDetails',
            'pics.jabatan'
        ])->whereNull('customer_id')->find($id);

        if (!$lead) {
            return null;
        }

        $lead->stgl_leads = Carbon::parse($lead->tgl_leads)->isoFormat('D MMMM Y');
        $lead->screated_at = Carbon::parse($lead->created_at)->isoFormat('D MMMM Y');
        $lead->kebutuhan_array = $lead->kebutuhan_id ? array_map('trim', explode(',', $lead->kebutuhan_id)) : [];

        return $lead;
    }

    /**
     * Get soft-deleted leads.
     */
    public function getDeletedLeads()
    {
        $data = Leads::onlyTrashed()
            ->with(['statusLeads', 'branch', 'platform', 'timSalesD'])
            ->whereNull('customer_id')
            ->get();

        $data->transform(function ($item) {
            $item->tgl = Carbon::parse($item->tgl_leads)->isoFormat('D MMMM Y');
            return $item;
        });

        return $data;
    }

    /**
     * Get child leads for a given parent lead id.
     */
    public function getChildLeads(int $id)
    {
        return Leads::where(function ($query) use ($id) {
            $query->where('leads_id', $id)
                ->orWhere('id', $id);
        })
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Get inactive leads (is_aktif is null).
     */
    public function getInactiveLeads()
    {
        $data = Leads::with(['statusLeads', 'kebutuhan', 'platform'])
            ->whereNull('is_aktif')
            ->get();

        $data->transform(function ($item) {
            $item->tgl_leads = Carbon::parse($item->tgl_leads)->isoFormat('D MMMM Y');
            return $item;
        });

        return $data;
    }

    /**
     * Get leads available for quotation.
     */
    public function getLeadsAvailableForQuotation()
    {
        $user = Auth::user();

        $query = Leads::with(['statusLeads', 'branch', 'kebutuhan', 'timSales', 'timSalesD'])
            ->availableForQuotation($user);

        $data = $query->get();

        $data->transform(function ($item) {
            $item->tgl = Carbon::parse($item->tgl_leads)->isoFormat('D MMMM Y');
            return $item;
        });

        return $data;
    }

    /**
     * Hitung sisa kontrak.
     */
    public function hitungBerakhirKontrak($tanggalBerakhir)
    {
        if (is_null($tanggalBerakhir)) {
            return "-";
        }

        $tanggalSekarang = Carbon::now();
        $tanggalBerakhir = Carbon::createFromFormat('Y-m-d', $tanggalBerakhir);

        if ($tanggalSekarang->greaterThanOrEqualTo($tanggalBerakhir)) {
            return "Kontrak habis";
        }

        $selisih = $tanggalSekarang->diff($tanggalBerakhir);

        $hasil = [];
        if ($selisih->y > 0)
            $hasil[] = "{$selisih->y} tahun";
        if ($selisih->m > 0)
            $hasil[] = "{$selisih->m} bulan";
        if ($selisih->d > 0)
            $hasil[] = "{$selisih->d} hari";

        return implode(', ', $hasil);
    }
}
