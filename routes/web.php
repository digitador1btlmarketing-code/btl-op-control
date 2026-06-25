<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrdenProduccionController;
use Illuminate\Support\Facades\Route;

// Authentication routes (No middleware required for showing/submitting login)
Route::get('/', [AuthController::class, 'showLogin'])->name('login');
Route::post('/', [AuthController::class, 'login']);
Route::any('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected Routes
// Creation page allowed for: ventas, jefe_ventas, admin
Route::middleware('access:ventas,jefe_ventas,admin')->group(function () {
    Route::get('/op/nueva', [OrdenProduccionController::class, 'create'])->name('op.create');
    Route::post('/op/nueva', [OrdenProduccionController::class, 'store'])->name('op.store');
});

// Admin Panel and quick updates
Route::middleware('access:admin,admin_branding,admin_promo')->group(function () {
    Route::get('/op/admin', [OrdenProduccionController::class, 'admin'])->name('op.admin');
    Route::get('/op/admin/updates', [OrdenProduccionController::class, 'adminUpdates'])->name('op.admin.updates');
    Route::post('/op/admin/update/{id}', [OrdenProduccionController::class, 'updateQuick'])->name('op.update');
    Route::post('/op/solicitar-cambio-fecha', [OrdenProduccionController::class, 'solicitarCambioFecha'])->name('op.solicitar_cambio_fecha');
});

Route::middleware('access:admin')->group(function () {
    Route::post('/admin/ordenes/reset', [OrdenProduccionController::class, 'reset'])->name('op.reset');
});

// Jefe de Ventas routes
Route::middleware('access:jefe_ventas')->group(function () {
    Route::get('/op/jefe-ventas', [OrdenProduccionController::class, 'jefeVentasPanel'])->name('op.jefe_ventas');
    Route::get('/op/jefe-ventas/updates', [OrdenProduccionController::class, 'jefeVentasUpdates'])->name('op.jefe_ventas.updates');
    
    // Gestión de usuarios de ventas (and other roles by Master Admin)
    Route::post('/jefe/usuarios', [OrdenProduccionController::class, 'storeUsuario'])->name('jefe.usuarios.store');
    Route::post('/jefe/usuarios/update/{id}', [OrdenProduccionController::class, 'updateUsuario'])->name('jefe.usuarios.update');
    Route::post('/jefe/usuarios/toggle/{id}', [OrdenProduccionController::class, 'toggleUsuarioVentas'])->name('jefe.usuarios.toggle');
    Route::post('/jefe/usuarios/delete/{id}', [OrdenProduccionController::class, 'deleteUsuarioVentas'])->name('jefe.usuarios.delete');
    
    // Solicitudes de cambio de fecha
    Route::post('/jefe/cambio-fecha/aprobar/{id}', [OrdenProduccionController::class, 'aprobarCambioFecha'])->name('jefe.cambio_fecha.aprobar');
    Route::post('/jefe/cambio-fecha/rechazar/{id}', [OrdenProduccionController::class, 'rechazarCambioFecha'])->name('jefe.cambio_fecha.rechazar');
});

// Vendedor routes
Route::middleware('access:ventas')->group(function () {
    Route::get('/op/mis-ordenes', [OrdenProduccionController::class, 'misOrdenes'])->name('op.mis_ordenes');
    Route::get('/op/mis-ordenes/updates', [OrdenProduccionController::class, 'misOrdenesUpdates'])->name('op.mis_ordenes.updates');
});

// History log route (shared by all roles)
Route::middleware('access:admin,admin_branding,admin_promo,jefe_ventas,ventas')->group(function () {
    Route::get('/op/historial/{id}', [OrdenProduccionController::class, 'obtenerHistorial'])->name('op.historial');
});

// Export and download routes
Route::middleware('access:admin,admin_branding,admin_promo,jefe_ventas')->group(function () {
    Route::get('/op/exportar/excel', [OrdenProduccionController::class, 'exportarExcel'])->name('op.exportar.excel');
    Route::get('/op/exportar/pdf', [OrdenProduccionController::class, 'exportarPDF'])->name('op.exportar.pdf');
});

Route::middleware('access:admin,admin_branding,admin_promo,jefe_ventas,ventas')->group(function () {
    Route::get('/op/exportar/detalle/{id}', [OrdenProduccionController::class, 'exportarDetallePDF'])->name('op.exportar.detalle');
    Route::get('/op/descargar-brief/{id}', [OrdenProduccionController::class, 'descargarBrief'])->name('op.descargar_brief');
});

Route::middleware('access:tv_branding,tv_promocional,admin,admin_branding,admin_promo')->group(function () {
    Route::get('/op/tv', [OrdenProduccionController::class, 'tv'])->name('op.tv');
    Route::get('/op/tv/updates', [OrdenProduccionController::class, 'tvUpdates'])->name('op.tv.updates');
});

// Temporary debug route for database verification in Render
Route::get('/debug-db', function () {
    try {
        $defaultConnection = config('database.default');
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $envDbConnection = env('DB_CONNECTION');
        $envHost = env('DB_HOST');
        $envDatabase = env('DB_DATABASE');
        
        $count = \App\Models\OrdenProduccion::count();
        $orders = \App\Models\OrdenProduccion::all();
        
        return response()->json([
            'success' => true,
            'connection' => $defaultConnection,
            'driver' => $driver,
            'env_db_connection' => $envDbConnection,
            'host' => $envHost,
            'database' => $envDatabase,
            'total_records' => $count,
            'records' => $orders
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});
