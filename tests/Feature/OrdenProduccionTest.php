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
}
