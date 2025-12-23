<?php

namespace App\Http\Resources;

use App\Models\JabatanPic;
use App\Models\SalaryRule;
use App\Services\QuotationService;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class QuotationResource extends JsonResource
{// Kemudian tambahkan property di class
    protected $calculatedQuotation;

    public function __construct($resource)
    {
        parent::__construct($resource);

        // Pastikan relasi diperlukan untuk calculation sudah diload
        if (!$resource->relationLoaded('quotationDetails')) {
            $resource->load([
                'quotationDetails.quotationDetailHpps',
                'quotationDetails.quotationDetailCosses',
                'quotationDetails.wage'
            ]);
        }

        // Hitung quotation menggunakan service dengan error handling
        try {
            $quotationService = new QuotationService();
            $this->calculatedQuotation = $quotationService->calculateQuotation($resource);
        } catch (\Exception $e) {
            \Log::error("Error calculating quotation in resource: " . $e->getMessage());
            $this->calculatedQuotation = null;
        }
    }
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nomor' => $this->nomor,
            'tgl_quotation' => $this->tgl_quotation,
            'tgl_quotation_formatted' => $this->tgl_quotation ? Carbon::parse($this->tgl_quotation)->isoFormat('D MMMM Y') : null,
            'nama_perusahaan' => $this->nama_perusahaan,
            'kebutuhan' => $this->kebutuhan,
            'kebutuhan_id' => $this->kebutuhan_id,
            'company' => $this->company,
            'company_id' => $this->company_id,
            'jumlah_site' => $this->jumlah_site,
            'step' => $this->step,
            'is_aktif' => $this->is_aktif,
            'status_quotation_id' => $this->status_quotation_id,
            'revisi' => $this->revisi,
            'alasan_revisi' => $this->alasan_revisi,
            'quotation_asal_id' => $this->quotation_asal_id,
            'pengiriman_invoice' => $this->pengiriman_invoice,

            // Contract details
            'jenis_kontrak' => $this->jenis_kontrak,
            'mulai_kontrak' => $this->mulai_kontrak,
            'mulai_kontrak_formatted' => $this->mulai_kontrak ? Carbon::parse($this->mulai_kontrak)->isoFormat('D MMMM Y') : null,
            'kontrak_selesai' => $this->kontrak_selesai,
            'kontrak_selesai_formatted' => $this->kontrak_selesai ? Carbon::parse($this->kontrak_selesai)->isoFormat('D MMMM Y') : null,
            'tgl_penempatan' => $this->tgl_penempatan,
            'tgl_penempatan_formatted' => $this->tgl_penempatan ? Carbon::parse($this->tgl_penempatan)->isoFormat('D MMMM Y') : null,
            

            // Payment details
            'top' => $this->top,
            'salary_rule_id' => $this->salary_rule_id,
            'upah' => $this->upah,
            'nominal_upah' => $this->nominal_upah,
            'hitungan_upah' => $this->hitungan_upah,
            'management_fee_id' => $this->management_fee_id,
            'persentase' => $this->persentase,
            'is_ppn' => $this->is_ppn,
            'ppn_pph_dipotong' => $this->ppn_pph_dipotong,

            // Allowance details
            'thr' => $this->thr,
            'kompensasi' => $this->kompensasi,
            'lembur' => $this->lembur,
            'nominal_lembur' => $this->nominal_lembur,
            'jenis_bayar_lembur' => $this->jenis_bayar_lembur,
            'lembur_ditagihkan' => $this->lembur_ditagihkan,
            'jam_per_bulan_lembur' => $this->jam_per_bulan_lembur,
            'tunjangan_holiday' => $this->tunjangan_holiday,
            'nominal_tunjangan_holiday' => $this->nominal_tunjangan_holiday,
            'jenis_bayar_tunjangan_holiday' => $this->jenis_bayar_tunjangan_holiday,

            // Leave details
            'cuti' => $this->cuti,
            'hari_cuti_kematian' => $this->hari_cuti_kematian,
            'hari_istri_melahirkan' => $this->hari_istri_melahirkan,
            'hari_cuti_menikah' => $this->hari_cuti_menikah,
            'gaji_saat_cuti' => $this->gaji_saat_cuti,
            'prorate' => $this->prorate,

            // Work details
            'shift_kerja' => $this->shift_kerja,
            'hari_kerja' => $this->hari_kerja,
            'jam_kerja' => $this->jam_kerja,
            'evaluasi_kontrak' => $this->evaluasi_kontrak,
            'durasi_kerjasama' => $this->durasi_kerjasama,
            'durasi_karyawan' => $this->durasi_karyawan,
            'evaluasi_karyawan' => $this->evaluasi_karyawan,

            // Company details
            'jenis_perusahaan_id' => $this->jenis_perusahaan_id,
            'jenis_perusahaan' => $this->jenis_perusahaan,
            'bidang_perusahaan_id' => $this->bidang_perusahaan_id,
            'bidang_perusahaan' => $this->bidang_perusahaan,
            'resiko' => $this->resiko,

            // Visit details
            'kunjungan_operasional' => $this->kunjungan_operasional,
            'kunjungan_tim_crm' => $this->kunjungan_tim_crm,
            'keterangan_kunjungan_operasional' => $this->keterangan_kunjungan_operasional,
            'keterangan_kunjungan_tim_crm' => $this->keterangan_kunjungan_tim_crm,

            // Training & Financial
            'training' => $this->training,
            'persen_bunga_bank' => $this->persen_bunga_bank,
            'persen_insentif' => $this->persen_insentif,
            'penagihan' => $this->penagihan,
            'note_harga_jual' => $this->note_harga_jual,

            // Approval
            'ot1' => $this->ot1,
            'ot2' => $this->ot2,
            'ot3' => $this->ot3,

            // Timestamps
            'created_at' => $this->created_at,
            'created_at_formatted' => $this->created_at ? Carbon::parse($this->created_at)->isoFormat('D MMMM Y') : null,
            'created_by' => $this->created_by,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,

            // Relationships
            'leads' => $this->whenLoaded('leads', function () {
                return [
                    'id' => $this->leads->id,
                    'nomor' => $this->leads->nomor,
                    'nama_perusahaan' => $this->leads->nama_perusahaan,
                    'pic' => $this->leads->pic,
                    'jabatan' => $this->leads->jabatan,
                    'no_telp' => $this->leads->no_telp,
                    'email' => $this->leads->email,
                    'alamat' => $this->leads->alamat,
                ];
            }),

            'status_quotation' => $this->whenLoaded('statusQuotation', function () {
                return [
                    'id' => $this->statusQuotation->id,
                    'nama' => $this->statusQuotation->nama,
                ];
            }),
            'salary_rule' => SalaryRule::find($this->salary_rule_id),

            'quotation_sites' => $this->whenLoaded('quotationSites', function () {
                return $this->quotationSites->map(function ($site) {
                    return [
                        'id' => $site->id,
                        'nama_site' => $site->nama_site,
                        'provinsi_id' => $site->provinsi_id,
                        'provinsi' => $site->provinsi,
                        'kota_id' => $site->kota_id,
                        'kota' => $site->kota,
                        'ump' => $site->ump,
                        'umk' => $site->umk,
                        'nominal_upah' => $site->nominal_upah,
                        'penempatan' => $site->penempatan,
                    ];
                });
            }),
            // QUOTATION DETAILS - struktur baru sama seperti step 11
            'quotation_details' => $this->whenLoaded('quotationDetails', function () {
                return $this->quotationDetails->map(function ($detail) {
                    $wage = $detail->wage; // Ambil data dari relasi wage
    
                    // Jika wage null, buat array kosong atau nilai default
                    if (!$wage) {
                        $wageData = [
                            'upah' => null,
                            'hitungan_upah' => null,
                            'lembur' => null,
                            'nominal_lembur' => null,
                            'jenis_bayar_lembur' => null,
                            'jam_per_bulan_lembur' => null,
                            'lembur_ditagihkan' => null,
                            'kompensasi' => null,
                            'thr' => null,
                            'tunjangan_holiday' => null,
                            'nominal_tunjangan_holiday' => null,
                            'jenis_bayar_tunjangan_holiday' => null,
                        ];
                    } else {
                        $wageData = [
                            'upah' => $wage->upah,
                            'hitungan_upah' => $wage->hitungan_upah,
                            'lembur' => $wage->lembur,
                            'nominal_lembur' => $wage->nominal_lembur,
                            'jenis_bayar_lembur' => $wage->jenis_bayar_lembur,
                            'jam_per_bulan_lembur' => $wage->jam_per_bulan_lembur,
                            'lembur_ditagihkan' => $wage->lembur_ditagihkan,
                            'kompensasi' => $wage->kompensasi,
                            'thr' => $wage->thr,
                            'tunjangan_holiday' => $wage->tunjangan_holiday,
                            'nominal_tunjangan_holiday' => $wage->nominal_tunjangan_holiday,
                            'jenis_bayar_tunjangan_holiday' => $wage->jenis_bayar_tunjangan_holiday,
                        ];
                    }

                    // Ambil data HPP dan COSS dari relasi yang sudah diload
                    $hpp = $detail->relationLoaded('quotationDetailHpps') ? $detail->quotationDetailHpps->first() : null;
                    $coss = $detail->relationLoaded('quotationDetailCosses') ? $detail->quotationDetailCosses->first() : null;

                    // Logic untuk display lembur
                    $lemburDisplay = '';
                    if ($wage) {
                        if ($wage->lembur == 'Normatif' || $wage->lembur_ditagihkan == 'Ditagihkan Terpisah') {
                            $lemburDisplay = 'Ditagihkan terpisah';
                        } elseif ($wage->lembur == 'Flat') {
                            // Pastikan nilai lembur numerik sebelum diformat
                            $lemburValue = $hpp->lembur ?? 0;
                            // Konversi ke float jika string
                            $lemburValue = is_numeric($lemburValue) ? floatval($lemburValue) : 0;
                            $lemburDisplay = 'Rp. ' . number_format($lemburValue, 2, ',', '.');
                        } else {
                            $lemburDisplay = 'Tidak Ada';
                        }
                    }

                    // Logic untuk display tunjangan_holiday
                    $tunjanganHolidayDisplay = '';
                    if ($wage) {
                        if ($wage->tunjangan_holiday == 'Normatif') {
                            $tunjanganHolidayDisplay = 'Ditagihkan terpisah';
                        } elseif ($wage->tunjangan_holiday == 'Flat') {
                            // Pastikan nilai tunjangan_hari_libur_nasional numerik sebelum diformat
                            $thlValue = $hpp->tunjangan_hari_libur_nasional ?? 0;
                            // Konversi ke float jika string
                            $thlValue = is_numeric($thlValue) ? floatval($thlValue) : 0;
                            $tunjanganHolidayDisplay = 'Rp. ' . number_format($thlValue, 2, ',', '.');
                        } else {
                            $tunjanganHolidayDisplay = 'Tidak Ada';
                        }
                    }

                    return [
                        'id' => $detail->id,
                        'quotation_site_id' => $detail->quotation_site_id,
                        'nama_site' => $detail->nama_site,
                        'position_id' => $detail->position_id,
                        'jabatan_kebutuhan' => $detail->jabatan_kebutuhan,
                        'jumlah_hc' => $detail->jumlah_hc,
                        'nominal_upah' => $detail->nominal_upah,
                        'penjamin_kesehatan' => $detail->penjamin_kesehatan,
                        'is_bpjs_jkk' => $detail->is_bpjs_jkk,
                        'is_bpjs_jkm' => $detail->is_bpjs_jkm,
                        'is_bpjs_jht' => $detail->is_bpjs_jht,
                        'is_bpjs_jp' => $detail->is_bpjs_jp,
                        'nominal_takaful' => $detail->nominal_takaful,
                        'biaya_monitoring_kontrol' => $detail->biaya_monitoring_kontrol,

                        // Data dari wage table (gunakan $wageData)
                        'upah' => $wageData['upah'],
                        'hitungan_upah' => $wageData['hitungan_upah'],
                        'lembur' => $wageData['lembur'],
                        'nominal_lembur' => $wageData['nominal_lembur'],
                        'jenis_bayar_lembur' => $wageData['jenis_bayar_lembur'],
                        'jam_per_bulan_lembur' => $wageData['jam_per_bulan_lembur'],
                        'lembur_ditagihkan' => $wageData['lembur_ditagihkan'],
                        'kompensasi' => $wageData['kompensasi'],
                        'thr' => $wageData['thr'],
                        'tunjangan_holiday' => $wageData['tunjangan_holiday'],
                        'nominal_tunjangan_holiday' => $wageData['nominal_tunjangan_holiday'],
                        'jenis_bayar_tunjangan_holiday' => $wageData['jenis_bayar_tunjangan_holiday'],

                        'requirements' => $detail->relationLoaded('quotationDetailRequirements') ? $detail->quotationDetailRequirements->map(function ($requirement) {
                            return [
                                'id' => $requirement->id,
                                'requirement' => $requirement->requirement,
                            ];
                        }) : [],

                        'tunjangans' => $detail->relationLoaded('quotationDetailTunjangans') ? $detail->quotationDetailTunjangans->map(function ($tunjangan) {
                            return [
                                'id' => $tunjangan->id,
                                'nama_tunjangan' => $tunjangan->nama_tunjangan,
                                'nominal' => $tunjangan->nominal,
                            ];
                        }) : [],

                        // ✅ DATA HPP dengan struktur sama seperti step 11
                        'hpp' => $hpp ? [
                            'nominal_upah' => $detail->nominal_upah,
                            'total_tunjangan' => $detail->total_tunjangan ?? 0,
                            'bpjs_ketenagakerjaan' => $hpp->bpjs_jkk + $hpp->bpjs_jkm + $hpp->bpjs_jht + $hpp->bpjs_jp,
                            'bpjs_kesehatan' => $hpp->bpjs_ks,
                            'tunjangan_hari_raya' => $hpp->tunjangan_hari_raya,
                            'kompensasi' => $hpp->kompensasi,
                            'lembur' => $lemburDisplay,
                            'nominal_takaful' => $hpp->takaful,
                            'tunjangan_holiday' => $tunjanganHolidayDisplay,
                            'bunga_bank' => $hpp->bunga_bank,
                            'insentif' => $hpp->insentif,
                            'personil_kaporlap' => $hpp->provisi_seragam ?? 0,
                            'personil_devices' => $hpp->provisi_peralatan ?? 0,
                            'personil_ohc' => $hpp->provisi_ohc ?? 0,
                            'personil_chemical' => $hpp->provisi_chemical ?? 0,
                            'total_personil' => ($hpp->provisi_seragam ?? 0) + ($hpp->provisi_peralatan ?? 0) + ($hpp->provisi_ohc ?? 0) + ($hpp->provisi_chemical ?? 0),
                            'sub_total_personil' => $hpp->total_hpp ?? 0,
                            'total_base_manpower' => $hpp->total_hpp ?? 0,
                            'total_exclude_base_manpower' => 0,
                        ] : null,

                        // ✅ DATA COSS dengan struktur sama seperti step 11
                        'coss' => $coss ? [
                            'nominal_upah' => $detail->nominal_upah,
                            'total_tunjangan' => $detail->total_tunjangan ?? 0,
                            'bpjs_ketenagakerjaan' => $coss->bpjs_jkk + $coss->bpjs_jkm + $coss->bpjs_jht + $coss->bpjs_jp,
                            'bpjs_kesehatan' => $coss->bpjs_ks,
                            'tunjangan_hari_raya' => $coss->tunjangan_hari_raya,
                            'kompensasi' => $coss->kompensasi,
                            'lembur' => $lemburDisplay,
                            'nominal_takaful' => $coss->takaful,
                            'tunjangan_holiday' => $tunjanganHolidayDisplay,
                            'bunga_bank' => $coss->bunga_bank,
                            'insentif' => $coss->insentif,
                            'personil_kaporlap_coss' => $coss->provisi_seragam ?? 0,
                            'personil_devices_coss' => $coss->provisi_peralatan ?? 0,
                            'personil_ohc_coss' => $coss->provisi_ohc ?? 0,
                            'personil_chemical_coss' => $coss->provisi_chemical ?? 0,
                            'total_personil_coss' => ($coss->provisi_seragam ?? 0) + ($coss->provisi_peralatan ?? 0) + ($coss->provisi_ohc ?? 0) + ($coss->provisi_chemical ?? 0),
                            'sub_total_personil_coss' => $coss->total_coss ?? 0,
                            'total_base_manpower' => $coss->total_coss ?? 0,
                            'total_exclude_base_manpower' => 0,
                        ] : null,
                    ];
                });
            }),

            'calculation' => $this->calculatedQuotation ? [
                'hpp' => [
                    'total_sebelum_management_fee' => $this->calculatedQuotation->calculation_summary->total_sebelum_management_fee ?? 0,
                    'nominal_management_fee' => $this->calculatedQuotation->calculation_summary->nominal_management_fee ?? 0,
                    'grand_total_sebelum_pajak' => $this->calculatedQuotation->calculation_summary->grand_total_sebelum_pajak ?? 0,
                    'ppn' => $this->calculatedQuotation->calculation_summary->ppn ?? 0,
                    'pph' => $this->calculatedQuotation->calculation_summary->pph ?? 0,
                    'dpp' => $this->calculatedQuotation->calculation_summary->dpp ?? 0,
                    'total_invoice' => $this->calculatedQuotation->calculation_summary->total_invoice ?? 0,
                    'pembulatan' => $this->calculatedQuotation->calculation_summary->pembulatan ?? 0,
                    'margin' => $this->calculatedQuotation->calculation_summary->margin ?? 0,
                    'gpm' => $this->calculatedQuotation->calculation_summary->gpm ?? 0,
                    'persen_bunga_bank' => $this->persen_bunga_bank ?? 0,
                    'persen_bpjs_kesehatan' => $this->calculatedQuotation->calculation_summary->persen_bpjs_kesehatan ?? 0,
                    'persen_bpjs_ketenagakerjaan_total' => $this->calculatedQuotation->calculation_summary->persen_bpjs_ketenagakerjaan ?? 0,
                    'persen_insentif' => $this->persen_insentif ?? 0,
                ],
                'coss' => [
                    'total_sebelum_management_fee_coss' => $this->calculatedQuotation->calculation_summary->total_sebelum_management_fee_coss ?? 0,
                    'nominal_management_fee_coss' => $this->calculatedQuotation->calculation_summary->nominal_management_fee_coss ?? 0,
                    'grand_total_sebelum_pajak_coss' => $this->calculatedQuotation->calculation_summary->grand_total_sebelum_pajak_coss ?? 0,
                    'ppn_coss' => $this->calculatedQuotation->calculation_summary->ppn_coss ?? 0,
                    'pph_coss' => $this->calculatedQuotation->calculation_summary->pph_coss ?? 0,
                    'dpp_coss' => $this->calculatedQuotation->calculation_summary->dpp_coss ?? 0,
                    'total_invoice_coss' => $this->calculatedQuotation->calculation_summary->total_invoice_coss ?? 0,
                    'pembulatan_coss' => $this->calculatedQuotation->calculation_summary->pembulatan_coss ?? 0,
                    'margin_coss' => $this->calculatedQuotation->calculation_summary->margin_coss ?? 0,
                    'gpm_coss' => $this->calculatedQuotation->calculation_summary->gpm_coss ?? 0,
                    'persen_bunga_bank' => $this->persen_bunga_bank ?? 0,
                    'persen_bpjs_kesehatan' => $this->calculatedQuotation->calculation_summary->persen_bpjs_kesehatan ?? 0,
                    'persen_bpjs_ketenagakerjaan_total' => $this->calculatedQuotation->calculation_summary->persen_bpjs_ketenagakerjaan_coss ?? 0,
                    'persen_insentif' => $this->persen_insentif ?? 0,
                ],
           
                'quotation_details' => $this->calculatedQuotation->quotation->quotation_detail->map(function ($detail) {
                    // Ambil data wage untuk mendapatkan info lembur dan tunjangan_holiday
                    $wage = $detail->wage ?? null;

                    // Logic untuk display lembur
                    $lemburDisplay = '';
                    if ($wage) {
                        if ($wage->lembur == 'Normatif' || $wage->lembur_ditagihkan == 'Ditagihkan Terpisah') {
                            $lemburDisplay = 'Ditagihkan terpisah';
                        } elseif ($wage->lembur == 'Flat') {
                            // Pastikan nilai lembur numerik sebelum diformat
                            $lemburValue = $hpp->lembur ?? 0;
                            // Konversi ke float jika string
                            $lemburValue = is_numeric($lemburValue) ? floatval($lemburValue) : 0;
                            $lemburDisplay = 'Rp. ' . number_format($lemburValue, 2, ',', '.');
                        } else {
                            $lemburDisplay = 'Tidak Ada';
                        }
                    }

                    // Logic untuk display tunjangan_holiday
                    $tunjanganHolidayDisplay = '';
                    if ($wage) {
                        if ($wage->tunjangan_holiday == 'Normatif') {
                            $tunjanganHolidayDisplay = 'Ditagihkan terpisah';
                        } elseif ($wage->tunjangan_holiday == 'Flat') {
                            // Pastikan nilai tunjangan_hari_libur_nasional numerik sebelum diformat
                            $thlValue = $hpp->tunjangan_hari_libur_nasional ?? 0;
                            // Konversi ke float jika string
                            $thlValue = is_numeric($thlValue) ? floatval($thlValue) : 0;
                            $tunjanganHolidayDisplay = 'Rp. ' . number_format($thlValue, 2, ',', '.');
                        } else {
                            $tunjanganHolidayDisplay = 'Tidak Ada';
                        }
                    }

                    return [
                        'id' => $detail->id,
                        'position_name' => $detail->jabatan_kebutuhan,
                        'jumlah_hc' => $detail->jumlah_hc,
                        'nama_site' => $detail->nama_site,
                        'quotation_site_id' => $detail->quotation_site_id,
                        'kota'=> $detail->quotationSite->kota ?? null,

                        // ✅ DATA HPP
                        'hpp' => [
                            'nominal_upah' => $detail->nominal_upah,
                            'total_tunjangan' => $detail->total_tunjangan,
                            'bpjs_ketenagakerjaan' => $detail->bpjs_ketenagakerjaan,
                            'bpjs_kesehatan' => $detail->bpjs_kesehatan,
                            'tunjangan_hari_raya' => $detail->tunjangan_hari_raya,
                            'kompensasi' => $detail->kompensasi,
                            'lembur' => $lemburDisplay,
                            'nominal_takaful' => $detail->nominal_takaful,
                            'tunjangan_holiday' => $tunjanganHolidayDisplay,
                            'bunga_bank' => $detail->bunga_bank,
                            'insentif' => $detail->insentif,
                            'personil_kaporlap' => $detail->personil_kaporlap ?? 0,
                            'personil_devices' => $detail->personil_devices ?? 0,
                            'personil_ohc' => $detail->personil_ohc ?? 0,
                            'personil_chemical' => $detail->personil_chemical ?? 0,
                            'total_personil' => $detail->total_personil,
                            'sub_total_personil' => $detail->sub_total_personil,
                            'total_base_manpower' => $detail->total_base_manpower ?? 0,
                            'total_exclude_base_manpower' => $detail->total_exclude_base_manpower ?? 0,
                        ],

                        // ✅ DATA COSS
                        'coss' => [
                            'nominal_upah' => $detail->nominal_upah,
                            'total_tunjangan' => $detail->total_tunjangan,
                            'bpjs_ketenagakerjaan' => $detail->bpjs_ketenagakerjaan,
                            'bpjs_kesehatan' => $detail->bpjs_kesehatan,
                            'tunjangan_hari_raya' => $detail->tunjangan_hari_raya,
                            'kompensasi' => $detail->kompensasi,
                            'lembur' => $lemburDisplay,
                            'nominal_takaful' => $detail->nominal_takaful,
                            'tunjangan_holiday' => $tunjanganHolidayDisplay,
                            'bunga_bank' => $detail->bunga_bank,
                            'insentif' => $detail->insentif,
                            'personil_kaporlap_coss' => $detail->personil_kaporlap_coss ?? 0,
                            'personil_devices_coss' => $detail->personil_devices_coss ?? 0,
                            'personil_ohc_coss' => $detail->personil_ohc_coss ?? 0,
                            'personil_chemical_coss' => $detail->personil_chemical_coss ?? 0,
                            'total_personil' => $detail->total_personil_coss ?? 0,
                            'sub_total_personil' => $detail->sub_total_personil_coss ?? 0,
                            'total_base_manpower' => $detail->total_base_manpower ?? 0,
                            'total_exclude_base_manpower' => $detail->total_exclude_base_manpower ?? 0,
                        ]
                    ];
                })->toArray()
            ] : null,

            'quotation_pics' => $this->whenLoaded('quotationPics', function () {
                return $this->quotationPics->map(function ($pic) {

                    // Kalau jabatan berupa angka → cari nama jabatan
                    $jabatanNama = is_numeric($pic->jabatan)
                        ? optional(JabatanPic::find($pic->jabatan))->nama
                        : $pic->jabatan; // Kalau sudah nama, langsung pakai
    
                    return [
                        'id' => $pic->id,
                        'nama' => $pic->nama,
                        'jabatan' => $jabatanNama,
                        'no_telp' => $pic->no_telp,
                        'email' => $pic->email,
                        'is_kuasa' => $pic->is_kuasa,
                    ];
                });
            }),
            'quotation_aplikasis' => $this->whenLoaded('quotationAplikasis', function () {
                return $this->quotationAplikasis->map(function ($aplikasi) {
                    return [
                        'id' => $aplikasi->id,
                        'aplikasi_pendukung_id' => $aplikasi->aplikasi_pendukung_id,
                        'aplikasi_pendukung' => $aplikasi->aplikasi_pendukung,
                        'harga' => $aplikasi->harga,
                    ];
                });
            }),

            'quotation_kaporlaps' => $this->whenLoaded('quotationKaporlaps', function () {
                return $this->quotationKaporlaps->map(function ($kaporlap) {
                    return [
                        'id' => $kaporlap->id,
                        'quotation_detail_id' => $kaporlap->quotation_detail_id,
                        'barang_id' => $kaporlap->barang_id,
                        'nama' => $kaporlap->nama,
                        'jenis_barang_id' => $kaporlap->jenis_barang_id,
                        'jenis_barang' => $kaporlap->jenis_barang,
                        'jumlah' => $kaporlap->jumlah,
                        'harga' => $kaporlap->harga,
                        'total' => $kaporlap->jumlah * $kaporlap->harga,
                    ];
                });
            }),

            'quotation_devices' => $this->whenLoaded('quotationDevices', function () {
                return $this->quotationDevices->map(function ($device) {
                    return [
                        'id' => $device->id,
                        'barang_id' => $device->barang_id,
                        'nama' => $device->nama,
                        'jenis_barang_id' => $device->jenis_barang_id,
                        'jenis_barang' => $device->jenis_barang,
                        'jumlah' => $device->jumlah,
                        'harga' => $device->harga,
                        'total' => $device->jumlah * $device->harga,
                    ];
                });
            }),

            'quotation_chemicals' => $this->whenLoaded('quotationChemicals', function () {
                return $this->quotationChemicals->map(function ($chemical) {
                    return [
                        'id' => $chemical->id,
                        'barang_id' => $chemical->barang_id,
                        'nama' => $chemical->nama,
                        'jenis_barang_id' => $chemical->jenis_barang_id,
                        'jenis_barang' => $chemical->jenis_barang,
                        'jumlah' => $chemical->jumlah,
                        'harga' => $chemical->harga,
                        'masa_pakai' => $chemical->masa_pakai,
                        'total_per_tahun' => $chemical->harga * $chemical->jumlah / $chemical->masa_pakai * 12,
                    ];
                });
            }),

            'quotation_ohcs' => $this->whenLoaded('quotationOhcs', function () {
                return $this->quotationOhcs->map(function ($ohc) {
                    return [
                        'id' => $ohc->id,
                        'barang_id' => $ohc->barang_id,
                        'nama' => $ohc->nama,
                        'jenis_barang_id' => $ohc->jenis_barang_id,
                        'jenis_barang' => $ohc->jenis_barang,
                        'jumlah' => $ohc->jumlah,
                        'harga' => $ohc->harga,
                        'total' => $ohc->jumlah * $ohc->harga,
                    ];
                });
            }),

            'quotation_trainings' => $this->whenLoaded('quotationTrainings', function () {
                return $this->quotationTrainings->map(function ($training) {
                    return [
                        'id' => $training->id,
                        'training_id' => $training->training_id,
                        'nama' => $training->nama,
                    ];
                });
            }),

            'quotation_kerjasamas' => $this->relationLoaded('quotationKerjasamas')
                ? $this->quotationKerjasamas
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
                : [],
            // ...
            // Calculated fields
            'total_hc' => $this->whenLoaded('quotationDetails', function () {
                return $this->quotationDetails->sum('jumlah_hc');
            }),

            'total_hpp' => $this->whenLoaded('quotationDetailHpps', function () {
                return $this->quotationDetailHpps->sum('total_hpp');
            }),

            'total_coss' => $this->whenLoaded('quotationDetailCosses', function () {
                return $this->quotationDetailCosses->sum('total_coss');
            }),

            'can_create_spk' => $this->is_aktif == 1,

            // Additional metadata
            'links' => [
                'self' => url("/api/quotation/view/{$this->id}"),
                'steps' => url("/api/quotation-step/{$this->id}/step/{$this->step}"),
            ],
        ];
    }

    public function with($request)
    {
        return [
            'meta' => [
                'version' => '1.0',
                'author' => 'Your Application Name',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}