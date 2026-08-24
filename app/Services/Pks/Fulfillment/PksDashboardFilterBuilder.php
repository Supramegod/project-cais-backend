<?php

namespace App\Services\Pks\Fulfillment;

use App\Models\Pks;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Filter & search daftar PKS untuk dashboard. Polanya diangkat dari
 * PksFulfillmentController@dashboard supaya dashboard baru tidak menyalin ulang
 * aturan yang sama (status aktif, search_by, branch, rentang tanggal).
 */
class PksDashboardFilterBuilder
{
    private const SEARCH_BY_LIKE = ['nomor', 'created_by'];

    public function defaultTglDari(): string
    {
        return Carbon::now()->startOfMonth()->subMonths(6)->toDateString();
    }

    public function defaultTglSampai(): string
    {
        return Carbon::now()->toDateString();
    }

    public function build(Request $request, string $tglDari, string $tglSampai): Builder
    {
        $query = Pks::query()
            ->leftJoin('sl_leads', 'sl_pks.leads_id', '=', 'sl_leads.id')
            ->where('sl_pks.status_pks_id', Pks::STATUS_AKTIF);

        if ($request->filled('search')) {
            $this->applySearch($query, $request);
        } else {
            $query->whereBetween(
                DB::raw('DATE(COALESCE(sl_pks.tgl_pks, sl_pks.initialized_at, sl_pks.created_at))'),
                [$tglDari, $tglSampai]
            );
        }

        if ($request->filled('branch')) {
            $query->where('sl_leads.branch_id', $request->branch);
        }

        return $query;
    }

    private function applySearch(Builder $query, Request $request): void
    {
        $searchTerm = $request->search;
        $searchBy = $request->input('search_by', 'nama_perusahaan');

        if ($searchBy === 'nama_perusahaan') {
            $searchTerm = str_contains($searchTerm, ' ')
                ? '"'.$searchTerm.'"'
                : $searchTerm.'*';
            $query->whereRaw('MATCH(sl_pks.nama_perusahaan) AGAINST(? IN BOOLEAN MODE)', [$searchTerm]);

            return;
        }

        if (in_array($searchBy, self::SEARCH_BY_LIKE, true)) {
            $query->where("sl_pks.{$searchBy}", 'LIKE', '%'.$searchTerm.'%');
        }
    }
}
