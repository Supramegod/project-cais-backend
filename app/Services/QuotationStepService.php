<?php

namespace App\Services;

use App\DTO\DetailCalculation;
use App\DTO\QuotationCalculationResult;
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
use App\Models\QuotationDetailRequirement;
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

                // Ambil data HPP untuk setiap detail untuk memastikan kompensasi ada
                $data['additional_data']['hpp_details'] = [];
                foreach ($quotation->quotationDetails as $detail) {
                    $hpp = QuotationDetailHpp::where('quotation_detail_id', $detail->id)->first();
                    $data['additional_data']['hpp_details'][$detail->id] = [
                        'tunjangan_hari_raya' => $hpp->tunjangan_hari_raya ?? 0,
                        'kompensasi' => $hpp->kompensasi ?? 0,
                        'insentif' => $hpp->insentif ?? 0
                    ];
                }

                // ✅ PASTIKAN: Data tunjangan diambil dengan relasi
                $data['additional_data']['daftar_tunjangan'] = QuotationDetailTunjangan::distinctTunjanganByQuotation($quotation->id);

                // Load tunjangan untuk setiap detail
                $quotation->load(['quotationDetails.quotationDetailTunjangans']);

                $data['additional_data']['training_list'] = Training::all();
                $data['additional_data']['selected_training'] = $quotation->quotationTrainings
                    ->pluck('training_id')
                    ->toArray();

                $data['additional_data']['jabatan_pic_list'] = JabatanPic::all();
                break;
            case 12:
                // ✅ STRUKTUR BARU: Include ID untuk setiap kerjasama
                $finalData = [
                    'quotation_kerjasamas' => $quotation->relationLoaded('quotationKerjasamas')
                        ? $quotation->quotationKerjasamas->map(function ($kerjasama) {
                            return [
                                'id' => $kerjasama->id,
                                'perjanjian' => $kerjasama->perjanjian,
                                'is_delete' => $kerjasama->is_delete ?? 1,
                                'created_at' => $kerjasama->created_at,
                                'created_by' => $kerjasama->created_by,
                            ];
                        })->toArray()
                        : [],
                    'final_confirmation' => true,
                ];

                $data['additional_data']['final_data'] = $finalData;
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
            $top = Top::where('nama', $request->jumlah_hari_invoice)->first();

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
        DB::beginTransaction();
        try {
            $currentDateTime = Carbon::now()->toDateTimeString();
            $user = Auth::user()->full_name;

            // ============================================================
            // DETERMINE DATA TO PROCESS
            // ============================================================

            $dataToProcess = null;

            // CASE 1: headCountData exists (bulk format)
            if ($request->has('headCountData') && is_array($request->headCountData)) {
                $dataToProcess = $request->headCountData;
            }
            // CASE 2: Legacy single data format
            elseif ($request->has('position_id') && $request->has('quotation_site_id')) {
                $dataToProcess = [
                    [
                        'quotation_site_id' => $request->quotation_site_id,
                        'position_id' => $request->position_id,
                        'jumlah_hc' => $request->jumlah_hc ?? 0,
                        'jabatan_kebutuhan' => $request->jabatan_kebutuhan ?? null,
                        'nama_site' => $request->nama_site ?? null,
                    ]
                ];
            }
            // CASE 3: No data sent - will delete all
            else {
                $dataToProcess = [];
            }

            // ============================================================
            // PROCESS DATA
            // ============================================================

            if (empty($dataToProcess)) {
                // Delete all existing details
                $this->softDeleteAllQuotationDetails($quotation, $currentDateTime, $user);
            } else {
                // Sync data (will handle create/update/delete)
                $this->syncDetailHCFromArray($quotation, $dataToProcess, $currentDateTime, $user);
            }

            // Update quotation timestamp
            $quotation->update([
                'updated_by' => $user,
                'updated_at' => $currentDateTime
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error in updateStep3", [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    public function updateStep4(Quotation $quotation, Request $request): void
    {
        DB::beginTransaction();
        try {
            \Log::info("Starting updateStep4", [
                'quotation_id' => $quotation->id,
                'has_position_data' => $request->has('position_data'),
                'has_global_data' => $request->hasAny(['is_ppn', 'ppn_pph_dipotong', 'management_fee_id', 'persentase']),
                'global_data_received' => $request->only(['is_ppn', 'ppn_pph_dipotong', 'management_fee_id', 'persentase'])
            ]);

            // ============================================================
            // UPDATE GLOBAL QUOTATION DATA (jika ada di request)
            // ============================================================
            $globalData = [];

            // Handle semua kemungkinan field global
            $globalFields = [
                'is_ppn' => 'is_ppn',
                'ppn_pph_dipotong' => 'ppn_pph_dipotong',
                'management_fee_id' => 'management_fee_id',
                'persentase' => 'persentase'
            ];

            foreach ($globalFields as $field => $requestField) {
                if ($request->filled($requestField)) {  // Gunakan filled() bukan has()
                    $globalData[$field] = $request->$requestField;
                    \Log::info("Global data found", [
                        'field' => $field,
                        'value' => $request->$requestField
                    ]);
                } else {
                    \Log::info("Global field not filled, keeping existing value", [
                        'field' => $field,
                        'request_value' => $request->$requestField ?? 'null'
                    ]);
                }
            }
            // Update global data jika ada
            if (!empty($globalData)) {
                $globalData['updated_by'] = Auth::user()->full_name;
                $quotation->update($globalData);

                \Log::info("Updated global quotation data", [
                    'quotation_id' => $quotation->id,
                    'global_data' => $globalData
                ]);
            } else {
                \Log::warning("No global data found in request for step 4");
            }

            // ============================================================
            // UPDATE POSITION DATA (jika ada)
            // ============================================================
            if ($request->has('position_data') && is_array($request->position_data)) {
                foreach ($request->position_data as $positionData) {
                    $this->updatePositionStep4($quotation, $positionData);
                }

                // Synchronize upah for all positions after update
                $this->updateUpahPerPosition($quotation);

                \Log::info("Updated position data", [
                    'quotation_id' => $quotation->id,
                    'position_count' => count($request->position_data)
                ]);
            }

            // Update quotation timestamp
            $quotation->update([
                'updated_by' => Auth::user()->full_name
            ]);

            DB::commit();

            \Log::info("Step 4 updated successfully", [
                'quotation_id' => $quotation->id,
                'global_data_updated' => !empty($globalData),
                'position_data_updated' => $request->has('position_data')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error in updateStep4", [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all() // Log semua data request untuk debugging
            ]);
            throw $e;
        }
    }
    public function updateStep5(Quotation $quotation, Request $request): void
    {
        // Update BPJS data untuk setiap detail (position)
        foreach ($quotation->quotationDetails as $detail) {
            $detailId = $detail->id;
            $penjamin = $request->penjamin[$detailId] ?? null;

            // PERBAIKAN: Pastikan nilai penjamin kesehatan konsisten
            $nominalTakaful = 0;
            if ($penjamin === 'Asuransi Swasta' || $penjamin === 'Takaful') {
                $nominalTakaful = $request->nominal_takaful[$detailId] ?? 0;
                // Convert string to integer jika diperlukan
                if (is_string($nominalTakaful)) {
                    $nominalTakaful = (int) str_replace('.', '', $nominalTakaful);
                }

                \Log::info("Setting Takaful for detail", [
                    'detail_id' => $detailId,
                    'penjamin' => $penjamin,
                    'nominal_takaful' => $nominalTakaful
                ]);
            }

            // PERBAIKAN: Pastikan nilai "BPJS" dan "BPJS Kesehatan" konsisten
            if ($penjamin === 'BPJS Kesehatan') {
                $penjamin = 'BPJS'; // Standardize to "BPJS"
            }

            // PERBAIKAN: Tambahkan field is_bpjs_kes untuk opt-out BPJS Kesehatan
            $detail->update([
                'penjamin_kesehatan' => $penjamin,
                'is_bpjs_jkk' => $this->toBoolean($request->jkk[$detailId] ?? false) ? 1 : 0,
                'is_bpjs_jkm' => $this->toBoolean($request->jkm[$detailId] ?? false) ? 1 : 0,
                'is_bpjs_jht' => $this->toBoolean($request->jht[$detailId] ?? false) ? 1 : 0,
                'is_bpjs_jp' => $this->toBoolean($request->jp[$detailId] ?? false) ? 1 : 0,
                'is_bpjs_kes' => $this->toBoolean($request->kes[$detailId] ?? true) ? 1 : 0, // Default true
                'nominal_takaful' => $nominalTakaful,
                'updated_by' => Auth::user()->full_name
            ]);

            \Log::info("Updated BPJS data for detail", [
                'detail_id' => $detailId,
                'penjamin' => $penjamin,
                'nominal_takaful' => $nominalTakaful
            ]);
        }

        $companyData = $this->prepareCompanyData($request);

        // TAMBAHKAN: Logika untuk note harga jual
        $quotationUpdateData = array_merge([
            'is_aktif' => $this->calculateIsAktif($quotation, $request),
            'program_bpjs' => $request->input('program-bpjs'),
            'updated_by' => Auth::user()->full_name
        ], $companyData);

        // Jika note_harga_jual masih null, tambahkan note default
        if (is_null($quotation->note_harga_jual)) {
            $note = '
              <b>Upah pokok base on Umk ' . Carbon::now()->year . ' </b> <br>
Tunjangan overtime flat total 75 jam. <span class="text-danger">*jika system jam kerja 12 jam </span> <br>
Tunjangan hari raya ditagihkan provisi setiap bulan. (upah/12) <br>
BPJS Ketenagakerjaan 4 Program (JKK, JKM, JHT, JP).
<span class="text-danger">Pengalian base on upah</span>		<br>
BPJS Kesehatan. <span class="text-danger">*base on Umk ' . Carbon::now()->year . '</span> <br>
<br>
<span class="text-danger">*prosentase Bpjs Tk J. Kecelakaan Kerja disesuaikan dengan tingkat resiko sesuai ketentuan.</span>';

            $quotationUpdateData['note_harga_jual'] = $note;

            \Log::info("Adding note_harga_jual to quotation", [
                'quotation_id' => $quotation->id,
                'year' => Carbon::now()->year
            ]);
        } else {
            \Log::info("note_harga_jual already exists for quotation", [
                'quotation_id' => $quotation->id
            ]);
        }

        $quotation->update($quotationUpdateData);

        // Update leads data
        if ($quotation->leads) {
            $quotation->leads->update($companyData);
        }

        \Log::info("Step 5 completed with BPJS data", [
            'quotation_id' => $quotation->id,
            'program_bpjs' => $request->input('program-bpjs'),
            'note_harga_jual_added' => is_null($quotation->note_harga_jual) ? 'yes' : 'already_exists'
        ]);
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

            // PERBAIKAN: Handle training data dari quotation_trainings
            if ($request->has('quotation_trainings') && is_array($request->quotation_trainings)) {
                $this->updateTrainingDataFromArray($quotation, $request->quotation_trainings, Carbon::now());
            } else {
                // Jika tidak ada training data, hapus semua training yang ada
                $this->clearAllTrainingData($quotation, Carbon::now());
            }

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
                'training_count' => $request->has('quotation_trainings') ? count($request->quotation_trainings) : 0
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
        // Panggil method utama yang menangani semua update
        $this->updateAllQuotationData($quotation, $request);
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
        }

        return $data;
    }
    private function calculateUpahForPosition(QuotationDetail $detail, array $positionData): array
    {
        $nominalUpah = $detail->nominal_upah;
        $hitunganUpah = "Per Bulan";

        if (($positionData['upah'] ?? null) == "Custom") {
            $hitunganUpah = $positionData['hitungan_upah'] ?? "Per Bulan";
            $customUpah = $positionData['nominal_upah'] ?? 0; // AMBIL DARI nominal_upah BUKAN custom_upah

            // Jika nominal_upah adalah string dengan format, bersihkan
            if (is_string($customUpah)) {
                $customUpah = str_replace('.', '', $customUpah);
            }

            // Konversi ke nominal bulanan berdasarkan hitungan upah
            if ($hitunganUpah == "Per Hari") {
                $nominalUpah = $customUpah * 21; // 21 hari kerja
            } else if ($hitunganUpah == "Per Jam") {
                $nominalUpah = $customUpah * 21 * 8; // 21 hari × 8 jam
            } else {
                $nominalUpah = $customUpah; // Per Bulan
            }
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

    /**
     * Update training data from array (new approach for step 10)
     */
    private function updateTrainingDataFromArray(Quotation $quotation, array $trainingIds, Carbon $currentDateTime): void
    {
        $user = Auth::user()->full_name;

        \Log::info("Updating training data from array", [
            'quotation_id' => $quotation->id,
            'training_ids' => $trainingIds,
            'count' => count($trainingIds)
        ]);

        // Get existing training IDs untuk quotation ini
        $existingTrainingIds = QuotationTraining::where('quotation_id', $quotation->id)
            ->whereNull('deleted_at')
            ->pluck('training_id')
            ->toArray();

        $trainingIdsToDelete = array_diff($existingTrainingIds, $trainingIds);
        $trainingIdsToAdd = array_diff($trainingIds, $existingTrainingIds);

        // Delete training yang tidak dipilih lagi
        if (!empty($trainingIdsToDelete)) {
            QuotationTraining::where('quotation_id', $quotation->id)
                ->whereIn('training_id', $trainingIdsToDelete)
                ->update([
                    'deleted_at' => $currentDateTime,
                    'deleted_by' => $user
                ]);

            \Log::info("Deleted training associations", [
                'quotation_id' => $quotation->id,
                'deleted_training_ids' => $trainingIdsToDelete
            ]);
        }

        // Add training baru
        foreach ($trainingIdsToAdd as $trainingId) {
            $training = Training::find($trainingId);
            if ($training) {
                QuotationTraining::create([
                    'training_id' => $trainingId,
                    'quotation_id' => $quotation->id,
                    'nama' => $training->nama,
                    'created_by' => $user
                ]);
            }
        }

        \Log::info("Added training associations", [
            'quotation_id' => $quotation->id,
            'added_training_ids' => $trainingIdsToAdd
        ]);
    }

    /**
     * Clear all training data for quotation
     */
    private function clearAllTrainingData(Quotation $quotation, Carbon $currentDateTime): void
    {
        $user = Auth::user()->full_name;

        QuotationTraining::where('quotation_id', $quotation->id)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $currentDateTime,
                'deleted_by' => $user
            ]);

        \Log::info("Cleared all training data", [
            'quotation_id' => $quotation->id
        ]);
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
        // 1. Cek BPJS (Jika ada salah satu item bernilai 0/false)
        $hasMissingBpjs = $quotation->quotationDetails()->where(function ($query) {
            $query->where('is_bpjs_jkk', 0)
                ->orWhere('is_bpjs_jkm', 0)
                ->orWhere('is_bpjs_jht', 0)
                ->orWhere('is_bpjs_jp', 0);
        })->exists();

        // 2. Cek Kompensasi & THR (Jika salah satu saja "Tidak Ada")
        $hasNoCompensation = $quotation->quotationDetails()->whereHas('wage', function ($query) {
            $query->where(function ($q) {
                $q->where('kompensasi', 'Tidak Ada')
                    ->orWhere('thr', 'Tidak Ada');
            });
        })->exists();

        // 3. Cek Upah Custom < 85% UMK
        $isUnderMinimumWage = $quotation->quotationDetails->some(function ($detail) {
            $wage = $detail->wage;
            $site = $detail->quotationSite;

            if (!$wage || !$site || $wage->upah !== 'Custom')
                return false;

            $umkData = Umk::byCity($site->kota_id)->active()->first();
            if (!$umkData)
                return false;

            $nominalUpah = (float) $wage->nominal_upah;
            $batasMinimal = (float) $umkData->umk * 0.85;

            return $nominalUpah < $batasMinimal;
        });
        $thresholdPersentase = ($quotation->kebutuhan_id == 1) ? 7 : 6;
        $isLowPercentage = $quotation->persentase < $thresholdPersentase;
        // 4. Evaluasi Akhir
        $needsApprovalLevel2 = (
            $hasMissingBpjs ||
            $hasNoCompensation ||
            $isUnderMinimumWage ||
            $isLowPercentage ||
            $quotation->persentase < 7 ||
            $quotation->company_id == 17
        );

        return [
            'is_aktif' => $needsApprovalLevel2 ? 0 : 1,
            'status_quotation_id' => $needsApprovalLevel2 ? 2 : 3
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
    /**
     * Sync HC details with composite key checking (position_id + quotation_site_id)
     */
    public function syncDetailHCFromArray(Quotation $quotation, array $details, string $timestamp, string $user): void
    {
        try {
            \Log::info("Starting syncDetailHCFromArray", [
                'quotation_id' => $quotation->id,
                'incoming_count' => count($details)
            ]);

            // ============================================================
            // GET EXISTING DATA WITH COMPOSITE KEY
            // ============================================================

            $existingDetails = QuotationDetail::where('quotation_id', $quotation->id)
                ->whereNull('deleted_at')
                ->get()
                ->keyBy(function ($detail) {
                    return $detail->position_id . '_' . $detail->quotation_site_id;
                });

            \Log::info("Existing details", [
                'count' => $existingDetails->count(),
                'keys' => $existingDetails->keys()->toArray()
            ]);

            // ============================================================
            // BUILD INCOMING COMPOSITE KEYS
            // ============================================================

            $incomingKeys = collect($details)
                ->filter(function ($detail) {
                    return !empty($detail['position_id']) && !empty($detail['quotation_site_id']);
                })
                ->map(function ($detail) {
                    return $detail['position_id'] . '_' . $detail['quotation_site_id'];
                })
                ->unique()
                ->values()
                ->toArray();

            \Log::info("Incoming composite keys", [
                'count' => count($incomingKeys),
                'keys' => $incomingKeys
            ]);

            // ============================================================
            // DELETE OLD DATA NOT IN NEW DATA
            // ============================================================

            $keysToDelete = $existingDetails->keys()->diff($incomingKeys);

            if ($keysToDelete->isNotEmpty()) {
                \Log::info("Deleting old details", [
                    'count' => $keysToDelete->count(),
                    'keys' => $keysToDelete->toArray()
                ]);

                foreach ($keysToDelete as $compositeKey) {
                    $detail = $existingDetails->get($compositeKey);
                    if ($detail) {
                        $this->softDeleteQuotationDetail($detail, $timestamp, $user);
                    }
                }
            }

            // ============================================================
            // CREATE OR UPDATE NEW DATA
            // ============================================================

            foreach ($details as $detailData) {
                // Skip invalid data
                if (empty($detailData['position_id']) || empty($detailData['quotation_site_id'])) {
                    \Log::warning("Skipping invalid detail data", ['data' => $detailData]);
                    continue;
                }

                $this->createOrUpdateQuotationDetail($quotation, $detailData, $timestamp, $user);
            }

            \Log::info("syncDetailHCFromArray completed", [
                'quotation_id' => $quotation->id,
                'processed' => count($details),
                'deleted' => $keysToDelete->count()
            ]);

        } catch (\Exception $e) {
            \Log::error("Error in syncDetailHCFromArray", [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create or update single quotation detail with composite key checking
     */
    private function createOrUpdateQuotationDetail(Quotation $quotation, array $data, string $timestamp, string $user): void
    {
        $positionId = $data['position_id'];
        $siteId = $data['quotation_site_id'];

        // Check if exists (composite key)
        $existing = QuotationDetail::where('quotation_id', $quotation->id)
            ->where('position_id', $positionId)
            ->where('quotation_site_id', $siteId)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            // UPDATE EXISTING
            $existing->update([
                'jumlah_hc' => $data['jumlah_hc'] ?? 0,
                'jabatan_kebutuhan' => $data['jabatan_kebutuhan'] ?? $existing->jabatan_kebutuhan,
                'nama_site' => $data['nama_site'] ?? $existing->nama_site,
                'nominal_upah' => $data['nominal_upah'] ?? $existing->nominal_upah,
                'updated_at' => $timestamp,
                'updated_by' => $user
            ]);

            // Update related HPP
            QuotationDetailHpp::where('quotation_detail_id', $existing->id)
                ->whereNull('deleted_at')
                ->update([
                    'jumlah_hc' => $data['jumlah_hc'] ?? 0,
                    'updated_at' => $timestamp,
                    'updated_by' => $user
                ]);

            // Update related COSS
            QuotationDetailCoss::where('quotation_detail_id', $existing->id)
                ->whereNull('deleted_at')
                ->update([
                    'jumlah_hc' => $data['jumlah_hc'] ?? 0,
                    'updated_at' => $timestamp,
                    'updated_by' => $user
                ]);

            \Log::info("Updated quotation detail", [
                'id' => $existing->id,
                'position_id' => $positionId,
                'site_id' => $siteId,
                'jumlah_hc' => $data['jumlah_hc'] ?? 0
            ]);

        } else {
            // CREATE NEW
            $newDetail = QuotationDetail::create([
                'quotation_id' => $quotation->id,
                'quotation_site_id' => $siteId,
                'nama_site' => $data['nama_site'] ?? null,
                'position_id' => $positionId,
                'jabatan_kebutuhan' => $data['jabatan_kebutuhan'] ?? null,
                'jumlah_hc' => $data['jumlah_hc'] ?? 0,
                'nominal_upah' => $data['nominal_upah'] ?? 0,
                'created_at' => $timestamp,
                'created_by' => $user
            ]);

            // Create HPP
            QuotationDetailHpp::create([
                'quotation_id' => $quotation->id,
                'quotation_detail_id' => $newDetail->id,
                'leads_id' => $quotation->leads_id,
                'position_id' => $positionId,
                'jumlah_hc' => $data['jumlah_hc'] ?? 0,
                'created_at' => $timestamp,
                'created_by' => $user
            ]);

            // Create COSS
            QuotationDetailCoss::create([
                'quotation_id' => $quotation->id,
                'quotation_detail_id' => $newDetail->id,
                'leads_id' => $quotation->leads_id,
                'position_id' => $positionId,
                'jumlah_hc' => $data['jumlah_hc'] ?? 0,
                'created_at' => $timestamp,
                'created_by' => $user
            ]);

            // Create Requirements if provided
            if (!empty($data['requirements']) && is_array($data['requirements'])) {
                foreach ($data['requirements'] as $requirement) {
                    if (!empty(trim($requirement))) {
                        QuotationDetailRequirement::create([
                            'quotation_id' => $quotation->id,
                            'quotation_detail_id' => $newDetail->id,
                            'requirement' => trim($requirement),
                            'created_at' => $timestamp,
                            'created_by' => $user
                        ]);
                    }
                }
            }

            \Log::info("Created new quotation detail", [
                'id' => $newDetail->id,
                'position_id' => $positionId,
                'site_id' => $siteId,
                'jumlah_hc' => $data['jumlah_hc'] ?? 0
            ]);
        }
    }
    /**
     * Update nominal upah untuk setiap position berdasarkan site yang terkait
     */
    private function updateUpahPerPosition(Quotation $quotation): void
    {
        try {
            \Log::info("=== updateUpahPerPosition START ===", [
                'quotation_id' => $quotation->id
            ]);

            // Ambil semua quotation details dengan relasi site dan wage
            $quotationDetails = QuotationDetail::with(['quotationSite', 'wage'])
                ->where('quotation_id', $quotation->id)
                ->get();

            foreach ($quotationDetails as $detail) {
                \Log::info("Processing detail", [
                    'detail_id' => $detail->id,
                    'current_nominal_upah' => $detail->nominal_upah,
                    'has_wage' => !is_null($detail->wage),
                    'wage_upah_type' => $detail->wage ? $detail->wage->upah : 'no_wage'
                ]);

                // JANGAN update nominal_upah jika wage type adalah Custom
                // Hanya update untuk UMP/UMK
                if ($detail->wage && $detail->wage->upah === 'Custom') {
                    \Log::info("Skipping update for Custom upah", [
                        'detail_id' => $detail->id,
                        'reason' => 'Custom upah should not be overwritten by site nominal_upah'
                    ]);
                    continue;
                }

                // Jika detail memiliki quotation site, gunakan nominal_upah dari site tersebut
                // HANYA untuk UMP/UMK
                if ($detail->quotationSite) {
                    $newNominalUpah = $detail->quotationSite->nominal_upah;

                    \Log::info("Updating UMP/UMK upah from site", [
                        'detail_id' => $detail->id,
                        'site_nominal_upah' => $newNominalUpah,
                        'current_detail_nominal_upah' => $detail->nominal_upah
                    ]);

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

            \Log::info("=== updateUpahPerPosition END ===");

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
        try {
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

            // DEBUG: Cek apakah wage sudah ada
            $existingWage = QuotationDetailWage::where('quotation_detail_id', $detail->id)->first();

            \Log::info("Wage check before update", [
                'quotation_detail_id' => $detail->id,
                'wage_exists' => !is_null($existingWage),
                'wage_id' => $existingWage ? $existingWage->id : 'none'
            ]);

            // Calculate upah data untuk position ini
            $upahData = $this->calculateUpahForPosition($detail, $positionData);

            // Data untuk wage table
            $wageData = [
                'quotation_id' => $quotation->id,
                'upah' => $positionData['upah'] ?? null,
                'hitungan_upah' => $upahData['hitungan_upah'] ?? null,
                'lembur' => $positionData['lembur'] ?? null,
                'nominal_upah' => $positionData['nominal_upah'] ?? null,
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
            ];

            // APPROACH: Gunakan updateOrCreate dengan kondisi yang tepat
            $wage = QuotationDetailWage::updateOrCreate(
                [
                    'quotation_detail_id' => $detail->id
                ],
                array_merge($wageData, [
                    'created_by' => Auth::user()->full_name
                ])
            );

            \Log::info("Wage operation result", [
                'quotation_detail_id' => $detail->id,
                'operation' => $wage->wasRecentlyCreated ? 'CREATED' : 'UPDATED',
                'wage_id' => $wage->id
            ]);

            // Update nominal_upah di quotation_detail
            $detail->update([
                'nominal_upah' => $upahData['nominal_upah'],
                'updated_by' => Auth::user()->full_name
            ]);

            \Log::info("Successfully updated step 4 wage for position", [
                'quotation_detail_id' => $detail->id,
                'position_id' => $detail->position_id,
                'upah' => $positionData['upah'] ?? 'null'
            ]);

        } catch (\Exception $e) {
            \Log::error("Error in updatePositionStep4", [
                'quotation_detail_id' => $positionData['quotation_detail_id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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

    /**
     * Soft delete quotation detail and all its relations - USING MODEL ONLY
     */
    private function softDeleteQuotationDetail(QuotationDetail $detail, string $timestamp, string $user): void
    {
        ;

        try {
            // Gunakan model untuk soft delete detail utama
            $detail->update([
                'deleted_at' => $timestamp,
                'deleted_by' => $user
            ]);

            \Log::info("Main detail soft deleted", [
                'detail_id' => $detail->id,
                'deleted_at' => $detail->deleted_at
            ]);

            // Gunakan model untuk soft delete semua relasi
            $this->softDeleteRelatedDataWithModel($detail, $timestamp, $user);

        } catch (\Exception $e) {
            \Log::error("Error soft deleting quotation detail with model", [
                'detail_id' => $detail->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Soft delete related data menggunakan model
     */
    private function softDeleteRelatedDataWithModel(QuotationDetail $detail, string $timestamp, string $user): void
    {
        // HPP - menggunakan model
        QuotationDetailHpp::where('quotation_detail_id', $detail->id)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $timestamp,
                'deleted_by' => $user
            ]);

        // COSS - menggunakan model  
        QuotationDetailCoss::where('quotation_detail_id', $detail->id)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $timestamp,
                'deleted_by' => $user
            ]);

        // Tunjangan - menggunakan model
        QuotationDetailTunjangan::where('quotation_detail_id', $detail->id)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $timestamp,
                'deleted_by' => $user
            ]);

        // Wage - menggunakan model
        QuotationDetailWage::where('quotation_detail_id', $detail->id)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $timestamp,
                'deleted_by' => $user
            ]);

        // Requirements - menggunakan DB table (karena mungkin bukan model)
        QuotationDetailRequirement::where('quotation_detail_id', $detail->id)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $timestamp,
                'deleted_by' => $user
            ]);
    }

    /**
     * Soft delete ALL quotation details for a quotation - USING MODEL ONLY
     */
    private function softDeleteAllQuotationDetails(Quotation $quotation, string $timestamp, string $user): void
    {
        \Log::info("Soft deleting ALL quotation details with model", [
            'quotation_id' => $quotation->id
        ]);

        try {
            // Ambil semua details yang belum di-delete menggunakan model
            $details = QuotationDetail::where('quotation_id', $quotation->id)
                ->whereNull('deleted_at')
                ->get();


            // Soft delete setiap detail menggunakan model
            foreach ($details as $detail) {
                $this->softDeleteQuotationDetail($detail, $timestamp, $user);
            }



        } catch (\Exception $e) {
            \Log::error("Error soft deleting all quotation details with model", [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Simpan data HPP dari DetailCalculation DTO dengan memperhatikan data yang diedit
     */
    private function saveHppDataFromCalculation(DetailCalculation $detailCalculation, QuotationCalculationResult $calculationResult, $user, $currentDateTime, $request = null): void
    {
        try {
            $existingHpp = QuotationDetailHpp::where('quotation_detail_id', $detailCalculation->detail_id)->first();
            $hppData = $detailCalculation->hpp_data;

            // ============================
            // HORMATI HANYA bpjs_persentase_data DARI REQUEST
            // ============================
            // 1. Hormati HPP data (thr, kompensasi, insentif)
            if ($request && $request->has('hpp_data') && isset($request->hpp_data[$detailCalculation->detail_id])) {
                $userHppData = $request->hpp_data[$detailCalculation->detail_id];

                $userEditableFields = ['thr', 'kompensasi', 'jumlah_hc'];

                foreach ($userEditableFields as $field) {
                    if (array_key_exists($field, $userHppData)) {
                        $userValue = $userHppData[$field];

                        if ($userValue === null) {
                            $hppData[$field] = null;
                        } elseif ($userValue === 0 || $userValue === "0") {
                            $hppData[$field] = 0;
                        } else {
                            if (is_string($userValue) && !is_numeric($userValue)) {
                                $userValue = (float) str_replace(['.', ','], ['', '.'], $userValue);
                            }
                            $hppData[$field] = (float) $userValue;
                        }
                    }
                }
            }
            if ($request && $request->has('hpp_editable_data') && isset($request->bpjs_persentase_data[$detailCalculation->detail_id])) {
                $userHppData = $request->hpp_editable_data[$detailCalculation->detail_id];

                $userEditableFields = ['thr', 'kompensasi', 'jumlah_hc'];

                foreach ($userEditableFields as $field) {
                    if (array_key_exists($field, $userHppData)) {
                        $userValue = $userHppData[$field];

                        if ($userValue === null) {
                            $hppData[$field] = null;
                        } elseif ($userValue === 0 || $userValue === "0") {
                            $hppData[$field] = 0;
                        } else {
                            if (is_string($userValue) && !is_numeric($userValue)) {
                                $userValue = (float) str_replace(['.', ','], ['', '.'], $userValue);
                            }
                            $hppData[$field] = (float) $userValue;
                        }
                    }
                }
            }


            if ($request && $request->has('bpjs_persentase_data') && isset($request->bpjs_persentase_data[$detailCalculation->detail_id])) {
                $userBpjsData = $request->bpjs_persentase_data[$detailCalculation->detail_id];

                $bpjsPercentFields = [
                    'persen_bpjs_jkk' => 'jkk',
                    'persen_bpjs_jkm' => 'jkm',
                    'persen_bpjs_jht' => 'jht',
                    'persen_bpjs_jp' => 'jp',
                    'persen_bpjs_kes' => 'kes'
                ];

                foreach ($bpjsPercentFields as $hppField => $requestField) {
                    if (isset($userBpjsData[$requestField])) {
                        $userValue = $userBpjsData[$requestField];
                        if (is_string($userValue) && !is_numeric($userValue)) {
                            $userValue = (float) str_replace(['.', ','], ['', '.'], $userValue);
                        }
                        $hppData[$hppField] = (float) $userValue;

                        \Log::info("Using user input for BPJS persentase", [
                            'detail_id' => $detailCalculation->detail_id,
                            'field' => $hppField,
                            'user_value' => $userValue
                        ]);
                    }
                }
            }
            // ============================
            // **PERBAIKAN TAMBAHAN**: PASTIKAN PERSENTASE BPJS DARI DETAIL MASUK KE HPP
            // ============================
            $detail = QuotationDetail::find($detailCalculation->detail_id);
            if ($detail) {
                // Copy persentase dari detail ke HPP
                $persentaseFields = [
                    'persen_bpjs_jkk',
                    'persen_bpjs_jkm',
                    'persen_bpjs_jht',
                    'persen_bpjs_jp',
                    'persen_bpjs_kes'
                ];

                foreach ($persentaseFields as $field) {
                    if (property_exists($detail, $field) && $detail->{$field} !== null) {
                        $hppData[$field] = (float) $detail->{$field};
                        \Log::info("Copying persentase from detail to HPP", [
                            'detail_id' => $detailCalculation->detail_id,
                            'field' => $field,
                            'value' => $detail->{$field}
                        ]);
                    }
                }
            }

            // ============================
            // PERBAIKAN: Konversi field string ke numerik
            // ============================
            $numericFields = [
                'kompensasi',
                'tunjangan_hari_raya',
                'tunjangan_hari_libur_nasional',
                'lembur',
                'takaful',
                'bpjs_jkk',
                'bpjs_jkm',
                'bpjs_jht',
                'bpjs_jp',
                'bpjs_ks',
                'provisi_seragam',
                'provisi_peralatan',
                'provisi_chemical',
                'provisi_ohc',
                'bunga_bank',
                'insentif',
                'potongan_bpu',
                'total_biaya_per_personil',
                'total_biaya_all_personil'
            ];

            foreach ($numericFields as $field) {
                if (isset($hppData[$field]) && $hppData[$field] !== null) {
                    if (is_string($hppData[$field])) {
                        $stringValue = strtolower(trim($hppData[$field]));
                        if (in_array($stringValue, ['tidak', 'tidak ada', 'false', 'no', 'ya'])) {
                            $hppData[$field] = 0;
                        } else {
                            $hppData[$field] = (float) str_replace(['.', ','], ['', '.'], $stringValue);
                        }
                    }
                    $hppData[$field] = (float) $hppData[$field];
                }
            }

            // Tambahkan field tambahan
            $hppData = array_merge($hppData, [
                'management_fee' => $calculationResult->calculation_summary->nominal_management_fee ?? 0,
                'persen_management_fee' => $calculationResult->quotation->persentase ?? 0,
                'grand_total' => $calculationResult->calculation_summary->grand_total_sebelum_pajak ?? 0,
                'ppn' => $calculationResult->calculation_summary->ppn ?? 0,
                'pph' => $calculationResult->calculation_summary->pph ?? 0,
                'total_invoice' => $calculationResult->calculation_summary->total_invoice ?? 0,
                'pembulatan' => $calculationResult->calculation_summary->pembulatan ?? 0,
                'is_pembulatan' => ($calculationResult->calculation_summary->pembulatan != $calculationResult->calculation_summary->total_invoice) ? 1 : 0,
                'updated_by' => $user,
                'updated_at' => $currentDateTime
            ]);

            // Jika existing data ada, update. Jika tidak, create baru
            if ($existingHpp) {
                // Field BPJS persentase yang harus dipertahankan jika user sudah input
                $userEditableFields = ['thr', 'kompensasi', 'jumlah_hc'];
                $bpjsPercentFields = ['persen_bpjs_jkk', 'persen_bpjs_jkm', 'persen_bpjs_jht', 'persen_bpjs_jp', 'persen_bpjs_kes'];

                // Cek apakah ada input BPJS dari user
                $hasUserInput = false;
                if ($request) {
                    if ($request->has('hpp_data') && isset($request->hpp_data[$detailCalculation->detail_id])) {
                        $hasUserInput = true;
                    }
                    if ($request->has('bpjs_persentase_data') && isset($request->bpjs_persentase_data[$detailCalculation->detail_id])) {
                        $hasUserInput = true;
                    }
                }

                // Jika ada input user, gunakan nilai dari user (sudah di-set di atas)
                // Jika tidak ada input user, pertahankan nilai existing (dari perhitungan)
                if (!$hasUserInput) {
                    foreach (array_merge($userEditableFields, $bpjsPercentFields) as $field) {
                        if ($existingHpp->$field !== null) {
                            $hppData[$field] = $existingHpp->$field;
                        }
                    }
                }

                $existingHpp->update($hppData);
            } else {
                $hppData['created_by'] = $user;
                $hppData['created_at'] = $currentDateTime;
                QuotationDetailHpp::create($hppData);
            }

            \Log::info("Saved HPP data from calculation", [
                'detail_id' => $detailCalculation->detail_id,
                'kompensasi' => $hppData['kompensasi'] ?? 0,
                'tunjangan_hari_raya' => $hppData['tunjangan_hari_raya'] ?? 0,
                'tunjangan_hari_libur_nasional' => $hppData['tunjangan_hari_libur_nasional'] ?? 0
            ]);

        } catch (\Exception $e) {
            \Log::error("Error saving HPP data from calculation", [
                'detail_id' => $detailCalculation->detail_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    /**
     * Simpan data COSS dari DetailCalculation DTO
     */
    private function saveCossDataFromCalculation(DetailCalculation $detailCalculation, QuotationCalculationResult $calculationResult, $user, $currentDateTime, $request = null): void
    {
        try {
            $existingCoss = QuotationDetailCoss::where('quotation_detail_id', $detailCalculation->detail_id)->first();
            $cossData = $detailCalculation->coss_data;

            // ============================
            // HORMATI HANYA bpjs_persentase_data DARI REQUEST
            // ============================

            if ($request && $request->has('bpjs_persentase_data') && isset($request->bpjs_persentase_data[$detailCalculation->detail_id])) {
                $userBpjsData = $request->bpjs_persentase_data[$detailCalculation->detail_id];

                $bpjsPercentFields = [
                    'persen_bpjs_jkk' => 'jkk',
                    'persen_bpjs_jkm' => 'jkm',
                    'persen_bpjs_jht' => 'jht',
                    'persen_bpjs_jp' => 'jp',
                    'persen_bpjs_kes' => 'kes'
                ];

                foreach ($bpjsPercentFields as $cossField => $requestField) {
                    if (isset($userBpjsData[$requestField])) {
                        $userValue = $userBpjsData[$requestField];
                        if (is_string($userValue) && !is_numeric($userValue)) {
                            $userValue = (float) str_replace(['.', ','], ['', '.'], $userValue);
                        }
                        $cossData[$cossField] = (float) $userValue;
                    }
                }
            }
            if ($request && $request->has('coss_data') && isset($request->coss_data[$detailCalculation->detail_id])) {
                $userCossData = $request->coss_data[$detailCalculation->detail_id];

                $userEditableFields = ['kompensasi', 'tunjangan_hari_raya', 'tunjangan_hari_libur_nasional', 'lembur',];

                foreach ($userEditableFields as $field) {
                    if (array_key_exists($field, $userCossData)) {
                        $userValue = $userCossData[$field];

                        if ($userValue === null) {
                            $cossData[$field] = null;
                        } elseif ($userValue === 0 || $userValue === "0") {
                            $cossData[$field] = 0;
                        } else {
                            if (is_string($userValue) && !is_numeric($userValue)) {
                                $userValue = (float) str_replace(['.', ','], ['', '.'], $userValue);
                            }
                            $cossData[$field] = (float) $userValue;
                        }
                    }
                }
            }

            // ============================
            // PERBAIKAN: Konversi field string ke numerik
            // ============================
            $numericFields = [
                'kompensasi',
                'tunjangan_hari_raya',
                'tunjangan_hari_libur_nasional',
                'lembur',
                'bpjs_jkk',
                'bpjs_jkm',
                'bpjs_jht',
                'bpjs_jp',
                'bpjs_ks',
                'provisi_seragam',
                'provisi_peralatan',
                'provisi_chemical',
                'provisi_ohc',
                'bunga_bank',
                'insentif',
                'potongan_bpu'
            ];

            foreach ($numericFields as $field) {
                if (isset($cossData[$field]) && $cossData[$field] !== null) {
                    if (is_string($cossData[$field])) {
                        $stringValue = strtolower(trim($cossData[$field]));
                        if (in_array($stringValue, ['tidak', 'tidak ada', 'false', 'no', 'ya'])) {
                            $cossData[$field] = 0;
                        } else {
                            $cossData[$field] = (float) str_replace(['.', ','], ['', '.'], $stringValue);
                        }
                    }
                    $cossData[$field] = (float) $cossData[$field];
                }
            }

            // Tambahkan field tambahan dari calculation summary
            $cossData = array_merge($cossData, [
                'management_fee' => $calculationResult->calculation_summary->nominal_management_fee_coss ?? 0,
                'persen_management_fee' => $calculationResult->quotation->persentase ?? 0,
                'grand_total' => $calculationResult->calculation_summary->grand_total_sebelum_pajak_coss ?? 0,
                'ppn' => $calculationResult->calculation_summary->ppn_coss ?? 0,
                'pph' => $calculationResult->calculation_summary->pph_coss ?? 0,
                'total_invoice' => $calculationResult->calculation_summary->total_invoice_coss ?? 0,
                'pembulatan' => $calculationResult->calculation_summary->pembulatan_coss ?? 0,
                'is_pembulatan' => ($calculationResult->calculation_summary->pembulatan_coss != $calculationResult->calculation_summary->total_invoice_coss) ? 1 : 0,
                'updated_by' => $user,
                'updated_at' => $currentDateTime
            ]);

            // Jika existing data ada, update. Jika tidak, create baru
            if ($existingCoss) {
                // Field BPJS persentase yang harus dipertahankan jika user sudah input
                $bpjsPercentFields = ['persen_bpjs_jkk', 'persen_bpjs_jkm', 'persen_bpjs_jht', 'persen_bpjs_jp', 'persen_bpjs_kes'];
                $userEditableFields = ['kompensasi', 'tunjangan_hari_raya', 'tunjangan_hari_libur_nasional', 'lembur',];

                // Cek apakah ada input BPJS dari user
                $hasBpjsUserInput = false;
                if ($request && $request->has('bpjs_persentase_data') && isset($request->bpjs_persentase_data[$detailCalculation->detail_id])) {
                    $hasBpjsUserInput = true;
                }
                if ($request && $request->has('coss_data') && isset($request->coss_data[$detailCalculation->detail_id])) {
                    $hasBpjsUserInput = true;
                }

                // Jika ada input user, gunakan nilai dari user (sudah di-set di atas)
                // Jika tidak ada input user, pertahankan nilai existing (dari perhitungan)
                if (!$hasBpjsUserInput) {
                    foreach (array_merge($bpjsPercentFields, $userEditableFields) as $field) {
                        if ($existingCoss->$field !== null) {
                            $cossData[$field] = $existingCoss->$field;
                        }
                    }
                }

                $existingCoss->update($cossData);
            } else {
                $cossData['created_by'] = $user;
                $cossData['created_at'] = $currentDateTime;
                QuotationDetailCoss::create($cossData);
            }

        } catch (\Exception $e) {
            \Log::error("Error saving COSS data from calculation", [
                'detail_id' => $detailCalculation->detail_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    private function updateQuotationDataFromCalculation(QuotationCalculationResult $calculationResult, $user, $currentDateTime): void
    {
        try {
            $quotation = $calculationResult->quotation;

            // RESET attributes yang tidak ada di tabel
            $quotation->offsetUnset('quotation_detail');
            $quotation->offsetUnset('quotation_site');
            $quotation->offsetUnset('management_fee');
            $quotation->offsetUnset('jumlah_hc');
            $quotation->offsetUnset('provisi');
            $quotation->offsetUnset('persen_bpjs_ketenagakerjaan');
            $quotation->offsetUnset('persen_bpjs_kesehatan');

            // HANYA update kolom yang ada di tabel
            $updateData = [
                'persen_bunga_bank' => $quotation->persen_bunga_bank ?? 0,
                'updated_by' => $user,
                'updated_at' => $currentDateTime
            ];

            $affectedRows = DB::table('sl_quotation')
                ->where('id', $quotation->id)
                ->update($updateData);

            \Log::info("Quotation data updated from calculation", [
                'quotation_id' => $quotation->id,
                'affected_rows' => $affectedRows
            ]);

        } catch (\Exception $e) {
            \Log::error("Error updating quotation data from calculation", [
                'quotation_id' => $quotation->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    private function syncTunjanganData(Quotation $quotation, array $tunjanganData, Carbon $currentDateTime, string $user): void
    {
        try {
            \Log::info("Starting tunjangan data sync", [
                'quotation_id' => $quotation->id,
                'detail_count' => count($tunjanganData)
            ]);

            foreach ($tunjanganData as $detailId => $tunjangans) {
                // Verify detail belongs to this quotation
                $detail = QuotationDetail::where('id', $detailId)
                    ->where('quotation_id', $quotation->id)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$detail) {
                    \Log::warning("Quotation detail not found or deleted", [
                        'detail_id' => $detailId,
                        'quotation_id' => $quotation->id
                    ]);
                    continue;
                }

                // Get existing tunjangan for this detail
                $existingTunjangan = QuotationDetailTunjangan::where('quotation_detail_id', $detailId)
                    ->whereNull('deleted_at')
                    ->get()
                    ->keyBy('nama_tunjangan');

                // Track which tunjangan to keep
                $processedTunjanganNames = [];

                // Process incoming tunjangan data
                if (is_array($tunjangans) && !empty($tunjangans)) {
                    foreach ($tunjangans as $tunjanganData) {
                        $namaTunjangan = trim($tunjanganData['nama_tunjangan'] ?? '');
                        $nominal = $tunjanganData['nominal'] ?? 0;
                        $nominalCoss = $tunjanganData['nominal_coss'] ?? 0;

                        // Skip empty names
                        if (empty($namaTunjangan)) {
                            continue;
                        }

                        // Convert string nominal to integer if needed
                        if (is_string($nominal)) {
                            $nominal = (int) str_replace('.', '', $nominal);
                        }
                        if (is_string($nominalCoss)) {
                            $nominalCoss = (int) str_replace('.', '', $nominalCoss);
                        }

                        $processedTunjanganNames[] = $namaTunjangan;

                        // Update or create
                        if ($existingTunjangan->has($namaTunjangan)) {
                            // Update existing
                            $existing = $existingTunjangan->get($namaTunjangan);
                            $existing->update([
                                'nominal' => $nominal,
                                'nominal_coss' => $nominalCoss,
                                'updated_at' => $currentDateTime,
                                'updated_by' => $user
                            ]);

                            \Log::debug("Updated tunjangan", [
                                'detail_id' => $detailId,
                                'nama_tunjangan' => $namaTunjangan,
                                'nominal' => $nominal,
                                'nominal_coss' => $nominalCoss,
                            ]);
                        } else {
                            // Create new
                            QuotationDetailTunjangan::create([
                                'quotation_id' => $quotation->id,
                                'quotation_detail_id' => $detailId,
                                'nama_tunjangan' => $namaTunjangan,
                                'nominal' => $nominal,
                                'nominal_coss' => $nominalCoss,
                                'created_at' => $currentDateTime,
                                'created_by' => $user
                            ]);

                            \Log::debug("Created tunjangan", [
                                'detail_id' => $detailId,
                                'nama_tunjangan' => $namaTunjangan,
                                'nominal' => $nominal
                            ]);
                        }
                    }
                }

                // Soft delete tunjangan that are no longer in the list
                $tunjanganToDelete = $existingTunjangan->keys()->diff($processedTunjanganNames);
                if ($tunjanganToDelete->isNotEmpty()) {
                    QuotationDetailTunjangan::where('quotation_detail_id', $detailId)
                        ->whereIn('nama_tunjangan', $tunjanganToDelete->toArray())
                        ->whereNull('deleted_at')
                        ->update([
                            'deleted_at' => $currentDateTime,
                            'deleted_by' => $user
                        ]);

                    \Log::debug("Soft deleted tunjangan", [
                        'detail_id' => $detailId,
                        'deleted_names' => $tunjanganToDelete->toArray()
                    ]);
                }
            }

            \Log::info("Tunjangan data sync completed", [
                'quotation_id' => $quotation->id
            ]);

        } catch (\Exception $e) {
            \Log::error("Error syncing tunjangan data", [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }


    /**
     * Update semua data quotation dalam satu fungsi untuk Step 11
     * Method utama yang menggantikan updateStep11
     */
    private function updateAllQuotationData(Quotation $quotation, Request $request): void
    {
        DB::beginTransaction();
        try {
            $user = Auth::user()->full_name;
            $currentDateTime = Carbon::now();

            \Log::info("Starting updateAllQuotationData for Step 11", [
                'quotation_id' => $quotation->id,
                'request_keys' => array_keys($request->all())
            ]);

            // =====================================================
            // 1. UPDATE DATA QUOTATION UTAMA
            // =====================================================
            $quotationData = $this->prepareQuotationDataForUpdate($quotation, $request, $user, $currentDateTime);
            $quotation->update($quotationData);

            // =====================================================
            // 2. UPDATE WAGE DATA JIKA ADA
            // =====================================================
            if ($request->has('wage_data') && is_array($request->wage_data)) {
                $this->processWageDataFromRequest($quotation, $request->wage_data, $user, $currentDateTime);
            }
            // =====================================================
            // UPDATE HPP EDITABLE DATA JIKA ADA
            // =====================================================
            if ($request->has('hpp_editable_data') && is_array($request->hpp_editable_data)) {
                $this->updateAllHppEditableData($quotation, $request, $user, $currentDateTime);
            }


            // =====================================================
            // 3. UPDATE PERSENTASE BPJS
            // =====================================================
            if ($request->has('bpjs_persentase_data') && is_array($request->bpjs_persentase_data)) {
                $this->updateBpjsPersentaseFromRequest($quotation, $request->bpjs_persentase_data, $user, $currentDateTime);
            }

            // =====================================================
            // 4. UPDATE COSS DATA (PROVISI ITEMS)
            // =====================================================
            if ($request->has('coss_data') && is_array($request->coss_data)) {
                foreach ($request->coss_data as $detailId => $cossFields) {
                    $this->updateCossDataFromRequest($detailId, $cossFields, $user, $currentDateTime, $quotation->id);
                }
            }

            // =====================================================
            // 5. UPDATE NOMINAL UPAH DAN DETAIL LAINNYA
            // =====================================================
            if ($request->has('detail_data') && is_array($request->detail_data)) {
                $this->updateQuotationDetailData($quotation, $request->detail_data, $user, $currentDateTime);
            }

            // =====================================================
            // 6. UPDATE DATA TUNJANGAN
            // =====================================================
            if ($request->has('tunjangan_data') && is_array($request->tunjangan_data)) {
                $this->syncTunjanganData($quotation, $request->tunjangan_data, $currentDateTime, $user);
            }

            // =====================================================
            // 7. JALANKAN PERHITUNGAN ULANG DENGAN DATA TERBARU
            // =====================================================
            $calculationResult = $this->quotationService->calculateQuotation($quotation);

            // =====================================================
            // 8. SIMPAN HASIL PERHITUNGAN KE DATABASE
            // =====================================================
            $this->saveAllCalculationResults($calculationResult, $user, $currentDateTime, $request);

            // =====================================================
            // 9. GENERATE PERJANJIAN KERJASAMA
            // =====================================================
            $this->generateKerjasama($quotation);

            // =====================================================
            // 10. UPDATE DATA QUOTATION DARI HASIL PERHITUNGAN
            // =====================================================
            $this->updateQuotationDataFromCalculation($calculationResult, $user, $currentDateTime);

            DB::commit();

            \Log::info("updateAllQuotationData completed successfully", [
                'quotation_id' => $quotation->id,
                'step' => 11
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error in updateAllQuotationData", [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    /** 
     * Prepare quotation data for update dari request Step 11
     */
    private function prepareQuotationDataForUpdate(Quotation $quotation, Request $request, string $user, Carbon $currentDateTime): array
    {
        $data = [
            'updated_by' => $user,
            'updated_at' => $currentDateTime
        ];

        // Penagihan (wajib ada di Step 11)
        if ($request->has('penagihan')) {
            $data['penagihan'] = $request->penagihan;
        } else {
            // Default value jika tidak ada
            $data['penagihan'] = $quotation->penagihan ?? 'Transfer';
        }

        // PERBAIKAN: Persentase management fee - hanya update jika ada nilai baru
        if ($request->filled('persentase')) {
            $persentase = $request->persentase;
            if (is_string($persentase) && !is_numeric($persentase)) {
                $persentase = (float) str_replace(['.', ','], ['', '.'], $persentase);
            }
            $data['persentase'] = $persentase;

            \Log::info("Persentase updated from request", [
                'quotation_id' => $quotation->id,
                'old_persentase' => $quotation->persentase,
                'new_persentase' => $persentase
            ]);
        } else {
            // Jika tidak ada request, gunakan nilai yang sudah ada di database
            $data['persentase'] = $quotation->persentase;

            \Log::info("Persentase not in request, using existing value", [
                'quotation_id' => $quotation->id,
                'persentase' => $quotation->persentase
            ]);
        }

        // Persen insentif (jika ada di request)
        if ($request->filled('persen_insentif')) {
            $persenInsentif = $request->persen_insentif;
            if (is_string($persenInsentif) && !is_numeric($persenInsentif)) {
                $persenInsentif = (float) str_replace(['.', ','], ['', '.'], $persenInsentif);
            }
            $data['persen_insentif'] = $persenInsentif;

            \Log::info("Persen insentif updated from request", [
                'quotation_id' => $quotation->id,
                'old_persen_insentif' => $quotation->persen_insentif,
                'new_persen_insentif' => $persenInsentif
            ]);
        } else {
            // Jika tidak ada request, gunakan nilai yang sudah ada di database
            $data['persen_insentif'] = $quotation->persen_insentif;
        }

        // Persen bunga bank (jika ada di request)
        if ($request->filled('persen_bunga_bank')) {
            $persenBungaBank = $request->persen_bunga_bank;
            if (is_string($persenBungaBank) && !is_numeric($persenBungaBank)) {
                $persenBungaBank = (float) str_replace(['.', ','], ['', '.'], $persenBungaBank);
            }
            $data['persen_bunga_bank'] = $persenBungaBank;
        } else {
            // Jika tidak ada request, gunakan nilai yang sudah ada di database
            $data['persen_bunga_bank'] = $quotation->persen_bunga_bank;
        }

        // Note harga jual (opsional)
        if ($request->filled('note_harga_jual')) {
            $data['note_harga_jual'] = $request->note_harga_jual;
        } else {
            // Jika tidak ada request, gunakan nilai yang sudah ada di database
            $data['note_harga_jual'] = $quotation->note_harga_jual;
        }

        return $data;
    }
    /**
     * Update persentase BPJS dari request Step 11
     */
    private function updateBpjsPersentaseFromRequest(Quotation $quotation, array $bpjsPersentaseData, string $user, Carbon $currentDateTime): void
    {
        \Log::info("Updating BPJS persentase from request - DETAILED LOG", [
            'quotation_id' => $quotation->id,
            'details_count' => count($bpjsPersentaseData),
            'data_received' => $bpjsPersentaseData
        ]);

        $updatedCount = 0;

        foreach ($bpjsPersentaseData as $detailId => $bpjsData) {
            $detail = QuotationDetail::where('id', $detailId)
                ->where('quotation_id', $quotation->id)
                ->first();

            if (!$detail) {
                \Log::warning("Detail not found for BPJS persentase update", [
                    'detail_id' => $detailId,
                    'quotation_id' => $quotation->id
                ]);
                continue;
            }

            $updateData = [];

            // Field persentase BPJS yang bisa diedit
            $bpjsFields = [
                'persen_bpjs_jkk' => 'jkk',
                'persen_bpjs_jkm' => 'jkm',
                'persen_bpjs_jht' => 'jht',
                'persen_bpjs_jp' => 'jp',
                'persen_bpjs_kes' => 'kes'
            ];

            foreach ($bpjsFields as $field => $key) {
                if (isset($bpjsData[$key])) {
                    $value = $bpjsData[$key];

                    // Konversi string ke float
                    if (is_string($value)) {
                        $value = (float) str_replace(['.', ','], ['', '.'], $value);
                    }

                    $updateData[$field] = (float) $value;
                }
            }

            if (!empty($updateData)) {
                $updateData['updated_by'] = $user;
                $updateData['updated_at'] = $currentDateTime;

                // ================================================
                // **PERBAIKAN KRITIS**: CLEAR NILAI BPJS DI HPP
                // ================================================
                $hpp = QuotationDetailHpp::where('quotation_detail_id', $detailId)->first();
                if ($hpp) {
                    // Clear nilai nominal BPJS di HPP agar dihitung ulang
                    $hpp->update([
                        'bpjs_jkk' => null,
                        'bpjs_jkm' => null,
                        'bpjs_jht' => null,
                        'bpjs_jp' => null,
                        'bpjs_ks' => null,
                        'updated_by' => $user,
                        'updated_at' => $currentDateTime
                    ]);

                    \Log::info("Cleared HPP BPJS values to force recalculation", [
                        'detail_id' => $detailId,
                        'hpp_id' => $hpp->id
                    ]);
                }

                // Update detail dengan persentase baru
                $detail->update($updateData);
                $updatedCount++;

                \Log::info("Updated BPJS persentase and cleared HPP values", [
                    'detail_id' => $detailId,
                    'update_data' => $updateData
                ]);
            }
        }

        \Log::info("BPJS persentase update completed", [
            'quotation_id' => $quotation->id,
            'details_updated' => $updatedCount,
            'total_requested' => count($bpjsPersentaseData)
        ]);
    }

    /**
     * Update data quotation lainnya (tunjangan, dll)
     */
    private function updateOtherQuotationData(Quotation $quotation, Request $request, string $user, Carbon $currentDateTime): void
    {
        // Sync tunjangan data
        if ($request->has('tunjangan_data') && is_array($request->tunjangan_data)) {
            $this->syncTunjanganData($quotation, $request->tunjangan_data, $currentDateTime, $user);

            \Log::info("Tunjangan data synced", [
                'quotation_id' => $quotation->id,
                'tunjangan_data_count' => count($request->tunjangan_data)
            ]);
        }

        // Jika ada field lain yang perlu diupdate di Step 11, tambahkan di sini
    }
    /**
     * Process wage data dari request Step 11
     */
    private function processWageDataFromRequest(Quotation $quotation, array $wageData, string $user, Carbon $currentDateTime): void
    {
        \Log::info("Processing wage data from request", [
            'quotation_id' => $quotation->id,
            'wage_data_count' => count($wageData)
        ]);

        $updatedCount = 0;

        foreach ($wageData as $detailId => $wageFields) {
            try {
                $this->updateSingleWageData($detailId, $wageFields, $user, $currentDateTime, $quotation->id);
                $updatedCount++;
            } catch (\Exception $e) {
                \Log::error("Failed to update wage data for detail", [
                    'detail_id' => $detailId,
                    'error' => $e->getMessage()
                ]);
                // Continue dengan detail lain
            }
        }

        \Log::info("Wage data processing completed", [
            'quotation_id' => $quotation->id,
            'updated_count' => $updatedCount,
            'total_requested' => count($wageData)
        ]);
    }

    /**
     * Update single wage data
     */
    private function updateSingleWageData($detailId, array $wageFields, string $user, Carbon $currentDateTime, $quotationId): void
    {
        // Verifikasi detail belongs to quotation
        $detail = QuotationDetail::where('id', $detailId)
            ->where('quotation_id', $quotationId)
            ->first();

        if (!$detail) {
            \Log::warning("Detail not found or doesn't belong to quotation", [
                'detail_id' => $detailId,
                'quotation_id' => $quotationId
            ]);
            return;
        }

        $wage = QuotationDetailWage::where('quotation_detail_id', $detailId)->first();

        if (!$wage) {
            \Log::warning("Wage not found for detail", ['detail_id' => $detailId]);
            return;
        }

        $updateData = [];

        // Field yang diizinkan untuk diupdate di Step 11
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

                // Handle null values
                if ($value === null || (is_string($value) && trim($value) === '')) {
                    $updateData[$field] = null;
                    continue;
                }

                // Konversi nilai numerik
                if (in_array($field, ['nominal_lembur', 'nominal_tunjangan_holiday', 'jam_per_bulan_lembur'])) {
                    $updateData[$field] = $this->convertToFloat($value);
                } else {
                    $updateData[$field] = $value;
                }

                \Log::info("Setting wage field", [
                    'detail_id' => $detailId,
                    'field' => $field,
                    'value' => $value
                ]);
            }
        }

        if (!empty($updateData)) {
            $updateData['updated_by'] = $user;
            $updateData['updated_at'] = $currentDateTime;

            $wage->update($updateData);

            \Log::info("Updated wage data from request", [
                'detail_id' => $detailId,
                'wage_id' => $wage->id,
                'update_data' => $updateData
            ]);
        }
    }

    /**
     * Save semua hasil perhitungan ke database
     */
    private function saveAllCalculationResults(QuotationCalculationResult $calculationResult, string $user, Carbon $currentDateTime, Request $request = null): void
    {
        $savedHppCount = 0;
        $savedCossCount = 0;

        foreach ($calculationResult->detail_calculations as $detailId => $detailCalculation) {
            try {
                // Simpan ke QuotationDetailHpp
                $this->saveHppDataFromCalculation($detailCalculation, $calculationResult, $user, $currentDateTime, $request);
                $savedHppCount++;

                // Simpan ke QuotationDetailCoss
                $this->saveCossDataFromCalculation($detailCalculation, $calculationResult, $user, $currentDateTime, $request);
                $savedCossCount++;

            } catch (\Exception $e) {
                \Log::error("Error saving calculation results for detail", [
                    'detail_id' => $detailId,
                    'error' => $e->getMessage()
                ]);
                // Continue dengan detail lain
            }
        }

        \Log::info("Calculation results saved", [
            'quotation_id' => $calculationResult->quotation->id,
            'hpp_saved' => $savedHppCount,
            'coss_saved' => $savedCossCount,
            'total_details' => count($calculationResult->detail_calculations)
        ]);
    }

    /**
     * Helper method untuk update COSS data dari request
     */
    private function updateCossDataFromRequest($detailId, array $cossFields, string $user, Carbon $currentDateTime, $quotationId): void
    {
        $detail = QuotationDetail::where('id', $detailId)
            ->where('quotation_id', $quotationId)
            ->first();

        if (!$detail) {
            \Log::warning("Detail not found or doesn't belong to quotation", [
                'detail_id' => $detailId,
                'quotation_id' => $quotationId
            ]);
            return;
        }

        $coss = QuotationDetailCoss::where('quotation_detail_id', $detailId)->first();

        if ($coss) {
            $updateData = [];
            $allowedCossFields = [
                'provisi_seragam',
                'provisi_peralatan',
                'provisi_chemical',
                'provisi_ohc',
                'lembur',
                'tunjangan_hari_raya',
                'tunjangan_hari_libur_nasional',
            ];

            foreach ($allowedCossFields as $field) {
                if (isset($cossFields[$field])) {
                    $value = $cossFields[$field];
                    if (is_string($value) && !is_numeric($value)) {
                        $value = (float) str_replace(['.', ','], ['', '.'], $value);
                    }
                    $updateData[$field] = $value;
                }
            }

            if (!empty($updateData)) {
                $updateData['updated_by'] = $user;
                $updateData['updated_at'] = $currentDateTime;
                $coss->update($updateData);
            }
        }
    }
    /**
     * Update nominal upah dari request Step 11
     */
    private function updateQuotationDetailData(Quotation $quotation, array $detailData, string $user, Carbon $currentDateTime): void
    {
        \Log::info("Starting quotation detail update", [
            'quotation_id' => $quotation->id,
            'details_count' => count($detailData)
        ]);

        $statistics = [
            'total_updated' => 0,
            'custom_upah_count' => 0,
            'wage_updated_count' => 0,
            'failed_count' => 0
        ];

        foreach ($detailData as $detailId => $data) {
            try {
                // Ambil detail berdasarkan ID
                $detail = QuotationDetail::where('id', $detailId)
                    ->where('quotation_id', $quotation->id)
                    ->first();

                if (!$detail) {
                    \Log::warning("Detail not found", [
                        'detail_id' => $detailId,
                        'quotation_id' => $quotation->id
                    ]);
                    $statistics['failed_count']++;
                    continue;
                }

                // Update QuotationDetail
                $this->updateDetailRecord($detail, $data, $user, $currentDateTime, $statistics);

                // Update QuotationDetailWage jika ada
                $this->updateDetailWage($detail, $data, $user, $currentDateTime, $statistics);

                $statistics['total_updated']++;

                \Log::debug("Detail updated successfully", [
                    'detail_id' => $detailId,
                    'updated_fields' => array_keys($data)
                ]);

            } catch (\Exception $e) {
                \Log::error("Failed to update detail", [
                    'detail_id' => $detailId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $statistics['failed_count']++;
            }
        }

        \Log::info("Quotation detail update completed", array_merge(
            ['quotation_id' => $quotation->id],
            $statistics
        ));
    }

    private function updateDetailRecord(QuotationDetail $detail, array $data, string $user, Carbon $currentDateTime, array &$statistics): void
    {
        $updateData = [
            'updated_by' => $user,
            'updated_at' => $currentDateTime
        ];

        // Update nominal_upah jika ada
        if (isset($data['nominal_upah'])) {
            $nominalUpah = $this->convertToFloat($data['nominal_upah']);

            // Cek apakah upah custom
            $isCustomUpah = $this->isCustomUpah($detail, $nominalUpah);

            $updateData['nominal_upah'] = $nominalUpah;
            $updateData['is_custom_upah'] = $isCustomUpah ? 1 : 0;

            if ($isCustomUpah) {
                $statistics['custom_upah_count']++;
            }

            \Log::info("Updating nominal upah", [
                'detail_id' => $detail->id,
                'nominal_upah' => $nominalUpah,
                'is_custom' => $isCustomUpah
            ]);
        }

        // Update field lain jika diperlukan (contoh: jumlah_hc, dll)
        $allowedFields = ['jumlah_hc', 'jabatan_kebutuhan', 'nama_site'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        $detail->update($updateData);
    }

    private function updateDetailWage(QuotationDetail $detail, array $data, string $user, Carbon $currentDateTime, array &$statistics): void
    {
        $wage = QuotationDetailWage::where('quotation_detail_id', $detail->id)->first();

        if (!$wage) {
            \Log::debug("Wage not found for detail", ['detail_id' => $detail->id]);
            return;
        }

        $wageUpdateData = [
            'updated_by' => $user,
            'updated_at' => $currentDateTime
        ];

        $hasUpdate = false;

        // 1. Update upah jika nominal_upah diubah dan custom (dari detail_data)
        if (isset($data['nominal_upah']) && isset($detail->is_custom_upah) && $detail->is_custom_upah) {
            $wageUpdateData['upah'] = 'Custom';
            $wageUpdateData['hitungan_upah'] = 'Per Bulan';
            $hasUpdate = true;
        }

        // 2. Update field wage dari data (untuk backward compatibility)
        // Jika ada data wage langsung di detail_data (format lama)
        $wageFieldMapping = [
            'tunjangan_hari_raya' => 'thr',
            'kompensasi' => 'kompensasi',
            'lembur' => 'lembur',
            'nominal_lembur' => 'nominal_lembur',
            'tunjangan_holiday' => 'tunjangan_holiday',
            'nominal_tunjangan_holiday' => 'nominal_tunjangan_holiday',
            'lembur_ditagihkan' => 'lembur_ditagihkan'
        ];

        foreach ($wageFieldMapping as $inputField => $wageField) {
            if (isset($data[$inputField])) {
                $value = $this->convertToFloat($data[$inputField]);

                // Untuk field string (bukan numerik), jangan konversi
                if (in_array($inputField, ['lembur', 'kompensasi', 'thr', 'tunjangan_holiday', 'lembur_ditagihkan'])) {
                    $value = $data[$inputField];
                }

                $wageUpdateData[$wageField] = $value;
                $hasUpdate = true;

                \Log::info("Updating wage field from detail_data", [
                    'detail_id' => $detail->id,
                    'field' => $wageField,
                    'value' => $value
                ]);
            }
        }

        if ($hasUpdate) {
            $wage->update($wageUpdateData);
            $statistics['wage_updated_count']++;

            \Log::debug("Wage updated from detail_data", [
                'detail_id' => $detail->id,
                'wage_id' => $wage->id,
                'updated_fields' => array_keys($wageUpdateData)
            ]);
        }
    }

    private function isCustomUpah(QuotationDetail $detail, float $nominalUpah): bool
    {
        $site = QuotationSite::find($detail->quotation_site_id);

        if (!$site) {
            return false;
        }

        $umk = Umk::byCity($site->kota_id)->active()->first();
        $ump = Ump::byProvince($site->provinsi_id)->active()->first();

        $umkValue = $umk ? $umk->umk : 0;
        $umpValue = $ump ? $ump->ump : 0;

        // Cek apakah upah berbeda dari UMK atau UMP
        $isCustom = ($nominalUpah != $umkValue && $nominalUpah != $umpValue);

        if ($isCustom) {
            \Log::info("Custom upah detected", [
                'detail_id' => $detail->id,
                'nominal_upah' => $nominalUpah,
                'umk_value' => $umkValue,
                'ump_value' => $umpValue
            ]);
        }

        return $isCustom;
    }

    private function convertToFloat($value): ?float
    {
        // Handle null
        if ($value === null) {
            return null;
        }

        // Handle string kosong
        if (is_string($value) && trim($value) === '') {
            return null;
        }

        // Handle string dengan format angka
        if (is_string($value) && !is_numeric($value)) {
            return (float) str_replace(['.', ','], ['', '.'], $value);
        }

        return (float) $value;
    }
    /**
     * Update semua HPP editable data dari request Step 11
     */
    private function updateAllHppEditableData(Quotation $quotation, Request $request, string $user, Carbon $currentDateTime): void
    {
        if (!$request->has('hpp_editable_data') || !is_array($request->hpp_editable_data)) {
            \Log::info("No HPP editable data in request", [
                'quotation_id' => $quotation->id
            ]);
            return;
        }

        $updatedCount = 0;

        \Log::info("Processing hpp_editable_data", [
            'quotation_id' => $quotation->id,
            'data_count' => count($request->hpp_editable_data),
            'sample_data' => count($request->hpp_editable_data) > 0 ? $request->hpp_editable_data[array_key_first($request->hpp_editable_data)] : []
        ]);

        foreach ($request->hpp_editable_data as $detailId => $hppData) {
            $hpp = QuotationDetailHpp::where('quotation_detail_id', $detailId)->first();

            if ($hpp) {
                $updateData = [];

                // PERBAIKAN: Tambahkan semua field HPP yang bisa diedit
                $editableFields = ['tunjangan_hari_raya', 'kompensasi', 'jumlah_hc'];

                foreach ($editableFields as $field) {
                    if (isset($hppData[$field])) {
                        $value = $hppData[$field];

                        // Handle berbagai format nilai
                        if ($value === null) {
                            $updateData[$field] = null;
                        } elseif (is_string($value) && trim($value) === '') {
                            $updateData[$field] = null;
                        } elseif (is_string($value) && !is_numeric($value)) {
                            // Konversi string dengan format angka (misal: "1.000" -> 1000)
                            $updateData[$field] = (float) str_replace(['.', ','], ['', '.'], $value);
                        } else {
                            $updateData[$field] = (float) $value;
                        }

                        \Log::info("Updating HPP editable field", [
                            'detail_id' => $detailId,
                            'field' => $field,
                            'value' => $value,
                            'converted_value' => $updateData[$field]
                        ]);
                    }
                }

                if (!empty($updateData)) {
                    $updateData['updated_by'] = $user;
                    $updateData['updated_at'] = $currentDateTime;
                    $hpp->update($updateData);
                    $updatedCount++;

                    \Log::info("Updated HPP editable data", [
                        'detail_id' => $detailId,
                        'update_data' => $updateData
                    ]);
                }
            } else {
                \Log::warning("HPP not found for detail", [
                    'detail_id' => $detailId,
                    'quotation_id' => $quotation->id
                ]);
            }
        }

        \Log::info("HPP editable data update completed", [
            'quotation_id' => $quotation->id,
            'hpp_updated' => $updatedCount,
            'total_requested' => count($request->hpp_editable_data)
        ]);
    }

}