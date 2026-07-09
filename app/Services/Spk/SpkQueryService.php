<?php

namespace App\Services\Spk;

use App\Models\Company;
use App\Models\Leads;
use App\Models\Quotation;
use App\Models\QuotationSite;
use App\Models\Spk;
use App\Models\SpkSite;
use App\Services\Quotation\QuotationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SpkQueryService
{
    private QuotationService $quotationService;

    public function __construct(QuotationService $quotationService)
    {
        $this->quotationService = $quotationService;
    }

    public function list(array $filters): array
    {
        $tglDari = $filters['tgl_dari'] ?? Carbon::now()->startOfMonth()->subMonths(3)->toDateString();
        $tglSampai = $filters['tgl_sampai'] ?? Carbon::now()->toDateString();
        $query = Spk::select(['sl_spk.id', 'sl_spk.leads_id', 'sl_spk.nomor', 'sl_spk.tgl_spk', 'sl_spk.nama_perusahaan', 'sl_spk.status_spk_id', 'sl_spk.created_by', 'sl_spk.created_at'])
            ->with(['leads:id,nama_perusahaan', 'statusSpk:id,nama', 'spkSites:id,spk_id,nama_site'])
            ->orderBy('sl_spk.created_at', 'desc');
        $query->leftJoin('sl_leads', 'sl_spk.leads_id', '=', 'sl_leads.id');
        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $searchBy = $filters['search_by'] ?? 'nama_perusahaan';
            if ($searchBy === 'nama_perusahaan') {
                $searchTerm = str_contains($searchTerm, ' ') ? '"' . $searchTerm . '"' : $searchTerm . '*';
                $query->whereRaw("MATCH(sl_spk.nama_perusahaan) AGAINST(? IN BOOLEAN MODE)", [$searchTerm]);
            } elseif (in_array($searchBy, ['nomor', 'created_by'])) {
                $query->where("sl_spk.{$searchBy}", 'LIKE', '%' . $searchTerm . '%');
            }
        } else {
            $query->whereBetween('sl_spk.tgl_spk', [$tglDari, $tglSampai]);
        }
        if (!empty($filters['branch'])) {
            $query->where('sl_leads.branch_id', $filters['branch']);
        }
        if (!empty($filters['status'])) {
            $query->where('sl_spk.status_spk_id', $filters['status']);
        }
        $perPage = $filters['per_page'] ?? 15;
        $data = $query->paginate($perPage);
        $data->getCollection()->transform(function ($spk) {
            return [
                'id' => $spk->id,
                'nomor_spk' => $spk->nomor,
                'tgl_spk' => Carbon::parse($spk->getRawOriginal('tgl_spk'))->locale('id')->isoFormat('D MMMM Y'),
                'nama_perusahaan' => $spk->leads->nama_perusahaan ?? $spk->nama_perusahaan,
                'nama_site' => $spk->spkSites->pluck('nama_site')->toArray(),
                'status' => $spk->statusSpk->nama ?? '-',
                'created_by' => $spk->created_by,
            ];
        });
        return [
            'list' => $data->items(),
            'pagination' => ['current_page' => $data->currentPage(), 'last_page' => $data->lastPage(), 'total' => $data->total(), 'total_per_page' => $data->count()],
        ];
    }

    public function listTerhapus()
    {
        return Spk::onlyTrashed()
            ->select('id', 'nomor', 'tgl_spk', 'nama_perusahaan', 'leads_id', 'quotation_id', 'status_spk_id', 'deleted_at', 'deleted_by', 'created_by')
            ->with(['leads:id,nama_perusahaan,nomor', 'quotation:id,nomor,leads_id,tgl_quotation'])
            ->get();
    }

    public function availableQuotation()
    {
        return Quotation::with(['leads.timSalesD'])
            ->whereNull('deleted_at')->where('status_quotation_id', 3)->where('is_aktif', 1)
            ->whereHas('leads.timSalesD', fn($q) => $q->where('user_id', Auth::user()->id))
            ->whereHas('quotationSites', fn($q) => $q->whereNull('deleted_at')->whereDoesntHave('spkSite'))
            ->get()
            ->map(fn($q) => [
                'id' => $q->id,
                'nomor' => $q->nomor,
                'quotation' => $q->nomor,
                'tgl_quotation' => Carbon::parse($q->tgl_quotation)->isoFormat('D MMMM Y'),
                'nama_perusahaan' => $q->nama_perusahaan,
                'jumlah_site' => $q->jumlah_site,
                'kebutuhan' => $q->kebutuhan,
                'layanan' => $q->kebutuhan,
            ]);
    }

    public function availableLeads()
    {
        return Leads::filterByuserRole()
            ->whereHas('quotations.quotationSites', fn($q) => $q->whereNull('deleted_at')->whereDoesntHave('spkSite', fn($q2) => $q2->whereNull('deleted_at')))
            ->whereHas('quotations', fn($q) => $q->whereNull('deleted_at')->where('status_quotation_id', 3)->where('is_aktif', 1))
            ->select('id', 'nomor', 'nama_perusahaan', 'provinsi', 'kota')
            ->distinct()->orderBy('id', 'desc')->get();
    }

    public function view(int $id): ?array
    {
        $spk = Spk::with([
            'leads', 'leads.jabatanPic', 'statusSpk',
            'spkSites.quotation', 'spkSites.quotation.quotationPics.jabatan',
            'spkSites.quotation.company', 'spkSites.quotation.quotationDetails',
            'spkSites.quotation.wage', 'spkSites.quotation.quotationTrainings',
            'spkSites.quotation.salaryRule', 'spkSites.quotation.ruleThr',
        ])->find($id);
        if (!$spk) return null;

        $spkInfo = ['nomor_spk' => $spk->nomor, 'tanggal_spk' => $spk->tgl_spk, 'link_spk_disetujui' => $spk->link_spk_disetujui ?? null, 'status' => $spk->statusSpk?->nama ?? null];
        $leadsInfo = [
            'id' => $spk->leads->id ?? null, 'nama_perusahaan' => $spk->leads->nama_perusahaan ?? null,
            'telp_perusahaan' => $spk->leads->telp_perusahaan ?? null, 'nama_pic' => $spk->leads->pic ?? null,
            'telepon_pic' => $spk->leads->no_telp ?? null, 'email_pic' => $spk->leads->email ?? null,
            'alamat_perusahaan' => $spk->leads->alamat ?? null, 'jabatan_nama' => $spk->leads->jabatanPic?->nama ?? null,
        ];

        $uniqueQuotations = collect();
        foreach ($spk->spkSites as $spkSite) {
            if ($spkSite->quotation && !$uniqueQuotations->contains('id', $spkSite->quotation->id)) {
                $uniqueQuotations->push($spkSite->quotation);
            }
        }

        $quotationsInfo = [];
        foreach ($uniqueQuotations as $quotation) {
            $quotationsInfo[] = $this->buildQuotationViewData($quotation);
        }

        $sitesInfo = $spk->spkSites->map(fn($site) => [
            'id' => $site->id, 'nama_site' => $site->nama_site, 'kota' => $site->kota,
            'penempatan' => $site->penempatan, 'quotation_id' => $site->quotation_id,
        ]);
        unset($uniqueQuotations);
        return ['spk' => $spkInfo, 'leads' => $leadsInfo, 'quotations' => $quotationsInfo, 'sites' => $sitesInfo];
    }

    private function buildQuotationViewData($quotation): array
    {
        $calculated = null;
        try {
            $calculated = $this->quotationService->calculateQuotation($quotation);
        } catch (\Exception $e) {
            Log::error("Error calculating quotation in SPK view: " . $e->getMessage());
        }
        $bpjs = [];
        if ($calculated && isset($calculated->calculation_summary)) {
            $s = $calculated->calculation_summary;
            $bpjs = ['persen_bpjs_jkk' => $s->persen_bpjs_jkk ?? 0, 'persen_bpjs_jkm' => $s->persen_bpjs_jkm ?? 0, 'persen_bpjs_jht' => $s->persen_bpjs_jht ?? 0, 'persen_bpjs_jp' => $s->persen_bpjs_jp ?? 0, 'persen_bpjs_kesehatan' => $s->persen_bpjs_kesehatan ?? 0];
        } else {
            $d = $quotation->quotationDetailCosses->first();
            $bpjs = ['persen_bpjs_jkk' => $d->persen_bpjs_jkk ?? 0, 'persen_bpjs_jkm' => $d->persen_bpjs_jkm ?? 0, 'persen_bpjs_jht' => $d->persen_bpjs_jht ?? 0, 'persen_bpjs_jp' => $d->persen_bpjs_jp ?? 0, 'persen_bpjs_kesehatan' => $d->persen_bpjs_kesehatan ?? 0];
        }
        unset($calculated);

        $company = null;
        if ($quotation->relationLoaded('company') && $quotation->company instanceof Company) {
            $company = $quotation->company;
        } elseif (!empty($quotation->company_id)) {
            $company = Company::find($quotation->company_id);
        }
        $totalHc = $quotation->quotationDetails->sum('jumlah_hc');
        $wage = $quotation->wage->first();

        return [
            'id' => $quotation->id, 'nomor_quotation' => $quotation->nomor ?? null, 'nama_perusahaan' => $quotation->nama_perusahaan ?? null,
            'status_quotation' => $quotation->status_quotation_id ?? null, 'kebutuhan' => $quotation->kebutuhan ?? null, 'jenis_kontrak' => $quotation->jenis_kontrak ?? null,
            'tanggal_penempatan' => $quotation->tgl_penempatan ?? null, 'company_name' => $company?->name ?? null, 'company_address' => $company?->address ?? null,
            'tanggal_quotation' => $quotation->tgl_quotation ?? null, 'npwp' => $quotation->npwp ?? null, 'materai' => $quotation->materai ?? null,
            'total_hc' => $totalHc, 'alamat_npwp' => $quotation->alamat_npwp ?? null,
            'durasi_kerjasama' => $quotation->durasi_kerjasama ?? null, 'durasi_karyawan' => $quotation->durasi_karyawan ?? null,
            'evaluasi_kontrak' => $quotation->evaluasi_kontrak ?? null, 'evaluasi_karyawan' => $quotation->evaluasi_karyawan ?? null,
            'mulai_kontrak' => $quotation->mulai_kontrak ?? null, 'kontrak_selesai' => $quotation->kontrak_selesai ?? null,
            'hari_kerja' => $quotation->hari_kerja ?? null, 'jam_kerja' => $quotation->jam_kerja ?? null, 'shift_kerja' => $quotation->shift_kerja ?? null,
            'kunjungan_operasional' => $quotation->kunjungan_operasional ?? null, 'kunjungan_tim_crm' => $quotation->kunjungan_tim_crm ?? null,
            'keterangan_kunjungan_tim_crm' => $quotation->keterangan_kunjungan_tim_crm ?? null, 'keterangan_kunjungan_operasional' => $quotation->keterangan_kunjungan_operasional ?? null,
            'persen_bpjs_jkk' => $bpjs['persen_bpjs_jkk'], 'persen_bpjs_jkm' => $bpjs['persen_bpjs_jkm'], 'persen_bpjs_jht' => $bpjs['persen_bpjs_jht'],
            'persen_bpjs_jp' => $bpjs['persen_bpjs_jp'], 'persen_bpjs_kesehatan' => $bpjs['persen_bpjs_kesehatan'],
            'kompensasi' => $wage?->kompensasi ?? null, 'lembur' => $wage?->lembur ?? null, 'thr' => $wage?->thr ?? null,
            'joker_reliever' => $quotation->joker_reliever ?? null, 'syarat_invoice' => $quotation->syarat_invoice ?? null,
            'top' => $quotation->top ?? null, 'jumlah_hari_invoice' => $quotation->jumlah_hari_invoice ?? null,
            'tipe_hari_invoice' => $quotation->tipe_hari_invoice ?? null, 'alamat_penagihan_invoice' => $quotation->alamat_penagihan_invoice ?? null,
            'catatan_site' => $quotation->catatan_site ?? null, 'cuti' => $quotation->cuti ?? null, 'gaji_saat_cuti' => $quotation->gaji_saat_cuti ?? null,
            'prorate' => $quotation->prorate ?? null,
            'status_serikat' => $quotation->ada_serikat === 'Tidak Ada' ? 'Tidak Ada' : $quotation->status_serikat,
            'ada_serikat' => $quotation->status_serikat ? 'Ada' : 'Tidak Ada',
            'salary_rule' => $quotation->salaryRule ? ['id' => $quotation->salaryRule->id, 'nama' => $quotation->salaryRule->nama_salary_rule ?? null, 'cutoff' => $quotation->salaryRule->cutoff ?? null, 'crosscheck' => $quotation->salaryRule->crosscheck_absen ?? null, 'pengiriman_invoice' => $quotation->salaryRule->pengiriman_invoice ?? null, 'perkiraan_invoice_diterima' => $quotation->salaryRule->perkiraan_invoice_diterima ?? null, 'rilis_payroll' => $quotation->salaryRule->rilis_payroll ?? null] : null,
            'rulethr' => $quotation->ruleThr ? ['id' => $quotation->ruleThr->id, 'nama' => $quotation->ruleThr->nama ?? null, 'hari_rilis_thr' => $quotation->ruleThr->hari_rilis_thr ?? null, 'hari_pembayaran_invoice' => $quotation->ruleThr->hari_pembayaran_invoice ?? null, 'hari_penagihan_invoice' => $quotation->ruleThr->hari_penagihan_invoice ?? null] : null,
            'quotation_details' => $quotation->quotationDetails->map(fn($d) => ['id' => $d->id, 'jabatan_kebutuhan' => $d->jabatan_kebutuhan, 'jumlah_hc' => $d->jumlah_hc]),
            'quotation_pics' => $quotation->quotationPics->map(fn($p) => ['id' => $p->id, 'nama' => $p->nama, 'jabatan' => $p->jabatan?->nama ?? null, 'no_telp' => $p->no_telp, 'email' => $p->email, 'is_kuasa' => $p->is_kuasa]),
            'quotation_trainings' => $quotation->quotationTrainings->map(fn($t) => ['id' => $t->id, 'training_id' => $t->training_id, 'nama' => $t->nama]),
        ];
    }

    public function getSiteList(int $id)
    {
        return SpkSite::with(['quotation', 'quotationSite'])
            ->where('spk_id', $id)->whereNull('deleted_at')->whereDoesntHave('site')
            ->get()
            ->map(fn($site, $key) => tap($site, fn($s) => $s->no = $key + 1));
    }

    public function getSiteAvailableList(int $leadsId)
    {
        return QuotationSite::with(['quotation'])
            ->where('leads_id', $leadsId)->whereNull('deleted_at')->whereDoesntHave('spkSite')
            ->get()
            ->map(fn($site) => [
                'id' => $site->id, 'nama_site' => $site->nama_site, 'provinsi' => $site->provinsi,
                'kota' => $site->kota, 'quotation' => is_object($site->quotation) ? $site->quotation->nomor : $site->quotation,
                'ump' => $site->ump, 'umk' => $site->umk, 'nominal_upah' => $site->nominal_upah, 'penempatan' => $site->penempatan,
            ]);
    }

    public function getDeletedSpkSites(int $spkId)
    {
        if (!Spk::withTrashed()->where('id', $spkId)->exists()) {
            throw new \Exception('SPK not found');
        }
        return SpkSite::onlyTrashed()->where('spk_id', $spkId)->with(['quotation', 'quotationSite'])->get()
            ->map(fn($site) => [
                'id' => $site->id, 'nama_site' => $site->nama_site, 'quotation_site_id' => $site->quotation_site_id,
                'deleted_at' => $site->deleted_at, 'deleted_by' => $site->deleted_by,
            ]);
    }
}
