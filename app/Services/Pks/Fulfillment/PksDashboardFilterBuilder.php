<?php

namespace App\Services\Pks\Fulfillment;

use App\Models\Pks;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Filter & search daftar PKS untuk dashboard. Dipakai bersama oleh
 * PksFulfillmentController@dashboard dan PksItemFulfillmentDashboardController
 * supaya aturan yang sama (status aktif, search_by, branch, rentang tanggal,
 * batas per_page) hanya hidup di satu tempat.
 */
class PksDashboardFilterBuilder
{
    public const PER_PAGE_DEFAULT = 15;

    public const PER_PAGE_MAX = 100;

    private const SEARCH_BY_FULLTEXT = 'nama_perusahaan';

    private const SEARCH_BY_LIKE = ['nomor', 'created_by'];

    public function defaultTglDari(): string
    {
        return Carbon::now()->startOfMonth()->subMonths(6)->toDateString();
    }

    public function defaultTglSampai(): string
    {
        return Carbon::now()->toDateString();
    }

    /**
     * per_page yang aman: nilai non-numerik atau di bawah 1 jatuh ke default,
     * nilai di atas PER_PAGE_MAX dijepit. Tanpa ini per_page negatif membuat
     * Builder::limit() diabaikan sehingga seluruh himpunan terkirim dalam satu
     * response, dan MySQL menolak query "offset 0" tanpa limit.
     */
    public function perPage(Request $request): int
    {
        $diminta = $request->input('per_page');

        if (! is_numeric($diminta) || (int) $diminta < 1) {
            return self::PER_PAGE_DEFAULT;
        }

        return min(self::PER_PAGE_MAX, (int) $diminta);
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

    /**
     * search_by tak dikenal ditolak, bukan diabaikan: mengabaikannya membuat
     * pencarian lolos tanpa predikat apa pun sementara filter tanggal juga sudah
     * dilewati, sehingga seluruh PKS aktif ikut terkirim.
     *
     * @throws ValidationException
     */
    private function applySearch(Builder $query, Request $request): void
    {
        $searchTerm = (string) $request->input('search');
        $searchBy = $request->input('search_by', self::SEARCH_BY_FULLTEXT);

        if ($searchBy === self::SEARCH_BY_FULLTEXT) {
            $searchTerm = str_contains($searchTerm, ' ')
                ? '"'.$searchTerm.'"'
                : $searchTerm.'*';
            $query->whereRaw('MATCH(sl_pks.nama_perusahaan) AGAINST(? IN BOOLEAN MODE)', [$searchTerm]);

            return;
        }

        if (! in_array($searchBy, self::SEARCH_BY_LIKE, true)) {
            throw ValidationException::withMessages([
                'search_by' => 'search_by hanya boleh salah satu dari: '
                    .implode(', ', array_merge([self::SEARCH_BY_FULLTEXT], self::SEARCH_BY_LIKE)).'.',
            ]);
        }

        $query->where("sl_pks.{$searchBy}", 'LIKE', '%'.$searchTerm.'%');
    }
}
