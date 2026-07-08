<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyGroupStoreRequest;
use App\Http\Requests\CompanyGroupUpdateRequest;
use App\Models\PerusahaanGroup;
use App\Models\PerusahaanGroupDetail;
use App\Models\Leads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Company Groups",
 *     description="API endpoints untuk manajemen grup perusahaan dan anggota perusahaan"
 * )
 */
class CompanyGroupController extends Controller
{
    // ==================== PRIVATE HELPERS ====================

    private function mapLeadToResponse($lead)
    {
        return [
            'id' => $lead->id,
            'nama_perusahaan' => $lead->nama_perusahaan,
            'kota' => $lead->kota,
            'pic' => $lead->pic,
            'no_telp' => $lead->no_telp,
            'email' => $lead->email,
            'jenis_perusahaan' => optional($lead->jenisPerusahaan)->nama,
            'status_leads' => optional($lead->statusLeads)->nama,
            'warna_background' => optional($lead->statusLeads)->warna_background,
            'warna_font' => optional($lead->statusLeads)->warna_font,
        ];
    }

    private function updateGroupCompanyCount($groupId)
    {
        $total = PerusahaanGroupDetail::where('group_id', $groupId)->count();

        PerusahaanGroup::where('id', $groupId)->update([
            'jumlah_perusahaan' => $total,
            'update_at' => Carbon::now(),
            'update_by' => Auth::user()->full_name ?? 'System'
        ]);

        return $total;
    }

    private function getCurrentUserInfo()
    {
        return [
            'name' => Auth::user()->full_name ?? 'System',
            'id' => Auth::id(),
            'time' => Carbon::now()
        ];
    }

    // ==================== GRUP MANAGEMENT ====================

