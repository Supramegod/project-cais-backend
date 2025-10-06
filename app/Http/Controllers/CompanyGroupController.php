<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PerusahaanGroup;
use App\Models\PerusahaanGroupDetail;
use App\Models\Leads;
use App\Models\JenisPerusahaan;
use App\Models\StatusLeads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
/**
 * @OA\Tag(
 *     name="Company Group",
 *     description="API untuk manajemen data group perusahaan"
 * )
 */
class CompanyGroupController extends Controller
{
    // --- PRIVATE HELPER METHOD ---
    /**
     * Helper untuk memformat objek Leads Eloquent menjadi array respons JSON.
     * @param Leads $lead
     * @return array
     */
    private function mapLeadToResponse($lead)
    {
        return [
            'id' => $lead->id,
            'nama_perusahaan' => $lead->nama_perusahaan,
            'kota' => $lead->kota,
            'pic' => $lead->pic,
            'no_telp' => $lead->no_telp,
            'email' => $lead->email,
            // Menggunakan optional() untuk menghindari error jika relasi null
            'jenis_perusahaan' => optional($lead->jenisPerusahaan)->nama,
            'status_leads' => optional($lead->statusLeads)->nama,
            'warna_background' => optional($lead->statusLeads)->warna_background,
            'warna_font' => optional($lead->statusLeads)->warna_font,
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/company-group/list",
     *     summary="Get list of company groups with pagination and search",
     *     tags={"Company Group"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data grup perusahaan berhasil diambil"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama_grup", type="string", example="Grup Perusahaan A"),
     *                         @OA\Property(property="jumlah_perusahaan", type="integer", example=5),
     *                         @OA\Property(property="created_by", type="string", example="Admin User"),
     *                         @OA\Property(property="created_at", type="string", format="date-time")
     *                     )
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=100)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function list(Request $request)
    {
        try {
            $search = $request->input('search');

            $query = PerusahaanGroup::select('id', 'nama_grup', 'jumlah_perusahaan', 'created_by', 'created_at')
                ->orderBy('created_at', 'desc');

            if ($search) {
                // Menggunakan scope 'search' dari Model PerusahaanGroup
                $query->search($search);
            }

            // Menggunakan get() instead of paginate() untuk mengambil semua data
            $groups = $query->get();

            return response()->json([
                'success' => true,
                'message' => 'Data grup perusahaan berhasil diambil',
                'data' => $groups,
                'total' => $groups->count() // Tambahkan total count untuk informasi
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/company-group/view/{id}",
     *     summary="Get detail of a company group",
     *     tags={"Company Group"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Company group ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Detail grup berhasil diambil"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="group", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_grup", type="string", example="Grup Perusahaan A"),
     *                     @OA\Property(property="jumlah_perusahaan", type="integer", example=5),
     *                     @OA\Property(property="created_by", type="string", example="Admin User"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 ),
     *                 @OA\Property(property="total_perusahaan", type="integer", example=5),
     *                 @OA\Property(property="perusahaan", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama_perusahaan", type="string", example="PT Contoh"),
     *                         @OA\Property(property="kota", type="string", example="Jakarta"),
     *                         @OA\Property(property="pic", type="string", example="John Doe"),
     *                         @OA\Property(property="no_telp", type="string", example="08123456789"),
     *                         @OA\Property(property="email", type="string", example="email@example.com"),
     *                         @OA\Property(property="jenis_perusahaan", type="string", example="Retail"),
     *                         @OA\Property(property="status_leads", type="string", example="Hot Lead"),
     *                         @OA\Property(property="warna_background", type="string", example="#FF0000"),
     *                         @OA\Property(property="warna_font", type="string", example="#FFFFFF")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Group not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grup tidak ditemukan")
     *         )
     *     )
     * )
     */

    public function view($id)
    {
        try {
            // Menggunakan Eloquent
            $group = PerusahaanGroup::find($id);

            if (!$group) {
                return response()->json(['success' => false, 'message' => 'Grup tidak ditemukan'], 404);
            }

            // Mengambil detail perusahaan menggunakan relasi dan Eager Loading
            $perusahaanDetails = PerusahaanGroupDetail::with([
                'lead' => function ($query) {
                    $query->select(
                        'id',
                        'nama_perusahaan',
                        'kota',
                        'pic',
                        'no_telp',
                        'email',
                        'jenis_perusahaan_id',
                        'status_leads_id'
                    )->whereNull('deleted_at');
                },
                'lead.jenisPerusahaan:id,nama',
                'lead.statusLeads:id,nama,warna_background,warna_font'
            ])
                ->where('group_id', $id)
                ->get();

            // Mapping menggunakan helper method
            $perusahaan = $perusahaanDetails->map(function ($detail) {
                if ($lead = $detail->lead) {
                    return $this->mapLeadToResponse($lead);
                }
                return null;
            })->filter()->sortBy('nama_perusahaan')->values();


            return response()->json([
                'success' => true,
                'message' => 'Detail grup berhasil diambil',
                'data' => [
                    'group' => $group,
                    'total_perusahaan' => $perusahaan->count(),
                    'perusahaan' => $perusahaan
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/company-group/add",
     *     summary="Create new company group",
     *     tags={"Company Group"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama_grup"},
     *             @OA\Property(property="nama_grup", type="string", example="Grup Perusahaan Baru")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grup 'Grup Perusahaan Baru' berhasil dibuat"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama_grup", type="string", example="Grup Perusahaan Baru"),
     *                 @OA\Property(property="jumlah_perusahaan", type="integer", example=0),
     *                 @OA\Property(property="created_by", type="string", example="Admin User"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validasi gagal"),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="nama_grup", type="array",
     *                     @OA\Items(type="string", example="Nama grup sudah digunakan")
     *                 )
     *             )
     *         )
     *     )
     * )
     */

    public function add(Request $request)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'nama_grup' => 'required|max:100|min:3|unique:sl_perusahaan_groups,nama_grup',
            ], [
                'min' => 'Masukkan :attribute minimal :min karakter',
                'max' => 'Masukkan :attribute maksimal :max karakter',
                'required' => ':attribute harus diisi',
                'unique' => 'Nama grup sudah digunakan',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 400);
            }

            $current_date_time = Carbon::now()->toDateTimeString();
            $userFullName = Auth::user()->full_name ?? 'System';

            // CREATE NEW GROUP MENGGUNAKAN MODEL
            $newGroup = PerusahaanGroup::create([
                'nama_grup' => $request->nama_grup,
                'jumlah_perusahaan' => 0,
                'created_at' => $current_date_time,
                'created_by' => $userFullName,
                'update_at' => $current_date_time,
                'update_by' => $userFullName
            ]);

            $msgSave = 'Grup "' . $request->nama_grup . '" berhasil dibuat';
            $responseData = $newGroup;

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $msgSave,
                'data' => $responseData
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/company-group/update/{id}",
     *     summary="Update company group",
     *     tags={"Company Group"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Company group ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama_grup"},
     *             @OA\Property(property="nama_grup", type="string", example="Grup Perusahaan Updated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grup 'Grup Perusahaan Updated' berhasil diperbarui"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama_grup", type="string", example="Grup Perusahaan Updated"),
     *                 @OA\Property(property="jumlah_perusahaan", type="integer", example=5),
     *                 @OA\Property(property="created_by", type="string", example="Admin User"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            // 1. Validasi, mengabaikan ID grup yang sedang di-update
            $validator = Validator::make($request->all(), [
                'nama_grup' => 'required|max:100|min:3|unique:sl_perusahaan_groups,nama_grup,' . $id,
            ], [
                'min' => 'Masukkan :attribute minimal :min karakter',
                'max' => 'Masukkan :attribute maksimal :max karakter',
                'required' => ':attribute harus diisi',
                'unique' => 'Nama grup sudah digunakan',
            ]);

            if ($validator->fails()) {
                DB::rollback();
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 400);
            }

            // 2. Cari Grup
            $group = PerusahaanGroup::find($id);

            if (!$group) {
                DB::rollback();
                return response()->json([
                    'success' => false,
                    'message' => 'Grup tidak ditemukan'
                ], 404);
            }

            // 3. Update data
            $current_date_time = Carbon::now()->toDateTimeString();
            $userFullName = Auth::user()->full_name ?? 'System';

            $group->update([
                'nama_grup' => $request->nama_grup,
                'update_at' => $current_date_time,
                'update_by' => $userFullName
            ]);

            $updatedGroup = $group->refresh();
            $msgSave = 'Grup "' . $request->nama_grup . '" berhasil diperbarui';
            $responseData = $updatedGroup;

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $msgSave,
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * @OA\Get(
     *     path="/api/company-group/get-available-companies/{groupId}",
     *     summary="dipakai untuk mengambil daftar perusahaan (leads) yang tersedia untuk dimasukkan ke dalam sebuah grup tertentu.",
     *     tags={"Company Group"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="groupId",
     *         in="path",
     *         required=true,
     *         description="Group ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="keyword",
     *         in="query",
     *         description="Search keyword",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Parameter(
     *         name="get_all",
     *         in="query",
     *         description="Get all data without pagination",
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data perusahaan tersedia berhasil diambil"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT Contoh"),
     *                     @OA\Property(property="kota", type="string", example="Jakarta"),
     *                     @OA\Property(property="pic", type="string", example="John Doe"),
     *                     @OA\Property(property="no_telp", type="string", example="08123456789"),
     *                     @OA\Property(property="email", type="string", example="email@example.com"),
     *                     @OA\Property(property="jenis_perusahaan", type="string", example="Retail"),
     *                     @OA\Property(property="status_leads", type="string", example="Hot Lead"),
     *                     @OA\Property(property="warna_background", type="string", example="#FF0000"),
     *                     @OA\Property(property="warna_font", type="string", example="#FFFFFF")
     *                 )
     *             ),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=10)
     *             )
     *         )
     *     )
     * )
     */
    public function getAvailableCompanies(Request $request, $groupId)
    {
        try {
            $keyword = trim($request->input('keyword', ''));
            $perPage = (int) $request->input('per_page', 10);
            $getAll = filter_var($request->input('get_all', false), FILTER_VALIDATE_BOOLEAN);

            // 1. Cek apakah grup ada (menggunakan Eloquent)
            $group = PerusahaanGroup::find($groupId);
            if (!$group) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grup tidak ditemukan'
                ], 404);
            }

            // 2. Query Leads dengan Eager Loading
            $query = Leads::query()
                ->select(
                    'id',
                    'nama_perusahaan',
                    'kota',
                    'pic',
                    'no_telp',
                    'email',
                    'jenis_perusahaan_id',
                    'status_leads_id'
                )
                ->with([
                    'jenisPerusahaan:id,nama',
                    'statusLeads:id,nama,warna_background,warna_font'
                ])
                // 3. Filter Leads yang BELUM ada di grup manapun (whereDoesntHave)
                ->whereDoesntHave('groupDetails')
                ->whereNull('deleted_at');

            // 4. Apply keyword filter
            if (!empty($keyword) && strlen($keyword) >= 2) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('nama_perusahaan', 'like', "%{$keyword}%")
                        ->orWhere('kota', 'like', "%{$keyword}%");
                });
            }

            $query->orderBy('nama_perusahaan');

            if ($getAll) {
                // Get all data without pagination
                $companies = $query->get()
                    ->map(fn($lead) => $this->mapLeadToResponse($lead)); // Map data

                return response()->json([
                    'success' => true,
                    'message' => 'Data perusahaan tersedia berhasil diambil',
                    'data' => $companies,
                    'total' => $companies->count()
                ]);
            } else {
                // 5. Get paginated data (menggunakan Eloquent pagination)
                $companiesPaginated = $query->paginate($perPage);

                // Mapping results ke format output yang diinginkan
                $mappedData = $companiesPaginated->getCollection()->map(fn($lead) => $this->mapLeadToResponse($lead));

                // Siapkan struktur pagination
                $pagination = [
                    'current_page' => $companiesPaginated->currentPage(),
                    'last_page' => $companiesPaginated->lastPage(),
                    'per_page' => $companiesPaginated->perPage(),
                    'total' => $companiesPaginated->total(),
                    'from' => $companiesPaginated->firstItem() ?? 0,
                    'to' => $companiesPaginated->lastItem() ?? 0
                ];

                return response()->json([
                    'success' => true,
                    'message' => 'Data perusahaan tersedia berhasil diambil',
                    'data' => $mappedData,
                    'pagination' => $pagination,
                    'total' => $companiesPaginated->total()
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/company-group/get-companies-in-group/{groupId}",
     *     summary="Get all companies in a specific group(list grub)",
     *     description="Retrieve all companies that are members of a specific company group",
     *     tags={"Company Group"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="groupId",
     *         in="path",
     *         required=true,
     *         description="ID of the company group",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data perusahaan dalam grup berhasil diambil"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT Contoh Indonesia"),
     *                     @OA\Property(property="kota", type="string", example="Jakarta"),
     *                     @OA\Property(property="pic", type="string", example="John Doe"),
     *                     @OA\Property(property="no_telp", type="string", example="08123456789"),
     *                     @OA\Property(property="email", type="string", example="john@contoh.com"),
     *                     @OA\Property(property="jenis_perusahaan", type="string", example="Retail"),
     *                     @OA\Property(property="status_leads", type="string", example="Hot Lead"),
     *                     @OA\Property(property="warna_background", type="string", example="#FF0000"),
     *                     @OA\Property(property="warna_font", type="string", example="#FFFFFF")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function getCompaniesInGroup($groupId)
    {
        try {
            // Menggunakan relasi dari PerusahaanGroupDetail
            $perusahaanDetails = PerusahaanGroupDetail::with([
                'lead' => function ($query) {
                    $query->select(
                        'id',
                        'nama_perusahaan',
                        'kota',
                        'pic',
                        'no_telp',
                        'email',
                        'jenis_perusahaan_id',
                        'status_leads_id'
                    )->whereNull('deleted_at');
                },
                'lead.jenisPerusahaan:id,nama',
                'lead.statusLeads:id,nama,warna_background,warna_font'
            ])
                ->where('group_id', $groupId)
                ->get();

            // Format data menggunakan helper method
            $perusahaan = $perusahaanDetails->map(function ($detail) {
                if ($lead = $detail->lead) {
                    return $this->mapLeadToResponse($lead);
                }
                return null;
            })->filter()->sortBy('nama_perusahaan')->values();


            return response()->json([
                'success' => true,
                'message' => 'Data perusahaan dalam grup berhasil diambil',
                'data' => $perusahaan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/company-group/bulk-assign",
     *     summary="Bulk assign companies to groups(group lama tambh anggota)",
     *     tags={"Company Group"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"assignments"},
     *             @OA\Property(property="assignments", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="group_id", type="integer", example=1),
     *                     @OA\Property(property="leads_ids", type="array",
     *                         @OA\Items(type="integer", example=1)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Berhasil memproses 5 perusahaan, 2 dilewati karena sudah ada di grup"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="processed", type="integer", example=5),
     *                 @OA\Property(property="skipped", type="integer", example=2)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid assignments data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Data assignments tidak valid")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */


    public function bulkAssign(Request $request)
    {
        try {
            DB::beginTransaction();

            $assignments = $request->input('assignments', []);

            if (empty($assignments) || !is_array($assignments)) {
                return response()->json(['success' => false, 'message' => 'Data assignments tidak valid'], 400);
            }

            $currentUser = Auth::user()->full_name ?? 'System';
            $now = Carbon::now();
            $totalProcessed = 0;
            $totalSkipped = 0;

            foreach ($assignments as $assignment) {
                $groupId = $assignment['group_id'] ?? null;
                $leadsIds = $assignment['leads_ids'] ?? [];

                if (empty($groupId) || empty($leadsIds)) {
                    continue;
                }

                $existingIds = PerusahaanGroupDetail::where('group_id', $groupId)
                    ->whereIn('leads_id', $leadsIds)
                    ->pluck('leads_id')
                    ->toArray();

                $newCompanies = array_diff($leadsIds, $existingIds);
                $totalSkipped += count($existingIds);

                if (empty($newCompanies)) {
                    continue;
                }

                $validCompanies = Leads::whereIn('id', $newCompanies)
                    ->whereNull('deleted_at')
                    ->select('id', 'nama_perusahaan')
                    ->get();

                $insertData = [];
                foreach ($validCompanies as $company) {
                    $insertData[] = [
                        'group_id' => (int) $groupId,
                        'leads_id' => (int) $company->id,
                        'nama_perusahaan' => $company->nama_perusahaan,
                        'created_at' => $now,
                        'created_by' => $currentUser,
                        'update_at' => $now,
                        'update_by' => $currentUser
                    ];
                }

                if (!empty($insertData)) {
                    PerusahaanGroupDetail::insert($insertData);
                    $totalProcessed += count($insertData);

                    $total = PerusahaanGroupDetail::where('group_id', $groupId)->count();

                    PerusahaanGroup::where('id', $groupId)
                        ->update([
                            'jumlah_perusahaan' => $total,
                            'update_at' => $now,
                            'update_by' => $currentUser
                        ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil memproses {$totalProcessed} perusahaan" .
                    ($totalSkipped > 0 ? ", {$totalSkipped} dilewati karena sudah ada di grup" : ""),
                'data' => [
                    'processed' => $totalProcessed,
                    'skipped' => $totalSkipped
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/company-group/statistics",
     *     summary="Get company group statistics",
     *     tags={"Company Group"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Statistik grup perusahaan berhasil diambil"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_groups", type="integer", example=10),
     *                 @OA\Property(property="total_companies_in_groups", type="integer", example=150),
     *                 @OA\Property(property="companies_without_group", type="integer", example=25),
     *                 @OA\Property(property="largest_group", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_grup", type="string", example="Grup Terbesar"),
     *                     @OA\Property(property="jumlah_perusahaan", type="integer", example=50),
     *                     @OA\Property(property="created_by", type="string", example="Admin User"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 ),
     *                 @OA\Property(property="recent_groups", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=5),
     *                         @OA\Property(property="nama_grup", type="string", example="Grup Baru"),
     *                         @OA\Property(property="jumlah_perusahaan", type="integer", example=3),
     *                         @OA\Property(property="created_by", type="string", example="Admin User"),
     *                         @OA\Property(property="created_at", type="string", format="date-time")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function getStatistics()
    {
        try {
            $totalGroups = PerusahaanGroup::count();
            $totalCompaniesInGroups = PerusahaanGroupDetail::count();

            // Mengambil perusahaan tanpa grup
            $companiesInGroups = PerusahaanGroupDetail::pluck('leads_id');
            $companiesWithoutGroup = Leads::whereNull('deleted_at')
                ->whereNotIn('id', $companiesInGroups)
                ->count();

            $largestGroup = PerusahaanGroup::orderBy('jumlah_perusahaan', 'desc')->first();
            $recentGroups = PerusahaanGroup::orderBy('created_at', 'desc')->limit(5)->get();

            return response()->json([
                'success' => true,
                'message' => 'Statistik grup perusahaan berhasil diambil',
                'data' => [
                    'total_groups' => $totalGroups,
                    'total_companies_in_groups' => $totalCompaniesInGroups,
                    'companies_without_group' => $companiesWithoutGroup,
                    'largest_group' => $largestGroup,
                    'recent_groups' => $recentGroups
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/company-group/filter-rekomendasi",
     *     summary="Filter recommended companies for grouping (fillter sebelum post)",
     *     tags={"Company Group"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="nama_grup",
     *         in="query",
     *         description="Group name for filtering",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama_perusahaan", type="string", example="PT Contoh Rekomendasi"),
     *                 @OA\Property(property="kota", type="string", example="Jakarta"),
     *                 @OA\Property(property="jenis_perusahaan", type="string", example="Retail")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan saat memfilter perusahaan: Error message")
     *         )
     *     )
     * )
     */

    public function filterRekomendasi(Request $request)
    {
        try {
            $keyword = $request->input('nama_grup');

            // MENGGUNAKAN MODEL Leads dan Relasi whereDoesntHave
            $companies = Leads::query()
                ->select(
                    'id',
                    'nama_perusahaan',
                    'kota',
                    'jenis_perusahaan_id'
                )
                ->whereDoesntHave('groupDetails')
                ->whereNull('deleted_at')
                ->with('jenisPerusahaan:id,nama')
                ->when($keyword, function ($query, $keyword) {
                    $query->where('nama_perusahaan', 'like', '%' . $keyword . '%');
                })
                ->orderBy('nama_perusahaan')
                ->get()
                // Mapping untuk format output
                ->map(function ($lead) {
                    return [
                        'id' => $lead->id,
                        'nama_perusahaan' => $lead->nama_perusahaan,
                        'kota' => $lead->kota,
                        'jenis_perusahaan' => optional($lead->jenisPerusahaan)->nama,
                    ];
                });

            return response()->json($companies);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memfilter perusahaan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/company-group/groupkan",
     *     summary="Group companies into a company group(post setelah filter )",
     *     tags={"Company Group"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama_grup_manual", "perusahaan_terpilih"},
     *             @OA\Property(property="nama_grup_manual", type="string", example="Grup Perusahaan Baru"),
     *             @OA\Property(property="perusahaan_terpilih", type="array",
     *                 @OA\Items(type="integer", example=1)
     *             ),
     *             @OA\Property(property="group_id", type="integer", example=1, description="Existing group ID (optional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Berhasil menambahkan 5 perusahaan ke grup. 2 perusahaan sudah ada di grup.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Nama grup dan setidaknya satu perusahaan harus dipilih.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Group not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grup tidak ditemukan.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Gagal mengelompokkan Leads: Error message")
     *         )
     *     )
     * )
     */

    public function groupkan(Request $request)
    {
        try {
            DB::beginTransaction();

            $namaGrup = $request->input('nama_grup_manual');
            $perusahaanTerpilih = $request->input('perusahaan_terpilih');
            $grupId = $request->input('group_id');

            if (empty($namaGrup) || empty($perusahaanTerpilih)) {
                // Mengembalikan JSON response karena ini diasumsikan sebagai API endpoint
                return response()->json(['success' => false, 'message' => 'Nama grup dan setidaknya satu perusahaan harus dipilih.'], 400);
            }

            $namaPengguna = auth()->user()->full_name ?? 'system';
            $now = now();

            // 1. Dapatkan atau Buat Grup
            if (!empty($grupId)) {
                $grup = PerusahaanGroup::find($grupId);
                if (!$grup) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'Grup tidak ditemukan.'], 404);
                }
            } else {
                // MENGGUNAKAN MODEL PerusahaanGroup::firstOrCreate
                $grup = PerusahaanGroup::firstOrCreate(
                    ['nama_grup' => $namaGrup],
                    [
                        'jumlah_perusahaan' => 0,
                        'created_at' => $now,
                        'created_by' => $namaPengguna,
                        'update_at' => $now,
                        'update_by' => $namaPengguna,
                    ]
                );
            }
            $grupId = $grup->id;

            // 2. Cek Leads mana yang sudah ada di grup
            $existingLeads = PerusahaanGroupDetail::where('group_id', $grupId)
                ->whereIn('leads_id', $perusahaanTerpilih)
                ->pluck('leads_id')
                ->toArray();

            $leadsToInsert = array_diff($perusahaanTerpilih, $existingLeads);

            if (!empty($leadsToInsert)) {
                // 3. Ambil data Leads
                $leadsData = Leads::whereIn('id', $leadsToInsert)
                    ->select('id', 'nama_perusahaan')
                    ->get()
                    ->keyBy('id');

                // 4. Persiapkan Data Insert
                $dataToInsert = [];
                foreach ($leadsToInsert as $leadsId) {
                    $leadData = $leadsData[$leadsId] ?? null;
                    if ($leadData) {
                        $dataToInsert[] = [
                            'group_id' => $grupId,
                            'leads_id' => $leadsId,
                            'nama_perusahaan' => $leadData->nama_perusahaan,
                            'created_at' => $now,
                            'created_by' => $namaPengguna,
                            'update_at' => $now,
                            'update_by' => $namaPengguna,
                        ];
                    }
                }

                // 5. Insert ke detail grup
                if (!empty($dataToInsert)) {
                    PerusahaanGroupDetail::insert($dataToInsert);
                }
            }

            // 6. HITUNG dan UPDATE jumlah perusahaan
            $totalPerusahaan = PerusahaanGroupDetail::where('group_id', $grupId)->count();

            $grup->update([
                'jumlah_perusahaan' => $totalPerusahaan,
                'update_at' => $now,
                'update_by' => $namaPengguna,
            ]);

            $jumlahBerhasil = count($leadsToInsert);
            $jumlahSudahAda = count($existingLeads);

            DB::commit();

            $message = "Berhasil menambahkan {$jumlahBerhasil} perusahaan ke grup.";
            if ($jumlahSudahAda > 0) {
                $message .= " {$jumlahSudahAda} perusahaan sudah ada di grup.";
            }

            // Mengembalikan JSON response karena ini diasumsikan sebagai API endpoint
            return response()->json(['success' => true, 'message' => $message]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal mengelompokkan Leads: ' . $e->getMessage()], 500);
        }
    }
    /**
     * @OA\Delete(
     *     path="/api/company-group/delete/{id}",
     *     summary="Delete a company group",
     *     tags={"Company Group"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Company group ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grup perusahaan berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Group not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grup tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function delete($id)
    {
        try {
            DB::beginTransaction();

            $group = PerusahaanGroup::find($id);

            if (!$group) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grup tidak ditemukan'
                ], 404);
            }

            // Delete all companies in this group first
            PerusahaanGroupDetail::where('group_id', $id)->delete();

            // Delete the group
            $group->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Grup perusahaan berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Delete(
     *     path="/api/company-group/remove-company/{groupId}/{companyId}",
     *     summary="Remove a company from group",
     *     tags={"Company Group"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="groupId",
     *         in="path",
     *         required=true,
     *         description="Group ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="companyId",
     *         in="path",
     *         required=true,
     *         description="Company/Leads ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Perusahaan berhasil dihapus dari grup")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Data not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Data tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function removeCompany($groupId, $companyId)
    {
        try {
            DB::beginTransaction();

            $groupDetail = PerusahaanGroupDetail::where('group_id', $groupId)
                ->where('leads_id', $companyId)
                ->first();

            if (!$groupDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }

            $groupDetail->delete();

            // Update company count in group
            $totalCompanies = PerusahaanGroupDetail::where('group_id', $groupId)->count();
            PerusahaanGroup::where('id', $groupId)->update([
                'jumlah_perusahaan' => $totalCompanies,
                'update_at' => Carbon::now(),
                'update_by' => Auth::user()->full_name ?? 'System'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Perusahaan berhasil dihapus dari grup'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/company-group/bulk-remove-companies",
     *     summary="Remove multiple companies from groups",
     *     tags={"Company Group"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"removals"},
     *             @OA\Property(property="removals", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="group_id", type="integer", example=1),
     *                     @OA\Property(property="leads_ids", type="array",
     *                         @OA\Items(type="integer", example=1)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Berhasil menghapus 3 perusahaan dari grup"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="removed", type="integer", example=3),
     *                 @OA\Property(property="not_found", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Data removals tidak valid")
     *         )
     *     )
     * )
     */
    public function bulkRemoveCompanies(Request $request)
    {
        try {
            DB::beginTransaction();

            $removals = $request->input('removals', []);

            if (empty($removals) || !is_array($removals)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data removals tidak valid'
                ], 400);
            }

            $totalRemoved = 0;
            $totalNotFound = 0;

            foreach ($removals as $removal) {
                $groupId = $removal['group_id'] ?? null;
                $leadsIds = $removal['leads_ids'] ?? [];

                if (empty($groupId) || empty($leadsIds)) {
                    continue;
                }

                $deleted = PerusahaanGroupDetail::where('group_id', $groupId)
                    ->whereIn('leads_id', $leadsIds)
                    ->delete();

                $totalRemoved += $deleted;
                $totalNotFound += (count($leadsIds) - $deleted);

                // Update company count in group
                if ($deleted > 0) {
                    $totalCompanies = PerusahaanGroupDetail::where('group_id', $groupId)->count();
                    PerusahaanGroup::where('id', $groupId)->update([
                        'jumlah_perusahaan' => $totalCompanies,
                        'update_at' => Carbon::now(),
                        'update_by' => Auth::user()->full_name ?? 'System'
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$totalRemoved} perusahaan dari grup" .
                    ($totalNotFound > 0 ? ", {$totalNotFound} tidak ditemukan" : ""),
                'data' => [
                    'removed' => $totalRemoved,
                    'not_found' => $totalNotFound
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}
