<?php

namespace App\Services\Report;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportDetailService
{
    use ReportHelperTrait;

    /**
     * Detail aktivitas sales regular per user_id.
     *
     * @return array|null
     */
    public function activityDetail(int $userId, int $month, int $year, ?int $branchId): ?array
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

        $periode = strtoupper(
            Carbon::createFromDate($year, $month, 1)->locale('id')->monthName
        ) . ' - ' . $year;

        $salesCollection = $this->getSalesNames($branchId);
        $matched = $salesCollection->firstWhere('user_id', $userId);

        if (!$matched) {
            return null;
        }

        $salesName = $matched->nama_sales;
        $cabang = $matched->cabang;

        $activities = DB::table('sl_activity_sales as sa')
            ->join('sl_leads as l', 'sa.leads_id', '=', 'l.id')
            ->select(
                'sa.id', 'sa.leads_id', 'sa.tgl_activity', 'sa.jenis_activity',
                DB::raw("COALESCE(sa.notulen, '') AS notulen"),
                'sa.created_by', 'sa.created_at', 'l.nama_perusahaan'
            )
            ->whereBetween('sa.tgl_activity', [$startDate, $endDate])
            ->where('sa.created_by_user_id', $userId)
            ->orderBy('sa.tgl_activity', 'desc')
            ->get();

        $leadsIdsNeedLookup = $activities
            ->whereIn('jenis_activity', ['Leads', 'Quotation', 'SPK', 'PKS'])
            ->pluck('leads_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $quotationMap = $this->latestQuotationBaruMap($leadsIdsNeedLookup);

        $spkMap = DB::table('sl_spk')
            ->selectRaw('leads_id, MAX(id) as doc_id')
            ->whereIn('leads_id', $leadsIdsNeedLookup)
            ->whereNull('deleted_at')
            ->groupBy('leads_id')
            ->pluck('doc_id', 'leads_id');

        $pksMap = DB::table('sl_pks')
            ->selectRaw('leads_id, MAX(id) as doc_id')
            ->whereIn('leads_id', $leadsIdsNeedLookup)
            ->whereNull('deleted_at')
            ->groupBy('leads_id')
            ->pluck('doc_id', 'leads_id');

        $data = $activities->map(function ($row, $index) use ($quotationMap, $spkMap, $pksMap) {
            $aksi = match ($row->jenis_activity) {
                'Leads' => $row->leads_id,
                'Quotation' => $quotationMap->get($row->leads_id),
                'SPK' => $spkMap->get($row->leads_id),
                'PKS' => $pksMap->get($row->leads_id),
                default => null,
            };

            return [
                'id' => $row->id,
                'tgl_activity' => Carbon::parse($row->tgl_activity)->locale('id')->isoFormat('D MMMM Y'),
                'nomor' => $index + 1,
                'nama_perusahaan' => $row->nama_perusahaan,
                'tipe' => $row->jenis_activity ?? '',
                'notes' => $row->notulen,
                'created_by' => $row->created_by,
                'created_at' => Carbon::parse($row->created_at)->format('d-m-Y H:i:s'),
                'aksi' => $aksi,
            ];
        })->values()->all();

        return [
            'user_id' => $userId,
            'sales_name' => $salesName,
            'cabang' => $cabang,
            'periode' => $periode,
            'data' => $data,
        ];
    }

    /**
     * Detail aktivitas telesales (cais_role_id = 30) per user_id.
     *
     * @return array|null
     */
    public function activityDetailTele(int $userId, int $month, int $year, ?int $branchId): ?array
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

        $periode = strtoupper(
            Carbon::createFromDate($year, $month, 1)->locale('id')->monthName
        ) . ' - ' . $year;

        $salesCollection = $this->getSalesNamesRole30($branchId);
        $matched = $salesCollection->firstWhere('user_id', $userId);

        if (!$matched) {
            return null;
        }

        $salesName = $matched->nama_sales;
        $cabang = $matched->cabang;

        $customerActivities = DB::table('sl_customer_activity as sa')
            ->join('sl_leads as l', 'sa.leads_id', '=', 'l.id')
            ->select(
                'sa.id', 'sa.leads_id', 'sa.tgl_activity',
                DB::raw('sa.tipe as tipe'),
                DB::raw("COALESCE(sa.notulen, '') AS notulen"),
                'sa.created_by', 'sa.created_at', 'l.nama_perusahaan'
            )
            ->whereBetween('sa.tgl_activity', [$startDate, $endDate])
            ->where('sa.user_id', $userId)
            ->whereIn('sa.tipe', ['Leads', 'Assignment'])
            ->get();

        $appointmentActivities = DB::table('sl_activity_sales as sa')
            ->join('sl_leads as l', 'sa.leads_id', '=', 'l.id')
            ->select(
                'sa.id', 'sa.leads_id', 'sa.tgl_activity',
                DB::raw('sa.jenis_activity as tipe'),
                DB::raw("COALESCE(sa.notulen, '') AS notulen"),
                'sa.created_by', 'sa.created_at', 'l.nama_perusahaan'
            )
            ->whereBetween('sa.tgl_activity', [$startDate, $endDate])
            ->where('sa.created_by_user_id', $userId)
            ->where('sa.jenis_activity', 'Appointment')
            ->get();

        $activities = $customerActivities
            ->concat($appointmentActivities)
            ->sortBy(['tgl_activity', 'desc'])
            ->values();

        $data = $activities->map(function ($row, $index) {
            $aksi = match ($row->tipe) {
                'Leads', 'Assignment' => $row->leads_id,
                default => null,
            };

            return [
                'id' => $row->id,
                'tgl_activity' => Carbon::parse($row->tgl_activity)->locale('id')->isoFormat('D MMMM Y'),
                'nomor' => $index + 1,
                'nama_perusahaan' => $row->nama_perusahaan,
                'tipe' => $row->tipe ?? '',
                'notes' => $row->notulen,
                'created_by' => $row->created_by,
                'created_at' => Carbon::parse($row->created_at)->format('d-m-Y H:i:s'),
                'aksi' => $aksi,
            ];
        })->values()->all();

        return [
            'user_id' => $userId,
            'sales_name' => $salesName,
            'cabang' => $cabang,
            'periode' => $periode,
            'data' => $data,
        ];
    }

    /**
     * Map leads_id => id quotation tipe 'baru' terbaru (berdasarkan tgl_quotation),
     * dipakai untuk resolusi kolom 'aksi' pada activity bertipe Quotation.
     */
    private function latestQuotationBaruMap(array $leadsIds)
    {
        if (empty($leadsIds)) {
            return collect();
        }

        return DB::table('sl_quotation')
            ->select('leads_id', 'id')
            ->whereIn('leads_id', $leadsIds)
            ->whereNull('deleted_at')
            ->where('tipe_quotation', 'baru')
            ->orderBy('tgl_quotation', 'desc')
            ->get()
            ->groupBy('leads_id')
            ->map(fn ($rows) => $rows->first()->id);
    }
}
