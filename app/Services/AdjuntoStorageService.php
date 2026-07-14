<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdjuntoStorageService
{
    /**
     * Upload an attachment to S3.
     * 
     * @param \Illuminate\Http\UploadedFile $file
     * @param int $ordenId
     * @return array [path, name, size, mime_type]
     */
    public static function uploadFile($file, int $ordenId): array
    {
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        
        // Generate unique UUID name to avoid collisions, spaces, or invalid chars
        $uuid = (string) Str::uuid();
        $path = "ordenes/{$ordenId}/adjuntos/{$uuid}.{$extension}";

        try {
            // Write to S3 using read stream to support large files efficiently
            $stream = fopen($file->getRealPath(), 'r');
            if ($stream === false) {
                throw new \Exception("Could not open read stream for uploaded file.");
            }

            $success = Storage::disk('s3')->writeStream($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (!$success) {
                throw new \Exception("S3 driver returned upload failure.");
            }

            return [
                'path' => $path,
                'name' => $originalName,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ];
        } catch (\Exception $e) {
            // Secure logging: only log safe details, do not log credentials/endpoints/signed URLs
            Log::error("Error uploading file to S3. Order ID: {$ordenId}, File Name: {$originalName}, Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upload multiple attachments to S3.
     * 
     * @param array $files
     * @param int $ordenId
     * @return array Array of upload metadata arrays
     */
    public static function uploadMultipleFiles(array $files, int $ordenId): array
    {
        $uploaded = [];
        foreach ($files as $file) {
            if ($file) {
                $uploaded[] = self::uploadFile($file, $ordenId);
            }
        }
        return $uploaded;
    }

    /**
     * Delete file from storage (S3 or local if legacy path).
     * 
     * @param string|null $path
     * @return bool
     */
    public static function deleteFile(?string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        try {
            if (self::isLocalPath($path)) {
                if (Storage::disk('public')->exists($path)) {
                    return Storage::disk('public')->delete($path);
                }
                return false;
            }

            if (Storage::disk('s3')->exists($path)) {
                return Storage::disk('s3')->delete($path);
            }
            return false;
        } catch (\Exception $e) {
            Log::error("Error deleting file from storage. Path: {$path}, Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get stream for reading/downloading a file.
     * Supports both S3 and legacy local paths.
     * 
     * @param string $path
     * @return resource|null
     */
    public static function getStream(string $path)
    {
        try {
            if (self::isLocalPath($path)) {
                if (Storage::disk('public')->exists($path)) {
                    return Storage::disk('public')->readStream($path);
                }
                return null;
            }

            if (Storage::disk('s3')->exists($path)) {
                return Storage::disk('s3')->readStream($path);
            }
            return null;
        } catch (\Exception $e) {
            Log::error("Error reading file stream. Path: {$path}, Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get temporary signed URL for a file (or local URL for legacy paths).
     * 
     * @param string|null $path
     * @param int $expiresIn Expiration time in seconds
     * @return string|null
     */
    public static function getSignedUrl(?string $path, int $expiresIn = 300): ?string
    {
        if (empty($path)) {
            return null;
        }

        try {
            if (self::isLocalPath($path)) {
                return asset('storage/' . $path);
            }

            return Storage::disk('s3')->temporaryUrl($path, now()->addSeconds($expiresIn));
        } catch (\Exception $e) {
            Log::error("Error generating signed URL. Path: {$path}, Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if a file exists (S3 or local if legacy path).
     * 
     * @param string|null $path
     * @return bool
     */
    public static function exists(?string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        try {
            if (self::isLocalPath($path)) {
                return Storage::disk('public')->exists($path);
            }

            return Storage::disk('s3')->exists($path);
        } catch (\Exception $e) {
            Log::error("Error checking file existence. Path: {$path}, Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper to detect if a path is a local/legacy path.
     * 
     * @param string $path
     * @return bool
     */
    public static function isLocalPath(string $path): bool
    {
        return str_starts_with($path, 'briefs/') || 
               str_starts_with($path, 'reprocesos_adjuntos/') || 
               str_starts_with($path, 'fallback/');
    }
}
