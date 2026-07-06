<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SolicitudReproceso;
use App\Models\OrdenProduccion;

// Find an order
$order = OrdenProduccion::first();
if (!$order) {
    echo "No orders found to test!" . PHP_EOL;
    exit;
}

echo "Testing with Order: #{$order->id} ({$order->numero_op}) Category: {$order->categoria}" . PHP_EOL;

// Create a pending request
$sol = SolicitudReproceso::create([
    'orden_produccion_id' => $order->id,
    'motivo' => 'Error de diseño',
    'descripcion' => 'Prueba de tray pendientes',
    'estado' => 'Pendiente',
    'solicitado_por_codigo' => 'TEST-123',
    'solicitado_por_nombre' => 'Test User',
]);

// Test query for admin_promo (Order is Promocional, so should find it)
$userRole = 'admin_promo';
$solQuery = SolicitudReproceso::with('ordenProduccion')->where('estado', 'Pendiente');
if ($userRole === 'admin_branding') {
    $solQuery->whereHas('ordenProduccion', function ($q) {
        $q->where('categoria', 'Branding');
    });
} elseif ($userRole === 'admin_promo') {
    $solQuery->whereHas('ordenProduccion', function ($q) {
        $q->where('categoria', 'Promocional');
    });
}
$results = $solQuery->get();
echo "Found for Admin Promo: " . $results->count() . " requests." . PHP_EOL;

// Test query for admin_branding (Order is Promocional, so should NOT find it)
$userRole = 'admin_branding';
$solQuery = SolicitudReproceso::with('ordenProduccion')->where('estado', 'Pendiente');
if ($userRole === 'admin_branding') {
    $solQuery->whereHas('ordenProduccion', function ($q) {
        $q->where('categoria', 'Branding');
    });
} elseif ($userRole === 'admin_promo') {
    $solQuery->whereHas('ordenProduccion', function ($q) {
        $q->where('categoria', 'Promocional');
    });
}
$results = $solQuery->get();
echo "Found for Admin Branding: " . $results->count() . " requests." . PHP_EOL;

// Clean up
$sol->delete();
