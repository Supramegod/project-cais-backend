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
use App\Models\Position;
use App\Models\Province;
use App\Models\Quotation;
use App\Models\QuotationAplikasi;
use App\Models\QuotationDetailTunjangan;
use App\Models\QuotationDetailWage;
use App\Models\QuotationKaporlap;
use App\Models\QuotationDevices;
use App\Models\QuotationChemical;
use App\Models\QuotationOhc;
use App\Models\QuotationSite;
use App\Models\QuotationTraining;
use App\Models\QuotationKerjasama;
use App\Models\QuotationDetail;
use App\Models\QuotationDetailHpp;
use App\Models\QuotationDetailCoss;
use App\Models\TunjanganPosisi;
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
    protected $quotationBarangService;

    public function __construct(QuotationService $quotationService, QuotationBarangService $quotationBarangService)
    {
        $this->quotationService = $quotationService;
        $this->quotationBarangService = $quotationBarangService;
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
        if ($step == 4) {
            $additionalRelations[] = 'quotationDetails.wage';
            $additionalRelations[] = 'quotationDetails.wage.managementFee';
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
        \Log::info("Updating Step 1", [
            'quotation_id' => $quotation->id,
            'current_jenis_kontrak' => $quotation->jenis_kontrak,
            'new_jenis_kontrak' => $request->jenis_kontrak,
            'user' => Auth::user()->full_name
        ]);

        try {
            // Approach 1: Gunakan save() instead of update()
            $quotation->jenis_kontrak = $request->jenis_kontrak;
            $quotation->updated_by = Auth::user()->full_name;

            $saved = $quotation->save();

            \Log::info("Save result", [
                'success' => $saved,
                'changes' => $quotation->getChanges(),
                'dirty' => $quotation->getDirty()
            ]);

            if (!$saved) {
                throw new \Exception("Failed to save quotation step 1");
            }

            // Verifikasi dengan query langsung
            $updatedQuotation = Quotation::find($quotation->id);
            \Log::info("Database verification", [
                'jenis_kontrak_in_db' => $updatedQuotation->jenis_kontrak
            ]);

        } catch (\Exception $e) {
            \Log::error("Error in updateStep1", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    public function updateStep2(Quotation $quotation, Request $request): void
    {
        $this->validateStep2($request);

        $cutiData = $this->prepareCutiData($request);

        // Ambil persentase bunga bank dari TOP
        $top = Top::find($request->top);
        $persenBungaBank = $top ? ($top->persentase ?? 0) : 0;

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
            'persen_bunga_bank' => $persenBungaBank,
            'updated_by' => Auth::user()->full_name
        ], $cutiData));
    }
    public function updateStep3(Quotation $quotation, Request $request): void
    {
        // Kalau request kirim banyak data (headCountData)
        if ($request->has('headCountData') && is_array($request->headCountData)) {
            $this->syncDetailHCFromArray($quotation, $request->headCountData);
        }

        // Kalau kirim satu data langsung
        elseif ($request->has('position_id') && $request->has('quotation_site_id')) {
            $detailData = [
                [ // bungkus jadi array of array
                    'quotation_site_id' => $request->quotation_site_id,
                    'position_id' => $request->position_id,
                    'jumlah_hc' => $request->jumlah_hc,
                    'jabatan_kebutuhan' => $request->jabatan_kebutuhan,
                    'nama_site' => $request->nama_site,
                    'nominal_upah' => $request->nominal_upah ?? 0,
                ]
            ];

            $this->syncDetailHCFromArray($quotation, $detailData);
        }
    }


    public function updateStep4(Quotation $quotation, Request $request): void
    {
        \Log::info("Updating Step 4 per position", [
            'quotation_id' => $quotation->id,
            'position_data_count' => count($request->position_data ?? [])
        ]);

        if ($request->has('position_data') && is_array($request->position_data)) {
            foreach ($request->position_data as $positionData) {
                $this->updatePositionStep4($quotation, $positionData);
            }

            // Synchronize upah for all positions after update
            $this->updateUpahPerPosition($quotation);
        }

        $quotation->update([
            'updated_by' => Auth::user()->full_name
        ]);
    }
    public function updateStep5(Quotation $quotation, Request $request): void
    {
        // Update BPJS data untuk setiap detail (position)
        foreach ($quotation->quotationDetails as $detail) {
            $detailId = $detail->id;
            $penjamin = $request->penjamin[$detailId] ?? null;

            // Tentukan nominal_takaful berdasarkan penjamin
            $nominalTakaful = 0;
            if ($penjamin === 'Asuransi Swasta' || $penjamin === 'Takaful') {
                $nominalTakaful = $request->nominal_takaful[$detailId] ?? 0;
                // Convert string to integer jika diperlukan
                if (is_string($nominalTakaful)) {
                    $nominalTakaful = (int) str_replace('.', '', $nominalTakaful);
                }
            }

            $detail->update([
                'penjamin_kesehatan' => $penjamin,
                'is_bpjs_jkk' => $this->toBoolean($request->jkk[$detailId] ?? false) ? 1 : 0,
                'is_bpjs_jkm' => $this->toBoolean($request->jkm[$detailId] ?? false) ? 1 : 0,
                'is_bpjs_jht' => $this->toBoolean($request->jht[$detailId] ?? false) ? 1 : 0,
                'is_bpjs_jp' => $this->toBoolean($request->jp[$detailId] ?? false) ? 1 : 0,
                'nominal_takaful' => $nominalTakaful, // Tambahkan field ini
                'updated_by' => Auth::user()->full_name
            ]);
        }

        $companyData = $this->prepareCompanyData($request);

        $quotation->update(array_merge([
            'is_aktif' => $this->calculateIsAktif($quotation, $request),
            'program_bpjs' => $request->input('program-bpjs'),
            'updated_by' => Auth::user()->full_name
        ], $companyData));

        // Update leads data
        if ($quotation->leads) {
            $quotation->leads->update($companyData);
        }
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
        $barangData = [];

        if ($request->has('kaporlaps') && is_array($request->kaporlaps)) {
            $barangData = $request->kaporlaps;
        } else {
            $barangData = $this->quotationBarangService->processLegacyFormat($quotation, $request, 'kaporlap');
        }

        // Selakukan sync meskipun barangData kosong (untuk menghapus semua data)
        $this->quotationBarangService->syncBarangData($quotation, 'kaporlap', $barangData);

        $quotation->update([
            'updated_by' => Auth::user()->full_name
        ]);
    }

    public function updateStep8(Quotation $quotation, Request $request): void
    {
        $barangData = [];

        if ($request->has('devices') && is_array($request->devices)) {
            $barangData = $request->devices;
        } else {
            $barangData = $this->quotationBarangService->processLegacyFormat($quotation, $request, 'devices');
        }

        $this->quotationBarangService->syncBarangData($quotation, 'devices', $barangData);

        $quotation->update([
            'updated_by' => Auth::user()->full_name
        ]);
    }

    public function updateStep9(Quotation $quotation, Request $request): void
    {
        $barangData = [];

        if ($request->has('chemicals') && is_array($request->chemicals)) {
            $barangData = $request->chemicals;
        } elseif ($request->has('barang_id') && $request->has('jumlah')) {
            $barangData = [
                [
                    'barang_id' => $request->barang_id,
                    'jumlah' => $request->jumlah,
                    'masa_pakai' => $request->masa_pakai,
                    'harga' => $request->harga
                ]
            ];
        } else {
            $barangData = $this->quotationBarangService->processLegacyFormat($quotation, $request, 'chemicals');
        }

        $this->quotationBarangService->syncBarangData($quotation, 'chemicals', $barangData);

        $quotation->update([
            'updated_by' => Auth::user()->full_name
        ]);
    }

    public function updateStep10(Quotation $quotation, Request $request): void
    {
        $barangData = [];

        if ($request->has('ohcs') && is_array($request->ohcs)) {
            $barangData = $request->ohcs;
        } else {
            $barangData = $this->quotationBarangService->processLegacyFormat($quotation, $request, 'ohc');
        }

        $this->quotationBarangService->syncBarangData($quotation, 'ohc', $barangData);

        // Update training data
        $this->updateTrainingData($quotation, $request, Carbon::now());

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

    private function calculateUpahForPosition(QuotationDetail $detail, array $positionData): array
    {
        $nominalUpah = $detail->nominal_upah;
        $hitunganUpah = "Per Bulan";

        if (($positionData['upah'] ?? null) == "Custom") {
            $hitunganUpah = $positionData['hitungan_upah'] ?? "Per Bulan";
            $customUpah = isset($positionData['custom_upah']) ? str_replace('.', '', $positionData['custom_upah']) : 0;

            if ($hitunganUpah == "Per Hari") {
                $customUpah = $customUpah * 21;
            } else if ($hitunganUpah == "Per Jam") {
                $customUpah = $customUpah * 21 * 8;
            }

            $nominalUpah = $customUpah;
        } else {
            $site = QuotationSite::find($detail->quotation_site_id);
            if ($site) {
                if (($positionData['upah'] ?? null) == "UMP") {
                    $dataUmp = Ump::byProvince($site->provinsi_id)
                        ->active()
                        ->first();
                    $nominalUpah = $dataUmp ? $dataUmp->ump : 0;
                } else if (($positionData['upah'] ?? null) == "UMK") {
                    $dataUmk = Umk::byCity($site->kota_id)
                        ->active()
                        ->first();
                    $nominalUpah = $dataUmk ? $dataUmk->umk : 0;
                }

                // Update site dengan nilai yang sesuai
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
        // Gunakan input() untuk field dengan dash
        $jenisPerusahaanId = $request->input('jenis-perusahaan');
        $bidangPerusahaanId = $request->input('bidang-perusahaan');

        $data = [
            'jenis_perusahaan_id' => $jenisPerusahaanId,
            'bidang_perusahaan_id' => $bidangPerusahaanId,
            'resiko' => $request->input('resiko')
        ];

        \Log::info('Preparing company data', [
            'jenis_perusahaan_id' => $jenisPerusahaanId,
            'bidang_perusahaan_id' => $bidangPerusahaanId,
            'resiko' => $request->input('resiko')
        ]);

        // Get nama jenis perusahaan
        if ($jenisPerusahaanId) {
            $jenisPerusahaan = JenisPerusahaan::where('id', $jenisPerusahaanId)->first();
            $data['jenis_perusahaan'] = $jenisPerusahaan ? $jenisPerusahaan->nama : null;
        }

        // Get nama bidang perusahaan
        if ($bidangPerusahaanId) {
            $bidangPerusahaan = BidangPerusahaan::where('id', $bidangPerusahaanId)->first();
            $data['bidang_perusahaan'] = $bidangPerusahaan ? $bidangPerusahaan->nama : null;
        }

        return $data;
    }

    private function calculateIsAktif(Quotation $quotation, Request $request): int
    {
        $isAktif = $quotation->is_aktif;

        \Log::info('Calculate is_aktif', [
            'current_is_aktif' => $isAktif,
            'new_is_aktif' => ($isAktif == 2) ? 1 : $isAktif
        ]);

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
    public function syncDetailHCFromArray(Quotation $quotation, array $details)
    {
        try {
            DB::beginTransaction();

            $current_date_time = Carbon::now()->toDateTimeString();
            $user = Auth::user()->full_name;

            // Ambil semua detail ID lama
            $existingDetails = QuotationDetail::where('quotation_id', $quotation->id)
                ->whereNull('deleted_at')
                ->pluck('id', 'position_id'); // key = position_id biar gampang dicocokkan

            $incomingPositionIds = collect($details)->pluck('position_id')->toArray();

            // Soft delete yang tidak dikirim lagi
            $toDelete = $existingDetails->keys()->diff($incomingPositionIds);
            if ($toDelete->isNotEmpty()) {
                $deletedDetails = QuotationDetail::where('quotation_id', $quotation->id)
                    ->whereIn('position_id', $toDelete)
                    ->get();

                foreach ($deletedDetails as $detail) {
                    $detail->update([
                        'deleted_at' => $current_date_time,
                        'deleted_by' => $user
                    ]);

                    QuotationDetailHpp::where('quotation_detail_id', $detail->id)->update([
                        'deleted_at' => $current_date_time,
                        'deleted_by' => $user
                    ]);
                    QuotationDetailCoss::where('quotation_detail_id', $detail->id)->update([
                        'deleted_at' => $current_date_time,
                        'deleted_by' => $user
                    ]);
                    QuotationDetailTunjangan::where('quotation_detail_id', $detail->id)->update([
                        'deleted_at' => $current_date_time,
                        'deleted_by' => $user
                    ]);
                    DB::table('sl_quotation_detail_requirement')
                        ->where('quotation_detail_id', $detail->id)
                        ->update([
                            'deleted_at' => $current_date_time,
                            'deleted_by' => $user
                        ]);
                }
            }

            // === Masuk ke loop data baru ===
            foreach ($details as $detail) {
                $this->addDetailHCFromArray($quotation, $detail); // pakai fungsi yang sudah kamu buat
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error in syncDetailHCFromArray: " . $e->getMessage());
            throw new \Exception("Failed to sync HC details: " . $e->getMessage());
        }
    }

    public function addDetailHCFromArray(Quotation $quotation, array $detail)
    {
        try {
            DB::beginTransaction();

            $current_date_time = Carbon::now()->toDateTimeString();

            // Get position
            $position = Position::where('id', $detail['position_id'])->first();

            $quotationSite = QuotationSite::where('quotation_id', $quotation->id)
                ->where('id', $detail['quotation_site_id'])
                ->first();

            // Check if data already exists
            $checkExist = QuotationDetail::where('quotation_id', $quotation->id)
                ->where('position_id', $detail['position_id'])
                ->where('quotation_site_id', $detail['quotation_site_id'])
                ->whereNull('deleted_at')
                ->first();

            if ($checkExist != null) {
                // Update existing
                $checkExist->update([
                    'jumlah_hc' => $detail['jumlah_hc'], // Gunakan value dari array, bukan increment
                    'updated_at' => $current_date_time,
                    'updated_by' => Auth::user()->full_name
                ]);

                // Also update HPP and COSS
                QuotationDetailHpp::where('quotation_detail_id', $checkExist->id)
                    ->update([
                        'jumlah_hc' => $detail['jumlah_hc'],
                        'updated_at' => $current_date_time,
                        'updated_by' => Auth::user()->full_name
                    ]);

                QuotationDetailCoss::where('quotation_detail_id', $checkExist->id)
                    ->update([
                        'jumlah_hc' => $detail['jumlah_hc'],
                        'updated_at' => $current_date_time,
                        'updated_by' => Auth::user()->full_name
                    ]);

            } else {
                // Create new quotation detail
                $detailBaru = QuotationDetail::create([
                    'quotation_id' => $quotation->id,
                    'quotation_site_id' => $detail['quotation_site_id'],
                    'nama_site' => $detail['nama_site'],
                    'position_id' => $detail['position_id'],
                    'jabatan_kebutuhan' => $detail['jabatan_kebutuhan'],
                    'jumlah_hc' => $detail['jumlah_hc'],
                    'nominal_upah' => $detail['nominal_upah'] ?? 0,
                    'created_at' => $current_date_time,
                    'created_by' => Auth::user()->full_name
                ]);

                // Create HPP record
                QuotationDetailHpp::create([
                    'quotation_id' => $quotation->id,
                    'quotation_detail_id' => $detailBaru->id,
                    'leads_id' => $quotation->leads_id,
                    'position_id' => $detail['position_id'],
                    'jumlah_hc' => $detail['jumlah_hc'],
                    'created_at' => $current_date_time,
                    'created_by' => Auth::user()->full_name
                ]);

                // Create COSS record
                QuotationDetailCoss::create([
                    'quotation_id' => $quotation->id,
                    'quotation_detail_id' => $detailBaru->id,
                    'leads_id' => $quotation->leads_id,
                    'position_id' => $detail['position_id'],
                    'jumlah_hc' => $detail['jumlah_hc'],
                    'created_at' => $current_date_time,
                    'created_by' => Auth::user()->full_name
                ]);

                // Insert tunjangan berdasarkan position (jika ada di array)
                if (isset($detail['tunjangans']) && is_array($detail['tunjangans'])) {
                    foreach ($detail['tunjangans'] as $tunjangan) {
                        QuotationDetailTunjangan::create([
                            'quotation_id' => $quotation->id,
                            'quotation_detail_id' => $detailBaru->id,
                            'nama_tunjangan' => $tunjangan['nama_tunjangan'] ?? '',
                            'nominal' => $tunjangan['nominal'] ?? 0,
                            'created_at' => $current_date_time,
                            'created_by' => Auth::user()->full_name
                        ]);
                    }
                }

                // Insert requirements berdasarkan position (jika ada di array)
                if (isset($detail['requirements']) && is_array($detail['requirements'])) {
                    foreach ($detail['requirements'] as $requirement) {
                        // Sesuaikan dengan model requirements Anda
                        DB::table('sl_quotation_detail_requirement')->insert([
                            'quotation_id' => $quotation->id,
                            'quotation_detail_id' => $detailBaru->id,
                            'requirement' => $requirement,
                            'created_at' => $current_date_time,
                            'created_by' => Auth::user()->full_name
                        ]);
                    }
                }
            }

            DB::commit();
            \Log::info("HC detail added from array", [
                'quotation_id' => $quotation->id,
                'position_id' => $detail['position_id'],
                'site_id' => $detail['quotation_site_id'],
                'jumlah_hc' => $detail['jumlah_hc']
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error in addDetailHCFromArray: " . $e->getMessage());
            throw new \Exception("Failed to add HC detail from array: " . $e->getMessage());
        }
    }
    /**
     * Update nominal upah untuk setiap position berdasarkan site yang terkait
     */
    private function updateUpahPerPosition(Quotation $quotation): void
    {
        try {
            // Ambil semua quotation details dengan relasi site dan wage
            $quotationDetails = QuotationDetail::with(['quotationSite', 'wage'])
                ->where('quotation_id', $quotation->id)
                ->get();

            foreach ($quotationDetails as $detail) {
                // Jika detail memiliki quotation site, gunakan nominal_upah dari site tersebut
                if ($detail->quotationSite) {
                    $newNominalUpah = $detail->quotationSite->nominal_upah;

                    // Update nominal_upah di quotation_detail
                    $detail->update([
                        'nominal_upah' => $newNominalUpah,
                        'updated_by' => Auth::user()->full_name
                    ]);

                    // Jika ada data wage, update juga hitungan_upah jika diperlukan
                    if ($detail->wage) {
                        // Jika upah type adalah UMP/UMK, update hitungan_upah ke "Per Bulan"
                        if (in_array($detail->wage->upah, ['UMP', 'UMK'])) {
                            $detail->wage->update([
                                'hitungan_upah' => 'Per Bulan',
                                'updated_by' => Auth::user()->full_name
                            ]);
                        }
                    }

                    \Log::info("Updated upah for position", [
                        'detail_id' => $detail->id,
                        'position_id' => $detail->position_id,
                        'site_id' => $detail->quotation_site_id,
                        'nominal_upah' => $newNominalUpah,
                        'upah_type' => $detail->wage->upah ?? 'N/A'
                    ]);
                }
            }

            \Log::info("Successfully updated upah for all positions per site", [
                'quotation_id' => $quotation->id,
                'total_details_updated' => $quotationDetails->count()
            ]);

        } catch (\Exception $e) {
            \Log::error("Error updating upah per position", [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to update upah per position: " . $e->getMessage());
        }
    }

    private function updatePositionStep4(Quotation $quotation, array $positionData): void
    {
        $detail = QuotationDetail::where('id', $positionData['quotation_detail_id'])
            ->where('quotation_id', $quotation->id)
            ->first();

        if (!$detail) {
            \Log::warning("Quotation detail not found", [
                'quotation_detail_id' => $positionData['quotation_detail_id'],
                'quotation_id' => $quotation->id
            ]);
            return;
        }

        // Calculate upah data untuk position ini
        $upahData = $this->calculateUpahForPosition($detail, $positionData);

        // Data untuk wage table - semua field sebagai string
        $wageData = [
            'quotation_id' => $quotation->id,
            'upah' => $positionData['upah'] ?? null,
            'hitungan_upah' => $upahData['hitungan_upah'] ?? null,
            'management_fee_id' => $positionData['manajemen_fee'] ?? null,
            'persentase' => $positionData['persentase'] ?? null,
            'lembur' => $positionData['lembur'] ?? null,
            'nominal_lembur' => isset($positionData['nominal_lembur']) ? str_replace('.', '', $positionData['nominal_lembur']) : null,
            'jenis_bayar_lembur' => $positionData['jenis_bayar_lembur'] ?? null,
            'jam_per_bulan_lembur' => $positionData['jam_per_bulan_lembur'] ?? null,
            'lembur_ditagihkan' => $positionData['lembur_ditagihkan'] ?? null,
            'kompensasi' => $positionData['kompensasi'] ?? null,
            'thr' => $positionData['thr'] ?? null,
            'tunjangan_holiday' => $positionData['tunjangan_holiday'] ?? null,
            'nominal_tunjangan_holiday' => isset($positionData['nominal_tunjangan_holiday']) ? str_replace('.', '', $positionData['nominal_tunjangan_holiday']) : null,
            'jenis_bayar_tunjangan_holiday' => $positionData['jenis_bayar_tunjangan_holiday'] ?? null,
            'is_ppn' => $positionData['is_ppn'] ?? null,
            'ppn_pph_dipotong' => $positionData['ppn_pph_dipotong'] ?? null,
            'updated_by' => Auth::user()->full_name
        ];

        // Update atau create wage data
        if ($detail->wage) {
            $detail->wage->update($wageData);
        } else {
            $wageData['quotation_detail_id'] = $detail->id;
            $wageData['created_by'] = Auth::user()->full_name;
            QuotationDetailWage::create($wageData);
        }

        // Update nominal_upah di quotation_detail (akan di-sync lagi oleh updateUpahPerPosition)
        $detail->update([
            'nominal_upah' => $upahData['nominal_upah'],
            'updated_by' => Auth::user()->full_name
        ]);

        \Log::info("Updated step 4 wage for position", [
            'quotation_detail_id' => $detail->id,
            'position_id' => $detail->position_id,
            'upah' => $positionData['upah'] ?? 'null'
        ]);
    }
    /**
     * Helper method to convert various boolean representations to proper boolean
     */
    private function toBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['true', '1', 'yes', 'on']);
        }

        return (bool) $value;
    }
    /**
     * Method untuk sync single chemical - TANPA quotation_detail_id
     */
    private function syncChemicalData(Quotation $quotation, Request $request, Carbon $currentDateTime): void
    {
        \Log::info("Starting single chemical data sync - WITHOUT DETAIL ID");

        // Validasi barang_id
        if (!$request->has('barang_id')) {
            \Log::warning("Request missing 'barang_id' field");
            return;
        }

        $barang_id = (int) $request->barang_id;
        $jumlah = (int) ($request->jumlah ?? 0);

        // Skip jika jumlah 0
        if ($jumlah <= 0) {
            \Log::info("Skipping chemical with zero quantity", [
                'barang_id' => $barang_id,
                'jumlah' => $jumlah
            ]);
            return;
        }

        // Cari barang
        $barang = Barang::where('id', $barang_id)
            ->whereIn('jenis_barang_id', [13, 14, 15, 16, 18, 19])
            ->first();

        if (!$barang) {
            \Log::warning("Barang not found", ['barang_id' => $barang_id]);
            return;
        }

        // Parse data
        $masa_pakai = $request->masa_pakai ? (int) $request->masa_pakai : ($barang->masa_pakai ?? 12);

        $harga = $barang->harga;
        if ($request->has('harga')) {
            if (is_string($request->harga)) {
                $harga = (float) str_replace(['.', ','], ['', '.'], $request->harga);
            } else {
                $harga = (float) $request->harga;
            }
        }

        // Cari data existing - TANPA quotation_detail_id
        $existingChemical = QuotationChemical::where('quotation_id', $quotation->id)
            ->where('barang_id', $barang_id)
            ->whereNull('quotation_detail_id') // Hanya cari yang global
            ->first();

        if ($existingChemical) {
            // UPDATE data existing
            $existingChemical->update([
                'jumlah' => $jumlah,
                'harga' => $harga,
                'masa_pakai' => $masa_pakai,
                'updated_by' => Auth::user()->full_name
            ]);

            \Log::info("Updated existing chemical", [
                'chemical_id' => $existingChemical->id,
                'barang_id' => $barang_id,
                'jumlah' => $jumlah
            ]);
        } else {
            // CREATE data baru - TANPA quotation_detail_id
            $newChemical = QuotationChemical::create([
                'quotation_id' => $quotation->id,
                'barang_id' => $barang_id,
                'jumlah' => $jumlah,
                'harga' => $harga,
                'nama' => $barang->nama,
                'jenis_barang_id' => $barang->jenis_barang_id,
                'jenis_barang' => $barang->jenis_barang,
                'masa_pakai' => $masa_pakai,
                'created_by' => Auth::user()->full_name
                // TIDAK ADA quotation_detail_id
            ]);

            \Log::info("Created new chemical", [
                'chemical_id' => $newChemical->id,
                'barang_id' => $barang_id,
                'barang_nama' => $barang->nama,
                'jumlah' => $jumlah
            ]);
        }
    }

    /**
     * Method untuk sync multiple chemicals - TANPA quotation_detail_id
     */
    private function syncMultipleChemicalData(Quotation $quotation, array $chemicals, Carbon $currentDateTime): void
    {
        \Log::info("Starting multiple chemical data sync - FIXED UPDATE/INSERT", [
            'chemicals_count' => count($chemicals),
            'quotation_id' => $quotation->id
        ]);
        $chemicalBarangIds = collect($chemicals)->pluck('barang_id')->filter()->toArray();

        // Soft delete data yang tidak lagi dikirim
        QuotationChemical::where('quotation_id', $quotation->id)
            ->whereNotIn('barang_id', $chemicalBarangIds)
            ->update([
                'deleted_at' => now(),
                'deleted_by' => Auth::user()->full_name,
            ]);

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($chemicals as $index => $chemicalData) {
            \Log::debug("Processing chemical #{$index}", $chemicalData);

            // Validasi data minimal
            if (!isset($chemicalData['barang_id']) || !isset($chemicalData['jumlah'])) {
                \Log::warning("Chemical data missing required fields", [
                    'index' => $index,
                    'data' => $chemicalData
                ]);
                $skippedCount++;
                continue;
            }

            $barang_id = (int) $chemicalData['barang_id'];
            $jumlah = isset($chemicalData['jumlah']) ? (int) $chemicalData['jumlah'] : 0;

            // Skip jika jumlah 0 atau negatif
            if ($jumlah <= 0) {
                \Log::info("Skipping chemical with zero/negative quantity", [
                    'barang_id' => $barang_id,
                    'jumlah' => $jumlah
                ]);
                $skippedCount++;
                continue;
            }

            // Cari barang
            $barang = Barang::where('id', $barang_id)
                ->whereIn('jenis_barang_id', [13, 14, 15, 16, 18, 19])
                ->first();

            if (!$barang) {
                \Log::warning("Barang not found", [
                    'barang_id' => $barang_id,
                    'chemical_index' => $index
                ]);
                $skippedCount++;
                continue;
            }

            // Parse data
            $masa_pakai = isset($chemicalData['masa_pakai']) ? (int) $chemicalData['masa_pakai'] : ($barang->masa_pakai ?? 12);

            // Handle harga
            $harga = $barang->harga;
            if (isset($chemicalData['harga'])) {
                if (is_string($chemicalData['harga'])) {
                    $harga = (float) str_replace(['.', ','], ['', '.'], $chemicalData['harga']);
                } else {
                    $harga = (float) $chemicalData['harga'];
                }
            }

            // **FIX: Cari data existing dengan query yang lebih spesifik**
            $existingChemical = QuotationChemical::where('quotation_id', $quotation->id)
                ->where('barang_id', $barang_id)
                ->first();

            \Log::debug("Existing chemical search", [
                'quotation_id' => $quotation->id,
                'barang_id' => $barang_id,
                'found' => $existingChemical ? true : false,
                'existing_id' => $existingChemical ? $existingChemical->id : null
            ]);

            if ($existingChemical) {
                // UPDATE data existing
                $existingChemical->update([
                    'jumlah' => $jumlah,
                    'harga' => $harga,
                    'masa_pakai' => $masa_pakai,
                    'updated_by' => Auth::user()->full_name
                ]);
                $updatedCount++;

                \Log::info("Updated existing chemical", [
                    'chemical_id' => $existingChemical->id,
                    'barang_id' => $barang_id,
                    'jumlah' => $jumlah,
                    'harga' => $harga
                ]);
            } else {
                // CREATE data baru
                $newChemical = QuotationChemical::create([
                    'quotation_id' => $quotation->id,
                    'barang_id' => $barang_id,
                    'jumlah' => $jumlah,
                    'harga' => $harga,
                    'nama' => $barang->nama,
                    'jenis_barang_id' => $barang->jenis_barang_id,
                    'jenis_barang' => $barang->jenis_barang,
                    'masa_pakai' => $masa_pakai,
                    'created_by' => Auth::user()->full_name
                ]);
                $createdCount++;

                \Log::info("Created new chemical", [
                    'chemical_id' => $newChemical->id,
                    'barang_id' => $barang_id,
                    'barang_nama' => $barang->nama,
                    'jumlah' => $jumlah,
                    'harga' => $harga
                ]);
            }
        }

        \Log::info("Multiple chemical sync completed - FIXED UPDATE/INSERT", [
            'quotation_id' => $quotation->id,
            'total_processed' => count($chemicals),
            'created' => $createdCount,
            'updated' => $updatedCount,
            'skipped' => $skippedCount
        ]);
    }
}