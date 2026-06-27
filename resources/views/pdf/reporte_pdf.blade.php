<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Órdenes de Producción</title>
    <style>
        @page {
            margin: 50px 40px 60px 40px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333333;
            font-size: 11px;
            line-height: 1.4;
        }
        header {
            position: relative;
            margin-bottom: 25px;
            border-bottom: 2px solid #00D2FF;
            padding-bottom: 15px;
        }
        .header-logo {
            float: left;
            width: 120px;
            height: auto;
        }
        .header-title {
            float: right;
            text-align: right;
        }
        .header-title h1 {
            margin: 0;
            font-size: 18px;
            color: #0c1c2e;
            text-transform: uppercase;
            font-weight: bold;
        }
        .header-title p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 10px;
        }
        .clear {
            clear: both;
        }
        .meta-section {
            margin-bottom: 20px;
            background-color: #f7f9fa;
            border: 1px solid #e1e8ed;
            border-radius: 6px;
            padding: 12px;
        }
        .meta-section table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-section td {
            padding: 4px 8px;
            vertical-align: top;
        }
        .meta-label {
            font-weight: bold;
            color: #555;
            width: 15%;
        }
        .meta-value {
            color: #333;
            width: 35%;
        }
        /* KPIs summary style */
        .kpis-container {
            margin-bottom: 20px;
            width: 100%;
        }
        .kpi-box {
            float: left;
            width: 18%;
            margin-right: 2%;
            background-color: #ffffff;
            border: 1px solid #e1e8ed;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
        }
        .kpi-box.last {
            margin-right: 0;
        }
        .kpi-value {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .kpi-label {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
        }
        .kpi-total { border-left: 3px solid #00D2FF; }
        .kpi-pendiente { border-left: 3px solid #f3a63b; }
        .kpi-proceso { border-left: 3px solid #00d2ff; }
        .kpi-terminado { border-left: 3px solid #2ecc71; }
        .kpi-urgente { border-left: 3px solid #ff3366; background-color: #fff0f3; }
        
        /* Table styles */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .report-table th {
            background-color: #0c1c2e;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 8px 6px;
            font-size: 10px;
            border: 1px solid #0c1c2e;
        }
        .report-table td {
            padding: 8px 6px;
            border: 1px solid #e1e8ed;
            font-size: 9.5px;
        }
        .report-table tr:nth-child(even) td {
            background-color: #f9fbfd;
        }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-Branding { background-color: #e3f2fd; color: #0d47a1; }
        .badge-Promocional { background-color: #fff3e0; color: #e65100; }
        .badge-Pendiente { background-color: #fff9c4; color: #f57f17; }
        .badge-En_proceso { background-color: #e0f7fa; color: #006064; }
        .badge-Terminado { background-color: #e8f5e9; color: #1b5e20; }
        .badge-Cancelado { background-color: #ffebee; color: #b71c1c; }
        .badge-urgente { background-color: #ffebee; color: #ff3366; border: 1px solid #ff3366; }
        
        .footer-page {
            position: fixed;
            bottom: -35px;
            left: 0;
            right: 0;
            height: 30px;
            text-align: center;
            border-top: 1px solid #e1e8ed;
            padding-top: 10px;
            color: #888888;
            font-size: 9px;
        }
        .page-number:after {
            content: counter(page);
        }
    </style>
</head>
<body>

    <header>
        <div class="header-logo">
            @php
                $logoPath = public_path('img/logo-btl.png');
                $logoBase64 = '';
                if (file_exists($logoPath)) {
                    $logoBase64 = base64_encode(file_get_contents($logoPath));
                }
            @endphp
            @if($logoBase64)
                <img src="data:image/png;base64,{{ $logoBase64 }}" style="width: 100px; height: auto;">
            @else
                <div style="font-weight: bold; font-size: 16px; color: #00D2FF; letter-spacing: 1px;">BTL MARKETING</div>
            @endif
        </div>
        <div class="header-title">
            <h1>Reporte de Órdenes</h1>
            <p>BTL Producción • Control Operativo</p>
        </div>
        <div class="clear"></div>
    </header>

    <div class="meta-section">
        <table>
            <tr>
                <td class="meta-label">Usuario:</td>
                <td class="meta-value">{{ $user_name }} ({{ $user_code }})</td>
                <td class="meta-label">Fecha Emisión:</td>
                <td class="meta-value">{{ $fecha_emision }}</td>
            </tr>
            <tr>
                <td class="meta-label">Filtros:</td>
                <td class="meta-value" colspan="3">
                    <strong>Búsqueda:</strong> {{ $filtros['search'] ?: 'Ninguno' }} | 
                    <strong>Estado:</strong> {{ $filtros['status_text'] }} | 
                    <strong>Categoría:</strong> {{ $filtros['category'] ?: 'Todas' }}
                </td>
            </tr>
        </table>
    </div>

    <!-- KPIs Box Summary -->
    <div class="kpis-container">
        <div class="kpi-box kpi-total">
            <div class="kpi-value">{{ $kpis['total'] }}</div>
            <div class="kpi-label">Total OP</div>
        </div>
        <div class="kpi-box kpi-pendiente">
            <div class="kpi-value" style="color: #f3a63b;">{{ $kpis['pendientes'] }}</div>
            <div class="kpi-label">Pendientes</div>
        </div>
        <div class="kpi-box kpi-proceso">
            <div class="kpi-value" style="color: #00D2FF;">{{ $kpis['en_proceso'] }}</div>
            <div class="kpi-label">En Proceso</div>
        </div>
        <div class="kpi-box kpi-terminado">
            <div class="kpi-value" style="color: #2ecc71;">{{ $kpis['terminadas'] }}</div>
            <div class="kpi-label">Terminadas</div>
        </div>
        <div class="kpi-box kpi-urgente last">
            <div class="kpi-value" style="color: #ff3366;">{{ $kpis['urgentes'] }}</div>
            <div class="kpi-label">Urgentes 🔥</div>
        </div>
        <div class="clear"></div>
    </div>

    <!-- Main List Table -->
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 10%;">Número OP</th>
                <th style="width: 10%;">Categoría</th>
                <th style="width: 15%;">Cliente</th>
                <th style="width: 15%;">Marca</th>
                <th style="width: 20%;">Proyecto</th>
                <th style="width: 15%;">Líder</th>
                <th style="width: 10%;">Entrega</th>
                <th style="width: 10%;">Estado / Avance</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ordenes as $o)
                <tr>
                    <td style="font-weight: bold;">
                        {{ $o->numero_op }}
                        @if($o->prioridad === 'URGENTE')
                            <span style="color: #ff3366;">🔥</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-{{ $o->categoria }}">
                            {{ $o->categoria }}
                        </span>
                    </td>
                    <td>{{ $o->cliente }}</td>
                    <td>{{ $o->marca }}</td>
                    <td>{{ $o->proyecto }}</td>
                    <td>{{ $o->lider_produccion ?: 'Sin asignar' }}</td>
                    <td>
                        {{ \Carbon\Carbon::parse($o->fecha_entrega)->format('d/m/Y') }}<br>
                        <small style="color: #666;">{{ \Carbon\Carbon::parse($o->hora_entrega)->format('H:i') }}</small>
                    </td>
                    <td>
                        <span class="badge badge-{{ str_replace(' ', '_', $o->estado) }}">
                            {{ $o->estado }}
                        </span>
                        <br>
                        <small style="font-weight: bold; color: #555;">{{ $o->avance }}%</small>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 25px; color: #999;">
                        No se encontraron órdenes de producción registradas.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer-page">
        BTL Marketing Nicaragua © 2026 • Página <span class="page-number"></span>
    </div>

</body>
</html>
