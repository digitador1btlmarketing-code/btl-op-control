<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OrdenProduccion;

echo "--- Unique categories in DB ---" . PHP_EOL;
$categories = OrdenProduccion::select('categoria')->distinct()->pluck('categoria')->toArray();
print_r($categories);

echo "--- Reproceso Orders in DB ---" . PHP_EOL;
$reprocesos = OrdenProduccion::whereNotNull('reproceso_de_id')->orWhere('categoria', 'Reprocesos')->orWhere('categoria', 'REPROCESO')->get();
foreach ($reprocesos as $rep) {
    echo "ID: {$rep->id}, Num: {$rep->numero_op}, Cat: {$rep->categoria}, Reproceso de ID: {$rep->reproceso_de_id}" . PHP_EOL;
}
