<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SolicitudReproceso;

$solicitudes = SolicitudReproceso::with('ordenProduccion')->get();
echo json_encode($solicitudes->toArray(), JSON_PRETTY_PRINT) . PHP_EOL;
