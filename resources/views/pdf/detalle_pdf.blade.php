<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Producción #{{ $orden->numero_op }}</title>
    <style>
        @page {
            margin: 45px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333333;
            font-size: 11px;
            line-height: 1.5;
        }
        header {
            border-bottom: 2px solid #00D2FF;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header-logo {
            float: left;
            width: 120px;
        }
        .header-title {
            float: right;
            text-align: right;
        }
        .header-title h1 {
            margin: 0;
            font-size: 20px;
            color: #0c1c2e;
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
        
        .section-title {
            background-color: #0c1c2e;
            color: #ffffff;
            font-size: 11px;
            font-weight: bold;
            padding: 5px 10px;
            margin-top: 20px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 3px;
        }
        
        /* Grid table for fields */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .info-table td {
            padding: 6px 8px;
            border: 1px solid #e1e8ed;
            vertical-align: top;
        }
        .info-table td.label {
            font-weight: bold;
            color: #555;
            background-color: #f7f9fa;
            width: 25%;
        }
        .info-table td.value {
            color: #333;
            width: 25%;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 9px;
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

        /* History log */
        .history-list {
            margin: 0;
            padding-left: 15px;
            list-style-type: square;
        }
        .history-item {
            margin-bottom: 8px;
            font-size: 9.5px;
        }
        .history-time {
            font-weight: bold;
            color: #00D2FF;
        }
        .history-desc {
            color: #333;
        }
        
        .footer-page {
            position: fixed;
            bottom: -20px;
            left: 0;
            right: 0;
            text-align: center;
            border-top: 1px solid #e1e8ed;
            padding-top: 10px;
            color: #888888;
            font-size: 9px;
        }
    </style>
</head>
<body>

    <header>
        <div class="header-logo">
            @if(file_exists(public_path('img/logo-btl.png')))
                <img src="{{ public_path('img/logo-btl.png') }}" style="width: 100px; height: auto;">
            @else
                <div style="font-weight: bold; font-size: 16px; color: #00D2FF; letter-spacing: 1px;">BTL MARKETING</div>
            @endif
        </div>
        <div class="header-title">
            <h1>Ficha de OP #{{ $orden->numero_op }}</h1>
            <p>Emisión: {{ $fecha_emision }} • Generado por: {{ $user_name }}</p>
        </div>
        <div class="clear"></div>
    </header>

    <div class="section-title">Información General de la OP</div>
    <table class="info-table">
        <tr>
            <td class="label">Número OP:</td>
            <td class="value" style="font-weight: bold; font-size: 12px; color: #0c1c2e;">{{ $orden->numero_op }}</td>
            <td class="label">Categoría:</td>
            <td class="value">
                <span class="badge badge-{{ $orden->categoria }}">
                    {{ $orden->categoria }}
                </span>
            </td>
        </tr>
        <tr>
            <td class="label">Cliente:</td>
            <td class="value">{{ $orden->cliente }}</td>
            <td class="label">Marca:</td>
            <td class="value">{{ $orden->marca }}</td>
        </tr>
        <tr>
            <td class="label">Proyecto:</td>
            <td class="value" colspan="3">{{ $orden->proyecto }}</td>
        </tr>
        <tr>
            <td class="label">Presupuestista:</td>
            <td class="value">{{ $orden->presupuestista }}</td>
            <td class="label">Creado por:</td>
            <td class="value">
                {{ $orden->creado_por_nombre }} <br>
                <small style="color: #666;">Código: {{ $orden->creado_por_codigo }} ({{ strtoupper(str_replace('_', ' ', $orden->creado_por_rol)) }})</small>
            </td>
        </tr>
        <tr>
            <td class="label">Líder Producción:</td>
            <td class="value" style="font-weight: bold; color: #0c1c2e;">{{ $orden->lider_produccion ?: 'No asignado' }}</td>
            <td class="label">Fecha y Hora Entrega:</td>
            <td class="value" style="font-weight: bold;">
                {{ \Carbon\Carbon::parse($orden->fecha_entrega)->format('d/m/Y') }} a las {{ \Carbon\Carbon::parse($orden->hora_entrega)->format('H:i') }} hrs
            </td>
        </tr>
        <tr>
            <td class="label">Estado Actual:</td>
            <td class="value">
                <span class="badge badge-{{ str_replace(' ', '_', $orden->estado) }}">
                    {{ $orden->estado }}
                </span>
            </td>
            <td class="label">Avance Operativo:</td>
            <td class="value" style="font-weight: bold; font-size: 11px;">{{ $orden->avance }}%</td>
        </tr>
        <tr>
            <td class="label">Entregar a:</td>
            <td class="value">{{ $orden->entregar_a }}</td>
            <td class="label">Brief Adjunto:</td>
            <td class="value" style="font-weight: bold;">
                {{ $orden->brief ? 'Sí (Archivo: ' . basename($orden->brief) . ')' : 'No tiene brief' }}
            </td>
        </tr>
    </table>

    @if($orden->entregar_a === 'Instaladores')
        <div class="section-title">Datos de Instalación y Logística</div>
        <table class="info-table">
            <tr>
                <td class="label" style="width: 25%;">Lugar Instalación:</td>
                <td class="value" colspan="3" style="width: 75%;">{{ $orden->lugar_instalacion ?: '-' }}</td>
            </tr>
            <tr>
                <td class="label">Fecha Instalación:</td>
                <td class="value">
                    {{ $orden->fecha_instalacion ? \Carbon\Carbon::parse($orden->fecha_instalacion)->format('d/m/Y') : '-' }} 
                    a las 
                    {{ $orden->hora_instalacion ? \Carbon\Carbon::parse($orden->hora_instalacion)->format('H:i') : '-' }} hrs
                </td>
                <td class="label">Fecha Desinstalación:</td>
                <td class="value">
                    {{ $orden->fecha_desinstalacion ? \Carbon\Carbon::parse($orden->fecha_desinstalacion)->format('d/m/Y') : '-' }} 
                    a las 
                    {{ $orden->hora_desinstalacion ? \Carbon\Carbon::parse($orden->hora_desinstalacion)->format('H:i') : '-' }} hrs
                </td>
            </tr>
        </table>
    @endif

    <div class="section-title">Historial de Eventos de la OP</div>
    @if($orden->historial->count() > 0)
        <ul class="history-list">
            @foreach($orden->historial as $item)
                <li class="history-item">
                    <span class="history-time">[{{ $item->created_at->format('d/m/Y H:i') }}]</span>
                    <span class="history-desc">{{ $item->descripcion }}</span>
                </li>
            @endforeach
        </ul>
    @else
        <p style="color: #888; font-style: italic; margin-left: 10px;">No hay eventos registrados en el historial de esta orden.</p>
    @endif

    <div class="footer-page">
        BTL Marketing Nicaragua © 2026 • Ficha de OP #{{ $orden->numero_op }}
    </div>

</body>
</html>
