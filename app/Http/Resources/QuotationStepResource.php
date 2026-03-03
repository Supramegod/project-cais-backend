<?php

namespace App\Http\Resources;

use App\Models\BarangDefaultQty;
use App\Models\Company;
use App\Models\JenisBarang;
use App\Models\Kebutuhan;
use App\Models\Province;
use App\Models\Quotation;
use App\Models\QuotationDetailCoss;
use App\Models\QuotationDetailHpp;
use App\Models\QuotationDetailTunjangan;
use App\Models\QuotationDevices;
use App\Models\QuotationKaporlap;
use App\Models\Umk;
use App\Models\Ump;
use App\Models\SalaryRule;
use App\Models\Top;
use App\Models\Position;
use App\Models\ManagementFee;
use App\Models\JenisPerusahaan;
use App\Models\BidangPerusahaan;
use App\Models\AplikasiPendukung;
use App\Models\Barang;
use App\Models\Training;
use App\Models\JabatanPic;
use App\Services\QuotationBarangService;
use App\Services\QuotationService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QuotationStepResource extends JsonResource
{
    private $step;
    private $barangService;
    private $quotationService;

    public function __construct($resource, $step = null)
    {
        parent::__construct($resource);
        $this->step = $step ?: ($resource['step'] ?? null);
        $this->barangService = new QuotationBarangService();
        $this->quotationService = app(QuotationService::class); // tambah ini
    }
    public function toArray($request)
    {
        $quotation = $this['quotation'] ?? $this->resource;
        $step = $this->step;

        // Ambil metadata jika ada
        $metadata = $this['metadata'] ?? [];
        $actualStep = $metadata['actual_step'] ?? $quotation->step;
        $isFinal = $metadata['is_final'] ?? ($actualStep >= 100);
        $readonly = $metadata['readonly'] ?? $isFinal;

        $baseData = [
            'id' => $this->id ?? $this['quotation']->id,
            'step' => $this->step,
            'metadata' => $actualStep
        ];

        // Hanya tambahkan data dasar jika diperlukan
        if (in_array($this->step, [1])) {
            $baseData['nama_perusahaan'] = $this->nama_perusahaan ?? $this['quotation']->nama_perusahaan;
            $baseData['kebutuhan'] = $this->kebutuhan ?? $this['quotation']->kebutuhan;
        }

        $stepData = $this->getStepSpecificData($quotation, $step);
        $additionalData = $this->getAdditionalData($quotation, $step);

        return array_merge($baseData, [
            'step_data' => $stepData,
            'additional_data' => $additionalData,
        ]);
    }
    private function getStepSpecificData($quotation, $step)
    {

        switch ($step) {
            case 1:
                return [
                    'jenis_kontrak' => $quotation->jenis_kontrak,
                    'layanan_id' => $quotation->kebutuhan_id,
                    'layanan_nama' => $quotation->kebutuhan->nama ?? null,
                ];

            case 2:
                return [
                    'jenis_kontrak' => $quotation->jenis_kontrak,
                    'mulai_kontrak' => $quotation->mulai_kontrak,
                    'kontrak_selesai' => $quotation->kontrak_selesai,
                    'tgl_penempatan' => $quotation->tgl_penempatan ? Carbon::parse($quotation->tgl_penempatan)->isoFormat('Y-MM-DD') : null,
                    'top' => $quotation->top,
                    'salary_rule_id' => $quotation->salary_rule_id,
                    'pengiriman_invoice' => $quotation->pengiriman_invoice,
                    'jumlah_hari_invoice' => $quotation->jumlah_hari_invoice,
                    'tipe_hari_invoice' => $quotation->tipe_hari_invoice,
                    'evaluasi_kontrak' => $quotation->evaluasi_kontrak,
                    'durasi_kerjasama' => $quotation->durasi_kerjasama,
                    'durasi_karyawan' => $quotation->durasi_karyawan,
                    'evaluasi_karyawan' => $quotation->evaluasi_karyawan,
                    'ada_cuti' => $quotation->ada_cuti,
                    'cuti' => $quotation->cuti,
                    'hari_cuti_kematian' => $quotation->hari_cuti_kematian,
                    'hari_istri_melahirkan' => $quotation->hari_istri_melahirkan,
                    'hari_cuti_menikah' => $quotation->hari_cuti_menikah,
                    'gaji_saat_cuti' => $quotation->gaji_saat_cuti,
                    'prorate' => $quotation->prorate,
                    'shift_kerja' => $quotation->shift_kerja,
                    'hari_kerja' => $quotation->hari_kerja,
                    'jam_kerja' => $quotation->jam_kerja,
                ];

            case 3:
                // PERBAIKI: Gunakan approach yang lebih robust
                $quotationDetails = [];

                if ($quotation->relationLoaded('quotationDetails')) {
                    $quotationDetails = $quotation->quotationDetails->map(function ($detail) {
                        $data = [
                            'id' => $detail->id,
                            'nama_site' => $detail->nama_site,
                            'quotation_site_id' => $detail->quotation_site_id,
                            'position_id' => $detail->position_id,
                            'jabatan_kebutuhan' => $detail->jabatan_kebutuhan,
                            'jumlah_hc' => $detail->jumlah_hc,
                            'nominal_upah' => $detail->nominal_upah,
                        ];

                        // PERBAIKI: Cek relasi requirements dengan cara yang lebih reliable
                        $requirements = [];
                        if (method_exists($detail, 'quotationDetailRequirements') && $detail->relationLoaded('quotationDetailRequirements')) {
                            $requirements = $detail->quotationDetailRequirements->pluck('requirement')->toArray();
                        } else {
                            // Fallback: load relasi jika belum dimuat
                            try {
                                $requirements = $detail->quotationDetailRequirements()->pluck('requirement')->toArray();
                            } catch (\Exception $e) {
                                $requirements = [];
                            }
                        }
                        $data['requirements'] = $requirements;

                        // PERBAIKI: Cek relasi tunjangan dengan cara yang lebih reliable
                        $tunjangans = [];
                        if (method_exists($detail, 'quotationDetailTunjangans') && $detail->relationLoaded('quotationDetailTunjangans')) {
                            $tunjangans = $detail->quotationDetailTunjangans->map(function ($tunjangan) {
                                return [
                                    'nama_tunjangan' => $tunjangan->nama_tunjangan,
                                    'nominal' => $tunjangan->nominal,
                                ];
                            })->toArray();
                        } else {
                            // Fallback: load relasi jika belum dimuat
                            try {
                                $tunjangans = $detail->quotationDetailTunjangans()->get()->map(function ($tunjangan) {
                                    return [
                                        'nama_tunjangan' => $tunjangan->nama_tunjangan,
                                        'nominal' => $tunjangan->nominal,
                                    ];
                                })->toArray();
                            } catch (\Exception $e) {
                                $tunjangans = [];
                            }
                        }
                        $data['tunjangans'] = $tunjangans;

                        return $data;
                    })->toArray();
                }

                return [
                    'quotation_details' => $quotationDetails,
                ];

            // Di method getStepSpecificData - case 4:
            case 4:
                $positionData = [];

                if ($quotation->relationLoaded('quotationDetails')) {
                    foreach ($quotation->quotationDetails as $detail) {
                        $wage = $detail->wage;
                        $site = $detail->quotationSite; // Pastikan relasi ini ada di model QuotationDetail

                        // Default keterangan jika UMK tidak ditemukan
                        $keteranganMinUpah = "Data UMK tidak ditemukan";

                        if ($site && $site->kota_id) {
                            // Cari data UMK aktif berdasarkan kota dari site
                            $umkData = Umk::byCity($site->kota_id)->active()->first();

                            if ($umkData) {
                                $minUpahNominal = $umkData->umk * 0.85;
                                $keteranganMinUpah = "Upah kurang dari 85% UMK ( Rp " . number_format($minUpahNominal, 0, ',', '.') . " ) membutuhkan approval ";
                            }
                        }


                        $positionData[] = [
                            'quotation_detail_id' => $detail->id,
                            'position_id' => $detail->position_id,
                            'position_name' => $detail->jabatan_kebutuhan,
                            'site_id' => $detail->quotation_site_id,
                            'site_name' => $detail->nama_site,
                            'jumlah_hc' => $detail->jumlah_hc,
                            'nominal_upah' => $detail->nominal_upah,

                            // Tambahkan field keterangan minimal upah di sini
                            'keterangan_minimal_upah' => $keteranganMinUpah,

                            // Data dari wage
                            'upah' => $wage->upah ?? null,
                            'hitungan_upah' => $wage->hitungan_upah ?? null,
                            'lembur' => $wage->lembur ?? null,
                            'nominal_lembur' => $wage->nominal_lembur ?? 0,
                            'jenis_bayar_lembur' => $wage->jenis_bayar_lembur ?? null,
                            'jam_per_bulan_lembur' => $wage->jam_per_bulan_lembur ?? 0,
                            'lembur_ditagihkan' => $wage->lembur_ditagihkan ?? null,
                            'kompensasi' => $wage->kompensasi ?? null,
                            'thr' => $wage->thr ?? null,
                            'tunjangan_holiday' => $wage->tunjangan_holiday ?? null,
                            'nominal_tunjangan_holiday' => $wage->nominal_tunjangan_holiday ?? 0,
                            'jenis_bayar_tunjangan_holiday' => $wage->jenis_bayar_tunjangan_holiday ?? null,

                            // Field BPJS
                            'is_bpjs_jkk' => $detail->is_bpjs_jkk ?? null,
                            'is_bpjs_jkm' => $detail->is_bpjs_jkm ?? null,
                            'is_bpjs_jht' => $detail->is_bpjs_jht ?? null,
                            'is_bpjs_jp' => $detail->is_bpjs_jp ?? null,
                            'penjamin_kesehatan' => $detail->penjamin_kesehatan ?? null,
                        ];
                    }
                }

                return [
                    'position_data' => $positionData,
                    'global_data' => [
                        'is_ppn' => $quotation->is_ppn,
                        'ppn_pph_dipotong' => $quotation->ppn_pph_dipotong,
                        'management_fee_id' => $quotation->management_fee_id,
                        'persentase' => $quotation->persentase,
                    ]
                ];
            // Di method getStepSpecificData - case 5:
            case 5:
                $bpjsPerPosition = [];
                if ($quotation->relationLoaded('quotationDetails')) {
                    $bpjsPerPosition = $quotation->quotationDetails->map(function ($detail) {
                        return [
                            'detail_id' => $detail->id,
                            'position_id' => $detail->position_id,
                            'position_name' => $detail->jabatan_kebutuhan,
                            'site_id' => $detail->quotation_site_id,
                            'site_name' => $detail->nama_site,
                            'penjamin_kesehatan' => $detail->penjamin_kesehatan,
                            'is_bpjs_jkk' => (bool) $detail->is_bpjs_jkk,
                            'is_bpjs_jkm' => (bool) $detail->is_bpjs_jkm,
                            'is_bpjs_jht' => (bool) $detail->is_bpjs_jht,
                            'is_bpjs_jp' => (bool) $detail->is_bpjs_jp,
                            'nominal_takaful' => $detail->nominal_takaful
                        ];
                    })->toArray();
                }

                return [
                    'jenis_perusahaan_id' => $quotation->jenis_perusahaan_id ?? $quotation->leads->jenis_perusahaan_id,
                    'bidang_perusahaan_id' => $quotation->bidang_perusahaan_id ?? $quotation->leads->bidang_perusahaan_id,
                    'resiko' => $quotation->jenisPerusahaan->resiko ?? $quotation->leads->jenisperusahaan->resiko ?? null,
                    'program_bpjs' => $quotation->program_bpjs,
                    'bpjs_per_position' => $bpjsPerPosition,
                ];
            case 6:
                return [
                    'aplikasi_pendukung' => $quotation->relationLoaded('quotationAplikasis') ?
                        $quotation->quotationAplikasis->pluck('aplikasi_pendukung_id')->toArray() : [],
                ];

            case 7:
                $kaporlapData = $this->barangService->prepareBarangData($quotation, 'kaporlap');
                return [
                    'quotation_kaporlaps' => $kaporlapData['data'],
                    'kaporlap_total' => $kaporlapData['total'],
                ];

            case 8:
                $devicesData = $this->barangService->prepareBarangData($quotation, 'devices');
                return [
                    'quotation_devices' => $devicesData['data'],
                    'devices_total' => $devicesData['total'],
                ];

            case 9:
                $chemicalData = $this->barangService->prepareBarangData($quotation, 'chemicals');
                return [
                    'quotation_chemicals' => $chemicalData['data'],
                    'chemicals_total' => $chemicalData['total'],
                ];
            case 10:
                // Parse kunjungan_operasional yang disimpan sebagai "jumlah periode"
                $kunjunganOperasional = $quotation->kunjungan_operasional ?? '';
                $jumlahKunjunganOperasional = '';
                $bulanTahunKunjunganOperasional = '';

                if (!empty($kunjunganOperasional)) {
                    $parts = explode(' ', $kunjunganOperasional, 2);
                    $jumlahKunjunganOperasional = $parts[0] ?? '';
                    $bulanTahunKunjunganOperasional = $parts[1] ?? '';
                }

                // Parse kunjungan_tim_crm yang disimpan sebagai "jumlah periode"
                $kunjunganTimCrm = $quotation->kunjungan_tim_crm ?? '';
                $jumlahKunjunganTimCrm = '';
                $bulanTahunKunjunganTimCrm = '';

                if (!empty($kunjunganTimCrm)) {
                    $parts = explode(' ', $kunjunganTimCrm, 2);
                    $jumlahKunjunganTimCrm = $parts[0] ?? '';
                    $bulanTahunKunjunganTimCrm = $parts[1] ?? '';
                }

                // Untuk ada_training, cek dari quotationTrainings relationship
                $quotationTrainings = $quotation->relationLoaded('quotationTrainings') ?
                    $quotation->quotationTrainings->pluck('training_id')->toArray() : [];
                $adaTraining = !empty($quotationTrainings) ? 'Ada' : 'Tidak Ada';

                // Dapatkan data OHC dari service
                $ohcData = $this->barangService->prepareBarangData($quotation, 'ohc');

                return [
                    // Field kunjungan operasional
                    'jumlah_kunjungan_operasional' => $jumlahKunjunganOperasional,
                    'bulan_tahun_kunjungan_operasional' => $bulanTahunKunjunganOperasional,
                    'keterangan_kunjungan_operasional' => $quotation->keterangan_kunjungan_operasional,

                    // Field kunjungan tim CRM
                    'jumlah_kunjungan_tim_crm' => $jumlahKunjunganTimCrm,
                    'bulan_tahun_kunjungan_tim_crm' => $bulanTahunKunjunganTimCrm,
                    'keterangan_kunjungan_tim_crm' => $quotation->keterangan_kunjungan_tim_crm,

                    // Field training
                    'ada_training' => $adaTraining,
                    'training' => $quotation->training,

                    // Field bunga bank
                    'persen_bunga_bank' => $quotation->persen_bunga_bank,

                    // Data OHC dari service
                    'quotation_ohcs' => $ohcData['data'],
                    'ohc_total' => $ohcData['total'],

                    // Data training (untuk checkbox)
                    'quotation_trainings' => $quotationTrainings,
                ];
            case 11:
                $calculatedQuotation = $this['additional_data']['calculated_quotation'] ?? null;

                $persenBpjsTotalHpp = 0;
                $persenBpjsTotalCoss = 0;
                $persenBpjsBreakdownHpp = [];
                $persenBpjsBreakdownCoss = [];

                if ($calculatedQuotation && isset($calculatedQuotation->calculation_summary)) {
                    $summary = $calculatedQuotation->calculation_summary;

                    $persenBpjsTotalHpp = $summary->persen_bpjs_ketenagakerjaan ?? 0;
                    $persenBpjsBreakdownHpp = [
                        'persen_bpjs_jkk' => $summary->persen_bpjs_jkk ?? 0,
                        'persen_bpjs_jkm' => $summary->persen_bpjs_jkm ?? 0,
                        'persen_bpjs_jht' => $summary->persen_bpjs_jht ?? 0,
                        'persen_bpjs_jp' => $summary->persen_bpjs_jp ?? 0,
                    ];

                    $persenBpjsTotalCoss = $summary->persen_bpjs_ketenagakerjaan_coss ?? 0;
                    $persenBpjsBreakdownCoss = [
                        'persen_bpjs_jkk' => $summary->persen_bpjs_jkk_coss ?? 0,
                        'persen_bpjs_jkm' => $summary->persen_bpjs_jkm_coss ?? 0,
                        'persen_bpjs_jht' => $summary->persen_bpjs_jht_coss ?? 0,
                        'persen_bpjs_jp' => $summary->persen_bpjs_jp_coss ?? 0,
                    ];
                }

                if ($calculatedQuotation && $calculatedQuotation->quotation) {
                    $calculatedQuotation->quotation->quotationDetails->loadMissing([
                        'quotationDetailHpps',
                        'quotationDetailCosses',
                        'wage',
                        'quotationDetailTunjangans' => fn($q) => $q->whereNull('deleted_at'),
                    ]);
                }

                $getTunjanganDisplayForBoth = function ($wage, string $jenisField, $hppValue, $cossValue, ?string $fieldDitagihkanTerpisah = null) {
                    if (!$wage) {
                        return ['hpp' => 'Tidak Ada', 'coss' => 'Tidak Ada'];
                    }

                    $jenisValueString = is_string($wage->$jenisField ?? null) ? strtolower(trim($wage->$jenisField)) : '';
                    $ditagihkanValueString = '';

                    if ($fieldDitagihkanTerpisah && isset($wage->$fieldDitagihkanTerpisah)) {
                        $ditagihkanRaw = $wage->$fieldDitagihkanTerpisah;
                        $ditagihkanValueString = is_string($ditagihkanRaw) ? strtolower(trim($ditagihkanRaw)) : '';

                        if ($ditagihkanValueString === 'ditagihkan terpisah') {
                            return ['hpp' => 'Ditagihkan terpisah', 'coss' => 'Ditagihkan terpisah'];
                        }

                        if (in_array($ditagihkanValueString, ['diberikan langsung', 'diberikan langsung oleh client'])) {
                            return ['hpp' => 'Diberikan Langsung Oleh Client', 'coss' => 'Diberikan Langsung Oleh Client'];
                        }
                    }

                    if (in_array($jenisValueString, ['normatif', 'ditagihkan'])) {
                        return ['hpp' => 'Ditagihkan terpisah', 'coss' => 'Ditagihkan terpisah'];
                    }

                    if (in_array($jenisValueString, ['flat', 'diprovisikan']) || ($jenisValueString === 'flat' && $ditagihkanValueString === 'ditagihkan')) {
                        return [
                            'hpp' => $hppValue > 0 ? $hppValue : 'Tidak Ada',
                            'coss' => $cossValue > 0 ? $cossValue : 'Tidak Ada',
                        ];
                    }

                    if (in_array($jenisValueString, ['diberikan langsung', 'diberikan langsung oleh client'])) {
                        return ['hpp' => 'Diberikan Langsung Oleh Client', 'coss' => 'Diberikan Langsung Oleh Client'];
                    }

                    return ['hpp' => 'Tidak Ada', 'coss' => 'Tidak Ada'];
                };

                return [
                    'penagihan' => $quotation->penagihan,
                    'nama_perusahaan' => $quotation->nama_perusahaan,
                    'persentase' => $quotation->persentase,
                    'management_fee_nama' => $quotation->managementFee->nama ?? null,
                    'ppn_pph_dipotong' => $quotation->ppn_pph_dipotong,
                    'note_harga_jual' => $quotation->note_harga_jual,
                    'persen_bunga_bank' => $quotation->persen_bunga_bank ?? 0,
                    'persen_insentif' => $quotation->persen_insentif ?? 0,
                    'quotation_pics' => $quotation->relationLoaded('quotationPics')
                        ? $quotation->quotationPics->map(fn($pic) => [
                            'id' => $pic->id,
                            'nama' => $pic->nama,
                            'jabatan_id' => $pic->jabatan_id,
                            'no_telp' => $pic->no_telp,
                            'email' => $pic->email,
                            'is_kuasa' => $pic->is_kuasa,
                        ])->values()->toArray()
                        : [],

                    'calculation' => $calculatedQuotation ? [
                        'bpu' => [
                            'total_potongan_bpu' => $calculatedQuotation->calculation_summary->total_potongan_bpu ?? 0,
                            'potongan_bpu_per_orang' => $calculatedQuotation->calculation_summary->potongan_bpu_per_orang ?? 0,
                        ],
                        'hpp' => [
                            'total_sebelum_management_fee' => $calculatedQuotation->calculation_summary->total_sebelum_management_fee ?? 0,
                            'nominal_management_fee' => $calculatedQuotation->calculation_summary->nominal_management_fee ?? 0,
                            'grand_total_sebelum_pajak' => $calculatedQuotation->calculation_summary->grand_total_sebelum_pajak ?? 0,
                            'ppn' => $calculatedQuotation->calculation_summary->ppn ?? 0,
                            'pph' => $calculatedQuotation->calculation_summary->pph ?? 0,
                            'dpp' => $calculatedQuotation->calculation_summary->dpp ?? 0,
                            'total_invoice' => $calculatedQuotation->calculation_summary->total_invoice ?? 0,
                            'pembulatan' => $calculatedQuotation->calculation_summary->pembulatan ?? 0,
                            'margin' => $calculatedQuotation->calculation_summary->margin ?? 0,
                            'gpm' => $calculatedQuotation->calculation_summary->gpm ?? 0,
                            'persen_bunga_bank' => $quotation->persen_bunga_bank ?? 0,
                            'persen_insentif' => $quotation->persen_insentif ?? 0,
                            'persen_bpjs_ksht' => $calculatedQuotation->calculation_summary->persen_bpjs_kesehatan ?? 0,
                            'persen_bpjs_ketenagakerjaan' => $persenBpjsTotalHpp,
                            'breakdown_bpjs' => $persenBpjsBreakdownHpp,
                        ],
                        'coss' => [
                            'total_sebelum_management_fee' => $calculatedQuotation->calculation_summary->total_sebelum_management_fee_coss ?? 0,
                            'nominal_management_fee' => $calculatedQuotation->calculation_summary->nominal_management_fee_coss ?? 0,
                            'grand_total_sebelum_pajak' => $calculatedQuotation->calculation_summary->grand_total_sebelum_pajak_coss ?? 0,
                            'ppn' => $calculatedQuotation->calculation_summary->ppn_coss ?? 0,
                            'pph' => $calculatedQuotation->calculation_summary->pph_coss ?? 0,
                            'dpp' => $calculatedQuotation->calculation_summary->dpp_coss ?? 0,
                            'total_invoice' => $calculatedQuotation->calculation_summary->total_invoice_coss ?? 0,
                            'pembulatan' => $calculatedQuotation->calculation_summary->pembulatan_coss ?? 0,
                            'margin' => $calculatedQuotation->calculation_summary->margin_coss ?? 0,
                            'gpm' => $calculatedQuotation->calculation_summary->gpm_coss ?? 0,
                            'persen_bunga_bank' => $quotation->persen_bunga_bank ?? 0,
                            'persen_insentif' => $quotation->persen_insentif ?? 0,
                            'persen_bpjs_ksht' => $calculatedQuotation->calculation_summary->persen_bpjs_kesehatan_coss ?? 0,
                            'persen_bpjs_ketenagakerjaan' => $persenBpjsTotalCoss,
                            'breakdown_bpjs' => $persenBpjsBreakdownCoss,
                        ],
                        'quotation_details' => $calculatedQuotation->quotation->quotationDetails->map(
                            function ($detail) use ($calculatedQuotation, $getTunjanganDisplayForBoth) {
                                $wage = $detail->wage ?? null;

                                $detailCalc = $calculatedQuotation->detail_calculations[$detail->id] ?? null;
                                if ($detailCalc) {
                                    $hppData = $detailCalc->hpp_data ?? [];
                                    $cossData = $detailCalc->coss_data ?? [];
                                } else {
                                    // fallback ke database
                                    $hpp = $detail->quotationDetailHpps->first();
                                    $coss = $detail->quotationDetailCosses->first();
                                    $hppData = $hpp ? $hpp->toArray() : [];
                                    $cossData = $coss ? $coss->toArray() : [];
                                }

                                // ✅ Ambil nilai bunga_bank dan insentif dari properti detail (hasil kalkulasi

                                $tunjanganData = $detail->quotationDetailTunjangans
                                    ->map(fn($t) => [
                                        'nama_tunjangan' => $t->nama_tunjangan,
                                        'nominal' => $t->nominal,
                                        'nominal_coss' => $t->nominal_coss,
                                    ])->values()->toArray();

                                $thrHpp = $hppData['tunjangan_hari_raya'] ?? 0;
                                $thrCoss = $cossData['tunjangan_hari_raya'] ?? 0;
                                $kompHpp = $hppData['kompensasi'] ?? 0;
                                $kompCoss = $cossData['kompensasi'] ?? 0;
                                $lemburHpp = $hppData['lembur'] ?? 0;
                                $lemburCoss = $cossData['lembur'] ?? 0;
                                $holidayHpp = $hppData['tunjangan_hari_libur_nasional'] ?? 0;
                                $holidayCoss = $cossData['tunjangan_hari_libur_nasional'] ?? 0;

                                $thrDisplay = $getTunjanganDisplayForBoth($wage, 'thr', $thrHpp, $thrCoss);
                                $kompDisplay = $getTunjanganDisplayForBoth($wage, 'kompensasi', $kompHpp, $kompCoss);
                                $lemburDisplay = $getTunjanganDisplayForBoth($wage, 'lembur', $lemburHpp, $lemburCoss, 'lembur_ditagihkan');
                                $holidayDisplay = $getTunjanganDisplayForBoth($wage, 'tunjangan_holiday', $holidayHpp, $holidayCoss);
                                \Log::info("in get method detail {$detail->id}  insntif coss: " . ($cossData['insentif'] ?? 0) . " insentif hpp: " . ($hppData['insentif'] ?? 0));
                                \log::info("in get method detail {$detail->id}  bunga bank coss: " . ($cossData['bunga_bank'] ?? 0) . " bunga bank hpp: " . ($hppData['bunga_bank'] ?? 0));
                                return [
                                    'id' => $detail->id,
                                    'position_name' => $detail->jabatan_kebutuhan,
                                    'nama_site' => $detail->nama_site,
                                    'quotation_site_id' => $detail->quotation_site_id,
                                    'penjamin_kesehatan' => $detail->penjamin_kesehatan,
                                    'upah' => $wage?->upah ?? 0,
                                    'jumlah_hc_hpp' => $hppData['jumlah_hc'] ?? 0,
                                    'jumlah_hc_coss' => $cossData['jumlah_hc'] ?? 0,
                                    'tunjangan_data' => $tunjanganData,

                                    'hpp' => [
                                        'nominal_upah' => $hppData['gaji_pokok'] ?? 0,
                                        'total_tunjangan' => $hppData['total_tunjangan'] ?? 0,
                                        'tunjangan_hari_raya' => $thrDisplay['hpp'],
                                        'kompensasi' => $kompDisplay['hpp'],
                                        'lembur' => $lemburDisplay['hpp'],
                                        'tunjangan_holiday' => $holidayDisplay['hpp'],
                                        'bpjs_ketenagakerjaan' => ($hppData['bpjs_jkk'] ?? 0) + ($hppData['bpjs_jkm'] ?? 0) + ($hppData['bpjs_jht'] ?? 0) + ($hppData['bpjs_jp'] ?? 0),
                                        'bpjs_kesehatan' => $hppData['bpjs_ks'] ?? 0,
                                        'bpjs_jkk' => $hppData['bpjs_jkk'] ?? 0,
                                        'bpjs_jkm' => $hppData['bpjs_jkm'] ?? 0,
                                        'bpjs_jht' => $hppData['bpjs_jht'] ?? 0,
                                        'bpjs_jp' => $hppData['bpjs_jp'] ?? 0,
                                        'bpjs_kes' => $hppData['bpjs_ks'] ?? 0,
                                        'persen_bpjs_jkk' => $hppData['persen_bpjs_jkk'] ?? 0,
                                        'persen_bpjs_jkm' => $hppData['persen_bpjs_jkm'] ?? 0,
                                        'persen_bpjs_jht' => $hppData['persen_bpjs_jht'] ?? 0,
                                        'persen_bpjs_jp' => $hppData['persen_bpjs_jp'] ?? 0,
                                        'persen_bpjs_kes' => $hppData['persen_bpjs_ks'] ?? 0,
                                        'potongan_bpu' => $hppData['potongan_bpu'] ?? 0,
                                        'personil_kaporlap' => $hppData['provisi_seragam'] ?? 0,
                                        'personil_devices' => $hppData['provisi_peralatan'] ?? 0,
                                        'personil_ohc' => $hppData['provisi_ohc'] ?? 0,
                                        'personil_chemical' => $hppData['provisi_chemical'] ?? 0,
                                        'total_personil' => $hppData['total_biaya_per_personil'] ?? 0,
                                        'sub_total_personil' => $hppData['total_biaya_all_personil'] ?? 0,
                                        'bunga_bank' => $hppData['bunga_bank'] ?? 0,
                                        'insentif' => $hppData['insentif'] ?? 0,
                                    ],

                                    'coss' => [
                                        'nominal_upah' => $cossData['gaji_pokok'] ?? 0,
                                        'total_tunjangan' => $cossData['total_tunjangan'] ?? 0,
                                        'tunjangan_hari_raya' => $thrDisplay['coss'],
                                        'kompensasi' => $kompDisplay['coss'],
                                        'lembur' => $lemburDisplay['coss'],
                                        'tunjangan_holiday' => $holidayDisplay['coss'],
                                        'bpjs_ketenagakerjaan' => ($cossData['bpjs_jkk'] ?? 0) + ($cossData['bpjs_jkm'] ?? 0) + ($cossData['bpjs_jht'] ?? 0) + ($cossData['bpjs_jp'] ?? 0),
                                        'bpjs_kesehatan' => $cossData['bpjs_ks'] ?? 0,
                                        'bpjs_jkk' => $cossData['bpjs_jkk'] ?? 0,
                                        'bpjs_jkm' => $cossData['bpjs_jkm'] ?? 0,
                                        'bpjs_jht' => $cossData['bpjs_jht'] ?? 0,
                                        'bpjs_jp' => $cossData['bpjs_jp'] ?? 0,
                                        'bpjs_kes' => $cossData['bpjs_ks'] ?? 0,
                                        'persen_bpjs_jkk' => $cossData['persen_bpjs_jkk'] ?? 0,
                                        'persen_bpjs_jkm' => $cossData['persen_bpjs_jkm'] ?? 0,
                                        'persen_bpjs_jht' => $cossData['persen_bpjs_jht'] ?? 0,
                                        'persen_bpjs_jp' => $cossData['persen_bpjs_jp'] ?? 0,
                                        'persen_bpjs_kes' => $cossData['persen_bpjs_ks'] ?? 0,
                                        'potongan_bpu' => $cossData['potongan_bpu'] ?? 0,
                                        'personil_kaporlap' => $cossData['provisi_seragam'] ?? 0,
                                        'personil_devices' => $cossData['provisi_peralatan'] ?? 0,
                                        'personil_ohc' => $cossData['provisi_ohc'] ?? 0,
                                        'personil_chemical' => $cossData['provisi_chemical'] ?? 0,
                                        'total_base_manpower' => $cossData['total_base_manpower'] ?? 0,
                                        'total_exclude_base_manpower' => $cossData['total_exclude_base_manpower'] ?? 0,
                                        'bunga_bank' => $cossData['bunga_bank'] ?? 0,
                                        'insentif' => $cossData['insentif'] ?? 0,
                                    ],
                                ];
                            }
                        )->toArray(),
                    ] : null,
                ];
            case 12:
                $calculatedQuotation = $this['additional_data']['calculated_quotation'] ?? null;

                // ✅ STRUKTUR BARU: Kerjasama dengan ID tracking
                $kerjasamas = $quotation->relationLoaded('quotationKerjasamas')
                    ? $quotation->quotationKerjasamas
                        ->whereNull('deleted_at')
                        ->sortBy('id')
                        ->values()
                        ->map(function ($kerjasama, $index) {
                            return [
                                'id' => $kerjasama->id,
                                'order' => $index + 1,
                                'perjanjian' => $kerjasama->perjanjian,
                                'is_delete' => $kerjasama->is_delete ?? 1,
                                'is_editable' => $kerjasama->is_delete == 1,
                            ];
                        })->toArray()
                    : [];

                $finalData = [
                    'quotation_kerjasamas' => $kerjasamas,
                    'total_kerjasamas' => count($kerjasamas),
                    'can_edit' => $quotation->step < 100,
                    'final_confirmation' => true,
                ];

                // Add calculation data if available - DIPERBAIKI: akses melalui calculation_summary
                if ($calculatedQuotation) {
                    $finalData['final_calculation'] = [
                        'total_invoice' => $calculatedQuotation->calculation_summary->total_invoice ?? 0,
                        'total_invoice_coss' => $calculatedQuotation->calculation_summary->total_invoice_coss ?? 0,
                        'pembulatan' => $calculatedQuotation->calculation_summary->pembulatan ?? 0,
                        'pembulatan_coss' => $calculatedQuotation->calculation_summary->pembulatan_coss ?? 0,
                        'grand_total_sebelum_pajak' => $calculatedQuotation->calculation_summary->grand_total_sebelum_pajak ?? 0,
                        'grand_total_sebelum_pajak_coss' => $calculatedQuotation->calculation_summary->grand_total_sebelum_pajak_coss ?? 0,
                        'margin' => $calculatedQuotation->calculation_summary->margin ?? 0,
                        'margin_coss' => $calculatedQuotation->calculation_summary->margin_coss ?? 0,
                        'gpm' => $calculatedQuotation->calculation_summary->gpm ?? 0,
                        'gpm_coss' => $calculatedQuotation->calculation_summary->gpm_coss ?? 0,
                    ];
                }

                return $finalData;

            default:
                return [];
        }
    }

    private function getAdditionalData($quotation, $step)
    {
        // GUNAKAN: additional_data yang sudah dipersiapkan di service
        if (isset($this['additional_data'])) {
            return $this['additional_data'];
        }

        switch ($step) {
            case 1:
                return [
                ];

            case 2:
                $roleId = Auth::user()->cais_role_id;
                $salaryRules = in_array($roleId, [29, 30, 31, 32, 33])
                    ? SalaryRule::whereIn('id', [1, 2])->get()
                    : SalaryRule::all();

                return [
                    'salary_rules' => $salaryRules,
                    'top_list' => Top::orderBy('nama', 'asc')->get(),
                    'pengiriman_invoice' => Quotation::distinct()->pluck('pengiriman_invoice'),
                ];

            case 3:
                return [
                    'positions' => Position::where('is_active', 1)
                        ->where('layanan_id', $quotation->kebutuhan_id)
                        ->orderBy('name', 'asc')
                        ->get(),
                    'quotation_sites' => $quotation->relationLoaded('quotationSites') ?
                        $quotation->quotationSites->map(function ($site) {
                            return [
                                'id' => $site->id,
                                'nama_site' => $site->nama_site,
                                'provinsi' => $site->provinsi,
                                'kota' => $site->kota,
                                'penempatan' => $site->penempatan,
                            ];
                        })->toArray() : [],
                ];
            // Di method getAdditionalData - case 4:
            case 4:
                $quotation = $this->resource instanceof Quotation ? $this->resource : $this['quotation'];

                // Data UMK per site menggunakan scope
                $umkPerSite = [];
                $umpPerSite = [];

                if ($quotation->relationLoaded('quotationSites')) {
                    foreach ($quotation->quotationSites as $site) {
                        // Gunakan scope dari model Umk
                        $umk = Umk::byCity($site->kota_id)
                            ->active()
                            ->first();

                        $umkPerSite[$site->id] = [
                            'site_id' => $site->id,
                            'site_name' => $site->nama_site,
                            'city_id' => $site->kota_id,
                            'city_name' => $site->kota,
                            'umk_value' => $umk ? $umk->umk : 0,
                            'formatted_umk' => $umk ? $umk->formatUmk() : "UMK : Rp. 0"
                        ];

                        // Gunakan scope dari model Ump
                        $ump = Ump::byProvince($site->provinsi_id)
                            ->active()
                            ->first();

                        $umpPerSite[$site->id] = [
                            'site_id' => $site->id,
                            'site_name' => $site->nama_site,
                            'province_id' => $site->provinsi_id,
                            'province_name' => $site->provinsi,
                            'ump_value' => $ump ? $ump->ump : 0,
                            'formatted_ump' => $ump ? $ump->formatUmp() : "UMP : Rp. 0"
                        ];
                    }
                }

                return [
                    'management_fees' => ManagementFee::all(),
                    'upah_options' => ['UMP', 'UMK', 'Custom'],
                    'hitungan_upah_options' => ['Per Bulan', 'Per Hari', 'Per Jam'],
                    'jenis_bayar_options' => ['Per Bulan', 'Per Hari', 'Per Jam'],

                    // ✅ SESUAI DENGAN SERVICE
                    'lembur_options' => ['Tidak', 'Flat'],
                    'kompensasi_options' => ['Tidak', 'Diprovisikan'],
                    'thr_options' => ['Tidak', 'Diprovisikan'],
                    'tunjangan_holiday_options' => ['Tidak', 'Flat'],

                    // ✅ OPTIONS TAMBAHAN YANG DIGUNAKAN DI SERVICE
                    'lembur_ditagihkan_options' => ['Tidak Ditagihkan', 'Ditagihkan Terpisah'],


                    'is_ppn_options' => ['Ya', 'Tidak'],
                    'ppn_pph_dipotong_options' => ['Management Fee', 'Lainnya'],

                    'umk_per_site' => $umkPerSite,
                    'ump_per_site' => $umpPerSite,
                    'quotation_details' => $quotation->relationLoaded('quotationDetails') ?
                        $quotation->quotationDetails->map(function ($detail) {
                            return [
                                'id' => $detail->id,
                                'position_id' => $detail->position_id,
                                'position_name' => $detail->jabatan_kebutuhan,
                                'site_id' => $detail->quotation_site_id,
                                'site_name' => $detail->nama_site,
                                'jumlah_hc' => $detail->jumlah_hc,
                                'nominal_upah' => $detail->nominal_upah,
                            ];
                        })->toArray() : [],
                ];
            case 5:
                return [
                    'jenis_perusahaan' => JenisPerusahaan::all(),
                    'bidang_perusahaan' => BidangPerusahaan::all(),
                    'resiko_options' => ['Rendah', 'Sedang', 'Tinggi'],
                    'program_bpjs_options' => ['Asuransi swasta', 'BPJS', 'BPU'], // DIUBAH
                ];

            case 6:
                return [
                    'aplikasi_pendukung' => AplikasiPendukung::all(),
                ];

            case 7:
                return $this->getKaporlapData();

            case 8:
                return $this->getDevicesData();

            case 9:
                $chemicalList = Barang::whereIn('jenis_barang_id', [13, 14, 15, 16, 18, 19])
                    ->ordered()
                    ->get()
                    ->map(function ($chemical) {
                        $chemical->harga_formatted = number_format($chemical->harga, 0, ",", ".");

                        // Tambahkan field default untuk form
                        $chemical->jumlah = 0;
                        $chemical->masa_pakai = $chemical->masa_pakai ?? 12; // Default 12 bulan jika tidak ada
                        $chemical->jumlah_pertahun = 0;
                        $chemical->total_formatted = "Rp 0";

                        return $chemical;
                    });

                return [
                    'chemicals' => $chemicalList,
                ];

            case 10:
                return [
                    'ohc_items' => Barang::whereIn('jenis_barang_id', [6, 7, 8])
                        ->orderBy("urutan", "asc")
                        ->orderBy("nama", "asc")
                        ->get()
                        ->map(function ($ohc) {
                            $ohc->harga_formatted = number_format($ohc->harga, 0, ",", ".");
                            return $ohc;
                        }),
                    'trainings' => Training::all(),
                    'bulan_tahun_options' => ['Bulan', 'Tahun'],
                    'ada_training_options' => ['Ada', 'Tidak Ada'],
                ];

            case 11:
                // Gunakan additional_data yang sudah dihitung oleh service
                if (isset($this['additional_data'])) {
                    $additionalData = $this['additional_data'];

                    // Format data training dan jabatan pic untuk frontend
                    return array_merge($additionalData, [
                        'training_list' => $additionalData['training_list'] ?? [],
                        'jabatan_pic_list' => $additionalData['jabatan_pic_list'] ?? [],
                        'daftar_tunjangan' => $additionalData['daftar_tunjangan'] ?? [],
                    ]);
                }

                // Fallback jika additional_data tidak ada
                return $this->getCalculationData();

            case 12:
                return [
                    'kerjasama_list' => $quotation->relationLoaded('quotationKerjasamas')
                        ? $quotation->quotationKerjasamas
                            ->whereNull('deleted_at')
                            ->sortBy('id')
                            ->values()
                            ->map(function ($kerjasama) {
                                return [
                                    'id' => $kerjasama->id,
                                    'perjanjian' => $kerjasama->perjanjian,
                                    'is_delete' => $kerjasama->is_delete ?? 1,
                                ];
                            })->toArray()
                        : [],
                    'jabatan_pic_list' => JabatanPic::whereNull('deleted_at')->get(),
                    'quotation_pics' => $quotation->relationLoaded('quotationPics')
                        ? $quotation->quotationPics->map(function ($pic) {
                            return [
                                'id' => $pic->id,
                                'nama_pic' => $pic->nama_pic,
                                'jabatan_pic_id' => $pic->jabatan_pic_id,
                                'jabatan_pic' => $pic->jabatan_pic,
                                'no_telp_pic' => $pic->no_telp_pic,
                                'email_pic' => $pic->email_pic,
                            ];
                        })->toArray()
                        : [],
                ];

            default:
                return [];
        }
    }

    private function getKaporlapData()
    {
        $arrKaporlap = [1, 2, 3, 4, 5];
        if ($this->resource->kebutuhan_id != 1) {
            $arrKaporlap = [5];
        }
        $listJenis = JenisBarang::whereIn('id', $arrKaporlap)->get();


        $listKaporlap = Barang::whereIn('jenis_barang_id', $arrKaporlap)
            ->ordered()
            ->get();

        $quotation = $this->resource instanceof Quotation ? $this->resource : $this['quotation'];
        foreach ($listKaporlap as $kaporlap) {
            foreach ($quotation->quotationDetails as $detail) {
                $fieldName = 'jumlah_' . $detail->id;
                $kaporlap->$fieldName = 0;

                if ($quotation->revisi == 0) {
                    $qtyDefault = BarangDefaultQty::where('layanan_id', $quotation->kebutuhan_id)
                        ->where('barang_id', $kaporlap->id)
                        ->first();

                    if ($qtyDefault) {
                        $kaporlap->$fieldName = $qtyDefault->qty_default;
                    }
                } else {
                    $existing = QuotationKaporlap::where('barang_id', $kaporlap->id)
                        ->where('quotation_detail_id', $detail->id)
                        ->first();

                    if ($existing) {
                        $kaporlap->$fieldName = $existing->jumlah;
                    }
                }
            }
        }

        return [
            'jenis_barang' => $listJenis,
            'kaporlap_items' => $listKaporlap,
            'quotation_details' => $quotation->relationLoaded('quotationDetails') ?
                $quotation->quotationDetails->toArray() : [],
        ];
    }

    private function getDevicesData()
    {
        // Menggunakan model JenisBarang
        $listJenis = JenisBarang::whereIn('id', [9, 10, 11, 12, 17])->get();

        // Menggunakan model Barang dengan scope ordered
        $listDevices = Barang::whereIn('jenis_barang_id', [8, 9, 10, 11, 12, 17])
            ->ordered()
            ->get();

        $quotation = $this->resource instanceof Quotation ? $this->resource : $this['quotation'];

        foreach ($listDevices as $device) {
            $device->jumlah = 0;

            if ($quotation->revisi == 0) {
                // Menggunakan model BarangDefaultQty
                $qtyDefault = BarangDefaultQty::where('layanan_id', $quotation->kebutuhan_id)
                    ->where('barang_id', $device->id)
                    ->first();

                if ($qtyDefault) {
                    $device->jumlah = $qtyDefault->qty_default;
                }
            } else {
                // Menggunakan model QuotationDevices
                $existing = QuotationDevices::where('barang_id', $device->id)
                    ->where('quotation_id', $quotation->id)
                    ->first();

                if ($existing) {
                    $device->jumlah = $existing->jumlah;
                }
            }
        }

        return [
            'jenis_barang' => $listJenis,
            'devices_items' => $listDevices,
        ];
    }

    private function getCalculationData()
    {
        $calculation = $this->quotationService->calculateQuotation($this->resource);

        return [
            'calculation' => $calculation,
            'daftar_tunjangan' => QuotationDetailTunjangan::where('quotation_id', $this->resource->id)
                ->distinct()
                ->pluck('nama_tunjangan'),
            'training_list' => Training::all(),
            'jabatan_pic_list' => JabatanPic::all(),
        ];
    }
    private function getUmpData()
    {
        $quotation = $this->resource instanceof Quotation ? $this->resource : $this['quotation'];
        $umpData = [];

        if ($quotation->relationLoaded('quotationSites')) {
            foreach ($quotation->quotationSites as $site) {
                $ump = Ump::where('province_id', $site->provinsi_id)
                    ->where('is_aktif', 1)
                    ->first();

                $umpData[$site->id] = [
                    'site_id' => $site->id,
                    'site_name' => $site->nama_site,
                    'province_id' => $site->provinsi_id,
                    'province_name' => $site->provinsi,
                    'ump_value' => $ump ? $ump->ump : 0,
                    'formatted_ump' => $ump ? $ump->formatUmp() : "Rp. 0",
                ];
            }
        }

        return $umpData;
    }
}