<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\Benua;
use App\Models\BidangPerusahaan;
use App\Models\Branch;
use App\Models\City;
use App\Models\Company;
use App\Models\District;
use App\Models\JabatanPic;
use App\Models\KategoriSesuaiHc;
use App\Models\Loyalty;
use App\Models\Negara;
use App\Models\Platform;
use App\Models\Province;
use App\Models\RuleThr;
use App\Models\SalaryRule;
use App\Models\StatusLeads;
use App\Models\Statusoptions;
use App\Models\StatusQuotation;
use App\Models\StatusSpk;
use App\Models\User;
use App\Models\Village;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Validator;
/**
 * @OA\Tag(
 *     name="Option",
 *     description="Endpoints untuk mengelola opsi dropdown dan data referensi lainnya"
 * )
 */
class OptionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/options/entitas",
     *     operationId="getEntitasDropdown",
     *     tags={"Option"},
     *     summary="Get companies for dropdown",
     *     description="Retrieve simplified company list for dropdown/select components (id and name only)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success - Returns simplified company list",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Company options retrieved successfully"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="PT Teknologi Indonesia"),
     *                     @OA\Property(property="code", type="string", example="PTTI")
     *                 )
     *             ),
     *             @OA\Property(property="total", type="integer", example=25)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function listEntitas(Request $request)
    {
        try {
            $data = Company::where('is_active', true)
                ->select(['id', 'name', 'code'])
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Company options retrieved successfully',
                'data' => $data,
                'total' => $data->count()
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/options/bidang-perusahaan",
     *     summary="Mendapatkan daftar semua bidang perusahaan",
     *     description="Endpoint ini digunakan untuk mengambil data bidang/industri perusahaan (misal: Manufacturing, Trading, Service, dll). Berguna untuk dropdown form input options saat menentukan bidang usaha perusahaan.",
     *     tags={"Option"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data bidang perusahaan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data bidang perusahaan berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama", type="string", example="Manufacturing"),
     *                     @OA\Property(property="created_at", type="string", example="2025-01-01T00:00:00.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", example="2025-01-01T00:00:00.000000Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function getBidangPerusahaan()
    {
        try {
            $bidangPerusahaan = BidangPerusahaan::whereNull('deleted_at')
                ->orderBy('nama', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data bidang perusahaan berhasil diambil',
                'data' => $bidangPerusahaan
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/options/platforms",
     *     summary="Mendapatkan daftar semua platform sumber options",
     *     description="Endpoint ini digunakan untuk mengambil data platform sumber options (misal: website, social media, referral, dll). Berguna untuk filter dan dropdown form input options.",
     *     tags={"Option"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data platform",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data platform berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama", type="string", example="Website")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function getPlatforms()
    {
        try {
            $platforms = Platform::all();

            return response()->json([
                'success' => true,
                'message' => 'Data platform berhasil diambil',
                'data' => $platforms
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/options/status-leads",
     *     summary="Mendapatkan daftar semua status leads",
     *     description="Endpoint ini digunakan untuk mengambil data status leads (misal: new, contacted, qualified, dll). Berguna untuk filter dan dropdown form input leads.",
     *     tags={"Option"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data status leads",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data status leads berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama", type="string", example="New Lead")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function getStatusLeads()
    {
        try {
            $statusleads = StatusLeads::all();

            return response()->json([
                'success' => true,
                'message' => 'Data status leads berhasil diambil',
                'data' => $statusleads
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/options/benua",
     *     summary="Mendapatkan daftar semua benua",
     *     description="Endpoint ini digunakan untuk mengambil data benua dari database. Berguna untuk form input perusahaan luar negeri saat membuat atau mengupdate options.",
     *     tags={"Option"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data benua",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data benua berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id_benua", type="integer", example=1),
     *                     @OA\Property(property="nama_benua", type="string", example="Asia"),
     *                     @OA\Property(property="kode_benua", type="string", example="AS")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function getBenua()
    {
        try {
            $benua = Benua::all();

            return response()->json([
                'success' => true,
                'message' => 'Data benua berhasil diambil',
                'data' => $benua
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/options/jabatan-pic",
     *     summary="Mendapatkan daftar semua jabatan PIC",
     *     description="Endpoint ini digunakan untuk mengambil data jabatan Person In Charge (PIC). Berguna untuk dropdown form input jabatan PIC saat membuat atau mengupdate options.",
     *     tags={"Option"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data jabatan PIC",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data jabatan PIC berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_jabatan", type="string", example="Manager Purchasing"),
     *                     @OA\Property(property="kode", type="string", example="MGR-PUR"),
     *                     @OA\Property(property="created_at", type="string", example="15-01-2025")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function getJabatanPic()
    {
        try {
            // Menggunakan Eloquent Model JabatanPic tanpa filter is_active
            $jabatanPic = JabatanPic::whereNull('deleted_at')->get();

            return response()->json([
                'success' => true,
                'message' => 'Data jabatan PIC berhasil diambil',
                'data' => $jabatanPic
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/options/branches/{provinceId}",
     *     tags={"Option"},
     *     summary="Mendapatkan daftar branch berdasarkan provinsi",
     *     description="Mengambil daftar branch yang aktif berdasarkan ID provinsi",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="provinceId",
     *         in="path",
     *         description="ID provinsi yang dipilih",
     *         required=true,
     *         @OA\Schema(type="integer", example=11)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil daftar branch",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar branch berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Jakarta Pusat"),
     *                     @OA\Property(property="description", type="string", example="JKT"),
     *                     @OA\Property(property="city_id", type="integer", example=3171),
     *                     @OA\Property(property="is_active", type="integer", example=1),
     *                     @OA\Property(
     *                         property="city",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=3171),
     *                         @OA\Property(property="name", type="string", example="JAKARTA PUSAT"),
     *                         @OA\Property(property="province_id", type="integer", example=31)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Data tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Tidak ada branch untuk provinsi ini")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan saat mengambil data branch")
     *         )
     *     )
     * )
     */
    public function getBranchesByProvince($provinceId)
    {
        try {
            $branches = Branch::where('is_active', 1)
                ->byProvince($provinceId)
                ->with(['city:id,name,province_id'])
                ->select('id', 'name', 'description', 'city_id', 'is_active')
                ->get();

            if ($branches->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada branch untuk provinsi ini',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Daftar branch berhasil diambil',
                'data' => $branches
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data branch',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/options/branches",
     *     tags={"Option"},
     *     summary="Mendapatkan daftar branch",
     *     description="Mengambil daftar branch yang aktif, bisa difilter berdasarkan provinsi (optional)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="province_id",
     *         in="query",
     *         description="ID provinsi untuk filter (optional)",
     *         required=false,
     *         @OA\Schema(type="integer", example=31)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil daftar branch",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar branch berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Jakarta Pusat"),
     *                     @OA\Property(property="description", type="string", example="JKT"),
     *                     @OA\Property(property="city_id", type="integer", example=3171),
     *                     @OA\Property(property="is_active", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getBranches(Request $request)
    {
        try {
            $query = Branch::where('is_active', 1);

            // Filter berdasarkan province_id jika ada
            if ($request->has('province_id') && !empty($request->province_id)) {
                $query->byProvince($request->province_id);
            }

            $branches = $query->select('id', 'name', 'description', 'city_id', 'is_active')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar branch berhasil diambil',
                'data' => $branches
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data branch',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/options/users",
     *     tags={"Option"},
     *     summary="Mendapatkan daftar user sales",
     *     description="Mengambil daftar user dengan role sales berdasarkan branch",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="branch_id",
     *         in="query",
     *         description="ID branch",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil daftar user",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar user berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=123),
     *                     @OA\Property(property="full_name", type="string", example="John Doe"),
     *                     @OA\Property(property="username", type="string", example="john.doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="role_id", type="integer", example=29),
     *                     @OA\Property(property="branch_id", type="integer", example=2)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validasi gagal"
     *     )
     * )
     */
    public function getUsers(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $users = User::where('is_active', 1)
                ->whereIn('role_id', [29, 31, 32, 33])
                ->where('branch_id', $request->branch_id)
                ->select('id', 'full_name', 'username', 'email', 'role_id', 'branch_id')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar user berhasil diambil',
                'data' => $users
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data user',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/options/status-quotation",
     *     summary="Mendapatkan daftar semua ststus quotation",
     *     description="Endpoint ini digunakan untuk mengambil data jabatan Person In Charge (PIC). Berguna untuk dropdown form input ststus quotation.",
     *     tags={"Option"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data ststus quotation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data ststus quotation berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_jabatan", type="string", example="Manager Purchasing"),
     *                     @OA\Property(property="kode", type="string", example="MGR-PUR"),
     *                     @OA\Property(property="created_at", type="string", example="15-01-2025")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function getStatusQuotation()
    {
        try {
            // Menggunakan Eloquent Model JabatanPic tanpa filter is_active
            $jabatanPic = StatusQuotation::whereNull('deleted_at')->get();

            return response()->json([
                'success' => true,
                'message' => 'Data jabatan PIC berhasil diambil',
                'data' => $jabatanPic
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }/**
     * @OA\Get(
     *     path="/api/options/entitas/{layanan_id}",
     *     operationId="getEntitasByLayanan",
     *      tags={"Option"},
     *     summary="Get companies for dropdown based on layanan",
     *     description="Retrieve company list filtered by layanan_id for dropdown/select components",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="layanan_id",
     *         in="path",
     *         required=true,
     *         description="ID layanan/kebutuhan",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success - Returns filtered company list",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Company options retrieved successfully"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="PT Teknologi Indonesia"),
     *                     @OA\Property(property="code", type="string", example="PTTI")
     *                 )
     *             ),
     *             @OA\Property(property="total", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function getEntitas($layanan_id)
    {
        try {
            $query = Company::where('is_active', true)
                ->select(['id', 'name', 'code'])
                ->orderBy('name', 'asc');

            // Filter berdasarkan layanan_id sesuai dengan logika JavaScript
            switch ($layanan_id) {
                case 1:
                    // Untuk layanan_id = 1: GSU atau SN
                    $query->where(function ($q) {
                        $q->where('code', 'GSU')
                            ->orWhere('code', 'SN');
                    });
                    break;

                case 2:
                case 4:
                    // Untuk layanan_id = 2 atau 4: SIG atau SNI
                    $query->where(function ($q) {
                        $q->where('code', 'SIG')
                            ->orWhere('code', 'SNI');
                    });
                    break;

                case 3:
                    // Untuk layanan_id = 3: RCI atau SNI
                    $query->where(function ($q) {
                        $q->where('code', 'RCI')
                            ->orWhere('code', 'SNI');
                    });
                    break;

                default:
                    // Untuk layanan_id lainnya, kembalikan array kosong
                    return response()->json([
                        'success' => true,
                        'message' => 'Company options retrieved successfully',
                        'data' => [],
                        'total' => 0
                    ], 200);
            }

            $data = $query->get();

            // Tambahkan opsi IONS secara manual (sesuai dengan JavaScript)
            // Pastikan IONS belum ada dalam hasil query sebelum menambahkannya
            $ionsExists = $data->contains('id', 17);
            if (!$ionsExists) {
                $ionsCompany = [
                    'id' => 17,
                    'name' => 'PT. Indah Optimal Nusantara',
                    'code' => 'IONS'
                ];
                $data->push($ionsCompany);
            }

            return response()->json([
                'success' => true,
                'message' => 'Company options retrieved successfully',
                'data' => $data,
                'total' => $data->count()
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/options/provinsi",
     *     summary="Mendapatkan daftar semua provinsi",
     *     description="Endpoint ini digunakan untuk mengambil data provinsi dari database. Berguna untuk form input alamat perusahaan saat membuat atau mengupdate options.",
     *      tags={"Option"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data provinsi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data provinsi berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=11),
     *                     @OA\Property(property="name", type="string", example="ACEH"),
     *                     @OA\Property(property="province_id", type="string", example="11")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function getProvinsi()
    {
        try {
            // Menggunakan Eloquent Model Province
            $provinsi = Province::all();

            return response()->json([
                'success' => true,
                'message' => 'Data provinsi berhasil diambil',
                'data' => $provinsi
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/options/kota/{provinsiId}",
     *     summary="Mendapatkan daftar kota berdasarkan ID provinsi",
     *     description="Endpoint ini digunakan untuk mengambil data kota/kabupaten berdasarkan provinsi yang dipilih. Berguna untuk form input alamat perusahaan.",
     *     tags={"Option"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="provinsiId",
     *         in="path",
     *         description="ID provinsi yang dipilih",
     *         required=true,
     *         @OA\Schema(type="integer", example=11)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data kota",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data kota berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1101),
     *                     @OA\Property(property="name", type="string", example="KABUPATEN SIMEULUE"),
     *                     @OA\Property(property="province_id", type="integer", example=11)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */

    public function getKota($provinsiId)
    {
        try {
            // Menggunakan Eloquent Model City dan metode where
            $kota = City::where('province_id', $provinsiId)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data kota berhasil diambil',
                'data' => $kota
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/options/kecamatan/{kotaId}",
     *     summary="Mendapatkan daftar kecamatan berdasarkan ID kota",
     *     description="Endpoint ini digunakan untuk mengambil data kecamatan berdasarkan kota/kabupaten yang dipilih. Berguna untuk form input alamat perusahaan yang lebih detail.",
     *      tags={"Option"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="kotaId",
     *         in="path",
     *         description="ID kota/kabupaten yang dipilih",
     *         required=true,
     *         @OA\Schema(type="integer", example=1101)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data kecamatan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data kecamatan berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=110101),
     *                     @OA\Property(property="name", type="string", example="TEUPAH SELATAN"),
     *                     @OA\Property(property="city_id", type="integer", example=1101)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function getKecamatan($kotaId)
    {
        try {
            // Menggunakan Eloquent Model District
            $kecamatan = District::where('city_id', $kotaId)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data kecamatan berhasil diambil',
                'data' => $kecamatan
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/options/kelurahan/{kecamatanId}",
     *     summary="Mendapatkan daftar kelurahan berdasarkan ID kecamatan",
     *     description="Endpoint ini digunakan untuk mengambil data kelurahan/desa berdasarkan kecamatan yang dipilih. Berguna untuk form input alamat perusahaan yang lengkap.",
     *      tags={"Option"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="kecamatanId",
     *         in="path",
     *         description="ID kecamatan yang dipilih",
     *         required=true,
     *         @OA\Schema(type="integer", example=110101)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data kelurahan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data kelurahan berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=11010101),
     *                     @OA\Property(property="name", type="string", example="LATIUNG"),
     *                     @OA\Property(property="district_id", type="integer", example=110101)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function getKelurahan($kecamatanId)
    {
        try {
            $kelurahan = Village::where('district_id', $kecamatanId)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data kelurahan berhasil diambil',
                'data' => $kelurahan
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/options/negara/{benuaId}",
     *     summary="Mendapatkan daftar negara berdasarkan ID benua",
     *     description="Endpoint ini digunakan untuk mengambil data negara berdasarkan benua yang dipilih. Berguna untuk form input perusahaan luar negeri.",
     *      tags={"Option"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="benuaId",
     *         in="path",
     *         description="ID benua yang dipilih",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data negara",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data negara berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_negara", type="string", example="Indonesia"),
     *                     @OA\Property(property="id_benua", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */

    public function getNegara($benuaId)
    {
        try {
            $negara = Negara::where('id_benua', $benuaId)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data negara berhasil diambil',
                'data' => $negara
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/options/loyalty",
     *     summary="Mendapatkan daftar loyalty",
     *     description="Endpoint ini digunakan untuk mengambil data loyalty. Berguna untuk dropdown form input options.",
     *     tags={"Option"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data loyalty",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data loyalty list berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Standard Loyalty"),
     *                     @OA\Property(property="description", type="string", example="Standard loyalty program")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function loyaltylist()
    {
        try {
            $loyaltylist = Loyalty::get();

            return response()->json([
                'success' => true,
                'message' => 'Data loyalty list berhasil diambil',
                'data' => $loyaltylist
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/options/kategori-sesuai-hc",
     *     summary="Mendapatkan daftar kategori sesuai HC",
     *     description="Endpoint ini digunakan untuk mengambil data kategori sesuai headcount. Berguna untuk dropdown form input options.",
     *     tags={"Option"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data kategori sesuai HC",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data kategori sesuai HC berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Kategori A"),
     *                     @OA\Property(property="description", type="string", example="Kategori sesuai HC A")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function kategorusesuaihc()
    {
        try {
            $kategorisesuaihc = KategoriSesuaiHc::get();

            return response()->json([
                'success' => true,
                'message' => 'Data kategori sesuai HC berhasil diambil',
                'data' => $kategorisesuaihc
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/options/rule-thr",
     *     summary="Mendapatkan daftar rule THR",
     *     description="Endpoint ini digunakan untuk mengambil data rule THR (Tunjangan Hari Raya). Berguna untuk dropdown form input options.",
     *     tags={"Option"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data rule THR",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data rule THR berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Rule THR A"),
     *                     @OA\Property(property="description", type="string", example="Rule untuk THR tipe A")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function rulethr()
    {
        try {
            $rulethr = RuleThr::get();

            return response()->json([
                'success' => true,
                'message' => 'Data rule THR berhasil diambil',
                'data' => $rulethr
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/options/salary-rule",
     *     summary="Mendapatkan daftar salary rule",
     *     description="Endpoint ini digunakan untuk mengambil data salary rule. Berguna untuk dropdown form input options.",
     *     tags={"Option"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data salary rule",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data salary rule berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Salary Rule A"),
     *                     @OA\Property(property="description", type="string", example="Aturan gaji untuk tipe A")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function salaryrule()
    {
        try {
            $salaryrule = SalaryRule::get();

            return response()->json([
                'success' => true,
                'message' => 'Data salary rule berhasil diambil',
                'data' => $salaryrule
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/spk/status-spk",
     *     summary="Mendapatkan daftar semua status SPK",
     *     description="Endpoint untuk mengambil data master status SPK yang tersedia dalam sistem.",
     *     tags={"SPK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Sukses mengambil data status SPK",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data status SPK berhasil diambil"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="Draft"),
     *                 @OA\Property(property="keterangan", type="string", example="SPK masih dalam tahap draft"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15 10:30:00"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-15 10:30:00")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error server",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Database connection failed")
     *         )
     *     )
     * )
     */
    public function statusspk()
    {
        try {
            $statusspk = StatusSpk::get();

            return response()->json([
                'success' => true,
                'message' => 'Data status SPK berhasil diambil',
                'data' => $statusspk
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}