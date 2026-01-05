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
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QuotationStepResource extends JsonResource
{
    private $step;
    private $barangService; // ADD THIS

    public function __construct($resource, $step = null)
    {
        parent::__construct($resource);
        $this->step = $step ?: ($resource['step'] ?? null);
        $this->barangService = new QuotationBarangService(); // ADD THIS
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
                    'jenis_perusahaan_id' => $quotation->leads->jenis_perusahaan_id,
                    'bidang_perusahaan_id' => $quotation->leads->bidang_perusahaan_id,
                    'resiko' => $quotation->leads->jenisperusahaan->resiko,
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

                // Untuk ada_training, kita bisa infer dari field training
                $adaTraining = !empty($quotation->training) ? 'Ada' : 'Tidak Ada';

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
                    'quotation_trainings' => $quotation->relationLoaded('quotationTrainings') ?
                        $quotation->quotationTrainings->pluck('training_id')->toArray() : [],
                ];
            // Di method getStepSpecificData - case 11:
// Di method getStepSpecificData - case 11:
            case 11:
                $calculatedQuotation = $this['additional_data']['calculated_quotation'] ?? null;
                $persenBpjsTotalHpp = 0;
                $persenBpjsTotalCoss = 0;
                $persenBpjsBreakdownHpp = [];
                $persenBpjsBreakdownCoss = [];

                if ($calculatedQuotation && isset($calculatedQuotation->calculation_summary)) {
                    $summary = $calculatedQuotation->calculation_summary;

                    // Untuk HPP
                    $persenBpjsTotalHpp = $summary->persen_bpjs_ketenagakerjaan ?? 0;
                    $persenBpjsBreakdownHpp = [
                        'persen_bpjs_jkk' => $summary->persen_bpjs_jkk ?? 0,
                        'persen_bpjs_jkm' => $summary->persen_bpjs_jkm ?? 0,
                        'persen_bpjs_jht' => $summary->persen_bpjs_jht ?? 0,
                        'persen_bpjs_jp' => $summary->persen_bpjs_jp ?? 0,
                    ];

                    // Untuk COSS
                    $persenBpjsTotalCoss = $summary->persen_bpjs_ketenagakerjaan_coss ?? 0;
                    $persenBpjsBreakdownCoss = [
                        'persen_bpjs_jkk' => $summary->persen_bpjs_jkk_coss ?? 0,
                        'persen_bpjs_jkm' => $summary->persen_bpjs_jkm_coss ?? 0,
                        'persen_bpjs_jht' => $summary->persen_bpjs_jht_coss ?? 0,
                        'persen_bpjs_jp' => $summary->persen_bpjs_jp_coss ?? 0,
                    ];
                }

                // **PERBAIKAN UTAMA: Fungsi untuk menentukan display tunjangan dengan HPP dan COSS terpisah**
                $getTunjanganDisplayForBoth = function ($wage, $jenisField, $hppValue, $cossValue, $fieldDitagihkanTerpisah = null) {
                    if (!$wage) {
                        return ['hpp' => 'Tidak Ada1', 'coss' => 'Tidak Ada1'];
                    }

                    // Ambil jenis value terlebih dahulu
                    $jenisValue = $wage->$jenisField ?? null;

                    // **PERBAIKAN: Check if jenisValue is string for string operations**
                    $jenisValueString = is_string($jenisValue) ? strtolower(trim($jenisValue)) : '';

                    // CEK PRIORITAS 1: Field ditagihkan terpisah (HANYA untuk lembur yang punya field ini)
                    if ($fieldDitagihkanTerpisah && isset($wage->$fieldDitagihkanTerpisah)) {
                        $ditagihkanValue = $wage->$fieldDitagihkanTerpisah;
                        $ditagihkanValueString = is_string($ditagihkanValue) ? strtolower(trim($ditagihkanValue)) : '';

                        // Jika value adalah "Ditagihkan Terpisah"
                        if ($ditagihkanValueString == 'ditagihkan terpisah') {
                            return ['hpp' => 'Ditagihkan terpisah', 'coss' => 'Ditagihkan terpisah'];
                        }
                        // Jika diberikan langsung oleh client
                        if ($ditagihkanValueString == 'diberikan langsung' || $ditagihkanValueString == 'diberikan langsung oleh client') {
                            return ['hpp' => 'Diberikan Langsung Oleh Client', 'coss' => 'Diberikan Langsung Oleh Client'];
                        }
                    }
                    $ditagihkanValue = $wage->$fieldDitagihkanTerpisah;
                    $ditagihkanValueString = is_string($ditagihkanValue) ? strtolower(trim($ditagihkanValue)) : '';

                    // CEK PRIORITAS 2: Jenis tunjangan
                    if ($jenisValueString == 'normatif' || $jenisValueString == 'ditagihkan') {
                        return ['hpp' => 'Ditagihkan terpisah', 'coss' => 'Ditagihkan terpisah'];
                    } elseif (($jenisValueString == 'flat' && $ditagihkanValueString == 'ditagihkan') || $jenisValueString == 'diprovisikan' || $jenisValueString == 'flat' ) {
                        // **PERUBAHAN PENTING**: Gunakan nilai yang sesuai (HPP atau COSS)
                        $hppDisplay = $hppValue > 0 ? $hppValue : 'Tidak Ada2';
                        $cossDisplay = $cossValue > 0 ? $cossValue : 'Tidak Ada2';
                        return ['hpp' => $hppDisplay, 'coss' => $cossDisplay];
                    } elseif ($jenisValueString == 'diberikan langsung' || $jenisValueString == 'diberikan langsung oleh client') {
                        return ['hpp' => 'Diberikan Langsung Oleh Client', 'coss' => 'Diberikan Langsung Oleh Client'];
                    } else {
                        return ['hpp' => 'Tidak Ada', 'coss' => 'Tidak Ada'];
                    }
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
                    'quotation_pics' => $quotation->relationLoaded('quotationPics') ?
                        $quotation->quotationPics->map(function ($pic) {
                            return [
                                'id' => $pic->id,
                                'nama' => $pic->nama,
                                'jabatan_id' => $pic->jabatan_id,
                                'no_telp' => $pic->no_telp,
                                'email' => $pic->email,
                                'is_kuasa' => $pic->is_kuasa,
                            ];
                        })->toArray() : [],
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
                        ],
                        'coss' => [
                            'total_sebelum_management_fee_coss' => $calculatedQuotation->calculation_summary->total_sebelum_management_fee_coss ?? 0,
                            'nominal_management_fee_coss' => $calculatedQuotation->calculation_summary->nominal_management_fee_coss ?? 0,
                            'grand_total_sebelum_pajak_coss' => $calculatedQuotation->calculation_summary->grand_total_sebelum_pajak_coss ?? 0,
                            'ppn_coss' => $calculatedQuotation->calculation_summary->ppn_coss ?? 0,
                            'pph_coss' => $calculatedQuotation->calculation_summary->pph_coss ?? 0,
                            'dpp_coss' => $calculatedQuotation->calculation_summary->dpp_coss ?? 0,
                            'total_invoice_coss' => $calculatedQuotation->calculation_summary->total_invoice_coss ?? 0,
                            'pembulatan_coss' => $calculatedQuotation->calculation_summary->pembulatan_coss ?? 0,
                            'margin_coss' => $calculatedQuotation->calculation_summary->margin_coss ?? 0,
                            'gpm_coss' => $calculatedQuotation->calculation_summary->gpm_coss ?? 0,
                            'persen_bunga_bank' => $quotation->persen_bunga_bank ?? 0,
                            'persen_insentif' => $quotation->persen_insentif ?? 0,
                            'persen_bpjs_ksht' => $calculatedQuotation->calculation_summary->persen_bpjs_kesehatan_coss ?? 0,
                            'persen_bpjs_ketenagakerjaan' => $persenBpjsTotalCoss,

                        ],
                        'quotation_details' => $calculatedQuotation->quotation->quotation_detail->map(function ($detail) use ($getTunjanganDisplayForBoth) {
                            $wage = $detail->wage ?? null;
                            $potonganBpu = $detail->potongan_bpu ?? 0;

                            // **PERUBAHAN PENTING: Ambil data HPP dan COSS langsung dari database**
                            $hpp = QuotationDetailHpp::where('quotation_detail_id', $detail->id)->first();
                            $coss = QuotationDetailCoss::where('quotation_detail_id', $detail->id)->first();

                            $bpjsJkk = $detail->bpjs_jkk ?? 0;
                            $bpjsJkm = $detail->bpjs_jkm ?? 0;
                            $bpjsJht = $detail->bpjs_jht ?? 0;
                            $bpjsJp = $detail->bpjs_jp ?? 0;
                            $bpjsKes = $detail->bpjs_kes ?? 0;
                            $bpjsKetenagakerjaan = $bpjsJkk + $bpjsJkm + $bpjsJht + $bpjsJp;

                            $bpjsKesehatan = 0;
                            if ($detail->penjamin_kesehatan === 'BPJS' || $detail->penjamin_kesehatan === 'BPJS Kesehatan') {
                                $bpjsKesehatan = $bpjsKes;
                            } else if ($detail->penjamin_kesehatan === 'Asuransi Swasta' || $detail->penjamin_kesehatan === 'Takaful') {
                                $bpjsKesehatan = $detail->nominal_takaful ?? 0;
                            } else if ($detail->penjamin_kesehatan === 'BPU') {
                                $bpjsKesehatan = 0;
                            }

                            $tunjanganData = [];
                            if ($detail->relationLoaded('quotationDetailTunjangans')) {
                                $tunjanganData = $detail->quotationDetailTunjangans->map(function ($tunjangan) {
                                    return [
                                        'nama_tunjangan' => $tunjangan->nama_tunjangan,
                                        'nominal' => $tunjangan->nominal,
                                        'nominal_coss' => $tunjangan->nominal_coss,
                                    ];
                                })->toArray();
                            }

                            // **PERUBAHAN: Gunakan nilai dari HPP dan COSS dengan fallback ke detail jika tidak ada**
                            $tunjanganHariRayaHpp = $hpp->tunjangan_hari_raya ?? $detail->tunjangan_hari_raya_hpp ?? 0;
                            $tunjanganHariRayaCoss = $coss->tunjangan_hari_raya ?? $detail->tunjangan_hari_raya_coss ?? 0;

                            $kompensasiHpp = $hpp->kompensasi ?? $detail->kompensasi_hpp ?? 0;
                            $kompensasiCoss = $coss->kompensasi ?? $detail->kompensasi_coss ?? 0;

                            $lemburHpp = $hpp->lembur ?? $detail->lembur ?? 0;
                            $lemburCoss = $coss->lembur ?? $detail->lembur ?? 0;
                            // \log::info('Le mbur HPP: ' . $lemburHpp . ', Lembur COSS: ' . $lemburCoss);

                            $tunjanganHolidayHpp = $hpp->tunjangan_hari_libur_nasional ?? $detail->tunjangan_holiday ?? 0;
                            $tunjanganHolidayCoss = $coss->tunjangan_hari_libur_nasional ?? $detail->tunjangan_holiday ?? 0;

                            // **PERUBAHAN: Gunakan fungsi baru yang mendukung HPP dan COSS**
                            $thrDisplay = $getTunjanganDisplayForBoth(
                                $wage,
                                'thr',
                                $tunjanganHariRayaHpp,
                                $tunjanganHariRayaCoss
                            );

                            $kompensasiDisplay = $getTunjanganDisplayForBoth(
                                $wage,
                                'kompensasi',
                                $kompensasiHpp,
                                $kompensasiCoss
                            );

                            $lemburDisplay = $getTunjanganDisplayForBoth(
                                $wage,
                                'lembur',
                                $lemburHpp,
                                $lemburCoss,
                                'lembur_ditagihkan'
                            );

                            $tunjanganHolidayDisplay = $getTunjanganDisplayForBoth(
                                $wage,
                                'tunjangan_holiday',
                                $tunjanganHolidayHpp,
                                $tunjanganHolidayCoss
                            );

                            return [
                                'id' => $detail->id,
                                'position_name' => $detail->jabatan_kebutuhan,
                                'jumlah_hc_hpp' => $hpp->jumlah_hc ?? 0,
                                'jumlah_hc_coss' => $coss->jumlah_hc ?? 0,
                                'nama_site' => $detail->nama_site,
                                'quotation_site_id' => $detail->quotation_site_id,
                                'penjamin_kesehatan' => $detail->penjamin_kesehatan,
                                'tunjangan_data' => $tunjanganData,
                                'upah' => $wage ? $wage->upah : 0,
                                'hpp' => [
                                    'nominal_upah' => $detail->nominal_upah,
                                    'total_tunjangan' => $detail->total_tunjangan ?? 0,
                                    'bpjs_ketenagakerjaan' => $bpjsKetenagakerjaan,
                                    'bpjs_kesehatan' => $bpjsKesehatan,
                                    'bpjs_jkk' => $bpjsJkk,
                                    'bpjs_jkm' => $bpjsJkm,
                                    'bpjs_jht' => $bpjsJht,
                                    'bpjs_jp' => $bpjsJp,
                                    'bpjs_kes' => $bpjsKes,
                                    'persen_bpjs_jkk' => $detail->persen_bpjs_jkk ?? 0,
                                    'persen_bpjs_jkm' => $detail->persen_bpjs_jkm ?? 0,
                                    'persen_bpjs_jht' => $detail->persen_bpjs_jht ?? 0,
                                    'persen_bpjs_jp' => $detail->persen_bpjs_jp ?? 0,
                                    'persen_bpjs_kes' => $detail->persen_bpjs_kes ?? 0,
                                    'persen_bpjs_ketenagakerjaan' => $detail->persen_bpjs_ketenagakerjaan ?? 0,
                                    'persen_bpjs_kesehatan' => $detail->persen_bpjs_kesehatan ?? 0,
                                    'potongan_bpu' => $potonganBpu,
                                    'tunjangan_hari_raya' => $thrDisplay['hpp'],
                                    'kompensasi' => $kompensasiDisplay['hpp'],
                                    'lembur' => $lemburDisplay['hpp'],
                                    'tunjangan_holiday' => $tunjanganHolidayDisplay['hpp'],
                                    'bunga_bank' => $detail->bunga_bank ?? 0,
                                    'insentif' => $detail->insentif ?? 0,
                                    'personil_kaporlap' => $detail->personil_kaporlap ?? 0,
                                    'personil_devices' => $detail->personil_devices ?? 0,
                                    'personil_ohc' => $detail->personil_ohc ?? 0,
                                    'personil_chemical' => $detail->personil_chemical ?? 0,
                                    'total_personil' => $detail->total_personil ?? 0,
                                    'sub_total_personil' => $detail->sub_total_personil ?? 0,
                                    'total_base_manpower' => $detail->total_base_manpower ?? 0,
                                    'total_exclude_base_manpower' => $detail->total_exclude_base_manpower ?? 0,

                                ],
                                'coss' => [
                                    'nominal_upah' => $detail->nominal_upah,
                                    'total_tunjangan' => $detail->total_tunjangan_coss ?? 0,
                                    'bpjs_ketenagakerjaan' => $bpjsKetenagakerjaan,
                                    'bpjs_kesehatan' => $bpjsKesehatan,
                                    'bpjs_jkk' => $bpjsJkk,
                                    'bpjs_jkm' => $bpjsJkm,
                                    'bpjs_jht' => $bpjsJht,
                                    'bpjs_jp' => $bpjsJp,
                                    'bpjs_kes' => $bpjsKes,
                                    'persen_bpjs_jkk' => $detail->persen_bpjs_jkk ?? 0,
                                    'persen_bpjs_jkm' => $detail->persen_bpjs_jkm ?? 0,
                                    'persen_bpjs_jht' => $detail->persen_bpjs_jht ?? 0,
                                    'persen_bpjs_jp' => $detail->persen_bpjs_jp ?? 0,
                                    'persen_bpjs_kes' => $detail->persen_bpjs_kes ?? 0,
                                    'persen_bpjs_ketenagakerjaan' => $detail->persen_bpjs_ketenagakerjaan ?? 0,
                                    'persen_bpjs_kesehatan' => $detail->persen_bpjs_kesehatan ?? 0,
                                    'potongan_bpu' => $potonganBpu,
                                    'tunjangan_hari_raya' => $thrDisplay['coss'],
                                    'kompensasi' => $kompensasiDisplay['coss'],
                                    'lembur' => $lemburDisplay['coss'],
                                    'tunjangan_holiday' => $tunjanganHolidayDisplay['coss'],
                                    'bunga_bank' => $detail->bunga_bank ?? 0,
                                    'insentif' => $detail->insentif ?? 0,
                                    'personil_kaporlap_coss' => $detail->personil_kaporlap_coss ?? 0,
                                    'personil_devices_coss' => $detail->personil_devices_coss ?? 0,
                                    'personil_ohc_coss' => $detail->personil_ohc_coss ?? 0,
                                    'personil_chemical_coss' => $detail->personil_chemical_coss ?? 0,
                                    'total_personil' => $detail->total_personil_coss ?? 0,
                                    'sub_total_personil' => $detail->sub_total_personil_coss ?? 0,
                                    'total_base_manpower' => $detail->total_base_manpower ?? 0,
                                    'total_exclude_base_manpower' => $detail->total_exclude_base_manpower ?? 0,

                                ],
                                'debug_info' => [ // Untuk debugging
                                    'has_wage' => !empty($wage),
                                    'wage_thr' => $wage->thr ?? 'null',
                                    'wage_kompensasi' => $wage->kompensasi ?? 'null',
                                    'wage_lembur' => $wage->lembur ?? 'null',
                                    'wage_tunjangan_holiday' => $wage->tunjangan_holiday ?? 'null',
                                    'hpp_tunjangan_hari_raya' => $hpp->tunjangan_hari_raya ?? 'null',
                                    'coss_tunjangan_hari_raya' => $coss->tunjangan_hari_raya ?? 'null',
                                ]
                            ];
                        })->toArray()
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
                return [
                    'salary_rules' => SalaryRule::all(),
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
        $quotationService = new \App\Services\QuotationService();
        $calculation = $quotationService->calculateQuotation($this->resource);

        // Menggunakan model untuk semua query
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