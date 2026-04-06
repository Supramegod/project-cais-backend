<?php

namespace App\Http\Controllers;

use App\Models\SystemAnnouncement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="System Announcements",
 *     description="API Endpoints untuk Log Update Aplikasi dan Pengumuman Sistem"
 * )
 */
class SystemAnnouncementController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/system-announcements/list",
     *     summary="Get list of announcements",
     *     description="Mendapatkan daftar pengumuman sistem/update aplikasi",
     *     tags={"System Announcements"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         required=false,
     *         description="Filter berdasarkan kategori (Fitur Baru, Bug Fix, Aturan, Update)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function list(Request $request)
    {
        try {
            $query = SystemAnnouncement::query();

            // Optional filter by category
            if ($request->has('category') && $request->category !== 'Semua' && $request->category !== '') {
                $query->where('category', $request->category);
            }

            // Order by latest release date
            $data = $query->orderBy('release_date', 'desc')->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/system-announcements/add",
     *     summary="Create a new announcement",
     *     description="Membuat pengumuman sistem baru",
     *     tags={"System Announcements"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"category", "title"},
     *             @OA\Property(property="category", type="string", example="Fitur Baru", enum={"Fitur Baru", "Bug Fix", "Aturan", "Update"}),
     *             @OA\Property(property="title", type="string", example="Fitur Export Data ke Excel"),
     *             @OA\Property(property="version", type="string", example="v2.5.0"),
     *             @OA\Property(property="release_date", type="string", format="date", example="2026-03-25"),
     *             @OA\Property(property="description", type="string", example="Sekarang Anda dapat mengekspor semua data laporan ke format Excel dengan mudah."),
     *             @OA\Property(property="details", type="string", example="<ul><li>Mendukung export untuk semua jenis laporan</li></ul>"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Announcement created successfully"
     *     )
     * )
     */
    public function add(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'category' => 'required|in:Fitur Baru,Bug Fix,Aturan,Update',
                'title' => 'required|string|max:255',
                'version' => 'nullable|string|max:50',
                'release_date' => 'nullable|date',
                'description' => 'nullable|string',
                'details' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $announcement = SystemAnnouncement::create([
                'category' => $request->category,
                'title' => $request->title,
                'version' => $request->version,
                'release_date' => $request->release_date ?? now()->toDateString(),
                'description' => $request->description,
                'details' => $request->details,
                'is_active' => $request->has('is_active') ? $request->is_active : true,
                'created_by' => Auth::check() ? Auth::id() : null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Announcement berhasil dibuat',
                'data' => $announcement
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/system-announcements/view/{id}",
     *     summary="Get announcement by ID",
     *     description="Mendapatkan detail pengumuman berdasarkan ID",
     *     tags={"System Announcements"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function view($id)
    {
        try {
            $announcement = SystemAnnouncement::find($id);

            if (!$announcement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Announcement tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $announcement
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/system-announcements/update/{id}",
     *     summary="Update announcement",
     *     description="Mengupdate data pengumuman yang sudah ada",
     *     tags={"System Announcements"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="category", type="string", enum={"Fitur Baru", "Bug Fix", "Aturan", "Update"}),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Announcement updated successfully")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $announcement = SystemAnnouncement::find($id);

            if (!$announcement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Announcement tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'category' => 'required|in:Fitur Baru,Bug Fix,Aturan,Update',
                'title' => 'required|string|max:255',
                'version' => 'nullable|string|max:50',
                'release_date' => 'nullable|date',
                'description' => 'nullable|string',
                'details' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $announcement->update([
                'category' => $request->category,
                'title' => $request->title,
                'version' => $request->version,
                'release_date' => $request->release_date,
                'description' => $request->description,
                'details' => $request->details,
                'is_active' => $request->has('is_active') ? $request->is_active : $announcement->is_active
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Announcement berhasil diupdate',
                'data' => $announcement
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/system-announcements/delete/{id}",
     *     summary="Delete announcement",
     *     description="Menghapus pengumuman (soft delete)",
     *     tags={"System Announcements"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Announcement deleted successfully")
     * )
     */
    public function delete($id)
    {
        try {
            $announcement = SystemAnnouncement::find($id);

            if (!$announcement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Announcement tidak ditemukan'
                ], 404);
            }

            $announcement->delete();

            return response()->json([
                'success' => true,
                'message' => 'Announcement berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
