<?php

namespace App\Http\Controllers;

use App\Http\Requests\ComparePerjanjianRequest;
use App\Http\Requests\PksStoreRequest;
use App\Http\Requests\PksUpdateRequest;
use App\Http\Requests\StorePasalRequest;
use App\Http\Requests\UpdatePerjanjianRequest;
use App\Http\Requests\UploadPksRequest;
use App\Http\Resources\QuotationResource;
use App\Models\Client;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\HrisSite;
use App\Models\JabatanPic;
use App\Models\KategoriSesuaiHc;
use App\Models\Kebutuhan;
use App\Models\Leads;
use App\Models\LeadsKebutuhan;
use App\Models\Loyalty;
use App\Models\Pks;
use App\Models\PksPerjanjian;
use App\Models\PksPerjanjianHistory;
use App\Models\PksWizardStatus;
use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\QuotationDetailCoss;
use App\Models\QuotationDetailHpp;
use App\Models\QuotationMargin;
use App\Models\QuotationPic;
use App\Models\QuotationSite;
use App\Models\RuleThr;
use App\Models\SalaryRule;
use App\Models\SalesActivity;
use App\Models\Site;
use App\Models\Spk;
use App\Models\SpkSite;
use App\Services\PksPerjanjianTemplateService;
use App\Services\PksTemplate\PksTemplateFactory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 * @OA\Tag(
 *     name="PKS",
 *     description="API untuk manajemen PKS (Perjanjian Kerja Sama)"
 * )
 */
class PksController extends Controller
{
    public function __construct(private \App\Services\PksService $pksService)
    {
    }