    /**
     * @OA\Get(
     *     path="/api/company-group/list",
     *     summary="Get list of all company groups",
     *     tags={"Company Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Keyword untuk pencarian nama grup",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List grup perusahaan berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data grup perusahaan berhasil diambil"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_grup", type="string", example="Grup Retail Jakarta"),
     *                     @OA\Property(property="jumlah_perusahaan", type="integer", example=15),
     *                     @OA\Property(property="created_by", type="string", example="John Doe"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15 10:30:00")
     *                 )
     *             ),
     *             @OA\Property(property="total", type="integer", example=25)
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
        $search = $request->input('search');
        $query = PerusahaanGroup::select('id', 'nama_grup', 'jumlah_perusahaan', 'created_by', 'created_at')
            ->orderBy('created_at', 'desc');

        if ($search) {
            $query->search($search);
        }

        $groups = $query->get();

        // Bespoke `total` shape — kept raw (trait envelope tak punya field ini).
        return response()->json([
            'success' => true,
            'message' => 'Data grup perusahaan berhasil diambil',
            'data' => $groups,
            'total' => $groups->count()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/company-group/view/{id}",
     *     summary="Get detailed information of a company group",
     *     tags={"Company Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID grup perusahaan",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detail grup berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Detail grup berhasil diambil"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="group", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_grup", type="string", example="Grup Retail Jakarta"),
     *                     @OA\Property(property="jumlah_perusahaan", type="integer", example=15),
     *                     @OA\Property(property="created_by", type="string", example="John Doe"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15 10:30:00")
     *                 ),
     *                 @OA\Property(property="total_perusahaan", type="integer", example=15),
     *                 @OA\Property(property="perusahaan", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama_perusahaan", type="string", example="PT Contoh Indonesia"),
     *                         @OA\Property(property="kota", type="string", example="Jakarta"),
     *                         @OA\Property(property="pic", type="string", example="Budi Santoso"),
     *                         @OA\Property(property="no_telp", type="string", example="08123456789"),
     *                         @OA\Property(property="email", type="string", example="budi@contoh.com"),
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
    public function view($id)
    {
        $group = PerusahaanGroup::find($id);
        if (!$group) {
            return $this->notFoundResponse('Grup tidak ditemukan');
        }

        $perusahaanDetails = PerusahaanGroupDetail::with([
            'lead.jenisPerusahaan:id,nama',
            'lead.statusLeads:id,nama,warna_background,warna_font'
        ])->where('group_id', $id)->get();

        $perusahaan = $perusahaanDetails->map(function ($detail) {
            return $detail->lead ? $this->mapLeadToResponse($detail->lead) : null;
        })->filter()->sortBy('nama_perusahaan')->values();

        return $this->successResponse([
            'group' => $group,
            'total_perusahaan' => $perusahaan->count(),
            'perusahaan' => $perusahaan
        ], 'Detail grup berhasil diambil');
    }

    /**
     * @OA\Post(
     *     path="/api/company-group/create",
     *     summary="Create new company group (with or without companies)",
     *     description="Membuat grup perusahaan baru. Bisa membuat grup kosong atau langsung dengan perusahaan.",
     *     tags={"Company Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama_grup"},
     *             @OA\Property(property="nama_grup", type="string", example="Grup Retail Baru", description="Nama grup baru"),
     *             @OA\Property(property="perusahaan_ids", type="array", 
     *                 @OA\Items(type="integer", example=1),
     *                 description="Array ID perusahaan yang akan ditambahkan ke grup baru (opsional)"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Grup berhasil dibuat",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grup 'Grup Retail Baru' berhasil dibuat dan menambahkan 5 perusahaan"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="group", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_grup", type="string", example="Grup Retail Baru"),
     *                     @OA\Property(property="jumlah_perusahaan", type="integer", example=5),
     *                     @OA\Property(property="created_by", type="string", example="John Doe"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15 10:30:00")
     *                 ),
     *                 @OA\Property(property="added_companies", type="integer", example=5),
     *                 @OA\Property(property="already_in_group", type="integer", example=2),
     *                 @OA\Property(property="not_found", type="integer", example=1),
     *                 @OA\Property(property="companies_with_group", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="company_id", type="integer", example=10),
     *                         @OA\Property(property="company_name", type="string", example="PT Already Grouped"),
     *                         @OA\Property(property="current_group", type="string", example="Grup Existing")
     *                     ),
     *                     description="List perusahaan yang sudah memiliki grup"
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function create(CompanyGroupStoreRequest $request)
    {
        $userInfo = $this->getCurrentUserInfo();
        $perusahaanIds = $request->input('perusahaan_ids', []);
        $namaGrup = $request->input('nama_grup');

        [$group, $stats] = DB::transaction(function () use ($namaGrup, $perusahaanIds, $userInfo) {
            // Buat grup baru
            $group = PerusahaanGroup::create([
                'nama_grup' => $namaGrup,
                'jumlah_perusahaan' => 0,
                'created_at' => $userInfo['time'],
                'created_by' => $userInfo['name'],
                'created_by_user_id' => $userInfo['id'],
                'update_at' => $userInfo['time'],
                'update_by' => $userInfo['name']
            ]);

            // Inisialisasi counter
            $stats = [
                'added' => 0,
                'already_in_group' => 0,
                'not_found' => 0,
                'companies_with_group' => []
            ];

            // Proses penambahan perusahaan jika ada
            if (!empty($perusahaanIds)) {
                $stats = $this->processCompanyAdditions($group->id, $perusahaanIds, $userInfo);

                // Update jumlah perusahaan di grup
                $this->updateGroupCompanyCount($group->id);
                $group->refresh();
            }

            return [$group, $stats];
        });

        // Build response message
        $message = $this->buildCreateSuccessMessage($group->nama_grup, $stats);

        return $this->successResponse([
            'group' => $group,
            'added_companies' => $stats['added'],
            'already_in_group' => $stats['already_in_group'],
            'not_found' => $stats['not_found'],
            'companies_with_group' => $stats['companies_with_group']
        ], $message);
    }

    /**
     * Proses penambahan perusahaan ke grup baru
     * 
     * @param int $groupId
     * @param array $perusahaanIds
     * @param array $userInfo
     * @return array
     */
    private function processCompanyAdditions($groupId, $perusahaanIds, $userInfo)
    {
        $stats = [
            'added' => 0,
            'already_in_group' => 0,
            'not_found' => 0,
            'companies_with_group' => []
        ];

        // Cek perusahaan yang sudah ada di grup lain
        $companiesInGroups = PerusahaanGroupDetail::whereIn('leads_id', $perusahaanIds)
            ->with(['lead:id,nama_perusahaan', 'group:id,nama_grup'])
            ->get();

        // Buat mapping perusahaan yang sudah ada di grup
        $existingCompanyIds = $companiesInGroups->pluck('leads_id')->toArray();

        // Simpan info perusahaan yang sudah punya grup
        foreach ($companiesInGroups as $detail) {
            if ($detail->lead && $detail->group) {
                $stats['companies_with_group'][] = [
                    'company_id' => $detail->leads_id,
                    'company_name' => $detail->lead->nama_perusahaan,
                    'current_group' => $detail->group->nama_grup
                ];
            }
        }
        $stats['already_in_group'] = count($existingCompanyIds);

        // Filter perusahaan yang belum ada di grup
        $availableCompanyIds = array_diff($perusahaanIds, $existingCompanyIds);

        if (empty($availableCompanyIds)) {
            return $stats;
        }

        // Ambil data perusahaan yang valid dan belum ada di grup
        $validCompanies = Leads::whereIn('id', $availableCompanyIds)
            ->whereNull('deleted_at')
            ->select('id', 'nama_perusahaan')
            ->get();

        // Hitung perusahaan yang tidak ditemukan
        $stats['not_found'] = count($availableCompanyIds) - $validCompanies->count();

        // Siapkan data untuk insert
        $insertData = [];
        foreach ($validCompanies as $company) {
            $insertData[] = [
                'group_id' => $groupId,
                'leads_id' => $company->id,
                'nama_perusahaan' => $company->nama_perusahaan,
                'created_at' => $userInfo['time'],
                'created_by' => $userInfo['name'],
                'created_by_user_id' => $userInfo['id'],
                'update_at' => $userInfo['time'],
                'update_by' => $userInfo['name']
            ];
        }

        // Insert data jika ada
        if (!empty($insertData)) {
            PerusahaanGroupDetail::insert($insertData);
            $stats['added'] = count($insertData);
        }

        return $stats;
    }

