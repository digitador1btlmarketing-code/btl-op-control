<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Support\Facades\Route;

// Boot the console kernel to initialize Laravel configurations
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Resolve HTTP Kernel
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Global metric counters
$queryCount = 0;
$queryTime = 0.0;
$slowestQuery = null;
$slowestQueryTime = 0.0;
$supabaseCalls = 0;

// Setup Event Listeners
DB::listen(function ($query) use (&$queryCount, &$queryTime, &$slowestQuery, &$slowestQueryTime) {
    $queryCount++;
    $queryTime += $query->time; // time in ms
    if ($query->time > $slowestQueryTime) {
        $slowestQueryTime = $query->time;
        // Interpolate bindings
        $sql = $query->sql;
        foreach ($query->bindings as $binding) {
            $value = is_numeric($binding) ? $binding : "'" . $binding . "'";
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }
        $slowestQuery = $sql;
    }
});

Event::listen(RequestSending::class, function ($event) use (&$supabaseCalls) {
    if (str_contains($event->request->url(), 'supabase')) {
        $supabaseCalls++;
    }
});

// Setup mock session users
$users = [
    'admin' => [
        'rol' => 'admin',
        'codigo' => 'ADMIN-PROD-2026',
        'nombre' => 'ADMINISTRADOR'
    ],
    'admin_branding' => [
        'rol' => 'admin_branding',
        'codigo' => 'ADMIN-BRANDING-2026',
        'nombre' => 'ADMIN BRANDING'
    ],
    'admin_promo' => [
        'rol' => 'admin_promo',
        'codigo' => 'ADMIN-PROMO-2026',
        'nombre' => 'ADMIN PROMO'
    ],
    'ventas' => [
        'rol' => 'ventas',
        'codigo' => 'DAFNE-RAMIREZ-PROD-2026',
        'nombre' => 'DAFNE RAMIREZ'
    ],
    'jefe_ventas' => [
        'rol' => 'jefe_ventas',
        'codigo' => 'JEFERIZO-PROD-2026',
        'nombre' => 'JEFE RIZO'
    ],
    'tv_branding' => [
        'rol' => 'tv_branding',
        'codigo' => 'BRANDING-PROD-2026',
        'nombre' => 'TV BRANDING'
    ],
    'vista' => [
        'rol' => 'vista',
        'codigo' => 'VISTA-PROD-2026',
        'nombre' => 'USUARIO VISTA'
    ]
];

function runRequest($kernel, $method, $uri, $user = null, $data = []) {
    global $queryCount, $queryTime, $slowestQuery, $slowestQueryTime, $supabaseCalls;
    
    // Reset metrics
    $queryCount = 0;
    $queryTime = 0.0;
    $slowestQuery = null;
    $slowestQueryTime = 0.0;
    $supabaseCalls = 0;

    $request = Illuminate\Http\Request::create($uri, $method, $data);
    
    // Boot session
    $session = app('session')->driver();
    $session->flush();
    if ($user) {
        $session->put('user_role', $user['rol']);
        $session->put('user_code', $user['codigo']);
        $session->put('user_name', $user['nombre']);
    }
    $session->save();
    $request->setLaravelSession($session);

    // Measure memory & time
    $memStart = memory_get_usage();
    $timeStart = microtime(true);

    // Capture response or handle route inside transaction to protect DB
    $response = null;
    try {
        DB::transaction(function () use ($kernel, $request, &$response) {
            $response = $kernel->handle($request);
            // Throw exception to always rollback, preventing data modifications in profiling
            throw new \Exception('Rollback simulation');
        });
    } catch (\Exception $e) {
        if ($e->getMessage() !== 'Rollback simulation') {
            throw $e;
        }
    }

    $timeEnd = microtime(true);
    $memEnd = memory_get_usage();

    $totalTime = ($timeEnd - $timeStart) * 1000; // ms
    $memoryUsed = ($memEnd - $memStart) / 1024 / 1024; // MB
    $responseSize = $response ? strlen($response->getContent()) : 0;
    $status = $response ? $response->getStatusCode() : 500;

    return [
        'status' => $status,
        'total_time_ms' => $totalTime,
        'query_count' => $queryCount,
        'query_time_ms' => $queryTime,
        'slowest_query' => $slowestQuery ?: 'N/A',
        'slowest_query_time_ms' => $slowestQueryTime,
        'supabase_calls' => $supabaseCalls,
        'response_size_bytes' => $responseSize,
        'memory_used_mb' => $memoryUsed,
    ];
}

// Find first order ID for detail view / editing profiling
$firstOrder = DB::table('orden_produccions')->first();
$firstOrderId = $firstOrder ? $firstOrder->id : 1;

