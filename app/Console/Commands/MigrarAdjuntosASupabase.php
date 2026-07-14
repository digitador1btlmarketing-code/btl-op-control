<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\OrdenProduccionArchivo;
use App\Models\SolicitudReproceso;
use App\Models\OrdenProduccion;
use App\Services\AdjuntoStorageService;
use Illuminate\Support\Str;

class MigrarAdjuntosASupabase extends Command
{
    protected $signature = 'adjuntos:migrar-a-supabase 
                            {--dry-run : Simula la migración sin subir archivos ni modificar base de datos}
                            {--limit= : Limita la cantidad de archivos a migrar}
                            {--orden= : Filtra por el ID o número de OP específica}
                            {--delete-local : Elimina el archivo local tras una migración exitosa}';

    protected $description = 'Migra archivos locales de Render a Supabase Storage mediante S3 y registra metadatos en la base de datos';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');
        $ordenFilter = $this->option('orden');
        $deleteLocal = $this->option('delete-local');

        $this->info("=== MIGRACIÓN DE ADJUNTOS A SUPABASE STORAGE (S3) ===");
        if ($dryRun) {
            $this->warn("[MODO SIMULACIÓN - DRY RUN] No se realizarán cambios reales.");
        }

        $successCount = 0;
        $failCount = 0;
        $missingCount = 0;
        $skippedCount = 0;

        // 1. Process OrdenProduccionArchivo records
        $this->info("\n--- Procesando archivos de OP (OrdenProduccionArchivo) ---");
        $archivosQuery = OrdenProduccionArchivo::query()->with('ordenProduccion');

        if ($ordenFilter) {
            $archivosQuery->whereHas('ordenProduccion', function ($q) use ($ordenFilter) {
                $q->where('id', $ordenFilter)->orWhere('numero_op', $ordenFilter);
            });
        }

        $archivos = $archivosQuery->get();
        $processed = 0;

        foreach ($archivos as $archivo) {
            $localPath = $archivo->file_path;
            
            // A path is local if it is recognized as such by the service
            if (!AdjuntoStorageService::isLocalPath($localPath)) {
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

            // Check if local file exists
            if (!Storage::disk('public')->exists($localPath)) {
                $this->warn("Archivo no encontrado en almacenamiento local: {$localPath} (OP: {$op->numero_op})");
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

            // Real migration
            try {
                // Get file binary
                $binary = Storage::disk('public')->get($localPath);
                $size = Storage::disk('public')->size($localPath);
                $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
                $mimeType = $this->getMimeTypeByExtension($ext);

                // Prepare target path under ordenes/{orden_id}/adjuntos/{uuid}.{extension}
                $uuid = (string) Str::uuid();
                $supabasePath = "ordenes/{$op->id}/adjuntos/{$uuid}.{$ext}";

                // Upload using S3
                $uploadSuccess = Storage::disk('s3')->put($supabasePath, $binary);

                if ($uploadSuccess) {
                    DB::transaction(function () use ($archivo, $op, $supabasePath, $size, $mimeType, $localPath, $deleteLocal) {
                        $archivo->update([
                            'file_path' => $supabasePath,
                            'file_size' => $size,
                            'mime_type' => $mimeType,
                            'url' => null, // clear old signed URL
                            'is_missing' => false,
                        ]);

                        // Update brief of the OP if it pointed to the old local path
                        if ($op->brief === $localPath) {
                            $op->update(['brief' => $supabasePath]);
                        }

                        if ($deleteLocal) {
                            Storage::disk('public')->delete($localPath);
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

        // 2. Process SolicitudReproceso records
        $this->info("\n--- Procesando adjuntos de Reprocesos (SolicitudReproceso) ---");
        $reprocesosQuery = SolicitudReproceso::query()->with('ordenProduccion');

        if ($ordenFilter) {
            $reprocesosQuery->whereHas('ordenProduccion', function ($q) use ($ordenFilter) {
                $q->where('id', $ordenFilter)->orWhere('numero_op', $ordenFilter);
            });
        }

        $reprocesos = $reprocesosQuery->whereNotNull('archivo_adjunto')->get();
        $processedReproceso = 0;

        foreach ($reprocesos as $reproceso) {
            $localPath = $reproceso->archivo_adjunto;
            
            if (!AdjuntoStorageService::isLocalPath($localPath)) {
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
                $this->warn("Archivo de reproceso no encontrado en almacenamiento local: {$localPath} (OP: {$op->numero_op})");
                $missingCount++;
                if (!$dryRun) {
                    $reproceso->update(['is_missing' => true]);
                }
                continue;
            }

            $processedReproceso++;
            $this->info("Migrando reproceso: {$localPath} (OP: {$op->numero_op})");

            if ($dryRun) {
                $successCount++;
                continue;
            }

            // Real migration
            try {
                $binary = Storage::disk('public')->get($localPath);
                $size = Storage::disk('public')->size($localPath);
                $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
                $mimeType = $this->getMimeTypeByExtension($ext);

                // Target path format: ordenes/{orden_id}/adjuntos/{uuid}.{extension}
                $uuid = (string) Str::uuid();
                $supabasePath = "ordenes/{$op->id}/adjuntos/{$uuid}.{$ext}";

                $uploadSuccess = Storage::disk('s3')->put($supabasePath, $binary);

                if ($uploadSuccess) {
                    DB::transaction(function () use ($reproceso, $supabasePath, $size, $mimeType, $localPath, $deleteLocal) {
                        $reproceso->update([
                            'archivo_adjunto' => $supabasePath,
                            'archivo_size' => $size,
                            'archivo_mime_type' => $mimeType,
                            'archivo_url' => null, // clear old signed URL
                            'is_missing' => false,
                        ]);

                        if ($deleteLocal) {
                            Storage::disk('public')->delete($localPath);
                        }
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
        $this->info("Archivos faltantes localmente (no migrados): {$missingCount}");
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
        return $mimes[strtolower($ext)] ?? 'application/octet-stream';
    }
}
