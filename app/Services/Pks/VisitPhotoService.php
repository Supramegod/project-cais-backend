<?php

namespace App\Services\Pks;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class VisitPhotoService
{
    public function uploadAndCompress(UploadedFile $file): array
    {
        try {
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = 'jpg';
            $fileName = $originalName.date('YmdHis').rand(10000, 99999).'.'.$extension;

            // Kompres via Intervention v3
            $manager = new ImageManager(new Driver);
            $image = $manager->read($file);
            $image->scaleDown(width: 1600);
            $encoded = (string) $image->toJpeg(70);

            // Upload ke S3/MinIO
            $disk = Storage::disk('visit-photo');
            $success = $disk->put($fileName, $encoded, 'public');

            if (! $success) {
                Log::error('VisitPhoto: Gagal upload ke S3', [
                    'file' => $fileName,
                    'disk' => config('filesystems.disks.visit-photo'),
                ]);
                throw new \RuntimeException('Gagal mengupload foto ke storage.');
            }

            $url = $disk->url($fileName);

            Log::info('VisitPhoto: Upload sukses', ['file' => $fileName, 'url' => $url]);

            return [
                'url_file' => $url,
                'nama_file' => $fileName,
            ];
        } catch (\Throwable $e) {
            Log::error('VisitPhoto: '.$e->getMessage(), [
                'file' => $file->getClientOriginalName(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function deletePhoto(string $fileName): void
    {
        if (Storage::disk('visit-photo')->exists($fileName)) {
            Storage::disk('visit-photo')->delete($fileName);
        }
    }
}
