<?php

namespace App\Http\Controllers;

use App\Models\SystemAnnouncement;
use App\Models\SystemAnnouncementFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="System Announcements V2",
 *     description="API v2 — Announcement dengan rich content, image upload, dan file attachment"
 * )
 */
class SystemAnnouncementV2Controller extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v2/system-announcements/list",
     *     summary="List announcements (paginated)",
     *     tags={"System Announcements V2"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="category", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function list(Request $request)
    {
        try {
            $query = SystemAnnouncement::with('files');

            if ($request->filled('category') && $request->category !== 'Semua') {
                $query->where('category', $request->category);
            }

            if ($request->has('is_active') && $request->is_active !== '') {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->filled('search')) {
                $keyword = '%' . $request->search . '%';
                $query->where(function ($q) use ($keyword) {
                    $q->where('title', 'like', $keyword)
                      ->orWhere('description', 'like', $keyword);
                });
            }

            $perPage = $request->input('per_page', 15);
            $data = $query->orderBy('release_date', 'desc')
                          ->orderBy('created_at', 'desc')
                          ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v2/system-announcements/view/{id}",
     *     summary="Get announcement detail with files",
     *     tags={"System Announcements V2"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function view($id)
    {
        try {
            $announcement = SystemAnnouncement::with('files')->find($id);

            if (!$announcement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Announcement tidak ditemukan',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $announcement,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v2/system-announcements/upload-image",
     *     summary="Upload inline image untuk editor",
     *     description="Upload gambar dan dapatkan URL untuk disisipkan ke konten HTML editor",
     *     tags={"System Announcements V2"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="image", type="string", format="binary", description="File gambar (jpg,jpeg,png,gif,webp), max 5MB")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="URL gambar",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="url", type="string", example="http://example.com/document/announcement-images/image20260512123456.jpg")
     *         )
     *     )
     * )
     */
    public function uploadImage(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'image' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $file     = $request->file('image');
            $fileName = $this->generateFileName($file);
            Storage::disk('announcement-images')->put($fileName, file_get_contents($file));

            return response()->json([
                'success' => true,
                'url'     => url('document/announcement-images/' . $fileName),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v2/system-announcements/add",
     *     summary="Buat announcement baru dengan file attachment",
     *     tags={"System Announcements V2"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"category","title"},
     *                 @OA\Property(property="category", type="string", enum={"Fitur Baru","Bug Fix","Aturan","Update"}),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="version", type="string"),
     *                 @OA\Property(property="release_date", type="string", format="date"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="details", type="string", description="HTML content dari rich editor"),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="attachments[]", type="array", @OA\Items(type="string", format="binary"), description="File attachment (pdf,doc,docx,jpg,jpeg,png), max 10MB each")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Announcement created successfully")
     * )
     */
    public function add(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'category'       => 'required|in:Fitur Baru,Bug Fix,Aturan,Update',
                'title'          => 'required|string|max:255',
                'version'        => 'nullable|string|max:50',
                'release_date'   => 'nullable|date',
                'description'    => 'nullable|string',
                'details'        => 'nullable|string',
                'is_active'      => 'boolean',
                'attachments'    => 'nullable|array',
                'attachments.*'  => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $announcement = SystemAnnouncement::create([
                'category'     => $request->category,
                'title'        => $request->title,
                'version'      => $request->version,
                'release_date' => $request->release_date ?? now()->toDateString(),
                'description'  => $request->description,
                'details'      => $request->details,
                'is_active'    => $request->has('is_active') ? $request->is_active : true,
                'created_by'   => Auth::check() ? Auth::id() : null,
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $this->storeAttachment($announcement->id, $file);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Announcement berhasil dibuat',
                'data'    => $announcement->load('files'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v2/system-announcements/update/{id}",
     *     summary="Update announcement dan tambah attachment baru",
     *     tags={"System Announcements V2"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"category","title"},
     *                 @OA\Property(property="category", type="string", enum={"Fitur Baru","Bug Fix","Aturan","Update"}),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="version", type="string"),
     *                 @OA\Property(property="release_date", type="string", format="date"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="details", type="string"),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="attachments[]", type="array", @OA\Items(type="string", format="binary"))
     *             )
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
                    'message' => 'Announcement tidak ditemukan',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'category'       => 'required|in:Fitur Baru,Bug Fix,Aturan,Update',
                'title'          => 'required|string|max:255',
                'version'        => 'nullable|string|max:50',
                'release_date'   => 'nullable|date',
                'description'    => 'nullable|string',
                'details'        => 'nullable|string',
                'is_active'      => 'boolean',
                'attachments'    => 'nullable|array',
                'attachments.*'  => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $announcement->update([
                'category'     => $request->category,
                'title'        => $request->title,
                'version'      => $request->version,
                'release_date' => $request->release_date ?? $announcement->release_date,
                'description'  => $request->description,
                'details'      => $request->details,
                'is_active'    => $request->has('is_active') ? $request->is_active : $announcement->is_active,
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $this->storeAttachment($announcement->id, $file);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Announcement berhasil diupdate',
                'data'    => $announcement->fresh()->load('files'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v2/system-announcements/delete/{id}",
     *     summary="Hapus announcement beserta semua file-nya",
     *     tags={"System Announcements V2"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Announcement deleted successfully")
     * )
     */
    public function delete($id)
    {
        try {
            $announcement = SystemAnnouncement::with('files')->find($id);

            if (!$announcement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Announcement tidak ditemukan',
                ], 404);
            }

            foreach ($announcement->files as $file) {
                $this->deleteFileFromDisk($file->url_file);
                $file->delete();
            }

            $announcement->delete();

            return response()->json([
                'success' => true,
                'message' => 'Announcement berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v2/system-announcements/delete-file/{fileId}",
     *     summary="Hapus satu file attachment",
     *     tags={"System Announcements V2"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="fileId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="File deleted successfully")
     * )
     */
    public function deleteFile($fileId)
    {
        try {
            $file = SystemAnnouncementFile::find($fileId);

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak ditemukan',
                ], 404);
            }

            $this->deleteFileFromDisk($file->url_file);
            $file->delete();

            return response()->json([
                'success' => true,
                'message' => 'File berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function storeAttachment(int $announcementId, $file): void
    {
        $fileName = $this->generateFileName($file);
        Storage::disk('announcement-files')->put($fileName, file_get_contents($file));

        SystemAnnouncementFile::create([
            'system_announcement_id' => $announcementId,
            'nama_file'              => $file->getClientOriginalName(),
            'url_file'               => url('document/announcement-files/' . $fileName),
            'mime_type'              => $file->getMimeType(),
            'size'                   => $file->getSize(),
            'created_by'             => Auth::check() ? Auth::id() : null,
        ]);
    }

    private function generateFileName($file): string
    {
        $original  = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext       = $file->getClientOriginalExtension();
        return $original . now()->format('YmdHis') . rand(10000, 99999) . '.' . $ext;
    }

    private function deleteFileFromDisk(string $fileUrl): void
    {
        // Extract filename from URL and delete from disk
        $fileName = basename($fileUrl);
        if (Storage::disk('announcement-files')->exists($fileName)) {
            Storage::disk('announcement-files')->delete($fileName);
        }
        if (Storage::disk('announcement-images')->exists($fileName)) {
            Storage::disk('announcement-images')->delete($fileName);
        }
    }
}
