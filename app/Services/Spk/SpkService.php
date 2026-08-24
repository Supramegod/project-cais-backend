<?php

namespace App\Services\Spk;

use App\Models\Company;
use App\Models\CustomerActivity;
use App\Models\JabatanPic;
use App\Models\Leads;
use App\Models\LeadsKebutuhan;
use App\Models\Quotation;
use App\Models\QuotationAplikasi;
use App\Models\QuotationChemical;
use App\Models\QuotationDetail;
use App\Models\QuotationDetailCoss;
use App\Models\QuotationDetailHpp;
use App\Models\QuotationDetailRequirement;
use App\Models\QuotationDetailTunjangan;
use App\Models\QuotationDevices;
use App\Models\QuotationKaporlap;
use App\Models\QuotationKerjasama;
use App\Models\QuotationOhc;
use App\Models\QuotationPic;
use App\Models\QuotationSite;
use App\Models\QuotationTraining;
use App\Models\SalesActivity;
use App\Models\Spk;
use App\Models\SpkSite;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SpkService
{
    private QuotationService $quotationService;

    public function __construct(QuotationService $quotationService)
    {
        $this->quotationService = $quotationService;
    }

    /**
     * Daftar SPK dengan filter, search, dan pagination.
     */
    public function list(array $filters): array
    {
        $tglDari = $filters['tgl_dari'] ?? Carbon::now()->startOfMonth()->subMonths(3)->toDateString();
        $tglSampai = $filters['tgl_sampai'] ?? Carbon::now()->toDateString();

        $query = Spk::select([
            'sl_spk.id',
            'sl_spk.leads_id',
            'sl_spk.nomor',
            'sl_spk.tgl_spk',
            'sl_spk.nama_perusahaan',
            'sl_spk.status_spk_id',
            'sl_spk.created_by',
            'sl_spk.created_at',
        ])
            ->with([
                'leads:id,nama_perusahaan',
                'statusSpk:id,nama',
                'spkSites:id,spk_id,nama_site',
            ])
            ->orderBy('sl_spk.created_at', 'desc');

        $query->leftJoin('sl_leads', 'sl_spk.leads_id', '=', 'sl_leads.id');

        // Search
        if (! empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $searchBy = $filters['search_by'] ?? 'nama_perusahaan';

            if ($searchBy === 'nama_perusahaan') {
                $searchTerm = str_contains($searchTerm, ' ')
                    ? '"'.$searchTerm.'"'
                    : $searchTerm.'*';
                $query->whereRaw('MATCH(sl_spk.nama_perusahaan) AGAINST(? IN BOOLEAN MODE)', [$searchTerm]);
            } elseif (in_array($searchBy, ['nomor', 'created_by'])) {
                $query->where("sl_spk.{$searchBy}", 'LIKE', '%'.$searchTerm.'%');
            }
        } else {
            $query->whereBetween('sl_spk.tgl_spk', [$tglDari, $tglSampai]);
        }

        if (! empty($filters['branch'])) {
            $query->where('sl_leads.branch_id', $filters['branch']);
        }

        if (! empty($filters['status'])) {
            $query->where('sl_spk.status_spk_id', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 15;
        $data = $query->paginate($perPage);

        $data->getCollection()->transform(function ($spk) {
            return [
                'id' => $spk->id,
                'nomor_spk' => $spk->nomor,
                'tgl_spk' => Carbon::parse($spk->getRawOriginal('tgl_spk'))
                    ->locale('id')
                    ->isoFormat('D MMMM Y'),
                'nama_perusahaan' => $spk->leads->nama_perusahaan ?? $spk->nama_perusahaan,
                'nama_site' => $spk->spkSites->pluck('nama_site')->toArray(),
                'status' => $spk->statusSpk->nama ?? '-',
                'created_by' => $spk->created_by,
            ];
        });

        return [
            'list' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'total' => $data->total(),
                'total_per_page' => $data->count(),
            ],
        ];
    }

    /**
     * Daftar SPK yang sudah dihapus (soft delete).
     */
    public function listTerhapus()
    {
        return Spk::onlyTrashed()
            ->select('id', 'nomor', 'tgl_spk', 'nama_perusahaan', 'leads_id', 'quotation_id', 'status_spk_id', 'deleted_at', 'deleted_by', 'created_by')
            ->with([
                'leads:id,nama_perusahaan,nomor',
                'quotation:id,nomor,leads_id,tgl_quotation',
            ])
            ->get();
    }

    /**
     * Quotation yang tersedia untuk dibuat SPK.
     */
    public function availableQuotation()
    {
        return Quotation::with(['leads.timSalesD'])
            ->whereNull('deleted_at')
            ->where('status_quotation_id', 3)
            ->where('is_aktif', 1)
            ->whereHas('leads.timSalesD', function ($query) {
                $query->where('user_id', Auth::user()->id);
            })
            ->whereHas('quotationSites', function ($query) {
                $query->whereNull('deleted_at')
                    ->whereDoesntHave('spkSite');
            })
            ->get()
            ->map(function ($quotation) {
                return [
                    'id' => $quotation->id,
                    'nomor' => $quotation->nomor,
                    'quotation' => $quotation->nomor,
                    'tgl_quotation' => Carbon::parse($quotation->tgl_quotation)->isoFormat('D MMMM Y'),
                    'nama_perusahaan' => $quotation->nama_perusahaan,
                    'jumlah_site' => $quotation->jumlah_site,
                    'kebutuhan' => $quotation->kebutuhan,
                    'layanan' => $quotation->kebutuhan,
                ];
            });
    }

    /**
     * Leads yang tersedia untuk dibuat SPK.
     */
    public function availableLeads()
    {
        $query = Leads::filterByuserRole()
            ->whereHas('quotations.quotationSites', function ($query) {
                $query->whereNull('deleted_at')
                    ->whereDoesntHave('spkSite', function ($q) {
                        $q->whereNull('deleted_at');
                    });
            })
            ->whereHas('quotations', function ($query) {
                $query->whereNull('deleted_at')
                    ->where('status_quotation_id', 3)
                    ->where('is_aktif', 1);
            });

        return $query->select('id', 'nomor', 'nama_perusahaan', 'provinsi', 'kota')
            ->distinct()
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Membuat SPK baru.
     */
    public function add(int $leadsId, string $tanggalSpk, array $siteIds): Spk
    {
        return DB::transaction(function () use ($leadsId, $tanggalSpk, $siteIds) {
            $leads = Leads::whereNull('deleted_at')->find($leadsId);

            if (! $leads) {
                throw new \Exception("Leads dengan ID {$leadsId} tidak ditemukan atau sudah dihapus.");
            }

            // Validasi: pastikan semua site_ids termasuk dalam leads yang dipilih
            $invalidSites = QuotationSite::whereIn('id', $siteIds)
                ->where('leads_id', '!=', $leadsId)
                ->exists();

            if ($invalidSites) {
                throw new \Exception('Beberapa site yang dipilih tidak termasuk dalam leads yang dipilih.');
            }

            // Validasi: pastikan site belum memiliki SPK
            $sitesWithSPK = QuotationSite::whereIn('id', $siteIds)
                ->whereHas('spkSite')
                ->exists();

            if ($sitesWithSPK) {
                throw new \Exception('Beberapa site yang dipilih sudah memiliki SPK.');
            }

            // Ambil quotation_id dari site pertama
            $firstSite = QuotationSite::find($siteIds[0]);
            $quotationId = $firstSite ? $firstSite->quotation_id : null;

            $quotation = $quotationId ? Quotation::find($quotationId) : null;
            $companyId = $quotation?->company_id ?? 0;
            $spkNomor = $this->generateNomorNew($leads->id, $companyId);

            // Buat SPK
            $spk = Spk::create([
                'leads_id' => $leads->id,
                'nomor' => $spkNomor,
                'tgl_spk' => $tanggalSpk,
                'nama_perusahaan' => $leads->nama_perusahaan,
                'tim_sales_id' => $leads->tim_sales_id,
                'tim_sales_d_id' => $leads->tim_sales_d_id,
                'link_spk_disetujui' => null,
                'status_spk_id' => 1,
                'created_by' => Auth::user()->full_name ?? 'System',
                'created_by_user_id' => Auth::id(),
            ]);

            $this->createSpkSites($spk, $siteIds);
            $this->createCustomerActivity($leads, $spk, $spkNomor);

            // Update status quotation ke "Generated SPK" (id: 4)
            if ($quotationId) {
                Quotation::where('id', $quotationId)
                    ->where('status_quotation_id', '!=', 100)
                    ->update([
                        'status_quotation_id' => 4,
                        'updated_by' => Auth::user()->full_name ?? 'System',
                    ]);
            }

            // Update status leads ke "SPK / Closing" (id: 3)
            $statusTerminalLeads = [99, 100, 101, 102];
            if (! in_array($leads->status_leads_id, $statusTerminalLeads)) {
                $leads->update([
                    'status_leads_id' => 3,
                    'updated_by' => Auth::user()->full_name ?? 'System',
                ]);
            }

            return $spk->load(['spkSites', 'leads']);
        });
    }

    /**
     * Detail SPK dengan semua relasi.
     */
    public function view(int $id): ?array
    {
        $spk = Spk::with([
            'leads',
            'leads.jabatanPic',
            'statusSpk',
            'spkSites.quotation',
            'spkSites.quotation.quotationPics.jabatan',
            'spkSites.quotation.company',
            'spkSites.quotation.quotationDetails',
            'spkSites.quotation.wage',
            'spkSites.quotation.quotationTrainings',
            'spkSites.quotation.salaryRule',
            'spkSites.quotation.ruleThr',
        ])->find($id);

        if (! $spk) {
            return null;
        }

        // 1. Informasi SPK
        $spkInfo = [
            'nomor_spk' => $spk->nomor,
            'tanggal_spk' => $spk->tgl_spk,
            'link_spk_disetujui' => $spk->link_spk_disetujui ?? null,
            'status' => $spk->statusSpk?->nama ?? null,
        ];

        // 2. Informasi Leads
        $leadsInfo = [
            'id' => $spk->leads->id ?? null,
            'nama_perusahaan' => $spk->leads->nama_perusahaan ?? null,
            'telp_perusahaan' => $spk->leads->telp_perusahaan ?? null,
            'nama_pic' => $spk->leads->pic ?? null,
            'telepon_pic' => $spk->leads->no_telp ?? null,
            'email_pic' => $spk->leads->email ?? null,
            'alamat_perusahaan' => $spk->leads->alamat ?? null,
            'jabatan_nama' => $spk->leads->jabatanPic?->nama ?? null,
        ];

        // 3. Informasi Quotation
        $quotationsInfo = [];
        $uniqueQuotations = collect();

        foreach ($spk->spkSites as $spkSite) {
            if ($spkSite->quotation && ! $uniqueQuotations->contains('id', $spkSite->quotation->id)) {
                $uniqueQuotations->push($spkSite->quotation);
            }
        }

        foreach ($uniqueQuotations as $quotation) {
            $calculatedQuotation = null;
            try {
                $calculatedQuotation = $this->quotationService->calculateQuotation($quotation);
            } catch (\Exception $e) {
                Log::error('Error calculating quotation in SPK view: '.$e->getMessage());
            }

            $persenBpjsBreakdown = [];
            if ($calculatedQuotation && isset($calculatedQuotation->calculation_summary)) {
                $summary = $calculatedQuotation->calculation_summary;
                $persenBpjsBreakdown = [
                    'persen_bpjs_jkk' => $summary->persen_bpjs_jkk ?? 0,
                    'persen_bpjs_jkm' => $summary->persen_bpjs_jkm ?? 0,
                    'persen_bpjs_jht' => $summary->persen_bpjs_jht ?? 0,
                    'persen_bpjs_jp' => $summary->persen_bpjs_jp ?? 0,
                    'persen_bpjs_kesehatan' => $summary->persen_bpjs_kesehatan ?? 0,
                ];
            } else {
                $firstDetail = $quotation->quotationDetailCosses->first();
                $persenBpjsBreakdown = [
                    'persen_bpjs_jkk' => $firstDetail->persen_bpjs_jkk ?? 0,
                    'persen_bpjs_jkm' => $firstDetail->persen_bpjs_jkm ?? 0,
                    'persen_bpjs_jht' => $firstDetail->persen_bpjs_jht ?? 0,
                    'persen_bpjs_jp' => $firstDetail->persen_bpjs_jp ?? 0,
                    'persen_bpjs_kesehatan' => $firstDetail->persen_bpjs_kesehatan ?? 0,
                ];
            }
            unset($calculatedQuotation);

            $companyModel = null;
            if ($quotation->relationLoaded('company') && $quotation->company instanceof Company) {
                $companyModel = $quotation->company;
            } elseif (! empty($quotation->company_id)) {
                $companyModel = Company::find($quotation->company_id);
            }

            $totalHc = $quotation->quotationDetails->sum('jumlah_hc');
            $wagedata = $quotation->wage->first();
            $firstDetail = $quotation->quotationDetails->first();
            $adatserikat = $quotation->status_serikat ? 'Ada' : 'Tidak Ada';

            $quotationsInfo[] = [
                'id' => $quotation->id,
                'nomor_quotation' => $quotation->nomor ?? null,
                'nama_perusahaan' => $quotation->nama_perusahaan ?? null,
                'status_quotation' => $quotation->status_quotation_id ?? null,
                'kebutuhan' => $quotation->kebutuhan ?? null,
                'jenis_kontrak' => $quotation->jenis_kontrak ?? null,
                'tanggal_penempatan' => $quotation->tgl_penempatan ?? null,
                'company_name' => $companyModel ? ($companyModel->name ?? null) : null,
                'company_address' => $companyModel ? ($companyModel->address ?? null) : null,
                'tanggal_quotation' => $quotation->tgl_quotation ?? null,
                'npwp' => $quotation->npwp ?? null,
                'materai' => $quotation->materai ?? null,
                'total_hc' => $totalHc,
                'alamat_npwp' => $quotation->alamat_npwp ?? null,
                'durasi_kerjasama' => $quotation->durasi_kerjasama ?? null,
                'durasi_karyawan' => $quotation->durasi_karyawan ?? null,
                'evaluasi_kontrak' => $quotation->evaluasi_kontrak ?? null,
                'evaluasi_karyawan' => $quotation->evaluasi_karyawan ?? null,
                'mulai_kontrak' => $quotation->mulai_kontrak ?? null,
                'kontrak_selesai' => $quotation->kontrak_selesai ?? null,
                'hari_kerja' => $quotation->hari_kerja ?? null,
                'jam_kerja' => $quotation->jam_kerja ?? null,
                'shift_kerja' => $quotation->shift_kerja ?? null,
                'kunjungan_operasional' => $quotation->kunjungan_operasional ?? null,
                'kunjungan_tim_crm' => $quotation->kunjungan_tim_crm ?? null,
                'keterangan_kunjungan_tim_crm' => $quotation->keterangan_kunjungan_tim_crm ?? null,
                'keterangan_kunjungan_operasional' => $quotation->keterangan_kunjungan_operasional ?? null,
                'persen_bpjs_jkk' => $persenBpjsBreakdown['persen_bpjs_jkk'],
                'persen_bpjs_jkm' => $persenBpjsBreakdown['persen_bpjs_jkm'],
                'persen_bpjs_jht' => $persenBpjsBreakdown['persen_bpjs_jht'],
                'persen_bpjs_jp' => $persenBpjsBreakdown['persen_bpjs_jp'],
                'persen_bpjs_kesehatan' => $persenBpjsBreakdown['persen_bpjs_kesehatan'],
                'kompensasi' => $wagedata ? $wagedata->kompensasi ?? null : null,
                'lembur' => $wagedata ? $wagedata->lembur ?? null : null,
                'thr' => $wagedata ? $wagedata->thr ?? null : null,
                'joker_reliever' => $quotation->joker_reliever ?? null,
                'syarat_invoice' => $quotation->syarat_invoice ?? null,
                'top' => $quotation->top ?? null,
                'jumlah_hari_invoice' => $quotation->jumlah_hari_invoice ?? null,
                'tipe_hari_invoice' => $quotation->tipe_hari_invoice ?? null,
                'alamat_penagihan_invoice' => $quotation->alamat_penagihan_invoice ?? null,
                'catatan_site' => $quotation->catatan_site ?? null,
                'cuti' => $quotation->cuti ?? null,
                'gaji_saat_cuti' => $quotation->gaji_saat_cuti ?? null,
                'prorate' => $quotation->prorate ?? null,
                'status_serikat' => $quotation->ada_serikat === 'Tidak Ada' ? 'Tidak Ada' : $quotation->status_serikat,
                'ada_serikat' => $adatserikat,
                'salary_rule' => $quotation->salaryRule ? [
                    'id' => $quotation->salaryRule->id,
                    'nama' => is_object($quotation->salaryRule) ? ($quotation->salaryRule->nama_salary_rule ?? null) : null,
                    'cutoff' => is_object($quotation->salaryRule) ? ($quotation->salaryRule->cutoff ?? null) : null,
                    'crosscheck' => is_object($quotation->salaryRule) ? ($quotation->salaryRule->crosscheck_absen ?? null) : null,
                    'pengiriman_invoice' => is_object($quotation->salaryRule) ? ($quotation->salaryRule->pengiriman_invoice ?? null) : null,
                    'perkiraan_invoice_diterima' => is_object($quotation->salaryRule) ? ($quotation->salaryRule->perkiraan_invoice_diterima ?? null) : null,
                    'rilis_payroll' => is_object($quotation->salaryRule) ? ($quotation->salaryRule->rilis_payroll ?? null) : null,
                ] : null,
                'rulethr' => $quotation->ruleThr ? [
                    'id' => $quotation->ruleThr->id,
                    'nama' => is_object($quotation->ruleThr) ? ($quotation->ruleThr->nama ?? null) : null,
                    'hari_rilis_thr' => is_object($quotation->ruleThr) ? ($quotation->ruleThr->hari_rilis_thr ?? null) : null,
                    'hari_pembayaran_invoice' => is_object($quotation->ruleThr) ? ($quotation->ruleThr->hari_pembayaran_invoice ?? null) : null,
                    'hari_penagihan_invoice' => is_object($quotation->ruleThr) ? ($quotation->ruleThr->hari_penagihan_invoice ?? null) : null,
                ] : null,
                'quotation_details' => $quotation->quotationDetails->map(function ($detail) {
                    return [
                        'id' => $detail->id,
                        'jabatan_kebutuhan' => $detail->jabatan_kebutuhan,
                        'jumlah_hc' => $detail->jumlah_hc,
                    ];
                }),
                'quotation_pics' => $quotation->quotationPics->map(function ($pic) {
                    return [
                        'id' => $pic->id,
                        'nama' => $pic->nama,
                        'jabatan' => is_object($pic->jabatan) ? ($pic->jabatan->nama ?? null) : null,
                        'no_telp' => $pic->no_telp,
                        'email' => $pic->email,
                        'is_kuasa' => $pic->is_kuasa,
                    ];
                }),
                'quotation_trainings' => $quotation->quotationTrainings->map(function ($training) {
                    return [
                        'id' => $training->id,
                        'training_id' => $training->training_id,
                        'nama' => $training->nama,
                    ];
                }),
            ];
        }

        // 4. Informasi Site
        $sitesInfo = $spk->spkSites->map(function ($site) {
            return [
                'id' => $site->id,
                'nama_site' => $site->nama_site,
                'kota' => $site->kota,
                'penempatan' => $site->penempatan,
                'quotation_id' => $site->quotation_id,
            ];
        });

        unset($uniqueQuotations);

        return [
            'spk' => $spkInfo,
            'leads' => $leadsInfo,
            'quotations' => $quotationsInfo,
            'sites' => $sitesInfo,
        ];
    }

    /**
     * Data cetak SPK.
     */
    public function cetakSpk(int $id): ?array
    {
        $now = Carbon::now()->isoFormat('DD MMMM Y');

        $spk = Spk::with(['quotation', 'leads'])->find($id);
        if (! $spk) {
            return null;
        }

        $spkSites = SpkSite::where('spk_id', $id)->get();

        if ($spkSites->isEmpty()) {
            throw new \Exception('No SPK sites found');
        }

        $quotation = $spk->quotation;
        $leads = $spk->leads;

        $spk->unsetRelation('quotation');
        $spk->unsetRelation('leads');

        // Get jabatan PIC
        if ($leads->jabatan) {
            $jabatanPic = JabatanPic::find($leads->jabatan);
            if ($jabatanPic) {
                $leads->jabatan_nama = $jabatanPic->nama_jabatan;
            }
        }

        // Process quotation data
        if ($quotation) {
            $quotation->tgl_penempatan_formatted = $quotation->tgl_penempatan
                ? Carbon::parse($quotation->tgl_penempatan)->isoFormat('D MMMM Y')
                : null;

            $quotation->details = QuotationDetail::where('quotation_id', $quotation->id)
                ->whereNull('deleted_at')
                ->get();

            $quotation->total_hc = $quotation->details->sum('jumlah_hc');

            $quotation->pic = QuotationPic::where('quotation_id', $quotation->id)
                ->where('is_kuasa', 1)
                ->whereNull('deleted_at')
                ->first();
        }

        $company = $quotation ? Company::find($quotation->company_id) : null;

        return [
            'now' => $now,
            'spk' => $spk,
            'spk_sites' => $spkSites,
            'quotation' => $quotation,
            'leads' => $leads,
            'company' => $company,
        ];
    }

    /**
     * Upload file SPK.
     */
    public function uploadSpk(int $id, $file): array
    {
        return DB::transaction(function () use ($id, $file) {
            $spk = Spk::find($id);

            if (! $spk) {
                throw new \Exception('SPK not found');
            }

            // Hapus file lama jika ada
            if ($spk->link_spk_disetujui) {
                $oldFileName = basename($spk->link_spk_disetujui);
                if (Storage::disk('spk')->exists($oldFileName)) {
                    Storage::disk('spk')->delete($oldFileName);
                }
            }

            // Upload file baru
            $fileName = $this->storeSpkFile($file);
            $fileUrl = url('document/spk/'.$fileName);

            Log::info('Generated URL: '.$fileUrl);
            Log::info('Filename: '.$fileName);

            $spk->update([
                'status_spk_id' => 2,
                'link_spk_disetujui' => $fileUrl,
                'updated_by' => Auth::user()->full_name,
            ]);

            // Catat aktivitas
            $this->createUploadActivity($spk);

            $spk->load(['statusSpk']);

            return [
                'id' => $spk->id,
                'nomor' => $spk->nomor,
                'status_spk_id' => $spk->status_spk_id,
                'status' => $spk->statusSpk->nama ?? null,
                'link_spk_disetujui' => $spk->link_spk_disetujui,
            ];
        });
    }

    /**
     * Ajukan ulang quotation dari SPK.
     */
    public function ajukanUlangQuotation(int $spkId, array $quotationSiteIds, string $alasan): array
    {
        return DB::transaction(function () use ($spkId, $quotationSiteIds, $alasan) {
            $spk = Spk::with(['spkSites.quotation', 'spkSites.quotationSite'])->find($spkId);

            if (! $spk) {
                throw new \Exception('SPK not found');
            }

            $allSpkSites = $spk->spkSites;

            if ($allSpkSites->isEmpty()) {
                throw new \Exception('Tidak ada site yang terkait dengan SPK ini.');
            }

            $spkSitesToResubmit = $allSpkSites->whereIn('quotation_site_id', $quotationSiteIds);

            if ($spkSitesToResubmit->isEmpty()) {
                throw new \Exception('Tidak ada site yang valid untuk diajukan ulang.');
            }

            $quotationGroups = $spkSitesToResubmit->groupBy('quotation_id');

            $newQuotations = [];
            $deletedSpkSiteIds = [];
            $deletedQuotationSiteIds = [];
            $quotationAsal = null;

            foreach ($quotationGroups as $quotationId => $spkSites) {
                $quotationAsal = $spkSites->first()->quotation;

                if (! $quotationAsal) {
                    continue;
                }

                $nomorQuotationBaru = $this->generateNomorQuotation($quotationAsal->leads_id, $quotationAsal->company_id, $quotationAsal->id);
                $newQuotation = $this->createNewQuotation($quotationAsal, $nomorQuotationBaru, $alasan);
                $newQuotations[] = $newQuotation;

                $this->copyQuotationRelatedData($quotationAsal->id, $newQuotation->id);

                foreach ($spkSites as $spkSite) {
                    $deletedSpkSiteIds[] = $spkSite->id;
                    $deletedQuotationSiteIds[] = $spkSite->quotation_site_id;
                }

                $quotationAsal->update([
                    'deleted_at' => now(),
                    'deleted_by' => Auth::user()->full_name,
                ]);

                QuotationSite::whereIn('id', $deletedQuotationSiteIds)
                    ->update([
                        'deleted_at' => now(),
                        'deleted_by' => Auth::user()->full_name,
                    ]);
            }

            // Hapus spk_sites yang dipilih
            SpkSite::whereIn('id', $deletedSpkSiteIds)
                ->update([
                    'deleted_at' => now(),
                    'deleted_by' => Auth::user()->full_name,
                ]);

            // Cek apakah masih ada spk_sites yang aktif
            $remainingSpkSites = SpkSite::where('spk_id', $spk->id)
                ->whereNull('deleted_at')
                ->count();

            $spkDeleted = false;

            if ($remainingSpkSites === 0) {
                $spk->update([
                    'deleted_at' => now(),
                    'deleted_by' => Auth::user()->full_name,
                ]);
                $spkDeleted = true;
            }

            $this->createResubmissionActivities($quotationAsal, $newQuotation ?? null, $spk, $spkDeleted, $deletedSpkSiteIds, $deletedQuotationSiteIds);

            $newQuotation = $newQuotations[0] ?? null;

            return [
                'quotation_baru_id' => $newQuotation ? $newQuotation->id : null,
                'quotation_baru_nomor' => $newQuotation ? $newQuotation->nomor : null,
                'spk_id' => $spk->id,
                'spk_dihapus' => $spkDeleted,
                'spk_sites_dihapus' => $deletedSpkSiteIds,
                'quotation_sites_dihapus' => $deletedQuotationSiteIds,
                'all_sites_resubmitted' => $spkDeleted,
            ];
        });
    }

    /**
     * Mendapatkan daftar SPK sites yang sudah dihapus.
     */
    public function getDeletedSpkSites(int $spkId)
    {
        $spkExists = Spk::withTrashed()->where('id', $spkId)->exists();

        if (! $spkExists) {
            throw new \Exception('SPK not found');
        }

        return SpkSite::onlyTrashed()
            ->where('spk_id', $spkId)
            ->with(['quotation', 'quotationSite'])
            ->get()
            ->map(function ($site) {
                return [
                    'id' => $site->id,
                    'nama_site' => $site->nama_site,
                    'quotation_site_id' => $site->quotation_site_id,
                    'deleted_at' => $site->deleted_at,
                    'deleted_by' => $site->deleted_by,
                ];
            });
    }

    /**
     * Mendapatkan daftar site untuk SPK tertentu.
     */
    public function getSiteList(int $id)
    {
        return SpkSite::with(['quotation', 'quotationSite'])
            ->where('spk_id', $id)
            ->whereNull('deleted_at')
            ->whereDoesntHave('site')
            ->get()
            ->map(function ($site, $key) {
                $site->no = $key + 1;

                return $site;
            });
    }

    /**
     * Mendapatkan daftar site yang tersedia untuk leads tertentu.
     */
    public function getSiteAvailableList(int $leadsId)
    {
        return QuotationSite::with(['quotation'])
            ->where('leads_id', $leadsId)
            ->whereNull('deleted_at')
            ->whereDoesntHave('spkSite')
            ->get()
            ->map(function ($site) {
                return [
                    'id' => $site->id,
                    'nama_site' => $site->nama_site,
                    'provinsi' => $site->provinsi,
                    'kota' => $site->kota,
                    'quotation' => is_object($site->quotation) ? $site->quotation->nomor : $site->quotation,
                    'ump' => $site->ump,
                    'umk' => $site->umk,
                    'nominal_upah' => $site->nominal_upah,
                    'penempatan' => $site->penempatan,
                ];
            });
    }

    /**
     * Menghapus SPK (soft delete).
     */
    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $spk = Spk::find($id);

            if (! $spk) {
                throw new \Exception('SPK not found');
            }

            // Soft delete semua SpkSite yang terkait
            SpkSite::where('spk_id', $id)
                ->update([
                    'deleted_at' => now(),
                    'deleted_by' => Auth::user()->full_name,
                ]);

            // Soft delete SPK
            $spk->update([
                'deleted_at' => now(),
                'deleted_by' => Auth::user()->full_name,
            ]);

            $this->createDeleteActivity($spk);
        });
    }

    /**
     * Menghapus SpkSite (soft delete).
     */
    public function deleteSite(int $siteId): void
    {
        DB::transaction(function () use ($siteId) {
            $spkSite = SpkSite::find($siteId);

            if (! $spkSite) {
                throw new \Exception('SPK site not found');
            }

            $spkSite->update([
                'deleted_at' => now(),
                'deleted_by' => Auth::user()->full_name,
            ]);

            // Cek apakah SPK masih memiliki site aktif
            $remainingSites = SpkSite::where('spk_id', $spkSite->spk_id)
                ->whereNull('deleted_at')
                ->count();

            if ($remainingSites === 0) {
                $spk = Spk::find($spkSite->spk_id);
                if ($spk) {
                    $spk->update([
                        'deleted_at' => now(),
                        'deleted_by' => Auth::user()->full_name,
                    ]);

                    $this->createDeleteActivity($spk);
                }
            }

            $lead = Leads::find($spkSite->leads_id);
            if ($lead) {
                $lead->tgl_leads = Carbon::now();
                $lead->save();
            }
        });
    }

    /**
     * Submit checklist untuk quotation terkait SPK.
     */
    public function submitChecklist(string $id, array $data): array
    {
        return DB::transaction(function () use ($id, $data) {
            $user = Auth::user();
            $currentDateTime = Carbon::now()->toDateTimeString();

            $quotation = Quotation::notDeleted()->findOrFail($id);

            // Logika untuk status serikat
            $statusSerikat = $data['status_serikat'];
            if (($data['ada_serikat'] ?? null) === 'Tidak Ada') {
                $statusSerikat = 'Tidak Ada';
            }

            // Update quotation data
            $quotation->update([
                'npwp' => $data['npwp'],
                'alamat_npwp' => $data['alamat_npwp'],
                'pic_invoice' => $data['pic_invoice'] ?? null,
                'telp_pic_invoice' => $data['telp_pic_invoice'] ?? null,
                'email_pic_invoice' => $data['email_pic_invoice'] ?? null,
                'materai' => $data['materai'],
                'joker_reliever' => $data['joker_reliever'],
                'syarat_invoice' => $data['syarat_invoice'],
                'alamat_penagihan_invoice' => $data['alamat_penagihan_invoice'],
                'catatan_site' => $data['catatan_site'] ?? null,
                'status_serikat' => $statusSerikat,
                'updated_at' => $currentDateTime,
                'updated_by' => $user->full_name,
            ]);

            // Tambah PICs jika ada
            $picsAdded = 0;
            if (! empty($data['pics']) && is_array($data['pics'])) {
                foreach ($data['pics'] as $picData) {
                    $this->addDetailPic($quotation, $picData, $currentDateTime);
                    $picsAdded++;
                }
            }

            return [
                'id' => $quotation->id,
                'npwp' => $quotation->npwp,
                'pic_invoice' => $quotation->pic_invoice,
                'pics_added' => $picsAdded,
            ];
        });
    }

    // =============================================
    // PRIVATE HELPER METHODS
    // =============================================

    private function createSpkSites(Spk $spk, array $siteIds): void
    {
        foreach ($siteIds as $siteId) {
            $quotationSite = QuotationSite::with('quotation')->find($siteId);

            if (! $quotationSite) {
                throw new \Exception("Quotation site dengan ID {$siteId} tidak ditemukan.");
            }

            if ($quotationSite->leads_id != $spk->leads_id) {
                throw new \Exception("Quotation site dengan ID {$siteId} tidak termasuk dalam leads yang dipilih.");
            }

            SpkSite::create([
                'spk_id' => $spk->id,
                'quotation_id' => $quotationSite->quotation_id,
                'quotation_site_id' => $quotationSite->id,
                'leads_id' => $quotationSite->leads_id,
                'nama_site' => $quotationSite->nama_site,
                'provinsi_id' => $quotationSite->provinsi_id,
                'provinsi' => $quotationSite->provinsi,
                'kota_id' => $quotationSite->kota_id,
                'kota' => $quotationSite->kota,
                'ump' => $quotationSite->ump,
                'umk' => $quotationSite->umk,
                'nominal_upah' => $quotationSite->nominal_upah,
                'penempatan' => $quotationSite->penempatan,
                'kebutuhan_id' => $quotationSite->quotation->kebutuhan_id,
                'kebutuhan' => $quotationSite->quotation->kebutuhan,
                'jenis_site' => $quotationSite->quotation->jumlah_site,
                'nomor_quotation' => $quotationSite->quotation->nomor,
                'created_by' => Auth::user()->full_name ?? 'System',
                'created_by_user_id' => Auth::id(),
            ]);
        }
    }

    private function createCustomerActivity($leads, $spk, $spkNomor): void
    {
        $nomorActivity = $this->generateActivityNomor($leads->id);
        $user = Auth::user();

        if ($user && in_array($user->cais_role_id, [29, 30, 31, 32, 33])) {
            $this->createSalesActivity($spk, $leads);
        } else {
            CustomerActivity::create([
                'leads_id' => $leads->id,
                'spk_id' => $spk->id,
                'branch_id' => $leads->branch_id,
                'tgl_activity' => now(),
                'nomor' => $nomorActivity,
                'tipe' => 'SPK',
                'notes' => 'SPK dengan nomor : '.$spkNomor.' terbentuk',
                'is_activity' => 0,
                'user_id' => Auth::user()->id,
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::user()->id,
            ]);
        }

        if ($leads) {
            $leads->tgl_leads = Carbon::now()->toDateString();
            $leads->save();
        }
    }

    private function createSalesActivity($spk, $leads): void
    {
        $user = Auth::user();

        $leadsKebutuhanList = LeadsKebutuhan::where('leads_id', $spk->leads_id)
            ->whereNotNull('tim_sales_d_id')
            ->get();

        foreach ($leadsKebutuhanList as $leadsKebutuhan) {
            $spkSiteExists = SpkSite::where('spk_id', $spk->id)
                ->where('kebutuhan_id', $leadsKebutuhan->kebutuhan_id)
                ->exists();

            if ($spkSiteExists) {
                SalesActivity::create([
                    'leads_id' => $spk->leads_id,
                    'leads_kebutuhan_id' => $leadsKebutuhan->id,
                    'spk_id' => $spk->id,
                    'tgl_activity' => Carbon::now(),
                    'jenis_activity' => 'SPK',
                    'notulen' => "SPK baru {$spk->nomor} dibuat untuk kebutuhan {$leadsKebutuhan->kebutuhan->nama}",
                    'created_by' => $user->full_name,
                    'created_by_user_id' => $user->id,
                ]);
            }
        }
    }

    private function storeSpkFile($file): string
    {
        $fileExtension = $file->getClientOriginalExtension();
        $originalFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $fileName = $originalFileName.date('YmdHis').rand(10000, 99999).'.'.$fileExtension;

        Storage::disk('spk')->put($fileName, file_get_contents($file));

        return $fileName;
    }

    private function generateNomorNew(int $leadsId, int $companyId = 0): string
    {
        return app(\App\Services\Spk\SpkNumberingService::class)->generate($leadsId, $companyId);
    }

    private function generateActivityNomor(int $leadsId): string
    {
        $now = Carbon::now();
        $leads = Leads::find($leadsId);

        $prefix = 'CAT/';
        if ($leads) {
            $prefix .= match ($leads->kebutuhan_id) {
                1 => 'SG/',
                2 => 'LS/',
                3 => 'CS/',
                4 => 'LL/',
                default => 'NN/',
            };
            $prefix .= $leads->nomor.'-';
        } else {
            $prefix .= 'NN/NNNNN-';
        }

        $month = str_pad($now->month, 2, '0', STR_PAD_LEFT);
        $year = $now->year;

        $count = CustomerActivity::where('nomor', 'like', $prefix.$month.$year.'-%')->count();
        $sequence = str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        return $prefix.$month.$year.'-'.$sequence;
    }

    /**
     * Pengajuan ulang menghasilkan REVISI dari quotation asal, bukan dokumen
     * baru. Sebelumnya tipe di-hardcode 'baru' sehingga quotation revisi
     * mendapat nomor QUOT/ORG/... dan urutan revisinya tidak terlihat di nomor.
     */
    private function generateNomorQuotation(int $leadsId, ?int $companyId, int $referensiId): string
    {
        return app(\App\Services\Quotation\QuotationNumberingService::class)
            ->generate($leadsId, $companyId ?? 0, 'revisi', $referensiId);
    }

    private function createNewQuotation($quotationAsal, string $nomorQuotationBaru, string $alasan)
    {
        $newQuotationData = $quotationAsal->toArray();

        unset(
            $newQuotationData['id'],
            $newQuotationData['nomor'],
            $newQuotationData['created_at'],
            $newQuotationData['updated_at'],
            $newQuotationData['deleted_at']
        );

        $newQuotationData['nomor'] = $nomorQuotationBaru;
        $newQuotationData['revisi'] = ($quotationAsal->revisi ?? 0) + 1;
        $newQuotationData['alasan_revisi'] = $alasan;
        $newQuotationData['quotation_asal_id'] = $quotationAsal->id;
        // Selaraskan data dengan nomor yang digenerate: dokumen ini adalah
        // revisi, dan rantai versinya ditelusuri lewat quotation_referensi_id.
        // Tanpa ini, toArray() akan mewarisi referensi milik quotation asal.
        $newQuotationData['quotation_referensi_id'] = $quotationAsal->id;
        $newQuotationData['tipe_quotation'] = 'revisi';
        $newQuotationData['created_at'] = now();
        $newQuotationData['created_by'] = Auth::user()->full_name;
        $newQuotationData['updated_at'] = null;
        $newQuotationData['updated_by'] = null;

        $newQuotationData['ot1'] = null;
        $newQuotationData['ot2'] = null;
        $newQuotationData['ot3'] = null;
        $newQuotationData['tgl_quotation'] = now()->format('Y-m-d');
        $newQuotationData['tgl_penempatan'] = null;

        $isAktif = 1;
        $statusQuotation = 1;

        if ($quotationAsal->top == 'Lebih Dari 7 Hari') {
            $isAktif = 0;
            $statusQuotation = 2;
        }

        if ($quotationAsal->persentase < 7) {
            $isAktif = 0;
            $statusQuotation = 2;
        }

        $newQuotationData['status_quotation_id'] = $statusQuotation;
        $newQuotationData['is_aktif'] = $isAktif;
        $newQuotationData['step'] = 1;

        return Quotation::create($newQuotationData);
    }

    private function copyQuotationRelatedData(int $quotationAsalId, int $quotationBaruId): void
    {
        $models = [
            QuotationSite::class,
            QuotationDetail::class,
            QuotationDetailRequirement::class,
            QuotationDetailHpp::class,
            QuotationDetailCoss::class,
            QuotationDetailTunjangan::class,
            QuotationKaporlap::class,
            QuotationDevices::class,
            QuotationChemical::class,
            QuotationOhc::class,
            QuotationAplikasi::class,
            QuotationKerjasama::class,
            QuotationPic::class,
            QuotationTraining::class,
        ];

        foreach ($models as $model) {
            $this->copyModelData($model, $quotationAsalId, $quotationBaruId);
        }
    }

    private function copyModelData(string $modelClass, int $quotationAsalId, int $quotationBaruId): void
    {
        $records = $modelClass::where('quotation_id', $quotationAsalId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($records as $record) {
            $newRecord = $record->replicate();
            $newRecord->quotation_id = $quotationBaruId;
            $newRecord->created_at = now();
            $newRecord->created_by = Auth::user()->full_name;
            $newRecord->save();
        }
    }

    private function createResubmissionActivities($quotationAsal, $newQuotation, $spk, bool $spkDeleted, array $deletedSpkSiteIds, array $deletedQuotationSiteIds): void
    {
        $leads = Leads::find($quotationAsal->leads_id);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'quotation_id' => $quotationAsal->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $this->generateActivityNomor($leads->id),
            'tipe' => 'Quotation',
            'notes' => 'Quotation dengan nomor : '.$quotationAsal->nomor.' di ajukan ulang',
            'is_activity' => 0,
            'user_id' => Auth::user()->id,
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::user()->id,
        ]);

        if ($newQuotation) {
            CustomerActivity::create([
                'leads_id' => $leads->id,
                'quotation_id' => $newQuotation->id,
                'branch_id' => $leads->branch_id,
                'tgl_activity' => now(),
                'nomor' => $this->generateActivityNomor($leads->id),
                'tipe' => 'Quotation',
                'notes' => 'Quotation dengan nomor : '.$newQuotation->nomor.' terbentuk dari ajukan ulang quotation dengan nomor : '.$quotationAsal->nomor,
                'is_activity' => 0,
                'user_id' => Auth::user()->id,
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::user()->id,
            ]);
        }

        if (! empty($deletedSpkSiteIds)) {
            $spkSiteCount = count($deletedSpkSiteIds);
            CustomerActivity::create([
                'leads_id' => $leads->id,
                'spk_id' => $spk->id,
                'branch_id' => $leads->branch_id,
                'tgl_activity' => now(),
                'nomor' => $this->generateActivityNomor($leads->id),
                'tipe' => 'SPK Site',
                'notes' => $spkSiteCount.' SPK site dihapus karena quotation diajukan ulang',
                'is_activity' => 0,
                'user_id' => Auth::user()->id,
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::user()->id,
            ]);
        }

        if (! empty($deletedQuotationSiteIds)) {
            $quotationSiteCount = count($deletedQuotationSiteIds);
            CustomerActivity::create([
                'leads_id' => $leads->id,
                'quotation_id' => $quotationAsal->id,
                'branch_id' => $leads->branch_id,
                'tgl_activity' => now(),
                'nomor' => $this->generateActivityNomor($leads->id),
                'tipe' => 'Quotation Site',
                'notes' => $quotationSiteCount.' Quotation site dihapus karena diajukan ulang',
                'is_activity' => 0,
                'user_id' => Auth::user()->id,
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::user()->id,
            ]);
        }

        if ($spkDeleted) {
            CustomerActivity::create([
                'leads_id' => $leads->id,
                'spk_id' => $spk->id,
                'branch_id' => $leads->branch_id,
                'tgl_activity' => now(),
                'nomor' => $this->generateActivityNomor($leads->id),
                'tipe' => 'SPK',
                'notes' => 'SPK dengan nomor : '.$spk->nomor.' dihapus karena semua quotation site diajukan ulang',
                'is_activity' => 0,
                'user_id' => Auth::user()->id,
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::user()->id,
            ]);
        }

        if ($leads) {
            $leads->tgl_leads = Carbon::now()->toDateString();
            $leads->save();
        }
    }

    private function createDeleteActivity($spk): void
    {
        $leads = Leads::find($spk->leads_id);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'spk_id' => $spk->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $this->generateActivityNomor($leads->id),
            'tipe' => 'SPK',
            'notes' => 'SPK dengan nomor : '.$spk->nomor.' dihapus',
            'is_activity' => 0,
            'user_id' => Auth::user()->id,
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::user()->id,
        ]);

        if ($leads) {
            $leads->tgl_leads = Carbon::now()->toDateString();
            $leads->save();
        }
    }

    private function createUploadActivity($spk): void
    {
        $leads = Leads::find($spk->leads_id);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'spk_id' => $spk->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $this->generateActivityNomor($leads->id),
            'tipe' => 'SPK',
            'notes' => 'SPK dengan nomor : '.$spk->nomor.' telah diupload dan disetujui',
            'is_activity' => 0,
            'user_id' => Auth::user()->id,
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::user()->id,
        ]);

        if ($leads) {
            $leads->tgl_leads = Carbon::now()->toDateString();
            $leads->save();
        }
    }

    private function addDetailPic($quotation, array $picData, string $currentDateTime): void
    {
        $user = Auth::user();

        QuotationPic::create([
            'quotation_id' => $quotation->id,
            'nama' => $picData['nama'],
            'jabatan_id' => $picData['jabatan'] ?? null,
            'no_telp' => $picData['no_telp'] ?? null,
            'email' => $picData['email'] ?? null,
            'leads_id' => $quotation->leads_id,
            'is_kuasa' => 0,
            'created_at' => $currentDateTime,
            'created_by' => $user->full_name,
            'created_by_user_id' => $user->id,
        ]);
    }
}