    /**
     * Build success message untuk create grup
     * 
     * @param string $groupName
     * @param array $stats
     * @return string
     */
    private function buildCreateSuccessMessage($groupName, $stats)
    {
        $messages = ["Grup '{$groupName}' berhasil dibuat"];

        if ($stats['added'] > 0) {
            $messages[] = "{$stats['added']} perusahaan berhasil ditambahkan";
        }

        if ($stats['already_in_group'] > 0) {
            $groupDetails = $this->formatCompaniesWithGroupMessage($stats['companies_with_group']);
            $messages[] = "{$stats['already_in_group']} perusahaan sudah terdaftar di grup lain: {$groupDetails}";
        }

        if ($stats['not_found'] > 0) {
            $messages[] = "{$stats['not_found']} perusahaan tidak ditemukan";
        }

        return implode(', ', $messages) . '.';
    }

    /**
     * Format pesan detail perusahaan yang sudah ada di grup
     * 
     * @param array $companiesWithGroup
     * @return string
     */
    private function formatCompaniesWithGroupMessage($companiesWithGroup)
    {
        if (empty($companiesWithGroup)) {
            return '';
        }

        $details = array_map(function ($item) {
            return "{$item['company_name']} (di {$item['current_group']})";
        }, $companiesWithGroup);

        // Batasi maksimal 3 perusahaan di pesan, sisanya tampilkan jumlahnya
        if (count($details) > 3) {
            $shown = array_slice($details, 0, 3);
            $remaining = count($details) - 3;
            return implode(', ', $shown) . " dan {$remaining} lainnya";
        }

        return implode(', ', $details);
    }
    /**
     * @OA\Put(
     *     path="/api/company-group/update/{id}",
     *     summary="Update company group name",
     *     tags={"Company Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID grup perusahaan yang akan diupdate",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama_grup"},
     *             @OA\Property(property="nama_grup", type="string", example="Grup Retail Jakarta Updated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Grup berhasil diupdate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grup 'Grup Retail Jakarta Updated' berhasil diperbarui"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama_grup", type="string", example="Grup Retail Jakarta Updated"),
     *                 @OA\Property(property="jumlah_perusahaan", type="integer", example=15),
     *                 @OA\Property(property="created_by", type="string", example="John Doe"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15 10:30:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validasi gagal"),
     *             @OA\Property(property="errors", type="object")
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
    public function update(CompanyGroupUpdateRequest $request, $id)
    {
        $group = PerusahaanGroup::find($id);
        if (!$group) {
            return $this->notFoundResponse('Grup tidak ditemukan');
        }

        $userInfo = $this->getCurrentUserInfo();

        $group->update([
            'nama_grup' => $request->nama_grup,
            'update_at' => $userInfo['time'],
            'update_by' => $userInfo['name']
        ]);

        return $this->successResponse(
            $group->refresh(),
            'Grup "' . $request->nama_grup . '" berhasil diperbarui'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/company-group/delete/{id}",
     *     summary="Delete company group and all its members",
     *     tags={"Company Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID grup perusahaan yang akan dihapus",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Grup berhasil dihapus",
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
        $group = PerusahaanGroup::find($id);
        if (!$group) {
            return $this->notFoundResponse('Grup tidak ditemukan');
        }

        DB::transaction(function () use ($id, $group) {
            PerusahaanGroupDetail::where('group_id', $id)->delete();
            $group->delete();
        });

        return $this->messageResponse('Grup perusahaan berhasil dihapus');
    }

    // ==================== ANGGOTA MANAGEMENT ====================

    /**
     * @OA\Get(
     *     path="/api/company-group/companies/{groupId}",
     *     summary="Get all companies in a specific group",
     *     tags={"Company Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="groupId",
     *         in="path",
     *         description="ID grup perusahaan",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data perusahaan dalam grup berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data perusahaan dalam grup berhasil diambil"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT Contoh Indonesia"),
     *                     @OA\Property(property="kota", type="string", example="Jakarta"),
     *                     @OA\Property(property="pic", type="string", example="Budi Santoso"),
     *                     @OA\Property(property="no_telp", type="string", example="08123456789"),
     *                     @OA\Property(property="email", type="string", example="budi@contoh.com"),
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
        $perusahaanDetails = PerusahaanGroupDetail::with([
            'lead.jenisPerusahaan:id,nama',
            'lead.statusLeads:id,nama,warna_background,warna_font'
        ])->where('group_id', $groupId)->get();

        $perusahaan = $perusahaanDetails->map(function ($detail) {
            return $detail->lead ? $this->mapLeadToResponse($detail->lead) : null;
        })->filter()->sortBy('nama_perusahaan')->values();

        return $this->successResponse($perusahaan, 'Data perusahaan dalam grup berhasil diambil');
    }

    /**
     * @OA\Get(
     *     path="/api/company-group/available-companies/{groupId}",
     *     summary="Search available companies to add to group",
     *     tags={"Company Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="groupId",
     *         in="path",
     *         description="ID grup perusahaan tujuan",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="keyword",
     *         in="query",
     *         description="Keyword pencarian (nama perusahaan atau kota)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data perusahaan tersedia berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data perusahaan tersedia berhasil diambil"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT Contoh Baru"),
     *                     @OA\Property(property="kota", type="string", example="Bandung"),
     *                     @OA\Property(property="pic", type="string", example="Sari Dewi"),
     *                     @OA\Property(property="no_telp", type="string", example="08123456790"),
     *                     @OA\Property(property="email", type="string", example="sari@contoh.com"),
     *                     @OA\Property(property="jenis_perusahaan", type="string", example="Manufacturing"),
     *                     @OA\Property(property="status_leads", type="string", example="Warm Lead"),
     *                     @OA\Property(property="warna_background", type="string", example="#FFFF00"),
     *                     @OA\Property(property="warna_font", type="string", example="#000000")
     *                 )
     *             ),
     *             @OA\Property(property="total", type="integer", example=50)
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
    public function getAvailableCompanies(Request $request, $groupId)
    {
        $keyword = trim($request->input('keyword', ''));

        $group = PerusahaanGroup::find($groupId);
        if (!$group) {
            return $this->notFoundResponse('Grup tidak ditemukan');
        }

        $query = Leads::query()
            ->select('id', 'nama_perusahaan', 'kota', 'pic', 'no_telp', 'email', 'jenis_perusahaan_id', 'status_leads_id')
            ->with(['jenisPerusahaan:id,nama', 'statusLeads:id,nama,warna_background,warna_font'])
            ->whereDoesntHave('groupDetails')
            ->whereNull('deleted_at');

        if (!empty($keyword) && strlen($keyword) >= 2) {
            $query->where(function ($q) use ($keyword) {
                $q->where('nama_perusahaan', 'like', "%{$keyword}%")
                    ->orWhere('kota', 'like', "%{$keyword}%");
            });
        }

        $companies = $query->orderBy('nama_perusahaan')
            ->get()
            ->map(fn($lead) => $this->mapLeadToResponse($lead));

        // Bespoke `total` shape — kept raw.
        return response()->json([
            'success' => true,
            'message' => 'Data perusahaan tersedia berhasil diambil',
            'data' => $companies,
            'total' => $companies->count()
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/company-group/remove-company/{groupId}/{companyId}",
     *     summary="Remove a company from group",
     *     tags={"Company Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="groupId",
     *         in="path",
     *         description="ID grup perusahaan",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="companyId",
     *         in="path",
     *         description="ID perusahaan/leads yang akan dihapus",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Perusahaan berhasil dihapus dari grup",
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
        $groupDetail = PerusahaanGroupDetail::where('group_id', $groupId)
            ->where('leads_id', $companyId)
            ->first();

        if (!$groupDetail) {
            return $this->notFoundResponse('Data tidak ditemukan');
        }

        DB::transaction(function () use ($groupDetail, $groupId) {
            $groupDetail->delete();
            $this->updateGroupCompanyCount($groupId);
        });

        return $this->messageResponse('Perusahaan berhasil dihapus dari grup');
    }

    // ==================== BULK OPERATIONS ====================

    /**
     * @OA\Post(
     *     path="/api/company-group/bulk-assign",
     *     summary="Bulk assign companies to multiple groups",
     *     tags={"Company Groups"},
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
     *         description="Bulk assignment berhasil",
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
     *         description="Invalid data",
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
        $assignments = $request->input('assignments', []);
        if (empty($assignments) || !is_array($assignments)) {
            return $this->errorResponse('Data assignments tidak valid', 400);
        }

        $userInfo = $this->getCurrentUserInfo();

        [$totalProcessed, $totalSkipped] = DB::transaction(function () use ($assignments, $userInfo) {
            $totalProcessed = 0;
            $totalSkipped = 0;

            foreach ($assignments as $assignment) {
                $groupId = $assignment['group_id'] ?? null;
                $leadsIds = $assignment['leads_ids'] ?? [];

                if (empty($groupId) || empty($leadsIds))
                    continue;

                $existingIds = PerusahaanGroupDetail::where('group_id', $groupId)
                    ->whereIn('leads_id', $leadsIds)
                    ->pluck('leads_id')
                    ->toArray();

                $newCompanies = array_diff($leadsIds, $existingIds);
                $totalSkipped += count($existingIds);

                if (empty($newCompanies))
                    continue;

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
                        'created_at' => $userInfo['time'],
                        'created_by' => $userInfo['name'],
                        'created_by_user_id' => $userInfo['id'],
                        'update_at' => $userInfo['time'],
                        'update_by' => $userInfo['name']
                    ];
                }

                if (!empty($insertData)) {
                    PerusahaanGroupDetail::insert($insertData);
                    $totalProcessed += count($insertData);
                    $this->updateGroupCompanyCount($groupId);
                }
            }

            return [$totalProcessed, $totalSkipped];
        });

        $message = "Berhasil memproses {$totalProcessed} perusahaan";
        if ($totalSkipped > 0)
            $message .= ", {$totalSkipped} dilewati karena sudah ada di grup";

        return $this->successResponse(
            ['processed' => $totalProcessed, 'skipped' => $totalSkipped],
            $message
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/company-group/bulk-remove-companies",
     *     summary="Bulk remove companies from groups",
     *     tags={"Company Groups"},
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
     *         description="Bulk removal berhasil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Berhasil menghapus 3 perusahaan dari grup, 1 tidak ditemukan"),
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
    public function bulkRemoveCompanies(Request $request)
    {
        $removals = $request->input('removals', []);
        if (empty($removals) || !is_array($removals)) {
            return $this->errorResponse('Data removals tidak valid', 400);
        }

        [$totalRemoved, $totalNotFound] = DB::transaction(function () use ($removals) {
            $totalRemoved = 0;
            $totalNotFound = 0;

            foreach ($removals as $removal) {
                $groupId = $removal['group_id'] ?? null;
                $leadsIds = $removal['leads_ids'] ?? [];

                if (empty($groupId) || empty($leadsIds))
                    continue;

                $deleted = PerusahaanGroupDetail::where('group_id', $groupId)
                    ->whereIn('leads_id', $leadsIds)
                    ->delete();

                $totalRemoved += $deleted;
                $totalNotFound += (count($leadsIds) - $deleted);

                if ($deleted > 0) {
                    $this->updateGroupCompanyCount($groupId);
                }
            }

            return [$totalRemoved, $totalNotFound];
        });

        $message = "Berhasil menghapus {$totalRemoved} perusahaan dari grup";
        if ($totalNotFound > 0)
            $message .= ", {$totalNotFound} tidak ditemukan";

        return $this->successResponse(
            ['removed' => $totalRemoved, 'not_found' => $totalNotFound],
            $message
        );
    }

    // ==================== DASHBOARD & UTILITIES ====================

    /**
     * @OA\Get(
     *     path="/api/company-group/statistics",
     *     summary="Get company groups statistics",
     *     tags={"Company Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistik berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Statistik grup perusahaan berhasil diambil"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_groups", type="integer", example=15),
     *                 @OA\Property(property="total_companies_in_groups", type="integer", example=245),
     *                 @OA\Property(property="companies_without_group", type="integer", example=78),
     *                 @OA\Property(property="largest_group", type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="nama_grup", type="string", example="Grup Enterprise"),
     *                     @OA\Property(property="jumlah_perusahaan", type="integer", example=45),
     *                     @OA\Property(property="created_by", type="string", example="Admin User"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-10 08:15:00")
     *                 ),
     *                 @OA\Property(property="recent_groups", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=15),
     *                         @OA\Property(property="nama_grup", type="string", example="Grup Baru"),
     *                         @OA\Property(property="jumlah_perusahaan", type="integer", example=3),
     *                         @OA\Property(property="created_by", type="string", example="John Doe"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15 14:20:00")
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
        $totalGroups = PerusahaanGroup::count();
        $totalCompaniesInGroups = PerusahaanGroupDetail::count();

        $companiesInGroups = PerusahaanGroupDetail::pluck('leads_id');
        $companiesWithoutGroup = Leads::whereNull('deleted_at')
            ->whereNotIn('id', $companiesInGroups)
            ->count();

        $largestGroup = PerusahaanGroup::orderBy('jumlah_perusahaan', 'desc')->first();
        $recentGroups = PerusahaanGroup::orderBy('created_at', 'desc')->limit(5)->get();

        return $this->successResponse([
            'total_groups' => $totalGroups,
            'total_companies_in_groups' => $totalCompaniesInGroups,
            'companies_without_group' => $companiesWithoutGroup,
            'largest_group' => $largestGroup,
            'recent_groups' => $recentGroups
        ], 'Statistik grup perusahaan berhasil diambil');
    }

    /**
     * @OA\Get(
     *     path="/api/company-group/recommendations",
     *     summary="Filter companies for grouping recommendation",
     *     tags={"Company Groups"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="keyword",
     *         in="query",
     *         description="Keyword untuk filter nama perusahaan",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data rekomendasi berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Rekomendasi perusahaan berhasil diambil"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT Contoh Rekomendasi"),
     *                     @OA\Property(property="kota", type="string", example="Jakarta"),
     *                     @OA\Property(property="jenis_perusahaan", type="string", example="Retail")
     *                 )
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
    public function getRecommendations(Request $request)
    {
        $keyword = $request->input('keyword');

        $companies = Leads::query()
            ->select('id', 'nama_perusahaan', 'kota', 'jenis_perusahaan_id')
            ->whereDoesntHave('groupDetails')
            ->whereNull('deleted_at')
            ->with('jenisPerusahaan:id,nama')
            ->when($keyword, function ($query, $keyword) {
                $query->where('nama_perusahaan', 'like', '%' . $keyword . '%');
            })
            ->orderBy('nama_perusahaan')
            ->get()
            ->map(function ($lead) {
                return [
                    'id' => $lead->id,
                    'nama_perusahaan' => $lead->nama_perusahaan,
                    'kota' => $lead->kota,
                    'jenis_perusahaan' => optional($lead->jenisPerusahaan)->nama,
                ];
            });

        return $this->successResponse($companies, 'Rekomendasi perusahaan berhasil diambil');
    }
}