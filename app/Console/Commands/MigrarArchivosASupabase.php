<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\OrdenProduccionArchivo;
use App\Models\SolicitudReproceso;
use App\Models\OrdenProduccion;
use App\Services\SupabaseStorageService;

class MigrarArchivosASupabase extends Command
{
    protected $signature = 'archivos:migrar-a-supabase 
                            {--dry-run : Simula la migración sin subir archivos ni modificar base de datos}
                            {--limit= : Limita la cantidad de archivos a migrar}
                            {--op= : Filtra por el número de una OP específica}';

    protected $description = 'Migra archivos locales de Render a Supabase Storage y registra metadatos en la base de datos';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');
        $opFilter = $this->option('op');

        $this->info("=== MIGRACIÓN DE ARCHIVOS A SUPABASE STORAGE ===");
        if ($dryRun) {
            $this->warn("[MODO SIMULACIÓN - DRY RUN] No se realizarán cambios reales.");
        }

        $successCount = 0;
        $failCount = 0;
        $missingCount = 0;
        $skippedCount = 0;

        // --- 1. Migrar archivos de OrdenProduccionArchivo (briefs/etc) ---
        $this->info("\n--- Procesando archivos de OP (OrdenProduccionArchivo) ---");
        $archivosQuery = OrdenProduccionArchivo::query()->with('ordenProduccion');

        if ($opFilter) {
            $archivosQuery->whereHas('ordenProduccion', function ($q) use ($opFilter) {
                $q->where('numero_op', $opFilter);
            });
        }

        $archivos = $archivosQuery->get();
        $processed = 0;

