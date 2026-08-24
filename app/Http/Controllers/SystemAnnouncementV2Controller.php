<?php

namespace App\Http\Controllers;

use App\Http\Requests\SystemAnnouncement\SystemAnnouncementImageRequest;
use App\Http\Requests\SystemAnnouncement\SystemAnnouncementV2Request;
use App\Models\SystemAnnouncement;
use App\Models\SystemAnnouncementFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

        return $this->successResponse($data);
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
        $announcement = SystemAnnouncement::with('files')->find($id);

        if (!$announcement) {
            return $this->notFoundResponse('Announcement tidak ditemukan');
        }

        return $this->successResponse($announcement);
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
    public function uploadImage(SystemAnnouncementImageRequest $request)
    {
        $file     = $request->file('image');
        $fileName = $this->generateFileName($file);
        Storage::disk('announcement-images')->put($fileName, file_get_contents($file));

        return response()->json([
            'success' => true,
            'url'     => url('document/announcement-images/' . $fileName),
        ]);
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
    public function add(SystemAnnouncementV2Request $request)
    {
        $announcement = DB::transaction(function () use ($request) {
            $announcement = SystemAnnouncement::create([
                'category'     => $request->category,
                'title'        => $request->title,
                'version'      => $request->version,
                'release_date' => $request->release_date ?? now()->toDateString(),
                'description'  => $request->description,
                'details'      => $request->details,
                'is_active'    => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : true,
                'created_by'   => Auth::check() ? Auth::id() : null,
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $this->storeAttachment($announcement->id, $file);
                }
            }

            return $announcement;
        });

        return $this->successResponse($announcement->load('files'), 'Announcement berhasil dibuat', 201);
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
    public function update(SystemAnnouncementV2Request $request, $id)
    {
        $announcement = SystemAnnouncement::find($id);

        if (!$announcement) {
            return $this->notFoundResponse('Announcement tidak ditemukan');
        }

        DB::transaction(function () use ($request, $announcement) {
            $announcement->update([
                'category'     => $request->category,
                'title'        => $request->title,
                'version'      => $request->version,
                'release_date' => $request->release_date ?? $announcement->release_date,
                'description'  => $request->description,
                'details'      => $request->details,
                'is_active'    => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : $announcement->is_active,
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $this->storeAttachment($announcement->id, $file);
                }
            }
        });

        return $this->successResponse($announcement->fresh()->load('files'), 'Announcement berhasil diupdate');
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
        $announcement = SystemAnnouncement::with('files')->find($id);

        if (!$announcement) {
            return $this->notFoundResponse('Announcement tidak ditemukan');
        }

        DB::transaction(function () use ($announcement) {
            foreach ($announcement->files as $file) {
                $this->deleteFileFromDisk($file->url_file);
                $file->delete();
            }

            $announcement->delete();
        });

        return $this->messageResponse('Announcement berhasil dihapus');
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
        $file = SystemAnnouncementFile::find($fileId);

        if (!$file) {
            return $this->notFoundResponse('File tidak ditemukan');
        }

        $this->deleteFileFromDisk($file->url_file);
        $file->delete();

        return $this->messageResponse('File berhasil dihapus');
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