    /**
     * @OA\Get(
     *     path="/api/pks/list",
     *     summary="Get list of PKS",
     *     description="Mengambil daftar PKS dengan filter tanggal, status, branch, dan pencarian",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="tgl_dari",
     *         in="query",
     *         description="Tanggal mulai filter (format: Y-m-d). Default: 6 bulan kebelakang",
     *         required=false,
     *
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *
     *     @OA\Parameter(
     *         name="tgl_sampai",
     *         in="query",
     *         description="Tanggal akhir filter (format: Y-m-d). Default: hari ini",
     *         required=false,
     *
     *         @OA\Schema(type="string", format="date", example="2025-12-31")
     *     ),
     *
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter berdasarkan status PKS ID",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="branch",
     *         in="query",
     *         description="Filter berdasarkan branch ID dari leads",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Keyword pencarian (jika diisi, filter tanggal akan diabaikan)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="PT ABC")
     *     ),
     *
     *     @OA\Parameter(
     *         name="search_by",
     *         in="query",
     *         description="Kolom yang akan dicari (default: nama_perusahaan)",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"nama_perusahaan", "nomor", "created_by"}, example="nama_perusahaan")
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Jumlah data per halaman (default: 15)",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Nomor halaman (default: 1)",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     * @OA\Parameter(
     *     name="status_berlaku",
     *     in="query",
     *     description="Filter status berlaku kontrak",
     *     required=false,
     *     @OA\Schema(
     *         type="string",
     *         enum={"kontrak_habis", "berakhir_2_bulan", "berakhir_3_bulan", "lebih_3_bulan"}
     *     )
     * ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="PKS data retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nomor", type="string", example="PKS/LEAD001-012024-00001"),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT Example Company"),
     *                     @OA\Property(property="tgl_pks", type="string", format="date", example="2025-01-15"),
     *                     @OA\Property(property="nama_site", type="array", @OA\Items(type="string"), example={"Site A", "Site B"}),
     *                     @OA\Property(property="kontrak_awal", type="string", format="date", example="2025-01-01"),
     *                     @OA\Property(property="kontrak_akhir", type="string", format="date", example="2025-12-31"),
     *                     @OA\Property(property="formatted_kontrak_awal", type="string", example="1 Januari 2025"),
     *                     @OA\Property(property="formatted_kontrak_akhir", type="string", example="31 Desember 2025"),
     *                     @OA\Property(property="status", type="string", example="Aktif"),
     *                     @OA\Property(property="berakhir_dalam", type="string", example="6 bulan"),
     *                     @OA\Property(property="status_berlaku", type="string", example="Berlaku"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-15 10:30:00"),
     *                     @OA\Property(property="created_by", type="string", example="John Doe")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="total", type="integer", example=75),
     *                 @OA\Property(property="per_page", type="integer", example=15)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="tgl_dari", type="string", format="date", example="2025-01-01"),
     *                 @OA\Property(property="tgl_sampai", type="string", format="date", example="2025-12-31")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve PKS list"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tglDari = $request->tgl_dari ?? Carbon::now()->startOfMonth()->subMonths(6)->toDateString();
            $tglSampai = $request->tgl_sampai ?? Carbon::now()->toDateString();

            $query = Pks::select([
                'sl_pks.id',
                'sl_pks.leads_id',      // ✅ WAJIB untuk eager load leads
                'sl_pks.nomor',
                'sl_pks.nama_perusahaan',
                'sl_pks.tgl_pks',
                'sl_pks.kontrak_awal',
                'sl_pks.kontrak_akhir',
                'sl_pks.status_pks_id', // ✅ WAJIB untuk eager load statusPks
                'sl_pks.wizard_status_id',
                'sl_pks.wizard_current_step',
                'sl_pks.wizard_completed_steps',
                'sl_pks.initialized_at',
                'sl_pks.created_at',
                'sl_pks.created_by',
            ])
                ->with([
                    // ✅ Batasi kolom — jangan load semua
                    'statusPks:id,nama',
                    'wizardStatus:id,kode,nama',
                    'sites:id,pks_id,nama_site',
                ])
                // ✅ JOIN leads sekali — dipakai untuk filter branch
                ->leftJoin('sl_leads', 'sl_pks.leads_id', '=', 'sl_leads.id')
                // ✅ Hapus whereNull deleted_at — SoftDeletes sudah handle
                ->orderBy('sl_pks.created_at', 'desc');

            if ($request->filled('search')) {
                $searchTerm = $request->search;
                $searchBy = $request->get('search_by', 'nama_perusahaan');

                if ($searchBy === 'nama_perusahaan') {
                    $searchTerm = str_contains($searchTerm, ' ')
                        ? '"' . $searchTerm . '"'
                        : $searchTerm . '*';
                    $query->whereRaw('MATCH(sl_pks.nama_perusahaan) AGAINST(? IN BOOLEAN MODE)', [$searchTerm]);
                } elseif (in_array($searchBy, ['nomor', 'created_by'])) {
                    $query->where("sl_pks.{$searchBy}", 'LIKE', '%' . $searchTerm . '%');
                }
            } else {
                $query->whereBetween(
                    DB::raw('DATE(COALESCE(sl_pks.tgl_pks, sl_pks.initialized_at, sl_pks.created_at))'),
                    [$tglDari, $tglSampai]
                );
            }

            if ($request->filled('status')) {
                $query->where('sl_pks.status_pks_id', $request->status);
            }

            // ✅ Pakai JOIN bukan whereHas — sudah JOIN di atas
            if ($request->filled('branch')) {
                $query->where('sl_leads.branch_id', $request->branch);
            }
            // Setelah bagian search dan branch filter, tambahkan:

            if ($request->filled('status_berlaku')) {
                $now = Carbon::now()->toDateString();
                $duaBulan = Carbon::now()->addDays(60)->toDateString();
                $tigaBulan = Carbon::now()->addDays(90)->toDateString();

                switch ($request->status_berlaku) {
                    case 'kontrak_habis':
                        $query->whereDate('sl_pks.kontrak_akhir', '<=', $now);
                        break;
                    case 'berakhir_2_bulan':
                        $query->whereDate('sl_pks.kontrak_akhir', '>', $now)
                            ->whereDate('sl_pks.kontrak_akhir', '<=', $duaBulan);
                        break;
                    case 'berakhir_3_bulan':
                        $query->whereDate('sl_pks.kontrak_akhir', '>', $duaBulan)
                            ->whereDate('sl_pks.kontrak_akhir', '<=', $tigaBulan);
                        break;
                    case 'lebih_3_bulan':
                        $query->whereDate('sl_pks.kontrak_akhir', '>', $tigaBulan);
                        break;
                }
            }

            $pksList = $query->paginate($request->get('per_page', 15));

            $pksList->getCollection()->transform(function ($pks) {
                $tglPks = $pks->getRawOriginal('tgl_pks');
                $initializedAt = $pks->getRawOriginal('initialized_at') ?: $pks->getRawOriginal('created_at');

                return [
                    'id' => $pks->id,
                    'nomor' => $pks->nomor,
                    'nama_perusahaan' => $pks->nama_perusahaan,
                    'tgl_pks' => $tglPks
                        ? Carbon::parse($tglPks)->locale('id')->isoFormat('D MMMM Y')
                        : null,
                    'initialized_at' => $initializedAt,
                    'nama_site' => $pks->sites->pluck('nama_site')->toArray(),
                    'kontrak_awal' => $pks->getRawOriginal('kontrak_awal'),
                    'kontrak_akhir' => $pks->getRawOriginal('kontrak_akhir'),
                    'formatted_kontrak_awal' => $pks->getRawOriginal('kontrak_awal')
                        ? Carbon::parse($pks->getRawOriginal('kontrak_awal'))->locale('id')->isoFormat('D MMMM Y')
                        : null,
                    'formatted_kontrak_akhir' => $pks->getRawOriginal('kontrak_akhir')
                        ? Carbon::parse($pks->getRawOriginal('kontrak_akhir'))->locale('id')->isoFormat('D MMMM Y')
                        : null,
                    'status' => $pks->statusPks->nama ?? '-',
                    'wizard_status' => $pks->wizardStatus ? [
                        'id' => $pks->wizardStatus->id,
                        'kode' => $pks->wizardStatus->kode,
                        'nama' => $pks->wizardStatus->nama,
                    ] : null,
                    'wizard_current_step' => $pks->wizard_current_step,
                    'wizard_completed_steps' => $pks->wizard_completed_steps ?? [],
                    'is_wizard_in_progress' => in_array($pks->wizard_status_id, [
                        PksWizardStatus::INITIALIZED,
                        PksWizardStatus::IN_PROGRESS,
                        PksWizardStatus::READY_TO_FINALIZE,
                    ], true),
                    'berakhir_dalam' => $pks->getRawOriginal('kontrak_akhir')
                        ? $this->pksService->hitungBerakhirKontrak($pks->getRawOriginal('kontrak_akhir'))
                        : null,
                    'status_berlaku' => $pks->getRawOriginal('kontrak_akhir')
                        ? $this->pksService->getStatusBerlaku($pks->getRawOriginal('kontrak_akhir'))
                        : null,
                    'created_at' => $pks->getRawOriginal('created_at'),
                    'created_by' => $pks->created_by,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'PKS data retrieved successfully',
                'data' => $pksList->items(),
                'pagination' => [
                    'current_page' => $pksList->currentPage(),
                    'last_page' => $pksList->lastPage(),
                    'total' => $pksList->total(),
                    'total_per_page' => $pksList->count(),
                ],
                'meta' => ['tgl_dari' => $tglDari, 'tgl_sampai' => $tglSampai],
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in PksController@index: ' . $e->getMessage());

            return $this->serverErrorResponse($e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/pks/view/{id}",
     *     summary="Get PKS details with mapped data",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *
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
     *
     *                         @OA\Items(
     *
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
     *
     *                 @OA\Items(type="object")
     *             ),
     *
     *             @OA\Property(
     *                 property="spk_data",
     *                 type="array",
     *                 description="Array of SPK data",
     *
     *                 @OA\Items(type="object")
     *             )
     *         )
     *     ),
     *
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
                'leads.kebutuhan:id,nama',
                'statusPks:id,nama',
                'sites:id,pks_id,nama_site,kota,penempatan,quotation_id,created_at',
                'spk.spkSites:id,spk_id,nama_site,kota,penempatan,quotation_id',
                'perjanjian:id,pks_id,pasal,judul,raw_text,created_by',
                'activities:id,pks_id,tgl_activity,notes,tipe,created_by',
                'ruleThr:id,nama',
            ])->find($id);

            if (!$pks) {
                return $this->notFoundResponse('PKS not found');
            }

            // ──────────────────────────────────────────────
            // LEADS MAPPED
            // ──────────────────────────────────────────────
            $leads_mapped = null;
            if ($pks->leads) {
                $leads = $pks->leads;

                $leads_mapped = [
                    'id' => $leads->id,
                    'nama_perusahaan' => $leads->nama_perusahaan ?? null,
                    'nomor_leads' => $leads->nomor ?? null,
                    'kebutuhan_leads' => $leads->kebutuhan->first()?->nama,
                    'negara' => $leads->negara ?? null,
                    'bidang_perusahaan' => $leads->bidang_perusahaan ?? null,
                    'pma_pmdn' => $leads->pma ?? null,
                    'provinsi' => $leads->provinsi ?? null,
                    'kecamatan' => $leads->kecamatan ?? null,
                    'kelurahan' => $leads->kelurahan ?? null,
                    'alamat' => $leads->alamat ?? null,
                    'pic' => $leads->pic ?? null,
                    'jabatan' => $leads->jabatan ?? null,
                ];
            }

            // ──────────────────────────────────────────────
            // PKS MAPPED
            // ──────────────────────────────────────────────
            $pks_mapped = [
                'id' => $pks->id,
                'nomor' => $pks->nomor ?? null,
                'link_pks_disetujui' => $pks->link_pks_disetujui ?? null,
                'status' => $pks->statusPks?->nama,
                'formatted_kontrak_awal' => Carbon::parse($pks->kontrak_awal)->isoFormat('D MMMM Y'),
                'formatted_kontrak_akhir' => Carbon::parse($pks->kontrak_akhir)->isoFormat('D MMMM Y'),
                'berakhir_dalam' => $this->pksService->hitungBerakhirKontrak($pks->kontrak_akhir),
                'activities' => $pks->activities->map(fn($a) => [
                    'id' => $a->id,
                    'tgl_activity' => $a->tgl_activity,
                    'notes' => $a->notes,
                    'tipe' => $a->tipe,
                    'created_by' => $a->created_by,
                ])->toArray(),
                'perjanjian' => $pks->perjanjian->map(fn($p) => [
                    'id' => $p->id,
                    'pasal' => $p->pasal,
                    'judul' => $p->judul,
                    'raw_text' => $p->raw_text,
                    'created_by' => $p->created_by,
                ])->toArray(),
            ];
            $quotationDataArray = [];
            $spkarray = [];

            if ($pks->sites->isNotEmpty()) {

                // 1 query: ambil distinct quotation_id + spk_id dari sites
                $siteData = Site::where('pks_id', $pks->id)
                    ->whereNotNull('quotation_id')
                    ->whereNull('deleted_at')
                    ->select('quotation_id', 'spk_id')
                    ->distinct()
                    ->get();

                // Kumpulkan semua ID terlebih dahulu
                $quotationIds = $siteData->pluck('quotation_id')->filter()->unique()->values();
                $spkIds = $siteData->pluck('spk_id')->filter()->unique()->values();

                // 1 query: load semua Quotation sekaligus
                $quotations = Quotation::with([
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
                    'managementFee',
                ])
                    ->whereIn('id', $quotationIds)
                    ->get()
                    ->keyBy('id');  // akses O(1) di bawah

                // 1 query: load semua Spk sekaligus
                $spks = Spk::select('id', 'nomor', 'leads_id', 'tgl_spk')
                    ->whereIn('id', $spkIds)
                    ->get()
                    ->keyBy('id');  // akses O(1) di bawah

                // Cache QuotationResource per quotation_id: satu quotation bisa
                // dipakai banyak site (multi-site), dan konstruktor QuotationResource
                // menjalankan calculateQuotation. Tanpa cache, quotation yang sama
                // dihitung ulang tiap site — dedup agar hanya sekali per quotation.
                $resourceCache = [];
                foreach ($siteData as $item) {
                    $qid = $item->quotation_id;
                    if (! array_key_exists($qid, $resourceCache)) {
                        $quotation = $quotations->get($qid);
                        $resourceCache[$qid] = $quotation ? new QuotationResource($quotation) : null;
                    }

                    if ($resourceCache[$qid]) {
                        $quotationDataArray[] = $resourceCache[$qid];
                    }

                    $spk = $spks->get($item->spk_id);
                    if ($spk) {
                        $spkarray[] = $spk;
                    }
                }
            }

            // ──────────────────────────────────────────────
            // SITES INFO
            // ✅ FIX : branch else pakai $pks->sites (koleksi sudah eager-loaded)
            //          bukan $pks->sites() yang menembak query baru
            // ──────────────────────────────────────────────
            $sitesInfo = [];

            if ($pks->spk && $pks->spk->spkSites) {
                $sitesInfo = $pks->spk->spkSites->map(fn($site) => [
                    'id' => $site->id,
                    'nama_site' => $site->nama_site,
                    'kota' => $site->kota,
                    'penempatan' => $site->penempatan,
                    'quotation_id' => $site->quotation_id,
                ])->toArray();
            } else {
                // ✅ $pks->sites = property (koleksi in-memory, 0 query)
                //    $pks->sites() = method (query builder baru, +1 query) — jangan pakai ini
                $sitesInfo = $pks->sites
                    ->sortByDesc('created_at')
                    ->unique('nama_site')
                    ->values()
                    ->map(fn($site) => [
                        'id' => $site->id,
                        'nama_site' => $site->nama_site,
                        'kota' => $site->kota,
                        'penempatan' => $site->penempatan,
                        'quotation_id' => $site->quotation_id,
                    ])->toArray();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'pks_mapped' => $pks_mapped,
                    'leads_mapped' => $leads_mapped,
                ],
                'quotation_data' => $quotationDataArray,
                'spk_data' => $spkarray,
                'sites_info' => $sitesInfo,
            ]);


        } catch (\Exception $e) {
            \Log::error('Failed to retrieve PKS details: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return $this->serverErrorResponse($e->getMessage());
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
     *     path="/api/pks/add/{tipe}",
     *     summary="Create new PKS - Kontrak Baru, Rekontrak, atau Addendum",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="tipe",
     *         in="path",
     *         required=true,
     *         description="Tipe kontrak: baru, rekontrak, atau addendum",
     *
     *         @OA\Schema(
     *             type="string",
     *             enum={"baru", "rekontrak", "addendum"}
     *         )
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"leads_id","tanggal_pks","tanggal_awal_kontrak","tanggal_akhir_kontrak","kategoriHC","loyalty","salary_rule","rule_thr","entitas"},
     *
     *             @OA\Property(property="leads_id", type="integer", example=1, description="Required untuk semua tipe kecuali addendum (untuk addendum gunakan pks_id)"),
     *             @OA\Property(
     *                 property="pks_id",
     *                 type="integer",
     *                 description="Required untuk tipe=addendum. ID PKS induk yang akan di-addendum",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="site_ids",
     *                 type="array",
     *                 description="Required untuk tipe=baru. Array of SpkSite IDs",
     *
     *                 @OA\Items(type="integer"),
     *                 example={1, 2, 3}
     *             ),
     *
     *             @OA\Property(
     *                 property="quotation_site_ids",
     *                 type="array",
     *                 description="Required untuk tipe=rekontrak dan addendum. Array of QuotationSite IDs",
     *
     *                 @OA\Items(type="integer"),
     *                 example={1, 2, 3}
     *             ),
     *
     *             @OA\Property(property="tanggal_pks", type="string", format="date", example="2025-01-15"),
     *             @OA\Property(property="tanggal_awal_kontrak", type="string", format="date", example="2025-02-01"),
     *             @OA\Property(property="tanggal_akhir_kontrak", type="string", format="date", example="2026-01-31"),
     *             @OA\Property(property="kategoriHC", type="integer", example=1),
     *             @OA\Property(property="loyalty", type="integer", example=1),
     *             @OA\Property(property="salary_rule", type="integer", example=1),
     *             @OA\Property(property="rule_thr", type="integer", example=1),
     *             @OA\Property(property="entitas", type="integer", example=1, description="Untuk addendum, entitas akan diambil dari PKS induk")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="PKS created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="PKS created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Invalid tipe parameter"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(PksStoreRequest $request, $tipe): JsonResponse
    {
        // Guard tipe (validasi field ditangani PksStoreRequest secara dinamis per-tipe)
        if (! in_array($tipe, ['baru', 'rekontrak', 'addendum'], true)) {
            return $this->errorResponse('Invalid tipe. Pilihan: baru, rekontrak, addendum', 400);
        }

        // Core logic dalam closure transaction (dikelola service) — exception
        // otomatis rollback & rethrow ke global exception handler (bootstrap/app.php).
        $pks = $this->pksService->createPks($request, $tipe);

        return $this->createdResponse($pks, 'PKS created successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/pks/update/{id}",
     *     summary="Update PKS",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="PKS ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="tanggal_pks", type="string", format="date", example="2025-10-14"),
     *             @OA\Property(property="tanggal_awal_kontrak", type="string", format="date", example="2025-11-01"),
     *             @OA\Property(property="tanggal_akhir_kontrak", type="string", format="date", example="2026-10-31"),
     *             @OA\Property(property="status_pks_id", type="integer", example=2, description="Status PKS ID (1=Draft, 2=Active, 3=Expired, etc.)")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="PKS updated successfully",
     *
     *         @OA\JsonContent(
     *
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
     *
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="PKS tidak ditemukan")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
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
    public function update(PksUpdateRequest $request, $id): JsonResponse
    {
        $pks = Pks::find($id);

        if (!$pks) {
            return $this->notFoundResponse('PKS not found');
        }

        return DB::transaction(function () use ($request, $pks) {
            // Simpan data lama untuk pengecekan
            $oldIsAktif = $pks->is_aktif;
            $oldKontrakAkhir = $pks->kontrak_akhir;

            $pks->update($request->validated());

            // Jika ada perubahan pada is_aktif atau kontrak_akhir, sync customer_active
            if ($oldIsAktif != $pks->is_aktif || $oldKontrakAkhir != $pks->kontrak_akhir) {
                $this->pksService->autoSyncCustomerActiveStatus();
            }

            return $this->successResponse($pks, 'PKS updated successfully');
        });
    }

    /**
     * @OA\Delete(
     *     path="/api/pks/delete/{id}",
     *     summary="Delete PKS",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="PKS deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     )
     * )
     */
    public function destroy($id): JsonResponse
    {
        $pks = Pks::find($id);

        if (!$pks) {
            return $this->notFoundResponse('PKS not found');
        }

        $pks->delete();

        return $this->messageResponse('PKS deleted successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/pks/{id}/approve",
     *     summary="Approve PKS",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"ot"},
     *
     *             @OA\Property(property="ot", type="integer", description="Approval level (1-4)")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="PKS approved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     )
     * )
     */
    public function approve(Request $request, $id): JsonResponse
    {
        $pks = Pks::find($id);

        if (!$pks) {
            return $this->notFoundResponse('PKS not found');
        }

        if ($response = $this->ensureWizardFinalizedOrLegacy($pks)) {
            return $response;
        }

        $this->pksService->approvePks($pks, $request->ot);

        return $this->messageResponse('PKS approved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/pks/{id}/activate",
     *     summary="Activate PKS sites with full synchronization",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="PKS sites activated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     )
     * )
     */
    public function activate(Request $request, $id): JsonResponse
    {
        $current_date_time = Carbon::now()->toDateTimeString();
        $pks = Pks::find($id);

        if (!$pks) {
            return $this->notFoundResponse('PKS not found');
        }

        if ($response = $this->ensureWizardFinalizedOrLegacy($pks)) {
            return $response;
        }

        // Transaksi koneksi default dikelola closure (auto rollback + rethrow ke
        // global handler); koneksi mysqlhris tetap dikelola manual di dalam closure
        // karena DB::transaction() hanya membungkus koneksi default.
        return DB::transaction(function () use ($pks, $current_date_time) {
            DB::connection('mysqlhris')->beginTransaction();

            try {
                // Step 1: Update PKS and Leads Status
                $leads = $this->pksService->updateStatus($pks, $current_date_time);

                // Step 2: Sync Customer to HRIS
                $clientId = $this->pksService->syncCustomerToHris($leads, $current_date_time);

                // Step 3: Process PKS Sites
                $this->pksService->processPksSites($pks, $leads, $clientId, $current_date_time);

                // Step 4: Create Customer Activity Log
                $this->pksService->createCustomerActivityLog($pks, $leads, $current_date_time);

                DB::connection('mysqlhris')->commit();
            } catch (\Throwable $e) {
                DB::connection('mysqlhris')->rollBack();
                \Log::error('Failed to activate PKS sites: ' . $e->getMessage());
                throw $e;
            }

            return $this->messageResponse('PKS sites activated successfully with HRIS synchronization');
        });
    }

    /**
     * @OA\Get(
     *     path="/api/pks/{id}/perjanjian",
     *     summary="Get PKS perjanjian data for template",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", description="Template data for frontend")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     )
     * )
     */
    public function getPerjanjianTemplateData($id): JsonResponse
    {
        try {
            $pks = Pks::with([
                'leads',
                'sites:id,pks_id,nama_site,penempatan,kota',
                'company:id,name,code,nama_direktur,address',
                'kebutuhan:id,nama',
                'ruleThr:id,hari_penagihan_invoice,hari_pembayaran_invoice,hari_rilis_thr',
                'salaryRule:id,cutoff,crosscheck_absen,pengiriman_invoice,perkiraan_invoice_diterima,pembayaran_invoice,rilis_payroll',
            ])->find($id);

            if (!$pks) {
                return $this->notFoundResponse('PKS not found');
            }

            $templateData = $this->pksService->getTemplateData($pks);

            return $this->successResponse($templateData);

        } catch (\Exception $e) {
            return $this->serverErrorResponse($e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/pks/available-leads",
     *     summary="Get available leads for PKS creation",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Keyword pencarian. Jika diisi, filter tanggal tidak dipakai.",
     *         @OA\Schema(type="string", example="PT ABC")
     *     ),
     *     @OA\Parameter(
     *         name="search_by",
     *         in="query",
     *         required=false,
     *         description="Kolom pencarian (default: nama_perusahaan)",
     *         @OA\Schema(type="string", enum={"nama_perusahaan", "nomor", "provinsi", "kota", "created_by"}, example="nama_perusahaan")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Jumlah data per halaman",
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Nomor halaman",
     *         @OA\Schema(type="integer", example=1)
     *     ),
      *
      *     @OA\Response(
      *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *
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
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getAvailableLeads(Request $request): JsonResponse
    {
        try {
            $leads = $this->pksService->getAvailableLeadsData($request);

            return response()->json([
                'success' => true,
                'data' => $leads->items(),
                'pagination' => [
                    'current_page' => $leads->currentPage(),
                    'last_page' => $leads->lastPage(),
                    'total' => $leads->total(),
                    'total_per_page' => $leads->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return $this->serverErrorResponse($e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/pks/available-sites/{leadsId}/{tipe}",
     *     summary="Get available sites for leads",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="leadsId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="tipe",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"baru", "rekontrak","addendum"})
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *
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
     *
     *     @OA\Response(
     *         response=404,
     *         description="Leads not found"
     *     )
     * )
     */
    public function getAvailableSites($leadsId, $tipe): JsonResponse
    {
        try {
            // Panggil fungsi yang sudah disatukan
            $sites = $this->pksService->getAvailableSitesData($leadsId, $tipe);

            return $this->successResponse($sites);
        } catch (\Exception $e) {
            return $this->serverErrorResponse($e->getMessage());
        }
    }
    /**
     * @OA\Post(
     *     path="/api/pks/{id}/submit-checklist",
     *     tags={"PKS"},
     *     summary="Submit quotation checklist",
     *     description="Submits checklist data for quotation including NPWP, invoice, and other administrative details",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Quotation ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Checklist data",
     *
     *         @OA\JsonContent(
     *             required={"npwp", "alamat_npwp", "pic_invoice", "telp_pic_invoice", "email_pic_invoice", "materai", "joker_reliever", "syarat_invoice", "alamat_penagihan_invoice", "status_serikat"},
     *
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
     *
     *                 @OA\Items(
     *                     type="object",
     *                     required={"nama", "jabatan", "no_telp", "email"},
     *
     *                     @OA\Property(property="nama", type="string", example="Jane Doe"),
     *                     @OA\Property(property="jabatan", type="integer", example=1),
     *                     @OA\Property(property="no_telp", type="string", example="081234567890"),
     *                     @OA\Property(property="email", type="string", example="jane@example.com")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Checklist submitted successfully",
     *
     *         @OA\JsonContent(
     *
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
     *
     *     @OA\Response(
     *         response=404,
     *         description="Quotation not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Quotation not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to submit checklist"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    // public function submitChecklist(Request $request, string $id): JsonResponse
    // {
    //     DB::beginTransaction();
    //     try {
    //         $user = Auth::user();
    //         $current_date_time = Carbon::now()->toDateTimeString();

    //         // Validasi input
    //         $validator = Validator::make($request->all(), [
    //             'npwp' => 'required|string|max:50',
    //             'alamat_npwp' => 'required|string|max:255',
    //             'materai' => 'required|string|max:50',
    //             'joker_reliever' => 'required|string|max:50',
    //             'syarat_invoice' => 'required|string|max:255',
    //             'alamat_penagihan_invoice' => 'required|string|max:255',
    //             'catatan_site' => 'nullable|string',
    //             'ada_serikat' => 'nullable|string',
    //             // Validasi untuk PICs
    //             'pics' => 'nullable|array',
    //             'pics.*.nama' => 'required|string|max:100',
    //             'pics.*.jabatan' => 'required|integer|exists:m_jabatan_pic,id',
    //             'pics.*.no_telp' => 'required|string|max:20',
    //             'pics.*.email' => 'required|email|max:100'
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => $validator->errors()
    //             ], 422);
    //         }

    //         // Cari quotation
    //         $quotation = Quotation::notDeleted()->findOrFail($id);

    //         // Logika untuk status serikat
    //         $statusSerikat = $request->status_serikat;
    //         if ($request->ada_serikat === "Tidak Ada") {
    //             $statusSerikat = "Tidak Ada";
    //         }

    //         // Update quotation data
    //         $quotation->update([
    //             'npwp' => $request->npwp,
    //             'alamat_npwp' => $request->alamat_npwp,
    //             'pic_invoice' => $request->pic_invoice,
    //             'telp_pic_invoice' => $request->telp_pic_invoice,
    //             'email_pic_invoice' => $request->email_pic_invoice,
    //             'materai' => $request->materai,
    //             'joker_reliever' => $request->joker_reliever,
    //             'syarat_invoice' => $request->syarat_invoice,
    //             'alamat_penagihan_invoice' => $request->alamat_penagihan_invoice,
    //             'catatan_site' => $request->catatan_site,
    //             'status_serikat' => $statusSerikat,
    //             'updated_at' => $current_date_time,
    //             'updated_by' => $user->full_name
    //         ]);

    //         // Tambah PICs jika ada
    //         $picsAdded = 0;
    //         if ($request->has('pics') && is_array($request->pics)) {
    //             foreach ($request->pics as $picData) {
    //                 $this->addDetailPic($quotation, $picData, $current_date_time);
    //                 $picsAdded++;
    //             }
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Checklist submitted successfully',
    //             'data' => [
    //                 'id' => $quotation->id,
    //                 'npwp' => $quotation->npwp,
    //                 'pic_invoice' => $quotation->pic_invoice,
    //                 'pics_added' => $picsAdded
    //             ]
    //         ]);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         \Log::error('Failed to submit checklist: ' . $e->getMessage());

    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    /**
     * @OA\Post(
     *     path="/api/pks/upload/{id}",
     *     summary="Upload dokumen PKS yang sudah disetujui",
     *     description="Endpoint untuk mengupload file PKS yang sudah disetujui dan mengubah status PKS menjadi approved.",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID PKS",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="File PKS (pdf, doc, docx, jpg, jpeg, png) maksimal 10MB"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="File berhasil diupload",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="PKS file uploaded successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nomor", type="string", example="PKS/COMP001/LEAD001-012024-00001"),
     *                 @OA\Property(property="status_pks_id", type="integer", example=7),
     *                 @OA\Property(property="link_pks_disetujui", type="string", example="http://example.com/document/pks/file.pdf")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="File tidak valid",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="PKS tidak ditemukan",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="PKS not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error server",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error uploading PKS file")
     *         )
     *     )
     * )
     */
    public function uploadPks(UploadPksRequest $request, $id): JsonResponse
    {
        $pks = Pks::with('leads:id,nomor,kebutuhan_id,branch_id')->find($id);

        if (!$pks) {
            return $this->notFoundResponse('PKS not found');
        }

        if ($response = $this->ensureWizardFinalizedOrLegacy($pks)) {
            return $response;
        }

        return DB::transaction(function () use ($request, $pks) {
            $fileName = null;

            try {
                // Hapus file lama jika ada
                if ($pks->link_pks_disetujui) {
                    $oldFileName = basename($pks->link_pks_disetujui);
                    if (Storage::disk('pks')->exists($oldFileName)) {
                        Storage::disk('pks')->delete($oldFileName);
                    }
                }

                // Upload file baru
                $fileName = $this->pksService->storePksFile($request->file('file'));

                // Generate URL yang benar
                $fileUrl = url('document/pks/' . $fileName);

                \Log::info('Generated URL: ' . $fileUrl);
                \Log::info('Filename: ' . $fileName);
                \Log::info('File path: ' . Storage::disk('pks')->path($fileName));
                \Log::info('File exists: ' . (Storage::disk('pks')->exists($fileName) ? 'Yes' : 'No'));

                $pks->update([
                    'status_pks_id' => 6, // Status Approved/Active
                    'link_pks_disetujui' => $fileUrl,
                    'updated_at' => now(),
                    'updated_by' => Auth::user()->full_name,
                ]);
                $leads = $pks->leads; // pastikan relasi leads sudah di-load
                if (!$leads) {
                    $leads = Leads::find($pks->leads_id);
                }

                // Catat aktivitas
                $this->pksService->createUploadPksActivity($pks, $leads);

                $pks->load(['statusPks']);

                return $this->successResponse([
                    'id' => $pks->id,
                    'nomor' => $pks->nomor,
                    'status_pks_id' => $pks->status_pks_id,
                    'status' => $pks->statusPks->nama ?? null,
                    'link_pks_disetujui' => $pks->link_pks_disetujui,
                ], 'PKS file uploaded successfully');
            } catch (\Throwable $e) {
                // Bersihkan file yang sudah terlanjur diupload sebelum rethrow
                // (DB rollback ditangani oleh DB::transaction).
                if ($fileName && Storage::disk('pks')->exists($fileName)) {
                    Storage::disk('pks')->delete($fileName);
                }
                throw $e;
            }
        });
    }
    /**
     * @OA\Put(
     *     path="/api/pks/perjanjian/{id}",
     *     summary="Update perjanjian PKS (menyimpan history versi lama)",
     *     description="Memperbarui konten perjanjian PKS. Sebelum update, data lama akan disimpan ke tabel history untuk keperluan komparasi.",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID perjanjian (sl_pks_perjanjian.id)",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"raw_text"},
     *             @OA\Property(property="judul", type="string", example="Ruang Lingkup Pekerjaan (Revisi)"),
     *             @OA\Property(property="raw_text", type="string", example="<p>Isi kontrak yang sudah diperbarui...</p>")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Perjanjian berhasil diperbarui",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Perjanjian berhasil diperbarui"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=10),
     *                 @OA\Property(property="pasal", type="string", example="Pasal 1"),
     *                 @OA\Property(property="judul", type="string", example="Ruang Lingkup Pekerjaan (Revisi)"),
     *                 @OA\Property(property="raw_text", type="string", example="<p>Isi kontrak yang sudah diperbarui...</p>"),
     *                 @OA\Property(property="created_by", type="string", example="Admin"),
     *                 @OA\Property(property="updated_by", type="string", example="John Doe")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Perjanjian tidak ditemukan"),
     *     @OA\Response(response=422, description="Validasi error")
     * )
     */
    public function updatePerjanjian(UpdatePerjanjianRequest $request, $id)
    {
        $perjanjian = PksPerjanjian::findOrFail($id);

        $judulBaru = $request->judul ?? $perjanjian->judul;
        $rawTextBaru = $request->raw_text;

        // Cek apakah ada perubahan
        if ($perjanjian->raw_text === $rawTextBaru && $perjanjian->judul === $judulBaru) {
            return $this->successResponse($perjanjian, 'Tidak ada perubahan');
        }

        return DB::transaction(function () use ($perjanjian, $judulBaru, $rawTextBaru) {
            // Simpan data LAMA ke history
            PksPerjanjianHistory::create([
                'pks_perjanjian_id' => $perjanjian->id,
                'pks_id' => $perjanjian->pks_id,
                'pasal' => $perjanjian->pasal,
                'judul' => $perjanjian->judul,
                'raw_text' => $perjanjian->raw_text,
                'snapshot' => json_encode($perjanjian->toArray(), JSON_PRETTY_PRINT),
                'changed_by' => Auth::user()->full_name,
            ]);

            // Update data utama
            $perjanjian->update([
                'judul' => $judulBaru,
                'raw_text' => $rawTextBaru,
                'updated_by' => Auth::user()->full_name,
            ]);

            $pks = Pks::with('leads')->find($perjanjian->pks_id);
            if ($pks && $pks->leads) {
                $this->pksService->logPerjanjianChange($perjanjian, $pks->leads);
            }

            return $this->successResponse($perjanjian, 'Perjanjian berhasil diperbarui');
        });
    }
    /**
     * @OA\Get(
     *     path="/api/pks/perjanjian/{id}/history",
     *     summary="Daftar riwayat perubahan perjanjian",
     *     description="Mengembalikan daftar history perubahan untuk suatu perjanjian (berdasarkan ID perjanjian, bukan ID history).",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID perjanjian (sl_pks_perjanjian.id)",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List riwayat perubahan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=101),
     *                     @OA\Property(property="judul", type="string", example="RUANG LINGKUP PEKERJAAN"),
     *                     @OA\Property(property="changed_by", type="string", example="John Doe"),
     *                     @OA\Property(property="waktu", type="string", example="20-05-2026 14:30:00")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Perjanjian tidak ditemukan")
     * )
     */
    public function getPerjanjianHistory($id)
    {
        $perjanjian = PksPerjanjian::find($id);
        if (!$perjanjian) {
            return $this->notFoundResponse('Perjanjian not found');
        }

        $history = PksPerjanjianHistory::where('pks_perjanjian_id', $id)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'judul', 'raw_text', 'changed_by', 'created_at']);

        return $this->successResponse($history->map(fn($h) => [
            'id' => $h->id,
            'judul' => $h->judul,
            'changed_by' => $h->changed_by,
            'waktu' => $h->created_at->format('d-m-Y H:i:s'),
        ]));
    }
    /**
     * @OA\Post(
     *     path="/api/pks/perjanjian/compare",
     *     summary="Bandingkan perjanjian (versi terbaru vs history terbaru atau history tertentu)",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"pks_perjanjian_id"},
     *             @OA\Property(property="pks_perjanjian_id", type="integer", example=10),
     *             @OA\Property(property="history_id", type="integer", description="Opsional, ID history yang akan dibandingkan dengan versi terbaru. Jika kosong, ambil history terbaru.")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Hasil komparasi")
     * )
     */
    public function comparePerjanjian(ComparePerjanjianRequest $request)
    {
        $perjanjian = PksPerjanjian::find($request->pks_perjanjian_id);
        $newText = $perjanjian->raw_text;
        $newJudul = $perjanjian->judul;
        $labelNew = "Saat ini (terbaru)";

        if ($request->filled('history_id')) {
            $history = PksPerjanjianHistory::find($request->history_id);
            if (!$history) {
                return $this->notFoundResponse('History tidak ditemukan');
            }
            $oldText = $history->raw_text;
            $oldJudul = $history->judul;
            $labelOld = "Versi " . $history->created_at->format('d-m-Y H:i');
        } else {
            $latestHistory = PksPerjanjianHistory::where('pks_perjanjian_id', $perjanjian->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$latestHistory) {
                return $this->notFoundResponse('Tidak ada riwayat perubahan untuk perjanjian ini.');
            }

            $oldText = $latestHistory->raw_text;
            $oldJudul = $latestHistory->judul;
            $labelOld = "Sebelum edit (" . $latestHistory->created_at->format('d-m-Y H:i') . ")";
        }

        $judulChanged = ($oldJudul !== $newJudul);

        $diff = null;
        if ($oldText !== $newText) {
            try {
                $oldLines = preg_split('/\r\n|\r|\n/', $oldText);
                $newLines = preg_split('/\r\n|\r|\n/', $newText);
                $outputBuilder = new UnifiedDiffOutputBuilder("--- Original\n+++ New\n");
                $differ = new Differ($outputBuilder);
                $diff = $differ->diff($oldLines, $newLines);
            } catch (\Exception $e) {
                $diff = null;
            }
        }

        return $this->successResponse([
            'version_label_old' => $labelOld,
            'version_label_new' => $labelNew,
            'judul_old' => $oldJudul,
            'judul_new' => $newJudul,
            'judul_changed' => $judulChanged,
            'diff_unified' => $diff,
            'old_text' => $oldText,
            'new_text' => $newText,
        ]);
    }
    /**
     * @OA\Post(
     *     path="/api/pks/{pks_id}/perjanjian",
     *     summary="Tambah pasal baru ke perjanjian PKS",
     *     description="Menambahkan pasal baru ke dalam perjanjian suatu PKS. Nomor pasal harus unik dalam satu PKS.",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="pks_id",
     *         in="path",
     *         required=true,
     *         description="ID PKS",
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"pasal","judul","raw_text"},
     *             @OA\Property(property="pasal",    type="string", example="Pasal 12",           description="Nomor/label pasal, unik per PKS"),
     *             @OA\Property(property="judul",    type="string", example="KETENTUAN LAIN-LAIN", description="Judul pasal"),
     *             @OA\Property(property="raw_text", type="string", example="Hal-hal yang tidak diatur...", description="Isi lengkap pasal")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Pasal berhasil ditambahkan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Pasal berhasil ditambahkan"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id",         type="integer", example=42),
     *                 @OA\Property(property="pks_id",     type="integer", example=5),
     *                 @OA\Property(property="pasal",      type="string",  example="Pasal 12"),
     *                 @OA\Property(property="judul",      type="string",  example="KETENTUAN LAIN-LAIN"),
     *                 @OA\Property(property="raw_text",   type="string",  example="Hal-hal yang tidak diatur..."),
     *                 @OA\Property(property="created_by", type="string",  example="John Doe")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="PKS tidak ditemukan"),
     *     @OA\Response(response=422, description="Validasi gagal atau pasal sudah ada")
     * )
     */
    public function storePasal(StorePasalRequest $request, $pks_id): JsonResponse
    {
        // ✅ 2 query: PKS + leads sekaligus (eager load), tidak ada query susulan untuk leads
        $pks = Pks::with('leads:id,nomor,kebutuhan_id,branch_id')->find($pks_id);
        if (!$pks) {
            return $this->notFoundResponse('PKS tidak ditemukan');
        }

        // ✅ 1 query: cek duplikasi pasal dalam PKS yang sama
        if (
            PksPerjanjian::where('pks_id', $pks_id)
                ->where('pasal', $request->pasal)
                ->exists()
        ) {
            return $this->errorResponse("Pasal '{$request->pasal}' sudah ada dalam perjanjian PKS ini.", 422);
        }

        return DB::transaction(function () use ($request, $pks) {
            // ✅ 1 query: insert pasal baru
            $perjanjian = PksPerjanjian::create([
                'pks_id' => $pks->id,
                'pasal' => $request->pasal,
                'judul' => $request->judul,
                'raw_text' => $request->raw_text,
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id(),
                'updated_by' => Auth::user()->full_name,
            ]);

            // ✅ Activity log — leads sudah ter-load di atas, tidak ada query tambahan untuk leads
            if ($pks->leads) {
                $nomorActivity = $this->pksService->generateNomorActivity($pks->leads);
                CustomerActivity::create([
                    'leads_id' => $pks->leads->id,
                    'pks_id' => $pks->id,
                    'branch_id' => $pks->leads->branch_id,
                    'tgl_activity' => now(),
                    'nomor' => $nomorActivity,
                    'tipe' => 'PKS_PERJANJIAN',
                    'notes' => "Pasal {$perjanjian->pasal} - {$perjanjian->judul} ditambahkan oleh " . Auth::user()->full_name,
                    'is_activity' => 0,
                    'user_id' => Auth::id(),
                    'created_by' => Auth::user()->full_name,
                    'created_by_user_id' => Auth::id(),
                ]);
            }

            return $this->createdResponse([
                'id' => $perjanjian->id,
                'pks_id' => $perjanjian->pks_id,
                'pasal' => $perjanjian->pasal,
                'judul' => $perjanjian->judul,
                'raw_text' => $perjanjian->raw_text,
                'created_by' => $perjanjian->created_by,
            ], 'Pasal berhasil ditambahkan');
        });
    }

    /**
     * @OA\Delete(
     *     path="/api/pks/perjanjian/{id}",
     *     summary="Hapus pasal dari perjanjian PKS",
     *     description="Menghapus (soft-delete) satu pasal dari perjanjian PKS berdasarkan ID pasal.",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID pasal (sl_pks_perjanjian.id)",
     *         @OA\Schema(type="integer", example=42)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Pasal berhasil dihapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string",  example="Pasal berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Pasal tidak ditemukan")
     * )
     */
    public function destroyPasal($id): JsonResponse
    {
        // ✅ 1 query: ambil pasal — kolom minimal yang dibutuhkan
        $perjanjian = PksPerjanjian::select('id', 'pks_id', 'pasal', 'judul')->find($id);
        if (!$perjanjian) {
            return $this->notFoundResponse('Pasal tidak ditemukan');
        }

        // ✅ 1 query: PKS + leads sekaligus, tidak ada query susulan untuk leads
        $pks = Pks::with('leads:id,nomor,kebutuhan_id,branch_id')
            ->find($perjanjian->pks_id);

        return DB::transaction(function () use ($perjanjian, $pks) {
            // ✅ 1 query: soft-delete (sets deleted_at, SoftDeletes trait)
            $perjanjian->delete();

            // ✅ Activity log — leads sudah ter-load di atas
            if ($pks && $pks->leads) {
                $nomorActivity = $this->pksService->generateNomorActivity($pks->leads);
                CustomerActivity::create([
                    'leads_id' => $pks->leads->id,
                    'pks_id' => $pks->id,
                    'branch_id' => $pks->leads->branch_id,
                    'tgl_activity' => now(),
                    'nomor' => $nomorActivity,
                    'tipe' => 'PKS_PERJANJIAN',
                    'notes' => "Pasal {$perjanjian->pasal} - {$perjanjian->judul} dihapus oleh " . Auth::user()->full_name,
                    'is_activity' => 0,
                    'user_id' => Auth::id(),
                    'created_by' => Auth::user()->full_name,
                    'created_by_user_id' => Auth::id(),
                ]);
            }

            return $this->messageResponse('Pasal berhasil dihapus');
        });
    }

    private function ensureWizardFinalizedOrLegacy(Pks $pks): ?JsonResponse
    {
        if ($pks->wizard_status_id === null) {
            return null;
        }

        if ((int) $pks->wizard_status_id !== PksWizardStatus::FINALIZED) {
            return $this->errorResponse('PKS wizard belum finalized', 422);
        }

        return null;
    }
}
