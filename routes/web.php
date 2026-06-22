<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrdenProduccionController;
use Illuminate\Support\Facades\Route;

// Authentication routes (No middleware required for showing/submitting login)
Route::get('/', [AuthController::class, 'showLogin'])->name('login');
Route::post('/', [AuthController::class, 'login']);
Route::any('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected Routes
Route::middleware('access:ventas,admin')->group(function () {
    Route::get('/op/nueva', [OrdenProduccionController::class, 'create'])->name('op.create');
    Route::post('/op/nueva', [OrdenProduccionController::class, 'store'])->name('op.store');
});

Route::middleware('access:admin')->group(function () {
    Route::get('/op/admin', [OrdenProduccionController::class, 'admin'])->name('op.admin');
    Route::get('/op/admin/updates', [OrdenProduccionController::class, 'adminUpdates'])->name('op.admin.updates');
    Route::post('/op/admin/update/{id}', [OrdenProduccionController::class, 'updateQuick'])->name('op.update');
    Route::post('/admin/ordenes/reset', [OrdenProduccionController::class, 'reset'])->name('op.reset');
});

Route::middleware('access:tv_branding,tv_promocional,admin')->group(function () {
    Route::get('/op/tv', [OrdenProduccionController::class, 'tv'])->name('op.tv');
    Route::get('/op/tv/updates', [OrdenProduccionController::class, 'tvUpdates'])->name('op.tv.updates');
});

// Temporary debug route for database verification in Render
Route::get('/debug-db', function () {
    try {
        $count = \App\Models\OrdenProduccion::count();
        $orders = \App\Models\OrdenProduccion::all();
        $dbPath = config('database.connections.sqlite.database');
        return response()->json([
            'success' => true,
            'total_records' => $count,
            'database_file' => $dbPath,
            'database_exists' => file_exists($dbPath),
            'records' => $orders
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});
