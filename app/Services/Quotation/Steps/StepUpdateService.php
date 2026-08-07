<?php

namespace App\Services\Quotation\Steps;

use App\Models\AplikasiPendukung;
use App\Models\Barang;
use App\Models\BarangDefaultQty;
use App\Models\BidangPerusahaan;
use App\Models\JabatanPic;
use App\Models\JenisBarang;
use App\Models\JenisPerusahaan;
use App\Models\ManagementFee;
use App\Models\Position;
use App\Models\Quotation;
use App\Models\QuotationDetailHpp;
use App\Models\QuotationDetailTunjangan;
use App\Models\QuotationDevices;
use App\Models\QuotationKaporlap;
use App\Models\QuotationManagementFee;
use App\Models\SalaryRule;
use App\Models\Top;
use App\Models\Training;
use App\Models\Umk;
use App\Services\Quotation\QuotationBarangService;
use App\Services\Quotation\QuotationBusinessService;
use App\Services\Quotation\QuotationNotificationService;
use App\Services\Quotation\QuotationService;
use Illuminate\Support\Facades\Auth;

/**
 * Simplified router — delegates step updates to dedicated Step*Service classes.
 * Keeps getStepRelations() and prepareStepData() for backward compatibility.
 */
class StepUpdateService
{
    private array $handlers;

    protected $quotationBarangService;

    protected $quotationNotificationService;

    protected $quotationBusinessService;

    public function __construct(
        QuotationBarangService $quotationBarangService,
        QuotationNotificationService $quotationNotificationService,
        QuotationBusinessService $quotationBusinessService,
        Step1Service $step1,
        Step2Service $step2,
        \App\Services\Quotation\Steps\Step3Service $step3,
        Step5Service $step5,
        Step6Service $step6,
        Step7Service $step7,
        Step8Service $step8,
        Step9Service $step9,
        Step10Service $step10,
        DataSiteService $dataSite
    ) {
        $this->quotationBarangService = $quotationBarangService;
        $this->quotationNotificationService = $quotationNotificationService;
        $this->quotationBusinessService = $quotationBusinessService;

        $this->handlers = [
            'dataSite' => $dataSite,
            1 => $step1, 2 => $step2, 3 => $step3,
            5 => $step5, 6 => $step6, 7 => $step7, 8 => $step8,
            9 => $step9, 10 => $step10,
        ];
    }

    public function getQuotationService(): QuotationService
    {
        return app(QuotationService::class);
    }

    public function getStepRelations(int $step): array
    {
        $relations = [
            'leads',
            'statusQuotation',
            'quotationSites',
            'quotationDetails',
            'quotationPics',
            'company',
        ];

        $logicalStepName = StepMapper::resolveUpdateMethod(1, $step);

        if ($logicalStepName === 'updateHeadcount') {
            $additionalRelations[] = 'quotationDetails.quotationDetailRequirements';
            $additionalRelations[] = 'quotationDetails.quotationDetailTunjangans';
            $additionalRelations[] = 'quotationDetails.position';
        }
        if ($logicalStepName === 'updateCosting') {
            $additionalRelations[] = 'quotationDetails.wage';
        }

        // Just include all standard operational relations if it's beyond a certain point,
        // or just include them always since it's lazy loading anyway.
        $additionalRelations[] = 'quotationAplikasis';
        $additionalRelations[] = 'quotationKaporlaps';
        $additionalRelations[] = 'quotationDevices';
        $additionalRelations[] = 'quotationChemicals';
        $additionalRelations[] = 'quotationOhcs';
        $additionalRelations[] = 'quotationTrainings';
        $additionalRelations[] = 'quotationKerjasamas';

        return array_merge($relations, $additionalRelations);
    }

