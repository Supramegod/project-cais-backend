<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuotationResource;
use App\Models\Client;
use App\Models\Company;
use App\Models\Customer;
use App\Models\HrisSite;
use App\Models\JabatanPic;
use App\Models\Loyalty;
use App\Models\Pks;
use App\Models\Leads;
use App\Models\KategoriSesuaiHc;
use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\QuotationDetailCoss;
use App\Models\QuotationDetailHpp;
use App\Models\QuotationMargin;
use App\Models\QuotationPic;
use App\Models\RuleThr;
use App\Models\SalaryRule;
use App\Models\Site;
use App\Models\PksPerjanjian;
use App\Models\CustomerActivity;
use App\Models\Kebutuhan;
use App\Models\Spk;
use App\Models\SpkSite;
use App\Services\PksPerjanjianTemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
/**
 * @OA\Tag(
 *     name="PKS",
 *     description="API untuk manajemen PKS (Perjanjian Kerja Sama)"
 * )
 */
class PksController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pks/list",
     *     summary="Get list of PKS",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tgl_dari",
     *         in="query",
     *         description="Start date",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-10-01")
     *     ),
     *     @OA\Parameter(
     *         name="tgl_sampai",
     *         in="query",
     *         description="End date",
     *         required=false,
     *         @OA\Schema(type="string", format="date",example="2025-11-01")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Status filter",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="nomor", type="string"),
     *                 @OA\Property(property="nama_perusahaan", type="string"),
     *                 @OA\Property(property="tgl_pks", type="string"),
     *                 @OA\Property(property="formatted_tgl_pks", type="string"),
     *                 @OA\Property(property="kontrak_awal", type="string"),
     *                 @OA\Property(property="kontrak_akhir", type="string"),
     *                 @OA\Property(property="formatted_kontrak_awal", type="string"),
     *                 @OA\Property(property="formatted_kontrak_akhir", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="berakhir_dalam", type="string"),
     *                 @OA\Property(property="status_berlaku", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="created_by", type="string")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'tgl_dari' => 'nullable|date',
                'tgl_sampai' => 'nullable|date|after_or_equal:tgl_dari',
                'status' => 'nullable|integer|exists:m_status_pks,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = Pks::with(['statusPks'])
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'desc');

            if (!empty($request->status)) {
                $query->where('status_pks_id', $request->status);
            }

            if (!empty($request->tgl_dari) && !empty($request->tgl_sampai)) {
                $query->whereBetween('tgl_pks', [
                    $request->tgl_dari,
                    $request->tgl_sampai
                ]);
            }

            $pksList = $query->get()->map(function ($pks) {
                return [
                    'id' => $pks->id,
                    'nomor' => $pks->nomor,
                    'nama_perusahaan' => $pks->nama_perusahaan,
                    'tgl_pks' => $pks->tgl_pks,
                    'kontrak_awal' => $pks->kontrak_awal,
                    'kontrak_akhir' => $pks->kontrak_akhir,
                    'formatted_kontrak_awal' => Carbon::parse($pks->kontrak_awal)->isoFormat('D MMMM Y'),
                    'formatted_kontrak_akhir' => Carbon::parse($pks->kontrak_akhir)->isoFormat('D MMMM Y'),
                    'status' => $pks->statusPks->nama ?? null,
                    'berakhir_dalam' => $this->hitungBerakhirKontrak($pks->kontrak_akhir),
                    'status_berlaku' => $this->getStatusBerlaku($pks->kontrak_akhir),
                    'created_at' => $pks->created_at,
                    'created_by' => $pks->created_by
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $pksList
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve PKS list',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/pks/view/{id}",
     *     summary="Get PKS details with mapped data",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="pks_mapped",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="nomor", type="string"),
     *                     @OA\Property(
     *                         property="activities",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer"),
     *                             @OA\Property(property="tgl_activity", type="string", format="date"),
     *                             @OA\Property(property="notes", type="string"),
     *                             @OA\Property(property="tipe", type="string"),
     *                             @OA\Property(property="created_by", type="string")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="leads_mapped",
     *                     type="object",
     *                     @OA\Property(property="nama_perusahaan", type="string"),
     *                     @OA\Property(property="nomor_leads", type="string"),
     *                     @OA\Property(property="kebutuhan_leads", type="string"),
     *                     @OA\Property(property="kota", type="string"),
     *                     @OA\Property(property="bidang_perusahaan", type="string"),
     *                     @OA\Property(property="pma_pmdn", type="string"),
     *                     @OA\Property(property="provinsi", type="string"),
     *                     @OA\Property(property="kecamatan", type="string"),
     *                     @OA\Property(property="kelurahan", type="string"),
     *                     @OA\Property(property="alamat", type="string"),
     *                     @OA\Property(property="pic", type="string"),
     *                     @OA\Property(property="jabatan", type="string")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="quotation_data",
     *                 type="array",
     *                 description="Array of Quotation details and calculations",
     *                 @OA\Items(type="object")
     *             ),
     *             @OA\Property(
     *                 property="spk_data",
     *                 type="array",
     *                 description="Array of SPK data",
     *                 @OA\Items(type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     )
     * )
     */
    public function show($id): JsonResponse
    {
        try {
            $pks = Pks::with([
                'leads',
                'statusPks',
                'sites',
                'spk.spkSites',
                'perjanjian',
                'activities',
                'ruleThr',
            ])->find($id);

            if (!$pks) {
                return response()->json(['success' => false, 'message' => 'PKS not found'], 404);
            }

            // Format dates
            $pks->formatted_kontrak_awal = Carbon::parse($pks->kontrak_awal)->isoFormat('D MMMM Y');
            $pks->formatted_kontrak_akhir = Carbon::parse($pks->kontrak_akhir)->isoFormat('D MMMM Y');
            $pks->berakhir_dalam = $this->hitungBerakhirKontrak($pks->kontrak_akhir);

            // MAPPED DATA - LEADS
            $leads_mapped = null;
            if ($pks->leads) {
                $leads = $pks->leads;

                // Ambil data kebutuhan dari leads
                $kebutuhan_leads = null;
                if ($leads->relationLoaded('kebutuhan') && $leads->kebutuhan) {
                    $kebutuhan_leads = $leads->kebutuhan->nama;
                }

                $leads_mapped = [
                    'id' => $leads->id,
                    'nama_perusahaan' => $leads->nama_perusahaan ?? null,
                    'nomor_leads' => $leads->nomor ?? null,
                    'kebutuhan_leads' => $kebutuhan_leads,
                    'negara' => $leads->negara ?? null,
                    'bidang_perusahaan' => $leads->bidang_perusahaan ?? null,
                    'pma_pmdn' => $leads->pma ?? null,
                    'provinsi' => $leads->provinsi ?? null,
                    'kecamatan' => $leads->kecamatan ?? null,
                    'kelurahan' => $leads->kelurahan ?? null,
                    'alamat' => $leads->alamat ?? null,
                    'pic' => $leads->pic ?? null,
                    'jabatan' => $leads->jabatan ?? null

                ];
            }

            // MAPPED DATA - PKS
            $pks_mapped = [
                'id' => $pks->id,
                'nomor' => $pks->nomor ?? null,
                'activities' => $pks->activities->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'tgl_activity' => $activity->tgl_activity,
                        'notes' => $activity->notes,
                        'tipe' => $activity->tipe,
                        'created_by' => $activity->created_by
                    ];
                })->toArray(),
                'perjanjian' => $pks->perjanjian->map(function ($perjanjian) {
                    return [
                        'id' => $perjanjian->id,
                        'pasal' => $perjanjian->pasal,
                        'judul' => $perjanjian->judul,
                        'raw_text' => $perjanjian->raw_text,
                        'created_by' => $perjanjian->created_by
                    ];
                })->toArray(),
                'rule_thr' => $pks->ruleThr ? [
                    'id' => $pks->ruleThr->id,
                    'nama' => $pks->ruleThr->nama,
                    'hari_rilis_thr' => $pks->ruleThr->hari_rilis_thr,
                    'hari_pembayaran_invoice' => $pks->ruleThr->hari_pembayaran_invoice,
                    'hari_penagihan_invoice' => $pks->ruleThr->hari_penagihan_invoice
                ] : null
            ];

            // QUOTATION DATA
            $quotationDataArray = [];
            $spkarray = [];

            if ($pks->sites->isNotEmpty()) {
                $data = Site::where('pks_id', $pks->id)
                    ->whereNotNull('quotation_id')
                    ->whereNull('deleted_at')
                    ->select('quotation_id', 'spk_id')
                    ->distinct()
                    ->get();

                foreach ($data as $dataid) {
                    $quotation = Quotation::with([
                        'quotationDetails.quotationDetailHpps',
                        'quotationDetails.quotationDetailCosses',
                        'quotationDetails.wage',
                        'quotationDetails.quotationDetailRequirements',
                        'quotationDetails.quotationDetailTunjangans',
                        'leads',
                        'statusQuotation',
                        'quotationSites',
                        'quotationPics',
                        'quotationAplikasis',
                        'quotationKaporlaps',
                        'quotationDevices',
                        'quotationChemicals',
                        'quotationOhcs',
                        'quotationTrainings',
                        'quotationKerjasamas',
                        'managementFee'
                    ])->find($dataid->quotation_id);

                    if ($quotation) {
                        $quotationDataArray[] = new QuotationResource($quotation);
                    }

                    $spk = Spk::select('id', 'nomor', 'leads_id', 'tgl_spk')
                        ->find($dataid->spk_id);

                    if ($spk) {
                        $spkarray[] = $spk;
                    }
                }
            }

            // SITES INFO - PERBAIKAN DI SINI
            $sitesInfo = [];

            // Cek apakah relasi spk ada dan tidak null
            if ($pks->spk && $pks->spk->spkSites) {
                $sitesInfo = $pks->spk->spkSites->map(function ($site) {
                    return [
                        'id' => $site->id,
                        'nama_site' => $site->nama_site,
                        'kota' => $site->kota,
                        'penempatan' => $site->penempatan,
                        'quotation_id' => $site->quotation_id
                    ];
                })->toArray();
            } else {
                // Alternatif: Ambil sites info dari relasi sites yang sudah ada
                $sitesInfo = $pks->sites->map(function ($site) {
                    return [
                        'id' => $site->id,
                        'nama_site' => $site->nama_site,
                        'kota' => $site->kota,
                        'penempatan' => $site->penempatan,
                        'quotation_id' => $site->quotation_id
                    ];
                })->toArray();
            }

            $response = [
                'success' => true,
                'data' => [
                    'pks_mapped' => $pks_mapped,
                    'leads_mapped' => $leads_mapped,
                ],
                'quotation_data' => $quotationDataArray,
                'spk_data' => $spkarray,
                'sites_info' => $sitesInfo
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('Failed to retrieve PKS details: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve PKS details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // /**
    //  * Format quotation data according to case 11 structure
    //  *//**
    //  * Format quotation data according to case 11 structure
    //  */
    // private function formatQuotationCase11($quotation)
    // {
    //     // PERBAIKAN: Gunakan QuotationService untuk menghitung quotation
    //     $quotationService = new \App\Services\QuotationService();
    //     $calculatedQuotation = $quotationService->calculateQuotation($quotation);

    //     // Sekarang kita punya calculation_summary
    //     $summary = $calculatedQuotation->calculation_summary ?? null;
    //     $persenBpjsTotalCoss = 0;
    //     $persenBpjsBreakdownHpp = [];
    //     $persenBpjsBreakdownCoss = [];

    //     if ($summary) {
    //         // Untuk HPP
    //         $persenBpjsTotalHpp = $summary->persen_bpjs_ketenagakerjaan ?? 0;
    //         $persenBpjsBreakdownHpp = [
    //             'persen_bpjs_jkk' => $summary->persen_bpjs_jkk ?? 0,
    //             'persen_bpjs_jkm' => $summary->persen_bpjs_jkm ?? 0,
    //             'persen_bpjs_jht' => $summary->persen_bpjs_jht ?? 0,
    //             'persen_bpjs_jp' => $summary->persen_bpjs_jp ?? 0,
    //         ];

    //         // Untuk COSS
    //         $persenBpjsTotalCoss = $summary->persen_bpjs_ketenagakerjaan_coss ?? 0;
    //         $persenBpjsBreakdownCoss = [
    //             'persen_bpjs_jkk' => $summary->persen_bpjs_jkk_coss ?? 0,
    //             'persen_bpjs_jkm' => $summary->persen_bpjs_jkm_coss ?? 0,
    //             'persen_bpjs_jht' => $summary->persen_bpjs_jht_coss ?? 0,
    //             'persen_bpjs_jp' => $summary->persen_bpjs_jp_coss ?? 0,
    //         ];
    //     }

    //     return [
    //         'quotation_id' => $quotation->id,
    //         'nomor_quotation' => $quotation->nomor,
    //         'jenis_kontrak' => $quotation->jenis_kontrak,
    //         'penagihan' => $quotation->penagihan,
    //         'nama_perusahaan' => $quotation->nama_perusahaan,
    //         'persentase' => $quotation->persentase,
    //         'management_fee_nama' => $quotation->managementFee->nama ?? null,
    //         'ppn_pph_dipotong' => $quotation->ppn_pph_dipotong,
    //         'note_harga_jual' => $quotation->note_harga_jual,
    //         'quotation_pics' => $quotation->relationLoaded('quotationPics') ?
    //             $quotation->quotationPics->map(function ($pic) {
    //                 return [
    //                     'id' => $pic->id,
    //                     'nama' => $pic->nama,
    //                     'jabatan_id' => $pic->jabatan_id,
    //                     'no_telp' => $pic->no_telp,
    //                     'email' => $pic->email,
    //                     'is_kuasa' => $pic->is_kuasa,
    //                 ];
    //             })->toArray() : [],
    //         // Data perhitungan dari calculation_summary
    //         'calculation' => $summary ? [
    //             'bpu' => [
    //                 'total_potongan_bpu' => $summary->total_potongan_bpu ?? 0,
    //                 'potongan_bpu_per_orang' => $summary->potongan_bpu_per_orang ?? 0,
    //             ],
    //             'hpp' => [
    //                 'total_sebelum_management_fee' => $summary->total_sebelum_management_fee ?? 0,
    //                 'nominal_management_fee' => $summary->nominal_management_fee ?? 0,
    //                 'grand_total_sebelum_pajak' => $summary->grand_total_sebelum_pajak ?? 0,
    //                 'ppn' => $summary->ppn ?? 0,
    //                 'pph' => $summary->pph ?? 0,
    //                 'dpp' => $summary->dpp ?? 0,
    //                 'total_invoice' => $summary->total_invoice ?? 0,
    //                 'pembulatan' => $summary->pembulatan ?? 0,
    //                 'margin' => $summary->margin ?? 0,
    //                 'gpm' => $summary->gpm ?? 0,
    //                 'persen_bunga_bank' => $quotation->persen_bunga_bank ?? 0,
    //                 'persen_insentif' => $quotation->persen_insentif ?? 0,
    //                 'persen_bpjs_total' => $persenBpjsTotalHpp,
    //                 'persen_bpjs_ksht' => $summary->persen_bpjs_kesehatan ?? 0,
    //                 'persen_bpjs_breakdown' => $persenBpjsBreakdownHpp,
    //             ],
    //             'coss' => [
    //                 'total_sebelum_management_fee_coss' => $summary->total_sebelum_management_fee_coss ?? 0,
    //                 'nominal_management_fee_coss' => $summary->nominal_management_fee_coss ?? 0,
    //                 'grand_total_sebelum_pajak_coss' => $summary->grand_total_sebelum_pajak_coss ?? 0,
    //                 'ppn_coss' => $summary->ppn_coss ?? 0,
    //                 'pph_coss' => $summary->pph_coss ?? 0,
    //                 'dpp_coss' => $summary->dpp_coss ?? 0,
    //                 'total_invoice_coss' => $summary->total_invoice_coss ?? 0,
    //                 'pembulatan_coss' => $summary->pembulatan_coss ?? 0,
    //                 'margin_coss' => $summary->margin_coss ?? 0,
    //                 'gpm_coss' => $summary->gpm_coss ?? 0,
    //                 'persen_bunga_bank' => $quotation->persen_bunga_bank ?? 0,
    //                 'persen_insentif' => $quotation->persen_insentif ?? 0,
    //                 'persen_bpjs_total' => $persenBpjsTotalCoss,
    //                 'persen_bpjs_ksht' => $summary->persen_bpjs_kesehatan_coss ?? 0,
    //                 'persen_bpjs_breakdown' => $persenBpjsBreakdownCoss,
    //             ],
    //             'quotation_details' => $quotation->quotationDetails->map(function ($detail) {
    //                 $wage = $detail->wage ?? null;
    //                 $potonganBpu = $detail->potongan_bpu ?? 0;

    //                 $bpjsJkk = $detail->bpjs_jkk ?? 0;
    //                 $bpjsJkm = $detail->bpjs_jkm ?? 0;
    //                 $bpjsJht = $detail->bpjs_jht ?? 0;
    //                 $bpjsJp = $detail->bpjs_jp ?? 0;
    //                 $bpjsKes = $detail->bpjs_kes ?? 0;
    //                 $bpjsKetenagakerjaan = $bpjsJkk + $bpjsJkm + $bpjsJht + $bpjsJp;

    //                 $bpjsKesehatan = 0;
    //                 if ($detail->penjamin_kesehatan === 'BPJS' || $detail->penjamin_kesehatan === 'BPJS Kesehatan') {
    //                     $bpjsKesehatan = $bpjsKes;
    //                 } else if ($detail->penjamin_kesehatan === 'Asuransi Swasta' || $detail->penjamin_kesehatan === 'Takaful') {
    //                     $bpjsKesehatan = $detail->nominal_takaful ?? 0;
    //                 } else if ($detail->penjamin_kesehatan === 'BPU') {
    //                     $bpjsKesehatan = 0;
    //                 }

    //                 $tunjanganData = [];
    //                 if ($detail->relationLoaded('quotationDetailTunjangans')) {
    //                     $tunjanganData = $detail->quotationDetailTunjangans->map(function ($tunjangan) {
    //                         return [
    //                             'nama_tunjangan' => $tunjangan->nama_tunjangan,
    //                             'nominal' => $tunjangan->nominal,
    //                         ];
    //                     })->toArray();
    //                 }

    //                 $lemburDisplay = '';
    //                 if ($wage) {
    //                     if ($wage->lembur == 'Normatif' || $wage->lembur_ditagihkan == 'Ditagihkan Terpisah') {
    //                         $lemburDisplay = 'Ditagihkan terpisah';
    //                     } elseif ($wage->lembur == 'Flat') {
    //                         $lemburDisplay = 'Rp. ' . number_format($detail->lembur, 2, ',', '.');
    //                     } else {
    //                         $lemburDisplay = 'Tidak Ada';
    //                     }
    //                 }

    //                 $tunjanganHolidayDisplay = '';
    //                 if ($wage) {
    //                     if ($wage->tunjangan_holiday == 'Normatif') {
    //                         $tunjanganHolidayDisplay = 'Ditagihkan terpisah';
    //                     } elseif ($wage->tunjangan_holiday == 'Flat') {
    //                         $tunjanganHolidayDisplay = 'Rp. ' . number_format($detail->tunjangan_holiday, 2, ',', '.');
    //                     } else {
    //                         $tunjanganHolidayDisplay = 'Tidak Ada';
    //                     }
    //                 }

    //                 return [
    //                     'id' => $detail->id,
    //                     'position_name' => $detail->jabatan_kebutuhan,
    //                     'jumlah_hc' => $detail->jumlah_hc,
    //                     'nama_site' => $detail->nama_site,
    //                     'kebutuhan' => $detail->kebutuhan,
    //                     'kota_site' => $detail->quotationSite->kota,
    //                     'quotation_site_id' => $detail->quotation_site_id,
    //                     'penjamin_kesehatan' => $detail->penjamin_kesehatan,
    //                     'tunjangan_data' => $tunjanganData,
    //                     'hpp' => [
    //                         'nominal_upah' => $detail->nominal_upah,
    //                         'total_tunjangan' => $detail->total_tunjangan,
    //                         'bpjs_ketenagakerjaan' => $bpjsKetenagakerjaan,
    //                         'bpjs_kesehatan' => $bpjsKesehatan,
    //                         'bpjs_jkk' => $bpjsJkk,
    //                         'bpjs_jkm' => $bpjsJkm,
    //                         'bpjs_jht' => $bpjsJht,
    //                         'bpjs_jp' => $bpjsJp,
    //                         'bpjs_kes' => $bpjsKes,
    //                         'persen_bpjs_jkk' => $detail->persen_bpjs_jkk ?? 0,
    //                         'persen_bpjs_jkm' => $detail->persen_bpjs_jkm ?? 0,
    //                         'persen_bpjs_jht' => $detail->persen_bpjs_jht ?? 0,
    //                         'persen_bpjs_jp' => $detail->persen_bpjs_jp ?? 0,
    //                         'persen_bpjs_kes' => $detail->persen_bpjs_kes ?? 0,
    //                         'persen_bpjs_ketenagakerjaan' => $detail->persen_bpjs_ketenagakerjaan ?? 0,
    //                         'persen_bpjs_kesehatan' => $detail->persen_bpjs_kesehatan ?? 0,
    //                         'potongan_bpu' => $potonganBpu,
    //                         'tunjangan_hari_raya' => $detail->tunjangan_hari_raya,
    //                         'kompensasi' => $detail->kompensasi,
    //                         'lembur' => $lemburDisplay,
    //                         'tunjangan_holiday' => $tunjanganHolidayDisplay,
    //                         'bunga_bank' => $detail->bunga_bank,
    //                         'insentif' => $detail->insentif,
    //                         'personil_kaporlap' => $detail->personil_kaporlap ?? 0,
    //                         'personil_devices' => $detail->personil_devices ?? 0,
    //                         'personil_ohc' => $detail->personil_ohc ?? 0,
    //                         'personil_chemical' => $detail->personil_chemical ?? 0,
    //                         'total_personil' => $detail->total_personil,
    //                         'sub_total_personil' => $detail->sub_total_personil,
    //                         'total_base_manpower' => $detail->total_base_manpower ?? 0,
    //                         'total_exclude_base_manpower' => $detail->total_exclude_base_manpower ?? 0,
    //                     ],
    //                     'coss' => [
    //                         'nominal_upah' => $detail->nominal_upah,
    //                         'total_tunjangan' => $detail->total_tunjangan,
    //                         'bpjs_ketenagakerjaan' => $bpjsKetenagakerjaan,
    //                         'bpjs_kesehatan' => $bpjsKesehatan,
    //                         'bpjs_jkk' => $bpjsJkk,
    //                         'bpjs_jkm' => $bpjsJkm,
    //                         'bpjs_jht' => $bpjsJht,
    //                         'bpjs_jp' => $bpjsJp,
    //                         'bpjs_kes' => $bpjsKes,
    //                         'persen_bpjs_jkk' => $detail->persen_bpjs_jkk ?? 0,
    //                         'persen_bpjs_jkm' => $detail->persen_bpjs_jkm ?? 0,
    //                         'persen_bpjs_jht' => $detail->persen_bpjs_jht ?? 0,
    //                         'persen_bpjs_jp' => $detail->persen_bpjs_jp ?? 0,
    //                         'persen_bpjs_kes' => $detail->persen_bpjs_kes ?? 0,
    //                         'persen_bpjs_ketenagakerjaan' => $detail->persen_bpjs_ketenagakerjaan ?? 0,
    //                         'persen_bpjs_kesehatan' => $detail->persen_bpjs_kesehatan ?? 0,
    //                         'potongan_bpu' => $potonganBpu,
    //                         'tunjangan_hari_raya' => $detail->tunjangan_hari_raya,
    //                         'kompensasi' => $detail->kompensasi,
    //                         'lembur' => $lemburDisplay,
    //                         'tunjangan_holiday' => $tunjanganHolidayDisplay,
    //                         'bunga_bank' => $detail->bunga_bank,
    //                         'insentif' => $detail->insentif,
    //                         'personil_kaporlap_coss' => $detail->personil_kaporlap_coss ?? 0,
    //                         'personil_devices_coss' => $detail->personil_devices_coss ?? 0,
    //                         'personil_ohc_coss' => $detail->personil_ohc_coss ?? 0,
    //                         'personil_chemical_coss' => $detail->personil_chemical_coss ?? 0,
    //                         'total_personil' => $detail->total_personil_coss ?? 0,
    //                         'sub_total_personil' => $detail->sub_total_personil_coss ?? 0,
    //                         'total_base_manpower' => $detail->total_base_manpower ?? 0,
    //                         'total_exclude_base_manpower' => $detail->total_exclude_base_manpower ?? 0,
    //                     ]
    //                 ];
    //             })->toArray()
    //         ] : null,
    //     ];
    // }

    /**
     * @OA\Post(
     *     path="/api/pks/add",
     *     summary="Create new PKS",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leads_id", "site_ids", "tanggal_pks", "tanggal_awal_kontrak", "tanggal_akhir_kontrak", "kategoriHC", "loyalty", "salary_rule", "rule_thr", "entitas"},
     *             @OA\Property(property="leads_id", type="integer", example=38),
     *             @OA\Property(
     *                 property="site_ids", 
     *                 type="array", 
     *                 @OA\Items(type="integer"),
     *                 example={130}
     *             ),
     *             @OA\Property(property="tanggal_pks", type="string", format="date", example="2025-10-14"),
     *             @OA\Property(property="tanggal_awal_kontrak", type="string", format="date", example="2025-11-01"),
     *             @OA\Property(property="tanggal_akhir_kontrak", type="string", format="date", example="2026-10-31"),
     *             @OA\Property(property="kategoriHC", type="integer", example=1, description="Kategori HC ID"),
     *             @OA\Property(property="loyalty", type="integer", example=1, description="Loyalty ID"),
     *             @OA\Property(property="salary_rule", type="integer", example=1, description="Salary Rule ID"),
     *             @OA\Property(property="rule_thr", type="integer", example=1, description="Rule THR ID"),
     *             @OA\Property(property="entitas", type="integer", example=1, description="Entitas ID"),
     *             @OA\Property(
     *                 property="perjanjian_data", 
     *                 type="object", 
     *                 description="Template data for frontend to generate agreement",
     *                 example={
     *                     "nomor_perjanjian": "PKS/2025/001",
     *                     "pihak_pertama": "PT ABC",
     *                     "pihak_kedua": "PT Client XYZ",
     *                     "lokasi_penandatanganan": "Jakarta"
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="PKS created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="PKS berhasil dibuat"),
     *             @OA\Property(
     *                 property="data", 
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nomor", type="string", example="PKS/2025/001"),
     *                 @OA\Property(property="leads_id", type="integer", example=1),
     *                 @OA\Property(property="tanggal_pks", type="string", example="14-10-2025"),
     *                 @OA\Property(property="tanggal_awal_kontrak", type="string", example="01-11-2025"),
     *                 @OA\Property(property="tanggal_akhir_kontrak", type="string", example="31-10-2026")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors", 
     *                 type="object",
     *                 example={
     *                     "leads_id": {"The leads id field is required."},
     *                     "site_ids": {"The site ids field is required."}
     *                 }
     *             )
     *         )
     *     )
     * )
     */

    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'leads_id' => 'required|integer|exists:sl_leads,id',
                'site_ids' => 'required|array',
                'site_ids.*' => 'integer|exists:sl_spk_site,id',
                'tanggal_pks' => 'required|date',
                'tanggal_awal_kontrak' => 'required|date',
                'tanggal_akhir_kontrak' => 'required|date|after:tanggal_awal_kontrak',
                'kategoriHC' => 'required|integer|exists:m_kategori_sesuai_hc,id',
                'loyalty' => 'required|integer|exists:m_loyalty,id',
                'salary_rule' => 'required|integer|exists:m_salary_rule,id',
                'rule_thr' => 'required|integer|exists:m_rule_thr,id',
                'entitas' => 'required|integer|exists:m_company,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $pksData = $this->createPks($request);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PKS created successfully',
                'data' => $pksData
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/pks/update/{id}",
     *     summary="Update PKS",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="PKS ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="tanggal_pks", type="string", format="date", example="2025-10-14"),
     *             @OA\Property(property="tanggal_awal_kontrak", type="string", format="date", example="2025-11-01"),
     *             @OA\Property(property="tanggal_akhir_kontrak", type="string", format="date", example="2026-10-31"),
     *             @OA\Property(property="status_pks_id", type="integer", example=2, description="Status PKS ID (1=Draft, 2=Active, 3=Expired, etc.)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PKS updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="PKS berhasil diupdate"),
     *             @OA\Property(
     *                 property="data", 
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nomor", type="string", example="PKS/2025/001"),
     *                 @OA\Property(property="tanggal_pks", type="string", example="14-10-2025"),
     *                 @OA\Property(property="tanggal_awal_kontrak", type="string", example="01-11-2025"),
     *                 @OA\Property(property="tanggal_akhir_kontrak", type="string", example="31-10-2026"),
     *                 @OA\Property(property="status_pks_id", type="integer", example=2)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="PKS tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors", 
     *                 type="object",
     *                 example={
     *                     "tanggal_pks": {"The tanggal pks must be a valid date."}
     *                 }
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $pks = Pks::find($id);

            if (!$pks) {
                return response()->json([
                    'success' => false,
                    'message' => 'PKS not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'tanggal_pks' => 'sometimes|date',
                'tanggal_awal_kontrak' => 'sometimes|date',
                'tanggal_akhir_kontrak' => 'sometimes|date|after:tanggal_awal_kontrak',
                'status_pks_id' => 'sometimes|integer|exists:m_status_pks,id',
                'is_aktif' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Simpan data lama untuk pengecekan
            $oldIsAktif = $pks->is_aktif;
            $oldKontrakAkhir = $pks->kontrak_akhir;

            $pks->update($request->all());

            // Jika ada perubahan pada is_aktif atau kontrak_akhir, sync customer_active
            if ($oldIsAktif != $pks->is_aktif || $oldKontrakAkhir != $pks->kontrak_akhir) {
                $this->autoSyncCustomerActiveStatus();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PKS updated successfully',
                'data' => $pks
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/pks/delete/{id}",
     *     summary="Delete PKS",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PKS deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     )
     * )
     */
    public function destroy($id): JsonResponse
    {
        try {
            $pks = Pks::find($id);

            if (!$pks) {
                return response()->json([
                    'success' => false,
                    'message' => 'PKS not found'
                ], 404);
            }

            $pks->delete();

            return response()->json([
                'success' => true,
                'message' => 'PKS deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/pks/{id}/approve",
     *     summary="Approve PKS",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ot"},
     *             @OA\Property(property="ot", type="integer", description="Approval level (1-4)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PKS approved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     )
     * )
     */
    public function approve(Request $request, $id): JsonResponse
    {
        try {
            $pks = Pks::find($id);

            if (!$pks) {
                return response()->json([
                    'success' => false,
                    'message' => 'PKS not found'
                ], 404);
            }

            $this->approvePks($pks, $request->ot);

            return response()->json([
                'success' => true,
                'message' => 'PKS approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/pks/{id}/activate",
     *     summary="Activate PKS sites with full synchronization",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PKS sites activated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     )
     * )
     */
    public function activate(Request $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            DB::connection('mysqlhris')->beginTransaction();

            $current_date_time = Carbon::now()->toDateTimeString();
            $pks = Pks::find($id);

            if (!$pks) {
                return response()->json([
                    'success' => false,
                    'message' => 'PKS not found'
                ], 404);
            }

            // Step 1: Update PKS and Leads Status
            $leads = $this->updatePksAndLeadsStatus($pks, $current_date_time);

            // Step 2: Sync Customer to HRIS
            $clientId = $this->syncCustomerToHris($leads, $current_date_time);

            // Step 3: Process PKS Sites
            $this->processPksSites($pks, $leads, $clientId, $current_date_time);

            // Step 4: Create Customer Activity Log
            $this->createCustomerActivityLog($pks, $leads, $current_date_time);

            // Step 5: Handle Customer Creation/Update
            $this->handleCustomerStatus($pks, $leads, $current_date_time);

            DB::commit();
            DB::connection('mysqlhris')->commit();

            return response()->json([
                'success' => true,
                'message' => 'PKS sites activated successfully with HRIS synchronization'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            DB::connection('mysqlhris')->rollBack();

            \Log::error('Failed to activate PKS sites: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/pks/{id}/perjanjian",
     *     summary="Get PKS perjanjian data for template",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", description="Template data for frontend")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     )
     * )
     */
    public function getPerjanjianTemplateData($id): JsonResponse
    {
        try {
            $pks = Pks::with(['leads', 'sites'])->find($id);

            if (!$pks) {
                return response()->json([
                    'success' => false,
                    'message' => 'PKS not found'
                ], 404);
            }

            $templateData = $this->getTemplateData($pks);

            return response()->json([
                'success' => true,
                'data' => $templateData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/pks/available-leads",
     *     summary="Get available leads for PKS creation",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="nomor", type="string"),
     *                 @OA\Property(property="nama_perusahaan", type="string"),
     *                 @OA\Property(property="provinsi", type="string"),
     *                 @OA\Property(property="kota", type="string")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getAvailableLeads(): JsonResponse
    {
        try {
            $leads = $this->getAvailableLeadsData();

            return response()->json([
                'success' => true,
                'data' => $leads
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/pks/available-sites/{leadsId}",
     *     summary="Get available sites for leads",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="leadsId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="nomor", type="string"),
     *                 @OA\Property(property="nama_site", type="string"),
     *                 @OA\Property(property="provinsi", type="string"),
     *                 @OA\Property(property="kota", type="string"),
     *                 @OA\Property(property="penempatan", type="string")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leads not found"
     *     )
     * )
     */

    public function getAvailableSites($leadsId): JsonResponse
    {
        try {
            $sites = $this->getAvailableSitesData($leadsId);

            return response()->json([
                'success' => true,
                'data' => $sites
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/pks/{id}/submit-checklist",
     *     tags={"PKS"},
     *     summary="Submit quotation checklist",
     *     description="Submits checklist data for quotation including NPWP, invoice, and other administrative details",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Quotation ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Checklist data",
     *         @OA\JsonContent(
     *             required={"npwp", "alamat_npwp", "pic_invoice", "telp_pic_invoice", "email_pic_invoice", "materai", "joker_reliever", "syarat_invoice", "alamat_penagihan_invoice", "status_serikat"},
     *             @OA\Property(property="npwp", type="string", description="NPWP number", example="123456789012345"),
     *             @OA\Property(property="alamat_npwp", type="string", description="NPWP address", example="Jl. Sudirman No. 123, Jakarta"),
     *             @OA\Property(property="pic_invoice", type="string", description="PIC for invoice", example="John Doe"),
     *             @OA\Property(property="telp_pic_invoice", type="string", description="Phone number of PIC", example="081234567890"),
     *             @OA\Property(property="email_pic_invoice", type="string", format="email", description="Email of PIC", example="john@example.com"),
     *             @OA\Property(property="materai", type="string", description="Stamp duty amount", example="10000"),
     *             @OA\Property(property="joker_reliever", type="string", description="Joker/Reliever availability", example="Tersedia"),
     *             @OA\Property(property="syarat_invoice", type="string", description="Invoice terms", example="Net 30 days"),
     *             @OA\Property(property="alamat_penagihan_invoice", type="string", description="Invoice billing address", example="Jl. Thamrin No. 456, Jakarta"),
     *             @OA\Property(property="catatan_site", type="string", description="Site notes", example="Catatan penting untuk site"),
     *             @OA\Property(property="status_serikat", type="string", description="Union status", example="Tidak Ada"),
     *             @OA\Property(property="pks_id", type="integer", description="PKS ID if exists", example=1),
     *             @OA\Property(property="ro", type="integer", description="RO user ID", example=10),
     *             @OA\Property(property="crm", type="integer", description="CRM user ID", example=11),
     *             @OA\Property(property="ada_serikat", type="string", description="Union existence", example="Tidak Ada"),
     *             @OA\Property(
     *                 property="pics",
     *                 type="array",
     *                 description="Array of PIC data",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"nama", "jabatan", "no_telp", "email"},
     *                     @OA\Property(property="nama", type="string", example="Jane Doe"),
     *                     @OA\Property(property="jabatan", type="integer", example=1),
     *                     @OA\Property(property="no_telp", type="string", example="081234567890"),
     *                     @OA\Property(property="email", type="string", example="jane@example.com")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Checklist submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Checklist submitted successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="npwp", type="string", example="123456789012345"),
     *                 @OA\Property(property="pic_invoice", type="string", example="John Doe"),
     *                 @OA\Property(property="pics_added", type="integer", example=2)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Quotation not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Quotation not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to submit checklist"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function submitChecklist(Request $request, string $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            $current_date_time = Carbon::now()->toDateTimeString();

            // Validasi input
            $validator = Validator::make($request->all(), [
                'npwp' => 'required|string|max:50',
                'alamat_npwp' => 'required|string|max:255',
                'materai' => 'required|string|max:50',
                'joker_reliever' => 'required|string|max:50',
                'syarat_invoice' => 'required|string|max:255',
                'alamat_penagihan_invoice' => 'required|string|max:255',
                'catatan_site' => 'nullable|string',
                'ada_serikat' => 'nullable|string',
                // Validasi untuk PICs
                'pics' => 'nullable|array',
                'pics.*.nama' => 'required|string|max:100',
                'pics.*.jabatan' => 'required|integer|exists:m_jabatan_pic,id',
                'pics.*.no_telp' => 'required|string|max:20',
                'pics.*.email' => 'required|email|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()
                ], 422);
            }

            // Cari quotation
            $quotation = Quotation::notDeleted()->findOrFail($id);

            // Logika untuk status serikat
            $statusSerikat = $request->status_serikat;
            if ($request->ada_serikat === "Tidak Ada") {
                $statusSerikat = "Tidak Ada";
            }

            // Update quotation data
            $quotation->update([
                'npwp' => $request->npwp,
                'alamat_npwp' => $request->alamat_npwp,
                'pic_invoice' => $request->pic_invoice,
                'telp_pic_invoice' => $request->telp_pic_invoice,
                'email_pic_invoice' => $request->email_pic_invoice,
                'materai' => $request->materai,
                'joker_reliever' => $request->joker_reliever,
                'syarat_invoice' => $request->syarat_invoice,
                'alamat_penagihan_invoice' => $request->alamat_penagihan_invoice,
                'catatan_site' => $request->catatan_site,
                'status_serikat' => $statusSerikat,
                'updated_at' => $current_date_time,
                'updated_by' => $user->full_name
            ]);

            // Tambah PICs jika ada
            $picsAdded = 0;
            if ($request->has('pics') && is_array($request->pics)) {
                foreach ($request->pics as $picData) {
                    $this->addDetailPic($quotation, $picData, $current_date_time);
                    $picsAdded++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Checklist submitted successfully',
                'data' => [
                    'id' => $quotation->id,
                    'npwp' => $quotation->npwp,
                    'pic_invoice' => $quotation->pic_invoice,
                    'pics_added' => $picsAdded
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to submit checklist: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ======================================================================
    // PRIVATE METHODS - Business Logic
    // ======================================================================

    private function createPks($request)
    {
        $leads = Leads::findOrFail($request->leads_id);
        $kebutuhan = Kebutuhan::find($leads->kebutuhan_id);
        $kategoriHC = KategoriSesuaiHc::find($request->kategoriHC);
        $loyalty = Loyalty::find($request->loyalty);
        $ruleThr = RuleThr::find($request->rule_thr);
        $salaryRule = SalaryRule::find($request->salary_rule);
        $company = Company::find($request->entitas);

        $pksNomor = $this->generateNomor($leads->id, $request->entitas);

        // Create PKS
        $pks = Pks::create([
            'leads_id' => $leads->id,
            'branch_id' => $leads->branch_id,
            'nomor' => $pksNomor,
            'tgl_pks' => $request->tanggal_pks,
            'kode_perusahaan' => $leads->nomor,
            'nama_perusahaan' => $leads->nama_perusahaan,
            'alamat_perusahaan' => $leads->alamat,
            'layanan_id' => $leads->kebutuhan_id,
            'layanan' => $kebutuhan->nama ?? null,
            'bidang_usaha_id' => $leads->bidang_perusahaan_id,
            'bidang_usaha' => $leads->bidang_perusahaan,
            'jenis_perusahaan_id' => $leads->jenis_perusahaan_id,
            'jenis_perusahaan' => $leads->jenis_perusahaan,
            'kontrak_awal' => $request->tanggal_awal_kontrak,
            'kontrak_akhir' => $request->tanggal_akhir_kontrak,
            'status_pks_id' => 5,
            'sales_id' => Auth::id(),
            'company_id' => $request->entitas,
            'salary_rule_id' => $request->salary_rule,
            'rule_thr_id' => $request->rule_thr,
            'kategori_sesuai_hc_id' => $request->kategoriHC,
            'kategori_sesuai_hc' => $kategoriHC->nama ?? null,
            'loyalty_id' => $request->loyalty,
            'loyalty' => $loyalty->nama ?? null,
            'provinsi_id' => $leads->provinsi_id,
            'provinsi' => $leads->provinsi,
            'kota_id' => $leads->kota_id,
            'kota' => $leads->kota,
            'pma' => $leads->pma,
            'created_by' => Auth::user()->full_name
        ]);

        // Create Sites
        $this->createSites($pks, $request->site_ids, $pksNomor, $kebutuhan, $leads);

        // Create Initial Activity
        $this->createInitialActivity($pks, $leads, $pksNomor);
        $this->createPksPerjanjian($pks, $leads, $company, $kebutuhan, $ruleThr, $salaryRule, $pksNomor);


        return $pks;
    }

    /**
     * Create PKS Perjanjian using service
     */
    private function createPksPerjanjian($pks, $leads, $company, $kebutuhan, $ruleThr, $salaryRule, $pksNomor)
    {
        try {
            // Inisialisasi service
            $templateService = new PksPerjanjianTemplateService(
                $leads,
                $company,
                $kebutuhan,
                $ruleThr,
                $salaryRule,
                $pksNomor
            );

            // Insert agreement sections
            $templateService->insertAgreementSections($pks->id, Auth::user()->full_name);

            \Log::info('PKS Perjanjian created successfully for PKS ID: ' . $pks->id);

        } catch (\Exception $e) {
            \Log::error('Failed to create PKS Perjanjian: ' . $e->getMessage());
            throw $e;
        }
    }

    private function createSites($pks, $siteIds, $pksNomor, $kebutuhan, $leads)
    {
        foreach ($siteIds as $key => $siteId) {
            $spkSite = SpkSite::where('id', $siteId)->first();

            if ($spkSite) {
                $nomorSite = $pksNomor . '-' . sprintf("%04d", ($key + 1));
                $namaProyek = sprintf(
                    '%s-%s.%s.%s',
                    Carbon::parse($pks->kontrak_awal)->format('my'),
                    Carbon::parse($pks->kontrak_akhir)->format('my'),
                    strtoupper(substr($kebutuhan->nama, 0, 2)),
                    strtoupper($leads->nama_perusahaan)
                );

                Site::create([
                    'quotation_id' => $spkSite->quotation_id,
                    'spk_id' => $spkSite->spk_id,
                    'pks_id' => $pks->id,
                    'quotation_site_id' => $spkSite->quotation_site_id,
                    'spk_site_id' => $spkSite->id,
                    'leads_id' => $spkSite->leads_id,
                    'nomor' => $nomorSite,
                    'nomor_proyek' => $nomorSite,
                    'nama_proyek' => $namaProyek,
                    'nama_site' => $spkSite->nama_site,
                    'provinsi_id' => $spkSite->provinsi_id,
                    'provinsi' => $spkSite->provinsi,
                    'kota_id' => $spkSite->kota_id,
                    'kota' => $spkSite->kota,
                    'ump' => $spkSite->ump,
                    'umk' => $spkSite->umk,
                    'nominal_upah' => $spkSite->nominal_upah,
                    'penempatan' => $spkSite->penempatan,
                    'kebutuhan_id' => $spkSite->kebutuhan_id,
                    'kebutuhan' => $spkSite->kebutuhan,
                    'nomor_quotation' => $spkSite->nomor_quotation,
                    'created_by' => Auth::user()->full_name
                ]);
            }
        }
    }

    private function createInitialActivity($pks, $leads, $pksNomor)
    {
        $nomorActivity = $this->generateNomorActivity($leads->id);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'pks_id' => $pks->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $nomorActivity,
            'tipe' => 'PKS',
            'notes' => 'PKS dengan nomor :' . $pksNomor . ' terbentuk',
            'is_activity' => 0,
            'user_id' => Auth::id(),
            'created_by' => Auth::user()->full_name
        ]);
    }

    private function approvePks($pks, $otLevel)
    {
        $statusMap = [
            1 => 2,
            2 => 3,
            3 => 4,
            4 => 5
        ];

        $approveField = "ot{$otLevel}";

        $pks->update([
            $approveField => Auth::user()->full_name,
            'status_pks_id' => $statusMap[$otLevel] ?? $pks->status_pks_id,
            'updated_by' => Auth::user()->full_name
        ]);
    }

    private function activatePksSites($pks)
    {
        DB::beginTransaction();

        try {
            // Update PKS menjadi aktif
            $pks->update([
                'ot5' => Auth::user()->full_name,
                'status_pks_id' => 7,
                'is_aktif' => 1,
                'updated_by' => Auth::user()->full_name
            ]);

            $leads = $pks->leads;

            // Cek apakah customer sudah ada
            if (!$leads->customer_id) {
                // Generate nomor customer
                $customerNomor = $this->generateCustomerNumber($leads->id, $pks->company_id);

                // Buat record customer
                $customer = Customer::create([
                    'leads_id' => $leads->id,
                    'nomor' => $customerNomor,
                    'tgl_customer' => now(),
                    'tim_sales_id' => $leads->tim_sales_id,
                    'tim_sales_d_id' => $leads->tim_sales_d_id,
                    'created_by' => Auth::user()->full_name
                ]);

                // Update leads dengan customer_id, status_leads_id, dan customer_active
                $leads->update([
                    'customer_id' => $customer->id,
                    'status_leads_id' => 102,
                    'customer_active' => 1, // Set ke 1 karena PKS aktif
                    'updated_by' => Auth::user()->full_name
                ]);

                // Buat activity log untuk customer
                $this->createCustomerActivity($leads, $customerNomor);

            } else {
                // Jika customer sudah ada, update status dan customer_active
                $leads->update([
                    'status_leads_id' => 102,
                    'customer_active' => 1, // Set ke 1 karena PKS aktif
                    'updated_by' => Auth::user()->full_name
                ]);
            }

            // Update customer_active untuk semua leads yang terkait (otomatis)
            $this->autoSyncCustomerActiveStatus();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    /**
     * SYNC OTOMATIS - Update customer_active berdasarkan status PKS
     * Dipanggil otomatis ketika ada perubahan PKS
     */
    private function autoSyncCustomerActiveStatus()
    {
        try {
            // Ambil semua leads yang sudah menjadi customer
            $leadsWithCustomers = Leads::whereNotNull('customer_id')
                ->whereNull('deleted_at')
                ->get();

            foreach ($leadsWithCustomers as $lead) {
                $this->updateCustomerActiveFromPks($lead);
            }

            \Log::info('Auto sync customer_active status completed', [
                'count' => $leadsWithCustomers->count(),
                'timestamp' => now()
            ]);

        } catch (\Exception $e) {
            \Log::error('Auto sync customer_active status failed: ' . $e->getMessage());
        }
    }
    /**
     * Update customer_active untuk satu leads berdasarkan status PKS
     */
    private function updateCustomerActiveFromPks($lead)
    {
        // Cari semua PKS yang terkait dengan leads ini
        $pksList = Pks::where('leads_id', $lead->id)
            ->whereNull('deleted_at')
            ->get();

        $hasActivePks = false;

        foreach ($pksList as $pks) {
            // Cek apakah PKS aktif dan kontrak masih berlaku
            if ($pks->is_aktif == 1 && $this->isKontrakBerlaku($pks->kontrak_akhir)) {
                $hasActivePks = true;
                break;
            }
        }

        // Update customer_active berdasarkan status PKS
        $newStatus = $hasActivePks ? 1 : 0;

        // Only update if changed to avoid unnecessary database operations
        if ($lead->customer_active != $newStatus) {
            $lead->update([
                'customer_active' => $newStatus
            ]);

            \Log::info('Customer active status updated', [
                'leads_id' => $lead->id,
                'customer_id' => $lead->customer_id,
                'customer_active' => $newStatus,
                'timestamp' => now()
            ]);
        }
    }

    /**
     * Cek apakah kontrak masih berlaku
     */
    private function isKontrakBerlaku($kontrakAkhir)
    {
        if (!$kontrakAkhir) {
            return false;
        }

        $tanggalSekarang = Carbon::now();
        $tanggalKontrakAkhir = Carbon::parse($kontrakAkhir);

        return $tanggalSekarang->lessThanOrEqualTo($tanggalKontrakAkhir);
    }
    /**
     * Generate nomor customer dengan format seperti PKS
     */
    private function generateCustomerNumber($leadsId, $companyId = null)
    {
        $now = Carbon::now();
        $leads = Leads::find($leadsId);

        if (!$companyId && $leads && $leads->company_id) {
            $companyId = $leads->company_id;
        }

        $company = Company::where('id', $companyId)->first();
        $dataLeads = Leads::find($leadsId);

        $nomor = "CUST/"; // Prefix CUST untuk Customer

        if ($company && $dataLeads) {
            $nomor .= $company->code . "/";
            $nomor .= $dataLeads->nomor . "-";
        } else {
            $nomor .= "NN/NNNNN-";
        }

        $month = str_pad($now->month, 2, '0', STR_PAD_LEFT);

        // Hitung jumlah data customer dengan pattern yang sama
        $pattern = $nomor . $month . $now->year . "-%";
        $jumlahData = Customer::where('nomor', 'like', $pattern)->count();
        $urutan = sprintf("%05d", $jumlahData + 1);

        return $nomor . $month . $now->year . "-" . $urutan;
    }

    /**
     * Create customer activity log
     */
    private function createCustomerActivity($leads, $customerNomor)
    {
        $nomorActivity = $this->generateNomorActivity($leads->id);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $nomorActivity,
            'tipe' => 'CUSTOMER',
            'notes' => 'Customer dengan nomor :' . $customerNomor . ' terbentuk dari PKS',
            'is_activity' => 0,
            'user_id' => Auth::id(),
            'created_by' => Auth::user()->full_name
        ]);
    }

    private function getTemplateData($pks)
    {
        $leads = $pks->leads;
        $company = Company::find($pks->company_id);
        $kebutuhan = Kebutuhan::find($pks->layanan_id);
        $ruleThr = RuleThr::find($pks->rule_thr_id);
        $salaryRule = SalaryRule::find($pks->salary_rule_id);

        return [
            'pks' => [
                'nomor' => $pks->nomor,
                'tanggal_pks' => Carbon::parse($pks->tgl_pks)->isoFormat('D MMMM Y'),
                'kontrak_awal' => Carbon::parse($pks->kontrak_awal)->isoFormat('D MMMM Y'),
                'kontrak_akhir' => Carbon::parse($pks->kontrak_akhir)->isoFormat('D MMMM Y'),
            ],
            'perusahaan' => [
                'nama' => $leads->nama_perusahaan,
                'alamat' => $leads->alamat,
                'pic' => $leads->pic,
                'nomor' => $leads->nomor,
            ],
            'penyedia' => [
                'nama' => $company->name ?? '',
                'direktur' => $company->nama_direktur ?? '',
                'alamat' => $company->address ?? '',
                'bank' => [
                    'nama' => 'MANDIRI',
                    'cabang' => 'KCP SURABAYA RUNGKUT MEGAH RAYA',
                    'rekening' => '1420001290823',
                    'nama_rekening' => $company->name ?? ''
                ]
            ],
            'layanan' => [
                'nama' => $kebutuhan->nama ?? '',
                'kebutuhan_id' => $pks->layanan_id
            ],
            'rule_thr' => [
                'hari_penagihan_invoice' => $ruleThr->hari_penagihan_invoice ?? 0,
                'hari_pembayaran_invoice' => $ruleThr->hari_pembayaran_invoice ?? 0,
                'hari_rilis_thr' => $ruleThr->hari_rilis_thr ?? 0
            ],
            'salary_rule' => [
                'cutoff' => $salaryRule->cutoff ?? '',
                'crosscheck_absen' => $salaryRule->crosscheck_absen ?? '',
                'pengiriman_invoice' => $salaryRule->pengiriman_invoice ?? '',
                'perkiraan_invoice_diterima' => $salaryRule->perkiraan_invoice_diterima ?? '',
                'pembayaran_invoice' => $salaryRule->pembayaran_invoice ?? '',
                'rilis_payroll' => $salaryRule->rilis_payroll ?? ''
            ],
            'sites' => $pks->sites->map(function ($site) {
                return [
                    'nama_site' => $site->nama_site,
                    'alamat' => $site->penempatan,
                    'kota' => $site->kota
                ];
            })
        ];
    }

    // ======================================================================
    // UTILITY METHODS
    // ======================================================================

    private function hitungBerakhirKontrak($tanggalBerakhir)
    {
        if (is_null($tanggalBerakhir)) {
            return "-";
        }

        $tanggalSekarang = Carbon::now();
        $tanggalBerakhir = Carbon::createFromFormat('Y-m-d', $tanggalBerakhir);

        if ($tanggalSekarang->greaterThanOrEqualTo($tanggalBerakhir)) {
            return "Kontrak habis";
        }

        $selisih = $tanggalSekarang->diff($tanggalBerakhir);

        $hasil = [];
        if ($selisih->y > 0)
            $hasil[] = "{$selisih->y} tahun";
        if ($selisih->m > 0)
            $hasil[] = "{$selisih->m} bulan";
        if ($selisih->d > 0)
            $hasil[] = "{$selisih->d} hari";

        return implode(', ', $hasil);
    }

    private function getStatusBerlaku($tanggalBerakhir)
    {
        $selisih = $this->selisihKontrakBerakhir($tanggalBerakhir);

        if ($selisih <= 0)
            return 'Kontrak Habis';
        if ($selisih <= 60)
            return 'Berakhir dalam 2 bulan';
        if ($selisih <= 90)
            return 'Berakhir dalam 3 bulan';
        return 'Lebih dari 3 Bulan';
    }

    private function selisihKontrakBerakhir($tanggalBerakhir)
    {
        if (is_null($tanggalBerakhir))
            return 0;

        $tanggalSekarang = Carbon::now();
        $tanggalBerakhir = Carbon::createFromFormat('Y-m-d', $tanggalBerakhir);

        if ($tanggalSekarang->greaterThanOrEqualTo($tanggalBerakhir)) {
            return 0;
        }

        return $tanggalSekarang->diffInDays($tanggalBerakhir);
    }

    private function generateNomor($leadsId, $companyId)
    {
        $now = Carbon::now();
        $dataLeads = Leads::find($leadsId);
        $company = Company::where('id', $companyId)->first();

        $nomor = "PKS/";
        if ($company) {
            $nomor .= $company->code . "/";
            $nomor .= $dataLeads->nomor . "-";
        } else {
            $nomor .= "NN/NNNNN-";
        }

        $month = str_pad($now->month, 2, '0', STR_PAD_LEFT);
        $jumlahData = Pks::where('nomor', 'like', $nomor . $month . $now->year . "-%")->count();
        $urutan = sprintf("%05d", $jumlahData + 1);

        return $nomor . $month . $now->year . "-" . $urutan;
    }

    private function generateNomorActivity($leadsId)
    {
        $now = Carbon::now();
        $leads = Leads::find($leadsId);

        $prefix = "CAT/";
        if ($leads) {
            $prefix .= match ($leads->kebutuhan_id) {
                1 => "SG/",
                2 => "LS/",
                3 => "CS/",
                4 => "LL/",
                default => "NN/"
            };
            $prefix .= $leads->nomor . "-";
        } else {
            $prefix .= "NN/NNNNN-";
        }

        $month = str_pad($now->month, 2, '0', STR_PAD_LEFT);
        $year = $now->year;

        $count = CustomerActivity::where('nomor', 'like', $prefix . $month . $year . "-%")->count();
        $sequence = str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        return $prefix . $month . $year . "-" . $sequence;
    }

    private function getAvailableLeadsData()
    {
        $user = Auth::user();

        return Leads::with(['timSalesD.user'])
            // Jika cais_role_id BUKAN 2 (Bukan Superadmin), maka filter berdasarkan user_id login
            ->when($user->cais_role_id != 2, function ($query) use ($user) {
                $query->whereHas('timSalesD', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            })
            ->whereHas('spkSites', function ($query) {
                $query->whereNull('sl_spk_site.deleted_at')
                    ->whereHas('spk', function ($subQuery) {
                        $subQuery->whereNull('sl_spk.deleted_at');
                    })
                    ->whereDoesntHave('site');
            })
            ->select('id', 'nomor', 'nama_perusahaan', 'provinsi', 'kota')
            ->distinct()
            ->get();
    }
    private function getAvailableSitesData($leadsId)
    {
        // Ambil data sites yang tersedia dengan relasi yang diperlukan
        $sites = SpkSite::with([
            'spk',
            'quotation' => function ($query) {
                $query->with(['company', 'salaryRule', 'ruleThr']);
            },
            'quotation.quotationSites',
            'leads'
        ])
            ->where('leads_id', $leadsId)
            ->whereNull('deleted_at')
            ->whereDoesntHave('site')
            ->whereHas('spk', function ($query) {
                $query->whereNull('deleted_at');
            })
            ->select('id', 'nama_site', 'provinsi', 'kota', 'penempatan', 'spk_id', 'quotation_id')
            ->orderBy(function ($query) {
                $query->select('nomor')
                    ->from('sl_spk')
                    ->whereColumn('sl_spk.id', 'sl_spk_site.spk_id')
                    ->limit(1);
            }, 'asc')
            ->get()
            ->map(function ($site) {
                $quotation = $site->quotation;
                $quotationCompany = $quotation ? $quotation->company()->first() : null;


                return [
                    'id' => $site->id,
                    'nomor' => $site->spk->nomor ?? null,
                    'nama_site' => $site->nama_site,
                    'provinsi' => $site->provinsi,
                    'kota' => $site->kota,
                    'penempatan' => $site->penempatan,

                    // Data dari Quotation
                    'mulai_kontrak' => $quotation?->mulai_kontrak,
                    'kontrak_selesai' => $quotation?->kontrak_selesai,
                    'durasi_kerjasama' => $quotation?->durasi_kerjasama,

                    // Data company dari quotation
                    'company' => $quotationCompany ? [
                        'id' => $quotationCompany->id,
                        'name' => $quotationCompany->name ?? null,
                        'code' => $quotationCompany->code ?? null,
                    ] : null,

                    // Data salary rule dari quotation
                    'salary_rule' => $quotation && $quotation->salaryRule ? [
                        'id' => $quotation->salaryRule->id ?? null,
                        'nama' => $quotation->salaryRule->nama_salary_rule ?? null,
                        'cutoff' => $quotation->salaryRule->cutoff ?? null,
                        'pembayaran_invoice' => $quotation->salaryRule->pembayaran_invoice ?? null,
                        'rilis_payroll' => $quotation->salaryRule->rilis_payroll ?? null
                    ] : null,

                    // Data rule THR dari quotation
                    'rule_thr' => $quotation && $quotation->ruleThr ? [
                        'id' => $quotation->ruleThr->id ?? null,
                        'nama' => $quotation->ruleThr->nama ?? null,
                        'hari_penagihan_invoice' => $quotation->ruleThr->hari_penagihan_invoice ?? null,
                        'hari_pembayaran_invoice' => $quotation->ruleThr->hari_pembayaran_invoice ?? null,
                        'hari_rilis_thr' => $quotation->ruleThr->hari_rilis_thr ?? null
                    ] : null,

                    // Data tambahan dari quotation yang mungkin berguna
                    'quotation_id' => $quotation?->id,
                    'nomor_quotation' => $quotation?->nomor,
                ];
            });

        return $sites;
    }
    // ======================================================================
    // PRIVATE METHODS FOR ACTIVATE FUNCTION
    // ======================================================================

    /**
     * Update PKS and Leads Status
     */
    private function updatePksAndLeadsStatus($pks, $current_date_time)
    {
        // Update PKS status
        $pks->update([
            'ot5' => Auth::user()->full_name,
            'status_pks_id' => 7,
            'is_aktif' => 1,
            'updated_at' => $current_date_time,
            'updated_by' => Auth::user()->full_name
        ]);

        // Get leads
        $leads = Leads::find($pks->leads_id);
        if (!$leads) {
            throw new \Exception('Leads not found');
        }

        // Check RO and supervisor fields
        if ($leads->ro_id_1 == null)
            $leads->ro_id_1 = 0;
        if ($leads->ro_id_2 == null)
            $leads->ro_id_2 = 0;
        if ($leads->ro_id_3 == null)
            $leads->ro_id_3 = 0;
        if ($leads->ro_id == null)
            $leads->ro_id = 0;

        // Update leads status
        $leads->update([
            'status_leads_id' => 102,
            'updated_at' => $current_date_time,
            'updated_by' => Auth::user()->full_name
        ]);

        return $leads;
    }

    /**
     * Sync Customer to HRIS
     */
    private function syncCustomerToHris($leads, $current_date_time)
    {
        // Check if client exists in HRIS
        $client = Client::whereNull('deleted_at')
            ->where('customer_id', $leads->id)
            ->where('is_active', 1)
            ->first();

        if ($client != null) {
            return $client->id;
        }

        // Create new client in HRIS
        return Client::insertGetId([
            'customer_id' => $leads->id,
            'name' => $leads->nama_perusahaan,
            'address' => $leads->alamat ?? '-',
            'is_active' => 1,
            'created_at' => $current_date_time,
            'created_by' => Auth::user()->id,
            'updated_at' => $current_date_time,
            'updated_by' => Auth::user()->id
        ]);
    }

    /**
     * Process PKS Sites
     */
    private function processPksSites($pks, $leads, $clientId, $current_date_time)
    {
        $siteList = Site::where('pks_id', $pks->id)
            ->whereNull('deleted_at')
            ->get();

        foreach ($siteList as $site) {
            $quotation = Quotation::find($site->quotation_id);
            if (!$quotation)
                continue;

            // Sync Site to HRIS
            $this->syncSiteToHris($site, $pks, $leads, $quotation, $clientId, $current_date_time);

            // Update Quotation Calculations
            $this->updateQuotationCalculations($site, $quotation, $leads, $current_date_time);
        }
    }

    /**
     * Sync Site to HRIS
     */
    private function syncSiteToHris($site, $pks, $leads, $quotation, $clientId, $current_date_time)
    {
        HrisSite::create([
            'site_id' => $site->id,
            'code' => $leads->nomor,
            'proyek_id' => 0,
            'contract_number' => $pks->nomor,
            'name' => $site->nama_site,
            'address' => $site->penempatan,
            'layanan_id' => $site->kebutuhan_id,
            'client_id' => $clientId,
            'city_id' => $site->kota_id,
            'branch_id' => $leads->branch_id,
            'company_id' => $quotation->company_id,
            'pic_id_1' => $leads->ro_id_1,
            'pic_id_2' => $leads->ro_id_2,
            'pic_id_3' => $leads->ro_id_3,
            'supervisor_id' => $leads->ro_id,
            'reliever' => $quotation->joker_reliever,
            'contract_value' => 0,
            'contract_start' => $pks->kontrak_awal,
            'contract_end' => $pks->kontrak_akhir,
            'contract_terminated' => null,
            'note_terminated' => '',
            'contract_status' => 'Aktif',
            'health_insurance_status' => 'Terdaftar',
            'labor_insurance_status' => 'Terdaftar',
            'vacation' => 0,
            'attendance_machine' => '',
            'is_active' => 1,
            'created_at' => $current_date_time,
            'created_by' => Auth::user()->id,
            'updated_at' => $current_date_time,
            'updated_by' => Auth::user()->id
        ]);
    }

    /**
     * Update Quotation Calculations
     */
    private function updateQuotationCalculations($site, $quotation, $leads, $current_date_time)
    {
        // Get quotation details
        $detailQuotation = QuotationDetail::whereNull('deleted_at')
            ->whereNull('deleted_at')
            ->where('quotation_site_id', $site->quotation_site_id)
            ->get();

        // Calculate quotation (assuming QuotationService exists)
        // Note: You might need to adjust this based on your actual QuotationService
        // $quotationService = new \App\Services\QuotationService();
        // $calcQuotation = $quotationService->calculateQuotation($quotation);

        // For now, we'll use simplified calculation
        $calcQuotation = $this->calculateQuotationSimple($quotation);

        // Update HPP and COSS calculations
        $totalData = $this->updateHppAndCossCalculations($calcQuotation, $leads, $current_date_time);

        // Insert Quotation Margin
        $this->insertQuotationMargin($quotation, $leads, $totalData, $current_date_time);
    }

    /**
     * Simple Quotation Calculation (replace with actual service if available)
     */
    private function calculateQuotationSimple($quotation)
    {
        // This is a simplified version. Replace with actual calculation logic
        return (object) [
            'jumlah_hc' => 0,
            'nominal_upah' => 0,
            'total_invoice' => 0,
            'total_invoice_coss' => 0,
            'ppn' => 0,
            'ppn_coss' => 0,
            'grand_total_sebelum_pajak' => 0,
            'grand_total_sebelum_pajak_coss' => 0,
            'nominal_management_fee' => 0,
            'nominal_management_fee_coss' => 0,
            'persentase' => 0,
            'persen_bunga_bank' => 0,
            'persen_insentif' => 0,
            'pembulatan' => 0,
            'pembulatan_coss' => 0,
            'penagihan' => 'Tanpa Pembulatan',
            'pph' => 0,
            'pph_coss' => 0,
            'quotation_detail' => []
        ];
    }

    /**
     * Update HPP and COSS Calculations
     */
    private function updateHppAndCossCalculations($calcQuotation, $leads, $current_date_time)
    {
        $totalNominal = 0;
        $totalNominalCoss = 0;
        $ppn = 0;
        $ppnCoss = 0;
        $totalBiaya = 0;
        $totalBiayaCoss = 0;

        // Assuming we have quotation details
        foreach ($calcQuotation->quotation_detail as $kbd) {
            // Update HPP calculation
            QuotationDetailHpp::whereNull('deleted_at')
                ->where('quotation_detail_id', $kbd->id)
                ->whereNull('deleted_at')
                ->update([
                    'position_id' => $kbd->position_id ?? 0,
                    'leads_id' => $leads->id,
                    'jumlah_hc' => $calcQuotation->jumlah_hc,
                    'gaji_pokok' => $calcQuotation->nominal_upah,
                    // Add other fields as needed
                    'updated_at' => $current_date_time,
                    'updated_by' => Auth::user()->full_name
                ]);

            // Update COSS calculation
            QuotationDetailCoss::whereNull('deleted_at')
                ->where('quotation_detail_id', $kbd->id)
                ->update([
                    'position_id' => $kbd->position_id ?? 0,
                    'leads_id' => $leads->id,
                    'jumlah_hc' => $calcQuotation->jumlah_hc,
                    'gaji_pokok' => $calcQuotation->nominal_upah,
                    // Add other fields as needed
                    'updated_at' => $current_date_time,
                    'updated_by' => Auth::user()->full_name
                ]);

            // Accumulate totals
            $totalNominal += $calcQuotation->total_invoice;
            $totalNominalCoss += $calcQuotation->total_invoice_coss;
            $ppn += $calcQuotation->ppn;
            $ppnCoss += $calcQuotation->ppn_coss;
            $totalBiaya += $kbd->sub_total_personil ?? 0;
            $totalBiayaCoss += $kbd->sub_total_personil ?? 0;
        }

        // Calculate margins
        $margin = $totalNominal - $ppn - $totalBiaya;
        $marginCoss = $totalNominalCoss - $ppnCoss - $totalBiayaCoss;
        $gpm = $totalBiaya > 0 ? ($margin / $totalBiaya) * 100 : 0;
        $gpmCoss = $totalBiayaCoss > 0 ? ($marginCoss / $totalBiayaCoss) * 100 : 0;

        return compact(
            'totalNominal',
            'totalNominalCoss',
            'ppn',
            'ppnCoss',
            'totalBiaya',
            'totalBiayaCoss',
            'margin',
            'marginCoss',
            'gpm',
            'gpmCoss'
        );
    }

    /**
     * Insert Quotation Margin
     */
    private function insertQuotationMargin($quotation, $leads, $totalData, $current_date_time)
    {
        QuotationMargin::create([
            'quotation_id' => $quotation->id,
            'leads_id' => $leads->id,
            'nominal_hpp' => $totalData['totalNominal'],
            'nominal_harga_pokok' => $totalData['totalNominalCoss'],
            'ppn_hpp' => $totalData['ppn'],
            'ppn_harga_pokok' => $totalData['ppnCoss'],
            'total_biaya_hpp' => $totalData['totalBiaya'],
            'total_biaya_harga_pokok' => $totalData['totalBiayaCoss'],
            'margin_hpp' => $totalData['margin'],
            'margin_harga_pokok' => $totalData['marginCoss'],
            'gpm_hpp' => $totalData['gpm'],
            'gpm_harga_pokok' => $totalData['gpmCoss'],
            'created_at' => $current_date_time,
            'created_by' => Auth::user()->full_name
        ]);
    }

    /**
     * Create Customer Activity Log
     */
    private function createCustomerActivityLog($pks, $leads, $current_date_time)
    {
        $nomorActivity = $this->generateNomorActivity($leads->id);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'pks_id' => $pks->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => $current_date_time,
            'nomor' => $nomorActivity,
            'tipe' => 'PKS',
            'notes' => 'PKS dengan nomor :' . $pks->nomor . ' telah diaktifkan oleh ' . Auth::user()->full_name,
            'is_activity' => 0,
            'user_id' => Auth::user()->id,
            'created_at' => $current_date_time,
            'created_by' => Auth::user()->full_name
        ]);
    }

    /**
     * Handle Customer Status
     */
    private function handleCustomerStatus($pks, $leads, $current_date_time)
    {
        // If customer doesn't exist, create one
        if (!$leads->customer_id) {
            $customerNomor = $this->generateCustomerNumber($leads->id, $pks->company_id);

            $customer = Customer::create([
                'leads_id' => $leads->id,
                'nomor' => $customerNomor,
                'tgl_customer' => $current_date_time,
                'tim_sales_id' => $leads->tim_sales_id,
                'tim_sales_d_id' => $leads->tim_sales_d_id,
                'created_by' => Auth::user()->full_name
            ]);

            $leads->update([
                'customer_id' => $customer->id,
                'customer_active' => 1,
                'updated_at' => $current_date_time,
                'updated_by' => Auth::user()->full_name
            ]);

            // Create customer activity log
            $this->createCustomerCreationActivity($leads, $customerNomor, $current_date_time);
        } else {
            // Update existing customer status
            $leads->update([
                'customer_active' => 1,
                'updated_at' => $current_date_time,
                'updated_by' => Auth::user()->full_name
            ]);
        }

        // Auto sync customer active status
        $this->autoSyncCustomerActiveStatus();
    }

    /**
     * Create Customer Creation Activity
     */
    private function createCustomerCreationActivity($leads, $customerNomor, $current_date_time)
    {
        $nomorActivity = $this->generateNomorActivity($leads->id);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => $current_date_time,
            'nomor' => $nomorActivity,
            'tipe' => 'CUSTOMER',
            'notes' => 'Customer dengan nomor :' . $customerNomor . ' terbentuk dari PKS',
            'is_activity' => 0,
            'user_id' => Auth::id(),
            'created_at' => $current_date_time,
            'created_by' => Auth::user()->full_name
        ]);
    }
    /**
     * Add Detail PIC to Quotation
     * 
     * @param Quotation $quotation
     * @param array $picData
     * @param string $current_date_time
     * @return void
     */
    private function addDetailPic($quotation, array $picData, string $current_date_time): void
    {
        try {
            $jabatan = JabatanPic::where('id', $picData['jabatan'])->first();

            if (!$jabatan) {
                throw new \Exception("Jabatan dengan ID {$picData['jabatan']} tidak ditemukan");
            }

            QuotationPic::create([
                'quotation_id' => $quotation->id,
                'nama' => $picData['nama'],
                'jabatan_id' => $jabatan->id,
                'no_telp' => $picData['no_telp'],
                'email' => $picData['email'],
                'created_at' => $current_date_time,
                'created_by' => Auth::user()->full_name
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to add detail PIC: ' . $e->getMessage());
            throw $e;
        }
    }


}