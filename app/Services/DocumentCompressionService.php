<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Exception;
use Intervention\Image\Image;

/**
 * Service untuk kompresi dokumen
 * Menangani kompresi gambar (jpg, jpeg, png) dan PDF
 * Target ukuran: maksimal 100KB
 */
class DocumentCompressionService
{
    /**
     * Target ukuran file dalam bytes (100KB)
     */
    private const TARGET_SIZE = 100 * 1024; // 100KB

    /**
     * Ukuran minimum yang diizinkan (20KB)
     * Jika hasil kompresi lebih kecil dari ini, gunakan file original
     */
    private const MIN_SIZE = 20 * 1024; // 20KB

    /**
     * Quality awal untuk kompresi
     */
    private const INITIAL_QUALITY = 85;

    /**
     * Quality minimum untuk kompresi
     */
    private const MIN_QUALITY = 30;

    /**
     * Maksimal iterasi kompresi
     */
    private const MAX_ITERATIONS = 10;

    /**
     * Kompresi file berdasarkan tipe
     *
     * @param UploadedFile $file
     * @return string Binary content dari file yang sudah dikompres
     * @throws Exception
     */
    public function compress(UploadedFile $file): string
    {
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        Log::info('Starting compression', [
            'original_size' => $fileSize,
            'mime_type' => $mimeType,
            'target_size' => self::TARGET_SIZE
        ]);

        // Jika file sudah lebih kecil dari target, return original
        if ($fileSize <= self::TARGET_SIZE) {
            Log::info('File already smaller than target size, skipping compression');
            return file_get_contents($file->getRealPath());
        }

        // Kompresi berdasarkan tipe file
        try {
            // if (in_array($mimeType, ['image/jpeg', 'image/jpg', 'image/png'])) {
            //     return $this->compressImage($file);
            // }
             if ($mimeType === 'application/pdf') {
                return $this->compressPdf($file);
            } else {
                // Untuk tipe file lain, coba kompresi sederhana
                return $this->compressGeneric($file);
            }
        } catch (Exception $e) {
            Log::error('Compression failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName()
            ]);

            // Jika kompresi gagal, return file original
            return file_get_contents($file->getRealPath());
        }
    }

    // /**
    //  * Kompresi gambar (JPEG, PNG)
    //  *
    //  * @param UploadedFile $file
    //  * @return string
    //  */
    // private function compressImage(UploadedFile $file): string
    // {
    //     $image = Image::make($file->getRealPath());
    //     $originalWidth = $image->width();
    //     $originalHeight = $image->height();

    //     $quality = self::INITIAL_QUALITY;
    //     $scale = 1.0;
    //     $compressed = null;
    //     $iteration = 0;

    //     Log::info('Image compression started', [
    //         'original_dimensions' => "{$originalWidth}x{$originalHeight}",
    //         'initial_quality' => $quality
    //     ]);

    //     while ($iteration < self::MAX_ITERATIONS) {
    //         $iteration++;

    //         // Resize jika diperlukan
    //         if ($scale < 1.0) {
    //             $newWidth = (int) ($originalWidth * $scale);
    //             $newHeight = (int) ($originalHeight * $scale);
    //             $tempImage = clone $image;
    //             $tempImage->resize($newWidth, $newHeight, function ($constraint) {
    //                 $constraint->aspectRatio();
    //                 $constraint->upsize();
    //             });
    //         } else {
    //             $tempImage = clone $image;
    //         }

    //         // Encode dengan quality tertentu
    //         if ($file->getMimeType() === 'image/png') {
    //             // PNG: konversi ke JPEG untuk kompresi lebih baik
    //             $compressed = $tempImage->encode('jpg', $quality)->getEncoded();
    //         } else {
    //             $compressed = $tempImage->encode('jpg', $quality)->getEncoded();
    //         }

    //         $compressedSize = strlen($compressed);

    //         Log::debug("Compression iteration {$iteration}", [
    //             'quality' => $quality,
    //             'scale' => $scale,
    //             'size' => $compressedSize,
    //             'target' => self::TARGET_SIZE
    //         ]);

    //         // Jika ukuran sudah sesuai target
    //         if ($compressedSize <= self::TARGET_SIZE && $compressedSize >= self::MIN_SIZE) {
    //             Log::info('Compression successful', [
    //                 'iterations' => $iteration,
    //                 'final_quality' => $quality,
    //                 'final_scale' => $scale,
    //                 'final_size' => $compressedSize,
    //                 'reduction_percentage' => round((1 - $compressedSize / $file->getSize()) * 100, 2)
    //             ]);
    //             break;
    //         }

    //         // Jika masih terlalu besar, reduce quality atau scale
    //         if ($compressedSize > self::TARGET_SIZE) {
    //             if ($quality > self::MIN_QUALITY) {
    //                 $quality -= 10;
    //             } else {
    //                 $scale -= 0.1;
    //             }
    //         } else {
    //             // Jika terlalu kecil, tambah sedikit quality
    //             $quality = min($quality + 5, self::INITIAL_QUALITY);
    //         }

    //         // Jika scale sudah terlalu kecil, break
    //         if ($scale < 0.3) {
    //             Log::warning('Scale too small, stopping compression', [
    //                 'final_scale' => $scale
    //             ]);
    //             break;
    //         }
    //     }

    //     // Cleanup
    //     $image->destroy();

    //     return $compressed ?? file_get_contents($file->getRealPath());
    // }

    /**
     * Kompresi PDF
     * Note: Memerlukan Ghostscript terinstall di server
     * Jika Ghostscript tidak tersedia, akan return file original
     *
     * @param UploadedFile $file
     * @return string
     */
    private function compressPdf(UploadedFile $file): string
    {
        // Check jika Ghostscript tersedia
        $gsPath = $this->findGhostscript();

        if (!$gsPath) {
            Log::warning('Ghostscript not found, PDF compression skipped');
            return file_get_contents($file->getRealPath());
        }

        $inputPath = $file->getRealPath();
        $outputPath = sys_get_temp_dir() . '/compressed_' . uniqid() . '.pdf';

        // Ghostscript command untuk kompresi PDF
        $command = sprintf(
            '%s -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook ' .
            '-dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s',
            escapeshellarg($gsPath),
            escapeshellarg($outputPath),
            escapeshellarg($inputPath)
        );

        exec($command, $output, $returnVar);

        if ($returnVar === 0 && file_exists($outputPath)) {
            $compressedSize = filesize($outputPath);
            $originalSize = $file->getSize();

            Log::info('PDF compression completed', [
                'original_size' => $originalSize,
                'compressed_size' => $compressedSize,
                'reduction_percentage' => round((1 - $compressedSize / $originalSize) * 100, 2)
            ]);

            // Jika hasil kompresi lebih besar dari target, coba kompresi lebih agresif
            if ($compressedSize > self::TARGET_SIZE) {
                $outputPath2 = sys_get_temp_dir() . '/compressed2_' . uniqid() . '.pdf';
                $command2 = sprintf(
                    '%s -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/screen ' .
                    '-dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s',
                    escapeshellarg($gsPath),
                    escapeshellarg($outputPath2),
                    escapeshellarg($inputPath)
                );

                exec($command2, $output2, $returnVar2);

                if ($returnVar2 === 0 && file_exists($outputPath2)) {
                    $compressed = file_get_contents($outputPath2);
                    @unlink($outputPath);
                    @unlink($outputPath2);
                    return $compressed;
                }
            }

            $compressed = file_get_contents($outputPath);
            @unlink($outputPath);
            return $compressed;
        }

        Log::warning('PDF compression failed, using original file');
        return file_get_contents($file->getRealPath());
    }

    /**
     * Kompresi generic untuk file lain (doc, docx, dll)
     * Hanya mencoba compress dengan gzip
     *
     * @param UploadedFile $file
     * @return string
     */
    private function compressGeneric(UploadedFile $file): string
    {
        $content = file_get_contents($file->getRealPath());
        $compressed = gzcompress($content, 9);

        $compressedSize = strlen($compressed);
        $originalSize = strlen($content);

        if ($compressedSize < $originalSize && $compressedSize <= self::TARGET_SIZE) {
            Log::info('Generic compression successful', [
                'original_size' => $originalSize,
                'compressed_size' => $compressedSize,
                'reduction_percentage' => round((1 - $compressedSize / $originalSize) * 100, 2)
            ]);
            return $compressed;
        }

        Log::info('Generic compression skipped, using original file');
        return $content;
    }

    /**
     * Cari path Ghostscript di sistem
     *
     * @return string|null
     */
    private function findGhostscript(): ?string
    {
        $possiblePaths = [
            '/usr/bin/gs',
            '/usr/local/bin/gs',
            'C:\\Program Files\\gs\\gs9.56.1\\bin\\gswin64c.exe', // Windows
            'C:\\Program Files (x86)\\gs\\gs9.56.1\\bin\\gswin32c.exe', // Windows 32bit
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Try to find via which command (Linux/Mac)
        exec('which gs 2>/dev/null', $output, $returnVar);
        if ($returnVar === 0 && !empty($output[0])) {
            return $output[0];
        }

        return null;
    }

    /**
     * Validasi apakah file bisa dikompres
     *
     * @param UploadedFile $file
     * @return bool
     */
    public function canCompress(UploadedFile $file): bool
    {
        $allowedMimes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        return in_array($file->getMimeType(), $allowedMimes);
    }

    /**
     * Get info tentang hasil kompresi tanpa menyimpan
     *
     * @param UploadedFile $file
     * @return array
     */
    public function getCompressionInfo(UploadedFile $file): array
    {
        $originalSize = $file->getSize();

        try {
            $compressed = $this->compress($file);
            $compressedSize = strlen($compressed);

            return [
                'success' => true,
                'original_size' => $originalSize,
                'compressed_size' => $compressedSize,
                'reduction_bytes' => $originalSize - $compressedSize,
                'reduction_percentage' => round((1 - $compressedSize / $originalSize) * 100, 2),
                'meets_target' => $compressedSize <= self::TARGET_SIZE
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'original_size' => $originalSize
            ];
        }
    }
}