$scenarios = [
    'login (GET /)' => ['GET', '/', null],
    'dashboard (GET /op/mis-ordenes as ventas)' => ['GET', '/op/mis-ordenes', $users['ventas']],
    'Admin Branding (GET /op/admin as admin_branding)' => ['GET', '/op/admin', $users['admin_branding']],
    'Admin Promo (GET /op/admin as admin_promo)' => ['GET', '/op/admin', $users['admin_promo']],
    'Vendedor Panel (GET /op/mis-ordenes as ventas)' => ['GET', '/op/mis-ordenes', $users['ventas']],
    'Jefe Panel (GET /op/jefe-ventas as jefe_ventas)' => ['GET', '/op/jefe-ventas', $users['jefe_ventas']],
    'Vista Panel (GET /op/vista as vista)' => ['GET', '/op/vista', $users['vista']],
    'listado de OP AJAX (GET /op/admin X-Requested-With as admin)' => ['GET', '/op/admin', $users['admin']], // we can pass X-Requested-With via headers later or query param if ajax check checks query/header
    'detalle de OP PDF (GET /op/exportar/detalle/{id} as admin)' => ['GET', "/op/exportar/detalle/{$firstOrderId}", $users['admin']],
    'edición modal GET (GET /op/editar/{id} as admin)' => ['GET', "/op/editar/{$firstOrderId}", $users['admin']],
    'guardado POST (POST /op/editar/{id} as admin)' => ['POST', "/op/editar/{$firstOrderId}", $users['admin'], [
        'categoria' => 'Branding',
        'numero_op' => 'OP-TEST-EDIT',
        'proyecto' => 'Proyecto Editado Test',
        'presupuestista' => 'Alejandro Ramos',
        'cliente' => 'Tigo',
        'marca' => 'Tigo Business',
        'entregar_a' => 'Cliente',
    ]],
    'filtros AJAX (GET /op/admin?status=Pendiente&category=Branding as admin)' => ['GET', '/op/admin?status=Pendiente&category=Branding', $users['admin']],
    'búsqueda AJAX (GET /op/admin?search=OP-2026 as admin)' => ['GET', '/op/admin?search=OP-2026', $users['admin']],
    'vistas TV (GET /op/tv?categoria=Branding as tv_branding)' => ['GET', '/op/tv?categoria=Branding', $users['tv_branding']],
    'updates polling admin (GET /op/admin/updates as admin)' => ['GET', '/op/admin/updates', $users['admin']],
    'updates polling vendedor (GET /op/mis-ordenes/updates as ventas)' => ['GET', '/op/mis-ordenes/updates', $users['ventas']],
    'updates polling jefe (GET /op/jefe-ventas/updates as jefe_ventas)' => ['GET', '/op/jefe-ventas/updates', $users['jefe_ventas']],
    'updates polling tv (GET /op/tv/updates?categoria=Branding as tv_branding)' => ['GET', '/op/tv/updates?categoria=Branding', $users['tv_branding']],
];

echo "==========================================================================================" . PHP_EOL;
echo "ROUTE DIAGNOSTIC PROFILE RESULTS" . PHP_EOL;
echo "==========================================================================================" . PHP_EOL;

$results = [];
foreach ($scenarios as $name => $scen) {
    list($method, $uri, $user) = $scen;
    $data = isset($scen[3]) ? $scen[3] : [];
    
    // Simulate AJAX header if it's an AJAX route
    if (str_contains($name, 'AJAX') || str_contains($name, 'updates')) {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    } else {
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    try {
        $res = runRequest($kernel, $method, $uri, $user, $data);
        $results[$name] = $res;
        echo sprintf(
            "Route: %-60s | Status: %d | Time: %7.2fms | Queries: %3d | DB Time: %7.2fms | Supabase Storage: %d | Size: %8d B | Mem: %5.2f MB" . PHP_EOL,
            $name,
            $res['status'],
            $res['total_time_ms'],
            $res['query_count'],
            $res['query_time_ms'],
            $res['supabase_calls'],
            $res['response_size_bytes'],
            $res['memory_used_mb']
        );
        if ($res['query_count'] > 0 && $res['slowest_query_time_ms'] > 0.1) {
            echo sprintf("  -> Slowest Query (%7.2fms): %s" . PHP_EOL, $res['slowest_query_time_ms'], substr($res['slowest_query'], 0, 120) . (strlen($res['slowest_query']) > 120 ? '...' : ''));
        }
    } catch (\Exception $e) {
        echo "Error profiling route {$name}: " . $e->getMessage() . PHP_EOL;
    }
}

// Print summary table sorted by total time
uasort($results, function ($a, $b) {
    return $b['total_time_ms'] <=> $a['total_time_ms'];
});

echo PHP_EOL . "==========================================================================================" . PHP_EOL;
echo "TOP 5 SLOWEST ROUTES" . PHP_EOL;
echo "==========================================================================================" . PHP_EOL;
$count = 0;
foreach ($results as $name => $res) {
    if ($count++ >= 5) break;
    echo sprintf(
        "#%d: %s" . PHP_EOL . "    Total Time: %.2fms | DB Queries: %d (%.2fms) | Supabase Calls: %d | Size: %d Bytes" . PHP_EOL . "    Slowest SQL: %s" . PHP_EOL,
        $count,
        $name,
        $res['total_time_ms'],
        $res['query_count'],
        $res['query_time_ms'],
        $res['supabase_calls'],
        $res['response_size_bytes'],
        $res['slowest_query']
    );
}