    public function prepareStepData(Quotation $quotation, int $step): array
    {
        $data = [
            'quotation' => $quotation,
            'step' => $step,
            'additional_data' => [],
        ];

        $logicalStepName = StepMapper::resolveUpdateMethod($quotation->version ?? 1, $step);

        switch ($logicalStepName) {
            case 'updateDataSite':
                $data['additional_data']['jenis_perusahaan_list'] = \App\Models\JenisPerusahaan::getAllActive();
                break;

            case 'updateJenisKontrak':
                break;

            case 'updateDetailKontrak':
                $roleId = Auth::user()->cais_role_id;
                $data['additional_data']['salary_rules'] = in_array($roleId, [29, 30, 31, 32, 33])
                    ? SalaryRule::whereIn('id', [1, 2])->get()
                    : SalaryRule::all();
                $data['additional_data']['top_list'] = Top::orderBy('nama', 'asc')->get();
                break;

            case 'updateHeadcount':
                $data['additional_data']['positions'] = Position::where('is_active', 1)
                    ->where('layanan_id', $quotation->kebutuhan_id)
                    ->orderBy('name', 'asc')
                    ->get();

                $data['additional_data']['quotation_sites'] = $quotation->quotationSites->map(function ($site) {
                    return [
                        'id' => $site->id,
                        'nama_site' => $site->nama_site,
                        'provinsi' => $site->provinsi,
                        'kota' => $site->kota,
                        'penempatan' => $site->penempatan,
                    ];
                })->toArray();
                break;

            case 'updateCosting':
                $data['additional_data']['manajemen_fee_list'] = ManagementFee::all();
                $data['additional_data']['management_fee_config'] =
                    QuotationManagementFee::resolveForQuotation($quotation->id);

                $data['additional_data']['management_fee_component_labels'] = [
                    'is_thr' => 'THR (Tunjangan Hari Raya)',
                    'is_kompensasi' => 'Kompensasi PKWT',
                    'is_thl' => 'Tunjangan Hari Libur Nasional',
                    'is_lembur' => 'Lembur (Flat)',
                    'is_bpjs_kes' => 'BPJS Kesehatan',
                    'is_bpjs_tk' => 'BPJS Ketenagakerjaan (JKK+JKM+JHT+JP)',
                    'is_chemical' => 'Chemical',
                    'is_kaporlap' => 'Kaporlap / Seragam',
                    'is_device' => 'Device / Peralatan',
                    'is_ohc' => 'OHC',
                    'is_tunjangan_lain' => 'Tunjangan Lain',
                ];

                $data['additional_data']['umk_per_site'] = [];
                foreach ($quotation->quotationSites as $site) {
                    $umk = Umk::byCity($site->kota_id)
                        ->active()
                        ->first();

                    $data['additional_data']['umk_per_site'][$site->id] = [
                        'site_id' => $site->id,
                        'site_name' => $site->nama_site,
                        'city_id' => $site->kota_id,
                        'city_name' => $site->kota,
                        'umk_value' => $umk ? $umk->umk : 0,
                        'umk_display' => $umk->formatUmk(),
                    ];
                }
                break;

            case 'updateBpjs':
                $data['additional_data']['jenis_perusahaan_list'] = JenisPerusahaan::getAllActive();
                $data['additional_data']['bidang_perusahaan_list'] = BidangPerusahaan::getAllActive();
                break;

            case 'updateAplikasiPendukung':
                $data['additional_data']['aplikasi_pendukung_list'] = AplikasiPendukung::getAllActive();
                $data['additional_data']['selected_aplikasi'] = $quotation->quotationAplikasis
                    ->pluck('aplikasi_pendukung_id')
                    ->toArray();
                break;

            case 'updateKaporlap':
                $arrKaporlap = $quotation->kebutuhan_id != 1 ? [5] : [1, 2, 3, 4, 5];

                $data['additional_data']['jenis_barang_list'] = JenisBarang::whereIn('id', $arrKaporlap)->get();

                $data['additional_data']['kaporlap_list'] = Barang::whereIn('jenis_barang_id', $arrKaporlap)
                    ->ordered()
                    ->get()
                    ->map(function ($barang) use ($quotation) {
                        foreach ($quotation->quotationDetails as $detail) {
                            $barang->{"jumlah_{$detail->id}"} = 0;

                            if ($quotation->revisi == 0) {
                                $qtyDefault = BarangDefaultQty::byBarang($barang->id)
                                    ->byLayanan($quotation->kebutuhan_id)
                                    ->first();
                                $barang->{"jumlah_{$detail->id}"} = $qtyDefault->qty_default ?? 0;
                            } else {
                                $existing = QuotationKaporlap::byBarangAndDetail($barang->id, $detail->id)
                                    ->first();
                                $barang->{"jumlah_{$detail->id}"} = $existing->jumlah ?? 0;
                            }
                        }

                        return $barang;
                    });
                break;

            case 'updatePeralatan':
                $data['additional_data']['jenis_barang_list'] = JenisBarang::whereIn('id', [9, 10, 11, 12, 17])->get();

                $data['additional_data']['devices_list'] = Barang::whereIn('jenis_barang_id', [8, 9, 10, 11, 12, 17])
                    ->ordered()
                    ->get()
                    ->map(function ($barang) use ($quotation) {
                        $barang->jumlah = 0;

                        if ($quotation->revisi == 0) {
                            $qtyDefault = BarangDefaultQty::byBarang($barang->id)
                                ->byLayanan($quotation->kebutuhan_id)
                                ->first();

                            $barang->jumlah = $qtyDefault->qty_default ?? 0;
                        } else {
                            $existing = QuotationDevices::byBarangAndQuotation($barang->id, $quotation->id)
                                ->first();

                            $barang->jumlah = $existing->jumlah ?? 0;
                        }

                        return $barang;
                    });
                break;

            case 'updateChemical':
                $data['additional_data']['jenis_barang_list'] = JenisBarang::whereIn('id', [13, 14, 15, 16, 18, 19])->get();

                $data['additional_data']['chemical_list'] = Barang::whereIn('jenis_barang_id', [13, 14, 15, 16, 18, 19])
                    ->ordered()
                    ->get()
                    ->map(function ($barang) {
                        $barang->harga_formatted = $barang->formatted_harga;

                        return $barang;
                    });
                break;

            case 'updateOperasional':
                $data['additional_data']['jenis_barang_list'] = JenisBarang::whereIn('id', [6, 7, 8])->get();
                $data['additional_data']['training_list'] = Training::all();
                $data['additional_data']['ohc_list'] = Barang::whereIn('jenis_barang_id', [6, 7, 8])
                    ->ordered()
                    ->get()
                    ->map(function ($barang) {
                        $barang->harga_formatted = $barang->formatted_harga;

                        return $barang;
                    });
                break;

            case 'updatePricing':
                $data['additional_data']['calculated_quotation'] = $this->getQuotationService()->calculateQuotation($quotation);

                $data['additional_data']['hpp_details'] = [];
                foreach ($quotation->quotationDetails as $detail) {
                    $hpp = QuotationDetailHpp::where('quotation_detail_id', $detail->id)->first();
                    $data['additional_data']['hpp_details'][$detail->id] = [
                        'tunjangan_hari_raya' => $hpp->tunjangan_hari_raya ?? 0,
                        'kompensasi' => $hpp->kompensasi ?? 0,
                        'insentif' => $hpp->insentif ?? 0,
                    ];
                }

                $data['additional_data']['daftar_tunjangan'] = QuotationDetailTunjangan::distinctTunjanganByQuotation($quotation->id);

                $quotation->load(['quotationDetails.quotationDetailTunjangans']);

                $data['additional_data']['training_list'] = Training::all();
                $data['additional_data']['selected_training'] = $quotation->quotationTrainings
                    ->pluck('training_id')
                    ->toArray();

                $data['additional_data']['jabatan_pic_list'] = JabatanPic::all();
                break;

            case 12:
                $finalData = [
                    'quotation_kerjasamas' => $quotation->relationLoaded('quotationKerjasamas')
                        ? $quotation->quotationKerjasamas->map(function ($kerjasama) {
                            return [
                                'id' => $kerjasama->id,
                                'perjanjian' => $kerjasama->perjanjian,
                                'is_delete' => $kerjasama->is_delete ?? 1,
                                'created_at' => $kerjasama->created_at,
                                'created_by' => $kerjasama->created_by,
                                'created_by_user_id' => $kerjasama->created_by_user_id,
                            ];
                        })->toArray()
                        : [],
                    'final_confirmation' => true,
                ];

                $data['additional_data']['final_data'] = $finalData;
                break;
        }

        if (in_array($step, [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12])) {
            $data['additional_data']['salary_rule_current'] = SalaryRule::find($quotation->salary_rule_id);
        }

        if (in_array($step, [3, 4, 5, 6, 7, 8, 9, 10, 11, 12])) {
            $data['additional_data']['quotation_sites'] = $quotation->quotationSites;
            $data['additional_data']['quotation_details'] = $quotation->quotationDetails;
        }

        return $data;
    }

    public function __call(string $method, array $args): void
    {
        $handlerMap = [
            'updateDataSite' => 'dataSite',
            'updateJenisKontrak' => 1,
            'updateDetailKontrak' => 2,
            'updateHeadcount' => 3,
            'updateBpjs' => 5,
            'updateAplikasiPendukung' => 6,
            'updateKaporlap' => 7,
            'updatePeralatan' => 8,
            'updateChemical' => 9,
            'updateOperasional' => 10,
        ];

        if (isset($handlerMap[$method])) {
            $key = $handlerMap[$method];
            if (isset($this->handlers[$key])) {
                $this->handlers[$key]->execute($args[0], $args[1]);
                return;
            }
        }

        // Keep backward compatibility for things directly calling updateStepX
        if (preg_match('/^updateStep(\d+)$/', $method, $m)) {
            $step = (int) $m[1];
            if (isset($this->handlers[$step])) {
                $this->handlers[$step]->execute($args[0], $args[1]);

                return;
            }
        }
        throw new \BadMethodCallException("Method {$method} not found");
    }
}
