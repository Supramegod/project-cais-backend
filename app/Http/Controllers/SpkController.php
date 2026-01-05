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
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'desc');

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
    public function availableLeads(Request $request)
    {
        try {
            $user = Auth::user();

            $query = Leads::whereHas('quotations.quotationSites', function ($query) {
                $query->whereNull('deleted_at')
                    ->whereDoesntHave('spkSite', function ($q) {
                        $q->whereNull('deleted_at');
                    });
            })
                ->whereHas('quotations', function ($query) {
                    $query->whereNull('deleted_at')
                        ->where('is_aktif', 1);
                });

            // Role 2 (Superadmin) bisa lihat semua data
            // Role lainnya hanya bisa lihat data tim sales mereka
            if ($user->role_id != 2) {
                $query->whereHas('timSalesD', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                });
            }

            $data = $query->select('id', 'nomor', 'nama_perusahaan', 'provinsi', 'kota')
                ->distinct()
                ->get();

            return $this->successResponse('Available leads retrieved successfully', $data);

        } catch (\Exception $e) {
            \Log::error('Error in availableLeads: ' . $e->getMessage());
            return $this->errorResponse('Error fetching available leads', $e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/spk/add",
     *     summary="Membuat SPK baru",
     *     description="Endpoint untuk membuat SPK baru berdasarkan leads dan site yang dipilih.",
     *     tags={"SPK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leads_id", "tanggal_spk", "site_ids"},
     *             @OA\Property(property="leads_id", type="integer", example=1, description="ID Leads"),
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
     *                 @OA\Property(property="spk_sites", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="leads", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function add(Request $request)
    {
        // Ekstrak hanya ID dari array of objects jika diperlukan
        $siteIds = $request->site_ids;

        // Jika site_ids adalah array of objects, ekstrak id-nya
        if (is_array($siteIds) && count($siteIds) > 0 && is_array($siteIds[0])) {
            $siteIds = array_column($siteIds, 'id');
        }

        // Validasi dengan data yang sudah diolah
        $validator = Validator::make(array_merge($request->all(), ['site_ids' => $siteIds]), [
            'leads_id' => 'required|exists:sl_leads,id',
            'tanggal_spk' => 'required|date',
            'site_ids' => 'required|array|min:1',
            'site_ids.*' => 'exists:sl_quotation_site,id'
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            DB::beginTransaction();

            $leads = Leads::whereNull('deleted_at')->find($request->leads_id);

            if (!$leads) {
                return $this->errorResponse("Leads dengan ID {$request->leads_id} tidak ditemukan atau sudah dihapus.");
            }

            // Validasi: pastikan semua site_ids termasuk dalam leads yang dipilih
            $invalidSites = QuotationSite::whereIn('id', $siteIds)
                ->where('leads_id', '!=', $request->leads_id)
                ->exists();

            if ($invalidSites) {
                return $this->errorResponse("Beberapa site yang dipilih tidak termasuk dalam leads yang dipilih.");
            }

            // Validasi: pastikan site belum memiliki SPK
            $sitesWithSPK = QuotationSite::whereIn('id', $siteIds)
                ->whereHas('spkSite')
                ->exists();

            if ($sitesWithSPK) {
                return $this->errorResponse("Beberapa site yang dipilih sudah memiliki SPK.");
            }

            // Ambil quotation_id dari site pertama (jika diperlukan)
            $firstSite = QuotationSite::find($siteIds[0]);
            $quotationId = $firstSite ? $firstSite->quotation_id : null;

            $spkNomor = $this->generateNomorNew($leads->id);

            // Buat SPK
            $spk = Spk::create([
                'leads_id' => $leads->id,
                'quotation_id' => $quotationId,
                'nomor' => $spkNomor,
                'tgl_spk' => $request->tanggal_spk,
                'nama_perusahaan' => $leads->nama_perusahaan,
                'tim_sales_id' => $leads->tim_sales_id,
                'tim_sales_d_id' => $leads->tim_sales_d_id,
                'link_spk_disetujui' => null,
                'status_spk_id' => 1,
                'created_by' => Auth::user()->full_name ?? 'System'
            ]);

            $this->createSpkSites($spk, $siteIds);
            $this->createCustomerActivity($leads, $spk, $spkNomor);

            DB::commit();

            return $this->successResponse('SPK created successfully', $spk->load(['spkSites', 'leads']), 201);

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
                'leads.jabatanPic',
                'statusSpk',
                'spkSites.quotation',  // Relasi ke quotation dari spkSite
                'spkSites.quotation.quotationPics.jabatan', // Relasi ke PIC dengan jabatan
                'spkSites.quotation.company', // Relasi ke company untuk alamat
                'spkSites.quotation.quotationDetails', // Relasi ke details untuk HC
                'spkSites' // Relasi ke site
            ])->find($id);

            if (!$spk) {
                return $this->notFoundResponse('SPK not found');
            }
            // DEBUG: Cek apakah company dimuat dengan benar
            if ($spk->spkSites->isNotEmpty()) {
                $firstSite = $spk->spkSites->first();
                if ($firstSite->quotation) {
                    // Cek ini di browser/Postman
                    \Log::info('Quotation ID: ' . $firstSite->quotation->id);
                    \Log::info('Company ID: ' . ($firstSite->quotation->company_id ?? 'null'));
                    \Log::info('Company loaded: ' . ($firstSite->quotation->relationLoaded('company') ? 'yes' : 'no'));
                    // \Log::info('Company object: ', $firstSite->quotation->company ? $firstSite->quotation->company->toArray() : ['null']);
                }
            }

            // 1. Informasi SPK
            $spkInfo = [
                'nomor_spk' => $spk->nomor,
                'tanggal_spk' => $spk->tgl_spk,
            ];
            // 2. Informasi Leads
            $leadsInfo = [
                'id' => $spk->leads->id ?? null,
                'nama_perusahaan' => $spk->leads->nama_perusahaan ?? null,
                'nama_pic' => $spk->leads->pic ?? null,
                'telepon_pic' => $spk->leads->no_telp ?? null,
                'email_pic' => $spk->leads->email ?? null,
                'alamat_perusahaan' => $spk->leads->alamat ?? null,
                'jabatan_nama' => $spk->leads->jabatanPic?->nama ?? null,
            ];

            // 3. Informasi Quotation (ARRAY karena bisa lebih dari satu)
            $quotationsInfo = [];

            // Kelompokkan quotation berdasarkan ID untuk menghindari duplikat
            $uniqueQuotations = collect();

            foreach ($spk->spkSites as $spkSite) {
                if ($spkSite->quotation && !$uniqueQuotations->contains('id', $spkSite->quotation->id)) {
                    $uniqueQuotations->push($spkSite->quotation);
                }
            }

            foreach ($uniqueQuotations as $quotation) {
                // Cari PIC utama (is_kuasa = 1)
                // fallback: ambil Company via relation() jika properti ->company bukan model
                $companyModel = null;
                if ($quotation->relationLoaded('company') && $quotation->company instanceof Company) {
                    $companyModel = $quotation->company;
                } elseif (!empty($quotation->company_id)) {
                    $companyModel = Company::find($quotation->company_id);
                }

                // $picKuasa = $quotation->quotationPics->where('is_kuasa', 1)->first();
                // $totalHc = $quotation->quotationDetails->sum('jumlah_hc');
                // $posisiJabatan = $quotation->quotationDetails->first()?->jabatan_kebutuhan ?? null;

                $quotationsInfo[] = [
                    'id' => $quotation->id,
                    'nomor_quotation' => $quotation->nomor ?? null,
                    'kebutuhan' => $quotation->kebutuhan ?? null,
                    'jenis_kontrak' => $quotation->jenis_kontrak ?? null,
                    'tanggal_penempatan' => $quotation->tgl_penempatan ?? null,
                    'company_name' => $companyModel ? ($companyModel->name ?? null) : null,
                    'company_address' => $companyModel ? ($companyModel->address ?? null) : null,
                    'tanggal_quotation' => $quotation->tgl_quotation ?? null,
                    'npwp' => $quotation->npwp ?? null,
                    'quotation_details' => $quotation->quotationDetails->map(function ($detail) {
                        return [
                            'id' => $detail->id,
                            'jabatan_kebutuhan' => $detail->jabatan_kebutuhan,
                            'jumlah_hc' => $detail->jumlah_hc
                        ];
                    })
                ];
            }

            // 4. Informasi Site (array karena bisa banyak site)
            $sitesInfo = $spk->spkSites->map(function ($site) {
                return [
                    'id' => $site->id,
                    'nama_site' => $site->nama_site,
                    'kota' => $site->kota,
                    'penempatan' => $site->penempatan,
                    'quotation_id' => $site->quotation_id
                ];
            });

            // Struktur respons akhir
            $responseData = [
                'spk' => $spkInfo,
                'leads' => $leadsInfo,
                'quotations' => $quotationsInfo,
                'sites' => $sitesInfo
            ];

            return $this->successResponse('SPK details retrieved successfully', $responseData);

        } catch (\Exception $e) {
            // Untuk debugging, tambahkan ini:
            \Log::error('SPK View Error: ' . $e->getMessage());
            \Log::error('SPK View Stack Trace: ' . $e->getTraceAsString());

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
     *             @OA\Property(property="message", type="string", example="SPK file uploaded successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nomor", type="string", example="SPK/LEAD001-012024-00001"),
     *                 @OA\Property(property="status_spk_id", type="integer", example=2),
     *                 @OA\Property(property="link_spk_disetujui", type="string", example="http://example.com/public/spk/file.pdf")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="File tidak valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error")
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
     *             @OA\Property(property="message", type="string", example="Error uploading SPK file")
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
            DB::beginTransaction();

            $spk = Spk::find($id);

            if (!$spk) {
                return $this->notFoundResponse('SPK not found');
            }

            // Hapus file lama jika ada
            if ($spk->link_spk_disetujui) {
                $oldFileName = basename($spk->link_spk_disetujui);
                if (Storage::disk('spk')->exists($oldFileName)) {
                    Storage::disk('spk')->delete($oldFileName);
                }
            }

            // Upload file baru
            $fileName = $this->storeSpkFile($request->file('file'));

            // ✅ Generate URL yang benar
            $fileUrl = url('spk/' . $fileName);
            \Log::info('Generated URL: ' . $fileUrl);
            \Log::info('Filename: ' . $fileName);
            \Log::info('File path: ' . public_path('spk/' . $fileName));
            \Log::info('File exists: ' . (file_exists(public_path('spk/' . $fileName)) ? 'Yes' : 'No'));


            $spk->update([
                'status_spk_id' => 2,
                'link_spk_disetujui' => $fileUrl,
                'updated_by' => Auth::user()->full_name
            ]);

            // Catat aktivitas
            $this->createUploadActivity($spk);

            DB::commit();

            $spk->load(['statusSpk']);


            return $this->successResponse('SPK file uploaded successfully', [
                'id' => $spk->id,
                'nomor' => $spk->nomor,
                'status_spk_id' => $spk->status_spk_id,
                'status' => $spk->statusSpk->nama ?? null,
                'link_spk_disetujui' => $spk->link_spk_disetujui
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            if (isset($fileName) && Storage::disk('spk')->exists($fileName)) {
                Storage::disk('spk')->delete($fileName);
            }

            return $this->errorResponse('Error uploading SPK file', $e->getMessage());
        }
    }
    /**
     * @OA\Post(
     *     path="/api/spk/ajukan-ulang/{spkId}",
     *     summary="Mengajukan ulang quotation dari SPK",
     *     description="Endpoint untuk mengajukan ulang quotation yang terkait dengan SPK. Jika hanya sebagian site yang diajukan ulang, SPK tetap dipertahankan. Jika semua site diajukan ulang, SPK akan dihapus.",
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
     *             required={"alasan", "quotation_site_ids"},
     *             @OA\Property(property="alasan", type="string", example="Perubahan harga material", description="Alasan pengajuan ulang quotation"),
     *             @OA\Property(property="quotation_site_ids", type="array", @OA\Items(type="integer"), example={1,2,3}, description="Array ID Quotation Site yang akan diajukan ulang")
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
     *                 @OA\Property(property="spk_id", type="integer", example=1),
     *                 @OA\Property(property="spk_dihapus", type="boolean", example=false),
     *                 @OA\Property(property="spk_sites_dihapus", type="array", @OA\Items(type="integer"), example={1,2,3}),
     *                 @OA\Property(property="quotation_sites_dihapus", type="array", @OA\Items(type="integer"), example={4,5,6})
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
            'alasan' => 'required|string|max:500',
            'quotation_site_ids' => 'required|array|min:1',
            'quotation_site_ids.*' => 'exists:sl_quotation_site,id'
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            DB::beginTransaction();

            // Cari SPK beserta spk_sites dan quotation dari spk_sites
            $spk = Spk::with(['spkSites.quotation', 'spkSites.quotationSite'])->find($spkId);

            if (!$spk) {
                return $this->notFoundResponse('SPK not found');
            }

            // Ambil semua spk_sites yang terkait dengan SPK
            $allSpkSites = $spk->spkSites;

            if ($allSpkSites->isEmpty()) {
                return $this->errorResponse('Tidak ada site yang terkait dengan SPK ini.');
            }

            // Filter spk_sites yang akan diajukan ulang berdasarkan quotation_site_ids
            $spkSitesToResubmit = $allSpkSites->whereIn('quotation_site_id', $request->quotation_site_ids);

            if ($spkSitesToResubmit->isEmpty()) {
                return $this->errorResponse('Tidak ada site yang valid untuk diajukan ulang.');
            }

            // Kelompokkan berdasarkan quotation_id
            $quotationGroups = $spkSitesToResubmit->groupBy('quotation_id');

            $newQuotations = [];
            $deletedSpkSiteIds = [];
            $deletedQuotationSiteIds = [];

            foreach ($quotationGroups as $quotationId => $spkSites) {
                $quotationAsal = $spkSites->first()->quotation;

                if (!$quotationAsal) {
                    continue;
                }

                // Generate new quotation number
                $nomorQuotationBaru = $this->generateNomorQuotation($quotationAsal->leads_id, $quotationAsal->company_id);

                // Create new quotation based on the original one
                $newQuotation = $this->createNewQuotation($quotationAsal, $nomorQuotationBaru, $request->alasan);
                $newQuotations[] = $newQuotation;

                // Copy all related data
                $this->copyQuotationRelatedData($quotationAsal->id, $newQuotation->id);

                // Kumpulkan spk_site_ids dan quotation_site_ids yang akan dihapus
                foreach ($spkSites as $spkSite) {
                    $deletedSpkSiteIds[] = $spkSite->id;
                    $deletedQuotationSiteIds[] = $spkSite->quotation_site_id;
                }

                // Soft delete original quotation
                $quotationAsal->update([
                    'deleted_at' => now(),
                    'deleted_by' => Auth::user()->full_name
                ]);

                // Soft delete quotation sites yang terkait
                QuotationSite::whereIn('id', $deletedQuotationSiteIds)
                    ->update([
                        'deleted_at' => now(),
                        'deleted_by' => Auth::user()->full_name
                    ]);
            }

            // Hapus spk_sites yang dipilih
            SpkSite::whereIn('id', $deletedSpkSiteIds)
                ->update([
                    'deleted_at' => now(),
                    'deleted_by' => Auth::user()->full_name
                ]);

            // Cek apakah masih ada spk_sites yang aktif di SPK ini
            $remainingSpkSites = SpkSite::where('spk_id', $spk->id)
                ->whereNull('deleted_at')
                ->count();

            $spkDeleted = false;

            // Kondisi 1: Jika semua site di SPK diajukan ulang, hapus SPK
            // Kondisi 2: Jika hanya sebagian site yang diajukan ulang, SPK tetap dipertahankan
            if ($remainingSpkSites === 0) {
                // Hapus SPK (soft delete) karena semua site sudah diajukan ulang
                $spk->update([
                    'deleted_at' => now(),
                    'deleted_by' => Auth::user()->full_name
                ]);
                $spkDeleted = true;
            }

            // Create customer activities
            $this->createResubmissionActivities($quotationAsal, $newQuotation, $spk, $spkDeleted, $deletedSpkSiteIds, $deletedQuotationSiteIds);

            DB::commit();

            $responseData = [
                'quotation_baru_id' => $newQuotation->id,
                'quotation_baru_nomor' => $newQuotation->nomor,
                'spk_id' => $spk->id,
                'spk_dihapus' => $spkDeleted,
                'spk_sites_dihapus' => $deletedSpkSiteIds,
                'quotation_sites_dihapus' => $deletedQuotationSiteIds
            ];

            $message = $spkDeleted
                ? 'Quotation successfully resubmitted and SPK deleted'
                : 'Quotation successfully resubmitted for selected sites';

            return $this->successResponse($message, $responseData);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error resubmitting quotation', $e->getMessage());
        }
    }




    /**
     * @OA\Get(
     *     path="/api/spk/deleted-sites/{spkId}",
     *     summary="Mendapatkan daftar SPK sites yang sudah dihapus",
     *     description="Endpoint untuk mengambil data SPK sites yang telah dihapus (soft delete) berdasarkan ID SPK.",
     *     tags={"SPK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="spkId",
     *         in="path",
     *         required=true,
     *         description="ID SPK",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sukses mengambil data SPK sites yang dihapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Deleted SPK sites retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama_site", type="string", example="Site Jakarta Pusat"),
     *                 @OA\Property(property="quotation_site_id", type="integer", example=5),
     *                 @OA\Property(property="deleted_at", type="string", format="date-time", example="2024-01-20 15:30:00"),
     *                 @OA\Property(property="deleted_by", type="string", example="John Doe")
     *             ))
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
     *             @OA\Property(property="message", type="string", example="Error fetching deleted SPK sites")
     *         )
     *     )
     * )
     */
    public function getDeletedSpkSites($spkId)
    {
        try {
            // Validasi apakah SPK exists (termasuk yang sudah dihapus)
            $spkExists = Spk::withTrashed()->where('id', $spkId)->exists();

            if (!$spkExists) {
                return $this->notFoundResponse('SPK not found');
            }

            $deletedSites = SpkSite::onlyTrashed()
                ->where('spk_id', $spkId)
                ->with(['quotation', 'quotationSite'])
                ->get()
                ->map(function ($site) {
                    return [
                        'id' => $site->id,
                        'nama_site' => $site->nama_site,
                        'quotation_site_id' => $site->quotation_site_id,
                        'deleted_at' => $site->deleted_at,
                        'deleted_by' => $site->deleted_by
                    ];
                });

            return $this->successResponse('Deleted SPK sites retrieved successfully', $deletedSites);

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching deleted SPK sites', $e->getMessage());
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
                // ->whereDoesntHave('spkSite')
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
     * @OA\Delete(
     *     path="/api/spk/delete/{id}",
     *     summary="Menghapus SPK berdasarkan ID (soft delete)",
     *     description="Endpoint untuk menghapus SPK. Penghapusan dilakukan secara soft delete, sehingga data tidak benar-benar dihapus dari database.",
     *     tags={"SPK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID SPK yang akan dihapus",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SPK berhasil dihapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="SPK deleted successfully")
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
     *             @OA\Property(property="message", type="string", example="Error deleting SPK")
     *         )
     *     )
     * )
     */
    public function delete($id)
    {
        try {
            DB::beginTransaction();

            $spk = Spk::find($id);

            if (!$spk) {
                return $this->notFoundResponse('SPK not found');
            }

            // Soft delete semua SpkSite yang terkait
            SpkSite::where('spk_id', $id)
                ->update([
                    'deleted_at' => now(),
                    'deleted_by' => Auth::user()->full_name
                ]);

            // Soft delete SPK
            $spk->update([
                'deleted_at' => now(),
                'deleted_by' => Auth::user()->full_name
            ]);

            // Catat aktivitas
            $this->createDeleteActivity($spk);

            DB::commit();

            return $this->successResponse('SPK deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error deleting SPK', $e->getMessage());
        }
    }
    /**
     * @OA\Delete(
     *     path="/api/spk/delete-site/{siteId}",
     *     summary="Menghapus SpkSite berdasarkan ID (soft delete)",
     *     description="Endpoint untuk menghapus SpkSite individual. Penghapusan dilakukan secara soft delete.",
     *     tags={"SPK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="siteId",
     *         in="path",
     *         required=true,
     *         description="ID SpkSite yang akan dihapus",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SpkSite berhasil dihapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="SPK site deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="SpkSite tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="SPK site not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error server",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error deleting SPK site")
     *         )
     *     )
     * )
     */
    public function deleteSite($siteId)
    {
        try {
            DB::beginTransaction();

            $spkSite = SpkSite::find($siteId);

            if (!$spkSite) {
                return $this->notFoundResponse('SPK site not found');
            }

            // Soft delete SpkSite
            $spkSite->update([
                'deleted_at' => now(),
                'deleted_by' => Auth::user()->full_name
            ]);

            // Cek apakah SPK masih memiliki site aktif
            $remainingSites = SpkSite::where('spk_id', $spkSite->spk_id)
                ->whereNull('deleted_at')
                ->count();

            // Jika tidak ada site aktif lagi, hapus SPK juga
            if ($remainingSites === 0) {
                $spk = Spk::find($spkSite->spk_id);
                if ($spk) {
                    $spk->update([
                        'deleted_at' => now(),
                        'deleted_by' => Auth::user()->full_name
                    ]);

                    $this->createDeleteActivity($spk);
                }
            }

            DB::commit();

            return $this->successResponse('SPK site deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error deleting SPK site', $e->getMessage());
        }
    }

    /**
     * =============================================
     * PRIVATE HELPER METHODS
     * =============================================
     */

    private function createSpkSites(Spk $spk, $siteIds): void
    {
        foreach ($siteIds as $siteId) {
            $quotationSite = QuotationSite::with('quotation')->find($siteId);

            if (!$quotationSite) {
                throw new \Exception("Quotation site dengan ID {$siteId} tidak ditemukan.");
            }

            // Pastikan quotation site termasuk dalam leads yang sama dengan SPK
            if ($quotationSite->leads_id != $spk->leads_id) {
                throw new \Exception("Quotation site dengan ID {$siteId} tidak termasuk dalam leads yang dipilih.");
            }

            SpkSite::create([
                'spk_id' => $spk->id,
                'quotation_id' => $quotationSite->quotation_id, // Ambil dari quotation_site
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
                'kebutuhan_id' => $quotationSite->quotation->kebutuhan_id,
                'kebutuhan' => $quotationSite->quotation->kebutuhan,
                'jenis_site' => $quotationSite->quotation->jumlah_site,
                'nomor_quotation' => $quotationSite->quotation->nomor,
                'created_by' => Auth::user()->full_name ?? 'System'
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
        // Tambahkan pengecekan null untuk leads
        $leads = Leads::whereNull('deleted_at')->find($leadsId);
        if (!$leads) {
            return $this->errorResponse("Leads dengan ID {$leadsId} tidak ditemukan");
        }

        $baseNumber = "SPK/" . $leads->nomor . "-";
        $month = $now->month < 10 ? "0" . $now->month : $now->month;

        $count = Spk::where('nomor', 'like', $baseNumber . $month . $now->year . "-%")->count();
        $sequence = sprintf("%05d", $count + 1);

        return $baseNumber . $month . $now->year . "-" . $sequence;
    }

    private function generateActivityNomor($leadsId)
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

        // Reset approval fields
        $newQuotationData['ot1'] = null;
        $newQuotationData['ot2'] = null;
        $newQuotationData['ot3'] = null;
        // ✅ SOLUSI SEDERHANA: Reset tanggal ke hari ini
        $newQuotationData['tgl_quotation'] = now()->format('Y-m-d');
        $newQuotationData['tgl_penempatan'] = null; // atau now()->format('Y-m-d')


        // Determine status based on conditions - SESUAI CONTROLLER LAMA
        $isAktif = 1;
        $statusQuotation = 1;

        if ($quotationAsal->top == "Lebih Dari 7 Hari") {
            $isAktif = 0;
            $statusQuotation = 2;
        }

        if ($quotationAsal->persentase < 7) {
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
    /**
     * Update method createResubmissionActivities untuk menangani kedua kondisi dan mencatat SPK sites & quotation sites yang dihapus
     */
    private function createResubmissionActivities($quotationAsal, $newQuotation, $spk, $spkDeleted, $deletedSpkSiteIds, $deletedQuotationSiteIds): void
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

        // Activity untuk SPK sites yang dihapus
        if (!empty($deletedSpkSiteIds)) {
            $spkSiteCount = count($deletedSpkSiteIds);
            CustomerActivity::create([
                'leads_id' => $leads->id,
                'spk_id' => $spk->id,
                'branch_id' => $leads->branch_id,
                'tgl_activity' => now(),
                'nomor' => $this->generateActivityNomor($leads->id),
                'tipe' => 'SPK Site',
                'notes' => $spkSiteCount . ' SPK site dihapus karena quotation diajukan ulang',
                'is_activity' => 0,
                'user_id' => Auth::user()->id,
                'created_by' => Auth::user()->full_name
            ]);
        }

        // Activity untuk Quotation sites yang dihapus
        if (!empty($deletedQuotationSiteIds)) {
            $quotationSiteCount = count($deletedQuotationSiteIds);
            CustomerActivity::create([
                'leads_id' => $leads->id,
                'quotation_id' => $quotationAsal->id,
                'branch_id' => $leads->branch_id,
                'tgl_activity' => now(),
                'nomor' => $this->generateActivityNomor($leads->id),
                'tipe' => 'Quotation Site',
                'notes' => $quotationSiteCount . ' Quotation site dihapus karena diajukan ulang',
                'is_activity' => 0,
                'user_id' => Auth::user()->id,
                'created_by' => Auth::user()->full_name
            ]);
        }

        // Activity untuk SPK hanya jika dihapus
        if ($spkDeleted) {
            CustomerActivity::create([
                'leads_id' => $leads->id,
                'spk_id' => $spk->id,
                'branch_id' => $leads->branch_id,
                'tgl_activity' => now(),
                'nomor' => $this->generateActivityNomor($leads->id),
                'tipe' => 'SPK',
                'notes' => 'SPK dengan nomor : ' . $spk->nomor . ' dihapus karena semua quotation site diajukan ulang',
                'is_activity' => 0,
                'user_id' => Auth::user()->id,
                'created_by' => Auth::user()->full_name
            ]);
        }
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

    /**
     * Helper method untuk membuat aktivitas penghapusan SPK
     */
    private function createDeleteActivity($spk): void
    {
        $leads = Leads::find($spk->leads_id);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'spk_id' => $spk->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $this->generateActivityNomor($leads->id),
            'tipe' => 'SPK',
            'notes' => 'SPK dengan nomor : ' . $spk->nomor . ' dihapus',
            'is_activity' => 0,
            'user_id' => Auth::user()->id,
            'created_by' => Auth::user()->full_name
        ]);
    }
    /**
     * Helper method untuk membuat aktivitas upload SPK
     */
    private function createUploadActivity($spk): void
    {
        $leads = Leads::find($spk->leads_id);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'spk_id' => $spk->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $this->generateActivityNomor($leads->id),
            'tipe' => 'SPK',
            'notes' => 'SPK dengan nomor : ' . $spk->nomor . ' telah diupload dan disetujui',
            'is_activity' => 0,
            'user_id' => Auth::user()->id,
            'created_by' => Auth::user()->full_name
        ]);
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
    /**
     * Unified validation error response
     * Digunakan untuk semua jenis error (validation, not found, server error, dll)
     * 
     * @param mixed $errors - Bisa berupa string, array, atau MessageBag dari validator
     * @param int $status - HTTP status code
     */
    private function validationError($errors, int $status = 400)
    {
        // Jika errors adalah MessageBag dari validator, convert ke array
        if (is_object($errors) && method_exists($errors, 'toArray')) {
            $errors = $errors->toArray();
        }

        return response()->json([
            'success' => false,
            'message' => $errors
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