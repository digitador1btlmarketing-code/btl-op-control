<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SupabaseStorageService
{
    protected static function useFallback(): bool
    {
        $url = env('SUPABASE_URL');
        $key = env('SUPABASE_KEY');

        return app()->runningUnitTests() || 
               empty($url) || 
               empty($key) || 
               str_contains($url, 'your-project') || 
               str_contains($key, 'your-service-role');
    }

    protected static function getBucket(): string
    {
        return env('SUPABASE_STORAGE_BUCKET', 'ordenes-produccion');
    }

    protected static function getSupabaseUrl(): string
    {
        return rtrim(env('SUPABASE_URL'), '/');
    }

    protected static function getSupabaseKey(): string
    {
        return env('SUPABASE_KEY');
    }

    /**
     * Upload file to Supabase Storage.
     * Fallbacks to local storage if not configured.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $opNumber
     * @param string $folderType ('archivos-iniciales', 'avances', 'archivos-finales')
     * @return array [path, name, size, mime_type, url]
     */
    public static function uploadFile($file, string $opNumber, string $folderType = 'archivos-iniciales'): array
    {
        $originalName = $file->getClientOriginalName();
        $cleanOp = preg_replace('/[^a-zA-Z0-9_-]/', '', $opNumber);
        $uniqueName = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $originalName);
        $path = "{$cleanOp}/{$folderType}/{$uniqueName}";

        if (self::useFallback()) {
            // Local fallback
            $localPath = $file->storeAs("fallback/{$cleanOp}/{$folderType}", $uniqueName, 'public');
            return [
                'path' => $localPath,
                'name' => $originalName,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'url' => asset('storage/' . $localPath),
            ];
        }

        try {
            $bucket = self::getBucket();
            $url = self::getSupabaseUrl() . "/storage/v1/object/{$bucket}/{$path}";
            $key = self::getSupabaseKey();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $key,
                'apikey' => $key,
                'Content-Type' => $file->getMimeType(),
            ])->withBody(file_get_contents($file->getRealPath()), $file->getMimeType())
              ->post($url);

            if ($response->failed()) {
                throw new \Exception("Supabase upload failed: " . $response->body());
            }

            // Generate signed URL as public URL fallback
            $signedUrl = self::getSignedUrl($path);

            return [
                'path' => $path,
                'name' => $originalName,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'url' => $signedUrl,
            ];
        } catch (\Exception $e) {
            Log::error("Error uploading file to Supabase Storage: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete file from Supabase Storage.
     *
     * @param string $path
     * @return bool
     */
    public static function deleteFile(?string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        if (self::useFallback() || self::isLocalPath($path)) {
            // Delete locally
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->delete($path);
            }
            return false;
        }

        try {
            $bucket = self::getBucket();
            $url = self::getSupabaseUrl() . "/storage/v1/object/{$bucket}/{$path}";
            $key = self::getSupabaseKey();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $key,
                'apikey' => $key,
            ])->delete($url);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Error deleting file from Supabase Storage: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get temporary signed URL for a file.
     *
     * @param string $path
     * @param int $expiresIn
     * @return string|null
     */
    public static function getSignedUrl(?string $path, int $expiresIn = 3600): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (self::useFallback() || self::isLocalPath($path)) {
            return asset('storage/' . $path);
        }

        try {
            $bucket = self::getBucket();
            $url = self::getSupabaseUrl() . "/storage/v1/object/sign/{$bucket}/{$path}";
            $key = self::getSupabaseKey();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $key,
                'apikey' => $key,
                'Content-Type' => 'application/json',
            ])->post($url, [
                'expiresIn' => $expiresIn,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['signedURL'] ?? $data['signedUrl'] ?? null;
            }

            Log::error("Failed to generate signed URL from Supabase: " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("Error generating signed URL from Supabase: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Download binary data of a file.
     *
     * @param string $path
     * @return string|null
     */
    public static function downloadFile(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (self::useFallback() || self::isLocalPath($path)) {
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->get($path);
            }
            return null;
        }

        try {
            $bucket = self::getBucket();
            $url = self::getSupabaseUrl() . "/storage/v1/object/{$bucket}/{$path}";
            $key = self::getSupabaseKey();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $key,
                'apikey' => $key,
            ])->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Error downloading file from Supabase Storage: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if a file exists on Supabase Storage.
     *
     * @param string $path
     * @return bool
     */
    public static function exists(?string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        if (self::useFallback() || self::isLocalPath($path)) {
            return Storage::disk('public')->exists($path);
        }

        try {
            $bucket = self::getBucket();
            $url = self::getSupabaseUrl() . "/storage/v1/object/{$bucket}/{$path}";
            $key = self::getSupabaseKey();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $key,
                'apikey' => $key,
            ])->head($url);

            return $response->status() === 200;
        } catch (\Exception $e) {
            Log::error("Error checking file existence on Supabase Storage: " . $e->getMessage());
            return false;
        }
    }

    /**
     * List objects in the bucket.
     *
     * @param string $prefix
     * @return array|null
     */
    public static function listObjects(string $prefix = ''): ?array
    {
        if (self::useFallback()) {
            $directory = "fallback/" . $prefix;
            if (Storage::disk('public')->exists($directory)) {
                $files = Storage::disk('public')->files($directory);
                $directories = Storage::disk('public')->directories($directory);
                
                $result = [];
                foreach ($directories as $dir) {
                    $result[] = [
                        'name' => basename($dir),
                        'id' => null,
                    ];
                }
                foreach ($files as $file) {
                    $result[] = [
                        'name' => basename($file),
                        'id' => 'local_file',
                        'metadata' => [
                            'size' => Storage::disk('public')->size($file),
                            'mimetype' => Storage::disk('public')->mimeType($file),
                        ]
                    ];
                }
                return $result;
            }
            return [];
        }

        try {
            $bucket = self::getBucket();
            $url = self::getSupabaseUrl() . "/storage/v1/object/list/{$bucket}";
            $key = self::getSupabaseKey();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $key,
                'apikey' => $key,
                'Content-Type' => 'application/json',
            ])->post($url, [
                'prefix' => $prefix,
                'limit' => 100,
                'offset' => 0,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Error listing objects from Supabase Storage: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Helper to detect if a path is a local/legacy path.
     */
    protected static function isLocalPath(string $path): bool
    {
        return str_starts_with($path, 'briefs/') || 
               str_starts_with($path, 'reprocesos_adjuntos/') || 
               str_starts_with($path, 'fallback/');
    }
}
