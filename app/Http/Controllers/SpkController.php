<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\QuotationAplikasi;
use App\Models\QuotationChemical;
use App\Models\QuotationDetail;
use App\Models\QuotationDetailCoss;
use App\Models\QuotationDetailHpp;
use App\Models\QuotationDetailRequirement;
use App\Models\QuotationDetailTunjangan;
use App\Models\QuotationDevices;
use App\Models\QuotationKaporlap;
use App\Models\QuotationKerjasama;
use App\Models\QuotationOhc;
use App\Models\QuotationPic;
use App\Models\QuotationTraining;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Models\Spk;
use App\Models\SpkSite;
use App\Models\Leads;
use App\Models\Quotation;
use App\Models\QuotationSite;
use App\Models\CustomerActivity;
use App\Models\JabatanPic;
use App\Models\Company;
/**
 * @OA\Tag(
 *     name="SPK",
 *     description="API untuk manajemen Surat Perintah Kerja (SPK)"
 * )
 */
class SpkController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/spk/list",
     *     summary="Mendapatkan daftar semua SPK dengan filter tanggal dan status",
     *     description="Endpoint untuk mengambil data semua SPK yang aktif. Dapat difilter berdasarkan rentang tanggal dan status SPK.",
     *     tags={"SPK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tgl_dari",
     *         in="query",
     *         description="Tanggal mulai filter (format: Y-m-d). Default: 3 bulan kebelakang dari sekarang",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="tgl_sampai",
     *         in="query",
     *         description="Tanggal akhir filter (format: Y-m-d). Default: hari ini",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter berdasarkan status SPK ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sukses mengambil data SPK",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="SPK data retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nomor", type="string", example="SPK/LEAD001-012024-00001"),
     *                 @OA\Property(property="tgl_spk", type="string", example="2024-01-15"),
     *                 @OA\Property(property="nama_perusahaan", type="string", example="PT Example Company"),
     *                 @OA\Property(property="status_spk_id", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", example="2024-01-15 10:30:00"),
     *                 @OA\Property(property="leads", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT Example Company")
     *                 ),
     *                 @OA\Property(property="status_spk", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama", type="string", example="Draft")
     *                 )
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error server",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error fetching SPK data")
     *         )
     *     )
     * )
     */
    public function list(Request $request)
    {
        try {
            $tglDari = $request->tgl_dari ?? Carbon::now()->startOfMonth()->subMonths(3)->toDateString();
            $tglSampai = $request->tgl_sampai ?? Carbon::now()->toDateString();

            $query = Spk::with(['leads', 'statusSpk', 'spkSites'])
                ->whereNull('deleted_at');

            if (!empty($request->status)) {
                $query->where('status_spk_id', $request->status);
            }

            if ($tglDari && $tglSampai) {
                $query->whereBetween('tgl_spk', [$tglDari, $tglSampai]);
            }

            $data = $query->get();

            return $this->successResponse('SPK data retrieved successfully', $data);

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching SPK data', $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/spk/list-terhapus",
     *     summary="Mendapatkan daftar SPK yang sudah dihapus (soft delete)",
     *     description="Endpoint untuk mengambil data SPK yang telah dihapus (masuk dalam trash).",
     *     tags={"SPK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Sukses mengambil data SPK terhapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Deleted SPK data retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nomor", type="string", example="SPK/LEAD001-012024-00001"),
     *                 @OA\Property(property="deleted_at", type="string", example="2024-01-20 15:30:00"),
     *                 @OA\Property(property="deleted_by", type="string", example="John Doe")
     *             ))
     *         )
     *     )
     * )
     */
    public function listTerhapus()
    {
        try {
            $data = Spk::onlyTrashed()
                ->with(['leads', 'quotation'])
                ->get();

            return $this->successResponse('Deleted SPK data retrieved successfully', $data);

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching deleted SPK data', $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/spk/available-quotation",
     *     summary="Mendapatkan daftar quotation yang tersedia untuk dibuat SPK",
     *     description="Endpoint untuk mengambil data quotation yang memenuhi syarat untuk dibuat SPK. Hanya quotation milik user yang login dan belum memiliki SPK.",
     *     tags={"SPK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Sukses mengambil data quotation tersedia",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Available quotations retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nomor", type="string", example="QUOT/COMP001-012024-00001"),
     *                 @OA\Property(property="quotation", type="string", example="QUOT/COMP001-012024-00001"),
     *                 @OA\Property(property="tgl_quotation", type="string", example="15 Januari 2024"),
     *                 @OA\Property(property="nama_perusahaan", type="string", example="PT Example Company"),
     *                 @OA\Property(property="jumlah_site", type="integer", example=2),
     *                 @OA\Property(property="kebutuhan", type="string", example="Security Service"),
     *                 @OA\Property(property="layanan", type="string", example="Security Service")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error server",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error fetching available quotations")
     *         )
     *     )
     * )
     */
    public function availableQuotation()
    {
        try {
            $data = Quotation::with(['leads.timSalesD'])
                ->whereNull('deleted_at')
                ->where('is_aktif', 1)
                ->whereHas('leads.timSalesD', function ($query) {
                    $query->where('user_id', Auth::user()->id);
                })
                ->whereHas('quotationSites', function ($query) {
                    $query->whereNull('deleted_at')
                        ->whereDoesntHave('spkSite');
                })
                ->get()
                ->map(function ($quotation) {
                    return [
                        'id' => $quotation->id,
                        'nomor' => $quotation->nomor,
                        'quotation' => $quotation->nomor,
                        'tgl_quotation' => Carbon::parse($quotation->tgl_quotation)->isoFormat('D MMMM Y'),
                        'nama_perusahaan' => $quotation->nama_perusahaan,
                        'jumlah_site' => $quotation->jumlah_site,
                        'kebutuhan' => $quotation->kebutuhan,
                        'layanan' => $quotation->kebutuhan
                    ];
                });

            return $this->successResponse('Available quotations retrieved successfully', $data);

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching available quotations', $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/spk/available-leads",
     *     summary="Mendapatkan daftar leads yang tersedia untuk dibuat SPK",
     *     description="Endpoint untuk mengambil data leads yang memiliki quotation aktif dan belum memiliki SPK untuk semua site-nya.",
     *     tags={"SPK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Sukses mengambil data leads tersedia",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Available leads retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nomor", type="string", example="LEAD001"),
     *                 @OA\Property(property="nama_perusahaan", type="string", example="PT Example Company"),
     *                 @OA\Property(property="provinsi", type="string", example="DKI Jakarta"),
     *                 @OA\Property(property="kota", type="string", example="Jakarta Selatan")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error server",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error fetching available leads")
     *         )
     *     )
     * )
     */
    public function availableLeads()
    {
        try {
            $data = QuotationSite::with(['quotation.leads.timSalesD'])
                ->whereNull('deleted_at')
                ->whereHas('quotation', function ($query) {
                    $query->whereNull('deleted_at')
                        ->where('is_aktif', 1)
                        ->whereHas('leads.timSalesD', function ($q) {
                            $q->where('user_id', Auth::user()->id);
                        });
                })
                ->whereDoesntHave('spkSite')
                ->get()
                ->map(function ($site) {
                    $leads = $site->quotation->leads;
                    return [
                        'id' => $leads->id,
                        'nomor' => $leads->nomor,
                        'nama_perusahaan' => $leads->nama_perusahaan,
                        'provinsi' => $leads->provinsi,
                        'kota' => $leads->kota
                    ];
                });

            return $this->successResponse('Available leads retrieved successfully', $data);

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching available leads', $e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/spk/add",
     *     summary="Membuat SPK baru",
     *     description="Endpoint untuk membuat SPK baru berdasarkan leads dan quotation site yang dipilih.",
     *     tags={"SPK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leads_id", "quotation_id", "tanggal_spk", "site_ids"},
     *             @OA\Property(property="leads_id", type="integer", example=1, description="ID Leads"),
     *             @OA\Property(property="quotation_id", type="integer", example=1, description="ID Quotation"),
     *             @OA\Property(property="tanggal_spk", type="string", format="date", example="2024-01-15", description="Tanggal SPK"),
     *             @OA\Property(property="site_ids", type="array", @OA\Items(type="integer"), example={1,2,3}, description="Array ID Quotation Site")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="SPK berhasil dibuat",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="SPK created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nomor", type="string", example="SPK/LEAD001-012024-00001"),
     *                 @OA\Property(property="tgl_spk", type="string", example="2024-01-15"),
     *                 @OA\Property(property="status_spk_id", type="integer", example=1),
     *                 @OA\Property(property="leads_id", type="integer", example=1),
     *                 @OA\Property(property="quotation_id", type="integer", example=1),
     *                 @OA\Property(property="spk_sites", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="leads", type="object"),
     *                 @OA\Property(property="quotation", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error server",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error creating SPK")
     *         )
     *     )
     * )
     */
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'leads_id' => 'required|exists:sl_leads,id',
            'quotation_id' => 'required|exists:sl_quotation,id', // TAMBAHKAN INI
            'tanggal_spk' => 'required|date',
            'site_ids' => 'required|array',
            'site_ids.*' => 'exists:sl_quotation_site,id'
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            DB::beginTransaction();

            $leads = Leads::find($request->leads_id);
            $quotation = Quotation::find($request->quotation_id); // AMBIL QUOTATION
            $spkNomor = $this->generateNomorNew($request->leads_id);

            // Create SPK
            $spk = Spk::create([
                'leads_id' => $leads->id,
                'quotation_id' => $quotation->id, // SIMPAN QUOTATION_ID
                'nomor' => $spkNomor,
                'tgl_spk' => $request->tanggal_spk,
                'nama_perusahaan' => $leads->nama_perusahaan,
                'tim_sales_id' => $leads->tim_sales_id,
                'tim_sales_d_id' => $leads->tim_sales_d_id,
                'link_spk_disetujui' => null,
                'status_spk_id' => 1,
                'created_by' => Auth::user()->full_name
            ]);

            // Create SPK Sites
            $this->createSpkSites($spk->id, $request->site_ids);

            // Create customer activity
            $this->createCustomerActivity($leads, $spk, $spkNomor);

            DB::commit();

            return $this->successResponse('SPK created successfully', $spk->load(['spkSites', 'leads', 'quotation']), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error creating SPK', $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/spk/view/{id}",
     *     summary="Mendapatkan detail SPK berdasarkan ID",
     *     description="Endpoint untuk mengambil data detail SPK termasuk leads, status, dan site-site yang terkait.",
     *     tags={"SPK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID SPK",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sukses mengambil detail SPK",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="SPK details retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nomor", type="string", example="SPK/LEAD001-012024-00001"),
     *                 @OA\Property(property="tgl_spk", type="string", example="2024-01-15"),
     *                 @OA\Property(property="stgl_spk", type="string", example="15 Januari 2024"),
     *                 @OA\Property(property="status", type="string", example="Draft"),
     *                 @OA\Property(property="screated_at", type="string", example="15 Januari 2024"),
     *                 @OA\Property(property="leads", type="object"),
     *                 @OA\Property(property="status_spk", type="object"),
     *                 @OA\Property(property="spk_sites", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="SPK tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="SPK not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error server",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error fetching SPK details")
     *         )
     *     )
     * )
     */
    public function view($id)
    {
        try {
            $spk = Spk::with([
                'leads',
                'statusSpk',
                'spkSites.quotation',
                'spkSites.quotationSite'
            ])->find($id);

            if (!$spk) {
                return $this->notFoundResponse('SPK not found');
            }

            // Format dates
            $spk->stgl_spk = Carbon::parse($spk->tgl_spk)->isoFormat('D MMMM Y');
            $spk->screated_at = Carbon::parse($spk->created_at)->isoFormat('D MMMM Y');
            $spk->status = $spk->statusSpk->nama ?? 'Unknown';

            return $this->successResponse('SPK details retrieved successfully', $spk);

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching SPK details', $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/spk/cetak/{id}",
     *     summary="Mendapatkan data untuk cetak SPK",
     *     description="Endpoint untuk mengambil semua data yang diperlukan untuk proses pencetakan SPK.",
     *     tags={"SPK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID SPK",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sukses mengambil data cetak SPK",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="SPK print data retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="now", type="string", example="15 Januari 2024"),
     *                 @OA\Property(property="spk", type="object"),
     *                 @OA\Property(property="spk_sites", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="quotation", type="object"),
     *                 @OA\Property(property="leads", type="object"),
     *                 @OA\Property(property="company", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="SPK tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="SPK not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Tidak ada site ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No SPK sites found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error server",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error fetching SPK print data")
     *         )
     *     )
     * )
     */

    public function cetakSpk($id)
    {
        try {
            $now = Carbon::now()->isoFormat('DD MMMM Y');

            $spk = Spk::with(['quotation', 'leads'])->find($id);
            if (!$spk) {
                return $this->notFoundResponse('SPK not found');
            }

            $spkSites = SpkSite::where('spk_id', $id)->get();

            if ($spkSites->isEmpty()) {
                return $this->errorResponse('No SPK sites found');
            }

            // LANGSUNG AMBIL DARI RELASI SPK
            $quotation = $spk->quotation;
            $leads = $spk->leads;

            // Get jabatan PIC
            if ($leads->jabatan) {
                $jabatanPic = JabatanPic::find($leads->jabatan);
                if ($jabatanPic) {
                    $leads->jabatan_nama = $jabatanPic->nama_jabatan;
                }
            }

            // Process quotation data
            if ($quotation) {
                $quotation->tgl_penempatan_formatted = $quotation->tgl_penempatan ?
                    Carbon::parse($quotation->tgl_penempatan)->isoFormat('D MMMM Y') : null;

                // Get quotation details menggunakan model
                $quotation->details = QuotationDetail::where('quotation_id', $quotation->id)
                    ->whereNull('deleted_at')
                    ->get();

                // Calculate total HC
                $quotation->total_hc = $quotation->details->sum('jumlah_hc');

                // Get PIC menggunakan model
                $quotation->pic = QuotationPic::where('quotation_id', $quotation->id)
                    ->where('is_kuasa', 1)
                    ->whereNull('deleted_at')
                    ->first();
            }

            // Get company
            $company = $quotation ? Company::find($quotation->company_id) : null;

            $data = [
                'now' => $now,
                'spk' => $spk,
                'spk_sites' => $spkSites,
                'quotation' => $quotation,
                'leads' => $leads,
                'company' => $company
            ];

            return $this->successResponse('SPK print data retrieved successfully', $data);

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching SPK print data', $e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/spk/upload/{id}",
     *     summary="Upload dokumen SPK yang sudah disetujui",
     *     description="Endpoint untuk mengupload file SPK yang sudah disetujui dan mengubah status SPK menjadi approved.",
     *     tags={"SPK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID SPK",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="File SPK (pdf, doc, docx, jpg, jpeg, png) maksimal 10MB"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File berhasil diupload",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="File uploaded successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="File tidak valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error")
     *         )
     *     )
     * )
     */
    public function uploadSpk(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240'
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $spk = Spk::find($id);
            if (!$spk) {
                return $this->notFoundResponse('SPK not found');
            }

            $fileName = $this->storeSpkFile($request->file('file'));

            $spk->update([
                'status_spk_id' => 2,
                'link_spk_disetujui' => env('APP_URL') . "/public/spk/" . $fileName,
                'updated_by' => Auth::user()->full_name
            ]);

            return $this->successResponse('File uploaded successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error uploading file', $e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/spk/ajukan-ulang/{spkId}",
     *     summary="Mengajukan ulang quotation dari SPK",
     *     description="Endpoint untuk mengajukan ulang quotation yang terkait dengan SPK. Akan membuat quotation baru dan menghapus SPK yang lama.",
     *     tags={"SPK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="spkId",
     *         in="path",
     *         required=true,
     *         description="ID SPK yang akan diajukan ulang",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"alasan"},
     *             @OA\Property(property="alasan", type="string", example="Perubahan harga material", description="Alasan pengajuan ulang quotation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quotation berhasil diajukan ulang",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Quotation successfully resubmitted"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="quotation_baru_id", type="integer", example=1),
     *                 @OA\Property(property="quotation_baru_nomor", type="string", example="QUOT/COMP001-012024-00002"),
     *                 @OA\Property(property="spk_id", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error")
     *         )
     *     )
     * )
     */
    public function ajukanUlangQuotation(Request $request, $spkId)
    {
        $validator = Validator::make($request->all(), [
            'alasan' => 'required|string|max:500'
            // HAPUS quotation_id KARENA SUDAH BISA DIAMBIL DARI SPK
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            DB::beginTransaction();

            $spk = Spk::with('quotation')->find($spkId);
            if (!$spk) {
                return $this->notFoundResponse('SPK not found');
            }

            $quotationAsal = $spk->quotation; // LANGSUNG DARI RELASI
            if (!$quotationAsal) {
                return $this->notFoundResponse('Quotation not found for this SPK');
            }

            // Generate new quotation number
            $nomorQuotationBaru = $this->generateNomorQuotation($quotationAsal->leads_id, $quotationAsal->company_id);

            // Create new quotation based on the original one
            $newQuotation = $this->createNewQuotation($quotationAsal, $nomorQuotationBaru, $request->alasan);

            // Copy all related data
            $this->copyQuotationRelatedData($quotationAsal->id, $newQuotation->id);

            // Update SPK dengan quotation baru
            $spk->update([
                'quotation_id' => $newQuotation->id,
                'updated_by' => Auth::user()->full_name
            ]);

            // Soft delete original quotation
            $quotationAsal->update([
                'deleted_at' => now(),
                'deleted_by' => Auth::user()->full_name
            ]);

            // Create customer activities
            $this->createResubmissionActivities($quotationAsal, $newQuotation, $spk);

            DB::commit();

            $responseData = [
                'quotation_baru_id' => $newQuotation->id,
                'quotation_baru_nomor' => $newQuotation->nomor,
                'spk_id' => $spk->id
            ];

            return $this->successResponse('Quotation successfully resubmitted', $responseData);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error resubmitting quotation', $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/spk/site-list/{id}",
     *     summary="Mendapatkan daftar site untuk SPK tertentu",
     *     description="Endpoint untuk mengambil data site yang terkait dengan SPK dan belum digunakan untuk pembuatan site.",
     *     tags={"SPK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID SPK",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sukses mengambil daftar site",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Site list retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="no", type="integer", example=1),
     *                 @OA\Property(property="nama_site", type="string", example="Site Jakarta"),
     *                 @OA\Property(property="provinsi", type="string", example="DKI Jakarta"),
     *                 @OA\Property(property="kota", type="string", example="Jakarta Selatan"),
     *                 @OA\Property(property="nomor_quotation", type="string", example="QUOT/COMP001-012024-00001"),
     *                 @OA\Property(property="quotation", type="object"),
     *                 @OA\Property(property="quotation_site", type="object")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error server",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error fetching site list")
     *         )
     *     )
     * )
     */
    public function getSiteList($id)
    {
        try {
            $sites = SpkSite::with(['quotation', 'quotationSite'])
                ->where('spk_id', $id)
                ->whereNull('deleted_at')
                ->whereDoesntHave('site')
                ->get()
                ->map(function ($site, $key) {
                    $site->no = $key + 1;
                    return $site;
                });

            return $this->successResponse('Site list retrieved successfully', $sites);

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching site list', $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/spk/available-sites/{leadsId}",
     *     summary="Mendapatkan daftar site yang tersedia untuk leads tertentu",
     *     description="Endpoint untuk mengambil data quotation site yang tersedia (belum memiliki SPK) untuk leads tertentu.",
     *     tags={"SPK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="leadsId",
     *         in="path",
     *         required=true,
     *         description="ID Leads",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sukses mengambil daftar site tersedia",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Available sites retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama_site", type="string", example="Site Jakarta"),
     *                 @OA\Property(property="provinsi", type="string", example="DKI Jakarta"),
     *                 @OA\Property(property="kota", type="string", example="Jakarta Selatan"),
     *                 @OA\Property(property="quotation", type="string", example="QUOT/COMP001-012024-00001"),
     *                 @OA\Property(property="ump", type="number", example=4500000),
     *                 @OA\Property(property="umk", type="number", example=4400000),
     *                 @OA\Property(property="nominal_upah", type="number", example=4300000),
     *                 @OA\Property(property="penempatan", type="string", example="Jakarta Office")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error server",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error fetching available sites")
     *         )
     *     )
     * )
     */
    public function getSiteAvailableList($leadsId)
    {
        try {
            $sites = QuotationSite::with(['quotation'])
                ->where('leads_id', $leadsId)
                ->whereNull('deleted_at')
                ->whereDoesntHave('spkSite')
                ->get()
                ->map(function ($site) {
                    return [
                        'id' => $site->id,
                        'nama_site' => $site->nama_site,
                        'provinsi' => $site->provinsi,
                        'kota' => $site->kota,
                        'quotation' => $site->quotation->nomor,
                        'ump' => $site->ump,
                        'umk' => $site->umk,
                        'nominal_upah' => $site->nominal_upah,
                        'penempatan' => $site->penempatan
                    ];
                });

            return $this->successResponse('Available sites retrieved successfully', $sites);

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching available sites', $e->getMessage());
        }
    }

    /**
     * =============================================
     * PRIVATE HELPER METHODS
     * =============================================
     */

    private function createSpkSites($spkId, $siteIds): void
    {
        $spk = Spk::find($spkId);

        foreach ($siteIds as $siteId) {
            $quotationSite = QuotationSite::find($siteId);
            $quotation = $spk->quotation; // GUNAKAN QUOTATION DARI SPK

            SpkSite::create([
                'spk_id' => $spkId,
                'quotation_id' => $spk->quotation_id, // GUNAKAN QUOTATION_ID DARI SPK
                'quotation_site_id' => $quotationSite->id,
                'leads_id' => $quotationSite->leads_id,
                'nama_site' => $quotationSite->nama_site,
                'provinsi_id' => $quotationSite->provinsi_id,
                'provinsi' => $quotationSite->provinsi,
                'kota_id' => $quotationSite->kota_id,
                'kota' => $quotationSite->kota,
                'ump' => $quotationSite->ump,
                'umk' => $quotationSite->umk,
                'nominal_upah' => $quotationSite->nominal_upah,
                'penempatan' => $quotationSite->penempatan,
                'kebutuhan_id' => $quotation->kebutuhan_id,
                'kebutuhan' => $quotation->kebutuhan,
                'jenis_site' => $quotation->jumlah_site,
                'nomor_quotation' => $quotation->nomor,
                'created_by' => Auth::user()->full_name
            ]);
        }
    }

    private function createCustomerActivity($leads, $spk, $spkNomor): void
    {
        $nomorActivity = $this->generateActivityNomor($leads->id);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'spk_id' => $spk->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $nomorActivity,
            'tipe' => 'SPK',
            'notes' => 'SPK dengan nomor : ' . $spkNomor . ' terbentuk',
            'is_activity' => 0,
            'user_id' => Auth::user()->id,
            'created_by' => Auth::user()->full_name
        ]);
    }

    private function storeSpkFile($file)
    {
        $fileExtension = $file->getClientOriginalExtension();
        $originalFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $fileName = $originalFileName . date("YmdHis") . rand(10000, 99999) . "." . $fileExtension;

        Storage::disk('spk')->put($fileName, file_get_contents($file));

        return $fileName;
    }

    private function generateNomorNew($leadsId)
    {
        $now = Carbon::now();
        $leads = Leads::find($leadsId);

        $baseNumber = "SPK/" . $leads->nomor . "-";
        $month = $now->month < 10 ? "0" . $now->month : $now->month;

        $count = Spk::where('nomor', 'like', $baseNumber . $month . $now->year . "-%")->count();
        $sequence = sprintf("%05d", $count + 1);

        return $baseNumber . $month . $now->year . "-" . $sequence;
    }

    private function generateActivityNomor($leadsId)
    {
        $count = CustomerActivity::where('leads_id', $leadsId)->count();
        return sprintf("%03d", $count + 1);
    }

    private function getQuotationDetails($quotationId)
    {
        // Gunakan model QuotationDetail
        return QuotationDetail::whereNull('deleted_at')
            ->where('quotation_id', $quotationId)
            ->get();
    }

    private function getQuotationPic($quotationId)
    {
        // Gunakan model QuotationPic
        return QuotationPic::whereNull('deleted_at')
            ->where('quotation_id', $quotationId)
            ->where('is_kuasa', 1)
            ->first();
    }

    private function generateNomorQuotation($leadsId, $companyId)
    {
        $now = Carbon::now();
        $leads = Leads::find($leadsId);
        $company = Company::find($companyId);

        $nomor = "QUOT/";

        if ($company) {
            $nomor .= $company->code . "/";
            $nomor .= $leads->nomor . "-";
        } else {
            $nomor .= "NN/NNNNN-";
        }

        $month = $now->month < 10 ? "0" . $now->month : $now->month;

        $count = Quotation::where('nomor', 'like', $nomor . $month . $now->year . "-%")->count();
        $sequence = sprintf("%05d", $count + 1);

        return $nomor . $month . $now->year . "-" . $sequence;
    }

    private function createNewQuotation($quotationAsal, $nomorQuotationBaru, $alasan)
    {
        $newQuotationData = $quotationAsal->toArray();

        // Remove unnecessary fields
        unset(
            $newQuotationData['id'],
            $newQuotationData['nomor'],
            $newQuotationData['created_at'],
            $newQuotationData['updated_at'],
            $newQuotationData['deleted_at']
        );

        // Update with new data
        $newQuotationData['nomor'] = $nomorQuotationBaru;
        $newQuotationData['revisi'] = ($quotationAsal->revisi ?? 0) + 1;
        $newQuotationData['alasan_revisi'] = $alasan;
        $newQuotationData['quotation_asal_id'] = $quotationAsal->id;
        $newQuotationData['created_at'] = now();
        $newQuotationData['created_by'] = Auth::user()->full_name;
        $newQuotationData['updated_at'] = null;
        $newQuotationData['updated_by'] = null;

        // Reset some fields - SEKARANG BISA DIPAKAI KARENA SUDAH DITAMBAHKAN DI MODEL
        $newQuotationData['ot1'] = null;
        $newQuotationData['ot2'] = null;
        $newQuotationData['ot3'] = null;

        // Determine status based on conditions
        $isAktif = 1;
        $statusQuotation = 1;

        // Periksa apakah field top dan persentase ada sebelum mengaksesnya
        if (isset($quotationAsal->top) && $quotationAsal->top == "Lebih Dari 7 Hari") {
            $isAktif = 0;
            $statusQuotation = 2;
        } elseif (isset($quotationAsal->persentase) && $quotationAsal->persentase < 7) {
            $isAktif = 0;
            $statusQuotation = 2;
        }

        $newQuotationData['status_quotation_id'] = $statusQuotation;
        $newQuotationData['is_aktif'] = $isAktif;
        $newQuotationData['step'] = 1;

        return Quotation::create($newQuotationData);
    }
    private function copyQuotationRelatedData($quotationAsalId, $quotationBaruId): void
    {
        $models = [
            QuotationSite::class,
            QuotationDetail::class,
            QuotationDetailRequirement::class,
            QuotationDetailHpp::class,
            QuotationDetailCoss::class,
            QuotationDetailTunjangan::class,
            QuotationKaporlap::class,
            QuotationDevices::class,
            QuotationChemical::class,
            QuotationOhc::class,
            QuotationAplikasi::class,
            QuotationKerjasama::class,
            QuotationPic::class,
            QuotationTraining::class
        ];

        foreach ($models as $model) {
            $this->copyModelData($model, $quotationAsalId, $quotationBaruId);
        }
    }

    private function copyModelData($modelClass, $quotationAsalId, $quotationBaruId): void
    {
        $records = $modelClass::where('quotation_id', $quotationAsalId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($records as $record) {
            $newRecord = $record->replicate();
            $newRecord->quotation_id = $quotationBaruId;
            $newRecord->created_at = now();
            $newRecord->created_by = Auth::user()->full_name;
            $newRecord->save();
        }
    }
    private function createResubmissionActivities($quotationAsal, $newQuotation, $spk): void
    {
        $leads = Leads::find($quotationAsal->leads_id);

        // Activity for original quotation resubmission
        CustomerActivity::create([
            'leads_id' => $leads->id,
            'quotation_id' => $quotationAsal->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $this->generateActivityNomor($leads->id),
            'tipe' => 'Quotation',
            'notes' => 'Quotation dengan nomor : ' . $quotationAsal->nomor . ' di ajukan ulang',
            'is_activity' => 0,
            'user_id' => Auth::user()->id,
            'created_by' => Auth::user()->full_name
        ]);

        // Activity for new quotation creation
        CustomerActivity::create([
            'leads_id' => $leads->id,
            'quotation_id' => $newQuotation->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $this->generateActivityNomor($leads->id),
            'tipe' => 'Quotation',
            'notes' => 'Quotation dengan nomor : ' . $newQuotation->nomor . ' terbentuk dari ajukan ulang quotation dengan nomor : ' . $quotationAsal->nomor,
            'is_activity' => 0,
            'user_id' => Auth::user()->id,
            'created_by' => Auth::user()->full_name
        ]);
    }

    /**
     * =============================================
     * RESPONSE HELPER METHODS
     * =============================================
     */

    private function successResponse(string $message, $data = null, int $status = 200)
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    private function errorResponse(string $message, string $error = null, int $status = 500)
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($error && config('app.debug')) {
            $response['error'] = $error;
        }

        return response()->json($response, $status);
    }

    private function validationError($errors, int $status = 400)
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation error',
            'errors' => $errors
        ], $status);
    }

    private function notFoundResponse(string $message = 'Resource not found')
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 404);
    }
}