<?php

namespace App\Services\Pks;

use App\Models\Client;
use App\Models\Company;
use App\Models\CustomerActivity;
use App\Models\HrisSite;
use App\Models\JabatanPic;
use App\Models\KategoriSesuaiHc;
use App\Models\Kebutuhan;
use App\Models\Leads;
use App\Models\LeadsKebutuhan;
use App\Models\Loyalty;
use App\Models\Pks;
use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\QuotationDetailCoss;
use App\Models\QuotationDetailHpp;
use App\Models\QuotationMargin;
use App\Models\QuotationPic;
use App\Models\QuotationSite;
use App\Models\RuleThr;
use App\Models\SalaryRule;
use App\Models\SalesActivity;
use App\Models\Site;
use App\Models\Spk;
use App\Models\SpkSite;
use App\Services\Pks\Template\PksTemplateFactory;
use App\Services\Pks\PksNumberingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PksService
{
    /**
     * Create PKS (transactional) — wraps the core business logic in a
     * DB transaction so an exception auto-rolls-back & rethrows to the
     * global exception handler.
     */
    public function createPks($request, $tipe)
    {
        return DB::transaction(function () use ($request, $tipe) {
            return $this->processPksLogic($request, $tipe);
        });
    }

    public function processPksLogic($request, $tipe)
    {
        // 1. Ambil data Leads utama
        $leads = Leads::findOrFail($request->leads_id);

        $pksInduk = null;
        $quotationId = null;
        $pksIndukId = null;

        // 2. Tentukan nomor PKS & ID terkait berdasarkan tipe
        // rekontrak & addendum sama-sama terikat ke pks_induk_id, konsisten
        // dengan PksNumberingService/QuotationNumberingService.
        if (in_array($tipe, ['addendum', 'rekontrak'], true)) {
            $pksInduk = Pks::findOrFail($request->pks_id);
            $pksIndukId = $pksInduk->id;
            $companyId = $pksInduk->company_id ?? $request->entitas;

            if ($tipe === 'rekontrak') {
                // Gunakan select('quotation_id') agar query lebih ringan
                $firstSite = QuotationSite::select('quotation_id')->findOrFail($request->quotation_site_ids[0]);
                $quotationId = $firstSite->quotation_id;
            } else {
                $quotationId = $pksInduk->quotation_id;
            }
        } else {
            $companyId = $request->entitas;

            // Gunakan select('id', 'kebutuhan_id') untuk optimasi memori
            $quotation = Quotation::select('id', 'kebutuhan_id', 'persentase')->where('leads_id', $leads->id)->first();
            $quotationId = $quotation->id ?? null;
        }

        $pksNomor = app(PksNumberingService::class)->generate($leads->id, $companyId, $tipe, $pksIndukId);

        // 3. Tentukan Layanan/Kebutuhan ID secara presisi
        if ($tipe !== 'addendum' && isset($quotation)) {
            $layananId = $quotation->kebutuhan_id;
        } else {
            $quotation = $quotationId ? Quotation::select('id', 'kebutuhan_id', 'persentase')->find($quotationId) : null;
            $layananId = $quotation ? $quotation->kebutuhan_id : $leads->kebutuhan_id;
        }

        // 4. BATCH FETCH DATA MASTER (Hanya tembak query jika ID-nya ada)
        [$kebutuhan, $kategoriHC, $loyalty, $company, $ruleThr, $salaryRule] = [
            $layananId ? Kebutuhan::find($layananId) : null,
            $request->kategoriHC ? KategoriSesuaiHc::find($request->kategoriHC) : null,
            $request->loyalty ? Loyalty::find($request->loyalty) : null,
            $companyId ? Company::find($companyId) : null,
            $request->rule_thr ? RuleThr::find($request->rule_thr) : null,
            $request->salary_rule ? SalaryRule::find($request->salary_rule) : null,
        ];

        // 5. Create PKS (Unified)
        $pks = Pks::create([
            'leads_id' => $leads->id,
            'quotation_id' => $quotationId,
            'branch_id' => $leads->branch_id,
            'nomor' => $pksNomor,
            'tgl_pks' => $request->tanggal_pks,
            'kode_perusahaan' => $leads->nomor,
            'nama_perusahaan' => $leads->nama_perusahaan,
            'alamat_perusahaan' => $leads->alamat,
            'layanan_id' => $layananId,
            'layanan' => $kebutuhan->nama ?? null,
            'bidang_usaha_id' => $leads->bidang_perusahaan_id,
            'bidang_usaha' => $leads->bidang_perusahaan,
            'jenis_perusahaan_id' => $leads->jenis_perusahaan_id,
            'jenis_perusahaan' => $leads->jenis_perusahaan,
            'kontrak_awal' => $request->tanggal_awal_kontrak,
            'kontrak_akhir' => $request->tanggal_akhir_kontrak,
            'status_pks_id' => 5, // Draft
            'sales_id' => Auth::id(),
            'company_id' => $companyId,
            'salary_rule_id' => $request->salary_rule,
            'rule_thr_id' => $request->rule_thr,
            'kategori_sesuai_hc_id' => $request->kategoriHC,
            'kategori_sesuai_hc' => $kategoriHC->nama ?? null,
            'loyalty_id' => $request->loyalty,
            'loyalty' => $loyalty->nama ?? null,
            'provinsi_id' => $leads->provinsi_id,
            'provinsi' => $leads->provinsi,
            'kota_id' => $leads->kota_id,
            'kota' => $leads->kota,
            'pma' => $leads->pma,
            'pks_induk_id' => $pksIndukId,
            'tipe_pks' => $tipe,
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::id(),
        ]);

        // 6. Create Sites (Conditional)
        $siteIds = ($tipe === 'baru') ? $request->site_ids : $request->quotation_site_ids;
        $syncType = ($tipe === 'baru') ? 'baru' : 'rekontrak';

        $this->syncPksSites($pks, $siteIds, $pksNomor, $kebutuhan, $leads, $syncType);

        // 7. Side Effects
        $this->createInitialActivity($pks, $leads, $pksNomor);

        // FIX OPTIMASI: Langsung gunakan variabel data master yang sudah di-fetch di awal (Langkah 4)
        $this->createPksPerjanjian(
            $pks,
            $leads,
            $company,       // reuse variabel awal
            $kebutuhan,     // reuse variabel awal
            $ruleThr,       // reuse variabel awal
            $salaryRule,    // reuse variabel awal
            $pksNomor,
            $quotation->persentase ?? null
        );

        // 8. Bulk Update Status Model Lain
        Spk::where('leads_id', $leads->id)
            ->whereNotIn('status_spk_id', [100]) // skip Terminated
            ->update([
                'status_spk_id' => 3,
                'updated_by' => Auth::user()->full_name,
            ]);

        if ($quotationId) {
            Quotation::where('id', $quotationId)
                ->where('status_quotation_id', '!=', 100)
                ->update([
                    'status_quotation_id' => 5,
                    'updated_by' => Auth::user()->full_name,
                ]);
        }

        $statusTerminalLeads = [100, 101];
        if (!in_array($leads->status_leads_id, $statusTerminalLeads)) {
            $leads->update([
                'status_leads_id' => 99,
                'updated_by' => Auth::user()->full_name,
            ]);
        }

        return $pks;
    }

    private function syncPksSites($pks, array $siteIds, $pksNomor, $kebutuhan, $leads, $type = 'baru')
    {
        $isBaru = ($type === 'baru');
        $model = $isBaru ? SpkSite::class : QuotationSite::class;
        $sourceSites = $model::whereIn('id', $siteIds)
            ->with('quotation:id,nomor')
            ->get()
            ->keyBy('id');

        foreach ($siteIds as $key => $id) {
            $sourceSite = $sourceSites->get($id);
            if (!$sourceSite) {
                continue;
            }

            $nomorSite = $pksNomor . '-' . sprintf('%04d', ($key + 1));

            $namaProyek = sprintf(
                '%s-%s.%s.%s',
                Carbon::parse($pks->kontrak_awal)->format('my'),
                Carbon::parse($pks->kontrak_akhir)->format('my'),
                strtoupper(substr($kebutuhan->nama, 0, 2)),
                strtoupper($leads->nama_perusahaan)
            );

            Site::create([
                'pks_id' => $pks->id,
                'leads_id' => $leads->id,
                'quotation_id' => $sourceSite->quotation_id,
                'nomor' => $nomorSite,
                'nomor_proyek' => $nomorSite,
                'nama_proyek' => $namaProyek,
                'nama_site' => $sourceSite->nama_site,
                'provinsi_id' => $sourceSite->provinsi_id,
                'provinsi' => $sourceSite->provinsi,
                'kota_id' => $sourceSite->kota_id,
                'kota' => $sourceSite->kota,
                'nominal_upah' => $sourceSite->nominal_upah,
                'penempatan' => $sourceSite->penempatan,
                'kebutuhan_id' => $leads->kebutuhan_id,
                'kebutuhan' => $kebutuhan->nama ?? null,
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id(),

                'spk_id' => $isBaru ? $sourceSite->spk_id : null,
                'spk_site_id' => $isBaru ? $sourceSite->id : null,
                'quotation_site_id' => $isBaru ? $sourceSite->quotation_site_id : $sourceSite->id,
                'nomor_quotation' => $isBaru ? $sourceSite->nomor_quotation : ($sourceSite->quotation->nomor ?? null),

                'ump' => $sourceSite->ump ?? null,
                'umk' => $sourceSite->umk ?? null,
            ]);
        }
    }

    /**
     * Create PKS Perjanjian using service
     */
    private function createPksPerjanjian($pks, $leads, $company, $kebutuhan, $ruleThr, $salaryRule, $pksNomor, $persentase = null)
    {
        try {
            // Pilih template sesuai company, fallback ke PksPerjanjianTemplateService.
            // Oper $pks (tanggal kontrak) & $persentase (management fee) agar
            // placeholder dinamis pada template terisi.
            $templateService = (new PksTemplateFactory)->make(
                $leads,
                $company,
                $kebutuhan,
                $ruleThr,
                $salaryRule,
                $pksNomor,
                $pks,
                $persentase
            );

            // Insert agreement sections
            $templateService->insertAgreementSections($pks->id, Auth::user()->full_name);

            \Log::info('PKS Perjanjian created successfully for PKS ID: ' . $pks->id . ' using ' . get_class($templateService));

        } catch (\Exception $e) {
            \Log::error('Failed to create PKS Perjanjian: ' . $e->getMessage());
            throw $e;
        }
    }

    private function createInitialActivity($pks, $leads, $pksNomor)
    {
        $nomorActivity = $this->generateNomorActivity($leads);
        $user = Auth::user();
        if ($user && in_array($user->cais_role_id, [29, 30, 31, 32, 33])) {
            // Untuk Sales, buat SalesActivity
            $this->createSalesActivity($pks, $user->full_name);
        } else {

            CustomerActivity::create([
                'leads_id' => $leads->id,
                'pks_id' => $pks->id,
                'branch_id' => $leads->branch_id,
                'tgl_activity' => now(),
                'nomor' => $nomorActivity,
                'tipe' => 'PKS',
                'notes' => 'PKS dengan nomor :' . $pksNomor . ' terbentuk',
                'is_activity' => 0,
                'user_id' => Auth::id(),
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id(),
            ]);
        }
        if ($leads) {
            $leads->tgl_leads = Carbon::now()->toDateString();  // Set ke tanggal activity terbaru
            $leads->save();
        }
    }

    private function createSalesActivity(Pks $pks, string $createdBy): void
    {
        $user = Auth::user();

        // Ambil kebutuhan dari Quotation yang terhubung ke PKS ini
        $quotation = $pks->quotations; // relasi belongsTo ke Quotation

        $kebutuhanId = $quotation?->kebutuhan_id ?? $pks->layanan_id;
        $kebutuhanNama = $quotation?->kebutuhan ?? $pks->layanan;

        $leadsKebutuhan = LeadsKebutuhan::where('leads_id', $pks->leads_id)
            ->where('kebutuhan_id', $kebutuhanId)
            ->where('tim_sales_d_id', $user->id)
            ->first();

        SalesActivity::create([
            'leads_id' => $pks->leads_id,
            'leads_kebutuhan_id' => $leadsKebutuhan?->id,
            'pks_id' => $pks->id,
            'tgl_activity' => Carbon::now(),
            'jenis_activity' => 'PKS',
            'notulen' => "pks baru {$pks->nomor} dibuat untuk kebutuhan {$kebutuhanNama}",
            'created_by' => $createdBy,
            'created_by_user_id' => Auth::id(),
        ]);
    }

    public function approvePks($pks, $otLevel)
    {
        $statusMap = [
            1 => 2,
            2 => 3,
            3 => 4,
            4 => 5,
        ];

        $approveField = "ot{$otLevel}";

        $pks->update([
            $approveField => Auth::user()->full_name,
            'status_pks_id' => $statusMap[$otLevel] ?? $pks->status_pks_id,
            'updated_by' => Auth::user()->full_name,
        ]);
    }


    /**
     * Cek apakah kontrak masih berlaku
     */
    private function isKontrakBerlaku($kontrakAkhir)
    {
        if (!$kontrakAkhir) {
            return false;
        }

        $tanggalSekarang = Carbon::now();
        $tanggalKontrakAkhir = Carbon::parse($kontrakAkhir);

        return $tanggalSekarang->lessThanOrEqualTo($tanggalKontrakAkhir);
    }


    /**
     * Create customer activity log
     */
    private function createCustomerActivity($leads, $customerNomor)
    {
        $nomorActivity = $this->generateNomorActivity($leads);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $nomorActivity,
            'tipe' => 'CUSTOMER',
            'notes' => 'Customer dengan nomor :' . $customerNomor . ' terbentuk dari PKS',
            'is_activity' => 0,
            'user_id' => Auth::id(),
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::id(),
        ]);
    }

    public function getTemplateData($pks)
    {
        $company = $pks->company;
        $kebutuhan = $pks->kebutuhan;
        $ruleThr = $pks->ruleThr;
        $salaryRule = $pks->salaryRule;
        $leads = $pks->leads;

        return [
            'pks' => [
                'nomor' => $pks->nomor,
                'tanggal_pks' => Carbon::parse($pks->tgl_pks)->isoFormat('D MMMM Y'),
                'kontrak_awal' => Carbon::parse($pks->kontrak_awal)->isoFormat('D MMMM Y'),
                'kontrak_akhir' => Carbon::parse($pks->kontrak_akhir)->isoFormat('D MMMM Y'),
            ],
            'perusahaan' => [
                'nama' => $leads->nama_perusahaan,
                'alamat' => $leads->alamat,
                'pic' => $leads->pic,
                'nomor' => $leads->nomor,
            ],
            'penyedia' => [
                'nama' => $company->name ?? '',
                'direktur' => $company->nama_direktur ?? '',
                'alamat' => $company->address ?? '',
                'bank' => [
                    'nama' => 'MANDIRI',
                    'cabang' => 'KCP SURABAYA RUNGKUT MEGAH RAYA',
                    'rekening' => '1420001290823',
                    'nama_rekening' => $company->name ?? '',
                ],
            ],
            'layanan' => [
                'nama' => $kebutuhan->nama ?? '',
                'kebutuhan_id' => $pks->layanan_id,
            ],
            'rule_thr' => [
                'hari_penagihan_invoice' => $ruleThr->hari_penagihan_invoice ?? 0,
                'hari_pembayaran_invoice' => $ruleThr->hari_pembayaran_invoice ?? 0,
                'hari_rilis_thr' => $ruleThr->hari_rilis_thr ?? 0,
            ],
            'salary_rule' => [
                'cutoff' => $salaryRule->cutoff ?? '',
                'crosscheck_absen' => $salaryRule->crosscheck_absen ?? '',
                'pengiriman_invoice' => $salaryRule->pengiriman_invoice ?? '',
                'perkiraan_invoice_diterima' => $salaryRule->perkiraan_invoice_diterima ?? '',
                'pembayaran_invoice' => $salaryRule->pembayaran_invoice ?? '',
                'rilis_payroll' => $salaryRule->rilis_payroll ?? '',
            ],
            'sites' => $pks->sites->map(function ($site) {
                return [
                    'nama_site' => $site->nama_site,
                    'alamat' => $site->penempatan,
                    'kota' => $site->kota,
                ];
            }),
        ];
    }

    // ======================================================================
    // UTILITY METHODS
    // ======================================================================

    public function hitungBerakhirKontrak($tanggalBerakhir)
    {
        if (is_null($tanggalBerakhir)) {
            return '-';
        }

        $tanggalSekarang = Carbon::now();
        $tanggalBerakhir = Carbon::createFromFormat('Y-m-d', $tanggalBerakhir);

        if ($tanggalSekarang->greaterThanOrEqualTo($tanggalBerakhir)) {
            return 'Kontrak habis';
        }

        $selisih = $tanggalSekarang->diff($tanggalBerakhir);

        $hasil = [];
        if ($selisih->y > 0) {
            $hasil[] = "{$selisih->y} tahun";
        }
        if ($selisih->m > 0) {
            $hasil[] = "{$selisih->m} bulan";
        }
        if ($selisih->d > 0) {
            $hasil[] = "{$selisih->d} hari";
        }

        return implode(', ', $hasil);
    }

    public function getStatusBerlaku($tanggalBerakhir)
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

    private function selisihKontrakBerakhir($tanggalBerakhir)
    {
        if (is_null($tanggalBerakhir)) {
            return 0;
        }

        $tanggalSekarang = Carbon::now();
        $tanggalBerakhir = Carbon::createFromFormat('Y-m-d', $tanggalBerakhir);

        if ($tanggalSekarang->greaterThanOrEqualTo($tanggalBerakhir)) {
            return 0;
        }

        return $tanggalSekarang->diffInDays($tanggalBerakhir);
    }

    public function generateNomorActivity(Leads $leads)
    {
        $now = Carbon::now();


        $prefix = 'CAT/';
        if ($leads) {
            $prefix .= match ($leads->kebutuhan_id) {
                1 => 'SG/',
                2 => 'LS/',
                3 => 'CS/',
                4 => 'LL/',
                default => 'NN/'
            };
            $prefix .= $leads->nomor . '-';
        } else {
            $prefix .= 'NN/NNNNN-';
        }

        $month = str_pad($now->month, 2, '0', STR_PAD_LEFT);
        $year = $now->year;

        $count = CustomerActivity::where('nomor', 'like', $prefix . $month . $year . '-%')->count();
        $sequence = str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        return $prefix . $month . $year . '-' . $sequence;
    }

    public function getAvailableLeadsData(Request $request)
    {
        $query = Leads::filterByUserRole()
            ->whereHas('spkSites', function ($query) {
                $query->whereNull('sl_spk_site.deleted_at')
                    ->whereHas('spk', function ($subQuery) {
                        $subQuery->whereNull('sl_spk.deleted_at');
                    })
                    // Tambahkan closure di sini untuk memfilter soft deletes pada site
                    ->whereDoesntHave('site', function ($siteQuery) {
                        $siteQuery->whereNull('sl_site.deleted_at');
                    });
            })
            ->select('id', 'nomor', 'nama_perusahaan', 'provinsi', 'kota', 'created_by')
            ->distinct()
            ->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $searchBy = $request->get('search_by', 'nama_perusahaan');

            if ($searchBy === 'nama_perusahaan') {
                $searchTerm = str_contains($searchTerm, ' ')
                    ? '"' . $searchTerm . '"'
                    : $searchTerm . '*';
                $query->whereRaw('MATCH(nama_perusahaan) AGAINST(? IN BOOLEAN MODE)', [$searchTerm]);
            } elseif (in_array($searchBy, ['nomor', 'provinsi', 'kota', 'created_by'], true)) {
                $query->where($searchBy, 'LIKE', '%' . $searchTerm . '%');
            }
        }

        return $query->paginate($request->get('per_page', 15));
    }

    public function getAvailableSitesData($leadsId, $tipe = 'baru')
    {
        $isBaru = ($tipe === 'baru');
        $isaddendum = ($tipe === 'addendum');
        $isRekontrak = ($tipe === 'rekontrak');

        if ($isBaru) {
            $query = SpkSite::with([
                'spk',
                'quotation' => function ($q) use ($leadsId) {
                    $q->where('leads_id', $leadsId)
                        ->with(['company', 'salaryRule', 'ruleThr']);
                },
                'leads',
            ])
                ->where('leads_id', $leadsId)
                ->whereHas('spk', function ($q) {
                    $q->whereNull('deleted_at');
                });

            $orderTable = 'sl_spk';
            $orderColumn = 'spk_id';
        } else {
            // Logika untuk Rekontrak ATAU addendum (keduanya pakai QuotationSite)
            $tipeQuotation = $isaddendum ? 'addendum' : 'rekontrak';

            $query = QuotationSite::with([
                'quotation' => function ($q) use ($leadsId, $tipeQuotation) {
                    $q->where('leads_id', $leadsId)
                        ->whereIn('tipe_quotation', [$tipeQuotation, 'revisi'])
                        ->with(['company', 'salaryRule', 'ruleThr']);
                },
                'leads',
            ])
                ->where('leads_id', $leadsId)
                ->whereHas('quotation', function ($q) use ($leadsId, $tipeQuotation) {
                    $q->where('leads_id', $leadsId)
                        ->whereIn('tipe_quotation', [$tipeQuotation, 'revisi'])
                        ->whereNull('deleted_at');
                });

            $orderTable = 'sl_quotation';
            $orderColumn = 'quotation_id';
        }
        $query->whereNull('deleted_at');


        if ($isBaru) {
            $query->whereDoesntHave('site');
        }

        return $query->select('id', 'nama_site', 'provinsi', 'kota', 'penempatan', 'quotation_id', ($isBaru ? 'spk_id' : 'leads_id'))
            ->orderBy(function ($q) use ($orderTable, $orderColumn) {
                $q->select('nomor')
                    ->from($orderTable)
                    ->whereColumn($orderTable . '.id', $orderColumn)
                    ->limit(1);
            }, 'asc')
            ->whereHas('quotation', function ($q) {
                $q->whereNotIn('status_quotation_id', [1, 2]); // skip Terminated
            })
            ->get()
            ->filter(function ($site) {
                return $site->quotation !== null;
            })
            ->map(function ($site) use ($isBaru) {
                $quotation = $site->quotation;
                $companyRelation = $quotation ? $quotation->getRelation('company') : null;

                return [
                    'id' => $site->id,
                    'nomor' => $isBaru ? ($site->spk->nomor ?? null) : ($quotation->nomor ?? null),
                    'nama_site' => $site->nama_site,
                    'provinsi' => $site->provinsi,
                    'kota' => $site->kota,
                    'penempatan' => $site->penempatan,

                    // Data Kontrak
                    'mulai_kontrak' => $quotation?->mulai_kontrak,
                    'kontrak_selesai' => $quotation?->kontrak_selesai,
                    'durasi_kerjasama' => $quotation?->durasi_kerjasama,

                    // Data Entitas (Company)
                    'company' => ($companyRelation instanceof \Illuminate\Database\Eloquent\Model) ? [
                        'id' => $companyRelation->id,
                        'name' => $companyRelation->name ?? $companyRelation->nama ?? null,
                        'code' => $companyRelation->code ?? null,
                    ] : null,

                    // Data Salary Rule
                    'salary_rule' => $quotation?->salaryRule ? [
                        'id' => $quotation->salaryRule->id,
                        'nama' => $quotation->salaryRule->nama_salary_rule ?? null,
                        'cutoff' => $quotation->salaryRule->cutoff,
                        'pembayaran_invoice' => $quotation->salaryRule->pembayaran_invoice,
                        'rilis_payroll' => $quotation->salaryRule->rilis_payroll,
                    ] : null,

                    // Data Rule THR
                    'rule_thr' => $quotation?->ruleThr ? [
                        'id' => $quotation->ruleThr->id,
                        'nama' => $quotation->ruleThr->nama ?? null,
                        'hari_penagihan_invoice' => $quotation->ruleThr->hari_penagihan_invoice,
                        'hari_pembayaran_invoice' => $quotation->ruleThr->hari_pembayaran_invoice,
                        'hari_rilis_thr' => $quotation->ruleThr->hari_rilis_thr,
                    ] : null,

                    'quotation_id' => $quotation?->id,
                    'nomor_quotation' => $quotation?->nomor,
                ];
            })
            ->values();
    }

    // ======================================================================
    // METHODS FOR ACTIVATE FUNCTION
    // ======================================================================

    /**
     * Update PKS and Leads Status
     */
    public function updateStatus($pks, $current_date_time)
    {
        // Update PKS status → Aktif (id: 7)
        $pks->update([
            'ot5' => Auth::user()->full_name,
            'status_pks_id' => 7,
            'is_aktif' => 1,
            'updated_at' => $current_date_time,
            'updated_by' => Auth::user()->full_name,
        ]);

        if ($pks->quotation_id) {
            Quotation::where('id', $pks->quotation_id)
                ->where('status_quotation_id', '!=', 100)
                ->update([
                    'status_quotation_id' => 6,
                    'updated_at' => $current_date_time,
                    'updated_by' => Auth::user()->full_name,
                ]);
        }

        // Update SPK status → "Site Telah Aktif" (id: 4)
        Spk::where('leads_id', $pks->leads_id)
            ->whereNotIn('status_spk_id', [100]) // skip Terminated
            ->update([
                'status_spk_id' => 4,
                'updated_at' => $current_date_time,
                'updated_by' => Auth::user()->full_name,
            ]);

        // Get leads
        $leads = Leads::find($pks->leads_id);
        if (!$leads) {
            throw new \Exception('Leads not found');
        }

        // Pastikan field RO tidak null (diperlukan untuk sync HRIS)
        $leads->ro_id_1 = $leads->ro_id_1 ?? 0;
        $leads->ro_id_2 = $leads->ro_id_2 ?? 0;
        $leads->ro_id_3 = $leads->ro_id_3 ?? 0;
        $leads->ro_id = $leads->ro_id ?? 0;

        // Update Leads status → "Generated Customer" (id: 102)
        $leads->update([
            'status_leads_id' => 102,
            'updated_at' => $current_date_time,
            'updated_by' => Auth::user()->full_name,
        ]);

        return $leads;
    }

    /**
     * Sync Customer to HRIS
     */
    public function syncCustomerToHris($leads, $current_date_time)
    {
        // Check if client exists in HRIS
        $client = Client::where('customer_id', $leads->id)
            ->where('is_active', 1)
            ->first();

        if ($client != null) {
            return $client->id;
        }

        // Create new client in HRIS
        return Client::insertGetId([
            'customer_id' => $leads->id,
            'name' => $leads->nama_perusahaan,
            'address' => $leads->alamat ?? '-',
            'is_active' => 1,
            'created_at' => $current_date_time,
            'created_by' => Auth::user()->id,
            'created_by_user_id' => Auth::user()->id,
            'updated_at' => $current_date_time,
            'updated_by' => Auth::user()->id,
        ]);
    }

    /**
     * Process PKS Sites
     */
    public function processPksSites($pks, $leads, $clientId, $current_date_time)
    {
        $siteList = Site::where('pks_id', $pks->id)
            ->whereNull('deleted_at')
            ->with('quotation')          // ← 1 query tambahan, bukan N
            ->get();

        foreach ($siteList as $site) {
            $quotation = $site->quotation;
            if (!$quotation) {
                continue;
            }

            // Sync Site to HRIS
            $this->syncSiteToHris($site, $pks, $leads, $quotation, $clientId, $current_date_time);

            // Update Quotation Calculations
            $this->updateQuotationCalculations($site, $quotation, $leads, $current_date_time);
        }
    }

    /**
     * Sync Site to HRIS
     */
    private function syncSiteToHris($site, $pks, $leads, $quotation, $clientId, $current_date_time)
    {
        HrisSite::create([
            'site_id' => $site->id,
            'code' => $leads->nomor,
            'proyek_id' => 0,
            'contract_number' => $pks->nomor,
            'name' => $site->nama_site,
            'address' => $site->penempatan,
            'layanan_id' => $site->kebutuhan_id,
            'client_id' => $clientId,
            'city_id' => $site->kota_id,
            'branch_id' => $leads->branch_id,
            'company_id' => $quotation->company_id,
            'pic_id_1' => $leads->ro_id_1,
            'pic_id_2' => $leads->ro_id_2,
            'pic_id_3' => $leads->ro_id_3,
            'supervisor_id' => $leads->ro_id,
            'reliever' => $quotation->joker_reliever,
            'contract_value' => 0,
            'contract_start' => $pks->kontrak_awal,
            'contract_end' => $pks->kontrak_akhir,
            'contract_terminated' => null,
            'note_terminated' => '',
            'contract_status' => 'Aktif',
            'health_insurance_status' => 'Terdaftar',
            'labor_insurance_status' => 'Terdaftar',
            'vacation' => 0,
            'attendance_machine' => '',
            'is_active' => 1,
            'created_at' => $current_date_time,
            'created_by' => Auth::user()->id,
            'created_by_user_id' => Auth::user()->id,
            'updated_at' => $current_date_time,
            'updated_by' => Auth::user()->id,
        ]);
    }

    /**
     * Update Quotation Calculations
     */
    private function updateQuotationCalculations($site, $quotation, $leads, $current_date_time)
    {
        // Get quotation details
        $detailQuotation = QuotationDetail::whereNull('deleted_at')
            ->where('quotation_site_id', $site->quotation_site_id)
            ->get();

        // Calculate quotation (assuming QuotationService exists)
        // Note: You might need to adjust this based on your actual QuotationService
        // $quotationService = new \App\Services\QuotationService();
        // $calcQuotation = $quotationService->calculateQuotation($quotation);

        // For now, we'll use simplified calculation
        $calcQuotation = $this->calculateQuotationSimple($quotation);

        // Update HPP and COSS calculations
        $totalData = $this->updateHppAndCossCalculations($calcQuotation, $leads, $current_date_time);

        // Insert Quotation Margin
        $this->insertQuotationMargin($quotation, $leads, $totalData, $current_date_time);
    }

    /**
     * Simple Quotation Calculation (replace with actual service if available)
     */
    private function calculateQuotationSimple($quotation)
    {
        // This is a simplified version. Replace with actual calculation logic
        return (object) [
            'jumlah_hc' => 0,
            'nominal_upah' => 0,
            'total_invoice' => 0,
            'total_invoice_coss' => 0,
            'ppn' => 0,
            'ppn_coss' => 0,
            'grand_total_sebelum_pajak' => 0,
            'grand_total_sebelum_pajak_coss' => 0,
            'nominal_management_fee' => 0,
            'nominal_management_fee_coss' => 0,
            'persentase' => 0,
            'persen_bunga_bank' => 0,
            'persen_insentif' => 0,
            'pembulatan' => 0,
            'pembulatan_coss' => 0,
            'penagihan' => 'Tanpa Pembulatan',
            'pph' => 0,
            'pph_coss' => 0,
            'quotation_detail' => [],
        ];
    }

    /**
     * Update HPP and COSS Calculations
     */
    private function updateHppAndCossCalculations($calcQuotation, $leads, $current_date_time)
    {
        $totalNominal = 0;
        $totalNominalCoss = 0;
        $ppn = 0;
        $ppnCoss = 0;
        $totalBiaya = 0;
        $totalBiayaCoss = 0;

        // Assuming we have quotation details
        foreach ($calcQuotation->quotation_detail as $kbd) {
            // Update HPP calculation
            QuotationDetailHpp::whereNull('deleted_at')
                ->where('quotation_detail_id', $kbd->id)
                ->whereNull('deleted_at')
                ->update([
                    'position_id' => $kbd->position_id ?? 0,
                    'leads_id' => $leads->id,
                    'jumlah_hc' => $calcQuotation->jumlah_hc,
                    'gaji_pokok' => $calcQuotation->nominal_upah,
                    // Add other fields as needed
                    'updated_at' => $current_date_time,
                    'updated_by' => Auth::user()->full_name,
                ]);

            // Update COSS calculation
            QuotationDetailCoss::whereNull('deleted_at')
                ->where('quotation_detail_id', $kbd->id)
                ->update([
                    'position_id' => $kbd->position_id ?? 0,
                    'leads_id' => $leads->id,
                    'jumlah_hc' => $calcQuotation->jumlah_hc,
                    'gaji_pokok' => $calcQuotation->nominal_upah,
                    // Add other fields as needed
                    'updated_at' => $current_date_time,
                    'updated_by' => Auth::user()->full_name,
                ]);

            // Accumulate totals
            $totalNominal += $calcQuotation->total_invoice;
            $totalNominalCoss += $calcQuotation->total_invoice_coss;
            $ppn += $calcQuotation->ppn;
            $ppnCoss += $calcQuotation->ppn_coss;
            $totalBiaya += $kbd->sub_total_personil ?? 0;
            $totalBiayaCoss += $kbd->sub_total_personil ?? 0;
        }

        // Calculate margins
        $margin = $totalNominal - $ppn - $totalBiaya;
        $marginCoss = $totalNominalCoss - $ppnCoss - $totalBiayaCoss;
        $gpm = $totalBiaya > 0 ? ($margin / $totalBiaya) * 100 : 0;
        $gpmCoss = $totalBiayaCoss > 0 ? ($marginCoss / $totalBiayaCoss) * 100 : 0;

        return compact(
            'totalNominal',
            'totalNominalCoss',
            'ppn',
            'ppnCoss',
            'totalBiaya',
            'totalBiayaCoss',
            'margin',
            'marginCoss',
            'gpm',
            'gpmCoss'
        );
    }

    /**
     * Insert Quotation Margin
     */
    private function insertQuotationMargin($quotation, $leads, $totalData, $current_date_time)
    {
        QuotationMargin::create([
            'quotation_id' => $quotation->id,
            'leads_id' => $leads->id,
            'nominal_hpp' => $totalData['totalNominal'],
            'nominal_harga_pokok' => $totalData['totalNominalCoss'],
            'ppn_hpp' => $totalData['ppn'],
            'ppn_harga_pokok' => $totalData['ppnCoss'],
            'total_biaya_hpp' => $totalData['totalBiaya'],
            'total_biaya_harga_pokok' => $totalData['totalBiayaCoss'],
            'margin_hpp' => $totalData['margin'],
            'margin_harga_pokok' => $totalData['marginCoss'],
            'gpm_hpp' => $totalData['gpm'],
            'gpm_harga_pokok' => $totalData['gpmCoss'],
            'created_at' => $current_date_time,
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::id(),
        ]);
    }

    /**
     * Create Customer Activity Log
     */
    public function createCustomerActivityLog($pks, $leads, $current_date_time)
    {
        $nomorActivity = $this->generateNomorActivity($leads);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'pks_id' => $pks->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => $current_date_time,
            'nomor' => $nomorActivity,
            'tipe' => 'PKS',
            'notes' => 'PKS dengan nomor :' . $pks->nomor . ' telah diaktifkan oleh ' . Auth::user()->full_name,
            'is_activity' => 0,
            'user_id' => Auth::user()->id,
            'created_at' => $current_date_time,
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::id(),
        ]);
        if ($leads) {
            $leads->tgl_leads = Carbon::now()->toDateString();  // Set ke tanggal activity terbaru
            $leads->save();
        }
    }

    /**
     * Handle Customer Status
     */

    /**
     * Create Customer Creation Activity
     */
    private function createCustomerCreationActivity($leads, $customerNomor, $current_date_time)
    {
        $nomorActivity = $this->generateNomorActivity($leads);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => $current_date_time,
            'nomor' => $nomorActivity,
            'tipe' => 'CUSTOMER',
            'notes' => 'Customer dengan nomor :' . $customerNomor . ' terbentuk dari PKS',
            'is_activity' => 0,
            'user_id' => Auth::id(),
            'created_at' => $current_date_time,
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::id(),
        ]);
    }

    /**
     * Add Detail PIC to Quotation
     *
     * @param  Quotation  $quotation
     */
    private function addDetailPic($quotation, array $picData, string $current_date_time): void
    {
        try {
            $jabatan = JabatanPic::where('id', $picData['jabatan'])->first();

            if (!$jabatan) {
                throw new \Exception("Jabatan dengan ID {$picData['jabatan']} tidak ditemukan");
            }

            QuotationPic::create([
                'quotation_id' => $quotation->id,
                'nama' => $picData['nama'],
                'jabatan_id' => $jabatan->id,
                'no_telp' => $picData['no_telp'],
                'email' => $picData['email'],
                'created_at' => $current_date_time,
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to add detail PIC: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Store PKS file to storage
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     */
    public function storePksFile($file): string
    {
        $fileExtension = $file->getClientOriginalExtension();
        $originalFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $fileName = $originalFileName . date('YmdHis') . rand(10000, 99999) . '.' . $fileExtension;

        // Simpan file ke disk 'pks' yang sudah dikonfigurasi
        Storage::disk('pks')->put($fileName, file_get_contents($file));

        return $fileName;
    }

    /**
     * Create upload activity log for PKS
     *
     * @param  Pks  $pks
     */
    public function createUploadPksActivity($pks, Leads $leads): void
    {
        $nomorActivity = $this->generateNomorActivity($leads);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'pks_id' => $pks->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $nomorActivity,
            'tipe' => 'PKS',
            'notes' => 'PKS dengan nomor : ' . $pks->nomor . ' telah diupload dan disetujui',
            'is_activity' => 0,
            'user_id' => Auth::id(),
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::id(),
        ]);
        if ($leads) {
            $leads->tgl_leads = Carbon::now()->toDateString();  // Set ke tanggal activity terbaru
            $leads->save();
        }
    }

    public function logPerjanjianChange($perjanjian, Leads $leads)
    {
        $pks = Pks::find($perjanjian->pks_id, );
        if ($pks && $pks->leads_id) {
            if ($leads) {
                $nomorActivity = $this->generateNomorActivity($leads);
                CustomerActivity::create([
                    'leads_id' => $leads->id,
                    'pks_id' => $perjanjian->pks_id,
                    'tgl_activity' => now(),
                    'nomor' => $nomorActivity,
                    'tipe' => 'PKS_PERJANJIAN',
                    'notes' => "Perubahan pasal {$perjanjian->pasal} diedit oleh " . Auth::user()->full_name,
                    'created_by' => Auth::user()->full_name,
                    'created_by_user_id' => Auth::id(),
                ]);
            }
        }
        if ($leads) {
            $leads->tgl_leads = Carbon::now()->toDateString();  // Set ke tanggal activity terbaru
            $leads->save();
        }
    }

    public function autoSyncCustomerActiveStatus(): void
    {
        $activeLeadsIds = Pks::select('leads_id')
            ->where('is_aktif', 1)
            ->whereNull('deleted_at')
            ->where('kontrak_akhir', '>=', now()->toDateString())
            ->pluck('leads_id')
            ->unique();

        DB::table('sl_leads')
            ->whereNotNull('customer_id')
            ->whereIn('id', $activeLeadsIds)
            ->where('customer_active', '!=', 1)
            ->update(['customer_active' => 1]);

        DB::table('sl_leads')
            ->whereNotNull('customer_id')
            ->whereNotIn('id', $activeLeadsIds)
            ->where('customer_active', '!=', 0)
            ->update(['customer_active' => 0]);
    }
}
