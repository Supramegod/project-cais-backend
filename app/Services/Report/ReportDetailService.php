<?php

namespace App\Services\Report;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportDetailService
{
    use ReportHelperTrait;

    /**
     * Detail aktivitas sales regular per user_id.
     */
    public function activityDetail(int $userId, int $month, int $year, ?int $branchId, ?string $jenisActivity = null): ?array
    {
        [$startDate, $endDate] = $this->monthRange($month, $year);
        $periode = $this->buildPeriode($month, $year);

        $matched = $this->getSalesNames($branchId)->firstWhere('user_id', $userId);

        if (! $matched) {
            return null;
        }

        $activities = DB::table('sl_activity_sales as sa')
            ->join('sl_leads as l', 'sa.leads_id', '=', 'l.id')
            ->select(
                'sa.id', 'sa.leads_id', 'sa.tgl_activity', 'sa.jenis_activity',
                'sa.quotation_id', 'sa.spk_id', 'sa.pks_id',
                DB::raw("COALESCE(sa.notulen, '') AS notulen"),
                'sa.created_by', 'sa.created_at', 'l.nama_perusahaan'
            )
            ->whereBetween('sa.tgl_activity', [$startDate, $endDate])
            ->where(function ($query) use ($userId) {
                $query->where('sa.created_by_user_id', $userId)
                    ->orWhere(function ($q) use ($userId) {
                        $q->where('sa.jenis_activity', 'Appointment')
                            ->whereIn('sa.leads_id', function ($sub) use ($userId) {
                                $sub->select('lk.leads_id')
                                    ->from('sl_leads_kebutuhan as lk')
                                    ->join('m_tim_sales_d as tsd', 'tsd.id', '=', 'lk.tim_sales_d_id')
                                    ->whereNull('lk.deleted_at')
                                    ->whereNull('tsd.deleted_at')
                                    ->where('tsd.user_id', $userId);
                            });
                    });
            })
            ->when($jenisActivity, fn ($query) => $query->where('sa.jenis_activity', $jenisActivity))
            ->orderBy('sa.tgl_activity', 'desc')
            ->get();

        $quotationIdsNeedLookup = $activities
            ->where('jenis_activity', 'Quotation')
            ->pluck('quotation_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $validBaruQuotationIds = empty($quotationIdsNeedLookup)
            ? collect()
            : DB::table('sl_quotation')
                ->whereIn('id', $quotationIdsNeedLookup)
                ->whereNull('deleted_at')
                ->where('tipe_quotation', 'baru')
                ->pluck('id');

        $data = $activities->map(function ($row, $index) use ($validBaruQuotationIds) {
            $aksi = match ($row->jenis_activity) {
                'Leads' => $row->leads_id,
                'Quotation' => ($row->quotation_id && $validBaruQuotationIds->contains($row->quotation_id))
                    ? $row->quotation_id
                    : null,
                'SPK' => $row->spk_id,
                'PKS' => $row->pks_id,
                default => null,
            };

            return $this->formatActivityRow($row, $index, $row->jenis_activity, $aksi);
        })->values()->all();

        return $this->buildActivityDetailResult($userId, $matched, $periode, $data);
    }

    /**
     * Detail aktivitas telesales (cais_role_id = 30) per user_id.
     */
    public function activityDetailTele(int $userId, int $month, int $year, ?int $branchId, ?string $jenisActivity = null): ?array
    {
        [$startDate, $endDate] = $this->monthRange($month, $year);
        $periode = $this->buildPeriode($month, $year);

        $matched = $this->getSalesNamesRole30($branchId)->firstWhere('user_id', $userId);

        if (! $matched) {
            return null;
        }

        $customerTipe = $jenisActivity
            ? array_values(array_intersect(['Leads', 'Assignment'], [$jenisActivity]))
            : ['Leads', 'Assignment'];

        $customerActivities = empty($customerTipe)
            ? collect()
            : DB::table('sl_customer_activity as sa')
                ->join('sl_leads as l', 'sa.leads_id', '=', 'l.id')
                ->select(
                    'sa.id', 'sa.leads_id', 'sa.tgl_activity',
                    DB::raw('sa.tipe as tipe'),
                    DB::raw("COALESCE(sa.notulen, '') AS notulen"),
                    'sa.created_by', 'sa.created_at', 'l.nama_perusahaan'
                )
                ->whereBetween('sa.tgl_activity', [$startDate, $endDate])
                ->where('sa.user_id', $userId)
                ->whereIn('sa.tipe', $customerTipe)
                ->get();

        $appointmentActivities = ($jenisActivity !== null && $jenisActivity !== 'Appointment')
            ? collect()
            : DB::table('sl_activity_sales as sa')
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
            ->sortByDesc('tgl_activity')
            ->values();

        $data = $activities->map(function ($row, $index) {
            $aksi = match ($row->tipe) {
                'Leads', 'Assignment' => $row->leads_id,
                default => null,
            };

            return $this->formatActivityRow($row, $index, $row->tipe, $aksi);
        })->values()->all();

        return $this->buildActivityDetailResult($userId, $matched, $periode, $data);
    }

    // ======================== PRIVATE HELPERS ================================

    private function monthRange(int $month, int $year): array
    {
        $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

        return [$start, $end];
    }

    private function buildPeriode(int $month, int $year): string
    {
        return strtoupper(
            Carbon::createFromDate($year, $month, 1)->locale('id')->monthName
        ).' - '.$year;
    }

    private function formatActivityRow($row, int $index, ?string $tipe, $aksi): array
    {
        return [
            'id' => $row->id,
            'tgl_activity' => Carbon::parse($row->tgl_activity)->locale('id')->isoFormat('D MMMM Y'),
            'nomor' => $index + 1,
            'nama_perusahaan' => $row->nama_perusahaan,
            'tipe' => $tipe ?? '',
            'notes' => $row->notulen,
            'created_by' => $row->created_by,
            'created_at' => Carbon::parse($row->created_at)->format('d-m-Y H:i:s'),
            'aksi' => $aksi,
        ];
    }

    private function buildActivityDetailResult(int $userId, $matched, string $periode, array $data): array
    {
        return [
            'user_id' => $userId,
            'sales_name' => $matched->nama_sales,
            'cabang' => $matched->cabang,
            'periode' => $periode,
            'data' => $data,
        ];
    }
}
