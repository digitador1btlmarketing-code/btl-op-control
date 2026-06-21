<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OrdenProduccion;

// Clean up old ones if any
OrdenProduccion::where('numero_op', 'OP-TINKER')->delete();

$op = OrdenProduccion::create([
    'categoria' => 'Branding',
    'numero_op' => 'OP-TINKER',
    'proyecto' => 'Tinker',
    'presupuestista' => 'Tinker',
    'cliente' => 'Tinker',
    'marca' => 'Tinker',
    'fecha_entrega' => '2026-06-22',
    'hora_entrega' => '12:00:00',
    'entregar_a' => 'Cliente',
]);

echo 'Memory state: ' . var_export($op->estado, true) . PHP_EOL;
echo 'Memory progress: ' . var_export($op->avance, true) . PHP_EOL;

$dbOp = OrdenProduccion::where('numero_op', 'OP-TINKER')->first();
echo 'Database state: ' . var_export($dbOp->estado, true) . PHP_EOL;
echo 'Database progress: ' . var_export($dbOp->avance, true) . PHP_EOL;

// Clean up
$dbOp->delete();
