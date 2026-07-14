<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrdenProduccionArchivo;
use App\Models\SolicitudReproceso;
use App\Models\OrdenProduccion;
use App\Services\AdjuntoStorageService;
use Illuminate\Support\Facades\Storage;

class AuditarStorage extends Command
{
    protected $signature = 'archivos:auditar-storage';

    protected $description = 'Audita Supabase Storage comparando archivos en almacenamiento vs base de datos';

    public function handle()
    {
        $this->info("=== INICIANDO AUDITORÍA DE ALMACENAMIENTO ===");

        $bucket = config('filesystems.disks.s3.bucket');
        if (empty($bucket)) {
            $this->error("❌ El bucket S3 no está configurado (AWS_BUCKET está vacío en .env).");
            return Command::FAILURE;
        }

        // --- 1. Auditar de Base de Datos -> Storage ---
        $this->info("\n--- 1. Buscando registros de base de datos sin archivo físico (Missing Files) ---");
        
        $missingDbFiles = [];

        // OP Briefs
        $ops = OrdenProduccion::whereNotNull('brief')->get();
        foreach ($ops as $op) {
            $path = $op->brief;
            if (!AdjuntoStorageService::exists($path)) {
                $missingDbFiles[] = [
                    'tipo' => 'Brief de OP',
                    'id' => $op->id,
                    'op' => $op->numero_op,
                    'path' => $path,
                ];
            }
        }

        // OP Archivos
        $archivos = OrdenProduccionArchivo::all();
        foreach ($archivos as $arch) {
            $path = $arch->file_path;
            if (!AdjuntoStorageService::exists($path)) {
                $missingDbFiles[] = [
                    'tipo' => 'Archivo de OP',
                    'id' => $arch->id,
                    'op' => $arch->ordenProduccion->numero_op ?? 'N/A',
                    'path' => $path,
                ];
            }
        }

        // Reprocesos
        $reprocesos = SolicitudReproceso::whereNotNull('archivo_adjunto')->get();
        foreach ($reprocesos as $rep) {
            $path = $rep->archivo_adjunto;
            if (!AdjuntoStorageService::exists($path)) {
                $missingDbFiles[] = [
                    'tipo' => 'Adjunto Reproceso',
                    'id' => $rep->id,
                    'op' => $rep->ordenProduccion->numero_op ?? 'N/A',
                    'path' => $path,
                ];
            }
        }

        if (count($missingDbFiles) === 0) {
            $this->info("✅ Todos los registros de la base de datos cuentan con su respectivo archivo físico.");
        } else {
            $this->warn("⚠️ Se encontraron " . count($missingDbFiles) . " registros con archivos físicos faltantes:");
            $this->table(['Tipo Registro', 'ID', 'OP', 'Ruta en DB'], array_map(function ($item) {
                return [$item['tipo'], $item['id'], $item['op'], $item['path']];
            }, $missingDbFiles));
        }

        // --- 2. Auditar de Storage -> Base de Datos ---
        $this->info("\n--- 2. Buscando archivos huérfanos en Supabase Storage (no referenciados en DB) ---");
        
        try {
            // Get files recursively using Flysystem S3 adapter
            $storageFiles = Storage::disk('s3')->allFiles();
            
            $orphans = [];

            // Get all referenced paths
            $referencedPaths = array_merge(
                OrdenProduccion::whereNotNull('brief')->pluck('brief')->toArray(),
                OrdenProduccionArchivo::pluck('file_path')->toArray(),
                SolicitudReproceso::whereNotNull('archivo_adjunto')->pluck('archivo_adjunto')->toArray()
            );

            // Clean referenced paths array
            $referencedPaths = array_unique(array_filter($referencedPaths));

            foreach ($storageFiles as $file) {
                // If it is fallback/ ignore it since it is local fallback structure
                if (str_starts_with($file, 'fallback/')) {
                    continue;
                }

                if (!in_array($file, $referencedPaths)) {
                    $orphans[] = [
                        'path' => $file,
                    ];
                }
            }

            if (count($orphans) === 0) {
                $this->info("✅ No se encontraron archivos huérfanos en Supabase Storage.");
            } else {
                $this->warn("⚠️ Se encontraron " . count($orphans) . " archivos huérfanos:");
                $this->table(['Ruta Objeto'], array_map(function ($item) {
                    return [$item['path']];
                }, $orphans));
                $this->info("Nota: No se eliminó ningún archivo. Consérvelos o elimínelos manualmente si es seguro.");
            }

        } catch (\Exception $e) {
            $this->error("Error al obtener listado de Supabase Storage: " . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
