<?php

namespace App\Services\CustomerActivity;

use App\Models\CustomerActivity;
use App\Models\Leads;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivityQueryService
{
    /**
     * Get filtered list of customer activities with pagination and transformation.
     *
     * @return array  ['activities' => Paginator, 'tgl_dari', 'tgl_sampai', 'search', 'search_by', 'allowed_types']
     */
    public function getFilteredActivities(Request $request): array
    {
        $query = CustomerActivity::with([
            'leads:id,nama_perusahaan,branch_id',
            'leads.branch:id,name',
            'leads.kebutuhan:id,nama',
            'timSalesDetail:id,nama'
        ])->whereNull('deleted_at');

        $allowedTypes = ['Telepon', 'Online Meeting', 'Email', 'Kirim Berkas', 'Visit'];
        $query->whereIn('tipe', $allowedTypes);

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $searchBy = $request->get('search_by', 'nama_perusahaan');

            if ($searchBy === 'nama_perusahaan') {
                $searchTerm = str_contains($searchTerm, ' ')
                    ? '"' . $searchTerm . '"'
                    : $searchTerm . '*';
                $query->whereRaw("MATCH(nama_perusahaan) AGAINST(? IN BOOLEAN MODE)", [$searchTerm]);
            } elseif (in_array($searchBy, ['tipe', 'branch', 'kebutuhan', 'sales'])) {
                $query->where($searchBy, 'LIKE', '%' . $searchTerm . '%');
            }
        } else {
            $tglDari = $request->get('tgl_dari', Carbon::today()->subMonths(6)->toDateString());
            $tglSampai = $request->get('tgl_sampai', Carbon::today()->toDateString());
            $query->whereBetween('tgl_activity', [$tglDari, $tglSampai]);
        }

        $query->whereHas('leads', function ($q) use ($request) {
            $q->filterByUserRole();
            if ($request->filled('branch')) {
                $q->where('branch_id', $request->branch);
            }
            if ($request->filled('kebutuhan')) {
                $q->whereHas('kebutuhan', function ($sq) use ($request) {
                    $sq->where('m_kebutuhan.id', $request->kebutuhan);
                });
            }
        });
        if ($request->filled('user')) {
            $query->where('user_id', $request->user);
        }
        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        $activities = $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->get('per_page', 15));

        $activities->getCollection()->transform(function ($activity) {
            $base = [
                'id' => $activity->id,
                'nomor' => $activity->nomor,
                'tgl_activity' => $activity->tgl_activity,
                'tipe' => $activity->tipe,
                'notes' => $activity->notes,
                'status_leads_id' => $activity->status_leads_id,
                'created_at' => $activity->getRawOriginal('created_at'),
                'created_by' => $activity->created_by,
                'nama_perusahaan' => $activity->leads?->nama_perusahaan ?? '-',
                'kebutuhan' => $activity->leads?->kebutuhan->pluck('nama')->toArray() ?? [],
                'branch' => $activity->leads?->branch?->name ?? '-',
                'sales' => $activity->timSalesDetail?->nama ?? '-',
                'leads_id' => $activity->leads_id,
                'quotation_id' => $activity->quotation_id,
                'spk_id' => $activity->spk_id,
                'pks_id' => $activity->pks_id,
            ];

            $tipeLower = strtolower($activity->tipe);
            if (in_array($tipeLower, ['telepon', 'online meeting'])) {
                $base['start'] = $activity->start;
                $base['end'] = $activity->end;
                $base['durasi'] = $activity->durasi;
                $base['tgl_realisasi'] = $activity->tgl_realisasi;
            } elseif ($tipeLower === 'visit') {
                $base['tgl_realisasi'] = $activity->tgl_realisasi;
                $base['jam_realisasi'] = $activity->jam_realisasi;
                $base['jenis_visit'] = $activity->jenis_visit;
            }

            return $base;
        });

        return [
            'activities' => $activities,
            'tgl_dari' => $request->get('tgl_dari', Carbon::today()->subMonths(6)->toDateString()),
            'tgl_sampai' => $request->get('tgl_sampai', Carbon::today()->toDateString()),
            'search' => $request->search,
            'search_by' => $request->get('search_by', 'nama_perusahaan'),
            'allowed_types' => $allowedTypes,
        ];
    }

    /**
     * Get detail of a single customer activity with transformed data.
     *
     * @return array|null
     */
    public function getActivityDetail(int $id): ?array
    {
        $activity = CustomerActivity::with(['files'])
            ->whereNull('deleted_at')
            ->find($id);

        if (!$activity) {
            return null;
        }

        $activityData = [
            'id' => $activity->id,
            'nomor' => $activity->nomor,
            'nama_perusahaan' => $activity->leads?->nama_perusahaan ?? '-',
            'kebutuhan' => $activity->leads?->kebutuhan->pluck('nama')->toArray() ?? [],
            'branch' => $activity->leads?->branch?->name ?? '-',
            'sales' => $activity->timSalesDetail?->nama ?? '-',
            'tipe' => $activity->tipe,
            'notes' => $activity->notes_tipe ?? $activity->notes,
            'tgl_activity' => $activity->tgl_activity,
            'created_at' => $activity->getRawOriginal('created_at'),
            'created_by' => $activity->created_by,
            'activity_files' => $activity->files->isEmpty() ? null : $activity->files->map(function ($file) {
                return [
                    'id' => $file->id,
                    'nama_file' => $file->nama_file,
                    'url_file' => $file->url_file,
                    'created_at' => $file->created_at
                ];
            }),
        ];

        if (in_array(strtolower($activity->tipe), ['telepon', 'online meeting'])) {
            $activityData['start'] = $activity->start;
            $activityData['end'] = $activity->end;
            $activityData['durasi'] = $activity->durasi;
            $activityData['tgl_realisasi'] = $activity->tgl_realisasi;
        } elseif (strtolower($activity->tipe) === 'visit') {
            $activityData['tgl_realisasi'] = $activity->tgl_realisasi;
            $activityData['jam_realisasi'] = $activity->jam_realisasi;
            $activityData['jenis_visit'] = $activity->jenis_visit;
        }

        return $activityData;
    }

    /**
     * Track activities by leads ID.
     *
     * @return array  ['leads' => Leads|null, 'activities' => Collection]
     */
    public function trackActivity(int $leadsId): array
    {
        $activities = CustomerActivity::with(['files', 'statusLeads'])
            ->where('leads_id', $leadsId)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $leads = Leads::with(['kebutuhan', 'branch'])->find($leadsId);

        return [
            'leads' => $leads,
            'activities' => $activities,
        ];
    }

    /**
     * Get tim sales members by tim sales ID.
     */
    public function getTimSalesMembers(int $timSalesId): Collection
    {
        return DB::table('m_tim_sales_d')
            ->whereNull('deleted_at')
            ->where('tim_sales_id', $timSalesId)
            ->get();
    }

    /**
     * Get contract (PKS) activities.
     */
    public function getContractActivities(int $pksId): Collection
    {
        return CustomerActivity::with(['files'])
            ->where('pks_id', $pksId)
            ->where('is_activity', 1)
            ->whereNull('deleted_at')
            ->orderBy('tgl_activity', 'desc')
            ->get();
    }

    /**
     * Get contract issues by PKS ID.
     */
    public function getContractIssues(int $pksId): Collection
    {
        return DB::table('sl_issue')
            ->select([
                'id', 'judul', 'jenis_keluhan', 'kolaborator',
                'deskripsi', 'url_lampiran', 'status',
                'created_at', 'created_by', 'updated_at', 'updated_by'
            ])
            ->whereNull('deleted_at')
            ->where('pks_id', $pksId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get available leads for activity.
     */
    public function getAvailableLeads(): Collection
    {
        $user = Auth::user();

        $query = Leads::select([
            'id', 'nama_perusahaan', 'branch_id', 'tgl_leads', 'pic', 'no_telp'
        ])
            ->with(['branch:id,name'])
            ->availableForActivity($user);

        $data = $query->get();

        return $data->map(function ($item) {
            return [
                'id' => $item->id,
                'nama_perusahaan' => $item->nama_perusahaan,
                'nama_branch' => $item->branch ? $item->branch->name : null,
                'tanggal_leads' => Carbon::parse($item->tgl_leads)->isoFormat('D MMMM Y'),
                'pic' => $item->pic,
                'no_telp_pic' => $item->no_telp
            ];
        });
    }
}
