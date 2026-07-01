<?php

namespace App\Services;

use App\Models\Company;
use App\Models\KategoriSesuaiHc;
use App\Models\Kebutuhan;
use App\Models\Leads;
use App\Models\Loyalty;
use App\Models\Pks;
use App\Models\PksWizardStatus;
use App\Models\Quotation;
use App\Models\QuotationSite;
use App\Models\RuleThr;
use App\Models\SalaryRule;
use App\Models\Spk;
use App\Models\SpkSite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PksWizardService
{
    private const STEP_PAYLOAD_KEYS = [
        1 => 'source',
        2 => 'header',
        3 => 'sites',
        4 => 'pic',
        5 => 'commercial_snapshot',
        6 => 'template_inputs',
        7 => null,
    ];

    public function __construct(private readonly QuotationService $quotationService)
    {
    }

    public function initialize(string $tipe, array $data, User $user): Pks
    {
        if (!in_array($tipe, ['baru', 'rekontrak', 'addendum'], true)) {
            throw new \InvalidArgumentException('Tipe PKS wizard tidak valid');
        }

        return DB::transaction(function () use ($tipe, $data, $user) {
            $leads = Leads::filterByUserRole($user)->findOrFail($data['leads_id']);
            $quotation = !empty($data['quotation_id']) ? Quotation::find($data['quotation_id']) : null;
            $spk = !empty($data['spk_id']) ? Spk::find($data['spk_id']) : null;

            $pksInduk = null;
            $companyId = $data['company_id'] ?? null;

            if ($tipe === 'addendum') {
                $pksInduk = Pks::with('quotations')->findOrFail($data['pks_induk_id']);
                $companyId ??= $pksInduk->company_id;
                $quotation ??= $pksInduk->quotations;
            }

            $company = $companyId ? Company::find($companyId) : null;

            if (!$company && $tipe !== 'addendum') {
                throw new \InvalidArgumentException('Company tidak ditemukan');
            }

            $nomorAsli = $tipe === 'addendum'
                ? $this->generateNomorAddendum($pksInduk)
                : $this->generateNomor($leads, $company);

            $layananId = $quotation?->kebutuhan_id ?? $leads->kebutuhan_id;
            $kebutuhan = $layananId ? Kebutuhan::find($layananId) : null;

            $wizardPayload = [
                'source' => [
                    'tipe_pks' => $tipe,
                    'leads_id' => $leads->id,
                    'quotation_id' => $quotation?->id,
                    'spk_id' => $spk?->id,
                    'pks_induk_id' => $pksInduk?->id,
                    'company_id' => $companyId,
                    'leads' => [
                        'id' => $leads->id,
                        'nomor' => $leads->nomor,
                        'nama_perusahaan' => $leads->nama_perusahaan,
                        'alamat' => $leads->alamat,
                        'branch_id' => $leads->branch_id,
                        'pic' => $leads->pic,
                    ],
                    'quotation' => $quotation ? [
                        'id' => $quotation->id,
                        'nomor' => $quotation->nomor,
                        'status_quotation_id' => $quotation->status_quotation_id,
                    ] : null,
                    'spk' => $spk ? [
                        'id' => $spk->id,
                        'nomor' => $spk->nomor,
                        'status_spk_id' => $spk->status_spk_id,
                    ] : null,
                    'pks_induk' => $pksInduk ? [
                        'id' => $pksInduk->id,
                        'nomor' => $pksInduk->nomor,
                        'tipe_pks' => $pksInduk->tipe_pks,
                    ] : null,
                ],
                'header' => [],
                'sites' => [],
                'pic' => [],
                'commercial_snapshot' => [],
                'template_inputs' => [],
            ];

            $templatePayload = [
                'pks' => [
                    'nomor' => 'draft/' . $nomorAsli,
                ],
                'leads' => [
                    'nama_perusahaan' => $leads->nama_perusahaan,
                    'pic' => $leads->pic,
                ],
                'company' => $company ? [
                    'id' => $company->id,
                    'name' => $company->name,
                    'nama_direktur' => $company->nama_direktur,
                ] : null,
                'kebutuhan' => $kebutuhan ? [
                    'id' => $kebutuhan->id,
                    'nama' => $kebutuhan->nama,
                ] : null,
                'rule_thr' => null,
                'salary_rule' => null,
                'sites' => [],
                'commercial' => [],
            ];

            return Pks::create([
                'leads_id' => $leads->id,
                'quotation_id' => $quotation?->id,
                'branch_id' => $leads->branch_id,
                'nomor' => 'draft/' . $nomorAsli,
                'kode_perusahaan' => $leads->nomor,
                'nama_perusahaan' => $leads->nama_perusahaan,
                'alamat_perusahaan' => $leads->alamat,
                'layanan_id' => $layananId,
                'layanan' => $kebutuhan?->nama,
                'bidang_usaha_id' => $leads->bidang_perusahaan_id,
                'bidang_usaha' => $leads->bidang_perusahaan,
                'jenis_perusahaan_id' => $leads->jenis_perusahaan_id,
                'jenis_perusahaan' => $leads->jenis_perusahaan,
                'provinsi_id' => $leads->provinsi_id,
                'provinsi' => $leads->provinsi,
                'kota_id' => $leads->kota_id,
                'kota' => $leads->kota,
                'pma' => $leads->pma,
                'company_id' => $companyId,
                'pks_induk_id' => $pksInduk?->id,
                'tipe_pks' => $tipe,
                'status_pks_id' => 5,
                'wizard_status_id' => PksWizardStatus::INITIALIZED,
                'wizard_current_step' => 1,
                'wizard_completed_steps' => [],
                'wizard_payload' => $wizardPayload,
                'template_payload' => $templatePayload,
                'pasal_preview_payload' => [],
                'initialized_at' => now(),
                'sales_id' => $user->id,
                'created_by' => $user->full_name,
                'created_by_user_id' => $user->id,
            ]);
        });
    }

    public function getStep(Pks $pks, int $step): array
    {
        $this->assertValidStep($step);

        $payload = $pks->wizard_payload ?? [];
        $tipePks = $this->resolveTipePks($pks, $payload);
        $stepKey = self::STEP_PAYLOAD_KEYS[$step];

        return [
            'pks_id' => $pks->id,
            'step' => $step,
            'wizard_status_id' => $pks->wizard_status_id,
            'wizard_status' => $pks->wizardStatus?->nama,
            'wizard_current_step' => $pks->wizard_current_step,
            'wizard_completed_steps' => $pks->wizard_completed_steps ?? [],
            'nomor' => $pks->nomor,
            'tipe_pks' => $tipePks,
            'step_data' => $step === 7 ? $payload : Arr::get($payload, $stepKey, []),
            'additional_data' => $this->buildAdditionalData($pks, $step, $payload),
        ];
    }

    public function updateStep(Pks $pks, int $step, array $stepData, bool $markAsComplete, User $user): Pks
    {
        $this->assertValidStep($step);

        $payload = $pks->wizard_payload ?? [];
        $completedSteps = $pks->wizard_completed_steps ?? [];
        $stepKey = self::STEP_PAYLOAD_KEYS[$step];

        if ($step === 5) {
            $stepData = $this->resolveCommercialSnapshot($pks);
        }

        if ($stepKey !== null) {
            $payload[$stepKey] = $stepData;
        }

        if ($markAsComplete && !in_array($step, $completedSteps, true)) {
            $completedSteps[] = $step;
            sort($completedSteps);
        }

        $nextStep = $step < 7 ? $step + 1 : 7;
        $isReadyToFinalize = count(array_intersect(range(1, 7), $completedSteps)) === 7;

        $pks->fill([
            'wizard_payload' => $payload,
            'template_payload' => $this->mergeTemplatePayload($pks, $payload),
            'wizard_completed_steps' => $completedSteps,
            'wizard_current_step' => max($pks->wizard_current_step ?? 1, $nextStep),
            'wizard_status_id' => $isReadyToFinalize ? PksWizardStatus::READY_TO_FINALIZE : PksWizardStatus::IN_PROGRESS,
            'updated_by' => $user->full_name,
        ]);

        $pks->save();

        return $pks->fresh(['wizardStatus', 'leads']);
    }

    public function cancel(Pks $pks, User $user): Pks
    {
        if ((int) $pks->wizard_status_id === PksWizardStatus::FINALIZED) {
            throw new \InvalidArgumentException('PKS wizard yang sudah finalized tidak dapat dibatalkan');
        }

        $pks->update([
            'wizard_status_id' => PksWizardStatus::CANCELLED,
            'updated_by' => $user->full_name,
        ]);

        return $pks->fresh(['wizardStatus', 'leads']);
    }

    public function getAvailableQuotationsByLeads(
        int $leadsId,
        ?int $spkId = null,
        ?string $search = null,
        string $searchBy = 'nomor',
        int $perPage = 10
    ): LengthAwarePaginator
    {
        $lead = Leads::filterByUserRole()->findOrFail($leadsId);

        $query = Quotation::with(['company:id,name,code', 'salaryRule:id,nama_salary_rule', 'ruleThr:id,nama'])
            ->where('leads_id', $lead->id)
            ->whereNull('deleted_at')
            ->orderByDesc('id');

        if ($spkId) {
            $spk = Spk::with('spkSites:id,spk_id,quotation_id')->where('leads_id', $lead->id)->findOrFail($spkId);
            $linkedQuotationIds = collect([$spk->quotation_id])
                ->merge($spk->spkSites->pluck('quotation_id'))
                ->filter()
                ->unique()
                ->values();

            if ($linkedQuotationIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', $linkedQuotationIds->all());
            }
        }

        if ($search !== null && $search !== '') {
            switch ($searchBy) {
                case 'tipe_quotation':
                    $query->where('tipe_quotation', 'like', '%' . $search . '%');
                    break;
                case 'company_name':
                    $query->whereHas('company', function ($companyQuery) use ($search) {
                        $companyQuery->where('name', 'like', '%' . $search . '%');
                    });
                    break;
                case 'company_code':
                    $query->whereHas('company', function ($companyQuery) use ($search) {
                        $companyQuery->where('code', 'like', '%' . $search . '%');
                    });
                    break;
                case 'salary_rule':
                    $query->whereHas('salaryRule', function ($salaryRuleQuery) use ($search) {
                        $salaryRuleQuery->where('nama_salary_rule', 'like', '%' . $search . '%');
                    });
                    break;
                case 'rule_thr':
                    $query->whereHas('ruleThr', function ($ruleThrQuery) use ($search) {
                        $ruleThrQuery->where('nama', 'like', '%' . $search . '%');
                    });
                    break;
                case 'nomor':
                default:
                    $query->where('nomor', 'like', '%' . $search . '%');
                    break;
            }
        }

        $paginator = $query->paginate($perPage);
        $collection = $paginator->getCollection()->map(function (Quotation $quotation) {
                $company = $quotation->getRelation('company');
                $salaryRule = $quotation->getRelation('salaryRule');
                $ruleThr = $quotation->getRelation('ruleThr');

                return [
                    'id' => $quotation->id,
                    'nomor' => $quotation->nomor,
                    'status_quotation_id' => $quotation->status_quotation_id,
                    'tipe_quotation' => $quotation->tipe_quotation,
                    'company_id' => $quotation->company_id,
                    'linked_spk_ids' => Spk::query()
                        ->where('leads_id', $quotation->leads_id)
                        ->where(function ($query) use ($quotation) {
                            $query->where('quotation_id', $quotation->id)
                                ->orWhereHas('spkSites', function ($spkSiteQuery) use ($quotation) {
                                    $spkSiteQuery->where('quotation_id', $quotation->id)
                                        ->whereNull('deleted_at');
                                });
                        })
                        ->pluck('id')
                        ->unique()
                        ->values()
                        ->all(),
                    'company' => $company ? [
                        'id' => $company->id,
                        'name' => $company->name,
                        'code' => $company->code,
                    ] : null,
                    'salary_rule' => $salaryRule ? [
                        'id' => $salaryRule->id,
                        'nama' => $salaryRule->nama_salary_rule,
                    ] : null,
                    'rule_thr' => $ruleThr ? [
                        'id' => $ruleThr->id,
                        'nama' => $ruleThr->nama,
                    ] : null,
                ];
            });

        return $paginator->setCollection($collection);
    }

    public function getAvailableSpkByLeads(
        int $leadsId,
        ?string $search = null,
        string $searchBy = 'nomor',
        int $perPage = 10
    ): LengthAwarePaginator
    {
        $lead = Leads::filterByUserRole()->findOrFail($leadsId);

        $query = Spk::with(['quotation:id,nomor,company_id,salary_rule_id,rule_thr_id', 'quotation.company:id,name,code', 'spkSites:id,spk_id,quotation_id'])
            ->where('leads_id', $lead->id)
            ->whereNull('deleted_at')
            ->orderByDesc('id');

        if ($search !== null && $search !== '') {
            switch ($searchBy) {
                case 'quotation_nomor':
                    $query->whereHas('quotation', function ($quotationQuery) use ($search) {
                        $quotationQuery->where('nomor', 'like', '%' . $search . '%');
                    });
                    break;
                case 'company_name':
                    $query->whereHas('quotation.company', function ($companyQuery) use ($search) {
                        $companyQuery->where('name', 'like', '%' . $search . '%');
                    });
                    break;
                case 'company_code':
                    $query->whereHas('quotation.company', function ($companyQuery) use ($search) {
                        $companyQuery->where('code', 'like', '%' . $search . '%');
                    });
                    break;
                case 'nomor':
                default:
                    $query->where('nomor', 'like', '%' . $search . '%');
                    break;
            }
        }

        $paginator = $query->paginate($perPage);
        $collection = $paginator->getCollection()->map(function (Spk $spk) {
                $quotation = $spk->getRelation('quotation');
                $company = $quotation?->getRelation('company');
                $linkedQuotationIds = collect([$spk->quotation_id])
                    ->merge($spk->spkSites->pluck('quotation_id'))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'id' => $spk->id,
                    'nomor' => $spk->nomor,
                    'status_spk_id' => $spk->status_spk_id,
                    'quotation_id' => $spk->quotation_id,
                    'linked_quotation_ids' => $linkedQuotationIds,
                    'quotation' => $quotation ? [
                        'id' => $quotation->id,
                        'nomor' => $quotation->nomor,
                        'company_id' => $quotation->company_id,
                        'salary_rule_id' => $quotation->salary_rule_id,
                        'rule_thr_id' => $quotation->rule_thr_id,
                        'company' => $company ? [
                            'id' => $company->id,
                            'name' => $company->name,
                            'code' => $company->code,
                        ] : null,
                    ] : null,
                ];
            });

        return $paginator->setCollection($collection);
    }

    private function mergeTemplatePayload(Pks $pks, array $payload): array
    {
        $templatePayload = $pks->template_payload ?? [];
        $header = Arr::get($payload, 'header', []);

        $company = !empty($header['company_id']) ? Company::find($header['company_id']) : $pks->company;
        $salaryRule = !empty($header['salary_rule_id']) ? SalaryRule::find($header['salary_rule_id']) : $pks->salaryRule;
        $ruleThr = !empty($header['rule_thr_id']) ? RuleThr::find($header['rule_thr_id']) : $pks->ruleThr;

        $templatePayload['pks'] = array_filter([
            'nomor' => $pks->nomor,
            'tanggal_pks' => $header['tanggal_pks'] ?? null,
        ], fn($value) => $value !== null);

        $templatePayload['company'] = $company ? [
            'id' => $company->id,
            'name' => $company->name,
            'nama_direktur' => $company->nama_direktur,
        ] : ($templatePayload['company'] ?? null);

        $templatePayload['salary_rule'] = $salaryRule ? [
            'id' => $salaryRule->id,
            'nama' => $salaryRule->nama_salary_rule ?? null,
            'cutoff' => $salaryRule->cutoff,
            'crosscheck_absen' => $salaryRule->crosscheck_absen,
            'pengiriman_invoice' => $salaryRule->pengiriman_invoice,
            'perkiraan_invoice_diterima' => $salaryRule->perkiraan_invoice_diterima,
            'pembayaran_invoice' => $salaryRule->pembayaran_invoice,
            'rilis_payroll' => $salaryRule->rilis_payroll,
        ] : ($templatePayload['salary_rule'] ?? null);

        $templatePayload['rule_thr'] = $ruleThr ? [
            'id' => $ruleThr->id,
            'nama' => $ruleThr->nama,
            'hari_penagihan_invoice' => $ruleThr->hari_penagihan_invoice,
            'hari_pembayaran_invoice' => $ruleThr->hari_pembayaran_invoice,
            'hari_rilis_thr' => $ruleThr->hari_rilis_thr,
        ] : ($templatePayload['rule_thr'] ?? null);

        $templatePayload['commercial'] = Arr::get($payload, 'commercial_snapshot', $templatePayload['commercial'] ?? []);
        $templatePayload['sites'] = Arr::get($payload, 'sites', $templatePayload['sites'] ?? []);

        return $templatePayload;
    }

    private function buildAdditionalData(Pks $pks, int $step, array $payload): array
    {
        $tipePks = $this->resolveTipePks($pks, $payload);

        return match ($step) {
            1 => [
                'source_summary' => Arr::get($payload, 'source', []),
                'available_leads' => $this->getAvailableLeadsData(),
                'available_sites' => $this->getAvailableSitesData($pks->leads_id, $tipePks),
                'is_read_only' => true,
            ],
            2 => [
                'company_options' => $this->getCompanyOptions(),
                'salary_rule_options' => $this->getSalaryRuleOptions(),
                'rule_thr_options' => $this->getRuleThrOptions(),
                'kategori_hc_options' => $this->getKategoriHcOptions(),
                'loyalty_options' => $this->getLoyaltyOptions(),
                'readonly_source_fields' => [
                    'layanan_id' => $pks->layanan_id,
                    'branch_id' => $pks->branch_id,
                    'nama_perusahaan' => $pks->nama_perusahaan,
                    'alamat_perusahaan' => $pks->alamat_perusahaan,
                    'bidang_usaha' => $pks->bidang_usaha,
                    'jenis_perusahaan' => $pks->jenis_perusahaan,
                    'provinsi' => $pks->provinsi,
                    'kota' => $pks->kota,
                ],
            ],
            3 => [
                'available_sites' => $this->getAvailableSitesData($pks->leads_id, $tipePks),
                'selection_mode' => $tipePks === 'baru' ? 'site_ids' : ($tipePks === 'rekontrak' ? 'quotation_site_ids' : 'skipped'),
                'is_read_only' => $tipePks === 'addendum',
            ],
            4 => [
                'contact_defaults' => [
                    'pic_1' => $pks->leads?->pic,
                    'jabatan_pic_1' => $pks->leads?->jabatan,
                    'email_pic_1' => $pks->leads?->email,
                    'telp_pic_1' => $pks->leads?->no_telp,
                ],
            ],
            5 => [
                'commercial_snapshot' => $this->resolveCommercialSnapshot($pks),
                'is_read_only' => true,
            ],
            6 => [
                'template_payload' => $pks->template_payload ?? [],
                'pasal_preview_payload' => $pks->pasal_preview_payload ?? [],
                'preview_status' => 'Gunakan endpoint preview-pasal untuk generate atau edit draft pasal',
            ],
            7 => [
                'review_summary' => [
                    'wizard_payload' => $payload,
                    'template_payload' => $pks->template_payload ?? [],
                    'pasal_preview_payload' => $pks->pasal_preview_payload ?? [],
                ],
            ],
            default => [],
        };
    }

    private function resolveTipePks(Pks $pks, array $payload): string
    {
        $tipePks = $pks->tipe_pks
            ?? Arr::get($payload, 'source.tipe_pks')
            ?? Arr::get($pks->template_payload ?? [], 'pks.tipe_pks');

        if (!is_string($tipePks) || $tipePks === '') {
            return 'baru';
        }

        return $tipePks;
    }

    private function resolveCommercialSnapshot(Pks $pks): array
    {
        $existing = Arr::get($pks->wizard_payload ?? [], 'commercial_snapshot', []);
        if (!empty($existing)) {
            return $existing;
        }

        if (!$pks->quotation_id) {
            return [];
        }

        $quotation = Quotation::with(['salaryRule', 'ruleThr'])->find($pks->quotation_id);
        if (!$quotation) {
            return [];
        }

        $result = $this->quotationService->calculateQuotation($quotation);
        $summary = $result->calculation_summary ?? null;

        return [
            'total_sebelum_pajak' => $summary->grand_total_sebelum_pajak ?? 0,
            'dasar_pengenaan_pajak' => $summary->dpp ?? 0,
            'ppn' => $summary->ppn ?? 0,
            'pph' => $summary->pph ?? 0,
            'total_invoice' => $summary->total_invoice ?? 0,
            'persen_mf' => $quotation->persentase ?? 0,
            'nominal_mf' => $summary->nominal_management_fee ?? 0,
            'persen_bpjs_tk' => $summary->persen_bpjs_ketenagakerjaan ?? 0,
            'nominal_bpjs_tk' => $summary->nominal_bpjs_ketenagakerjaan ?? 0,
            'persen_bpjs_ks' => $summary->persen_bpjs_kesehatan ?? 0,
            'nominal_bpjs_ks' => $summary->nominal_bpjs_kesehatan ?? 0,
            'tgl_kirim_invoice' => $quotation->salaryRule->pengiriman_invoice ?? null,
            'jumlah_hari_top' => $quotation->jumlah_hari_invoice ?? null,
            'tipe_hari_top' => $quotation->tipe_hari_invoice ?? null,
            'tgl_gaji' => $quotation->salaryRule->rilis_payroll ?? null,
            'salary_rule' => $quotation->salaryRule ? [
                'id' => $quotation->salaryRule->id,
                'nama' => $quotation->salaryRule->nama_salary_rule,
                'cutoff' => $quotation->salaryRule->cutoff,
                'crosscheck_absen' => $quotation->salaryRule->crosscheck_absen,
                'pengiriman_invoice' => $quotation->salaryRule->pengiriman_invoice,
                'perkiraan_invoice_diterima' => $quotation->salaryRule->perkiraan_invoice_diterima,
                'pembayaran_invoice' => $quotation->salaryRule->pembayaran_invoice,
                'rilis_payroll' => $quotation->salaryRule->rilis_payroll,
            ] : null,
            'rule_thr' => $quotation->ruleThr ? [
                'id' => $quotation->ruleThr->id,
                'nama' => $quotation->ruleThr->nama,
                'hari_penagihan_invoice' => $quotation->ruleThr->hari_penagihan_invoice,
                'hari_pembayaran_invoice' => $quotation->ruleThr->hari_pembayaran_invoice,
                'hari_rilis_thr' => $quotation->ruleThr->hari_rilis_thr,
            ] : null,
        ];
    }

    private function getAvailableLeadsData(): array
    {
        return Leads::filterByUserRole()
            ->whereHas('spkSites', function ($query) {
                $query->whereNull('sl_spk_site.deleted_at')
                    ->whereHas('spk', fn($subQuery) => $subQuery->whereNull('sl_spk.deleted_at'))
                    ->whereDoesntHave('site', fn($siteQuery) => $siteQuery->whereNull('sl_site.deleted_at'));
            })
            ->select('id', 'nomor', 'nama_perusahaan', 'provinsi', 'kota')
            ->distinct()
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn(Leads $lead) => [
                'id' => $lead->id,
                'nomor' => $lead->nomor,
                'nama_perusahaan' => $lead->nama_perusahaan,
                'provinsi' => $lead->provinsi,
                'kota' => $lead->kota,
            ])
            ->all();
    }

    private function getAvailableSitesData(int $leadsId, ?string $tipe): array
    {
        $tipe = $tipe ?: 'baru';

        if ($tipe === 'addendum') {
            return [];
        }

        $isBaru = $tipe === 'baru';
        $query = $isBaru
            ? SpkSite::with(['spk', 'quotation.company', 'quotation.salaryRule', 'quotation.ruleThr'])
                ->where('leads_id', $leadsId)
                ->whereHas('spk', fn($q) => $q->whereNull('deleted_at'))
                ->whereDoesntHave('site')
            : QuotationSite::with(['quotation.company', 'quotation.salaryRule', 'quotation.ruleThr'])
                ->where('leads_id', $leadsId)
                ->whereHas('quotation', function ($q) use ($leadsId) {
                    $q->where('leads_id', $leadsId)
                        ->whereIn('tipe_quotation', ['rekontrak', 'revisi'])
                        ->whereNull('deleted_at');
                });

        return $query
            ->whereNull('deleted_at')
            ->get()
            ->filter(fn($site) => $site->quotation !== null)
            ->map(function ($site) use ($isBaru) {
                return [
                    'id' => $site->id,
                    'nomor' => $isBaru ? ($site->spk->nomor ?? null) : ($site->quotation->nomor ?? null),
                    'nama_site' => $site->nama_site,
                    'provinsi' => $site->provinsi,
                    'kota' => $site->kota,
                    'penempatan' => $site->penempatan,
                    'nominal_upah' => $site->nominal_upah ?? null,
                    'quotation_id' => $site->quotation?->id,
                    'spk_id' => $site->spk_id ?? null,
                    'company' => $site->quotation?->company ? [
                        'id' => $site->quotation->company->id,
                        'name' => $site->quotation->company->name,
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }

    private function getCompanyOptions(): array
    {
        return Company::where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn($company) => [
                'id' => $company->id,
                'name' => $company->name,
                'code' => $company->code,
            ])
            ->all();
    }

    private function getSalaryRuleOptions(): array
    {
        return SalaryRule::whereNull('deleted_at')
            ->orderBy('nama_salary_rule')
            ->get(['id', 'nama_salary_rule', 'cutoff', 'crosscheck_absen', 'pengiriman_invoice', 'perkiraan_invoice_diterima', 'pembayaran_invoice', 'rilis_payroll'])
            ->map(fn($rule) => [
                'id' => $rule->id,
                'nama' => $rule->nama_salary_rule,
                'cutoff' => $rule->cutoff,
                'crosscheck_absen' => $rule->crosscheck_absen,
                'pengiriman_invoice' => $rule->pengiriman_invoice,
                'perkiraan_invoice_diterima' => $rule->perkiraan_invoice_diterima,
                'pembayaran_invoice' => $rule->pembayaran_invoice,
                'rilis_payroll' => $rule->rilis_payroll,
            ])
            ->all();
    }

    private function getRuleThrOptions(): array
    {
        return RuleThr::whereNull('deleted_at')
            ->orderBy('nama')
            ->get(['id', 'nama', 'hari_penagihan_invoice', 'hari_pembayaran_invoice', 'hari_rilis_thr'])
            ->map(fn($rule) => [
                'id' => $rule->id,
                'nama' => $rule->nama,
                'hari_penagihan_invoice' => $rule->hari_penagihan_invoice,
                'hari_pembayaran_invoice' => $rule->hari_pembayaran_invoice,
                'hari_rilis_thr' => $rule->hari_rilis_thr,
            ])
            ->all();
    }

    private function getKategoriHcOptions(): array
    {
        return KategoriSesuaiHc::whereNull('deleted_at')
            ->orderBy('nama')
            ->get(['id', 'nama'])
            ->map(fn($item) => ['id' => $item->id, 'nama' => $item->nama])
            ->all();
    }

    private function getLoyaltyOptions(): array
    {
        return Loyalty::whereNull('deleted_at')
            ->orderBy('nama')
            ->get(['id', 'nama'])
            ->map(fn($item) => ['id' => $item->id, 'nama' => $item->nama])
            ->all();
    }

    private function assertValidStep(int $step): void
    {
        if (!array_key_exists($step, self::STEP_PAYLOAD_KEYS)) {
            throw new \InvalidArgumentException('Step wizard tidak valid');
        }
    }

    private function generateNomor(Leads $leads, Company $company): string
    {
        $now = Carbon::now();
        $prefix = 'PKS/' . $company->code . '/' . $leads->nomor . '-';
        $month = str_pad($now->month, 2, '0', STR_PAD_LEFT);
        $count = Pks::where('nomor', 'like', $prefix . $month . $now->year . '-%')->withTrashed()->count();
        $sequence = sprintf('%05d', $count + 1);

        return $prefix . $month . $now->year . '-' . $sequence;
    }

    private function generateNomorAddendum(Pks $pksInduk): string
    {
        $nomorPksInduk = preg_replace('/^draft\//', '', $pksInduk->nomor);

        $count = Pks::where('pks_induk_id', $pksInduk->id)
            ->orWhere('nomor', 'like', 'ADD/' . $nomorPksInduk . '/%')
            ->withTrashed()
            ->count();

        return 'ADD/' . $nomorPksInduk . '/' . sprintf('%04d', $count + 1);
    }
}
