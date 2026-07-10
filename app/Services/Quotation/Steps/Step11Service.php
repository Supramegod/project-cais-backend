<?php

namespace App\Services\Quotation\Steps;

use App\Models\Quotation;
use App\Models\QuotationAplikasi;
use App\Models\QuotationDetail;
use App\Models\QuotationDetailCoss;
use App\Models\QuotationDetailHpp;
use App\Models\QuotationDetailWage;
use App\Models\QuotationKerjasama;
use App\Models\QuotationSite;
use App\Models\SalaryRule;
use App\Models\Umk;
use App\Models\Ump;
use App\Services\Quotation\QuotationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Step11Service
{
    private ?QuotationService $quotationService = null;

    private $_detailMap = null;
    private $_wageMap = null;
    private $_hppMap = null;
    private $_cossMap = null;
    private $_siteMap = null;
    private $_umkMap = null;
    private $_umpMap = null;

    public function __construct(
        protected StepHelperService $helper,
        protected StepCalculationService $calculationService,
        protected StepTunjanganService $tunjanganService,
    ) {}

    public function setQuotationService(QuotationService $service): void
    {
        $this->quotationService = $service;
    }

    private function preloadStep11Maps(Quotation $quotation): void
    {
        $quotation->loadMissing('quotationDetails');
        $detailIds = $quotation->quotationDetails->pluck('id')->all();

        $this->_detailMap = QuotationDetail::whereIn('id', $detailIds)
            ->where('quotation_id', $quotation->id)
            ->get()
            ->keyBy('id');
        $this->_wageMap = QuotationDetailWage::whereIn('quotation_detail_id', $detailIds)
            ->get()
            ->keyBy('quotation_detail_id');
        $this->_hppMap = QuotationDetailHpp::whereIn('quotation_detail_id', $detailIds)
            ->get()
            ->keyBy('quotation_detail_id');
        $this->_cossMap = QuotationDetailCoss::whereIn('quotation_detail_id', $detailIds)
            ->get()
            ->keyBy('quotation_detail_id');

        $sites = QuotationSite::where('quotation_id', $quotation->id)->get();
        $this->_siteMap = $sites->keyBy('id');
        $this->_umkMap = Umk::whereIn('city_id', $sites->pluck('kota_id')->filter()->unique()->all())
            ->active()->get()->keyBy('city_id');
        $this->_umpMap = Ump::whereIn('province_id', $sites->pluck('provinsi_id')->filter()->unique()->all())
            ->active()->get()->keyBy('province_id');
    }

    private function clearStep11Maps(): void
    {
        $this->_detailMap = null;
        $this->_wageMap = null;
        $this->_hppMap = null;
        $this->_cossMap = null;
        $this->_siteMap = null;
        $this->_umkMap = null;
        $this->_umpMap = null;
    }

    private function getQuotationService(): QuotationService
    {
        if ($this->quotationService === null) {
            return app(QuotationService::class);
        }

        return $this->quotationService;
    }

    public function execute(Quotation $quotation, Request $request): void
    {
        $this->updateAllQuotationData($quotation, $request);
    }

    private function updateAllQuotationData(Quotation $quotation, Request $request): void
    {
        DB::beginTransaction();
        try {
            $user = Auth::user()->full_name;
            $currentDateTime = Carbon::now();

            $this->preloadStep11Maps($quotation);

            if ($request->has('wage_data') && is_array($request->wage_data)) {
                foreach ($request->wage_data as $detailId => $wageFields) {
                    $this->updateSingleWageData($detailId, $wageFields, $user, $currentDateTime, $quotation->id);
                }
            }

            if ($request->has('detail_data') && is_array($request->detail_data)) {
                $this->updateQuotationDetailData($quotation, $request->detail_data, $user, $currentDateTime);
            }

            if ($request->has('tunjangan_data') && is_array($request->tunjangan_data)) {
                $this->tunjanganService->syncTunjanganData($quotation, $request->tunjangan_data, $currentDateTime, $user);
            }

            foreach (['hpp_editable_data' => QuotationDetailHpp::class, 'coss_data' => QuotationDetailCoss::class] as $key => $model) {
                if ($request->has($key) && is_array($request->$key)) {
                    $idsByValue = [];
                    foreach ($request->$key as $detailId => $data) {
                        if (isset($data['jumlah_hc'])) {
                            $idsByValue[(string) $data['jumlah_hc']][] = $detailId;
                        }
                    }

                    foreach ($idsByValue as $value => $ids) {
                        $model::whereIn('quotation_detail_id', $ids)->update(['jumlah_hc' => $value]);
                    }
                }
            }

            $detailIdsForPreReset = $quotation->quotationDetails->pluck('id')->all();
            $preResetHppMap = QuotationDetailHpp::whereIn('quotation_detail_id', $detailIdsForPreReset)
                ->get()
                ->keyBy('quotation_detail_id');
            $preResetCossMap = QuotationDetailCoss::whereIn('quotation_detail_id', $detailIdsForPreReset)
                ->get()
                ->keyBy('quotation_detail_id');

            $this->calculationService->resetAllCalculatedValues($quotation, $user, $currentDateTime);

            if ($request->filled('persen_insentif')) {
                $quotation->persen_insentif = (float) str_replace(['.', ','], ['', '.'], $request->persen_insentif);
            }
            if ($request->filled('persen_bunga_bank')) {
                $quotation->persen_bunga_bank = (float) str_replace(['.', ','], ['', '.'], $request->persen_bunga_bank);
            }

            $calculationResult = $this->getQuotationService()->calculateQuotation($quotation);

            $this->calculationService->saveAllCalculationResults($calculationResult, $user, $currentDateTime, $request, $preResetHppMap, $preResetCossMap);

            if ($request->has('bpjs_ks_data') && is_array($request->bpjs_ks_data)) {
                $this->tunjanganService->updateBpjsKsNominal($quotation, $request->bpjs_ks_data, $user, $currentDateTime);
            }

            $quotationData = $this->helper->prepareQuotationDataForUpdate($quotation, $request, $user, $currentDateTime);
            $this->helper->cleanQuotationAttributes($quotation);

            DB::table('sl_quotation')->where('id', $quotation->id)->update($quotationData);

            DB::commit();
            $quotation->refresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in updateAllQuotationData: '.$e->getMessage());
            throw $e;
        } finally {
            $this->clearStep11Maps();
        }

        // Generates the boilerplate "perjanjian kerjasama" text from quotation fields
        // already committed above (kebutuhan/top/salary_rule_id, not calculation output).
        // Kept outside the pricing transaction (audit #10) — idempotent (guarded by an
        // existing-rows check) and a failure here shouldn't roll back the priced quotation.
        try {
            $this->generateKerjasama($quotation);
        } catch (\Exception $e) {
            Log::error('Error in generateKerjasama: '.$e->getMessage());
        }
    }

    private function generateKerjasama(Quotation $quotation): void
    {
        $currentDateTime = Carbon::now();

        $existing = QuotationKerjasama::where('quotation_id', $quotation->id)
            ->whereNull('deleted_at')
            ->count();

        if ($existing > 0) {
            return;
        }

        QuotationKerjasama::where('quotation_id', $quotation->id)->update([
            'deleted_at' => $currentDateTime,
            'deleted_by' => Auth::user()->full_name,
        ]);

        $arrPerjanjian = $this->generateKerjasamaContent($quotation);

        foreach ($arrPerjanjian as $perjanjian) {
            QuotationKerjasama::create([
                'quotation_id' => $quotation->id,
                'perjanjian' => $perjanjian,
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id(),
            ]);
        }
    }

    public function generateKerjasamaContent(Quotation $quotation)
    {
        $kebutuhanPerjanjian = '<b>'.$quotation->kebutuhan.'</b>';

        $salaryRuleQ = SalaryRule::select('cutoff', 'pengiriman_invoice', 'rilis_payroll')
            ->whereNull('deleted_at')
            ->where('id', $quotation->salary_rule_id)
            ->first();

        $tableSalary = '<table class="table table-bordered" style="width:100%">
                  <thead>
                    <tr>
                      <th class="text-center"><b>No.</b></th>
                      <th class="text-center"><b>Schedule Plan</b></th>
                      <th class="text-center"><b>Periode</b></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td class="text-center">1</td>
                      <td>Cut Off</td>
                      <td>'.$salaryRuleQ->cutoff.'</td>
                    </tr>
                    <tr>
                      <td class="text-center">2</td>
                      <td>Pengiriman <i>Invoice</i></td>
                      <td>'.($quotation->pengiriman_invoice ?: $salaryRuleQ->pengiriman_invoice).'</td>
                    </tr>
                    <tr>
                      <td class="text-center">3</td>
                      <td>Rilis <i>Payroll</i> / Gaji</td>
                      <td>'.$salaryRuleQ->rilis_payroll.'</td>
                    </tr>
                  </tbody>
                </table>';

        $kunjunganOperasional = '';
        if ($quotation->kunjungan_operasional != null) {
            $kunjunganParts = explode(' ', $quotation->kunjungan_operasional);
            if (count($kunjunganParts) >= 2) {
                $kunjunganOperasional = $kunjunganParts[0].' kali dalam 1 '.$kunjunganParts[1];
            }
        }

        $appPendukung = QuotationAplikasi::select('aplikasi_pendukung')
            ->whereNull('deleted_at')
            ->where('quotation_id', $quotation->id)
            ->get();

        $sAppPendukung = '<b>';
        foreach ($appPendukung as $kduk => $dukung) {
            if ($kduk != 0) {
                $sAppPendukung .= ', ';
            }
            $sAppPendukung .= $dukung->aplikasi_pendukung;
        }
        $sAppPendukung .= '</b>';

        $perjanjian = [];

        $perjanjian[] = 'Penawaran harga ini berlaku 30 hari sejak tanggal diterbitkan.';
        $perjanjian[] = 'Akan dilakukan <i>survey</i> area untuk kebutuhan '.$kebutuhanPerjanjian.' sebagai tahapan <i>assesment</i> area untuk memastikan efektifitas pekerjaan.';
        $perjanjian[] = 'Komponen dan nilai dalam penawaran harga ini berdasarkan kesepakatan para pihak dalam pengajuan harga awal, apabila ada perubahan, pengurangan maupun penambahan pada komponen dan nilai pada penawaran, maka <b>para pihak</b> sepakat akan melanjutkan ke tahap negosiasi selanjutnya.';

        $perjanjianContent = 'Skema cut-off, pengiriman <i>invoice</i>, pembayaran <i>invoice</i> dan penggajian dengan skema sebagai berikut: <br>'.$tableSalary;

        $catatanKaki = '<i><br>*Rilis gaji adalah talangan.';

        if ($quotation->top !== 'Non TOP') {
            $topValue = ($quotation->top === 'Lebih Dari 7 Hari')
                ? $quotation->jumlah_hari_invoice
                : $quotation->top;

            $catatanKaki .= '<br>*Maksimal pembayaran invoice '.$topValue.' hari '.$quotation->tipe_hari_invoice.' setelah invoice';
        }

        $catatanKaki .= '</i>';
        $perjanjian[] = $perjanjianContent.$catatanKaki;

        $perjanjian[] = 'Kunjungan tim operasional '.$kunjunganOperasional.', untuk monitoring dan supervisi dengan karyawan dan wajib bertemu dengan pic <b>Pihak Pertama</b> untuk koordinasi.';
        $perjanjian[] = 'Tim operasional bersifat <i>on call</i> apabila terjadi <i>case</i> atau insiden yang terjadi yang mengharuskan untuk datang ke lokasi kerja Pihak Pertama.';
        $perjanjian[] = 'Pemenuhan kandidat dilakukan dengan 2 tahap <i>screening</i> :<br>a. Tahap ke -1 : dilakukan oleh tim rekrutmen <b>Pihak Kedua</b> untuk memastikan bahwa kandidat sudah sesuai dengan kualifikasi <b>dari Pihak Pertama</b>.<br>b. Tahap ke -2 : dilakukan oleh user <b>Pihak Pertama</b>, dan dijadwalkan setelah adanya <i>report</i> hasil <i>screening</i> dari <b>Pihak Kedua</b>.';
        $perjanjian[] = '<i>Support</i> aplikasi digital :'.$sAppPendukung.'.';

        return $perjanjian;
    }

    private function updateSingleWageData($detailId, array $wageFields, string $user, Carbon $currentDateTime, $quotationId): void
    {
        $detail = $this->_detailMap?->get($detailId)
            ?? QuotationDetail::where('id', $detailId)
                ->where('quotation_id', $quotationId)
                ->first();

        if (! $detail) {
            return;
        }

        $wage = $this->_wageMap?->get($detailId)
            ?? QuotationDetailWage::where('quotation_detail_id', $detailId)->first();

        if (! $wage) {
            return;
        }

        $updateData = [];

        $allowedWageFields = [
            'upah',
            'hitungan_upah',
            'lembur',
            'nominal_lembur',
            'jenis_bayar_lembur',
            'jam_per_bulan_lembur',
            'lembur_ditagihkan',
            'kompensasi',
            'thr',
            'tunjangan_holiday',
            'nominal_tunjangan_holiday',
        ];

        foreach ($allowedWageFields as $field) {
            if (array_key_exists($field, $wageFields)) {
                $value = $wageFields[$field];

                if ($value === '' || (is_string($value) && trim($value) === '')) {
                    continue;
                }

                if ($value === null) {
                    $updateData[$field] = null;
                    continue;
                }

                if (in_array($field, ['nominal_lembur', 'nominal_tunjangan_holiday', 'jam_per_bulan_lembur'])) {
                    $updateData[$field] = $this->helper->convertToFloat($value);
                } else {
                    $updateData[$field] = $value;
                }
            }
        }

        if (! empty($updateData)) {
            $updateData['updated_by'] = $user;
            $updateData['updated_at'] = $currentDateTime;

            $wage->update($updateData);
        }
    }

    private function updateQuotationDetailData(Quotation $quotation, array $detailData, string $user, Carbon $currentDateTime): void
    {
        $statistics = [
            'total_updated' => 0,
            'custom_upah_count' => 0,
            'wage_updated_count' => 0,
            'failed_count' => 0,
        ];

        foreach ($detailData as $detailId => $data) {
            try {
                $detail = $this->_detailMap?->get($detailId)
                    ?? QuotationDetail::where('id', $detailId)
                        ->where('quotation_id', $quotation->id)
                        ->first();

                if (! $detail) {
                    $statistics['failed_count']++;
                    continue;
                }

                $this->updateDetailRecord($detail, $data, $user, $currentDateTime, $statistics);

                $this->updateDetailWage($detail, $data, $user, $currentDateTime, $statistics);

                $statistics['total_updated']++;

            } catch (\Exception $e) {
                Log::error('Failed to update detail', [
                    'detail_id' => $detailId,
                    'error' => $e->getMessage(),
                ]);
                $statistics['failed_count']++;
            }
        }
    }

    private function updateDetailRecord(QuotationDetail $detail, array $data, string $user, Carbon $currentDateTime, array &$statistics): void
    {
        $updateData = [
            'updated_by' => $user,
            'updated_at' => $currentDateTime,
        ];

        if (isset($data['nominal_upah'])) {
            $nominalUpah = $this->helper->convertToFloat($data['nominal_upah']);

            $isCustomUpah = $this->isCustomUpah($detail, $nominalUpah);

            $updateData['nominal_upah'] = $nominalUpah;
            $updateData['is_custom_upah'] = $isCustomUpah ? 1 : 0;

            if ($isCustomUpah) {
                $statistics['custom_upah_count']++;
            }
        }

        $allowedFields = ['jumlah_hc', 'jabatan_kebutuhan', 'nama_site', 'penjamin_kesehatan'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        $bpjsPercentFields = [
            'persen_bpjs_jkk',
            'persen_bpjs_jkm',
            'persen_bpjs_jht',
            'persen_bpjs_jp',
            'persen_bpjs_kes',
        ];

        $bpjsPercentChanged = false;
        foreach ($bpjsPercentFields as $field) {
            if (isset($data[$field])) {
                $value = $this->helper->convertToFloat($data[$field]);
                if ((float) ($detail->{$field} ?? 0) !== $value) {
                    $bpjsPercentChanged = true;
                }
                $updateData[$field] = $value;
            }
        }

        $detail->update($updateData);

        if ($bpjsPercentChanged) {
            $bpjsNominalFields = [
                'bpjs_jkk' => null,
                'bpjs_jkm' => null,
                'bpjs_jht' => null,
                'bpjs_jp' => null,
                'bpjs_ks' => null,
                'updated_by' => $user,
                'updated_at' => $currentDateTime,
            ];

            $hpp = $this->_hppMap?->get($detail->id)
                ?? QuotationDetailHpp::where('quotation_detail_id', $detail->id)->first();
            if ($hpp) {
                $hpp->update($bpjsNominalFields);
            }

            $coss = $this->_cossMap?->get($detail->id)
                ?? QuotationDetailCoss::where('quotation_detail_id', $detail->id)->first();
            if ($coss) {
                $coss->update($bpjsNominalFields);
            }
        }
    }

    private function updateDetailWage(QuotationDetail $detail, array $data, string $user, Carbon $currentDateTime, array &$statistics): void
    {
        $wage = $this->_wageMap?->get($detail->id)
            ?? QuotationDetailWage::where('quotation_detail_id', $detail->id)->first();

        if (! $wage) {
            return;
        }

        $wageUpdateData = [
            'updated_by' => $user,
            'updated_at' => $currentDateTime,
        ];

        $hasUpdate = false;
        $requiresHppCossRecalculation = false;

        if (isset($data['nominal_upah'])) {
            $newNominalUpah = $this->helper->convertToFloat($data['nominal_upah']);
            $oldNominalUpah = (float) $detail->nominal_upah;

            if ($newNominalUpah !== $oldNominalUpah) {
                $requiresHppCossRecalculation = true;
            }

            if (isset($detail->is_custom_upah) && $detail->is_custom_upah) {
                $wageUpdateData['upah'] = 'Custom';
                $hasUpdate = true;
            }
        }

        $wageFieldMapping = [
            'tunjangan_hari_raya' => 'thr',
            'kompensasi' => 'kompensasi',
            'lembur' => 'lembur',
            'nominal_lembur' => 'nominal_lembur',
            'tunjangan_holiday' => 'tunjangan_holiday',
            'nominal_tunjangan_holiday' => 'nominal_tunjangan_holiday',
            'lembur_ditagihkan' => 'lembur_ditagihkan',
        ];

        $fieldsTriggeringRecalculation = [
            'lembur',
            'nominal_lembur',
            'tunjangan_holiday',
            'nominal_tunjangan_holiday',
            'tunjangan_hari_raya',
            'kompensasi',
            'lembur_ditagihkan',
        ];

        foreach ($wageFieldMapping as $inputField => $wageField) {
            if (isset($data[$inputField])) {
                $value = $this->helper->convertToFloat($data[$inputField]);

                if (in_array($inputField, ['lembur', 'kompensasi', 'thr', 'tunjangan_holiday', 'lembur_ditagihkan', 'hitungan_upah'])) {
                    $value = $data[$inputField];
                }

                if ($wage->{$wageField} != $value) {
                    $wageUpdateData[$wageField] = $value;
                    $hasUpdate = true;

                    if (in_array($inputField, $fieldsTriggeringRecalculation)) {
                        $requiresHppCossRecalculation = true;
                    }
                }
            }
        }

        if ($hasUpdate) {
            $wage->update($wageUpdateData);
            $statistics['wage_updated_count']++;

            if ($requiresHppCossRecalculation) {
                $fieldsToClear = [
                    'tunjangan_hari_raya',
                    'kompensasi',
                    'tunjangan_hari_libur_nasional',
                    'lembur',
                    'updated_by' => $user,
                    'updated_at' => $currentDateTime,
                ];

                $hpp = $this->_hppMap?->get($detail->id)
                    ?? QuotationDetailHpp::where('quotation_detail_id', $detail->id)->first();
                if ($hpp) {
                    $hpp->update($fieldsToClear);
                }

                $coss = $this->_cossMap?->get($detail->id)
                    ?? QuotationDetailCoss::where('quotation_detail_id', $detail->id)->first();
                if ($coss) {
                    $coss->update($fieldsToClear);
                }
            }
        }
    }

    private function isCustomUpah(QuotationDetail $detail, float $nominalUpah): bool
    {
        $site = $this->_siteMap?->get($detail->quotation_site_id)
            ?? QuotationSite::find($detail->quotation_site_id);

        if (! $site) {
            return false;
        }

        $umk = $this->_umkMap?->get($site->kota_id)
            ?? Umk::byCity($site->kota_id)->active()->first();
        $ump = $this->_umpMap?->get($site->provinsi_id)
            ?? Ump::byProvince($site->provinsi_id)->active()->first();

        $umkValue = $umk ? $umk->umk : 0;
        $umpValue = $ump ? $ump->ump : 0;

        return ($nominalUpah != $umkValue && $nominalUpah != $umpValue);
    }
}
