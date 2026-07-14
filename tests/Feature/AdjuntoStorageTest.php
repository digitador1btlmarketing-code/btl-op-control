<?php

namespace Tests\Feature;

use App\Models\OrdenProduccion;
use App\Models\OrdenProduccionArchivo;
use App\Models\SolicitudReproceso;
use App\Models\UsuarioAcceso;
use App\Services\AdjuntoStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Carbon\Carbon;

class AdjuntoStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock S3 and public storage
        Storage::fake('s3');
        Storage::fake('public');

        // Create standard roles for authorization tests
        UsuarioAcceso::create([
            'codigo' => 'ADMIN-CODE-999',
            'nombre' => 'Admin',
            'apellido' => 'Test',
            'rol' => 'admin',
            'activo' => true
        ]);

        UsuarioAcceso::create([
            'codigo' => 'SALES-OWNER-001',
            'nombre' => 'Sales',
            'apellido' => 'Owner',
            'rol' => 'ventas',
            'activo' => true
        ]);

        UsuarioAcceso::create([
            'codigo' => 'SALES-OTHER-002',
            'nombre' => 'Sales',
            'apellido' => 'Other',
            'rol' => 'ventas',
            'activo' => true
        ]);
    }

    /**
     * Test uploading a single file and multiple files in store.
     */
    public function test_file_upload_in_store(): void
    {
        session([
            'user_role' => 'ventas',
            'user_code' => 'SALES-OWNER-001',
            'user_name' => 'Sales Owner',
        ]);

        $file1 = UploadedFile::fake()->create('documento1.pdf', 500, 'application/pdf');
        $file2 = UploadedFile::fake()->create('documento 2 con espacios.xlsx', 800, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $response = $this->post('/op/nueva', [
            'categoria' => 'Branding',
            'numero_op' => 'OP-TEST-FILE-1',
            'proyecto' => 'Test Files',
            'presupuestista' => 'Test Presup',
            'cliente' => 'Test Client',
            'marca' => 'Test Brand',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
            'brief' => [$file1, $file2]
        ]);

        $response->assertRedirect('/op/nueva');

        $op = OrdenProduccion::where('numero_op', 'OP-TEST-FILE-1')->first();
        $this->assertNotNull($op);

        // Verify files in DB and that they display unique routes (UUIDs)
        $this->assertEquals(2, $op->archivos()->count());
        $firstArch = $op->archivos()->first();
        
        $this->assertStringContainsString('ordenes/' . $op->id . '/adjuntos/', $firstArch->file_path);
        $this->assertStringEndsWith('.pdf', $firstArch->file_path);
        
        // Conservación del nombre original
        $this->assertEquals('documento1.pdf', $firstArch->file_name);
        $this->assertEquals('documento 2 con espacios.xlsx', $op->archivos()->get()[1]->file_name);

        // Physical check on faked S3
        Storage::disk('s3')->assertExists($firstArch->file_path);
        Storage::disk('s3')->assertExists($op->archivos()->get()[1]->file_path);
    }

    /**
     * Test file validations: size, extension/mime.
     */
    public function test_file_validation_restrictions(): void
    {
        session([
            'user_role' => 'ventas',
            'user_code' => 'SALES-OWNER-001',
            'user_name' => 'Sales Owner',
        ]);

        // Large file (> 50MB)
        $largeFile = UploadedFile::fake()->create('large.pdf', 52000); // ~50.7 MB

        $response = $this->post('/op/nueva', [
            'categoria' => 'Branding',
            'numero_op' => 'OP-TEST-FILE-2',
            'proyecto' => 'Test Files Large',
            'presupuestista' => 'Test Presup',
            'cliente' => 'Test Client',
            'marca' => 'Test Brand',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
            'brief' => [$largeFile]
        ]);

        $response->assertSessionHasErrors('brief.0');

        // Invalid extension/mime (e.g. php file or exe file)
        $exeFile = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');

        $response = $this->post('/op/nueva', [
            'categoria' => 'Branding',
            'numero_op' => 'OP-TEST-FILE-3',
            'proyecto' => 'Test Files Exe',
            'presupuestista' => 'Test Presup',
            'cliente' => 'Test Client',
            'marca' => 'Test Brand',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
            'brief' => [$exeFile]
        ]);

        $response->assertSessionHasErrors('brief.0');
    }

    /**
     * Test authorized and unauthorized downloads.
     */
    public function test_authorized_and_unauthorized_downloads(): void
    {
        // 1. Create OP and file under SALES-OWNER-001
        $op = OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-AUTH-1',
            'proyecto' => 'Auth OP',
            'presupuestista' => 'Test Presup',
            'cliente' => 'Test Client',
            'marca' => 'Test Brand',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
            'creado_por_codigo' => 'SALES-OWNER-001',
            'creado_por_nombre' => 'Owner',
            'creado_por_rol' => 'ventas',
        ]);

        $filePath = "ordenes/{$op->id}/adjuntos/testfile.pdf";
        Storage::disk('s3')->put($filePath, 'PDF CONTENT');

        $archivo = OrdenProduccionArchivo::create([
            'orden_produccion_id' => $op->id,
            'file_path' => $filePath,
            'file_name' => 'testfile.pdf',
            'file_size' => 100,
            'mime_type' => 'application/pdf',
        ]);

        // Try downloading unauthorized (SALES-OTHER-002)
        session([
            'user_role' => 'ventas',
            'user_code' => 'SALES-OTHER-002',
            'user_name' => 'Other Owner',
        ]);

        $response = $this->get("/op/descargar-archivo/{$archivo->id}");
        $response->assertStatus(403);

        // Try downloading authorized (SALES-OWNER-001)
        session([
            'user_role' => 'ventas',
            'user_code' => 'SALES-OWNER-001',
            'user_name' => 'Sales Owner',
        ]);

        $response = $this->get("/op/descargar-archivo/{$archivo->id}");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertEquals('PDF CONTENT', $response->streamedContent());

        // Try downloading as Admin
        session([
            'user_role' => 'admin',
            'user_code' => 'ADMIN-CODE-999',
            'user_name' => 'Admin User',
        ]);

        $response = $this->get("/op/descargar-archivo/{$archivo->id}");
        $response->assertStatus(200);
    }

    /**
     * Test file deletion: correct file is removed on update and hard delete.
     */
    public function test_file_deletion_and_cleanup(): void
    {
        session([
            'user_role' => 'ventas',
            'user_code' => 'SALES-OWNER-001',
            'user_name' => 'Sales Owner',
        ]);

        // 1. Create OP with file
        $op = OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-DEL-1',
            'proyecto' => 'Del OP',
            'presupuestista' => 'Test Presup',
            'cliente' => 'Test Client',
            'marca' => 'Test Brand',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
            'creado_por_codigo' => 'SALES-OWNER-001',
            'creado_por_nombre' => 'Owner',
            'creado_por_rol' => 'ventas',
            'estado' => 'Pendiente',
        ]);

        $filePath = "ordenes/{$op->id}/adjuntos/todel.pdf";
        Storage::disk('s3')->put($filePath, 'PDF CONTENT');

        $archivo = $op->archivos()->create([
            'file_path' => $filePath,
            'file_name' => 'todel.pdf',
            'file_size' => 100,
            'mime_type' => 'application/pdf',
        ]);

        // 2. Update OP marking file for deletion
        $response = $this->post("/op/editar/{$op->id}", [
            'categoria' => 'Branding',
            'numero_op' => 'OP-DEL-1',
            'proyecto' => 'Del OP Updated',
            'presupuestista' => 'Test Presup',
            'cliente' => 'Test Client',
            'marca' => 'Test Brand',
            'entregar_a' => 'Cliente',
            'archivos_eliminar' => [$archivo->id]
        ]);

        $response->assertRedirect();
        
        // Verify DB deletion and S3 physical deletion
        $this->assertEquals(0, $op->archivos()->count());
        Storage::disk('s3')->assertMissing($filePath);
    }

    /**
     * Test compensation cleanup when database transaction fails.
     */
    public function test_compensation_cleanup_on_db_fail(): void
    {
        session([
            'user_role' => 'ventas',
            'user_code' => 'SALES-OWNER-001',
            'user_name' => 'Sales Owner',
        ]);

        $file = UploadedFile::fake()->create('rollback_test.pdf', 500, 'application/pdf');

        // Register saving model event to throw an exception and force a rollback
        OrdenProduccion::saving(function ($model) {
            if ($model->numero_op === 'OP-ROLLBACK-FAIL') {
                throw new \Exception("Database transaction forced failure");
            }
        });

        try {
            $this->post('/op/nueva', [
                'categoria' => 'Branding',
                'numero_op' => 'OP-ROLLBACK-FAIL',
                'proyecto' => 'Rollback Fail',
                'presupuestista' => 'Test Presup',
                'cliente' => 'Test Client',
                'marca' => 'Test Brand',
                'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
                'hora_entrega' => '12:00:00',
                'entregar_a' => 'Cliente',
                'brief' => [$file]
            ]);
        } catch (\Exception $e) {
            $this->assertEquals("Database transaction forced failure", $e->getMessage());
        }

        // Verify that the file was deleted from S3 since DB rolled back
        $files = Storage::disk('s3')->allFiles();
        $this->assertCount(0, $files);
    }

    /**
     * Test legacy attachment compatibility.
     */
    public function test_legacy_attachment_compatibility(): void
    {
        session([
            'user_role' => 'ventas',
            'user_code' => 'SALES-OWNER-001',
            'user_name' => 'Sales Owner',
        ]);

        $op = OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-LEGACY-1',
            'proyecto' => 'Legacy OP',
            'presupuestista' => 'Test Presup',
            'cliente' => 'Test Client',
            'marca' => 'Test Brand',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
            'creado_por_codigo' => 'SALES-OWNER-001',
            'creado_por_nombre' => 'Owner',
            'creado_por_rol' => 'ventas',
        ]);

        // Place a mock legacy file on the public local disk
        $localPath = 'briefs/oldfile.pdf';
        Storage::disk('public')->put($localPath, 'OLD PDF CONTENT');

        $archivo = $op->archivos()->create([
            'file_path' => $localPath,
            'file_name' => 'oldfile.pdf',
            'file_size' => 100,
            'mime_type' => 'application/pdf',
        ]);

        // Download legacy file
        $response = $this->get("/op/descargar-archivo/{$archivo->id}");
        $response->assertStatus(200);
        $this->assertEquals('OLD PDF CONTENT', $response->streamedContent());
    }

    /**
     * Test migration command.
     */
    public function test_migration_command_execution(): void
    {
        $op = OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-MIGRATION-CMD',
            'proyecto' => 'Migration OP',
            'presupuestista' => 'Test Presup',
            'cliente' => 'Test Client',
            'marca' => 'Test Brand',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
        ]);

        $localPath = 'briefs/migration_old.pdf';
        Storage::disk('public')->put($localPath, 'TO BE MIGRATED');

        $archivo = $op->archivos()->create([
            'file_path' => $localPath,
            'file_name' => 'migration_old.pdf',
            'file_size' => 100,
            'mime_type' => 'application/pdf',
        ]);

        $op->update(['brief' => $localPath]);

        // Run dry run
        Artisan::call('adjuntos:migrar-a-supabase', ['--dry-run' => true]);
        $output = Artisan::output();
        $this->assertStringContainsString('Archivos migrados exitosamente: 1', $output);
        
        $archivo->refresh();
        $this->assertEquals($localPath, $archivo->file_path); // no change

        // Run real migration with delete-local
        Artisan::call('adjuntos:migrar-a-supabase', ['--delete-local' => true]);
        $output = Artisan::output();
        $this->assertStringContainsString('Archivos migrados exitosamente: 1', $output);

        $archivo->refresh();
        $op->refresh();

        // Check DB update
        $this->assertStringContainsString('ordenes/' . $op->id . '/adjuntos/', $archivo->file_path);
        $this->assertEquals($archivo->file_path, $op->brief);
        $this->assertNull($archivo->url);

        // Check physical file in S3 and deletion from local
        Storage::disk('s3')->assertExists($archivo->file_path);
        Storage::disk('public')->assertMissing($localPath);
    }

    /**
     * Test secure download/streaming of reproceso attachments.
     */
    public function test_reproceso_attachment_download(): void
    {
        $op = OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-REPRO-DL',
            'proyecto' => 'Repro OP',
            'presupuestista' => 'Test Presup',
            'cliente' => 'Test Client',
            'marca' => 'Test Brand',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
            'creado_por_codigo' => 'SALES-OWNER-001',
            'creado_por_nombre' => 'Owner',
            'creado_por_rol' => 'ventas',
        ]);

        $filePath = "ordenes/{$op->id}/adjuntos/repro_file.png";
        Storage::disk('s3')->put($filePath, 'PNG DATA');

        $reproceso = SolicitudReproceso::create([
            'orden_produccion_id' => $op->id,
            'motivo' => 'Repro Motivo',
            'descripcion' => 'Repro Desc',
            'fecha_requerida' => Carbon::tomorrow()->format('Y-m-d'),
            'archivo_adjunto' => $filePath,
            'archivo_mime_type' => 'image/png',
            'archivo_size' => 100,
            'solicitado_por_nombre' => 'Owner',
            'solicitado_por_codigo' => 'SALES-OWNER-001',
        ]);

        // Attempt downloading as other user (unauthorized)
        session([
            'user_role' => 'ventas',
            'user_code' => 'SALES-OTHER-002',
            'user_name' => 'Other Owner',
        ]);

        $response = $this->get("/op/descargar-reproceso/{$reproceso->id}");
        $response->assertStatus(403);

        // Attempt downloading as authorized user
        session([
            'user_role' => 'ventas',
            'user_code' => 'SALES-OWNER-001',
            'user_name' => 'Sales Owner',
        ]);

        $response = $this->get("/op/descargar-reproceso/{$reproceso->id}");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertEquals('PNG DATA', $response->streamedContent());
    }
}
