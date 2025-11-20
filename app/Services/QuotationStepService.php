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
                $data['additional_data']['training_list'] = Training::all();
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
        DB::beginTransaction();
        try {
            \Log::info('Starting updateStep2', [
                'quotation_id' => $quotation->id,
                'request_data' => $request->all()
            ]);

            $this->validateStep2($request);

            $cutiData = $this->prepareCutiData($request);

            // Ambil persentase bunga bank dari TOP
            $top = Top::find($request->top);
            $persenBungaBank = $top ? ($top->persentase ?? 0) : 0;

            $updateData = array_merge([
                'mulai_kontrak' => $request->mulai_kontrak,
                'kontrak_selesai' => $request->kontrak_selesai,
                'tgl_penempatan' => $request->tgl_penempatan,
                'salary_rule_id' => $request->salary_rule,
                'pengiriman_invoice' => $request->pengiriman_invoice,
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
            ], $cutiData);

            \Log::debug('Final data to update quotation:', $updateData);

            $quotation->update($updateData);

            DB::commit();

            \Log::info('Step 2 updated successfully', [
                'quotation_id' => $quotation->id,
                'cuti_data' => $cutiData
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error in updateStep2", [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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

        if ($request->has('position_data') && is_array($request->position_data)) {
            // UPDATE QUOTATION GLOBAL DATA DARI POSITION PERTAMA
            if (count($request->position_data) > 0) {
                $firstPosition = $request->position_data[0];

                $quotation->update([
                    'is_ppn' => $firstPosition['is_ppn'] ?? null,
                    'ppn_pph_dipotong' => $firstPosition['ppn_pph_dipotong'] ?? null,
                    'management_fee_id' => $firstPosition['manajemen_fee'] ?? null,
                    'persentase' => $firstPosition['persentase'] ?? null,
                    'updated_by' => Auth::user()->full_name
                ]);
            }

            // Update setiap position (TANPA 4 kolom global)
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
        DB::beginTransaction();
        try {
            $barangData = [];

            if ($request->has('kaporlaps') && is_array($request->kaporlaps)) {
                $barangData = $request->kaporlaps;
            } else {
                $barangData = $this->quotationBarangService->processLegacyFormat($quotation, $request, 'kaporlap');
            }

            // Sync barang data
            $syncResult = $this->quotationBarangService->syncBarangData($quotation, 'kaporlap', $barangData);

            \Log::info("Kaporlap Sync Result", $syncResult);

            $quotation->update([
                'updated_by' => Auth::user()->full_name
            ]);

            DB::commit(); // ✅ TAMBAHKAN INI

            \Log::info("Step 7 updated successfully", [
                'quotation_id' => $quotation->id,
                'kaporlap_items' => count($barangData)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error updating step 7", [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    public function updateStep8(Quotation $quotation, Request $request): void
    {
        DB::beginTransaction();
        try {
            $barangData = [];

            if ($request->has('devices') && is_array($request->devices)) {
                $barangData = $request->devices;
            } else {
                $barangData = $this->quotationBarangService->processLegacyFormat($quotation, $request, 'devices');
            }

            // Sync barang data
            $syncResult = $this->quotationBarangService->syncBarangData($quotation, 'devices', $barangData);

            \Log::info("Devices Sync Result", $syncResult);

            $quotation->update([
                'updated_by' => Auth::user()->full_name
            ]);

            DB::commit(); // ✅ TAMBAHKAN INI

            \Log::info("Step 8 updated successfully", [
                'quotation_id' => $quotation->id,
                'devices_items' => count($barangData)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error updating step 8", [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    public function updateStep9(Quotation $quotation, Request $request): void
    {
        DB::beginTransaction();
        try {
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

            // Sync barang data
            $syncResult = $this->quotationBarangService->syncBarangData($quotation, 'chemicals', $barangData);

            \Log::info("Chemicals Sync Result", $syncResult);

            $quotation->update([
                'updated_by' => Auth::user()->full_name
            ]);

            DB::commit(); // ✅ TAMBAHKAN INI

            \Log::info("Step 9 updated successfully", [
                'quotation_id' => $quotation->id,
                'chemical_items' => count($barangData)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error updating step 9", [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    public function updateStep10(Quotation $quotation, Request $request): void
    {
        DB::beginTransaction();

        try {
            $barangData = [];

            if ($request->has('ohcs') && is_array($request->ohcs)) {
                $barangData = $request->ohcs;
            } else {
                $barangData = $this->quotationBarangService->processLegacyFormat($quotation, $request, 'ohc');
            }

            // Sync barang data dengan approach baru
            $syncResult = $this->quotationBarangService->syncBarangData($quotation, 'ohc', $barangData);

            \Log::info("OHC Sync Result", $syncResult);

            // Update training data
            $this->updateTrainingData($quotation, $request, Carbon::now());

            // Update data kunjungan
            $quotation->update([
                'kunjungan_operasional' => $request->jumlah_kunjungan_operasional . " " . $request->bulan_tahun_kunjungan_operasional,
                'kunjungan_tim_crm' => $request->jumlah_kunjungan_tim_crm . " " . $request->bulan_tahun_kunjungan_tim_crm,
                'keterangan_kunjungan_operasional' => $request->keterangan_kunjungan_operasional,
                'keterangan_kunjungan_tim_crm' => $request->keterangan_kunjungan_tim_crm,
                'training' => $request->training,
                'persen_bunga_bank' => $request->persen_bunga_bank ?: 1.3,
                'updated_by' => Auth::user()->full_name
            ]);

            DB::commit();

            \Log::info("Step 10 updated successfully", [
                'quotation_id' => $quotation->id,
                'ohc_items' => count($barangData),
                'training' => $request->training
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error updating step 10", [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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
        DB::beginTransaction();
        try {
            $currentDateTime = Carbon::now();
            $user = Auth::user()->full_name;

            $statusData = $this->calculateFinalStatus($quotation);

            // Update quotation status
            $quotation->update(array_merge([
                'step' => 100,
                'updated_by' => $user
            ], $statusData));

            // Update kerjasama data - menggunakan pendekatan pengecekan seperti training
            $this->updateKerjasamaData($quotation, $request, $currentDateTime);

            // Insert requirements jika belum ada
            $this->insertRequirements($quotation);

            DB::commit();

            \Log::info("Step 12 completed successfully", [
                'quotation_id' => $quotation->id,
                'final_status' => $statusData,
                'step' => 100
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error in updateStep12", [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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
                } else {
                    $data['prorate'] = $request->prorate;
                }
                $data['gaji_saat_cuti'] = $request->gaji_saat_cuti;
            } else {
                $data['gaji_saat_cuti'] = $request->gaji_saat_cuti;
                $data['prorate'] = $request->prorate;
            }

            // $data['hari_cuti_kematian'] = in_array("Cuti Kematian", $cuti) ? $request->hari_cuti_kematian : null;
            // $data['hari_istri_melahirkan'] = in_array("Istri Melahirkan", $cuti) ? $request->hari_istri_melahirkan : null;
            // $data['hari_cuti_menikah'] = in_array("Cuti Menikah", $cuti) ? $request->hari_cuti_menikah : null;

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

                }
            }


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

        // Data untuk wage table - TANPA 4 KOLOM GLOBAL
        $wageData = [
            'quotation_id' => $quotation->id,
            'upah' => $positionData['upah'] ?? null,
            'hitungan_upah' => $upahData['hitungan_upah'] ?? null,
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
            'updated_by' => Auth::user()->full_name
            // HAPUS 4 KOLOM GLOBAL:
            // 'is_ppn', 'ppn_pph_dipotong', 'management_fee_id', 'persentase'
        ];

        // Update atau create wage data
        if ($detail->wage) {
            $detail->wage->update($wageData);
        } else {
            $wageData['quotation_detail_id'] = $detail->id;
            $wageData['created_by'] = Auth::user()->full_name;
            QuotationDetailWage::create($wageData);
        }

        // Update nominal_upah di quotation_detail
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
     * Manage quotation kerjasama (create, update, delete) - menggunakan pendekatan pengecekan seperti updateTrainingData
     */
    public function updateKerjasamaData(Quotation $quotation, Request $request, Carbon $currentDateTime): void
    {
        $user = Auth::user()->full_name;

        // Jika ada data kerjasama dari request, sync dengan database
        if ($request->has('quotation_kerjasamas') && is_array($request->quotation_kerjasamas)) {
            $this->syncKerjasamaData($quotation, $request->quotation_kerjasamas, $currentDateTime, $user);
        }
        // Jika tidak ada data kerjasama di request, hapus semua yang existing (soft delete)
        else {
            QuotationKerjasama::where('quotation_id', $quotation->id)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => $currentDateTime,
                    'deleted_by' => $user
                ]);
        }
    }

    /**
     * Sync kerjasama data - mirip dengan updateTrainingData
     */
    private function syncKerjasamaData(Quotation $quotation, array $kerjasamas, Carbon $currentDateTime, string $user): void
    {
        \Log::info("Starting kerjasama data sync", [
            'quotation_id' => $quotation->id,
            'kerjasamas_count' => count($kerjasamas)
        ]);

        // Get existing kerjasama IDs untuk quotation ini
        $existingKerjasamaIds = QuotationKerjasama::where('quotation_id', $quotation->id)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        $incomingKerjasamaIds = [];
        $createdCount = 0;
        $updatedCount = 0;
        $deletedCount = 0;

        foreach ($kerjasamas as $kerjasamaData) {
            // Skip jika perjanjian kosong
            if (empty(trim($kerjasamaData['perjanjian'] ?? ''))) {
                continue;
            }

            $kerjasamaId = $kerjasamaData['id'] ?? null;
            $perjanjian = trim($kerjasamaData['perjanjian']);
            $isDelete = $kerjasamaData['is_delete'] ?? 1;

            // Jika ada ID, update existing
            if ($kerjasamaId && in_array($kerjasamaId, $existingKerjasamaIds)) {
                $kerjasama = QuotationKerjasama::find($kerjasamaId);
                if ($kerjasama) {
                    $kerjasama->update([
                        'perjanjian' => $perjanjian,
                        'is_delete' => $isDelete,
                        'updated_at' => $currentDateTime,
                        'updated_by' => $user
                    ]);
                    $updatedCount++;
                }

                $incomingKerjasamaIds[] = $kerjasamaId;
            }
            // Jika tidak ada ID, create baru
            else {
                QuotationKerjasama::create([
                    'quotation_id' => $quotation->id,
                    'perjanjian' => $perjanjian,
                    'is_delete' => $isDelete,
                    'created_at' => $currentDateTime,
                    'created_by' => $user
                ]);
                $createdCount++;
            }
        }

        // Soft delete kerjasama yang tidak ada dalam incoming data tapi masih ada di database
        $toDeleteIds = array_diff($existingKerjasamaIds, $incomingKerjasamaIds);
        if (!empty($toDeleteIds)) {
            QuotationKerjasama::whereIn('id', $toDeleteIds)
                ->update([
                    'deleted_at' => $currentDateTime,
                    'deleted_by' => $user
                ]);
            $deletedCount = count($toDeleteIds);
        }

        \Log::info("Kerjasama data sync completed", [
            'quotation_id' => $quotation->id,
            'created' => $createdCount,
            'updated' => $updatedCount,
            'deleted' => $deletedCount
        ]);
    }
}