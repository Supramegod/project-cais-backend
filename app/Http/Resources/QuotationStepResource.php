<?php

namespace App\Http\Resources;

use App\Models\BarangDefaultQty;
use App\Models\Company;
use App\Models\JenisBarang;
use App\Models\Kebutuhan;
use App\Models\Province;
use App\Models\Quotation;
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
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QuotationStepResource extends JsonResource
{
    private $step;

    public function __construct($resource, $step = null)
    {
        parent::__construct($resource);
        $this->step = $step ?: ($resource['step'] ?? null);
    }

    public function toArray($request)
    {
        $quotation = $this['quotation'] ?? $this->resource;
        $step = $this->step;
        $baseData = [
            'id' => $this->id ?? $this['quotation']->id,
            'step' => $this->step,
        ];

        // Hanya tambahkan data dasar jika diperlukan
        if (in_array($this->step, [1, 2])) {
            $baseData['nomor'] = $this->nomor ?? $this['quotation']->nomor;
            $baseData['nama_perusahaan'] = $this->nama_perusahaan ?? $this['quotation']->nama_perusahaan;
            $baseData['jumlah_site'] = $this->jumlah_site ?? $this['quotation']->jumlah_site;
        }

        $stepData = $this->getStepSpecificData($quotation, $step);
        $additionalData = $this->getAdditionalData($quotation,$step);

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
                    'mulai_kontrak_formatted' => $quotation->mulai_kontrak ? Carbon::parse($quotation->mulai_kontrak)->isoFormat('D MMMM Y') : null,
                    'kontrak_selesai' => $quotation->kontrak_selesai,
                    'kontrak_selesai_formatted' => $quotation->kontrak_selesai ? Carbon::parse($quotation->kontrak_selesai)->isoFormat('D MMMM Y') : null,
                    'tgl_penempatan' => $quotation->tgl_penempatan,
                    'tgl_penempatan_formatted' => $quotation->tgl_penempatan ? Carbon::parse($quotation->tgl_penempatan)->isoFormat('D MMMM Y') : null,
                    'top' => $quotation->top,
                    'salary_rule_id' => $quotation->salary_rule_id,
                    'pembayaran_invoice' => $quotation->pembayaran_invoice,
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
            case 4:
                return [
                    'upah' => $quotation->upah,
                    // 'nominal_upah' => $quotation->nominal_upah,
                    'hitungan_upah' => $quotation->hitungan_upah,
                    'management_fee_id' => $quotation->management_fee_id,
                    'persentase' => $quotation->persentase,
                    'ada_lembur' => $quotation->ada_lembur,
                    'lembur' => $quotation->lembur,
                    'nominal_lembur' => $quotation->nominal_lembur,
                    'jenis_bayar_lembur' => $quotation->jenis_bayar_lembur,
                    'jam_per_bulan_lembur' => $quotation->jam_per_bulan_lembur,
                    'lembur_ditagihkan' => $quotation->lembur_ditagihkan,
                    'ada_kompensasi' => $quotation->ada_kompensasi,
                    'kompensasi' => $quotation->kompensasi,
                    'ada_thr' => $quotation->ada_thr,
                    'thr' => $quotation->thr,
                    'ada_tunjangan_holiday' => $quotation->ada_tunjangan_holiday,
                    'tunjangan_holiday' => $quotation->tunjangan_holiday,
                    'nominal_tunjangan_holiday' => $quotation->nominal_tunjangan_holiday,
                    'jenis_bayar_tunjangan_holiday' => $quotation->jenis_bayar_tunjangan_holiday,
                    'is_ppn' => $quotation->is_ppn,
                    'ppn_pph_dipotong' => $quotation->ppn_pph_dipotong,
                    // Tambahkan data upah per site
                    'upah_per_site' => $quotation->relationLoaded('quotationSites') ?
                        $quotation->quotationSites->map(function ($site) {
                            return [
                                'site_id' => $site->id,
                                'site_name' => $site->nama_site,
                                'nominal_upah' => $site->nominal_upah,
                                'city_id' => $site->kota_id,
                                'city_name' => $site->kota,
                                'province_id' => $site->provinsi_id,
                                'province_name' => $site->provinsi,
                            ];
                        })->toArray() : [],
                ];
            case 5:
                return [
                    'jenis_perusahaan_id' => $quotation->jenis_perusahaan_id,
                    'bidang_perusahaan_id' => $quotation->bidang_perusahaan_id,
                    'resiko' => $quotation->resiko,
                    'program_bpjs' => $quotation->program_bpjs,
                    'penjamin' => $quotation->penjamin,
                    'jkk' => $quotation->jkk,
                    'jkm' => $quotation->jkm,
                    'jht' => $quotation->jht,
                    'jp' => $quotation->jp,
                    'nominal_takaful' => $quotation->nominal_takaful,
                ];

            case 6:
                return [
                    'aplikasi_pendukung' => $quotation->relationLoaded('quotationAplikasis') ?
                        $quotation->quotationAplikasis->pluck('aplikasi_pendukung_id')->toArray() : [],
                ];

            case 7:
                return [
                    'quotation_kaporlaps' => $quotation->relationLoaded('quotationKaporlaps') ?
                        $quotation->quotationKaporlaps->map(function ($kaporlap) {
                            return [
                                'id' => $kaporlap->id,
                                'barang_id' => $kaporlap->barang_id,
                                'quotation_detail_id' => $kaporlap->quotation_detail_id,
                                'jumlah' => $kaporlap->jumlah,
                                'harga' => $kaporlap->harga,
                            ];
                        })->toArray() : [],
                ];

            case 8:
                return [
                    'quotation_devices' => $quotation->relationLoaded('quotationDevices') ?
                        $quotation->quotationDevices->map(function ($device) {
                            return [
                                'id' => $device->id,
                                'barang_id' => $device->barang_id,
                                'jumlah' => $device->jumlah,
                                'harga' => $device->harga,
                            ];
                        })->toArray() : [],
                ];

            case 9:
                return [
                    'quotation_chemicals' => $quotation->relationLoaded('quotationChemicals') ?
                        $quotation->quotationChemicals->map(function ($chemical) {
                            return [
                                'id' => $chemical->id,
                                'barang_id' => $chemical->barang_id,
                                'jumlah' => $chemical->jumlah,
                                'harga' => $chemical->harga,
                                'masa_pakai' => $chemical->masa_pakai,
                            ];
                        })->toArray() : [],
                ];

            case 10:
                return [
                    'jumlah_kunjungan_operasional' => $quotation->jumlah_kunjungan_operasional,
                    'bulan_tahun_kunjungan_operasional' => $quotation->bulan_tahun_kunjungan_operasional,
                    'jumlah_kunjungan_tim_crm' => $quotation->jumlah_kunjungan_tim_crm,
                    'bulan_tahun_kunjungan_tim_crm' => $quotation->bulan_tahun_kunjungan_tim_crm,
                    'keterangan_kunjungan_operasional' => $quotation->keterangan_kunjungan_operasional,
                    'keterangan_kunjungan_tim_crm' => $quotation->keterangan_kunjungan_tim_crm,
                    'ada_training' => $quotation->ada_training,
                    'training' => $quotation->training,
                    'persen_bunga_bank' => $quotation->persen_bunga_bank,
                    'quotation_ohcs' => $quotation->relationLoaded('quotationOhcs') ?
                        $quotation->quotationOhcs->map(function ($ohc) {
                            return [
                                'id' => $ohc->id,
                                'barang_id' => $ohc->barang_id,
                                'jumlah' => $ohc->jumlah,
                                'harga' => $ohc->harga,
                            ];
                        })->toArray() : [],
                    'quotation_trainings' => $quotation->relationLoaded('quotationTrainings') ?
                        $quotation->quotationTrainings->pluck('training_id')->toArray() : [],
                ];

            case 11:
                return [
                    'penagihan' => $quotation->penagihan,
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
                ];

            case 12:
                return [
                    'quotation_kerjasamas' => $quotation->relationLoaded('quotationKerjasamas') ?
                        $quotation->quotationKerjasamas->pluck('perjanjian')->toArray() : [],
                    'final_confirmation' => true,
                ];

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
                    'company_list' => Company::where('is_active', 1)->get(),
                    'kebutuhan_list' => Kebutuhan::all(),
                    'province_list' => Province::get()->map(function ($province) {
                        $ump = Ump::where('is_aktif', 1)
                            ->where('province_id', $province->id)
                            ->first();
                        $umpValue = $ump ? $ump->ump : 0;
                        $province->ump = $ump ? "UMP : Rp. " . number_format($umpValue, 2, ",", ".") : "UMP : Rp. 0";
                        return $province;
                    }),
                ];

            case 2:
                return [
                    'salary_rules' => SalaryRule::all(),
                    'top_list' => Top::orderBy('nama', 'asc')->get(),
                    'pembayaran_invoice' => Quotation::distinct()->pluck('pembayaran_invoice'),

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
                            'formatted_umk' => $umk->formatUmk()
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
                            'formatted_ump' => $ump->formatUmp()
                        ];
                    }
                }

                return [
                    'management_fees' => ManagementFee::all(),
                    'upah_options' => ['UMP', 'UMK', 'Custom'],
                    'hitungan_upah_options' => ['Per Bulan', 'Per Hari', 'Per Jam'],
                    'jenis_bayar_options' => ['Per Bulan', 'Per Hari', 'Per Jam'],
                    'boolean_options' => ['Ada', 'Tidak Ada'],
                    'lembur_options' => ['Flat', 'Tidak Ada'],
                    'umk_per_site' => $umkPerSite,
                    'ump_per_site' => $umpPerSite,
                ];

            case 5:
                return [
                    'jenis_perusahaan' => JenisPerusahaan::all(),
                    'bidang_perusahaan' => BidangPerusahaan::all(),
                    'resiko_options' => ['Rendah', 'Sedang', 'Tinggi'],
                    'program_bpjs_options' => ['BPJS Kesehatan', 'BPJS Ketenagakerjaan', 'Keduanya'],
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
                return [
                    'chemicals' => Barang::whereIn('jenis_barang_id', [13, 14, 15, 16, 18, 19])
                        ->orderBy("urutan", "asc")
                        ->orderBy("nama", "asc")
                        ->get()
                        ->map(function ($chemical) {
                            $chemical->harga_formatted = number_format($chemical->harga, 0, ",", ".");
                            return $chemical;
                        }),
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
                return $this->getCalculationData();

            case 12:
                return [
                    'kerjasama_list' => $quotation->relationLoaded('quotationKerjasamas') ?
                        $quotation->quotationKerjasamas->toArray() : [],
                    'jabatan_pic_list' => JabatanPic::whereNull('deleted_at')->get(),
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