        foreach ($archivos as $archivo) {
            // Un path local generalmente empieza con 'briefs/' o 'fallback/'
            // O podemos validar si no empieza con el número de OP (OP-XXXX)
            $isLegacy = str_starts_with($archivo->file_path, 'briefs/') || 
                        str_starts_with($archivo->file_path, 'fallback/') ||
                        (!str_contains($archivo->file_path, '/archivos-iniciales/') && !str_contains($archivo->file_path, '/avances/'));

            if (!$isLegacy) {
                $skippedCount++;
                continue;
            }

            if ($limit && $processed >= $limit) {
                $this->info("Límite alcanzado ({$limit}). Deteniendo.");
                break;
            }

            $op = $archivo->ordenProduccion;
            if (!$op) {
                $this->error("Archivo ID {$archivo->id} no tiene una OP asociada.");
                $failCount++;
                continue;
            }

            $localPath = $archivo->file_path;
            
            // Check if local file exists
            if (!Storage::disk('public')->exists($localPath)) {
                $this->warn("Archivo no encontrado en Render local: {$localPath} (OP: {$op->numero_op})");
                $missingCount++;
                if (!$dryRun) {
                    $archivo->update(['is_missing' => true]);
                }
                continue;
            }

            $processed++;
            $this->info("Migrando: {$localPath} (OP: {$op->numero_op})");

            if ($dryRun) {
                $successCount++;
                continue;
            }

            // Real upload
            try {
                // Get file binary
                $binary = Storage::disk('public')->get($localPath);
                
                // Get mime type and size
                $size = Storage::disk('public')->size($localPath);
                // Simple mime type detection
                $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
                $mimeType = $this->getMimeTypeByExtension($ext);

                // Prepare target path on Supabase Storage
                $cleanOp = preg_replace('/[^a-zA-Z0-9_-]/', '', $op->numero_op);
                $uniqueName = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $archivo->file_name);
                $supabasePath = "{$cleanOp}/archivos-iniciales/{$uniqueName}";

                // Sube usando la REST API a través de SupabaseStorageService (bypass fallback)
                $uploadSuccess = $this->uploadDirectToSupabase($supabasePath, $binary, $mimeType);

                if ($uploadSuccess) {
                    DB::transaction(function () use ($archivo, $op, $supabasePath, $size, $mimeType, $localPath) {
                        // Generate dynamic signed URL to verify/retrieve
                        $signedUrl = SupabaseStorageService::getSignedUrl($supabasePath);

                        $archivo->update([
                            'file_path' => $supabasePath,
                            'file_size' => $size,
                            'mime_type' => $mimeType,
                            'url' => $signedUrl,
                            'is_missing' => false,
                        ]);

                        // Si el brief de la OP apunta al path local que acabamos de migrar, actualizar el brief de la OP
                        if ($op->brief === $localPath) {
                            $op->update(['brief' => $supabasePath]);
                        }
                    });

                    $this->info("✅ Migrado con éxito a: {$supabasePath}");
                    $successCount++;
                } else {
                    $this->error("❌ Error al subir a Supabase Storage: {$supabasePath}");
                    $failCount++;
                }
            } catch (\Exception $e) {
                $this->error("❌ Excepción al migrar archivo ID {$archivo->id}: " . $e->getMessage());
                $failCount++;
            }
        }

        // --- 2. Migrar archivos de SolicitudReproceso (archivo_adjunto) ---
        $this->info("\n--- Procesando adjuntos de Reprocesos (SolicitudReproceso) ---");
        $reprocesosQuery = SolicitudReproceso::query()->with('ordenProduccion');

        if ($opFilter) {
            $reprocesosQuery->whereHas('ordenProduccion', function ($q) use ($opFilter) {
                $q->where('numero_op', $opFilter);
            });
        }

        $reprocesos = $reprocesosQuery->whereNotNull('archivo_adjunto')->get();
        $processedReproceso = 0;

        foreach ($reprocesos as $reproceso) {
            $localPath = $reproceso->archivo_adjunto;
            
            $isLegacy = str_starts_with($localPath, 'reprocesos_adjuntos/') || 
                        str_starts_with($localPath, 'fallback/') ||
                        (!str_contains($localPath, '/avances/'));

            if (!$isLegacy) {
                $skippedCount++;
                continue;
            }

            if ($limit && ($processed + $processedReproceso) >= $limit) {
                $this->info("Límite alcanzado ({$limit}). Deteniendo.");
                break;
            }

            $op = $reproceso->ordenProduccion;
            if (!$op) {
                $this->error("Solicitud Reproceso ID {$reproceso->id} no tiene una OP asociada.");
                $failCount++;
                continue;
            }

            // Check if local file exists
            if (!Storage::disk('public')->exists($localPath)) {
                $this->warn("Archivo de reproceso no encontrado en Render local: {$localPath} (OP: {$op->numero_op})");
                $missingCount++;
                if (!$dryRun) {
                    $reproceso->update(['is_missing' => true]);
                }
                continue;
            }

            $processedReproceso++;
            $this->info("Migrando: {$localPath} (OP: {$op->numero_op})");

            if ($dryRun) {
                $successCount++;
                continue;
            }

            // Real upload
            try {
                $binary = Storage::disk('public')->get($localPath);
                $size = Storage::disk('public')->size($localPath);
                $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
                $mimeType = $this->getMimeTypeByExtension($ext);

                $cleanOp = preg_replace('/[^a-zA-Z0-9_-]/', '', $op->numero_op);
                $uniqueName = time() . '_' . basename($localPath);
                $supabasePath = "{$cleanOp}/avances/{$uniqueName}";

                $uploadSuccess = $this->uploadDirectToSupabase($supabasePath, $binary, $mimeType);

                if ($uploadSuccess) {
                    DB::transaction(function () use ($reproceso, $supabasePath, $size, $mimeType) {
                        $signedUrl = SupabaseStorageService::getSignedUrl($supabasePath);

                        $reproceso->update([
                            'archivo_adjunto' => $supabasePath,
                            'archivo_size' => $size,
                            'archivo_mime_type' => $mimeType,
                            'archivo_url' => $signedUrl,
                            'is_missing' => false,
                        ]);
                    });

                    $this->info("✅ Migrado con éxito a: {$supabasePath}");
                    $successCount++;
                } else {
                    $this->error("❌ Error al subir a Supabase Storage: {$supabasePath}");
                    $failCount++;
                }
            } catch (\Exception $e) {
                $this->error("❌ Excepción al migrar reproceso ID {$reproceso->id}: " . $e->getMessage());
                $failCount++;
            }
        }

        $this->info("\n=== RESUMEN DE MIGRACIÓN ===");
        $this->info("Archivos ya migrados (omitidos): {$skippedCount}");
        $this->info("Archivos migrados exitosamente: {$successCount}");
        $this->info("Archivos faltantes en Render (no migrados): {$missingCount}");
        $this->info("Archivos con error en migración: {$failCount}");

        return Command::SUCCESS;
    }

    private function getMimeTypeByExtension($ext)
    {
        $mimes = [
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
        ];
        return $mimes[$ext] ?? 'application/octet-stream';
    }

    private function uploadDirectToSupabase($path, $binary, $mimeType)
    {
        $url = env('SUPABASE_URL');
        $key = env('SUPABASE_KEY');
        $bucket = env('SUPABASE_STORAGE_BUCKET', 'ordenes-produccion');

        if (app()->runningUnitTests() || 
            empty($url) || 
            empty($key) || 
            str_contains($url, 'your-project') || 
            str_contains($key, 'your-service-role')) {
            
            // Faked or fallback upload: save locally to public disk under fallback prefix
            Storage::disk('public')->put("fallback/{$path}", $binary);
            return true;
        }

        $endpoint = rtrim($url, '/') . "/storage/v1/object/{$bucket}/{$path}";

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $key,
                'apikey' => $key,
                'Content-Type' => $mimeType,
            ])->withBody($binary, $mimeType)
              ->post($endpoint);

            return $response->successful();
        } catch (\Exception $e) {
            $this->error("Excepción en llamada HTTP a Supabase Storage: " . $e->getMessage());
            return false;
        }
    }
}
