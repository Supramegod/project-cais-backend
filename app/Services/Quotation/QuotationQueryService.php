<?php

namespace App\Services\Quotation;

use App\Models\Quotation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class QuotationQueryService
{
    /**
     * Get filtered quotations list with search, date range, and filters.
     */
    public function getFilteredQuotationsList(Request $request): LengthAwarePaginator
    {
        $query = Quotation::select([
            'id', 'leads_id', 'nomor', 'step', 'jumlah_site', 'company_id',
            'company', 'kebutuhan', 'nama_perusahaan', 'tgl_quotation',
            'status_quotation_id', 'jenis_kontrak', 'created_at', 'created_by',
        ])
            ->with([
                'quotationSites:id,quotation_id,nama_site',
                'statusQuotation:id,nama',
            ])
            ->byUserRole()
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $searchBy = $request->get('search_by', 'nama_perusahaan');

            if ($searchBy === 'nama_perusahaan') {
                $searchTerm = str_contains($searchTerm, ' ')
                    ? '"'.$searchTerm.'"'
                    : $searchTerm.'*';
                $query->whereRaw('MATCH(nama_perusahaan) AGAINST(? IN BOOLEAN MODE)', [$searchTerm]);
            } elseif (in_array($searchBy, ['nomor', 'kebutuhan', 'created_by', 'jenis_kontrak'])) {
                $query->where($searchBy, 'LIKE', '%'.$searchTerm.'%');
            }
        } else {
            $tglDari = $request->get('tgl_dari', Carbon::today()->subMonths(6)->toDateString());
            $tglSampai = $request->get('tgl_sampai', Carbon::today()->toDateString());
            $query->whereBetween('tgl_quotation', [$tglDari, $tglSampai]);
        }

        if ($request->filled('branch')) {
            $query->whereHas('leads', fn ($q) => $q->where('branch_id', $request->branch));
        }
        if ($request->filled('platform')) {
            $query->whereHas('leads', fn ($q) => $q->where('platform_id', $request->platform));
        }
        if ($request->filled('status')) {
            $query->where('status_quotation_id', $request->status);
        }
        if ($request->filled('company')) {
            $query->where('company_id', $request->company);
        }
        if ($request->filled('kebutuhan_id')) {
            $query->where('kebutuhan_id', $request->kebutuhan_id);
        }
        if ($request->filled('status_berlaku')) {
            $this->applyStatusBerlakuFilter($query, $request->status_berlaku);
        }

        return $query->paginate($request->get('per_page', 15));
    }

    /**
     * Filter kontrak berdasarkan sisa masa berlaku (kolom `kontrak_selesai`).
     *
     * Nilai yang didukung: kontrak_habis, berakhir_2_bulan, berakhir_3_bulan, lebih_3_bulan.
     */
    public function applyStatusBerlakuFilter(Builder $query, string $statusBerlaku, string $column = 'kontrak_selesai'): Builder
    {
        $now = Carbon::now()->toDateString();
        $duaBulan = Carbon::now()->addDays(60)->toDateString();
        $tigaBulan = Carbon::now()->addDays(90)->toDateString();

        switch ($statusBerlaku) {
            case 'kontrak_habis':
                $query->whereNotNull($column)
                    ->whereDate($column, '<=', $now);
                break;
            case 'berakhir_2_bulan':
                $query->whereDate($column, '>', $now)
                    ->whereDate($column, '<=', $duaBulan);
                break;
            case 'berakhir_3_bulan':
                $query->whereDate($column, '>', $duaBulan)
                    ->whereDate($column, '<=', $tigaBulan);
                break;
            case 'lebih_3_bulan':
                $query->whereDate($column, '>', $tigaBulan);
                break;
        }

        return $query;
    }

    /**
     * Label status berlaku kontrak, mengikuti aturan yang dipakai modul PKS.
     */
    public function getStatusBerlaku(?string $tanggalBerakhir): string
    {
        $selisih = $this->selisihKontrakBerakhir($tanggalBerakhir);

        if ($selisih <= 0) {
            return 'Kontrak Habis';
        }
        if ($selisih <= 60) {
            return 'Berakhir dalam 2 bulan';
        }
        if ($selisih <= 90) {
            return 'Berakhir dalam 3 bulan';
        }

        return 'Lebih dari 3 Bulan';
    }

    private function selisihKontrakBerakhir(?string $tanggalBerakhir): int
    {
        if ($tanggalBerakhir === null || $tanggalBerakhir === '') {
            return 0;
        }

        $tanggalSekarang = Carbon::now();
        $tanggalBerakhir = Carbon::parse($tanggalBerakhir);

        if ($tanggalSekarang->greaterThanOrEqualTo($tanggalBerakhir)) {
            return 0;
        }

        return (int) $tanggalSekarang->diffInDays($tanggalBerakhir);
    }
}
