<?php

namespace App\Services\Pks;

use App\Models\Pks;
use App\Models\PksWizardStatus;
use App\Models\Quotation;
use App\Models\Site;
use App\Models\Spk;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PksQueryService
{
    public function getPksList(Request $request): array
    {
        $tglDari = $request->tgl_dari ?? Carbon::now()->startOfMonth()->subMonths(6)->toDateString();
        $tglSampai = $request->tgl_sampai ?? Carbon::now()->toDateString();

        $query = Pks::select([
            'sl_pks.id', 'sl_pks.leads_id', 'sl_pks.nomor', 'sl_pks.nama_perusahaan',
            'sl_pks.tgl_pks', 'sl_pks.kontrak_awal', 'sl_pks.kontrak_akhir',
            'sl_pks.status_pks_id', 'sl_pks.wizard_status_id', 'sl_pks.wizard_current_step',
            'sl_pks.wizard_completed_steps', 'sl_pks.initialized_at', 'sl_pks.created_at', 'sl_pks.created_by',
        ])
            ->with(['statusPks:id,nama', 'wizardStatus:id,kode,nama', 'sites:id,pks_id,nama_site'])
            ->leftJoin('sl_leads', 'sl_pks.leads_id', '=', 'sl_leads.id')
            ->orderBy('sl_pks.created_at', 'desc');

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $searchBy = $request->get('search_by', 'nama_perusahaan');
            if ($searchBy === 'nama_perusahaan') {
                $searchTerm = str_contains($searchTerm, ' ') ? '"' . $searchTerm . '"' : $searchTerm . '*';
                $query->whereRaw('MATCH(sl_pks.nama_perusahaan) AGAINST(? IN BOOLEAN MODE)', [$searchTerm]);
            } elseif (in_array($searchBy, ['nomor', 'created_by'], true)) {
                $query->where("sl_pks.{$searchBy}", 'LIKE', '%' . $searchTerm . '%');
            }
        } else {
            $query->whereBetween(DB::raw('DATE(COALESCE(sl_pks.tgl_pks, sl_pks.initialized_at, sl_pks.created_at))'), [$tglDari, $tglSampai]);
        }

        if ($request->filled('status')) { $query->where('sl_pks.status_pks_id', $request->status); }
        if ($request->filled('branch')) { $query->where('sl_leads.branch_id', $request->branch); }

        if ($request->filled('status_berlaku')) {
            $this->applyStatusBerlakuFilter($query, $request->status_berlaku, Carbon::now()->toDateString());
        }

        $pksList = $query->paginate($request->get('per_page', 15));

        $items = $pksList->getCollection()->transform(function ($pks) {
            return [
                'id' => $pks->id, 'nomor' => $pks->nomor, 'nama_perusahaan' => $pks->nama_perusahaan,
                'tgl_pks' => ($t = $pks->getRawOriginal('tgl_pks')) ? Carbon::parse($t)->locale('id')->isoFormat('D MMMM Y') : null,
                'initialized_at' => $pks->getRawOriginal('initialized_at') ?: $pks->getRawOriginal('created_at'),
                'nama_site' => $pks->sites->pluck('nama_site')->toArray(),
                'kontrak_awal' => $pks->getRawOriginal('kontrak_awal'),
                'kontrak_akhir' => $pks->getRawOriginal('kontrak_akhir'),
                'formatted_kontrak_awal' => ($ka = $pks->getRawOriginal('kontrak_awal')) ? Carbon::parse($ka)->locale('id')->isoFormat('D MMMM Y') : null,
                'formatted_kontrak_akhir' => ($ka = $pks->getRawOriginal('kontrak_akhir')) ? Carbon::parse($ka)->locale('id')->isoFormat('D MMMM Y') : null,
                'status' => $pks->statusPks->nama ?? '-',
                'wizard_status' => $pks->wizardStatus ? ['id' => $pks->wizardStatus->id, 'kode' => $pks->wizardStatus->kode, 'nama' => $pks->wizardStatus->nama] : null,
                'wizard_current_step' => $pks->wizard_current_step,
                'wizard_completed_steps' => $pks->wizard_completed_steps ?? [],
                'is_wizard_in_progress' => in_array($pks->wizard_status_id, [PksWizardStatus::INITIALIZED, PksWizardStatus::IN_PROGRESS, PksWizardStatus::READY_TO_FINALIZE], true),
                'berakhir_dalam' => ($kb = $pks->getRawOriginal('kontrak_akhir')) ? $this->hitungBerakhirKontrak($kb) : null,
                'status_berlaku' => ($kb = $pks->getRawOriginal('kontrak_akhir')) ? $this->getStatusBerlaku($kb) : null,
                'created_at' => $pks->getRawOriginal('created_at'), 'created_by' => $pks->created_by,
            ];
        })->toArray();

        return ['data' => $items, 'pagination' => [
            'current_page' => $pksList->currentPage(), 'last_page' => $pksList->lastPage(),
            'total' => $pksList->total(), 'total_per_page' => $pksList->count(),
        ], 'meta' => ['tgl_dari' => $tglDari, 'tgl_sampai' => $tglSampai]];
    }

    private function applyStatusBerlakuFilter($query, string $statusBerlaku, string $now): void
    {
        $duaBulan = Carbon::now()->addDays(60)->toDateString();
        $tigaBulan = Carbon::now()->addDays(90)->toDateString();
        switch ($statusBerlaku) {
            case 'kontrak_habis': $query->whereDate('sl_pks.kontrak_akhir', '<=', $now); break;
            case 'berakhir_2_bulan': $query->whereDate('sl_pks.kontrak_akhir', '>', $now)->whereDate('sl_pks.kontrak_akhir', '<=', $duaBulan); break;
            case 'berakhir_3_bulan': $query->whereDate('sl_pks.kontrak_akhir', '>', $duaBulan)->whereDate('sl_pks.kontrak_akhir', '<=', $tigaBulan); break;
            case 'lebih_3_bulan': $query->whereDate('sl_pks.kontrak_akhir', '>', $tigaBulan); break;
        }
    }

    public function getPksDetail($id): array
    {
        $pks = Pks::with([
            'leads.kebutuhan:id,nama', 'statusPks:id,nama',
            'sites:id,pks_id,nama_site,kota,penempatan,quotation_id,created_at',
            'spk.spkSites:id,spk_id,nama_site,kota,penempatan,quotation_id',
            'perjanjian:id,pks_id,pasal,judul,raw_text,created_by',
            'activities:id,pks_id,tgl_activity,notes,tipe,created_by', 'ruleThr:id,nama',
        ])->find($id);

        if (!$pks) { throw new ModelNotFoundException('PKS not found'); }

        $leadsMapped = $pks->leads ? [
            'id' => $pks->leads->id, 'nama_perusahaan' => $pks->leads->nama_perusahaan,
            'nomor_leads' => $pks->leads->nomor, 'kebutuhan_leads' => $pks->leads->kebutuhan->first()?->nama,
            'negara' => $pks->leads->negara, 'bidang_perusahaan' => $pks->leads->bidang_perusahaan,
            'pma_pmdn' => $pks->leads->pma, 'provinsi' => $pks->leads->provinsi,
            'kecamatan' => $pks->leads->kecamatan, 'kelurahan' => $pks->leads->kelurahan,
            'alamat' => $pks->leads->alamat, 'pic' => $pks->leads->pic, 'jabatan' => $pks->leads->jabatan,
        ] : null;

        $pksMapped = [
            'id' => $pks->id, 'nomor' => $pks->nomor, 'link_pks_disetujui' => $pks->link_pks_disetujui,
            'status' => $pks->statusPks?->nama,
            'formatted_kontrak_awal' => Carbon::parse($pks->kontrak_awal)->isoFormat('D MMMM Y'),
            'formatted_kontrak_akhir' => Carbon::parse($pks->kontrak_akhir)->isoFormat('D MMMM Y'),
            'berakhir_dalam' => $this->hitungBerakhirKontrak($pks->kontrak_akhir),
            'activities' => $pks->activities->map(fn($a) => ['id' => $a->id, 'tgl_activity' => $a->tgl_activity, 'notes' => $a->notes, 'tipe' => $a->tipe, 'created_by' => $a->created_by])->toArray(),
            'perjanjian' => $pks->perjanjian->map(fn($p) => ['id' => $p->id, 'pasal' => $p->pasal, 'judul' => $p->judul, 'raw_text' => $p->raw_text, 'created_by' => $p->created_by])->toArray(),
        ];

        $quotationDataArray = [];
        $spkarray = [];

        if ($pks->sites->isNotEmpty()) {
            $siteData = Site::where('pks_id', $pks->id)->whereNotNull('quotation_id')->whereNull('deleted_at')
                ->select('quotation_id', 'spk_id')->distinct()->get();
            $quotationIds = $siteData->pluck('quotation_id')->filter()->unique()->values();
            $spkIds = $siteData->pluck('spk_id')->filter()->unique()->values();

            $quotations = Quotation::with([
                'quotationDetails.quotationDetailHpps', 'quotationDetails.quotationDetailCosses',
                'quotationDetails.wage', 'quotationDetails.quotationDetailRequirements',
                'quotationDetails.quotationDetailTunjangans', 'leads', 'statusQuotation',
                'quotationSites', 'quotationPics', 'quotationAplikasis', 'quotationKaporlaps',
                'quotationDevices', 'quotationChemicals', 'quotationOhcs', 'quotationTrainings',
                'quotationKerjasamas', 'managementFee',
            ])->whereIn('id', $quotationIds)->get()->keyBy('id');

            $spks = Spk::select('id', 'nomor', 'leads_id', 'tgl_spk')->whereIn('id', $spkIds)->get()->keyBy('id');
            $resourceCache = [];
            foreach ($siteData as $item) {
                $qid = $item->quotation_id;
                if (!array_key_exists($qid, $resourceCache)) {
                    $quotation = $quotations->get($qid);
                    $resourceCache[$qid] = $quotation ? new \App\Http\Resources\QuotationResource($quotation) : null;
                }
                if ($resourceCache[$qid]) { $quotationDataArray[] = $resourceCache[$qid]; }
                $spk = $spks->get($item->spk_id);
                if ($spk) { $spkarray[] = $spk; }
            }
        }

        $sitesInfo = $pks->spk && $pks->spk->spkSites
            ? $pks->spk->spkSites->map(fn($site) => ['id' => $site->id, 'nama_site' => $site->nama_site, 'kota' => $site->kota, 'penempatan' => $site->penempatan, 'quotation_id' => $site->quotation_id])->toArray()
            : $pks->sites->sortByDesc('created_at')->unique('nama_site')->values()->map(fn($site) => ['id' => $site->id, 'nama_site' => $site->nama_site, 'kota' => $site->kota, 'penempatan' => $site->penempatan, 'quotation_id' => $site->quotation_id])->toArray();

        return ['data' => ['pks_mapped' => $pksMapped, 'leads_mapped' => $leadsMapped],
            'quotation_data' => $quotationDataArray, 'spk_data' => $spkarray, 'sites_info' => $sitesInfo];
    }

    public function getTemplateData($pks): array
    {
        $company = $pks->company; $kebutuhan = $pks->kebutuhan;
        $ruleThr = $pks->ruleThr; $salaryRule = $pks->salaryRule; $leads = $pks->leads;
        return [
            'pks' => ['nomor' => $pks->nomor, 'tanggal_pks' => Carbon::parse($pks->tgl_pks)->isoFormat('D MMMM Y'),
                'kontrak_awal' => Carbon::parse($pks->kontrak_awal)->isoFormat('D MMMM Y'),
                'kontrak_akhir' => Carbon::parse($pks->kontrak_akhir)->isoFormat('D MMMM Y')],
            'perusahaan' => ['nama' => $leads->nama_perusahaan, 'alamat' => $leads->alamat, 'pic' => $leads->pic, 'nomor' => $leads->nomor],
            'penyedia' => ['nama' => $company->name ?? '', 'direktur' => $company->nama_direktur ?? '', 'alamat' => $company->address ?? '',
                'bank' => ['nama' => 'MANDIRI', 'cabang' => 'KCP SURABAYA RUNGKUT MEGAH RAYA', 'rekening' => '1420001290823', 'nama_rekening' => $company->name ?? '']],
            'layanan' => ['nama' => $kebutuhan->nama ?? '', 'kebutuhan_id' => $pks->layanan_id],
            'rule_thr' => ['hari_penagihan_invoice' => $ruleThr->hari_penagihan_invoice ?? 0,
                'hari_pembayaran_invoice' => $ruleThr->hari_pembayaran_invoice ?? 0,
                'hari_rilis_thr' => $ruleThr->hari_rilis_thr ?? 0],
            'salary_rule' => ['cutoff' => $salaryRule->cutoff ?? '', 'crosscheck_absen' => $salaryRule->crosscheck_absen ?? '',
                'pengiriman_invoice' => $salaryRule->pengiriman_invoice ?? '', 'perkiraan_invoice_diterima' => $salaryRule->perkiraan_invoice_diterima ?? '',
                'pembayaran_invoice' => $salaryRule->pembayaran_invoice ?? '', 'rilis_payroll' => $salaryRule->rilis_payroll ?? ''],
            'sites' => $pks->sites->map(fn($site) => ['nama_site' => $site->nama_site, 'alamat' => $site->penempatan, 'kota' => $site->kota]),
        ];
    }

    public function getPerjanjianTemplateDataById($id): array
    {
        $pks = Pks::with([
            'leads', 'sites:id,pks_id,nama_site,penempatan,kota',
            'company:id,name,code,nama_direktur,address', 'kebutuhan:id,nama',
            'ruleThr:id,hari_penagihan_invoice,hari_pembayaran_invoice,hari_rilis_thr',
            'salaryRule:id,cutoff,crosscheck_absen,pengiriman_invoice,perkiraan_invoice_diterima,pembayaran_invoice,rilis_payroll',
        ])->find($id);

        if (!$pks) { throw new ModelNotFoundException('PKS not found'); }
        return $this->getTemplateData($pks);
    }

    public function hitungBerakhirKontrak($tanggalBerakhir): string
    {
        if (is_null($tanggalBerakhir)) { return '-'; }
        $tanggalSekarang = Carbon::now();
        $tanggalBerakhir = Carbon::createFromFormat('Y-m-d', $tanggalBerakhir);
        if ($tanggalSekarang->greaterThanOrEqualTo($tanggalBerakhir)) { return 'Kontrak habis'; }
        $selisih = $tanggalSekarang->diff($tanggalBerakhir);
        $hasil = [];
        if ($selisih->y > 0) { $hasil[] = "{$selisih->y} tahun"; }
        if ($selisih->m > 0) { $hasil[] = "{$selisih->m} bulan"; }
        if ($selisih->d > 0) { $hasil[] = "{$selisih->d} hari"; }
        return implode(', ', $hasil);
    }

    public function getStatusBerlaku($tanggalBerakhir): string
    {
        $selisih = $this->selisihKontrakBerakhir($tanggalBerakhir);
        if ($selisih <= 0) { return 'Kontrak Habis'; }
        if ($selisih <= 60) { return 'Berakhir dalam 2 bulan'; }
        if ($selisih <= 90) { return 'Berakhir dalam 3 bulan'; }
        return 'Lebih dari 3 Bulan';
    }

    private function selisihKontrakBerakhir($tanggalBerakhir): int
    {
        if (is_null($tanggalBerakhir)) { return 0; }
        $tanggalSekarang = Carbon::now();
        $tanggalBerakhir = Carbon::createFromFormat('Y-m-d', $tanggalBerakhir);
        if ($tanggalSekarang->greaterThanOrEqualTo($tanggalBerakhir)) { return 0; }
        return $tanggalSekarang->diffInDays($tanggalBerakhir);
    }

    public function isWizardFinalized(Pks $pks): bool
    {
        if ($pks->wizard_status_id === null) { return true; }
        return (int) $pks->wizard_status_id === PksWizardStatus::FINALIZED;
    }
}
