<?php

namespace App\Services;

use App\Models\AplikasiPendukung;
use App\Models\BarangDefaultQty;
use App\Models\BidangPerusahaan;
use App\Models\City;
use App\Models\Company;
use App\Models\JabatanPic;
use App\Models\JenisBarang;
use App\Models\JenisPerusahaan;
use App\Models\ManagementFee;
use App\Models\Province;
use App\Models\Quotation;
use App\Models\QuotationAplikasi;
use App\Models\QuotationDetailTunjangan;
use App\Models\QuotationKaporlap;
use App\Models\QuotationDevices;
use App\Models\QuotationChemical;
use App\Models\QuotationOhc;
use App\Models\QuotationTraining;
use App\Models\QuotationKerjasama;
use App\Models\Barang;
use App\Models\SalaryRule;
use App\Models\Top;
use App\Models\Training;
use App\Models\Umk;
use App\Models\Ump;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class QuotationStepService
{
    protected $quotationService;

    public function __construct(QuotationService $quotationService)
    {
        $this->quotationService = $quotationService;
    }

    /**
     * Get relations for specific step
     */
    public function getStepRelations(int $step): array
    {
        $relations = [
            'leads',
            'statusQuotation',
            'quotationSites',
            'quotationDetails',
            'quotationPics',
            'company'
        ];

        $additionalRelations = [];
        if ($step == 3) {
            $additionalRelations[] = 'quotationDetails.quotationDetailRequirements';
            $additionalRelations[] = 'quotationDetails.quotationDetailTunjangans';
            $additionalRelations[] = 'quotationDetails.position';
        }

        if ($step >= 6) {
            $additionalRelations[] = 'quotationAplikasis';
        }

        if ($step >= 7) {
            $additionalRelations[] = 'quotationKaporlaps';
        }

        if ($step >= 8) {
            $additionalRelations[] = 'quotationDevices';
        }

        if ($step >= 9) {
            $additionalRelations[] = 'quotationChemicals';
        }

        if ($step >= 10) {
            $additionalRelations[] = 'quotationOhcs';
            $additionalRelations[] = 'quotationTrainings';
        }

        if ($step >= 11) {
            $additionalRelations[] = 'quotationKerjasamas';
        }

        return array_merge($relations, $additionalRelations);
    }

    /**
     * Prepare step data
     */
    public function prepareStepData(Quotation $quotation, int $step): array
    {
        $data = [
            'quotation' => $quotation,
            'step' => $step,
            'additional_data' => []
        ];

        switch ($step) {
            case 1:
                $data['additional_data']['company_list'] = Company::where('is_active', 1)->get();

                $data['additional_data']['province_list'] = Province::all()->map(function ($province) {
                    $ump = Ump::byProvince($province->id)
                        ->active()
                        ->first();

                    $province->ump_display = $ump
                        ? $ump->formatted_ump
                        : "UMP : Rp. 0";

                    return $province;
                });
                break;

            case 2:
                $data['additional_data']['salary_rules'] = SalaryRule::all();
                $data['additional_data']['top_list'] = Top::orderBy('nama', 'asc')->get();
                break;

            case 3:
                // TAMBAHKAN data untuk step 3
                $data['additional_data']['positions'] = \App\Models\Position::where('is_active', 1)
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
            case 4:
                $data['additional_data']['manajemen_fee_list'] = ManagementFee::all();
                $data['additional_data']['province_list'] = Province::all();

                // Data UMK per site
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

                // Data kota untuk referensi (opsional)
                $data['additional_data']['city_list'] = City::all()->map(function ($city) {
                    $umk = Umk::byCity($city->id)
                        ->active()
                        ->first();

                    $city->umk_display = $umk
                        ? $umk->formatted_umk
                        : "UMK : Rp. 0";

                    return $city;
                });
                break;

            case 5:
                $data['additional_data']['jenis_perusahaan_list'] = JenisPerusahaan::getAllActive();
                $data['additional_data']['bidang_perusahaan_list'] = BidangPerusahaan::getAllActive();
                break;

            case 6:
                $data['additional_data']['aplikasi_pendukung_list'] = AplikasiPendukung::getAllActive();
                $data['additional_data']['selected_aplikasi'] = $quotation->quotationAplikasis
                    ->pluck('aplikasi_pendukung_id')
                    ->toArray();
                break;

            case 7:
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

            case 8:
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

            case 9:
                $data['additional_data']['jenis_barang_list'] = JenisBarang::whereIn('id', [13, 14, 15, 16, 18, 19])->get();

                $data['additional_data']['chemical_list'] = Barang::whereIn('jenis_barang_id', [13, 14, 15, 16, 18, 19])
                    ->ordered()
                    ->get()
                    ->map(function ($barang) {
                        $barang->harga_formatted = $barang->formatted_harga;
                        return $barang;
                    });
                break;

            case 10:
                $data['additional_data']['jenis_barang_list'] = JenisBarang::whereIn('id', [6, 7, 8])->get();

                $data['additional_data']['ohc_list'] = Barang::whereIn('jenis_barang_id', [6, 7, 8])
                    ->ordered()
                    ->get()
                    ->map(function ($barang) {
                        $barang->harga_formatted = $barang->formatted_harga;
                        return $barang;
                    });
                break;

            case 11:
                $data['additional_data']['calculated_quotation'] = $this->quotationService->calculateQuotation($quotation);

                // Menggunakan model method
                $data['additional_data']['daftar_tunjangan'] = QuotationDetailTunjangan::distinctTunjanganByQuotation($quotation->id);

                // Menggunakan model dengan SoftDeletes
                $data['additional_data']['training_list'] = Training::all();

                $data['additional_data']['selected_training'] = $quotation->quotationTrainings
                    ->pluck('training_id')
                    ->toArray();

                // Menggunakan model dengan SoftDeletes
                $data['additional_data']['jabatan_pic_list'] = JabatanPic::all();
                break;

            case 12:
                $data['additional_data']['calculated_quotation'] = $this->quotationService->calculateQuotation($quotation);
                break;
        }

        // Data umum yang dibutuhkan di beberapa step
        if (in_array($step, [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12])) {
            // Menggunakan model dengan find
            $data['additional_data']['salary_rule_current'] = SalaryRule::find($quotation->salary_rule_id);
        }

        if (in_array($step, [3, 4, 5, 6, 7, 8, 9, 10, 11, 12])) {
            $data['additional_data']['quotation_sites'] = $quotation->quotationSites;
            $data['additional_data']['quotation_details'] = $quotation->quotationDetails;
        }

        return $data;
    }

    // ============================
    // STEP UPDATE METHODS
    // ============================

    public function updateStep1(Quotation $quotation, Request $request): void
    {
        $quotation->update([
            'jenis_kontrak' => $request->jenis_kontrak,
            'updated_by' => Auth::user()->full_name
        ]);
    }

    public function updateStep2(Quotation $quotation, Request $request): void
    {
        $this->validateStep2($request);

        $cutiData = $this->prepareCutiData($request);

        $quotation->update(array_merge([
            'mulai_kontrak' => $request->mulai_kontrak,
            'kontrak_selesai' => $request->kontrak_selesai,
            'tgl_penempatan' => $request->tgl_penempatan,
            'salary_rule_id' => $request->salary_rule,
            'pembayaran_invoice' => $request->pembayaran_invoice,
            'top' => $request->top,
            'jumlah_hari_invoice' => $request->jumlah_hari_invoice,
            'tipe_hari_invoice' => $request->tipe_hari_invoice,
            'evaluasi_kontrak' => $request->evaluasi_kontrak,
            'durasi_kerjasama' => $request->durasi_kerjasama,
            'durasi_karyawan' => $request->durasi_karyawan,
            'evaluasi_karyawan' => $request->evaluasi_karyawan,
            'hari_kerja' => $request->hari_kerja,
            'shift_kerja' => $request->shift_kerja,
            'jam_kerja' => $request->jam_kerja,
            'updated_by' => Auth::user()->full_name
        ], $cutiData));
    }

    public function updateStep3(Quotation $quotation, Request $request): void
    {
        // Step 3 biasanya tidak membutuhkan update data tambahan
    }

    public function updateStep4(Quotation $quotation, Request $request): void
    {
        $upahData = $this->calculateUpahData($quotation, $request);

        $quotation->update(array_merge([
            'upah' => $request->upah,
            'management_fee_id' => $request->manajemen_fee,
            'persentase' => $request->persentase,
            'thr' => $request->thr,
            'kompensasi' => $request->kompensasi,
            'lembur' => $request->lembur,
            'is_ppn' => $request->is_ppn,
            'ppn_pph_dipotong' => $request->ppn_pph_dipotong,
            'tunjangan_holiday' => $request->tunjangan_holiday,
            'nominal_lembur' => $request->nominal_lembur ? str_replace('.', '', $request->nominal_lembur) : null,
            'nominal_tunjangan_holiday' => $request->nominal_tunjangan_holiday ? str_replace('.', '', $request->nominal_tunjangan_holiday) : null,
            'jenis_bayar_tunjangan_holiday' => $request->jenis_bayar_tunjangan_holiday,
            'jenis_bayar_lembur' => $request->jenis_bayar_lembur,
            'lembur_ditagihkan' => $request->lembur_ditagihkan,
            'jam_per_bulan_lembur' => $request->jam_per_bulan_lembur,
            'updated_by' => Auth::user()->full_name
        ], $upahData));

        // Update nominal upah di semua detail
        $quotation->quotationDetails()->update([
            'nominal_upah' => $upahData['nominal_upah'],
            'updated_by' => Auth::user()->full_name
        ]);
    }

    public function updateStep5(Quotation $quotation, Request $request): void
    {
        // Update BPJS data untuk setiap detail
        foreach ($quotation->quotationDetails as $detail) {
            $detail->update([
                'penjamin_kesehatan' => $request->penjamin[$detail->id] ?? null,
                'is_bpjs_jkk' => isset($request->jkk[$detail->id]) ? 1 : 0,
                'is_bpjs_jkm' => isset($request->jkm[$detail->id]) ? 1 : 0,
                'is_bpjs_jht' => isset($request->jht[$detail->id]) ? 1 : 0,
                'is_bpjs_jp' => isset($request->jp[$detail->id]) ? 1 : 0,
                'nominal_takaful' => $request->nominal_takaful[$detail->id] ?? null,
                'updated_by' => Auth::user()->full_name
            ]);
        }

        $companyData = $this->prepareCompanyData($request);

        $quotation->update(array_merge([
            'is_aktif' => $this->calculateIsAktif($quotation, $request),
            'updated_by' => Auth::user()->full_name
        ], $companyData));

        // Update leads data
        $quotation->leads->update($companyData);
    }

    public function updateStep6(Quotation $quotation, Request $request): void
    {
        $currentDateTime = Carbon::now();

        // Update aplikasi pendukung
        if ($request->has('aplikasi_pendukung')) {
            $this->updateAplikasiPendukung($quotation, $request->aplikasi_pendukung, $currentDateTime);
        } else {
            QuotationAplikasi::where('quotation_id', $quotation->id)->update([
                'deleted_at' => $currentDateTime,
                'deleted_by' => Auth::user()->full_name
            ]);
        }
    }

    public function updateStep7(Quotation $quotation, Request $request): void
    {
        $currentDateTime = Carbon::now();

        // Hapus data kaporlap existing
        QuotationKaporlap::where('quotation_id', $quotation->id)->update([
            'deleted_at' => $currentDateTime,
            'deleted_by' => Auth::user()->full_name
        ]);

        // Insert data kaporlap baru
        $this->insertKaporlapData($quotation, $request, $currentDateTime);
    }

    public function updateStep8(Quotation $quotation, Request $request): void
    {
        $currentDateTime = Carbon::now();

        // Hapus data devices existing (kecuali aplikasi pendukung)
        QuotationDevices::where('quotation_id', $quotation->id)
            ->whereNotIn('barang_id', [192, 194, 195, 196])
            ->update([
                'deleted_at' => $currentDateTime,
                'deleted_by' => Auth::user()->full_name
            ]);

        // Insert data devices baru
        $this->insertDevicesData($quotation, $request, $currentDateTime);
    }

    public function updateStep9(Quotation $quotation, Request $request): void
    {
        $currentDateTime = Carbon::now();

        // Hapus data chemical existing
        QuotationChemical::where('quotation_id', $quotation->id)->update([
            'deleted_at' => $currentDateTime,
            'deleted_by' => Auth::user()->full_name
        ]);

        // Insert data chemical baru
        $this->insertChemicalData($quotation, $request, $currentDateTime);
    }

    public function updateStep10(Quotation $quotation, Request $request): void
    {
        $currentDateTime = Carbon::now();

        // Hapus data OHC existing
        QuotationOhc::where('quotation_id', $quotation->id)->update([
            'deleted_at' => $currentDateTime,
            'deleted_by' => Auth::user()->full_name
        ]);

        // Insert data OHC baru
        $this->insertOhcData($quotation, $request, $currentDateTime);

        // Update training
        $this->updateTrainingData($quotation, $request, $currentDateTime);

        // Update data kunjungan
        $quotation->update([
            'kunjungan_operasional' => $request->jumlah_kunjungan_operasional . " " . $request->bulan_tahun_kunjungan_operasional,
            'kunjungan_tim_crm' => $request->jumlah_kunjungan_tim_crm . " " . $request->bulan_tahun_kunjungan_tim_crm,
            'keterangan_kunjungan_operasional' => $request->keterangan_kunjungan_operasional,
            'keterangan_kunjungan_tim_crm' => $request->keterangan_kunjungan_tim_crm,
            'training' => $request->training,
            'persen_bunga_bank' => $quotation->persen_bunga_bank ?: 1.3,
            'updated_by' => Auth::user()->full_name
        ]);
    }

    public function updateStep11(Quotation $quotation, Request $request): void
    {
        $quotation->update([
            'penagihan' => $request->penagihan,
            'updated_by' => Auth::user()->full_name
        ]);

        // Generate perjanjian kerjasama
        $this->generateKerjasama($quotation);
    }

    public function updateStep12(Quotation $quotation, Request $request): void
    {
        $statusData = $this->calculateFinalStatus($quotation);

        $quotation->update(array_merge([
            'step' => 100,
            'updated_by' => Auth::user()->full_name
        ], $statusData));

        // Insert requirements jika belum ada
        $this->insertRequirements($quotation);
    }

    // ============================
    // HELPER METHODS
    // ============================

    private function validateStep2(Request $request): void
    {
        $validator = Validator::make($request->all(), [
            'mulai_kontrak' => 'required|date',
            'kontrak_selesai' => 'required|date|after_or_equal:mulai_kontrak',
            'tgl_penempatan' => 'required|date',
            'top' => 'required|string',
            'salary_rule' => 'required|exists:m_salary_rule,id'
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        if ($request->tgl_penempatan < $request->mulai_kontrak) {
            throw new \Exception('Tanggal Penempatan tidak boleh kurang dari Kontrak Awal');
        }

        if ($request->tgl_penempatan > $request->kontrak_selesai) {
            throw new \Exception('Tanggal Penempatan tidak boleh lebih dari Kontrak Selesai');
        }
    }

    private function prepareCutiData(Request $request): array
    {
        $data = [];

        if ($request->ada_cuti == "Tidak Ada") {
            $data['cuti'] = "Tidak Ada";
            $data['gaji_saat_cuti'] = null;
            $data['prorate'] = null;
            $data['hari_cuti_kematian'] = null;
            $data['hari_istri_melahirkan'] = null;
            $data['hari_cuti_menikah'] = null;
        } else {
            // Handle case where cuti might be null, string, or array
            $cuti = $request->cuti;

            if (is_null($cuti)) {
                $cuti = [];
            } elseif (!is_array($cuti)) {
                // If it's a string, convert to array
                $cuti = [$cuti];
            }

            $data['cuti'] = !empty($cuti) ? implode(",", $cuti) : null;

            if (in_array("Cuti Melahirkan", $cuti)) {
                if ($request->gaji_saat_cuti != "Prorate") {
                    $data['prorate'] = null;
                }
            } else {
                $data['gaji_saat_cuti'] = null;
                $data['prorate'] = null;
            }

            $data['hari_cuti_kematian'] = in_array("Cuti Kematian", $cuti) ? $request->hari_cuti_kematian : null;
            $data['hari_istri_melahirkan'] = in_array("Istri Melahirkan", $cuti) ? $request->hari_istri_melahirkan : null;
            $data['hari_cuti_menikah'] = in_array("Cuti Menikah", $cuti) ? $request->hari_cuti_menikah : null;
        }

        return $data;
    }

    private function calculateUpahData(Quotation $quotation, Request $request): array
    {
        $nominalUpah = 0;
        $hitunganUpah = "Per Bulan";

        if ($request->upah == "Custom") {
            $hitunganUpah = $request->hitungan_upah;
            $customUpah = str_replace(".", "", $request->custom_upah);

            if ($hitunganUpah == "Per Hari") {
                $customUpah = $customUpah * 21;
            } else if ($hitunganUpah == "Per Jam") {
                $customUpah = $customUpah * 21 * 8;
            }

            $nominalUpah = $customUpah;

            foreach ($quotation->quotationSites as $site) {
                $site->update([
                    'nominal_upah' => $nominalUpah,
                    'updated_by' => Auth::user()->full_name
                ]);
            }
        } else {
            // Update nominal upah di setiap site berdasarkan UMP/UMK masing-masing menggunakan scope
            foreach ($quotation->quotationSites as $site) {
                if ($request->upah == "UMP") {
                    // Gunakan scope dari model Ump
                    $dataUmp = Ump::byProvince($site->provinsi_id)
                        ->active()
                        ->first();
                    $nominalUpah = $dataUmp ? $dataUmp->ump : 0;
                } else if ($request->upah == "UMK") {
                    // Gunakan scope dari model Umk
                    $dataUmk = Umk::byCity($site->kota_id)
                        ->active()
                        ->first();
                    $nominalUpah = $dataUmk ? $dataUmk->umk : 0;
                }

                $site->update([
                    'nominal_upah' => $nominalUpah,
                    'updated_by' => Auth::user()->full_name
                ]);
            }
        }

        return [
            'nominal_upah' => $nominalUpah,
            'hitungan_upah' => $hitunganUpah
        ];
    }

    private function prepareCompanyData(Request $request): array
    {
        $data = [
            'jenis_perusahaan_id' => $request->jenis_perusahaan,
            'bidang_perusahaan_id' => $request->bidang_perusahaan,
            'resiko' => $request->resiko
        ];

        if ($request->jenis_perusahaan) {
            $jenisPerusahaan = DB::table('m_jenis_perusahaan')->where('id', $request->jenis_perusahaan)->first();
            $data['jenis_perusahaan'] = $jenisPerusahaan ? $jenisPerusahaan->nama : null;
        }

        if ($request->bidang_perusahaan) {
            $bidangPerusahaan = DB::table('m_bidang_perusahaan')->where('id', $request->bidang_perusahaan)->first();
            $data['bidang_perusahaan'] = $bidangPerusahaan ? $bidangPerusahaan->nama : null;
        }

        return $data;
    }

    private function calculateIsAktif(Quotation $quotation, Request $request): int
    {
        $isAktif = $quotation->is_aktif;

        if ($isAktif == 2) {
            $isAktif = 1;
        }

        return $isAktif;
    }

    private function updateAplikasiPendukung(Quotation $quotation, array $aplikasiPendukung, Carbon $currentDateTime): void
    {
        // Hapus aplikasi yang tidak dipilih
        QuotationAplikasi::where('quotation_id', $quotation->id)
            ->whereNotIn('aplikasi_pendukung_id', $aplikasiPendukung)
            ->update([
                'deleted_at' => $currentDateTime,
                'deleted_by' => Auth::user()->full_name
            ]);

        // Tambah/Tambah aplikasi yang dipilih
        foreach ($aplikasiPendukung as $aplikasiId) {
            $aplikasi = DB::table('m_aplikasi_pendukung')->where('id', $aplikasiId)->first();

            if ($aplikasi) {
                QuotationAplikasi::updateOrCreate(
                    [
                        'quotation_id' => $quotation->id,
                        'aplikasi_pendukung_id' => $aplikasiId
                    ],
                    [
                        'aplikasi_pendukung' => $aplikasi->nama,
                        'harga' => $aplikasi->harga,
                        'updated_by' => Auth::user()->full_name,
                        'deleted_at' => null
                    ]
                );
            }
        }
    }

    private function insertKaporlapData(Quotation $quotation, Request $request, Carbon $currentDateTime): void
    {
        $listKaporlap = Barang::whereNull('deleted_at')
            ->ordered()
            ->get();

        foreach ($listKaporlap as $barang) {
            foreach ($quotation->quotationDetails as $detail) {
                $fieldName = 'jumlah_' . $barang->id . '_' . $detail->id;
                $jumlah = $request->$fieldName;

                if ($jumlah && $jumlah > 0) {
                    QuotationKaporlap::create([
                        'quotation_detail_id' => $detail->id,
                        'quotation_id' => $quotation->id,
                        'barang_id' => $barang->id,
                        'jumlah' => $jumlah,
                        'harga' => $barang->harga,
                        'nama' => $barang->nama,
                        'jenis_barang_id' => $barang->jenis_barang_id,
                        'jenis_barang' => $barang->jenis_barang,
                        'created_by' => Auth::user()->full_name
                    ]);
                }
            }
        }
    }

    private function insertDevicesData(Quotation $quotation, Request $request, Carbon $currentDateTime): void
    {
        $listDevices = Barang::whereNull('deleted_at')
            ->ordered()
            ->get();

        foreach ($listDevices as $barang) {
            $fieldName = 'jumlah_' . $barang->id;
            $jumlah = $request->$fieldName;

            if ($jumlah && $jumlah > 0) {
                QuotationDevices::create([
                    'quotation_id' => $quotation->id,
                    'barang_id' => $barang->id,
                    'jumlah' => $jumlah,
                    'harga' => $barang->harga,
                    'nama' => $barang->nama,
                    'jenis_barang_id' => $barang->jenis_barang_id,
                    'jenis_barang' => $barang->jenis_barang,
                    'created_by' => Auth::user()->full_name
                ]);
            }
        }
    }

    private function insertChemicalData(Quotation $quotation, Request $request, Carbon $currentDateTime): void
    {
        $listChemical = Barang::whereNull('deleted_at')
            ->ordered()
            ->get();

        foreach ($listChemical as $barang) {
            foreach ($quotation->quotationDetails as $detail) {
                $fieldName = 'jumlah_' . $barang->id . '_' . $detail->id;
                $jumlah = $request->$fieldName;

                if ($jumlah && $jumlah > 0) {
                    QuotationChemical::create([
                        'quotation_detail_id' => $detail->id,
                        'quotation_id' => $quotation->id,
                        'barang_id' => $barang->id,
                        'jumlah' => $jumlah,
                        'harga' => $barang->harga,
                        'nama' => $barang->nama,
                        'jenis_barang_id' => $barang->jenis_barang_id,
                        'jenis_barang' => $barang->jenis_barang,
                        'created_by' => Auth::user()->full_name
                    ]);
                }
            }
        }
    }

    private function insertOhcData(Quotation $quotation, Request $request, Carbon $currentDateTime): void
    {
        $listOhc = Barang::whereNull('deleted_at')
            ->ordered()
            ->get();

        foreach ($listOhc as $barang) {
            foreach ($quotation->quotationDetails as $detail) {
                $fieldName = 'jumlah_' . $barang->id . '_' . $detail->id;
                $jumlah = $request->$fieldName;

                if ($jumlah && $jumlah > 0) {
                    QuotationOhc::create([
                        'quotation_detail_id' => $detail->id,
                        'quotation_id' => $quotation->id,
                        'barang_id' => $barang->id,
                        'jumlah' => $jumlah,
                        'harga' => $barang->harga,
                        'nama' => $barang->nama,
                        'jenis_barang_id' => $barang->jenis_barang_id,
                        'jenis_barang' => $barang->jenis_barang,
                        'created_by' => Auth::user()->full_name
                    ]);
                }
            }
        }
    }

    private function updateTrainingData(Quotation $quotation, Request $request, Carbon $currentDateTime): void
    {
        if ($request->has('training_id')) {
            // Hapus training existing
            QuotationTraining::where('quotation_id', $quotation->id)->update([
                'deleted_at' => $currentDateTime,
                'deleted_by' => Auth::user()->full_name
            ]);

            // Insert training baru
            $arrTrainingId = explode(",", $request->training_id);
            foreach ($arrTrainingId as $trainingId) {
                $training = DB::table('m_training')->where('id', $trainingId)->first();
                if ($training) {
                    QuotationTraining::create([
                        'training_id' => $trainingId,
                        'quotation_id' => $quotation->id,
                        'nama' => $training->nama,
                        'created_by' => Auth::user()->full_name
                    ]);
                }
            }
        }
    }

    private function generateKerjasama(Quotation $quotation): void
    {
        $currentDateTime = Carbon::now();

        // Hapus kerjasama existing
        QuotationKerjasama::where('quotation_id', $quotation->id)->update([
            'deleted_at' => $currentDateTime,
            'deleted_by' => Auth::user()->full_name
        ]);

        // Generate perjanjian kerjasama berdasarkan business logic
        $arrPerjanjian = $this->quotationService->generateKerjasamaContent($quotation);

        foreach ($arrPerjanjian as $perjanjian) {
            QuotationKerjasama::create([
                'quotation_id' => $quotation->id,
                'perjanjian' => $perjanjian,
                'created_by' => Auth::user()->full_name
            ]);
        }
    }

    private function calculateFinalStatus(Quotation $quotation): array
    {
        $isAktif = 1;
        $statusQuotation = 3;

        // Business logic untuk menentukan status final
        if ($quotation->top == "Lebih Dari 7 Hari") {
            $isAktif = 0;
            $statusQuotation = 2;
        }

        if ($quotation->persentase < 7) {
            $isAktif = 0;
            $statusQuotation = 2;
        }

        if ($quotation->company_id == 17) { // PT ION
            $isAktif = 0;
            $statusQuotation = 2;
        }

        if ($quotation->jenis_kontrak == "Reguler" && $quotation->kompensasi == "Tidak Ada") {
            $isAktif = 0;
            $statusQuotation = 2;
        }

        return [
            'is_aktif' => $isAktif,
            'status_quotation_id' => $statusQuotation
        ];
    }

    private function insertRequirements(Quotation $quotation): void
    {
        $currentDateTime = Carbon::now();

        foreach ($quotation->quotationDetails as $detail) {
            $existData = $detail->quotationDetailRequirements->count();

            if ($existData == 0) {
                $requirements = DB::table('m_kebutuhan_detail_requirement')
                    ->whereNull('deleted_at')
                    ->where('position_id', $detail->position_id)
                    ->get();

                foreach ($requirements as $req) {
                    DB::table('sl_quotation_detail_requirement')->insert([
                        'quotation_id' => $quotation->id,
                        'quotation_detail_id' => $detail->id,
                        'requirement' => $req->requirement,
                        'created_at' => $currentDateTime,
                        'created_by' => Auth::user()->full_name
                    ]);
                }
            }
        }
    }
}