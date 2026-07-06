<?php

namespace Tests\Feature;

use App\Models\OrdenProduccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class OrdenProduccionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test redirection for unauthenticated access.
     */
    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $response = $this->get('/op/nueva');
        $response->assertRedirect('/');

        $response = $this->get('/op/admin');
        $response->assertRedirect('/');

        $response = $this->get('/op/tv');
        $response->assertRedirect('/');
    }

    /**
     * Test login with valid and invalid access codes.
     */
    public function test_access_code_login(): void
    {
        // Invalid code
        $response = $this->post('/', ['codigo_acceso' => 'INVALID-CODE']);
        $response->assertSessionHas('error');
        $this->assertNull(session('user_role'));

        // Sales code
        $response = $this->post('/', ['codigo_acceso' => 'DAFNE-RAMIREZ-PROD-2026']);
        $response->assertRedirect('/op/mis-ordenes');
        $this->assertEquals('ventas', session('user_role'));

        // Admin code
        session()->forget('user_role');
        $response = $this->post('/', ['codigo_acceso' => 'ADMIN-PROD-2026']);
        $response->assertRedirect('/op/admin');
        $this->assertEquals('admin', session('user_role'));

        // TV Branding code
        session()->forget('user_role');
        $response = $this->post('/', ['codigo_acceso' => 'BRANDING-PROD-2026']);
        $response->assertRedirect('/op/tv?categoria=Branding');
        $this->assertEquals('tv_branding', session('user_role'));
    }

    /**
     * Test automatic calculation of 'avance' percentage based on 'estado'.
     */
    public function test_automatic_progress_calculation(): void
    {
        // Pendiente should be 0%
        $op1 = OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-Test-1',
            'proyecto' => 'Test',
            'presupuestista' => 'Test',
            'cliente' => 'Test',
            'marca' => 'Test',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
            'estado' => 'Pendiente',
        ]);
        $this->assertEquals(0, $op1->avance);

        // En proceso should be 50%
        $op1->update(['estado' => 'En proceso']);
        $this->assertEquals(50, $op1->avance);

        // Terminado should be 100%
        $op1->update(['estado' => 'Terminado']);
        $this->assertEquals(100, $op1->avance);

        // Cancelado should be 0%
        $op1->update(['estado' => 'Cancelado']);
        $this->assertEquals(0, $op1->avance);
        $this->assertEquals('NORMAL', $op1->prioridad);
        $this->assertFalse($op1->mostrar_fuego);
    }

    /**
     * Test priority calculations based on date differences.
     */
    public function test_priority_calculations(): void
    {
        // Due today or overdue: URGENTE (fuego = true)
        $opUrgent = new OrdenProduccion([
            'fecha_entrega' => Carbon::today()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
        ]);
        $this->assertEquals('URGENTE', $opUrgent->prioridad);
        $this->assertTrue($opUrgent->mostrar_fuego);

        // Due tomorrow (1 day): PRÓXIMA (fuego = true)
        $opProxima = new OrdenProduccion([
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
        ]);
        $this->assertEquals('PRÓXIMA', $opProxima->prioridad);
        $this->assertTrue($opProxima->mostrar_fuego);

        // Due in 3 days: NORMAL (fuego = false)
        $opNormal = new OrdenProduccion([
            'fecha_entrega' => Carbon::today()->addDays(3)->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
        ]);
        $this->assertEquals('NORMAL', $opNormal->prioridad);
        $this->assertFalse($opNormal->mostrar_fuego);
    }

    /**
     * Test TV view restrictions and redirects.
     */
    public function test_tv_view_restrictions(): void
    {
        // Log in as TV Branding
        session(['user_role' => 'tv_branding']);

        // Attempting to see Branding category should be allowed
        $response = $this->get('/op/tv?categoria=Branding');
        $response->assertStatus(200);

        // Attempting to see Promocional should be redirected back to Branding
        $response = $this->get('/op/tv?categoria=Promocional');
        $response->assertRedirect('/op/tv?categoria=Branding');
    }

    /**
     * Test admin can update leader and state via AJAX.
     */
    public function test_admin_can_update_order_via_ajax(): void
    {
        session(['user_role' => 'admin']);

        $op = OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-Ajax-1',
            'proyecto' => 'Ajax Test',
            'presupuestista' => 'Test Presupuesto',
            'cliente' => 'Test Cliente',
            'marca' => 'Test Marca',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
            'estado' => 'Pendiente',
        ]);

        $response = $this->postJson("/op/admin/update/{$op->id}", [
            'lider_produccion' => 'Lider Test',
            'estado' => 'En proceso',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'avance' => 50,
                'mostrar_fuego' => true,
            ]);

        $op->refresh();
        $this->assertEquals('Lider Test', $op->lider_produccion);
        $this->assertEquals('En proceso', $op->estado);
        $this->assertEquals(50, $op->avance);
    }

    /**
     * Test default model state and progress calculations when created without them.
     */
    public function test_default_state_and_progress_on_creation(): void
    {
        $op = OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-TEST-DEFAULTS',
            'proyecto' => 'Defaults Test',
            'presupuestista' => 'Test Presupuesto',
            'cliente' => 'Test Cliente',
            'marca' => 'Test Marca',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
        ]);

        $this->assertEquals('Pendiente', $op->estado);
        $this->assertEquals(0, $op->avance);

        $dbOp = OrdenProduccion::where('numero_op', 'OP-TEST-DEFAULTS')->first();
        $this->assertEquals('Pendiente', $dbOp->estado);
        $this->assertEquals(0, $dbOp->avance);
    }

    /**
     * Test admin updates route.
     */
    public function test_admin_updates_route(): void
    {
        session(['user_role' => 'admin']);

        $response = $this->getJson('/op/admin/updates');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'ordenes',
                'kpis' => [
                    'total',
                    'pendientes',
                    'en_proceso',
                    'terminadas',
                    'urgentes',
                ]
            ]);
    }

    /**
     * Test admin can reset/clear all orders with the correct password.
     */
    public function test_admin_can_reset_orders_with_correct_password(): void
    {
        $initialCount = OrdenProduccion::count();

        // 1. Create a dummy order
        OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-Reset-Test',
            'proyecto' => 'Reset Test',
            'presupuestista' => 'Test Presupuesto',
            'cliente' => 'Test Cliente',
            'marca' => 'Test Marca',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
        ]);

        $this->assertEquals($initialCount + 1, OrdenProduccion::count());

        // 2. Perform reset request as admin with correct password
        session(['user_role' => 'admin']);
        $response = $this->post('/admin/ordenes/reset', [
            'reset_password' => 'BTL-RESET-2026'
        ]);

        $response->assertRedirect('/op/admin');
        $response->assertSessionHas('success', 'Órdenes eliminadas correctamente.');
        $this->assertEquals(0, OrdenProduccion::count());
    }

    /**
     * Test admin cannot reset/clear orders with incorrect password.
     */
    public function test_admin_cannot_reset_orders_with_incorrect_password(): void
    {
        $initialCount = OrdenProduccion::count();

        // 1. Create a dummy order
        OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-Reset-Fail',
            'proyecto' => 'Reset Test Fail',
            'presupuestista' => 'Test Presupuesto',
            'cliente' => 'Test Cliente',
            'marca' => 'Test Marca',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
        ]);

        $this->assertEquals($initialCount + 1, OrdenProduccion::count());

        // 2. Perform reset request as admin with WRONG password
        session(['user_role' => 'admin']);
        $response = $this->post('/admin/ordenes/reset', [
            'reset_password' => 'WRONG-PASSWORD'
        ]);

        $response->assertRedirect('/op/admin');
        $response->assertSessionHas('error', 'Contraseña de seguridad incorrecta.');
        $this->assertEquals($initialCount + 1, OrdenProduccion::count());
    }

    /**
     * Test non-admin cannot access the reset endpoint.
     */
    public function test_non_admin_cannot_reset_orders(): void
    {
        $initialCount = OrdenProduccion::count();

        // 1. Create a dummy order
        OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-Reset-NoAuth',
            'proyecto' => 'Reset Test NoAuth',
            'presupuestista' => 'Test Presupuesto',
            'cliente' => 'Test Cliente',
            'marca' => 'Test Marca',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
        ]);

        $this->assertEquals($initialCount + 1, OrdenProduccion::count());

        // 2. Attempt reset as sales role
        session(['user_role' => 'ventas']);
        $response = $this->post('/admin/ordenes/reset', [
            'reset_password' => 'BTL-RESET-2026'
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'No tiene permisos para acceder a esta sección.');
        $this->assertEquals($initialCount + 1, OrdenProduccion::count());
    }

    /**
     * Test Area Admins (Branding vs Promocional) category isolation.
     */
    public function test_area_admins_category_isolation(): void
    {
        // Create a branding and a promotional order
        OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-BRAND-100',
            'proyecto' => 'Branding Camp',
            'presupuestista' => 'Presup',
            'cliente' => 'Client',
            'marca' => 'Brand',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
        ]);

        OrdenProduccion::create([
            'categoria' => 'Promocional',
            'numero_op' => 'OP-PROMO-100',
            'proyecto' => 'Promo Camp',
            'presupuestista' => 'Presup',
            'cliente' => 'Client',
            'marca' => 'Promo',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
        ]);

        // Login as ADMIN-BRANDING-2026
        session([
            'user_role' => 'admin_branding',
            'user_code' => 'ADMIN-BRANDING-2026'
        ]);

        // Get admin updates
        $response = $this->getJson('/op/admin/updates');
        $response->assertStatus(200);
        $data = $response->json();
        
        // Assert only Branding orders are visible
        $this->assertGreaterThan(0, count($data['ordenes']));
        foreach ($data['ordenes'] as $o) {
            $this->assertEquals('Branding', $o['categoria']);
        }

        // Login as ADMIN-PROMO-2026
        session([
            'user_role' => 'admin_promo',
            'user_code' => 'ADMIN-PROMO-2026'
        ]);

        // Get admin updates
        $response = $this->getJson('/op/admin/updates');
        $response->assertStatus(200);
        $data = $response->json();

        // Assert only Promocional orders are visible
        $this->assertGreaterThan(0, count($data['ordenes']));
        foreach ($data['ordenes'] as $o) {
            $this->assertEquals('Promocional', $o['categoria']);
        }
    }

    /**
     * Test Jefe de Ventas commercial segmentation.
     */
    public function test_jefe_ventas_segmentation(): void
    {
        // Dafne belongs to Jefe Rizo (assigned via migration).
        // Carlos belongs to Jefe Cajina (assigned via migration).

        // Create OPs with different creators
        OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-RIZO-VEND',
            'proyecto' => 'Rizo Project',
            'presupuestista' => 'Presup',
            'cliente' => 'Client',
            'marca' => 'Brand',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
            'creado_por_codigo' => 'DAFNE-RAMIREZ-PROD-2026',
            'creado_por_nombre' => 'DAFNE',
            'creado_por_rol' => 'ventas',
        ]);

        OrdenProduccion::create([
            'categoria' => 'Promocional',
            'numero_op' => 'OP-CAJINA-VEND',
            'proyecto' => 'Cajina Project',
            'presupuestista' => 'Presup',
            'cliente' => 'Client',
            'marca' => 'Promo',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
            'creado_por_codigo' => 'CARLOS-VARGAS-PROD-2026',
            'creado_por_nombre' => 'CARLOS',
            'creado_por_rol' => 'ventas',
        ]);

        // Login as JEFERIZO-PROD-2026 (Jefe Rizo)
        session([
            'user_role' => 'jefe_ventas',
            'user_code' => 'JEFERIZO-PROD-2026'
        ]);

        // Get updates
        $response = $this->getJson('/op/jefe-ventas/updates');
        $response->assertStatus(200);
        $data = $response->json();

        // Should only see OPs from Rizo vendors
        $this->assertCount(1, $data['ordenes']);
        $this->assertEquals('OP-RIZO-VEND', $data['ordenes'][0]['numero_op']);

        // Login as JEFECAJINA-PROD-2026 (Jefe Cajina)
        session([
            'user_role' => 'jefe_ventas',
            'user_code' => 'JEFECAJINA-PROD-2026'
        ]);

        // Get updates
        $response = $this->getJson('/op/jefe-ventas/updates');
        $response->assertStatus(200);
        $data = $response->json();

        // Should only see OPs from Cajina vendors
        $this->assertCount(1, $data['ordenes']);
        $this->assertEquals('OP-CAJINA-VEND', $data['ordenes'][0]['numero_op']);
    }

    /**
     * Test history view endpoint permissions.
     */
    public function test_historial_permissions(): void
    {
        // Create an OP of category Branding by seller Dafne (who belongs to Rizo)
        $op = OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-RIZO-VEND',
            'proyecto' => 'Rizo Project',
            'presupuestista' => 'Presup',
            'cliente' => 'Client',
            'marca' => 'Brand',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
            'creado_por_codigo' => 'DAFNE-RAMIREZ-PROD-2026',
            'creado_por_nombre' => 'DAFNE',
            'creado_por_rol' => 'ventas',
        ]);

        // 1. Master Admin (admin) should see history
        session([
            'user_role' => 'admin',
            'user_code' => 'ADMIN-PROD-2026'
        ]);
        $response = $this->getJson("/op/historial/{$op->id}");
        $response->assertStatus(200);

        // 2. Admin Branding should see history (as OP is Branding)
        session([
            'user_role' => 'admin_branding',
            'user_code' => 'ADMIN-BRANDING-2026'
        ]);
        $response = $this->getJson("/op/historial/{$op->id}");
        $response->assertStatus(200);

        // 3. Admin Promo should NOT see history (as OP is Branding)
        session([
            'user_role' => 'admin_promo',
            'user_code' => 'ADMIN-PROMO-2026'
        ]);
        $response = $this->getJson("/op/historial/{$op->id}");
        $response->assertStatus(403);

        // 4. Jefe Rizo should see history (as creator Dafne belongs to Rizo)
        session([
            'user_role' => 'jefe_ventas',
            'user_code' => 'JEFERIZO-PROD-2026'
        ]);
        $response = $this->getJson("/op/historial/{$op->id}");
        $response->assertStatus(200);

        // 5. Jefe Cajina should NOT see history (as Dafne does not belong to Cajina)
        session([
            'user_role' => 'jefe_ventas',
            'user_code' => 'JEFECAJINA-PROD-2026'
        ]);
        $response = $this->getJson("/op/historial/{$op->id}");
        $response->assertStatus(403);

        // 6. Dafne (vendedor creator) should see history
        session([
            'user_role' => 'ventas',
            'user_code' => 'DAFNE-RAMIREZ-PROD-2026'
        ]);
        $response = $this->getJson("/op/historial/{$op->id}");
        $response->assertStatus(200);

        // 7. Another seller should NOT see history
        session([
            'user_role' => 'ventas',
            'user_code' => 'CARLOS-VARGAS-PROD-2026'
        ]);
        $response = $this->getJson("/op/historial/{$op->id}");
        $response->assertStatus(403);
    }

    /**
     * Test updates polling return recent events.
     */
    public function test_updates_return_recent_events(): void
    {
        $op = OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-RIZO-VEND',
            'proyecto' => 'Rizo Project',
            'presupuestista' => 'Presup',
            'cliente' => 'Client',
            'marca' => 'Brand',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
            'creado_por_codigo' => 'DAFNE-RAMIREZ-PROD-2026',
            'creado_por_nombre' => 'DAFNE',
            'creado_por_rol' => 'ventas',
        ]);

        // Create a history record by a system user (to check we receive it)
        $op->historial()->create([
            'tipo_evento' => 'cambio_estado',
            'descripcion' => 'SISTEMA cambió el estado a En proceso.',
            'realizado_por_codigo' => 'SISTEMA',
            'realizado_por_nombre' => 'SISTEMA',
            'realizado_por_rol' => 'sistema',
        ]);

        // Log in as Jefe Rizo
        session([
            'user_role' => 'jefe_ventas',
            'user_code' => 'JEFERIZO-PROD-2026'
        ]);

        $response = $this->getJson('/op/jefe-ventas/updates');
        $response->assertStatus(200);
        $response->assertJsonStructure(['recent_events']);
        
        $data = $response->json();
        $this->assertNotEmpty($data['recent_events']);
        $this->assertEquals('cambio_estado', $data['recent_events'][0]['tipo_evento']);
    }

    /**
     * Test En espera status does not override progress.
     */
    public function test_en_espera_status_and_progress_preservation(): void
    {
        $op = OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-Preserve-1',
            'proyecto' => 'Test',
            'presupuestista' => 'Test',
            'cliente' => 'Test',
            'marca' => 'Test',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'prioridad' => 'NORMAL',
            'estado' => 'En proceso', // Avance = 50%
            'entregar_a' => 'Cliente',
            'creado_por_codigo' => 'ADMIN-PROD-2026',
            'creado_por_nombre' => 'ADMIN',
            'creado_por_rol' => 'admin',
        ]);

        $this->assertEquals(50, $op->avance);

        $op->update(['estado' => 'En espera']);
        $this->assertEquals('En espera', $op->fresh()->estado);
        $this->assertEquals(50, $op->fresh()->avance);
    }

    /**
     * Test a user cannot approve/reject their own request.
     */
    public function test_self_approval_and_rejection_is_blocked(): void
    {
        $op = OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-Self-App-1',
            'proyecto' => 'Test',
            'presupuestista' => 'Test',
            'cliente' => 'Test',
            'marca' => 'Test',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'prioridad' => 'NORMAL',
            'estado' => 'Pendiente',
            'entregar_a' => 'Cliente',
            'creado_por_codigo' => 'DAFNE-RAMIREZ-PROD-2026',
            'creado_por_nombre' => 'DAFNE',
            'creado_por_rol' => 'ventas',
        ]);

        $solicitud = \App\Models\SolicitudCambioFecha::create([
            'orden_produccion_id' => $op->id,
            'fecha_actual' => $op->fecha_entrega,
            'hora_actual' => $op->hora_entrega,
            'fecha_solicitada' => Carbon::tomorrow()->addDay()->format('Y-m-d'),
            'hora_solicitada' => '14:00:00',
            'razon_solicitud' => 'Razón de test',
            'solicitado_por_codigo' => 'DAFNE-RAMIREZ-PROD-2026',
            'solicitado_por_nombre' => 'DAFNE',
            'estado_solicitud' => 'Pendiente',
            'fecha_solicitud' => now(),
        ]);

        session([
            'user_role' => 'ventas',
            'user_code' => 'DAFNE-RAMIREZ-PROD-2026',
            'user_name' => 'DAFNE',
        ]);

        $response = $this->postJson(route('jefe.cambio_fecha.aprobar', $solicitud->id));
        $response->assertStatus(403);

        $response = $this->postJson(route('jefe.cambio_fecha.rechazar', $solicitud->id), [
            'razon_rechazo' => 'Rechazo automático'
        ]);
        $response->assertStatus(403);
    }

    /**
     * Test symmetric approval flow rules.
     */
    public function test_symmetric_approval_matrix(): void
    {
        $op = OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-Sym-1',
            'proyecto' => 'Test',
            'presupuestista' => 'Test',
            'cliente' => 'Test',
            'marca' => 'Test',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'prioridad' => 'NORMAL',
            'estado' => 'Pendiente',
            'entregar_a' => 'Cliente',
            'creado_por_codigo' => 'DAFNE-RAMIREZ-PROD-2026',
            'creado_por_nombre' => 'DAFNE',
            'creado_por_rol' => 'ventas',
        ]);

        // Request by sales user
        $solicitud1 = \App\Models\SolicitudCambioFecha::create([
            'orden_produccion_id' => $op->id,
            'fecha_actual' => $op->fecha_entrega,
            'hora_actual' => $op->hora_entrega,
            'fecha_solicitada' => Carbon::tomorrow()->addDay()->format('Y-m-d'),
            'hora_solicitada' => '14:00:00',
            'razon_solicitud' => 'Test',
            'solicitado_por_codigo' => 'DAFNE-RAMIREZ-PROD-2026',
            'solicitado_por_nombre' => 'DAFNE',
            'estado_solicitud' => 'Pendiente',
            'fecha_solicitud' => now(),
        ]);

        // Mock session as admin branding (OP is Branding category, so admin_branding can approve)
        session([
            'user_role' => 'admin_branding',
            'user_code' => 'ADMIN-BRANDING-2026',
            'user_name' => 'BRANDING ADMIN',
        ]);

        $response = $this->postJson(route('jefe.cambio_fecha.aprobar', $solicitud1->id));
        $response->assertStatus(200);
        $this->assertEquals('Aprobada', $solicitud1->fresh()->estado_solicitud);

        // Reset and request by admin
        $solicitud2 = \App\Models\SolicitudCambioFecha::create([
            'orden_produccion_id' => $op->id,
            'fecha_actual' => $op->fecha_entrega,
            'hora_actual' => $op->hora_entrega,
            'fecha_solicitada' => Carbon::tomorrow()->addDays(2)->format('Y-m-d'),
            'hora_solicitada' => '15:00:00',
            'razon_solicitud' => 'Test admin',
            'solicitado_por_codigo' => 'ADMIN-BRANDING-2026',
            'solicitado_por_nombre' => 'BRANDING ADMIN',
            'estado_solicitud' => 'Pendiente',
            'fecha_solicitud' => now(),
        ]);

        // Mock session as vendor creator
        session([
            'user_role' => 'ventas',
            'user_code' => 'DAFNE-RAMIREZ-PROD-2026',
            'user_name' => 'DAFNE',
        ]);

        $response = $this->postJson(route('jefe.cambio_fecha.aprobar', $solicitud2->id));
        $response->assertStatus(200);
        $this->assertEquals('Aprobada', $solicitud2->fresh()->estado_solicitud);
    }

    /**
     * Test creation route access middleware for category admins.
     */
    public function test_creation_route_category_admins_access(): void
    {
        // Test admin_branding has access
        session([
            'user_role' => 'admin_branding',
            'user_code' => 'ADMIN-BRAND-TEST',
            'user_name' => 'Brand Admin',
        ]);
        $response = $this->get(route('op.create'));
        $response->assertStatus(200);

        // Test admin_promo has access
        session([
            'user_role' => 'admin_promo',
            'user_code' => 'ADMIN-PROMO-TEST',
            'user_name' => 'Promo Admin',
        ]);
        $response = $this->get(route('op.create'));
        $response->assertStatus(200);
    }

    /**
     * Test only authorized pending solicitudes show up in user lists.
     */
    public function test_solicitudes_tray_filtering(): void
    {
        $op = OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-Tray-1',
            'proyecto' => 'Test',
            'presupuestista' => 'Test',
            'cliente' => 'Test',
            'marca' => 'Test',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'prioridad' => 'NORMAL',
            'estado' => 'Pendiente',
            'entregar_a' => 'Cliente',
            'creado_por_codigo' => 'DAFNE-RAMIREZ-PROD-2026',
            'creado_por_nombre' => 'DAFNE',
            'creado_por_rol' => 'ventas',
        ]);

        $solicitud = \App\Models\SolicitudCambioFecha::create([
            'orden_produccion_id' => $op->id,
            'fecha_actual' => $op->fecha_entrega,
            'hora_actual' => $op->hora_entrega,
            'fecha_solicitada' => Carbon::tomorrow()->addDay()->format('Y-m-d'),
            'hora_solicitada' => '14:00:00',
            'razon_solicitud' => 'Test tray',
            'solicitado_por_codigo' => 'DAFNE-RAMIREZ-PROD-2026',
            'solicitado_por_nombre' => 'DAFNE',
            'estado_solicitud' => 'Pendiente',
            'fecha_solicitud' => now(),
        ]);

        // Mock as solicitor (DAFNE): tray should NOT show her own request
        session([
            'user_role' => 'ventas',
            'user_code' => 'DAFNE-RAMIREZ-PROD-2026',
            'user_name' => 'DAFNE',
        ]);
        $response = $this->get(route('op.mis_ordenes'));
        $response->assertStatus(200);
        $this->assertCount(0, $response->viewData('solicitudes'));

        // Mock as admin branding: tray should show the request
        session([
            'user_role' => 'admin_branding',
            'user_code' => 'ADMIN-BRAND-TEST',
            'user_name' => 'Brand Admin',
        ]);
        $response = $this->get(route('op.admin'));
        $response->assertStatus(200);
        $this->assertCount(1, $response->viewData('solicitudes'));
    }

    /**
     * Test the new 'vista' role and its access restrictions.
     */
    public function test_vista_role_permissions_and_restrictions(): void
    {
        // Seed the vista user inside this test
        $now = now();
        \Illuminate\Support\Facades\DB::table('usuarios_acceso')->insertOrIgnore([
            [
                'codigo' => 'VISTA-PROD-2026',
                'nombre' => 'USUARIO',
                'apellido' => 'VISTA',
                'rol' => 'vista',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now
            ]
        ]);

        // 1. Log in with vista user access code
        $response = $this->post('/', ['codigo_acceso' => 'VISTA-PROD-2026']);
        $response->assertRedirect('/op/vista');
        $this->assertEquals('vista', session('user_role'));

        // 2. Can access vista panel
        $response = $this->get('/op/vista');
        $response->assertStatus(200);

        // 3. Cannot access creation view
        $response = $this->get('/op/nueva');
        $response->assertRedirect('/');

        // 4. Cannot submit order creation
        $response = $this->post('/op/nueva', [
            'categoria' => 'Branding',
            'numero_op' => 'OP-VISTA-FAIL',
            'proyecto' => 'Fail',
            'presupuestista' => 'Fail',
            'cliente' => 'Fail',
            'marca' => 'Fail',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '10:00:00',
            'entregar_a' => 'Cliente',
        ]);
        $response->assertRedirect('/');

        // 5. Cannot reset database
        $response = $this->post('/admin/ordenes/reset');
        $response->assertRedirect('/');
    }

    /**
     * Test uploading multiple files in store.
     */
    public function test_multiple_file_upload_in_store(): void
    {
        // Mock user as vendor
        session([
            'user_role' => 'ventas',
            'user_code' => 'DAFNE-RAMIREZ-PROD-2026',
            'user_name' => 'DAFNE',
        ]);

        \Illuminate\Support\Facades\Storage::fake('public');

        $file1 = \Illuminate\Http\UploadedFile::fake()->create('document1.pdf', 500);
        $file2 = \Illuminate\Http\UploadedFile::fake()->create('spreadsheet2.xlsx', 800);

        $response = $this->post('/op/nueva', [
            'categoria' => 'Branding',
            'numero_op' => 'OP-MULTIFILE-TEST',
            'proyecto' => 'Multi upload project',
            'presupuestista' => 'Test Presup',
            'cliente' => 'Test Client',
            'marca' => 'Test Brand',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
            'brief' => [$file1, $file2]
        ]);

        $response->assertRedirect('/op/nueva');
        
        $op = OrdenProduccion::where('numero_op', 'OP-MULTIFILE-TEST')->first();
        $this->assertNotNull($op);
        
        // Assert files are linked in database
        $this->assertEquals(2, $op->archivos()->count());
        $this->assertNotNull($op->brief); // first file path

        $firstFile = $op->archivos->first();
        $this->assertEquals('document1.pdf', $firstFile->file_name);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($firstFile->file_path);

        $secondFile = $op->archivos->last();
        $this->assertEquals('spreadsheet2.xlsx', $secondFile->file_name);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($secondFile->file_path);
    }

    /**
     * Test the complete reproceso lifecycle (request, reject, request again, approve).
     */
    public function test_reprocesos_complete_workflow(): void
    {
        // 1. Create a base finished OP
        $op = OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-ORIGINAL-TEST',
            'proyecto' => 'Original Project',
            'presupuestista' => 'Test Presup',
            'cliente' => 'Test Client',
            'marca' => 'Test Brand',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
            'estado' => 'Terminado',
        ]);

        // Mock vendor user session
        session([
            'user_role' => 'ventas',
            'user_code' => 'DAFNE-RAMIREZ-PROD-2026',
            'user_name' => 'DAFNE',
        ]);

        // 2. Submit reproceso request
        $response = $this->post("/op/solicitar-reproceso/{$op->id}", [
            'motivo' => 'Error de diseño',
            'descripcion' => 'Necesitamos corregir los colores del branding principal.',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('solicitudes_reproceso', [
            'orden_produccion_id' => $op->id,
            'motivo' => 'Error de diseño',
            'descripcion' => 'Necesitamos corregir los colores del branding principal.',
            'estado' => 'Pendiente',
            'solicitado_por_codigo' => 'DAFNE-RAMIREZ-PROD-2026',
        ]);

        $solicitud = \App\Models\SolicitudReproceso::first();
        $this->assertNotNull($solicitud);

        // Mock admin user session
        session([
            'user_role' => 'admin',
            'user_code' => 'ADMIN-PROD-2026',
            'user_name' => 'ADMINISTRADOR',
        ]);

        // 3. Reject first request
        $response = $this->post("/admin/reproceso/rechazar/{$solicitud->id}", [
            'razon_rechazo' => 'Falta información de contacto del cliente.',
        ]);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertEquals('Rechazado', $solicitud->fresh()->estado);
        $this->assertEquals('Falta información de contacto del cliente.', $solicitud->fresh()->razon_rechazo);

        // Mock vendor user session again
        session([
            'user_role' => 'ventas',
            'user_code' => 'DAFNE-RAMIREZ-PROD-2026',
            'user_name' => 'DAFNE',
        ]);

        // 4. Request again
        $response = $this->post("/op/solicitar-reproceso/{$op->id}", [
            'motivo' => 'Solicitud del cliente',
            'descripcion' => 'Corregido diseño solicitado por cliente.',
        ]);
        $response->assertStatus(200);

        $nuevaSolicitud = \App\Models\SolicitudReproceso::where('estado', 'Pendiente')->first();
        $this->assertNotNull($nuevaSolicitud);

        // Mock admin user session again
        session([
            'user_role' => 'admin',
            'user_code' => 'ADMIN-PROD-2026',
            'user_name' => 'ADMINISTRADOR',
        ]);

        // 5. Approve request
        $response = $this->post("/admin/reproceso/aprobar/{$nuevaSolicitud->id}", [
            'numero_op' => 'OP-ORIGINAL-TEST-R',
            'lider_produccion' => 'Test Leader',
            'estado' => 'Pendiente',
        ]);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertEquals('Aprobado', $nuevaSolicitud->fresh()->estado);

        // Assert new reproceso OP is created
        $reproOP = OrdenProduccion::where('reproceso_de_id', $op->id)->first();
        $this->assertNotNull($reproOP);
        $this->assertEquals('Reprocesos', $reproOP->categoria);
        $this->assertEquals('OP-ORIGINAL-TEST-R', $reproOP->numero_op);
        $this->assertEquals('Original Project (Reproceso)', $reproOP->proyecto);
        $this->assertEquals('Pendiente', $reproOP->estado);
        $this->assertEquals('Test Leader', $reproOP->lider_produccion);

        // 6. Test that admin_branding sees it in query updates
        session([
            'user_role' => 'admin_branding',
            'user_code' => 'ADMIN-BRANDING-2026',
            'user_name' => 'ADMIN BRANDING',
        ]);
        
        $response = $this->get("/op/admin/updates");
        $response->assertStatus(200);
        $data = $response->json();
        
        $ids = collect($data['ordenes'])->pluck('id')->toArray();
        $this->assertTrue(in_array($reproOP->id, $ids));
        
        // 7. Test that admin_promo does NOT see it (since original OP category is Branding)
        session([
            'user_role' => 'admin_promo',
            'user_code' => 'ADMIN-PROMO-2026',
            'user_name' => 'ADMIN PROMO',
        ]);
        
        $response = $this->get("/op/admin/updates");
        $response->assertStatus(200);
        $data = $response->json();
        
        $ids = collect($data['ordenes'])->pluck('id')->toArray();
        $this->assertFalse(in_array($reproOP->id, $ids));
    }

    /**
     * Test OP deletion permissions (soft delete and hard delete).
     */
    public function test_op_deletion_permissions(): void
    {
        $opBranding = OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-DEL-BRAND',
            'proyecto' => 'Branding To Delete',
            'presupuestista' => 'Test Presup',
            'cliente' => 'Test Client',
            'marca' => 'Test Brand',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
        ]);

        $opPromo = OrdenProduccion::create([
            'categoria' => 'Promocional',
            'numero_op' => 'OP-DEL-PROMO',
            'proyecto' => 'Promo To Delete',
            'presupuestista' => 'Test Presup',
            'cliente' => 'Test Client',
            'marca' => 'Test Brand',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
        ]);

        // 1. Non-admin user (ventas) cannot delete (redirects due to access middleware check)
        session([
            'user_role' => 'ventas',
            'user_code' => 'DAFNE-RAMIREZ-PROD-2026',
            'user_name' => 'DAFNE',
        ]);
        $response = $this->post("/op/eliminar/{$opBranding->id}");
        $response->assertStatus(302); // Redirect back due to CheckAccess middleware

        // 2. Admin Branding attempts to delete Branding OP in 'Pendiente' state -> returns 400
        session([
            'user_role' => 'admin_branding',
            'user_code' => 'ADMIN-BRANDING-2026',
            'user_name' => 'ADMIN BRANDING',
        ]);
        $response = $this->postJson("/op/eliminar/{$opBranding->id}");
        $response->assertStatus(400);
        $response->assertJson(['success' => false, 'message' => 'Solo se pueden eliminar órdenes con estado Terminado o Cancelado.']);

        // Set status to Terminado to allow deletion
        $opBranding->update(['estado' => 'Terminado']);
        $opPromo->update(['estado' => 'Cancelado']);

        // Admin Branding can delete Branding OPs (Soft Delete)
        $response = $this->postJson("/op/eliminar/{$opBranding->id}");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertSoftDeleted('orden_produccions', ['id' => $opBranding->id]);

        // Admin Branding CANNOT delete Promocional OPs
        $response = $this->postJson("/op/eliminar/{$opPromo->id}");
        $response->assertStatus(403);
        $response->assertJson(['success' => false]);

        // Admin Branding CANNOT perform hard deletes
        $response = $this->postJson("/op/eliminar/{$opPromo->id}", ['hard_delete' => true]);
        $response->assertStatus(403);

        // 3. Super Admin (Master Admin) can delete anything (soft and hard)
        session([
            'user_role' => 'admin',
            'user_code' => 'ADMIN-PROD-2026',
            'user_name' => 'ADMINISTRADOR',
        ]);
        // Soft delete Promocional OP
        $response = $this->postJson("/op/eliminar/{$opPromo->id}");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertSoftDeleted('orden_produccions', ['id' => $opPromo->id]);

        // Hard delete OP (permanently)
        $opPromo->restore(); // restore to test hard delete
        $response = $this->postJson("/op/eliminar/{$opPromo->id}", ['hard_delete' => true]);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('orden_produccions', ['id' => $opPromo->id]);
    }

    /**
     * Test reproceso parent relationship.
     */
    public function test_reproceso_parent_relation(): void
    {
        $op = OrdenProduccion::create([
            'categoria' => 'Branding',
            'numero_op' => 'OP-PARENT-TEST',
            'proyecto' => 'Parent Project',
            'presupuestista' => 'Test Presup',
            'cliente' => 'Test Client',
            'marca' => 'Test Brand',
            'fecha_entrega' => Carbon::tomorrow()->format('Y-m-d'),
            'hora_entrega' => '12:00:00',
            'entregar_a' => 'Cliente',
            'estado' => 'Terminado',
        ]);

        session([
            'user_role' => 'ventas',
            'user_code' => 'DAFNE-RAMIREZ-PROD-2026',
            'user_name' => 'DAFNE',
        ]);

        $this->post("/op/solicitar-reproceso/{$op->id}", [
            'motivo' => 'Error de diseño',
            'descripcion' => 'Re-run layout.',
        ]);

        $solicitud = \App\Models\SolicitudReproceso::where('estado', 'Pendiente')->first();

        session([
            'user_role' => 'admin',
            'user_code' => 'ADMIN-PROD-2026',
            'user_name' => 'ADMINISTRADOR',
        ]);

        $this->post("/admin/reproceso/aprobar/{$solicitud->id}", [
            'numero_op' => 'OP-PARENT-TEST-R1',
            'lider_produccion' => 'Test Leader',
            'estado' => 'Pendiente',
        ]);

        $reproOP = OrdenProduccion::where('numero_op', 'OP-PARENT-TEST-R1')->first();
        $this->assertNotNull($reproOP);
        $this->assertEquals($op->id, $reproOP->reproceso_de_id);
        $this->assertEquals($op->id, $reproOP->parent_op_id);
        $this->assertEquals('OP-PARENT-TEST', $reproOP->parent->numero_op);
    }
}

