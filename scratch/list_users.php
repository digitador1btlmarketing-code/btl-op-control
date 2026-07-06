<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\UsuarioAcceso;

$usuarios = UsuarioAcceso::all();
foreach ($usuarios as $u) {
    echo "Nombre: {$u->nombre}, Rol: {$u->rol}, Codigo: {$u->codigo}" . PHP_EOL;
}
